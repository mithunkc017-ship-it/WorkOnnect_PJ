<?php // Job details: offers, chat, progress tracker, payment, review, report
require "config.php";
need();
$u = me();
$id = (int) ($_GET["id"] ?? 0);
$q = $pdo->prepare(
    "SELECT j.*,c.name cname,w.name wname FROM jobs j JOIN users c ON c.id=j.customer_id LEFT JOIN users w ON w.id=j.worker_id WHERE j.id=?",
);
$q->execute([$id]);
$j = $q->fetch();
if (!$j) {
    die("Job not found");
}
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $a = $_POST["a"];
    if (
        $a == "msg" &&
        ($j["customer_id"] == $u["id"] || $j["worker_id"] == $u["id"]) &&
        (trim($_POST["m"]) !== "" || !empty($_FILES["photo"]["name"]))
    ) {
        $ph = "";
        if (
            !empty($_FILES["photo"]["name"]) &&
            ($ii = @getimagesize($_FILES["photo"]["tmp_name"])) &&
            $_FILES["photo"]["size"] < 2000000 &&
            in_array($ii[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG])
        ) {
            $ph =
                "uploads/c" .
                $id .
                "_" .
                bin2hex(random_bytes(6)) .
                ($ii[2] == IMAGETYPE_PNG ? ".png" : ".jpg");
            move_uploaded_file($_FILES["photo"]["tmp_name"], $ph);
        }
        $pdo->prepare(
            "INSERT INTO messages(job_id,sender_id,msg,photo)VALUES(?,?,?,?)",
        )->execute([$id, $u["id"], substr($_POST["m"], 0, 300), $ph]);
        $to =
            $u["id"] == $j["customer_id"] ? $j["worker_id"] : $j["customer_id"];
        if ($to) {
            notify(
                $to,
                'New message on "' . $j["title"] . '"',
                "job.php?id=$id",
            );
        }
    }
    if (
        $a == "respond" &&
        $j["worker_id"] == $u["id"] &&
        $j["status"] == "requested"
    ) {
        $ok = $_POST["r"] == "accept";
        $pdo->prepare("UPDATE jobs SET status=? WHERE id=?")->execute([
            $ok ? "assigned" : "cancelled",
            $id,
        ]);
        notify(
            $j["customer_id"],
            $u["name"] .
                ($ok ? " accepted" : " declined") .
                ' your request "' .
                $j["title"] .
                '"',
            "job.php?id=$id",
        );
    }
    if (
        $a == "confirm" &&
        $j["customer_id"] == $u["id"] &&
        $j["status"] == "completed"
    ) {
        $pdo->prepare("UPDATE jobs SET confirmed=1 WHERE id=?")->execute([$id]);
        notify(
            $j["worker_id"],
            'Customer confirmed the work for "' .
                $j["title"] .
                '"' .
                ($j["paid"]
                    ? " – your payment is released"
                    : " – payment can be made now"),
            "job.php?id=$id",
        );
    }
    if (
        $a == "tip" &&
        $j["customer_id"] == $u["id"] &&
        $j["paid"] &&
        !$j["tip"] &&
        (int) $_POST["tip"] > 0 &&
        (int) $_POST["tip"] <= 5000
    ) {
        $pdo->prepare("UPDATE jobs SET tip=? WHERE id=?")->execute([
            (int) $_POST["tip"],
            $id,
        ]);
        notify(
            $j["worker_id"],
            "You received a ₹" .
                (int) $_POST["tip"] .
                ' tip for "' .
                $j["title"] .
                '"',
            "job.php?id=$id",
        );
        logit("Tip ₹" . (int) $_POST["tip"] . " on job #" . $id);
    }
    if (
        $a == "resched" &&
        in_array($u["id"], [$j["customer_id"], $j["worker_id"]]) &&
        $j["status"] == "assigned" &&
        $_POST["slot"]
    ) {
        $pdo->prepare(
            "UPDATE jobs SET reschedule_slot=?,reschedule_by=? WHERE id=?",
        )->execute([$_POST["slot"], $u["id"], $id]);
        notify(
            $u["id"] == $j["customer_id"] ? $j["worker_id"] : $j["customer_id"],
            'New time requested for "' . $j["title"] . '"',
            "job.php?id=$id",
        );
    }
    if (
        $a == "rr" &&
        $j["reschedule_by"] &&
        $j["reschedule_by"] != $u["id"] &&
        in_array($u["id"], [$j["customer_id"], $j["worker_id"]])
    ) {
        $acc = $_POST["r"] == "accept";
        $pdo->prepare(
            $acc
                ? "UPDATE jobs SET slot=reschedule_slot,reschedule_slot=NULL,reschedule_by=NULL WHERE id=?"
                : "UPDATE jobs SET reschedule_slot=NULL,reschedule_by=NULL WHERE id=?",
        )->execute([$id]);
        notify(
            $j["reschedule_by"],
            "Your time change request was " . ($acc ? "accepted" : "declined"),
            "job.php?id=$id",
        );
    }
    include "job_actions.php";
    if (
        $a == "cancel" &&
        $j["customer_id"] == $u["id"] &&
        in_array($j["status"], ["open", "requested", "assigned"])
    ) {
        $fee = cancelFee($j);
        $ok = true; // free before a worker accepts, otherwise a cancellation fee (policy in terms.php)
        if ($fee > 0) {
            if ($j["paid"]) {
                wallet(
                    $pdo,
                    $u["id"],
                    $j["price"] + $j["tip"] - $fee,
                    "Refund for cancelled job #" .
                        $id .
                        " (cancellation fee Rs" .
                        $fee .
                        " kept)",
                );
                $pdo->prepare(
                    "UPDATE jobs SET status='cancelled',cancel_fee=?,price=?,commission=ROUND(?*0.10),tip=0,escrow=0,confirmed=1 WHERE id=?",
                )->execute([$fee, $fee, $fee, $id]);
            } else {
                $f = $pdo->prepare("SELECT wallet FROM users WHERE id=?");
                $f->execute([$u["id"]]);
                if ((int) $f->fetchColumn() < $fee) {
                    $ok = false;
                } else {
                    wallet(
                        $pdo,
                        $u["id"],
                        -$fee,
                        "Cancellation fee for job #" . $id,
                    );
                    $pdo->prepare(
                        "UPDATE jobs SET status='cancelled',cancel_fee=?,price=?,paid=1,pay_method='Cancellation fee',commission=ROUND(?*0.10),escrow=0,confirmed=1 WHERE id=?",
                    )->execute([$fee, $fee, $fee, $id]);
                }
            }
        } else {
            $pdo->prepare(
                "UPDATE jobs SET status='cancelled' WHERE id=?",
            )->execute([$id]);
        }
        if ($ok) {
            if ($j["worker_id"]) {
                notify(
                    $j["worker_id"],
                    'Job "' .
                        $j["title"] .
                        '" was cancelled' .
                        ($fee
                            ? " - you get a compensation of Rs" .
                                ($fee - round($fee * 0.1))
                            : ""),
                    "job.php?id=$id",
                );
            }
            logit(
                "Cancelled job #" . $id . ($fee ? " (fee Rs" . $fee . ")" : ""),
            );
        } else {
            $_SESSION["flash"] =
                "Not enough wallet balance for the cancellation fee of Rs" .
                $fee .
                ". Please top up your wallet first.";
        }
    }
    if (
        $a == "reject" &&
        $j["customer_id"] == $u["id"] &&
        $j["status"] == "open"
    ) {
        $pdo->prepare(
            "UPDATE offers SET status='rejected' WHERE job_id=? AND worker_id=?",
        )->execute([$id, (int) $_POST["w"]]);
        notify(
            (int) $_POST["w"],
            'Your offer on "' . $j["title"] . '" was declined',
            "job.php?id=$id",
        );
    }
    if (
        $a == "offer" &&
        $u["role"] == "worker" &&
        $j["status"] == "open" &&
        (int) $_POST["price"] > 0 &&
        !rejected($pdo, $id, $u["id"])
    ) {
        $pdo->prepare(
            "REPLACE INTO offers(job_id,worker_id,price,message)VALUES(?,?,?,?)",
        )->execute([$id, $u["id"], (int) $_POST["price"], $_POST["msg"]]);
        notify(
            $j["customer_id"],
            $u["name"] . ' sent an offer on "' . $j["title"] . '"',
            "job.php?id=$id",
        );
    }
    if (
        $a == "accept" &&
        $j["customer_id"] == $u["id"] &&
        $j["status"] == "open"
    ) {
        $pdo->prepare(
            "UPDATE jobs SET worker_id=?,status='assigned',price=(SELECT price FROM offers WHERE job_id=? AND worker_id=?) WHERE id=?",
        )->execute([(int) $_POST["w"], $id, (int) $_POST["w"], $id]);
        notify(
            (int) $_POST["w"],
            'Your offer was accepted for "' . $j["title"] . '"',
            "job.php?id=$id",
        );
    }
    if (
        $a == "progress" &&
        $j["worker_id"] == $u["id"] &&
        in_array($_POST["s"], ["in_progress", "completed"])
    ) {
        $pdo->prepare(
            "UPDATE jobs SET status=?,done_at=IF(?='completed',NOW(),done_at) WHERE id=?",
        )->execute([$_POST["s"], $_POST["s"], $id]);
        notify(
            $j["customer_id"],
            'Job "' .
                $j["title"] .
                '" is now ' .
                str_replace("_", " ", $_POST["s"]),
            "job.php?id=$id",
        );
    }
    header("Location: job.php?id=$id");
    exit();
}
head($j["title"]);
$st = ["open", "assigned", "in_progress", "completed"];
$at = array_search($j["status"], $st);
$isCustomer = $j["customer_id"] == $u["id"];
$isWorker = $j["worker_id"] == $u["id"];
?>
<style>
.jp{--jp-ink:#1b2430;--jp-mute:#667085;--jp-line:#e4e7ec;--jp-soft:#f6f8fa;--jp-accent:#0f766e;--jp-accent-soft:#e6f4f2;--jp-danger:#c62828;--jp-ok:#1a7f4b;max-width:860px;margin:0 auto;color:var(--jp-ink)}
.jp *{box-sizing:border-box}
.jp .jp-card{background:#fff;border:1px solid var(--jp-line);border-radius:14px;padding:24px;margin-bottom:18px;box-shadow:0 1px 2px rgba(16,24,40,.04)}
.jp .jp-head{display:flex;flex-wrap:wrap;align-items:center;gap:10px;margin-bottom:18px}
.jp .jp-head h2{margin:0;font-size:1.5rem;line-height:1.25;letter-spacing:-.01em}
.jp .jp-badge{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:999px;font-size:.8rem;font-weight:600;background:#fdecea;color:var(--jp-danger)}
.jp .jp-badge .mi{font-size:16px}
.jp .jp-badge.err{color:var(--jp-danger)}
.jp .steps{display:flex;gap:6px;margin:0 0 20px;padding:0}
.jp .steps div{flex:1;text-align:center;padding:9px 4px;font-size:.82rem;font-weight:500;color:var(--jp-mute);background:var(--jp-soft);border-radius:8px;border-bottom:3px solid var(--jp-line)}
.jp .steps div.on{color:var(--jp-accent);background:var(--jp-accent-soft);border-bottom-color:var(--jp-accent);font-weight:600}
.jp .jp-meta{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px}
.jp .jp-chip{display:inline-flex;align-items:center;gap:5px;padding:5px 12px;border-radius:999px;background:var(--jp-soft);border:1px solid var(--jp-line);font-size:.88rem}
.jp .jp-chip .mi{font-size:17px;color:var(--jp-accent)}
.jp .jp-details{line-height:1.65;margin:0 0 18px;max-width:70ch}
.jp .jp-map{border-radius:12px;overflow:hidden;border:1px solid var(--jp-line);margin-bottom:8px}
.jp .jp-map iframe{display:block;width:100%;height:220px;border:0}
.jp a{color:var(--jp-accent)}
.jp .jp-maplink{display:inline-flex;align-items:center;gap:4px;font-size:.9rem;margin-bottom:14px;text-decoration:none;font-weight:500}
.jp img.p{max-width:100%;border-radius:12px;display:block;margin:8px 0 14px}
.jp .jp-posted{display:flex;flex-wrap:wrap;align-items:center;gap:6px 14px;padding:12px 14px;background:var(--jp-soft);border-radius:10px;margin:0 0 18px;font-size:.92rem}
.jp .jp-posted .mi{font-size:18px;vertical-align:middle;color:var(--jp-accent)}
.jp .jp-section{border-top:1px solid var(--jp-line);padding-top:18px;margin-top:18px}
.jp .jp-actions{display:flex;flex-wrap:wrap;gap:10px;align-items:center}
.jp .jp-actions form{margin:0}
.jp .jp-note{display:flex;align-items:center;gap:8px;padding:10px 14px;border-radius:10px;background:var(--jp-accent-soft);margin:12px 0;font-size:.92rem}
.jp .jp-note .mi{font-size:18px;color:var(--jp-accent)}
.jp .jp-note.err{background:#fdecea;color:var(--jp-danger)}
.jp .jp-price{display:flex;flex-wrap:wrap;align-items:center;gap:10px 16px;padding:14px 16px;border:1px solid var(--jp-line);border-radius:12px;margin:14px 0}
.jp .jp-price b{font-size:1.25rem}
.jp .jp-price .ok{color:var(--jp-ok);font-weight:600}
.jp .jp-inline{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin:12px 0}
.jp h3{margin:0 0 12px;font-size:1.05rem;display:flex;align-items:center;gap:6px}
.jp h3 .mi{color:var(--jp-accent)}
.jp input:not([type=hidden]):not([type=file]),.jp select,.jp textarea{padding:10px 12px;border:1px solid #cfd4dc;border-radius:8px;font:inherit;background:#fff;min-width:0}
.jp input:focus,.jp select:focus,.jp textarea:focus{outline:2px solid var(--jp-accent);outline-offset:1px;border-color:var(--jp-accent)}
.jp textarea{width:100%;resize:vertical;margin-bottom:10px}
.jp button,.jp .btn{display:inline-flex;align-items:center;gap:6px;padding:10px 16px;border:0;border-radius:8px;background:var(--jp-accent);color:#fff;font:inherit;font-weight:600;font-size:.92rem;cursor:pointer;text-decoration:none;transition:background .15s}
.jp button:hover,.jp .btn:hover{background:#0b5f58}
.jp button:focus-visible,.jp .btn:focus-visible{outline:2px solid var(--jp-ink);outline-offset:2px}
.jp button[style*="#c00"]{background:var(--jp-danger)!important}
.jp button[style*="#c00"]:hover{background:#a11f1f!important}
.jp .mi,.jp .mi-xs{vertical-align:middle}
.jp .jp-rate{display:flex;flex-wrap:wrap;gap:8px;align-items:center}
.jp .jp-rate input{flex:1;min-width:180px}
.jp .jp-stars{color:#f5a623}
.jp .jp-offer{display:grid;gap:10px}
.jp .jp-offer input{width:100%}
.jp #chat{max-height:300px;overflow:auto;background:var(--jp-soft);border:1px solid var(--jp-line);border-radius:12px;padding:12px 14px}
.jp #chat p{margin:0 0 8px;padding:8px 12px;background:#fff;border:1px solid var(--jp-line);border-radius:10px;line-height:1.45;word-break:break-word}
.jp #chat p small{display:block;color:var(--jp-mute);font-size:.74rem;margin-top:2px}
.jp #chat img.p{margin:6px 0 2px}
.jp .jp-chatform{display:flex;flex-wrap:wrap;gap:8px;margin-top:10px}
.jp .jp-chatform input[name=m]{flex:1;min-width:180px}
.jp .jp-chatform input[type=file]{width:auto;max-width:100%;font-size:.85rem;align-self:center}
.jp details.card,.jp details.jp-card{padding:16px 20px}
.jp details summary{cursor:pointer;font-weight:600;display:flex;align-items:center;gap:6px;color:var(--jp-danger)}
.jp details form{margin-top:12px}
@media(max-width:560px){.jp .jp-card{padding:16px;border-radius:12px}.jp .steps div{font-size:.72rem;padding:8px 2px}.jp .jp-head h2{font-size:1.25rem}}
/* hide the top menu on this page, show only a back arrow */
body>header,body>nav,header,nav,.nav,.navbar,.topbar,.top,.menu,.menubar,#nav,#menu,#header{display:none!important}
.jp-back{position:fixed;top:calc(12px + env(safe-area-inset-top,0px));left:12px;z-index:50;width:42px;height:42px;padding:0;border-radius:50%;background:#fff;color:#1b2430;border:1px solid #e4e7ec;box-shadow:0 2px 8px rgba(16,24,40,.12);display:inline-flex;align-items:center;justify-content:center;text-decoration:none}
.jp-back:hover{background:#e6f4f2;color:#0f766e}
.jp-back .material-icons{font-size:24px}
.jp{padding-top:56px}
/* compact chat send row */
.jp .jp-chatform{align-items:center;flex-wrap:nowrap;gap:8px}
.jp .jp-chatform input[name=m]{height:42px;flex:1 1 auto}
.jp .jp-chatform input[type=file]{flex:0 1 110px;width:110px;max-width:110px;font-size:.75rem}
.jp .jp-chatform button{height:42px;padding:0 20px;flex:0 0 auto;justify-content:center}
/* compact report box */
.jp details.jp-report{max-width:380px;margin:0 0 18px;padding:10px 14px;border:1px solid var(--jp-line);border-radius:10px;background:#fff}
.jp details.jp-report summary{font-size:.88rem}
.jp details.jp-report form{margin-top:10px}
.jp details.jp-report textarea{height:60px;min-height:60px;padding:8px 10px;font-size:.88rem;margin-bottom:8px}
.jp details.jp-report button{padding:7px 14px;font-size:.85rem}
@media(max-width:560px){.jp .jp-chatform{flex-wrap:wrap}.jp .jp-chatform input[name=m]{flex:1 1 100%}.jp .jp-chatform input[type=file]{flex:1 1 auto;width:auto;max-width:none}.jp details.jp-report{max-width:100%}}
</style>
<div class="jp">
<a class="jp-back" href="javascript:history.back()" aria-label="Go back" title="Back"><span class="material-icons">arrow_back</span></a>
<div class="jp-card card">
<div class="jp-head"><h2><?= e($j["title"]) ?></h2><?= $j["urgent"]
    ? '<span class="jp-badge err"><span class="material-icons mi">emergency</span> EMERGENCY</span>'
    : "" ?></div>
<div class="steps"><?php foreach ($st as $i => $s) {
    echo '<div class="' .
        ($i <= $at ? "on" : "") .
        '">' .
        ucfirst(str_replace("_", " ", $s)) .
        "</div>";
} ?></div>
<div class="jp-meta">
<span class="jp-chip"><b><?= e($j["category"]) ?></b></span>
<span class="jp-chip"><span class="material-icons mi">place</span> <?= e($j["location"]) ?></span>
<span class="jp-chip">Work: <?= e($j["amount"]) ?></span>
</div>
<p class="jp-details"><?= nl2br(e($j["details"])) ?></p>
<div class="jp-map"><iframe title="map" width="100%" height="220" loading="lazy" src="https://www.google.com/maps?q=<?= urlencode(
    $j["location"],
) ?>&output=embed"></iframe></div>
<a class="jp-maplink" href="https://www.google.com/maps/search/?api=1&query=<?= urlencode(
    $j["location"],
) ?>" target="_blank"><span class="material-icons mi">place</span> Open in Google Maps</a>
<?php if ($j["photo"]) {
    echo '<img class="p" src="' . e($j["photo"]) . '">';
} ?>
<div class="jp-posted"><span>Posted by <b><?= e($j["cname"]) ?></b></span>
<?php if (
    $j["customer_id"] == $u["id"] ||
    ($j["worker_id"] == $u["id"] && $j["status"] != "requested") ||
    $u["role"] == "admin"
) {
    echo '<span><span class="material-icons mi">phone</span> <a href="tel:' .
        e($j["phone"]) .
        '">' .
        e($j["phone"]) .
        "</a></span>";
} else {
    echo '<span><span class="material-icons mi">phone</span> <small>shown after the job is assigned to you</small></span>';
} ?></div>

<div class="jp-actions">
<?php
if ($j["customer_id"] == $u["id"] && $j["status"] == "open") {
    echo '<a class="btn" href="edit_job.php?id=' .
        $id .
        '"><span class="material-icons mi-xs">edit</span> Edit</a> ';
}
if (
    $j["customer_id"] == $u["id"] &&
    in_array($j["status"], ["open", "requested", "assigned"])
) {
    echo '<form method="post" style="display:inline" onsubmit="return confirm(\'Cancel this job?' .
        (cancelFee($j)
            ? " A cancellation fee of Rs" . cancelFee($j) . " will be charged."
            : "") .
        '\')"><input type="hidden" name="a" value="cancel"><button style="background:#c00">Cancel Job</button></form>';
}
?>
</div>
<?php
if ($j["status"] == "cancelled") {
    echo '<p class="jp-note err err"><b>This job was cancelled.</b></p>';
}
?>
<?php
if ($j["status"] == "requested" && $j["worker_id"] == $u["id"]) {
    echo '<form method="post" class="jp-actions"><input type="hidden" name="a" value="respond"><button name="r" value="accept"><span class="material-icons mi-xs">check_circle</span> Accept Request</button> <button name="r" value="decline" style="background:#c00">Decline</button></form>';
}
if ($j["status"] == "requested" && $j["customer_id"] == $u["id"]) {
    echo '<p class="jp-note"><span class="material-icons mi-xs">hourglass_empty</span> Waiting for the worker to accept your request.</p>';
}
if (
    $j["customer_id"] == $u["id"] &&
    $j["status"] == "completed" &&
    !$j["confirmed"]
) {
    echo '<form method="post" class="jp-actions"><input type="hidden" name="a" value="confirm"><button><span class="material-icons mi-xs">verified</span> Confirm the work is done</button></form>';
}
if ($j["slot"]) {
    echo '<p class="jp-note"><span class="material-icons mi">calendar_today</span> Preferred time: <b>' .
        e($j["slot"]) .
        "</b></p>";
}
?>
<?php
if (
    $j["status"] == "assigned" &&
    in_array($u["id"], [$j["customer_id"], $j["worker_id"]])
) {
    if ($j["reschedule_by"] && $j["reschedule_by"] != $u["id"]) {
        echo '<p class="jp-note"><span class="material-icons mi-xs">refresh</span> New time requested: <b>' .
            e($j["reschedule_slot"]) .
            '</b></p><form method="post" class="jp-actions"><input type="hidden" name="a" value="rr"><button name="r" value="accept">Accept</button> <button name="r" value="decline" style="background:#c00">Decline</button></form>';
    } elseif ($j["reschedule_by"]) {
        echo '<p class="jp-note"><span class="material-icons mi-xs">refresh</span> Waiting for the other person to answer your time change request.</p>';
    } else {
        echo '<form method="post" class="jp-inline"><input type="hidden" name="a" value="resched"><input type="datetime-local" name="slot" required><button>Request new time</button></form>';
    }
}
if (
    $j["customer_id"] == $u["id"] &&
    $j["paid"] &&
    !$j["tip"] &&
    !$j["refunded"]
) {
    echo '<form method="post" class="jp-inline"><input type="hidden" name="a" value="tip"><input type="number" name="tip" min="1" max="5000" placeholder="Tip ₹" required><button><span class="material-icons mi">volunteer_activism</span> Add tip</button></form>';
}
if (
    $j["customer_id"] == $u["id"] &&
    in_array($j["status"], ["completed", "cancelled"])
) {
    echo '<form method="post" action="repeat.php?id=' .
        $id .
        '" style="display:inline"><button><span class="material-icons mi-xs">refresh</span> Book again</button></form>';
}
?>
<?php include "job_extras.php"; ?>
<?php
if ($j["price"]) {
    echo '<div class="jp-price"><span>Agreed price: <b>₹' .
        $j["price"] .
        "</b></span>" .
        ($j["paid"]
            ? '<span class="ok">Paid via ' .
                e($j["pay_method"]) .
                '</span> <a class="btn" href="invoice.php?id=' .
                $id .
                '"><span class="material-icons mi">receipt</span> Invoice</a>'
            : "<span>Unpaid</span>") .
        ($j["tip"]
            ? '<span><span class="material-icons mi-xs">volunteer_activism</span> Tip ₹' .
                $j["tip"] .
                "</span>"
            : "") .
        ($j["refunded"] ? '<b class="err">Refunded</b>' : "") .
        "</div>";
}
if (
    $j["customer_id"] == $u["id"] &&
    !$j["paid"] &&
    $j["confirmed"] &&
    !$j["refunded"]
) {
    echo '<a class="btn" href="pay.php?id=' . $id . '">Pay Now</a>';
}
?>
<?php if ($j["customer_id"] == $u["id"] && $j["status"] == "completed") {
    $rv = $pdo->prepare("SELECT stars FROM reviews WHERE job_id=?");
    $rv->execute([$id]);
    $rv = $rv->fetchColumn();
    echo $rv
        ? '<p class="jp-stars">Your rating: ' .
            str_repeat('<span class="material-icons mi-xs">star</span>', $rv) .
            "</p>"
        : '<form method="post" class="jp-section" action="review.php?id=' .
            $id .
            '"><h3>Rate the worker</h3><div class="jp-rate"><select name="stars"><option value="5">5 – Excellent</option><option value="4">4 – Good</option><option value="3">3 – Okay</option><option value="2">2 – Poor</option><option value="1">1 – Bad</option></select><input name="comment" placeholder="Write a short review"><button>Submit Review</button></div></form>';
} ?>
<?php if ($j["wname"]) {
    echo '<p>Assigned to: <b><a href="worker.php?id=' .
        $j["worker_id"] .
        '">' .
        e($j["wname"]) .
        "</a></b></p>";
} ?>
<?php if (
    $u["role"] == "worker" &&
    $j["worker_id"] == $u["id"] &&
    in_array($j["status"], ["assigned", "in_progress"])
) { ?>
<form method="post" class="jp-actions"><input type="hidden" name="a" value="progress"><input type="hidden" name="s" value="<?= $j[
    "status"
] == "assigned"
    ? "in_progress"
    : "completed" ?>">
<button><?= $j["status"] == "assigned"
    ? "Start Work"
    : "Mark Completed" ?></button></form><?php } ?>
<?php if ($u["role"] == "worker" && $j["status"] == "open") { ?>
<form method="post" class="jp-section"><h3>Send Offer</h3><div class="jp-offer"><input type="hidden" name="a" value="offer"><input type="number" name="price" placeholder="Your price (₹)" required>
<input name="msg" placeholder="Short message" required><div><button>Send Offer</button></div></div></form><?php } ?></div>
<?php if (
    ($j["customer_id"] == $u["id"] || $j["worker_id"] == $u["id"]) &&
    $j["worker_id"]
) {

    $ms = $pdo->prepare(
        "SELECT m.*,u.name FROM messages m JOIN users u ON u.id=m.sender_id WHERE job_id=? ORDER BY m.id",
    );
    $ms->execute([$id]);
    ?>
<div class="jp-card card"><h3><span class="material-icons mi">chat</span> Job Chat</h3><div id="chat"><?php foreach (
    $ms
    as $m
) {
    echo '<p style="margin:6px 0"><b>' .
        e($m["name"]) .
        ":</b> " .
        e($m["msg"]) .
        ($m["photo"]
            ? '<br><img class="p" style="max-height:140px" src="' .
                e($m["photo"]) .
                '">'
            : "") .
        " <small>" .
        $m["created"] .
        "</small></p>";
} ?></div>
<form method="post" enctype="multipart/form-data" class="jp-chatform"><input type="hidden" name="a" value="msg"><input name="m" placeholder="Type a message"><input type="file" name="photo" accept="image/*"><button>Send</button></form></div>
<script>chat.scrollTop=1e9;setInterval(async()=>{let r=await fetch("msgs.php?id=<?= $id ?>");chat.innerHTML=await r.text();chat.scrollTop=1e9},4000)</script><?php
} ?>
<?php
if ($j["customer_id"] == $u["id"] || $u["role"] == "admin") {
    echo '<div class="jp-card card"><h3>Offers</h3>';
    include "offer_compare.php";
    echo "</div>";
}
if (
    ($j["customer_id"] == $u["id"] || $j["worker_id"] == $u["id"]) &&
    $j["worker_id"]
) { ?><details class="jp-report"><summary><span class="material-icons mi">flag</span> Report a problem</summary><form method="post" action="report.php?id=<?= $id ?>"><textarea name="msg" rows="2" required placeholder="Describe the problem"></textarea><button>Send Report</button></form></details><?php }
?>
</div>
<?php
foot();
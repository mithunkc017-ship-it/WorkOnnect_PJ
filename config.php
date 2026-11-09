<?php
// Shared setup: database, session, helper functions, page header/footer
session_start();
require_once __DIR__ . "/settings.php";
if (!defined("PLATFORM_UPI")) {
    define("PLATFORM_UPI", "yourname@upi");
} // the platform's UPI ID that receives customer payments (QR). Override it in settings.php
if (!defined("PLATFORM_NAME")) {
    define("PLATFORM_NAME", "Workonnect");
}
if (!defined("ADMIN_2FA")) {
    define("ADMIN_2FA", true);
} // admin must enter an e-mail code after the password (set false in settings.php to turn off)
$_SESSION["csrf"] = $_SESSION["csrf"] ?? bin2hex(random_bytes(16));
// CSRF protection: every POST must carry the session token (chat.php is a JSON endpoint, exempt)
if (
    $_SERVER["REQUEST_METHOD"] == "POST" &&
    basename($_SERVER["SCRIPT_NAME"]) != "chat.php" &&
    !hash_equals($_SESSION["csrf"], $_POST["csrf"] ?? "")
) {
    die("Invalid request (CSRF check failed). Go back and try again.");
}
// Auto-insert the hidden token into every <form method="post">
ob_start(function ($b) {
    return preg_replace(
        '/(<form[^>]*method="post"[^>]*>)/i',
        '$1<input type="hidden" name="csrf" value="' . $_SESSION["csrf"] . '">',
        $b,
    );
});
// Database: MongoDB (set MONGO_URI / MONGO_DB in settings.php). $pdo keeps its old name: mongo_db.php runs the pages' SQL against MongoDB.
require_once __DIR__ . "/mongo_db.php";
if (!defined("MONGO_URI")) {
    define("MONGO_URI", "mongodb://127.0.0.1:27017");
}
if (!defined("MONGO_DB")) {
    define("MONGO_DB", "workonet");
}
try {
    $mongo = new MongoStore(MONGO_URI, MONGO_DB);
    $pdo = new MongoPDO($mongo);
    if (empty($_SESSION["mongo_ready"])) {
        $mongo->ensureSchema();
        $_SESSION["mongo_ready"] = 1;
    } // first visit of a session: create indexes + default categories (safe to repeat)
} catch (Exception $x) {
    http_response_code(500);
    die(
        "<h3>Cannot connect to MongoDB</h3><p>" .
            htmlspecialchars($x->getMessage()) .
            "</p><p>Start the MongoDB service, check MONGO_URI in settings.php and that the PHP mongodb extension is enabled (see README.txt).</p>"
    );
}
// Refresh the logged-in user from the database on every request (role/verified changes apply immediately)
if (!empty($_SESSION["u"])) {
    $r = $pdo->prepare("SELECT * FROM users WHERE id=?");
    $r->execute([$_SESSION["u"]["id"]]);
    $f = $r->fetch();
    if ($f && !$f["banned"]) {
        $_SESSION["u"] = $f;
    } else {
        unset($_SESSION["u"]);
    }
} // banned users are logged out at once
$CATS = [
    "Electrician",
    "Plumber",
    "Carpenter",
    "Painter",
    "Cleaner",
    "Driver",
    "Mechanic",
    "AC Technician",
];
try {
    $d = $pdo
        ->query("SELECT name FROM categories ORDER BY name")
        ->fetchAll(MongoPDO::FETCH_COLUMN);
    if ($d) {
        $CATS = $d;
    }
} catch (Exception $x) {
} // admin-managed list
function e($s)
{
    return htmlspecialchars($s ?? "");
}
function me()
{
    return $_SESSION["u"] ?? null;
}
function need($role = null)
{
    $u = me();
    if (!$u || ($role && $u["role"] != $role)) {
        header("Location: login.php");
        exit();
    }
}
define("AI_KEY", ""); // paste your Anthropic API key here for real AI replies (leave empty = simple built-in answers)
function notify($uid, $msg, $link = "dashboard.php")
{
    global $pdo;
    $pdo->prepare(
        "INSERT INTO notifications(user_id,msg,link)VALUES(?,?,?)",
    )->execute([$uid, $msg, $link]);
}
function top($r, $n)
{
    return $r >= 4.5 && $n >= 3
        ? '<span class="ok" style="background:#fff3e0;color:#e65100"><span class="material-icons mi-xs">emoji_events</span> Top Rated</span>'
        : "";
}
function rejected($pdo, $jid, $wid)
{
    $s = $pdo->prepare(
        "SELECT 1 FROM offers WHERE job_id=? AND worker_id=? AND status='rejected'",
    );
    $s->execute([$jid, $wid]);
    return (bool) $s->fetch();
}
function wallet($pdo, $uid, $amt, $note)
{
    $pdo->prepare("UPDATE users SET wallet=wallet+? WHERE id=?")->execute([
        $amt,
        $uid,
    ]);
    $pdo->prepare(
        "INSERT INTO wallet_tx(user_id,amount,note)VALUES(?,?,?)",
    )->execute([$uid, $amt, $note]);
}
function logit($act, $uid = null)
{
    global $pdo;
    $s = me();
    $pdo->prepare(
        "INSERT INTO activity_log(user_id,action)VALUES(?,?)",
    )->execute([$uid ?? ($s["id"] ?? null), $act]);
}
function works($w, $slot)
{
    if (!$slot) {
        return true;
    }
    $d = date("D", strtotime($slot));
    $t = date("H:i:s", strtotime($slot));
    if ($w["work_days"] && strpos($w["work_days"], $d) === false) {
        return false;
    }
    if (
        $w["work_from"] &&
        $w["work_to"] &&
        ($t < $w["work_from"] || $t > $w["work_to"])
    ) {
        return false;
    }
    return true;
}
function level($done, $r)
{
    if ($done >= 20 && $r >= 4.5) {
        return '<span class="ok" style="background:#fff8e1;color:#b8860b"><span class="material-icons mi-xs">workspace_premium</span> Gold</span>';
    }
    if ($done >= 10 && $r >= 4) {
        return '<span class="ok" style="background:#eceff1;color:#546e7a"><span class="material-icons mi-xs">military_tech</span> Silver</span>';
    }
    if ($done >= 3) {
        return '<span class="ok" style="background:#fbe9e7;color:#8d4e2b"><span class="material-icons mi-xs">sports</span> Bronze</span>';
    }
    return "";
}
function expireJobs($pdo)
{
    foreach (
        $pdo
            ->query(
                "SELECT id,customer_id,title FROM jobs WHERE status='open' AND created<DATE_SUB(NOW(),INTERVAL 14 DAY)",
            )
            ->fetchAll()
        as $x
    ) {
        // open jobs with no worker for 14 days
        $r = $pdo->prepare(
            "UPDATE jobs SET status='cancelled' WHERE id=? AND status='open'",
        );
        $r->execute([$x["id"]]);
        if ($r->rowCount()) {
            notify(
                $x["customer_id"],
                '<span class="material-icons mi-xs">hourglass_empty</span> "' .
                    $x["title"] .
                    '" expired after 14 days without a worker. You can book it again.',
                "job.php?id=" . $x["id"],
            );
        }
    }
}
function clash($pdo, $wid, $slot, $skip = 0)
{
    if (!$slot || !$wid) {
        return 0;
    }
    $s = $pdo->prepare(
        "SELECT id FROM jobs WHERE worker_id=? AND id<>? AND status IN('assigned','in_progress') AND slot IS NOT NULL AND ABS(TIMESTAMPDIFF(MINUTE,slot,?))<120 LIMIT 1",
    );
    $s->execute([$wid, $skip, $slot]);
    return (int) $s->fetchColumn();
}
function cancelFee($j)
{
    // cancellation policy: free until a worker accepts; then 5% (min Rs20), or 20% (min Rs50) if the visit is within 24 h / the worker is on the way
    if (!$j["worker_id"] || $j["status"] != "assigned") {
        return 0;
    }
    $base = (int) $j["price"];
    $late =
        $j["onway"] || ($j["slot"] && strtotime($j["slot"]) - time() < 86400);
    $fee = max($late ? 50 : 20, (int) round($base * ($late ? 0.2 : 0.05)));
    return $base > 0 ? min($fee, $base) : $fee;
}
function sendReminders($pdo)
{
    foreach (
        [
            ["rem1", "INTERVAL 1 HOUR", "in about 1 hour"],
            ["rem24", "INTERVAL 24 HOUR", "within 24 hours"],
        ]
        as $r
    ) {
        // booked time slots: reminder for both sides
        $s = $pdo
            ->query(
                "SELECT id,title,slot,customer_id,worker_id FROM jobs WHERE status='assigned' AND slot IS NOT NULL AND {$r[0]}=0 AND slot>NOW() AND slot<=DATE_ADD(NOW(),{$r[1]})",
            )
            ->fetchAll();
        foreach ($s as $j) {
            $pdo->prepare(
                "UPDATE jobs SET {$r[0]}=1" .
                    ($r[0] == "rem1" ? ",rem24=1" : "") .
                    " WHERE id=?",
            )->execute([$j["id"]]);
            foreach ([$j["customer_id"], $j["worker_id"]] as $p) {
                if ($p) {
                    notify(
                        $p,
                        '<span class="material-icons mi">alarm</span> Reminder: "' .
                            $j["title"] .
                            '" is booked for ' .
                            date("d M, h:i A", strtotime($j["slot"])) .
                            " (" .
                            $r[2] .
                            ")",
                        "job.php?id=" . $j["id"],
                    );
                }
            }
        }
    }
}
function announceBar()
{
    global $pdo;
    try {
        $a = $pdo
            ->query(
                "SELECT * FROM announcements WHERE active=1 ORDER BY id DESC LIMIT 1",
            )
            ->fetch();
    } catch (Exception $x) {
        return "";
    }
    if (!$a) {
        return "";
    }
    return '<div class="ann" id="ann"><span class="material-icons mi">campaign</span> ' .
        e($a["msg"]) .
        ($a["link"] ? ' <a href="' . e($a["link"]) . '">Learn more</a>' : "") .
        ' <button onclick="localStorage.setItem(\'ann\',\'' .
        $a["id"] .
        '\');this.parentNode.hidden=true" style="padding:0 8px">×</button></div><script>if(localStorage.getItem("ann")==="' .
        $a["id"] .
        '")document.getElementById("ann").hidden=true</script>';
}
function lh($pdo, $uid, $email, $ok, $note)
{
    try {
        $pdo->prepare(
            "INSERT INTO login_history(user_id,email,ip,ua,ok,note)VALUES(?,?,?,?,?,?)",
        )->execute([
            $uid,
            substr((string) $email, 0, 100),
            $_SERVER["REMOTE_ADDR"] ?? "",
            substr($_SERVER["HTTP_USER_AGENT"] ?? "", 0, 120),
            $ok ? 1 : 0,
            substr($note, 0, 40),
        ]);
    } catch (Exception $x) {
    }
} // login history
function validUpi($v)
{
    return (bool) preg_match(
        '/^[a-zA-Z0-9._-]{2,64}@[a-zA-Z0-9]{2,32}$/',
        (string) $v,
    );
}
function platformUpi($pdo)
{
    // the UPI ID in the ADMIN profile receives the platform fee / QR payments (settings.php PLATFORM_UPI is only the fallback)
    $s = $pdo
        ->query(
            "SELECT upi_id FROM users WHERE role='admin' AND upi_id IS NOT NULL AND upi_id<>'' ORDER BY id LIMIT 1",
        )
        ->fetchColumn();
    return $s ?: PLATFORM_UPI;
}
function finishSplit($pdo, $qid)
{
    // split UPI payment: when the worker AND the admin both confirmed their money, the job becomes paid (and the worker share already settled)
    $s = $pdo->prepare(
        "SELECT * FROM qr_payments WHERE id=? AND mode='split' AND status='submitted' AND worker_ok=1 AND fee_ok=1",
    );
    $s->execute([$qid]);
    $q = $s->fetch();
    if (!$q) {
        return "";
    }
    $j = $pdo->prepare("SELECT * FROM jobs WHERE id=?");
    $j->execute([$q["job_id"]]);
    $j = $j->fetch();
    $c = $pdo->prepare("SELECT * FROM users WHERE id=?");
    $c->execute([$q["user_id"]]);
    $c = $c->fetch();
    if ($j["price"] - $q["discount"] != $q["amount"]) {
        return "The job price changed after this payment was created. Reject it and ask the customer to pay again.";
    }
    $err = finish_payment(
        $pdo,
        $c,
        $j,
        "UPI split",
        $q["amount"],
        $q["discount"],
        $q["coupon"],
        substr($q["fee_utr"] . "/" . $q["worker_utr"], 0, 40),
    );
    if (!$err) {
        $pdo->prepare(
            "UPDATE qr_payments SET status='approved' WHERE id=?",
        )->execute([$q["id"]]);
        $pdo->prepare(
            "UPDATE jobs SET settled=1,settled_at=NOW(),settle_ref=? WHERE id=?",
        )->execute([$q["worker_utr"], $q["job_id"]]);
        notify(
            $q["user_id"],
            '<span class="material-icons mi-xs">check_circle</span> Your UPI payment of ₹' .
                $q["amount"] .
                " was confirmed",
            "job.php?id=" . $q["job_id"],
        );
        logit("Split UPI payment #" . $q["id"] . " confirmed");
    }
    return $err;
}
function badge($v)
{
    return $v
        ? '<span class="ok"><span class="material-icons mi-xs">verified</span> Verified</span>'
        : "";
}
// Language switch: ?lang=kn or ?lang=en is remembered in the session
if (isset($_GET["lang"]) && in_array($_GET["lang"], ["en", "kn"])) {
    $_SESSION["lang"] = $_GET["lang"];
}
if (($_SESSION["lang"] ?? "en") == "kn") {
    ob_start(function ($b) {
        foreach (headers_list() as $h) {
            if (preg_match("/json|csv|Content-Disposition/i", $h)) {
                return $b;
            }
        }
        return kn_translate($b);
    });
}
function t($s)
{
    static $kn = [
        "Dashboard" => "ಡ್ಯಾಶ್‌ಬೋರ್ಡ್",
        "Post Job" => "ಕೆಲಸ ಪೋಸ್ಟ್ ಮಾಡಿ",
        "Logout" => "ಲಾಗ್‌ಔಟ್",
        "Login" => "ಲಾಗಿನ್",
        "Register" => "ನೋಂದಣಿ",
        "Find Workers" => "ಕೆಲಸಗಾರರನ್ನು ಹುಡುಕಿ",
        "Find Skilled Workers Near You" =>
            "ನಿಮ್ಮ ಹತ್ತಿರದ ನುರಿತ ಕೆಲಸಗಾರರನ್ನು ಹುಡುಕಿ",
        "Post a job, get offers, track progress – all in one place." =>
            "ಕೆಲಸ ಪೋಸ್ಟ್ ಮಾಡಿ, ಆಫರ್ ಪಡೆಯಿರಿ, ಪ್ರಗತಿ ಟ್ರ್ಯಾಕ್ ಮಾಡಿ – ಎಲ್ಲವೂ ಒಂದೇ ಸ್ಥಳದಲ್ಲಿ.",
        "Post a Job" => "ಕೆಲಸ ಪೋಸ್ಟ್ ಮಾಡಿ",
        "Join as Worker" => "ಕೆಲಸಗಾರರಾಗಿ ಸೇರಿ",
        "Services" => "ಸೇವೆಗಳು",
        "How it works" => "ಇದು ಹೇಗೆ ಕೆಲಸ ಮಾಡುತ್ತದೆ",
        "Verified Workers" => "ಪರಿಶೀಲಿತ ಕೆಲಸಗಾರರು",
        "1. Customer posts a job" => "1. ಗ್ರಾಹಕರು ಕೆಲಸ ಪೋಸ್ಟ್ ಮಾಡುತ್ತಾರೆ",
        "2. Workers send offers" => "2. ಕೆಲಸಗಾರರು ಆಫರ್ ಕಳುಹಿಸುತ್ತಾರೆ",
        "3. Accept the best offer" => "3. ಉತ್ತಮ ಆಫರ್ ಒಪ್ಪಿಕೊಳ್ಳಿ",
        "4. Track job progress" => "4. ಕೆಲಸದ ಪ್ರಗತಿ ಟ್ರ್ಯಾಕ್ ಮಾಡಿ",
    ];
    if (($_SESSION["lang"] ?? "en") != "kn") {
        return $s;
    }
    return $kn[$s] ?? (kn_dict()[$s] ?? $s);
}
function kn_dict()
{
    static $D = null;
    if ($D === null) {
        $src = require __DIR__ . "/lang_kn.php";
        $D = [];
        foreach ($src as $k => $v) {
            $key = preg_replace(
                "/^[\x{2300}-\x{27FF}\x{1F000}-\x{1FAFF}\x{FE0F}\x{200D}\s]+/u",
                "",
                $k,
            );
            $value = preg_replace(
                "/^[\x{2300}-\x{27FF}\x{1F000}-\x{1FAFF}\x{FE0F}\x{200D}\s]+/u",
                "",
                $v,
            );
            if ($key === $k || !array_key_exists($key, $D)) {
                $D[$key] = $value;
            }
        }
    }
    return $D;
}
function kn_translate($html)
{
    $D = kn_dict();
    $tr = function ($s) use ($D) {
        $k = trim($s);
        if ($k === "") {
            return $s;
        }
        if (isset($D[$k])) {
            return str_replace($k, $D[$k], $s);
        }
        if (
            preg_match('/^([^\p{L}\p{N}]+)(.+)$/us', $k, $m) &&
            isset($D[$m[2]])
        ) {
            return str_replace($k, $m[1] . $D[$m[2]], $s);
        }
        return $s;
    };
    $o = preg_replace_callback(
        "/(<[a-zA-Z][^>]*>)([^<>]+)(?=<)/u",
        function ($m) use ($tr) {
            if (
                stripos($m[1], "<option") === 0 &&
                stripos($m[1], "value=") === false
            ) {
                return $m[0];
            }
            if (preg_match("/^<(script|style|textarea)/i", $m[1])) {
                return $m[0];
            }
            return $m[1] . $tr($m[2]);
        },
        $html,
    );
    if ($o !== null) {
        $html = $o;
    }
    $o = preg_replace_callback(
        '/\b(placeholder|title)="([^"]+)"/u',
        function ($m) use ($tr) {
            return $m[1] . '="' . $tr($m[2]) . '"';
        },
        $html,
    );
    return $o ?? $html;
}
function send_mail($to, $subj, $body, $log = true)
{
    if ($log) {
        @file_put_contents(
            __DIR__ . "/mail_log.txt",
            date("c") . " TO $to | $subj | $body\n",
            FILE_APPEND,
        );
    }
    if (SMTP_HOST !== "") {
        return smtp_send($to, $subj, $body);
    }
    return @mail($to, $subj, $body);
}
function smtp_send($to, $subj, $body)
{
    // small SMTP client (Gmail: smtp.gmail.com, port 587 + App Password)
    $ssl = SMTP_PORT == 465;
    $f = @stream_socket_client(
        ($ssl ? "ssl://" : "tcp://") . SMTP_HOST . ":" . SMTP_PORT,
        $en,
        $es,
        15,
    );
    if (!$f) {
        return false;
    }
    $cm = function ($c, $code) use ($f) {
        if ($c !== null) {
            fwrite($f, $c . "\r\n");
        }
        $r = "";
        while ($l = fgets($f, 515)) {
            $r .= $l;
            if (substr($l, 3, 1) == " ") {
                break;
            }
        }
        return strpos($r, (string) $code) === 0;
    };
    $ok = $cm(null, 220) && $cm("EHLO Workonnect", 250);
    if ($ok && !$ssl) {
        $ok =
            $cm("STARTTLS", 220) &&
            stream_socket_enable_crypto(
                $f,
                true,
                STREAM_CRYPTO_METHOD_TLS_CLIENT,
            ) &&
            $cm("EHLO Workonnect", 250);
    }
    $from = SMTP_FROM ?: SMTP_USER;
    $ok =
        $ok &&
        $cm("AUTH LOGIN", 334) &&
        $cm(base64_encode(SMTP_USER), 334) &&
        $cm(base64_encode(SMTP_PASS), 235) &&
        $cm("MAIL FROM:<$from>", 250) &&
        $cm("RCPT TO:<$to>", 250) &&
        $cm("DATA", 354);
    if ($ok) {
        $m =
            "From: Workonnect <$from>\r\nTo: $to\r\nSubject: =?UTF-8?B?" .
            base64_encode($subj) .
            "?=\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n" .
            preg_replace("/^\./m", "..", $body) .
            "\r\n.";
        $ok = $cm($m, 250);
    }
    @fwrite($f, "QUIT\r\n");
    fclose($f);
    return $ok;
}
function send_otp($pdo, $em)
{
    $o = sprintf("%06d", random_int(0, 999999));
    $pdo->prepare(
        "UPDATE users SET otp=?,otp_expires=DATE_ADD(NOW(),INTERVAL 10 MINUTE),otp_tries=0 WHERE email=?",
    )->execute([hash("sha256", $o), $em]);
    $_SESSION["otp_t"] = time();
    send_mail(
        $em,
        "Your Workonnect verification code",
        "Your Workonnect verification code is " .
            $o .
            ". It is valid for 10 minutes.",
        false, // never write the code to mail_log.txt
    );
}
function finish_payment($pdo, $u, $j, $m, $final, $disc, $code, $ref = "")
{
    // atomic: marks job paid once, takes wallet money, pays referral bonus
    $pdo->beginTransaction();
    try {
        $s = $pdo->prepare(
            "SELECT wallet,referred_by,ref_rewarded FROM users WHERE id=? FOR UPDATE",
        );
        $s->execute([$u["id"]]);
        $w = $s->fetch();
        if ($m == "Wallet" && $w["wallet"] < $final) {
            $pdo->rollBack();
            return "Not enough wallet balance (₹" .
                (int) $w["wallet"] .
                "). Top up your wallet first.";
        }
        $r = $pdo->prepare(
            "UPDATE jobs SET paid=1,pay_method=?,pay_ref=?,discount=?,price=?,commission=ROUND(?*0.10),escrow=IF(confirmed=1,0,1),paid_at=NOW() WHERE id=? AND paid=0",
        );
        $r->execute([$m, $ref, $disc, $final, $final, $j["id"]]);
        if (!$r->rowCount()) {
            $pdo->rollBack();
            return "This job is already paid.";
        }
        if ($code !== "") {
            $pdo->prepare(
                "UPDATE coupons SET used=used+1 WHERE code=?",
            )->execute([$code]);
        }
        if ($m == "Wallet") {
            wallet($pdo, $u["id"], -$final, "Paid for job #" . $j["id"]);
        }
        if ($w["referred_by"] && !$w["ref_rewarded"]) {
            wallet($pdo, $u["id"], 50, "Referral bonus");
            wallet($pdo, $w["referred_by"], 50, "Referral bonus (friend paid)");
            $pdo->prepare(
                "UPDATE users SET ref_rewarded=1 WHERE id=?",
            )->execute([$u["id"]]);
        }
        $pdo->commit();
    } catch (Exception $x) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return "Payment failed, please try again.";
    }
    notify(
        $j["worker_id"],
        '<span class="material-icons mi">payments</span> Payment of ₹' .
            $final .
            ' received for "' .
            $j["title"] .
            '"',
        "job.php?id=" . $j["id"],
    );
    logit("Paid ₹" . $final . " for job #" . $j["id"] . " via " . $m);
    return "";
}
function fld($label, $icon, $html)
{
    return '<label class="lb">' .
        t($label) .
        '</label><div class="fld"><i>' .
        $icon .
        "</i>" .
        $html .
        "</div>";
}
function pwf($label, $name, $ph, $meter = false, $auto = "current-password")
{
    return '<label class="lb">' .
        t($label) .
        '</label><div class="fld"><span class="material-icons mi">lock</span><input type="password" name="' .
        $name .
        '" placeholder="' .
        e($ph) .
        '" autocomplete="' .
        $auto .
        '" minlength="6" required' .
        ($meter ? " data-meter" : "") .
        '><button type="button" class="eye" data-s="' .
        e(t("Show")) .
        '" data-h="' .
        e(t("Hide")) .
        '">' .
        t("Show") .
        "</button></div>" .
        ($meter
            ? '<div class="meter"><i></i><i></i><i></i><i></i></div><small id="pwl"></small>'
            : "");
}
function auth_open($eyebrow, $title, $sub = "")
{
    echo '<style>body>nav,body>footer{display:none}</style><div class="auth"><form class="authcard" method="post"><span class="mark">W</span><p class="eyebrow">' .
        e(t($eyebrow)) .
        "</p><h1>" .
        e(t($title)) .
        "</h1>" .
        ($sub ? '<p class="sub">' . e(t($sub)) . "</p>" : "");
}
function auth_close($alt = "")
{
    $L = [
        t("Weak password"),
        t("Fair password"),
        t("Strong password"),
        t("Very strong password"),
    ];
    echo ($alt
        ? '<div class="or"><span>' .
            t("OR") .
            '</span></div><p class="alt">' .
            $alt .
            "</p>"
        : "") .
        '</form><a class="back" href="index.php">' .
        t("← Back to Workonnect") .
        "</a></div><script>var L=" .
        json_encode($L, JSON_UNESCAPED_UNICODE) .
        ';document.querySelectorAll(".authcard").forEach(function(f){f.addEventListener("submit",function(e){var b=e.submitter;if(b&&b.classList.contains("cta")){b.querySelector("span").textContent=b.dataset.busy;b.querySelector(".go").textContent="…";setTimeout(function(){b.disabled=true},0)}})});document.querySelectorAll(".eye").forEach(function(b){b.onclick=function(){var i=b.closest(".fld").querySelector("input"),s=i.type=="password";i.type=s?"text":"password";b.textContent=s?b.dataset.h:b.dataset.s}});var pw=document.querySelector("input[data-meter]");if(pw){var m=document.querySelectorAll(".meter i"),l=document.getElementById("pwl");pw.oninput=function(){var v=pw.value,n=0;if(v.length>=8)n++;if(/[a-z]/.test(v)&&/[A-Z]/.test(v))n++;if(/\\d/.test(v))n++;if(/[^A-Za-z0-9]/.test(v)||v.length>=12)n++;if(!v)n=0;m.forEach(function(x,k){x.className=k<n?"on s"+n:""});l.textContent=v?(L[n-1]||L[0]):""}}</script>';
}
function head($t)
{
    $u = me();
    $isHome = basename($_SERVER["SCRIPT_NAME"]) == "index.php";
    $q = function ($l) {
        return "?" . http_build_query(array_merge($_GET, ["lang" => $l]));
    };
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' .
        e($t) .
        ' - Workonnect</title><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@400;600&family=Gaegu:wght@700&display=swap" rel="stylesheet"><link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet"><link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet"><link rel="stylesheet" href="style.css?v=' . (int) @filemtime(__DIR__ . "/style.css") . '"><link rel="manifest" href="manifest.json"><meta name="theme-color" content="#1a6b7c"><link rel="icon" href="icons/icon-192.png"><link rel="apple-touch-icon" href="icons/icon-192.png"></head><body><nav><a class="logo" href="index.php">Work<span>onnect</span></a><button class="nvb" type="button" aria-label="Menu" onclick="this.parentNode.classList.toggle(`open`)"><span class="material-icons mi">menu</span></button><div>';
    if ($u) {
        global $pdo;
        $n = $pdo->prepare(
            "SELECT COUNT(*) FROM notifications WHERE user_id=? AND seen=0",
        );
        $n->execute([$u["id"]]);
        $n = $n->fetchColumn();
        echo '<a href="dash.php"><span class="material-icons mi">dashboard</span>' .
            t("Dashboard") .
            '</a><span id="bell" class="bellw"><a href="#" onclick="return tb()"><span class="material-icons mi">notifications</span><span class="nl" style="display:none">Notifications</span><b id="bc"' .
            ($n ? "" : " hidden") .
            ">" .
            $n .
            '</b></a><div id="np" hidden></div></span>';
        if ($u["role"] == "customer") {
            echo '<a href="post_job.php"><span class="material-icons mi">add_circle</span>' .
                t("Post Job") .
                '</a><a href="wallet.php"><span class="material-icons mi">account_balance_wallet</span> Wallet</a>';
        }
        if ($u["role"] == "worker") {
            echo '<a href="open_jobs.php"><span class="material-icons mi">lock_open</span> Open Jobs</a><a href="profile.php"><span class="material-icons mi">person</span>My Profile</a><a href="earnings.php"><span class="material-icons mi">payments</span> Earnings</a><a href="portfolio.php"><span class="material-icons mi">photo_library</span> My Work</a><a href="verify.php"><span class="material-icons mi">verified</span> Get Verified</a>';
        }
        if ($u["role"] == "admin") {
            // echo '<a href="categories.php"><span class="material-icons mi">category</span>Categories</a><a href="complaints.php"><span class="material-icons mi">report</span>Complaints</a><a href="verifications.php"><span class="material-icons mi">fact_check</span>Verifications</a><a href="coupons.php"><span class="material-icons mi">sell</span>Coupons</a><a href="log.php"><span class="material-icons mi">history</span>Activity</a>';
            echo '<a href="dash.php" id="admBtn" onclick="return window.admTog ? admTog() : true"><span class="material-icons mi">apps</span>Admin Tools</a>';
        }
        echo '<a href="account_settings.php"><span class="material-icons mi">settings</span>Settings</a>';
        echo '<a href="logout.php"><span class="material-icons mi">logout</span>' .
            t("Logout") .
            "</a>";
    } else {
        echo '<a href="login.php"><span class="material-icons mi">login</span>' .
            t("Login") .
            '</a><a href="register.php"><span class="material-icons mi">person_add</span>' .
            t("Register") .
            "</a>";
    }
    echo '<a href="workers.php"><span class="material-icons mi">search</span>' .
        t("Find Workers") .
        '</a>' .
        ($isHome
            ? ""
            : '<a href="help.php"><span class="material-icons mi">help</span>Help</a><a href="#" onclick="document.body.classList.toggle(\'dark\');localStorage.d=document.body.classList.contains(\'dark\')?1:\'\';return false"><span class="material-icons mi">dark_mode</span><span class="nl" style="display:none">Dark mode</span></a><a href="' .
                $q("kn") .
                '"><span class="material-icons mi">translate</span>ಕನ್ನಡ</a><a href="' .
                $q("en") .
                '">EN</a>') .
        "</div></nav>" .
        announceBar() .
        "<main>" . adm_side_open();
}
function foot()
{
    $isHome = basename($_SERVER["SCRIPT_NAME"]) == "index.php";
        echo adm_side_close() . '</main><footer>© Workonnect – Final Year BCA Project · <a href="#" id="inst" hidden><span class="material-icons mi">install_mobile</span> Install app</a></footer>';
    if (!$isHome) {
        echo '<style>#cb{position:fixed;right:18px;bottom:18px;z-index:9}#cw{display:none;width:300px;background:#fff;border-radius:10px;box-shadow:0 4px 18px #0004;margin-bottom:8px}#cw header{background:#1a6b7c;color:#fff;padding:10px;border-radius:10px 10px 0 0}#cm{height:240px;overflow:auto;padding:8px;font-size:14px}#cm p{margin:6px 0;padding:6px 10px;border-radius:8px;background:#eef3ff}#cm p.me{background:#fff3e0;text-align:right}#cw input{margin:0;border-radius:0 0 10px 10px}</style>
<div id="cb"><div id="cw"><header><span class="material-icons mi">smart_toy</span> Workonnect Assistant</header><div id="cm"><p>Hi! Ask me how to post a job, send offers, pay or track progress.</p></div><input id="ci" placeholder="Type and press Enter"></div><button onclick="cw.style.display=cw.style.display==\'block\'?\'none\':\'block\'"><span class="material-icons mi">chat</span> Chat</button></div>';
    }
    echo '<script>if("serviceWorker" in navigator)navigator.serviceWorker.register("sw.js");var DP;addEventListener("beforeinstallprompt",function(e){e.preventDefault();DP=e;var i=document.getElementById("inst");i.hidden=false;i.onclick=function(){DP.prompt();return false}});';
    if (!$isHome) {
        echo 'ci.onkeydown=async e=>{if(e.key!="Enter"||!ci.value.trim())return;let t=ci.value;ci.value="";add(t,"me");let r=await fetch("chat.php",{method:"POST",body:JSON.stringify({msg:t})});add((await r.json()).reply)};function add(t,c){let p=document.createElement("p");p.className=c||"";p.textContent=t;cm.appendChild(p);cm.scrollTop=1e9}';
    }
    if (!$isHome) {
        echo "if(localStorage.d)document.body.classList.add('dark');";
    }
    echo '
function tb(){if(window.Notification&&Notification.permission=="default")Notification.requestPermission();var p=document.getElementById("np");p.hidden=!p.hidden;if(!p.hidden&&window.loadN)window.loadN();return false}
if(document.getElementById("bell")){const CS="' .
        $_SESSION["csrf"] .
        '";let LAST=+document.getElementById("bc").textContent;
function toast(i){if(window.Notification&&Notification.permission=="granted"&&document.hidden)new Notification("Workonnect",{body:i.msg,icon:"icons/icon-192.png"});let t=document.createElement("a");t.className="toast";t.href=i.link;t.textContent="<span class="material-icons mi">notifications</span> "+i.msg;document.body.appendChild(t);setTimeout(()=>t.remove(),6000)}
window.loadN=function(){fetch("notif.php").then(r=>r.json()).then(d=>{let c=document.getElementById("bc"),b=document.getElementById("bell"),p=document.getElementById("np");
c.textContent=d.unread;c.hidden=!d.unread;b.classList.toggle("ring",d.unread>0);p.innerHTML="";
let h=document.createElement("div");h.className="nh";h.innerHTML="<b>\'.t(\'Notifications\').\'</b>";let m=document.createElement("a");m.href="#";m.textContent="\'.t(\'Mark all read\').\'";
m.onclick=function(){fetch("notif.php",{method:"POST",body:new URLSearchParams({csrf:CS})}).then(window.loadN);return false};h.appendChild(m);p.appendChild(h);
if(!d.items.length){let e=document.createElement("div");e.className="ni";e.textContent="\'.t(\'No notifications yet\').\'";p.appendChild(e)}
d.items.forEach(i=>{let a=document.createElement("a");a.className="ni"+(i.seen==1?"":" new");a.href=i.link;a.textContent=i.msg;p.appendChild(a)});
let v=document.createElement("a");v.className="nv";v.href="notifications.php";v.textContent="\'.t(\'View all notifications\').\'";p.appendChild(v);
if(d.unread>LAST&&d.items[0])toast(d.items[0]);LAST=d.unread})};
document.addEventListener("click",e=>{if(!document.getElementById("bell").contains(e.target))document.getElementById("np").hidden=true});
window.loadN();setInterval(window.loadN,15000)}</script></body></html>';
}
require_once __DIR__ . "/dash_lib.php";
require_once __DIR__ . "/login_otp.php";
require_once __DIR__ . "/admin_sidebar.php";
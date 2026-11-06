<?php // Admin: confirm QR / UPI payments and see the platform commission. Split payments: the admin confirms the platform fee (the worker confirms their own share).
require "config.php";
need("admin");
$msg = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $s = $pdo->prepare(
        "SELECT * FROM qr_payments WHERE id=? AND status='submitted'",
    );
    $s->execute([(int) $_POST["id"]]);
    $q = $s->fetch();
    if ($q) {
        $r = $_POST["r"] ?? "";
        if ($r == "reject") {
            $pdo->prepare(
                "UPDATE qr_payments SET status='rejected' WHERE id=?",
            )->execute([$q["id"]]);
            notify(
                $q["user_id"],
                "Your UPI payment could not be verified. Check the reference numbers and try again, or contact support.",
                "pay.php?id=" . $q["job_id"],
            );
            logit("Rejected QR payment #" . $q["id"]);
        } elseif ($q["mode"] == "split" && $r == "fee_ok") {
            $pdo->prepare(
                "UPDATE qr_payments SET fee_ok=1 WHERE id=?",
            )->execute([$q["id"]]);
            $msg = finishSplit($pdo, $q["id"]);
            logit("Confirmed platform fee of QR payment #" . $q["id"]);
        } elseif ($q["mode"] != "split" && $r == "approve") {
            $j = $pdo->prepare("SELECT * FROM jobs WHERE id=?");
            $j->execute([$q["job_id"]]);
            $j = $j->fetch();
            $c = $pdo->prepare("SELECT * FROM users WHERE id=?");
            $c->execute([$q["user_id"]]);
            $c = $c->fetch();
            if ($j["price"] - $q["discount"] != $q["amount"]) {
                $msg =
                    "The job price changed after this QR was created. Reject it and ask the customer to pay again.";
            } else {
                $err = finish_payment(
                    $pdo,
                    $c,
                    $j,
                    "UPI QR",
                    $q["amount"],
                    $q["discount"],
                    $q["coupon"],
                    $q["utr"],
                );
                if ($err) {
                    $msg = $err;
                } else {
                    $pdo->prepare(
                        "UPDATE qr_payments SET status='approved' WHERE id=?",
                    )->execute([$q["id"]]);
                    notify(
                        $q["user_id"],
                        '<span class="material-icons mi-xs">check_circle</span> Your QR payment of ₹' .
                            $q["amount"] .
                            " was confirmed",
                        "job.php?id=" . $q["job_id"],
                    );
                    logit("Approved QR payment #" . $q["id"]);
                }
            }
        }
    }
    if (!$msg) {
        header("Location: qr_payments.php");
        exit();
    }
}
$t = $pdo
    ->query(
        "SELECT COALESCE(SUM(price),0) a,COALESCE(SUM(commission),0) c FROM jobs WHERE paid=1 AND refunded=0",
    )
    ->fetch();
head("QR payments");
echo d_top("qr_payments.php");
?><h2><span class="material-icons mi">smartphone</span> QR / UPI Payments</h2><p class="err"><?= e(
    $msg,
) ?></p>
<div class="grid"><div class="card">Total collected<h2>₹<?= (int) $t[
    "a"
] ?></h2></div><div class="card">Platform commission (10%)<h2>₹<?= (int) $t[
    "c"
] ?></h2></div><div class="card">Worker share<h2>₹<?= (int) $t["a"] -
    (int) $t["c"] ?></h2></div></div>
<div style="overflow-x:auto"><table><tr><th>Date</th><th>Job</th><th>Customer</th><th>Amount</th><th>Type / UPI reference</th><th>Status</th><th></th></tr>
<?php foreach (
    $pdo->query(
        "SELECT q.*,u.name FROM qr_payments q JOIN users u ON u.id=q.user_id WHERE q.status<>'waiting' ORDER BY q.status='submitted' DESC,q.id DESC LIMIT 100",
    )
    as $x
) {
    $sp = $x["mode"] == "split";
    echo "<tr><td>" .
        $x["created"] .
        '</td><td><a href="job.php?id=' .
        $x["job_id"] .
        '">#' .
        $x["job_id"] .
        "</a></td><td>" .
        e($x["name"]) .
        "</td><td>₹" .
        $x["amount"] .
        "</td><td>" .
        ($sp
            ? "<b>Split</b> · worker ref " .
                e($x["worker_utr"]) .
                " (" .
                ($x["worker_ok"]
                    ? '<span class="material-icons mi-xs">check_circle</span> confirmed'
                    : '<span class="material-icons mi-xs">hourglass_empty</span>') .
                ") · fee ref " .
                e($x["fee_utr"]) .
                " (" .
                ($x["fee_ok"]
                    ? '<span class="material-icons mi-xs">check_circle</span> confirmed'
                    : '<span class="material-icons mi-xs">hourglass_empty</span>') .
                ")"
            : "Platform QR · " . e($x["utr"])) .
        "</td><td>" .
        $x["status"] .
        "</td><td>";
    if ($x["status"] == "submitted") {
        echo '<form method="post" style="display:flex;gap:6px"><input type="hidden" name="id" value="' .
            $x["id"] .
            '">';
        if ($sp && !$x["fee_ok"]) {
            echo '<button name="r" value="fee_ok">Platform fee received <span class="material-icons mi-xs">check_circle</span></button>';
        } elseif (!$sp) {
            echo '<button name="r" value="approve">Money received – Approve</button>';
        }
        echo '<button name="r" value="reject" style="background:#c00">Reject</button></form>';
    }
    echo "</td></tr>";
} ?></table></div>
<p><small>Check that the UTR and amount match in your bank or UPI app before confirming. Platform QR: the platform keeps the 10% fee and the rest is the worker's share (see Settlements). Split: the customer pays the worker directly and sends the fee to the UPI ID in your admin profile.</small></p><?php foot();

<?php // Payment: UPI, Card, Net Banking, Cash, Wallet (demo) or Razorpay (real checkout, test mode when keys are set in settings.php). 10% platform fee.
require "config.php";
need("customer");
$u = me();
$id = (int) ($_GET["id"] ?? 0);
$err = "";
$q = $pdo->prepare(
    "SELECT * FROM jobs WHERE id=? AND customer_id=? AND (status IN('assigned','in_progress') OR (status='completed' AND confirmed=1)) AND refunded=0",
);
$q->execute([$id, $u["id"]]);
$j = $q->fetch();
if (!$j || !$j["worker_id"] || $j["paid"]) {
    die(
        'Payment is possible once a worker is assigned (held safely in escrow) or after the job is completed and confirmed. <a href="dashboard.php">Back</a>'
    );
}
$rz = RZP_KEY !== "" && RZP_SECRET !== "";
$wupi = $pdo->prepare("SELECT upi_id FROM users WHERE id=?");
$wupi->execute([$j["worker_id"]]);
$wupi = $wupi->fetchColumn();
$co = null;
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST["razorpay_signature"])) {
        // coming back from Razorpay: verify the signature on the server, then finish
        $s = $_SESSION["rzp"] ?? null;
        $pid = $_POST["razorpay_payment_id"] ?? "";
        if (
            $rz &&
            $s &&
            $s["job"] == $id &&
            hash_equals(
                hash_hmac("sha256", $s["order"] . "|" . $pid, RZP_SECRET),
                $_POST["razorpay_signature"],
            )
        ) {
            $err = finish_payment(
                $pdo,
                $u,
                $j,
                "Razorpay",
                $s["final"],
                $s["disc"],
                $s["code"],
                substr($pid, 0, 40),
            );
            unset($_SESSION["rzp"]);
            if (!$err) {
                header("Location: job.php?id=$id");
                exit();
            }
        } else {
            $err =
                "Payment could not be verified. If money was deducted, contact us with your payment id.";
        }
    } else {
        $code = strtoupper(trim($_POST["coupon"] ?? ""));
        $pct = 0;
        $ok = true;
        if ($code !== "") {
            $c = $pdo->prepare(
                "SELECT percent FROM coupons WHERE code=? AND active=1",
            );
            $c->execute([$code]);
            $pct = (int) $c->fetchColumn();
            if (!$pct) {
                $ok = false;
                $err = "Invalid or inactive coupon code.";
            }
        }
        $disc = (int) round(($j["price"] * $pct) / 100);
        $final = $j["price"] - $disc;
        $m = in_array($_POST["method"] ?? "", [
            "UPI",
            "Card",
            "Net Banking",
            "Cash",
            "Wallet",
            "Razorpay",
            "UPI QR",
            "UPI split",
        ])
            ? $_POST["method"]
            : "Cash";
        if ($m == "UPI QR" && platformUpi($pdo) === "") {
            $ok = false;
            $err =
                "QR payment is not set up yet (add PLATFORM_UPI in settings.php).";
        }
        if ($m == "UPI split") {
            if (!$wupi || !validUpi($wupi)) {
                $ok = false;
                $err =
                    "This worker has not added a UPI ID yet. Choose another payment method.";
            } elseif (
                !validUpi(platformUpi($pdo)) ||
                platformUpi($pdo) === "yourname@upi"
            ) {
                $ok = false;
                $err =
                    "The platform UPI ID is not set yet (admin must add it in My UPI ID).";
            } elseif (!($j["status"] == "completed" && $j["confirmed"])) {
                $ok = false;
                $err =
                    "Direct UPI payment is possible after the job is completed and you confirmed it. For earlier payment use escrow (Wallet, QR or Razorpay).";
            }
        }
        if ($m == "Razorpay" && !$rz) {
            $ok = false;
            $err = "Online payment is not set up yet.";
        }
        if ($ok && $m == "Razorpay") {
            $ch = curl_init("https://api.razorpay.com/v1/orders");
            curl_setopt_array($ch, [
                CURLOPT_USERPWD => RZP_KEY . ":" . RZP_SECRET,
                CURLOPT_POST => 1,
                CURLOPT_POSTFIELDS => json_encode([
                    "amount" => $final * 100,
                    "currency" => "INR",
                    "receipt" => "job" . $id,
                ]),
                CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
                CURLOPT_RETURNTRANSFER => 1,
                CURLOPT_TIMEOUT => 15,
            ]);
            $o = json_decode((string) curl_exec($ch), true);
            if (!empty($o["id"])) {
                $_SESSION["rzp"] = [
                    "order" => $o["id"],
                    "job" => $id,
                    "final" => $final,
                    "disc" => $disc,
                    "code" => $code,
                ];
                $co = [
                    "order" => $o["id"],
                    "amt" => $final * 100,
                    "final" => $final,
                ];
            } else {
                $err =
                    "Could not start online payment. Check your Razorpay keys and internet connection.";
            }
        } elseif ($ok && ($m == "UPI QR" || $m == "UPI split")) {
            // QR payment: save the amount, show the QR code (pay_qr.php); the admin confirms after the customer enters the UPI reference
            $pdo->prepare(
                "UPDATE qr_payments SET status='rejected' WHERE job_id=? AND user_id=? AND status='waiting'",
            )->execute([$id, $u["id"]]);
            $pdo->prepare(
                "INSERT INTO qr_payments(job_id,user_id,amount,discount,coupon,mode)VALUES(?,?,?,?,?,?)",
            )->execute([
                $id,
                $u["id"],
                $final,
                $disc,
                $code,
                $m == "UPI split" ? "split" : "platform",
            ]);
            header("Location: pay_qr.php?q=" . $pdo->lastInsertId());
            exit();
        } elseif ($ok) {
            $err = finish_payment($pdo, $u, $j, $m, $final, $disc, $code);
            if (!$err) {
                header("Location: job.php?id=$id");
                exit();
            }
        }
    }
}
head("Payment");
if ($co) {
    $o = [
        "key" => RZP_KEY,
        "amount" => $co["amt"],
        "currency" => "INR",
        "name" => "Workonnect",
        "description" => "Job #" . $id,
        "order_id" => $co["order"],
        "prefill" => [
            "name" => $u["name"],
            "email" => $u["email"],
            "contact" => $u["phone"],
        ],
        "theme" => ["color" => "#0F62FE"],
    ]; ?>
<div class="card"><h2>Pay ₹<?= $co[
    "final"
] ?></h2><p>Opening secure Razorpay checkout…</p><button id="rb">Open payment window</button></div>
<form id="rf" method="post" action="pay.php?id=<?= $id ?>"><input type="hidden" name="razorpay_payment_id" id="rp"><input type="hidden" name="razorpay_signature" id="rs"></form>
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>var o=<?= json_encode(
    $o,
    JSON_HEX_TAG | JSON_HEX_AMP,
) ?>;o.handler=function(r){rp.value=r.razorpay_payment_id;rs.value=r.razorpay_signature;rf.submit()};function go(){new Razorpay(o).open()}rb.onclick=go;go();</script>
<?php
foot();
exit();

}
?>
<form class="card" method="post"><h2>Pay for: <?= e(
    $j["title"],
) ?></h2><h1>₹<?= $j["price"] ?></h1>
<table><tr><td>Job amount</td><td>₹<?= $j[
    "price"
] ?></td></tr><tr><td>Platform fee (10%) – kept by <?= e(
    PLATFORM_NAME,
) ?></td><td>₹<?= (int) round(
    $j["price"] * 0.1,
) ?></td></tr><tr><td><b>Worker receives</b></td><td><b>₹<?= $j["price"] -
    (int) round(
        $j["price"] * 0.1,
    ) ?></b></td></tr></table><small>(before any coupon)</small><?= $j[
    "confirmed"
]
    ? ""
    : '<p class="ok"><span class="material-icons mi">lock</span> Escrow: your money is held safely and released to the worker only after you confirm the work.</p>' ?><p class="err"><?= e(
    $err,
) ?></p>
<select name="method" onchange="f.style.display=(this.value=='Cash'||this.value=='Wallet'||this.value=='Razorpay'||this.value=='UPI QR'||this.value=='UPI split')?'none':'block'"><option value="UPI QR"><span class="material-icons mi">smartphone</span> Scan QR code (any UPI app)</option><?php
if (
    $wupi &&
    validUpi($wupi) &&
    $j["status"] == "completed" &&
    $j["confirmed"]
) {
    echo '<option value="UPI split"><span class="material-icons mi">smartphone</span> Pay worker directly + platform fee (2 QR codes)</option>';
}
if ($rz) {
    echo '<option value="Razorpay">Razorpay (UPI / Card / Net Banking)</option>';
}
?><option>UPI</option><option>Card</option><option>Net Banking</option><option>Cash</option><option>Wallet</option></select>
<div id="f" style="display:none"><input placeholder="UPI ID / Card number (demo – not saved)"></div><small>Wallet balance: ₹<?= (int) $u[
    "wallet"
] ?> (<a href="wallet.php">top up</a>)</small>
<input name="coupon" placeholder="Coupon code (optional)">
<button>Pay Now</button><p><small>10% platform fee is kept from the worker's earnings.<?= $rz
    ? ""
    : " Demo payment for project purposes. Add Razorpay keys in settings.php for real online payments." ?></small></p></form>
<?php foot();

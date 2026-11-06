<?php // Printable invoice for a paid job, with the full fee breakdown (service amount, coupon, platform fee, worker share)
require "config.php";
need();
$u = me();
$id = (int) ($_GET["id"] ?? 0);
$q = $pdo->prepare(
    "SELECT j.*,c.name cn,c.phone cp,w.name wn FROM jobs j JOIN users c ON c.id=j.customer_id JOIN users w ON w.id=j.worker_id WHERE j.id=? AND j.paid=1",
);
$q->execute([$id]);
$j = $q->fetch();
if (
    !$j ||
    (!in_array($u["id"], [$j["customer_id"], $j["worker_id"]]) &&
        $u["role"] != "admin")
) {
    die("Invoice not available.");
}
$orig = $j["price"] + $j["discount"];
$fee = (int) $j["commission"];
$share = $j["price"] - $fee;
$paidOn = $j["paid_at"] ?: $j["created"];
?>
<!DOCTYPE html><html><head><meta charset="utf-8"><title>Invoice <?= $id ?></title><link rel="stylesheet" href="style.css"></head><body>
<main><div class="card" style="max-width:640px;margin:auto"><h1 style="color:#0F62FE">work<span style="color:#FF9800">onet</span></h1>
<h3>INVOICE #INV-<?= str_pad(
    $id,
    5,
    "0",
    STR_PAD_LEFT,
) ?></h3><p>Job date: <?= e($j["created"]) ?> · Paid on: <?= e(
     $paidOn,
 ) ?></p><hr>
<p><b>Customer:</b> <?= e($j["cn"]) ?> (<?= e(
     $j["cp"],
 ) ?>)<br><b>Worker:</b> <?= e($j["wn"]) ?></p>
<table><tr><th>Service</th><th>Location</th><th style="text-align:right">Amount</th></tr>
<tr><td><?= e($j["title"]) ?> (<?= e($j["category"]) ?>)</td><td><?= e(
    $j["location"],
) ?></td><td style="text-align:right">₹<?= $orig ?></td></tr>
<?php if ($j["discount"]) {
    echo '<tr><td colspan="2">Coupon discount</td><td style="text-align:right">− ₹' .
        $j["discount"] .
        "</td></tr>";
} ?>
<tr><td colspan="2">Amount paid for the job</td><td style="text-align:right">₹<?= $j[
    "price"
] ?></td></tr>
<?php if ($j["tip"]) {
    echo '<tr><td colspan="2">Tip for the worker</td><td style="text-align:right">₹' .
        $j["tip"] .
        "</td></tr>";
} ?>
<tr><td colspan="2"><b>Total paid by customer</b></td><td style="text-align:right"><b>₹<?= $j[
    "price"
] + $j["tip"] ?></b></td></tr></table>
<h4>Where the money goes</h4>
<table><tr><td>Platform fee (<?= e(
    PLATFORM_NAME,
) ?>, 10%)</td><td style="text-align:right">₹<?= $fee ?></td></tr><tr><td>Worker receives<?= $j[
    "tip"
]
    ? " (incl. tip ₹" . $j["tip"] . ")"
    : "" ?></td><td style="text-align:right">₹<?= $share +
    $j["tip"] ?></td></tr></table>
<p>Payment method: <b><?= e($j["pay_method"]) ?></b><?= $j["pay_ref"]
    ? " · Reference: <b>" . e($j["pay_ref"]) . "</b>"
    : "" ?> · Status: <span class="ok">PAID</span></p>
<?php if ($u["role"] != "customer") {
    echo "<p>Worker payment: " .
        ($j["settled"]
            ? '<span class="ok">Paid on ' .
                e($j["settled_at"]) .
                " (ref " .
                e($j["settle_ref"]) .
                ")</span>"
            : "Pending") .
        "</p>";
} ?>
<p class="no-print"><button onclick="window.print()"><span class="material-icons mi">print</span> Print / Save as PDF</button> <a href="job.php?id=<?= $id ?>">Back</a></p></div></main>
<style>@media print{.no-print,nav{display:none}}</style></body></html>

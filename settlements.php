<?php // Admin: pay each worker their share (money collected by the platform minus the 10% fee) and record the payment reference
require "config.php";
need("admin");
$msg = "";
$cond =
    "j.paid=1 AND j.refunded=0 AND j.disputed=0 AND (j.escrow=0 OR j.confirmed=1) AND j.settled=0";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $wid = (int) $_POST["w"];
    $ref = trim($_POST["ref"] ?? "");
    $ids = array_filter(array_map("intval", explode(",", $_POST["ids"] ?? "")));
    if ($ref === "" || !$ids) {
        $msg = "Enter the payment reference (UPI ref or note).";
    } else {
        $in = implode(",", $ids);
        $s = $pdo->prepare(
            "SELECT COUNT(*) n,COALESCE(SUM(j.price-j.commission+j.tip),0) a FROM jobs j WHERE j.worker_id=? AND j.id IN($in) AND $cond",
        );
        $s->execute([$wid]);
        $t = $s->fetch();
        if ($t["n"]) {
            $pdo->prepare(
                "UPDATE jobs j SET j.settled=1,j.settled_at=NOW(),j.settle_ref=? WHERE j.worker_id=? AND j.id IN($in) AND $cond",
            )->execute([substr($ref, 0, 40), $wid]);
            notify(
                $wid,
                '<span class="material-icons mi">attach_money</span> ₹' .
                    (int) $t["a"] .
                    " was paid to you for " .
                    $t["n"] .
                    " job(s). Reference: " .
                    $ref,
                "earnings.php",
            );
            logit(
                "Settled ₹" .
                    (int) $t["a"] .
                    " with worker #" .
                    $wid .
                    " (ref " .
                    $ref .
                    ")",
            );
        }
        header("Location: settlements.php");
        exit();
    }
}
$by = [];
foreach (
    $pdo
        ->query(
            "SELECT j.id,j.title,j.worker_id,u.name,u.phone,u.upi_id,(j.price-j.commission+j.tip) amt FROM jobs j JOIN users u ON u.id=j.worker_id WHERE $cond ORDER BY u.name,j.id",
        )
        ->fetchAll()
    as $r
) {
    $by[$r["worker_id"]][] = $r;
}
$pend = 0;
foreach ($by as $L) {
    foreach ($L as $r) {
        $pend += $r["amt"];
    }
}
$done = (int) $pdo
    ->query(
        "SELECT COALESCE(SUM(price-commission+tip),0) FROM jobs WHERE settled=1",
    )
    ->fetchColumn();
head("Settlements");
echo d_top("settlements.php");
?>
<style>
/* Simple settlements styling (scoped to .st) */
.st{--s:#4f46e5}
.st>h2:first-of-type{display:flex;align-items:center;gap:8px;margin:0 0 14px;padding-bottom:10px;border-bottom:3px solid var(--s);animation:sIn .4s ease both}
.st>h2:first-of-type .mi{color:var(--s)}
.st .grid>.card{border-radius:14px;border-left:5px solid var(--s);animation:sIn .45s ease both;transition:transform .25s,box-shadow .25s}
.st .grid>.card:nth-child(1){border-left-color:#f59e0b;animation-delay:.05s}
.st .grid>.card:nth-child(2){border-left-color:#12b76a;animation-delay:.12s}
.st .grid>.card:hover{transform:translateY(-4px);box-shadow:0 10px 24px rgba(0,0,0,.1)}
.st .grid>.card h2{margin:6px 0 0}
.st .wc{border-radius:14px;border-left:5px solid var(--s);animation:sIn .45s ease both;transition:transform .25s,box-shadow .25s}
.st .wc:hover{transform:translateY(-3px);box-shadow:0 10px 24px rgba(0,0,0,.1)}
.st .wc input:not([type=hidden]){flex:1;padding:10px 12px;border:1.5px solid #dcdfe8;border-radius:10px;transition:border-color .2s,box-shadow .2s}
.st .wc input:not([type=hidden]):focus{outline:none;border-color:var(--s);box-shadow:0 0 0 3px rgba(79,70,229,.15)}
.st .btn,.st button{transition:transform .15s,box-shadow .2s,filter .2s}
.st .btn:hover,.st button:hover{transform:translateY(-2px);box-shadow:0 6px 14px rgba(0,0,0,.18);filter:brightness(1.06)}
.st .btn:active,.st button:active{transform:scale(.97)}
.st .uq{margin-top:8px;padding:6px;display:inline-block;background:#fff;border:1px solid #e3e6f0;border-radius:10px}
.st h3{margin:24px 0 10px;padding-left:10px;border-left:4px solid var(--s)}
.st table{width:100%;border-collapse:separate;border-spacing:0;background:#fff;border-radius:12px;overflow:hidden}
.st th{background:var(--s);color:#fff;padding:11px 12px;text-align:left}
.st td{padding:10px 12px;border-bottom:1px solid #eef0f6;transition:background .2s}
.st tr:hover td{background:#f4f3ff}
.st p.err:empty{display:none}
@keyframes sIn{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:none}}
@media (prefers-reduced-motion:reduce){.st *{animation:none!important;transition:none!important}}
</style>
<div class="st"><h2><span class="material-icons mi">attach_money</span> Worker Settlements</h2><p class="err"><?= e(
    $msg,
) ?></p>
<div class="grid"><div class="card">To pay workers<h2>₹<?= $pend ?></h2></div><div class="card">Already paid<h2>₹<?= $done ?></h2></div></div>
<p><small>The platform collects the full amount and keeps the 10% fee. Pay each worker their share (UPI/bank), then record the reference here. Only released money (escrow confirmed, no dispute) is listed.</small></p>
<?php
$i = 0;
foreach ($by as $wid => $L) {
    $sum = array_sum(array_column($L, "amt"));
    echo '<div class="card wc" style="margin:10px 0;animation-delay:' .
        min($i * 0.07, 0.6) .
        's"><b>' .
        e($L[0]["name"]) .
        '</b> · <span class="material-icons mi">phone</span> ' .
        e($L[0]["phone"]) .
        " · <b>₹" .
        $sum .
        "</b> for " .
        count($L) .
        " job(s)<br>" .
        (validUpi($L[0]["upi_id"])
            ? '<span class="material-icons mi">credit_card</span> ' .
                e($L[0]["upi_id"]) .
                ' <a class="btn" href="upi://pay?pa=' .
                rawurlencode($L[0]["upi_id"]) .
                "&pn=" .
                rawurlencode($L[0]["name"]) .
                "&am=" .
                number_format($sum, 2, ".", "") .
                "&cu=INR&tn=" .
                rawurlencode("Workonnect earnings") .
                '">Pay by UPI</a><div class="uq" data-u="upi://pay?pa=' .
                e(rawurlencode($L[0]["upi_id"])) .
                "&amp;pn=" .
                e(rawurlencode($L[0]["name"])) .
                "&amp;am=" .
                number_format($sum, 2, ".", "") .
                '&amp;cu=INR&amp;tn=Workonnect%20earnings" style="width:110px"></div>'
            : '<span class="err">This worker has not added a UPI ID yet.</span>') .
        "<br><small>";
    foreach ($L as $r) {
        echo '<a href="job.php?id=' .
            $r["id"] .
            '">#' .
            $r["id"] .
            " " .
            e($r["title"]) .
            "</a> (₹" .
            $r["amt"] .
            ") · ";
    }
    echo '</small><form method="post" style="display:flex;gap:8px;margin-top:8px"><input type="hidden" name="w" value="' .
        $wid .
        '"><input type="hidden" name="ids" value="' .
        implode(",", array_column($L, "id")) .
        '"><input name="ref" maxlength="40" placeholder="Payment reference (UPI ref / note)" required><button><span class="material-icons mi-xs">check_circle</span> Mark as paid</button></form></div>';
    $i++;
}
if (!$by) {
    echo "<p>Nothing to pay right now.</p>";
}
?>
<h3>Recent settlements</h3><div style="overflow-x:auto"><table><tr><th>Date</th><th>Worker</th><th>Jobs</th><th>Amount</th><th>Reference</th></tr>
<?php foreach (
    $pdo->query(
        "SELECT j.worker_id,u.name,j.settled_at,j.settle_ref,COUNT(*) n,SUM(j.price-j.commission+j.tip) a FROM jobs j JOIN users u ON u.id=j.worker_id WHERE j.settled=1 GROUP BY j.worker_id,u.name,j.settled_at,j.settle_ref ORDER BY j.settled_at DESC LIMIT 30",
    )
    as $x
) {
    echo "<tr><td>" .
        $x["settled_at"] .
        "</td><td>" .
        e($x["name"]) .
        "</td><td>" .
        $x["n"] .
        "</td><td>₹" .
        (int) $x["a"] .
        "</td><td>" .
        e($x["settle_ref"]) .
        "</td></tr>";
} ?></table></div></div><script src="qrcode.js"></script><style>.uq svg{width:110px;height:110px}</style><script>document.querySelectorAll(".uq").forEach(function(d){var q=qrcode(0,"M");q.addData(d.dataset.u);q.make();d.innerHTML=q.createSvgTag(3,0)})</script><?php foot();
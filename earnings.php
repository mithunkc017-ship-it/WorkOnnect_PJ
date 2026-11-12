<?php // Worker earnings dashboard
require "config.php";
need("worker");
$u = me();
function sum($pdo, $sql, $id)
{
    $s = $pdo->prepare($sql);
    $s->execute([$id]);
    return (int) $s->fetchColumn();
}
head("Earnings");
echo d_top("earnings.php");
?>
<style>
.earn{--ac:#FF9800;--ac-d:#E65100;--ac-bg:#FFF3E0;--ink:#1f2937;--mute:#6b7280;--line:#eceef2;--surf:#fff;--ok:#15803d;--ok-bg:#e8f6ee;--wait:#b45309;--wait-bg:#fff4e0;
  max-width:1000px;margin:0 auto;padding:4px 0 32px;color:var(--ink);font-family:Inter,system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}
.earn *{box-sizing:border-box}
.earn h2,.earn h3{margin:0}
.earn .page-title{display:flex;align-items:center;gap:10px;font-size:1.6rem;font-weight:700;letter-spacing:-.02em;margin:8px 0 20px}
.earn .page-title .mi{display:inline-grid;place-items:center;width:40px;height:40px;border-radius:12px;background:var(--ac-bg);color:var(--ac-d);font-size:22px}
.earn .stats{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:18px}
.earn .stat{background:var(--surf);border:1px solid var(--line);border-radius:16px;padding:18px 18px 16px;display:flex;flex-direction:column;gap:8px;box-shadow:0 1px 2px rgba(16,24,40,.04)}
.earn .stat .lbl{font-size:.82rem;color:var(--mute);font-weight:500;line-height:1.3}
.earn .stat .val{font-size:1.7rem;font-weight:700;letter-spacing:-.02em;font-variant-numeric:tabular-nums}
.earn .stat.hero{background:linear-gradient(135deg,#FF9800,#F57C00);border-color:transparent;color:#fff;box-shadow:0 8px 22px rgba(245,124,0,.28)}
.earn .stat.hero .lbl{color:rgba(255,255,255,.88)}
.earn .stat.pending .val{color:var(--wait)}
.earn .panel{background:var(--surf);border:1px solid var(--line);border-radius:16px;padding:20px;margin-bottom:18px;box-shadow:0 1px 2px rgba(16,24,40,.04)}
.earn .panel-title{display:flex;align-items:center;gap:8px;font-size:1.02rem;font-weight:650;margin-bottom:14px}
.earn .panel-title .mi{color:var(--ac-d);font-size:20px}
.earn .bars{display:flex;flex-direction:column;gap:10px}
.earn .bar-row{display:grid;grid-template-columns:84px 1fr 90px;align-items:center;gap:12px;font-size:.9rem}
.earn .bar-row .m{color:var(--mute);font-weight:500}
.earn .bar-row .track{background:#f4f5f8;border-radius:999px;height:12px;overflow:hidden}
.earn .bar-row .fill{display:block;height:100%;border-radius:999px;background:linear-gradient(90deg,#FFB74D,#FF9800)}
.earn .bar-row .amt{text-align:right;font-weight:650;font-variant-numeric:tabular-nums}
.earn .table-wrap{overflow-x:auto;border:1px solid var(--line);border-radius:12px}
.earn table{width:100%;border-collapse:collapse;min-width:520px;background:var(--surf)}
.earn th{text-align:left;font-size:.78rem;font-weight:600;color:var(--mute);background:#fafbfc;padding:12px 16px;border-bottom:1px solid var(--line)}
.earn td{padding:14px 16px;border-bottom:1px solid var(--line);font-size:.92rem;vertical-align:middle}
.earn tr:last-child td{border-bottom:0}
.earn tbody tr:hover td{background:#fffaf2}
.earn td a{color:var(--ink);font-weight:600;text-decoration:none}
.earn td a:hover{color:var(--ac-d);text-decoration:underline}
.earn td.share{font-weight:650;font-variant-numeric:tabular-nums;white-space:nowrap}
.earn .badge{display:inline-flex;align-items:center;gap:6px;padding:5px 11px;border-radius:999px;font-size:.8rem;font-weight:600;line-height:1.3}
.earn .badge.ok{background:var(--ok-bg);color:var(--ok)}
.earn .badge.wait{background:var(--wait-bg);color:var(--wait)}
.earn .badge .mi-xs{font-size:16px}
.earn a:focus-visible{outline:2px solid var(--ac);outline-offset:2px;border-radius:4px}
@media (max-width:820px){.earn .stats{grid-template-columns:repeat(2,1fr)}}
@media (max-width:480px){
  .earn .stats{gap:10px}
  .earn .stat{padding:14px}
  .earn .stat .val{font-size:1.35rem}
  .earn .bar-row{grid-template-columns:70px 1fr 74px;gap:8px;font-size:.82rem}
  .earn .page-title{font-size:1.35rem}
}
@media (prefers-color-scheme:dark){
  .earn{--ink:#eef0f4;--mute:#9aa3b2;--line:#2a2f3a;--surf:#171b23;--ac-bg:#3a2a12;--ok:#4ade80;--ok-bg:#12301f;--wait:#fbbf24;--wait-bg:#3a2d10}
  .earn .bar-row .track{background:#232833}
  .earn th{background:#1c212b}
  .earn tbody tr:hover td{background:#1d222c}
}
</style>

<div class="earn">
<h2 class="page-title"><span class="material-icons mi">payments</span> My Earnings</h2>

<div class="stats">
  <div class="stat hero">
    <span class="lbl">Total earned (after 10% fee)</span>
    <span class="val">₹<?= sum(
        $pdo,
        "SELECT SUM(price-commission+tip) FROM jobs WHERE worker_id=? AND paid=1 AND (escrow=0 OR confirmed=1) AND disputed=0",
        $u["id"],
    ) ?></span>
  </div>
  <div class="stat pending">
    <span class="lbl">Pending payment</span>
    <span class="val">₹<?= sum(
        $pdo,
        "SELECT SUM(price) FROM jobs WHERE worker_id=? AND (paid=0 OR (escrow=1 AND confirmed=0)) AND refunded=0 AND status<>'cancelled'",
        $u["id"],
    ) ?></span>
  </div>
  <div class="stat">
    <span class="lbl">Jobs completed</span>
    <span class="val"><?= sum(
        $pdo,
        "SELECT COUNT(*) FROM jobs WHERE worker_id=? AND status='completed'",
        $u["id"],
    ) ?></span>
  </div>
  <div class="stat">
    <span class="lbl">This month</span>
    <span class="val">₹<?= sum(
        $pdo,
        "SELECT SUM(price-commission+tip) FROM jobs WHERE worker_id=? AND paid=1 AND (escrow=0 OR confirmed=1) AND disputed=0 AND MONTH(created)=MONTH(NOW()) AND YEAR(created)=YEAR(NOW())",
        $u["id"],
    ) ?></span>
  </div>
</div>

<div class="panel">
  <div class="panel-title"><span class="material-icons mi">bar_chart</span> Earnings by month</div>
  <div class="bars">
<?php
$s = $pdo->prepare(
    "SELECT DATE_FORMAT(created,'%b %Y') m,SUM(price-commission+tip) t FROM jobs WHERE worker_id=? AND paid=1 AND (escrow=0 OR confirmed=1) AND disputed=0 GROUP BY m ORDER BY MIN(created) DESC LIMIT 6",
);
$s->execute([$u["id"]]);
foreach ($s as $x) {
    echo '<div class="bar-row"><span class="m">' .
        $x["m"] .
        '</span><span class="track"><span class="fill" style="width:' .
        min(60, $x["t"] / 50) .
        '%"></span></span><span class="amt">₹' .
        $x["t"] .
        "</span></div>";
}
?>
  </div>
</div>

<div class="panel">
  <div class="panel-title"><span class="material-icons mi">attach_money</span> Payment status</div>
  <div class="table-wrap"><table>
    <thead><tr><th>Job</th><th>Your share</th><th>Status</th></tr></thead>
    <tbody>
<?php
$s = $pdo->prepare(
    "SELECT id,title,price-commission+tip amt,settled,settled_at,settle_ref FROM jobs WHERE worker_id=? AND paid=1 AND refunded=0 AND disputed=0 AND (escrow=0 OR confirmed=1) ORDER BY id DESC LIMIT 30",
);
$s->execute([$u["id"]]);
foreach ($s as $x) {
    echo '<tr><td><a href="job.php?id=' .
        $x["id"] .
        '">' .
        e($x["title"]) .
        '</a></td><td class="share">₹' .
        $x["amt"] .
        "</td><td>" .
        ($x["settled"]
            ? '<span class="badge ok"><span class="material-icons mi-xs">check_circle</span> Paid on ' .
                date("d M Y", strtotime($x["settled_at"])) .
                " (ref " .
                e($x["settle_ref"]) .
                ")</span>"
            : '<span class="badge wait"><span class="material-icons mi-xs">hourglass_empty</span> Pending – the admin will pay you</span>') .
        "</td></tr>";
}
?>
    </tbody>
  </table></div>
</div>
</div>
<?php foot();
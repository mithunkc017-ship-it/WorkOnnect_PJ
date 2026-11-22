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
// display only: split-bar percentages
$workerGets = $share + $j["tip"];
$splitTotal = max(1, $fee + $workerGets);
$feePct = round(($fee / $splitTotal) * 100, 1);
$invNo = "INV-" . str_pad($id, 5, "0", STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html lang="en"><head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Invoice <?= $id ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root{--navy:#0a3f9e;--blue:#0a63d6;--sky:#2a9bff;--ink:#0f1f3d;--mute:#5a6b8c;--line:#dbe6f6;--soft:#f3f8ff;--green:#12a150;--bg:#eaf3ff}
*{box-sizing:border-box;margin:0}
body{font:500 15px/1.55 Inter,system-ui,-apple-system,"Segoe UI",sans-serif;color:var(--ink);min-height:100vh;padding:34px 14px 50px;
  background:radial-gradient(circle at 12% 8%,#cfe5ff 0,transparent 30%),radial-gradient(circle at 92% 90%,#d6e9ff 0,transparent 32%),var(--bg);overflow-x:hidden}
body::before,body::after{content:"";position:fixed;z-index:-1;border-radius:50%;filter:blur(70px);opacity:.35;pointer-events:none}
body::before{width:320px;height:320px;left:-90px;top:20%;background:var(--sky);animation:float 14s ease-in-out infinite}
body::after{width:280px;height:280px;right:-80px;bottom:8%;background:#7cc2ff;animation:float 18s ease-in-out infinite reverse}
@keyframes float{50%{transform:translate(30px,-30px) scale(1.1)}}
@keyframes rise{from{opacity:0;transform:translateY(26px) scale(.985)}to{opacity:1;transform:none}}
@keyframes row{from{opacity:0;transform:translateX(-14px)}to{opacity:1;transform:none}}
@keyframes stamp{0%{opacity:0;transform:rotate(-18deg) scale(2.4)}60%{opacity:1;transform:rotate(-8deg) scale(.92)}100%{opacity:1;transform:rotate(-8deg) scale(1)}}
@keyframes fill{from{width:0}}
@keyframes sheen{to{transform:translateX(260%)}}
@keyframes ping{0%{box-shadow:0 0 0 0 rgba(18,161,80,.5)}70%{box-shadow:0 0 0 12px rgba(18,161,80,0)}100%{box-shadow:0 0 0 0 rgba(18,161,80,0)}}

.inv{max-width:680px;margin:0 auto;background:#fff;border-radius:26px;box-shadow:0 30px 70px -28px rgba(10,70,170,.4);overflow:hidden;animation:rise .7s cubic-bezier(.2,.8,.2,1) both}
.head{position:relative;color:#fff;padding:28px 32px 54px;overflow:hidden;background:linear-gradient(120deg,#0a3f9e,#0a63d6 60%,#1e90ff);clip-path:ellipse(140% 100% at 30% 0)}
.head::before{content:"";position:absolute;right:-70px;bottom:-130px;width:330px;height:330px;border-radius:50%;background:rgba(255,255,255,.1)}
.head::after{content:"";position:absolute;top:0;left:-40%;width:35%;height:100%;background:linear-gradient(100deg,transparent,rgba(255,255,255,.18),transparent);transform:translateX(-100%);animation:sheen 3.4s 1s ease-in-out 1 forwards}
.brand{display:flex;align-items:center;gap:10px;font-size:1.7rem;font-weight:800;letter-spacing:-.04em;position:relative;z-index:1}
.brand svg{width:34px;height:28px}.brand b{color:#8fd0ff;font-weight:800}
.head h1{font-size:.8rem;font-weight:700;letter-spacing:.14em;margin-top:22px;opacity:.85;position:relative;z-index:1}
.no{font-size:2rem;font-weight:800;letter-spacing:-.03em;position:relative;z-index:1}
.dates{display:flex;flex-wrap:wrap;gap:8px;margin-top:12px;position:relative;z-index:1}
.dates span{padding:5px 13px;border-radius:99px;background:rgba(255,255,255,.16);border:1px solid rgba(255,255,255,.3);font-size:.8rem;font-weight:600}
.stamp{position:absolute;z-index:2;right:26px;top:26px;padding:8px 18px;border:3px solid #7cf0aa;color:#7cf0aa;border-radius:12px;font-weight:800;font-size:1.25rem;letter-spacing:.14em;
  background:rgba(255,255,255,.06);animation:stamp .6s .7s cubic-bezier(.2,.8,.2,1) both}

.body{padding:6px 32px 30px}
.parties{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin:6px 0 24px}
.party{display:flex;gap:12px;align-items:center;padding:14px 16px;border-radius:16px;background:var(--soft);border:1px solid var(--line);animation:rise .6s both}
.party:nth-child(1){animation-delay:.25s}.party:nth-child(2){animation-delay:.33s}
.av{flex:none;width:44px;height:44px;border-radius:50%;display:grid;place-items:center;color:#fff;font-weight:800;font-size:1.1rem;background:linear-gradient(135deg,#2a9bff,#0a63d6);box-shadow:0 6px 14px -4px rgba(10,99,214,.55)}
.party small{display:block;color:var(--mute);font-weight:600;font-size:.72rem;letter-spacing:.06em;text-transform:uppercase}
.party b{display:block;font-size:1rem;line-height:1.25}.party span{color:var(--mute);font-size:.85rem}

.tw{overflow-x:auto;margin:0 -4px;padding:0 4px}
table{width:100%;border-collapse:collapse}
th{text-align:left;color:var(--mute);font-size:.72rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;padding:10px 8px;border-bottom:2px solid var(--line)}
td{padding:14px 8px;border-bottom:1px solid var(--line)}
.r{text-align:right;white-space:nowrap}
tbody tr{animation:row .5s both;animation-delay:calc(.45s + var(--i)*.08s)}
tbody tr:hover td{background:var(--soft)}
.disc td{color:var(--green);font-weight:600}
tr.sum td{font-weight:600;color:var(--mute)}
tr.total td{border-bottom:0;padding-top:18px}
tr.total td:first-child{font-weight:700}
.big{font-size:1.9rem;font-weight:800;letter-spacing:-.03em;color:var(--blue)}
.pill{display:inline-block;margin-left:8px;padding:2px 10px;border-radius:99px;background:#dcecff;color:var(--blue);font-size:.75rem;font-weight:700;vertical-align:middle}

.tear{position:relative;border-top:2px dashed var(--line);margin:8px -32px 26px}
.tear::before,.tear::after{content:"";position:absolute;top:-14px;width:26px;height:26px;border-radius:50%;background:var(--bg)}
.tear::before{left:-13px}.tear::after{right:-13px}

h2{font-size:1.1rem;font-weight:800;letter-spacing:-.01em;margin-bottom:14px;display:flex;align-items:center;gap:8px}
h2 i{width:8px;height:22px;border-radius:4px;background:linear-gradient(180deg,#2a9bff,#0a63d6)}
.split{height:16px;border-radius:99px;background:#d6f3e3;overflow:hidden;display:flex;box-shadow:inset 0 1px 3px rgba(10,70,170,.15)}
.split i{display:block;height:100%;width:<?= $feePct ?>%;background:linear-gradient(90deg,#ffb347,#ff8a00);border-radius:99px 0 0 99px;animation:fill 1.2s .9s cubic-bezier(.2,.8,.2,1) both}
.legend{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:14px}
.lg{padding:14px 16px;border-radius:14px;border:1px solid var(--line);background:#fff;animation:rise .6s both}
.lg:nth-child(1){animation-delay:1s}.lg:nth-child(2){animation-delay:1.08s}
.lg small{display:flex;align-items:center;gap:8px;color:var(--mute);font-weight:600;font-size:.82rem}
.lg small::before{content:"";width:10px;height:10px;border-radius:50%;background:#ff8a00}
.lg.w small::before{background:var(--green)}
.lg b{display:block;font-size:1.45rem;font-weight:800;letter-spacing:-.02em;margin-top:2px}

.meta{display:flex;flex-wrap:wrap;gap:10px;margin:22px 0 4px}
.chip{display:inline-flex;align-items:center;gap:8px;padding:9px 16px;border-radius:99px;background:var(--soft);border:1px solid var(--line);font-size:.9rem}
.ok{display:inline-flex;align-items:center;gap:7px;padding:4px 13px;border-radius:99px;background:rgba(18,161,80,.12);color:var(--green);font-weight:800;letter-spacing:.06em;font-size:.8rem}
.ok::before{content:"";width:8px;height:8px;border-radius:50%;background:var(--green);animation:ping 2s infinite}
.pend{color:#b26a00;background:#fff3dc;padding:4px 13px;border-radius:99px;font-weight:700;font-size:.85rem}

.no-print{display:flex;flex-wrap:wrap;align-items:center;gap:14px;margin-top:26px}
.btn{position:relative;overflow:hidden;display:inline-flex;align-items:center;gap:9px;border:0;cursor:pointer;font:700 .98rem Inter,sans-serif;color:#fff;padding:13px 24px;border-radius:14px;
  background:linear-gradient(180deg,#2a9bff,#0a63d6);box-shadow:0 10px 22px -8px rgba(10,99,214,.6);transition:transform .15s,box-shadow .15s}
.btn:hover{transform:translateY(-2px);box-shadow:0 14px 26px -8px rgba(10,99,214,.7)}
.btn:active{transform:scale(.97)}
.btn:focus-visible,.back:focus-visible{outline:3px solid #7bbcff;outline-offset:3px}
.btn svg{width:20px;height:20px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.back{color:var(--blue);font-weight:700;text-decoration:none;padding:10px 6px}
.back:hover{text-decoration:underline}
.foot{text-align:center;color:var(--mute);font-size:.8rem;padding:0 0 22px}

@media(max-width:560px){
  body{padding:14px 8px 34px}.head{padding:22px 20px 48px}.body{padding:4px 18px 24px}.tear{margin:8px -18px 22px}
  .parties,.legend{grid-template-columns:1fr}.stamp{right:16px;top:18px;font-size:1rem;padding:6px 12px}.no{font-size:1.6rem}.big{font-size:1.5rem}
}
@media(prefers-reduced-motion:reduce){*,*::before,*::after{animation:none!important;transition:none!important}}
@media print{
  body{background:#fff;padding:0}body::before,body::after,.no-print,nav{display:none!important}
  .inv{box-shadow:none;border-radius:0;max-width:100%}*{animation:none!important;-webkit-print-color-adjust:exact;print-color-adjust:exact}
  .tear::before,.tear::after{background:#fff}
}
</style>
</head>
<body>
<main class="inv">
  <header class="head">
    <span class="stamp">PAID</span>
    <div class="brand"><svg viewBox="0 0 60 48" fill="none" stroke="#8fd0ff" stroke-width="7" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8l12 30L30 12l12 26L54 8"/></svg>Work<b>Onnect</b></div>
    <h1>INVOICE</h1>
    <div class="no">#<?= $invNo ?></div>
    <div class="dates"><span>Job date: <?= e($j["created"]) ?></span><span>Paid on: <?= e($paidOn) ?></span></div>
  </header>

  <div class="body">
    <div class="parties">
      <div class="party"><span class="av"><?= e(mb_strtoupper(mb_substr((string) $j["cn"], 0, 1))) ?></span>
        <div><small>Customer</small><b><?= e($j["cn"]) ?></b><span><?= e($j["cp"]) ?></span></div></div>
      <div class="party"><span class="av"><?= e(mb_strtoupper(mb_substr((string) $j["wn"], 0, 1))) ?></span>
        <div><small>Worker</small><b><?= e($j["wn"]) ?></b></div></div>
    </div>

    <div class="tw"><table>
      <thead><tr><th>Service</th><th>Location</th><th class="r">Amount</th></tr></thead>
      <tbody>
      <tr style="--i:0"><td><b><?= e($j["title"]) ?></b> <span class="pill"><?= e($j["category"]) ?></span></td><td><?= e($j["location"]) ?></td><td class="r">₹<span class="cnt"><?= $orig ?></span></td></tr>
      <?php if ($j["discount"]) {
          echo '<tr class="disc" style="--i:1"><td colspan="2">Coupon discount</td><td class="r">− ₹<span class="cnt">' . $j["discount"] . "</span></td></tr>";
      } ?>
      <tr class="sum" style="--i:2"><td colspan="2">Amount paid for the job</td><td class="r">₹<span class="cnt"><?= $j["price"] ?></span></td></tr>
      <?php if ($j["tip"]) {
          echo '<tr class="sum" style="--i:3"><td colspan="2">Tip for the worker</td><td class="r">₹<span class="cnt">' . $j["tip"] . "</span></td></tr>";
      } ?>
      <tr class="total" style="--i:4"><td colspan="2">Total paid by customer</td><td class="r big">₹<span class="cnt"><?= $j["price"] + $j["tip"] ?></span></td></tr>
      </tbody>
    </table></div>

    <div class="tear"></div>

    <h2><i></i>Where the money goes</h2>
    <div class="split" role="img" aria-label="Platform fee <?= $feePct ?> percent, rest to the worker"><i></i></div>
    <div class="legend">
      <div class="lg"><small>Platform fee (<?= e(PLATFORM_NAME) ?>, 10%)</small><b>₹<span class="cnt"><?= $fee ?></span></b></div>
      <div class="lg w"><small>Worker receives<?= $j["tip"] ? " (incl. tip ₹" . $j["tip"] . ")" : "" ?></small><b>₹<span class="cnt"><?= $share + $j["tip"] ?></span></b></div>
    </div>

    <div class="meta">
      <span class="chip">Payment method: <b><?= e($j["pay_method"]) ?></b></span>
      <?= $j["pay_ref"] ? '<span class="chip">Reference: <b>' . e($j["pay_ref"]) . "</b></span>" : "" ?>
      <span class="chip">Status: <span class="ok">PAID</span></span>
      <?php if ($u["role"] != "customer") {
          echo '<span class="chip">Worker payment: ' .
              ($j["settled"]
                  ? '<span class="ok">Paid on ' . e($j["settled_at"]) . " (ref " . e($j["settle_ref"]) . ")</span>"
                  : '<span class="pend">Pending</span>') .
              "</span>";
      } ?>
    </div>

    <p class="no-print">
      <button class="btn" onclick="window.print()"><svg viewBox="0 0 24 24"><path d="M6 9V3h12v6M6 18H4v-7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v7h-2M6 14h12v7H6z"/></svg>Print / Save as PDF</button>
      <a class="back" href="job.php?id=<?= $id ?>">&larr; Back</a>
    </p>
  </div>
  <p class="foot">Thank you for using <?= e(PLATFORM_NAME) ?></p>
</main>
<script>
// numbers count up on load (display only; the real values are already in the page)
(function(){
  if(matchMedia("(prefers-reduced-motion:reduce)").matches)return;
  document.querySelectorAll(".cnt").forEach(function(el){
    var end=parseInt(el.textContent,10);if(isNaN(end)||end<1)return;
    var t0=null,dur=900;el.textContent="0";
    function step(t){if(!t0)t0=t;var p=Math.min((t-t0)/dur,1),e=1-Math.pow(1-p,3);el.textContent=Math.round(end*e);if(p<1)requestAnimationFrame(step);else el.textContent=end}
    setTimeout(function(){requestAnimationFrame(step)},500);
  });
})();
</script>
</body></html>
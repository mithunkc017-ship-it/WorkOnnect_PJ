<?php // Worker: confirm that a customer's direct UPI payment (your share) reached your UPI account
require "config.php";
need("worker");
$u = me();
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $s = $pdo->prepare(
        "SELECT q.* FROM qr_payments q JOIN jobs j ON j.id=q.job_id WHERE q.id=? AND j.worker_id=? AND q.mode='split' AND q.status='submitted' AND q.worker_ok=0",
    );
    $s->execute([(int) $_POST["id"], $u["id"]]);
    $q = $s->fetch();
    if ($q) {
        if (($_POST["r"] ?? "") == "ok") {
            $pdo->prepare(
                "UPDATE qr_payments SET worker_ok=1 WHERE id=?",
            )->execute([$q["id"]]);
            finishSplit($pdo, $q["id"]);
            logit("Worker confirmed receiving UPI payment #" . $q["id"]);
        } else {
            $pdo->prepare(
                "UPDATE qr_payments SET status='rejected' WHERE id=?",
            )->execute([$q["id"]]);
            notify(
                $q["user_id"],
                "The worker did not receive your UPI payment for job #" .
                    $q["job_id"] .
                    ". Please check the reference number and try again.",
                "pay.php?id=" . $q["job_id"],
            );
            foreach (
                $pdo->query("SELECT id FROM users WHERE role='admin'")
                as $ad
            ) {
                notify(
                    $ad["id"],
                    '<span class="material-icons mi-xs">warning</span> Worker says a split UPI payment was NOT received (job #' .
                        $q["job_id"] .
                        ")",
                    "qr_payments.php",
                );
            }
            logit("Worker rejected UPI payment #" . $q["id"]);
        }
    }
    header("Location: upi_confirm.php");
    exit();
}

// ---- same query as before; rows are only collected so the page can show summary cards and filters ----
$s = $pdo->prepare(
    "SELECT q.*,j.title FROM qr_payments q JOIN jobs j ON j.id=q.job_id WHERE j.worker_id=? AND q.mode='split' AND q.status<>'waiting' ORDER BY q.status='submitted' DESC,q.id DESC LIMIT 50",
);
$s->execute([$u["id"]]);
$rows = [];
foreach ($s as $x) {
    $rows[] = $x;
}
$share = function ($x) {
    return $x["amount"] - (int) round($x["amount"] * 0.1);
};
$state = function ($x) {
    if ($x["status"] == "submitted") {
        return $x["worker_ok"] ? "platform" : "you";
    }
    return in_array($x["status"], ["approved", "rejected"], true)
        ? $x["status"]
        : "other";
};
$cnt = ["all" => count($rows), "you" => 0, "platform" => 0, "approved" => 0, "rejected" => 0];
$amtWait = 0;
$amtDone = 0;
foreach ($rows as $x) {
    $st = $state($x);
    if (isset($cnt[$st])) {
        $cnt[$st]++;
    }
    if ($st == "you" || $st == "platform") {
        $amtWait += $share($x);
    }
    if ($st == "approved") {
        $amtDone += $share($x);
    }
}
$hasUpi = validUpi($u["upi_id"] ?? "");

head("UPI confirmations");
?>
<style>
body>nav a[href="dash.php"],body>nav a[href="login.php"]{display:none!important}
.uc{--bg:#fff;--bg2:#f8fafc;--bd:#e3e8ef;--tx:#1c2530;--mu:#667085;--pri:#4f46e5;--pri2:#4338ca;--ok:#067647;--okb:#ecfdf3;--wa:#b54708;--wab:#fffaeb;--er:#b42318;--erb:#fef3f2;--in:#3730a3;--inb:#eef2ff;max-width:1040px;margin:0 auto;padding:4px 4px 40px;color:var(--tx)}
body.dark .uc{--bg:#182230;--bg2:#1d2939;--bd:#2f3b4d;--tx:#eaecf0;--mu:#98a2b3;--ok:#6ce9a6;--okb:#0b3d2a;--wa:#fec84b;--wab:#43300a;--er:#fda29b;--erb:#4a1612;--in:#c7d2fe;--inb:#26264f}
.uc *{box-sizing:border-box}
.uc-top{display:flex;align-items:center;gap:14px;margin-bottom:6px}
.uc-ico{width:48px;height:48px;border-radius:13px;display:grid;place-items:center;background:var(--inb);color:var(--pri);flex:none}
.uc-ico .material-icons{font-size:26px}
.uc h2{margin:0;font-size:24px;font-weight:700;color:var(--tx)}
.uc-lead{margin:10px 0 22px;font-size:14.5px;line-height:1.65;color:var(--mu);max-width:68ch}
.uc-warn{display:flex;gap:12px;align-items:flex-start;background:var(--erb);color:var(--er);border:1px solid color-mix(in srgb,var(--er) 25%,transparent);border-radius:14px;padding:14px 16px;margin-bottom:20px;font-size:14px;line-height:1.5}
.uc-warn .material-icons{font-size:22px;flex:none}
.uc-warn a{color:inherit;font-weight:700;text-decoration:underline}
.uc-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-bottom:22px}
.uc-stat{background:var(--bg);border:1px solid var(--bd);border-radius:16px;padding:16px 18px;box-shadow:0 1px 2px rgba(16,24,40,.04)}
.uc-stat .l{display:flex;align-items:center;gap:8px;font-size:13px;color:var(--mu);font-weight:500}
.uc-stat .l .material-icons{font-size:18px}
.uc-stat b{display:block;margin-top:8px;font-size:26px;line-height:1.1;font-weight:700;color:var(--tx)}
.uc-stat.go{cursor:pointer;transition:box-shadow .15s,transform .1s}
.uc-stat.go:hover{box-shadow:0 8px 20px -10px rgba(16,24,40,.35)}
.uc-stat.go:focus-visible{outline:3px solid #a5b4fc;outline-offset:2px}
.uc-stat.sel{box-shadow:0 0 0 2px var(--pri)}
.uc-stat.hot{border-color:color-mix(in srgb,var(--wa) 45%,transparent);background:var(--wab)}
.uc-stat.hot .l,.uc-stat.hot b{color:var(--wa)}
.uc-card{background:var(--bg);border:1px solid var(--bd);border-radius:18px;box-shadow:0 1px 2px rgba(16,24,40,.04),0 12px 32px -16px rgba(16,24,40,.15);overflow:hidden}
.uc-bar{display:flex;gap:14px;align-items:center;justify-content:space-between;flex-wrap:wrap;padding:16px 18px;border-bottom:1px solid var(--bd)}
.uc-tabs{display:flex;gap:6px;flex-wrap:wrap}
.uc-tab{-webkit-appearance:none;appearance:none;border:1px solid var(--bd)!important;background:var(--bg)!important;color:var(--mu)!important;font-size:13px;font-weight:600;padding:7px 13px;border-radius:999px;cursor:pointer;display:inline-flex;gap:7px;align-items:center;width:auto;margin:0;box-shadow:none!important;text-shadow:none;-webkit-tap-highlight-color:transparent}
.uc-tab:hover,.uc-tab:focus,.uc-tab:active{background:var(--bg2)!important;color:var(--tx)!important}
.uc-tab.on,.uc-tab.on:hover,.uc-tab.on:focus,.uc-tab.on:active{background:var(--pri)!important;border-color:var(--pri)!important;color:#fff!important}
.uc-tab .uc-n{font-style:normal;font-size:11.5px;line-height:1.5;padding:1px 8px;border-radius:999px;background:rgba(127,127,127,.2)!important;color:inherit!important}
.uc-tab.on .uc-n{background:rgba(255,255,255,.28)!important;color:#fff!important}
.uc-search{display:flex;align-items:center;gap:8px;border:1.5px solid var(--bd);border-radius:12px;padding:0 12px;background:var(--bg);min-width:230px}
.uc-search:focus-within{border-color:var(--pri);box-shadow:0 0 0 4px rgba(79,70,229,.14)}
.uc-search .material-icons{font-size:19px;color:var(--mu)}
.uc-search input{border:0!important;outline:0!important;box-shadow:none!important;background:transparent!important;padding:10px 0!important;margin:0!important;font-size:14px;color:var(--tx);width:100%}
.uc-scroll{overflow-x:auto}
.uc table{width:100%;border-collapse:collapse;border:0;margin:0}
.uc th{background:var(--bg2);color:var(--mu);font-size:12.5px;font-weight:600;text-align:left;padding:12px 18px;border:0;border-bottom:1px solid var(--bd);white-space:nowrap}
.uc td{padding:16px 18px;border:0;border-bottom:1px solid var(--bd);vertical-align:middle;font-size:14px;color:var(--tx);background:transparent}
.uc tbody tr:last-child td{border-bottom:0}
.uc tbody tr:hover td{background:var(--bg2)}
.uc tr.need td:first-child{box-shadow:inset 4px 0 0 var(--wa)}
.uc td small{display:block;color:var(--mu);font-size:12px;margin-top:2px}
.uc-job{font-weight:600;color:var(--pri);text-decoration:none}
.uc-job:hover{text-decoration:underline}
.uc-amt{font-size:16px;font-weight:700}
.uc-ref{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:13px;background:var(--bg2);border:1px solid var(--bd);border-radius:8px;padding:4px 8px;word-break:break-all}
.uc-copy{border:0;background:transparent;color:var(--mu);cursor:pointer;padding:4px;border-radius:8px;vertical-align:middle;width:auto;margin:0 0 0 4px;line-height:1}
.uc-copy:hover{background:var(--bg2);color:var(--pri)}
.uc-copy .material-icons{font-size:18px}
.uc-badge{display:inline-flex;align-items:center;gap:6px;font-size:12.5px;font-weight:600;padding:5px 11px;border-radius:999px;white-space:nowrap}
.uc-badge .material-icons{font-size:16px}
.b-you{background:var(--wab);color:var(--wa)}
.b-platform{background:var(--inb);color:var(--in)}
.b-approved{background:var(--okb);color:var(--ok)}
.b-rejected{background:var(--erb);color:var(--er)}
.b-other{background:var(--bg2);color:var(--mu)}
.uc-act{display:flex;gap:8px;flex-wrap:wrap}
.uc-b{-webkit-appearance:none;appearance:none;display:inline-flex;align-items:center;gap:6px;border:0!important;cursor:pointer;font-size:13.5px;font-weight:600;padding:9px 14px;border-radius:10px;width:auto;margin:0;color:#fff!important;box-shadow:none!important;-webkit-tap-highlight-color:transparent}
.uc-b .material-icons{font-size:18px}
.uc-b.go,.uc-b.go:focus,.uc-b.go:active{background:#039855!important;color:#fff!important}
.uc-b.go:hover{background:#027a48!important}
.uc-b.no,.uc-b.no:focus,.uc-b.no:active{background:var(--erb)!important;color:var(--er)!important;box-shadow:inset 0 0 0 1.5px #f97066!important}
.uc-b.no:hover{background:#d92d20!important;color:#fff!important;box-shadow:none!important}
.uc-b:focus-visible,.uc-tab:focus-visible,.uc-copy:focus-visible{outline:3px solid #a5b4fc;outline-offset:2px}
.uc-empty{text-align:center;padding:48px 20px;color:var(--mu)}
.uc-empty .material-icons{font-size:44px;color:var(--bd);display:block;margin:0 auto 10px}
.uc-empty b{display:block;color:var(--tx);font-size:16px;margin-bottom:4px}
.uc-none{display:none}
.uc-toast{position:fixed;left:50%;bottom:28px;transform:translateX(-50%);background:#101828;color:#fff;padding:10px 16px;border-radius:12px;font-size:13.5px;z-index:50;box-shadow:0 10px 30px rgba(0,0,0,.3)}
.uc-toast[hidden]{display:none}
@media (max-width:820px){
 .uc-stats{grid-template-columns:repeat(2,minmax(0,1fr))}
 .uc thead{display:none}
 .uc table,.uc tbody,.uc tr,.uc td{display:block;width:100%}
 .uc tbody tr{padding:14px 16px;border-bottom:1px solid var(--bd)}
 .uc tr.need td:first-child{box-shadow:none}
 .uc tr.need{box-shadow:inset 4px 0 0 var(--wa)}
 .uc td{border:0;padding:6px 0;display:flex;justify-content:space-between;gap:14px;align-items:center;text-align:right}
 .uc td::before{content:attr(data-l);font-size:12.5px;color:var(--mu);font-weight:600;text-align:left;flex:none}
 .uc td:last-child:empty{display:none}
 .uc tbody tr:hover td{background:transparent}
 .uc-search{width:100%}
}
@media (prefers-reduced-motion:reduce){*{transition:none!important}}
</style>

<div class="uc">
  <div class="uc-top">
    <div class="uc-ico"><span class="material-icons">smartphone</span></div>
    <h2>Confirm UPI Payments</h2>
  </div>
  <p class="uc-lead">Customers who pay you directly by UPI enter their reference number here. Check your UPI app and confirm only if the money arrived.</p>

  <?php if (!$hasUpi) { ?>
    <div class="uc-warn" role="alert">
      <span class="material-icons">error_outline</span>
      <span>You have no UPI ID yet. <a href="my_upi.php">Add your UPI ID</a> so customers can pay you directly.</span>
    </div>
  <?php } ?>

  <div class="uc-stats">
    <div class="uc-stat go<?= $cnt["you"] ? " hot" : "" ?>" data-go="you" role="button" tabindex="0" title="Show payments waiting for you">
      <span class="l"><span class="material-icons">hourglass_empty</span>Waiting for you</span>
      <b><?= (int) $cnt["you"] ?></b>
    </div>
    <div class="uc-stat go" data-go="platform" role="button" tabindex="0" title="Show payments waiting for the platform">
      <span class="l"><span class="material-icons">account_balance</span>Waiting for platform</span>
      <b><?= (int) $cnt["platform"] ?></b>
    </div>
    <div class="uc-stat">
      <span class="l"><span class="material-icons">schedule</span>Amount pending</span>
      <b>₹<?= (int) $amtWait ?></b>
    </div>
    <div class="uc-stat">
      <span class="l"><span class="material-icons">task_alt</span>Amount confirmed</span>
      <b>₹<?= (int) $amtDone ?></b>
    </div>
  </div>

  <div class="uc-card">
    <div class="uc-bar">
      <div class="uc-tabs" role="tablist" aria-label="Filter payments">
        <button type="button" class="uc-tab on" data-f="all">All <em class="uc-n"><?= (int) $cnt["all"] ?></em></button>
        <button type="button" class="uc-tab" data-f="you">Waiting for you <em class="uc-n"><?= (int) $cnt["you"] ?></em></button>
        <button type="button" class="uc-tab" data-f="platform">Waiting for platform <em class="uc-n"><?= (int) $cnt["platform"] ?></em></button>
        <button type="button" class="uc-tab" data-f="approved">Approved <em class="uc-n"><?= (int) $cnt["approved"] ?></em></button>
        <button type="button" class="uc-tab" data-f="rejected">Rejected <em class="uc-n"><?= (int) $cnt["rejected"] ?></em></button>
      </div>
      <label class="uc-search">
        <span class="material-icons">search</span>
        <input id="ucQ" type="search" placeholder="Search job or reference" autocomplete="off" aria-label="Search job or reference">
      </label>
    </div>

    <?php if (!$rows) { ?>
      <div class="uc-empty">
        <span class="material-icons">inbox</span>
        <b>No UPI payments yet</b>
        When a customer pays you directly by UPI, it will show up here for you to confirm.
      </div>
    <?php } else { ?>
    <div class="uc-scroll">
    <table>
      <thead><tr><th>Job</th><th>Your share</th><th>UPI reference</th><th>Status</th><th>Action</th></tr></thead>
      <tbody id="ucBody">
      <?php foreach ($rows as $x) {
          $st = $state($x);
          $need = $st == "you";
          $utr = (string) ($x["worker_utr"] ?? "");
          $badge = [
              "you" => ["b-you", "hourglass_empty", "Waiting for you"],
              "platform" => ["b-platform", "check_circle", "You confirmed – waiting for the platform"],
              "approved" => ["b-approved", "verified", "Approved"],
              "rejected" => ["b-rejected", "cancel", "Rejected"],
              "other" => ["b-other", "info", $x["status"]],
          ][$st];
          ?>
        <tr class="<?= $need ? "need" : "" ?>" data-st="<?= e($st) ?>" data-q="<?= e(mb_strtolower($x["title"] . " " . $utr . " #" . $x["job_id"])) ?>">
          <td data-l="Job">
            <div><a class="uc-job" href="job.php?id=<?= (int) $x["job_id"] ?>"><?= e($x["title"]) ?></a><small>Job #<?= (int) $x["job_id"] ?></small></div>
          </td>
          <td data-l="Your share">
            <div><span class="uc-amt">₹<?= (int) $share($x) ?></span><small>of ₹<?= (int) $x["amount"] ?> paid</small></div>
          </td>
          <td data-l="UPI reference">
            <div>
              <?php if ($utr !== "") { ?>
                <span class="uc-ref"><?= e($utr) ?></span><button type="button" class="uc-copy" data-c="<?= e($utr) ?>" title="Copy reference" aria-label="Copy reference"><span class="material-icons">content_copy</span></button>
              <?php } else { ?>
                <span class="uc-ref">—</span>
              <?php } ?>
            </div>
          </td>
          <td data-l="Status"><span class="uc-badge <?= $badge[0] ?>"><span class="material-icons"><?= $badge[1] ?></span><?= e($badge[2]) ?></span></td>
          <td data-l="Action"><?php if ($x["status"] == "submitted" && !$x["worker_ok"]) { ?>
            <form method="post" class="uc-act">
              <input type="hidden" name="id" value="<?= (int) $x["id"] ?>">
              <button class="uc-b go" name="r" value="ok"><span class="material-icons">check_circle</span>Money received</button>
              <button class="uc-b no" name="r" value="no"><span class="material-icons">close</span>Not received</button>
            </form>
          <?php } ?></td>
        </tr>
      <?php } ?>
      </tbody>
    </table>
    </div>
    <div class="uc-empty uc-none" id="ucNone">
      <span class="material-icons">search_off</span>
      <b>Nothing matches</b>
      Try another filter or search word.
    </div>
    <?php } ?>
  </div>
</div>
<div class="uc-toast" id="ucToast" hidden></div>

<script>
(function(){
  var body=document.getElementById('ucBody');
  if(!body)return;
  var rows=[].slice.call(body.querySelectorAll('tr')),tabs=[].slice.call(document.querySelectorAll('.uc-tab')),
      q=document.getElementById('ucQ'),none=document.getElementById('ucNone'),f='all';
  function apply(){
    var s=q.value.trim().toLowerCase(),n=0;
    rows.forEach(function(r){
      var ok=(f==='all'||r.dataset.st===f)&&(!s||r.dataset.q.indexOf(s)>-1);
      r.style.display=ok?'':'none';if(ok)n++;
    });
    none.classList.toggle('uc-none',n>0);
  }
  tabs.forEach(function(t){t.addEventListener('click',function(){
    tabs.forEach(function(x){x.classList.remove('on')});t.classList.add('on');f=t.dataset.f;apply();
  })});
  q.addEventListener('input',apply);
  var cards=[].slice.call(document.querySelectorAll('.uc-stat.go'));
  function pick(c){
    var t=tabs.filter(function(x){return x.dataset.f===c.dataset.go})[0];if(!t)return;
    t.click();cards.forEach(function(x){x.classList.toggle('sel',x===c)});
    var card=document.querySelector('.uc-card');if(card&&card.scrollIntoView)card.scrollIntoView({behavior:'smooth',block:'start'});
  }
  cards.forEach(function(c){
    c.addEventListener('click',function(){pick(c)});
    c.addEventListener('keydown',function(e){if(e.key==='Enter'||e.key===' '){e.preventDefault();pick(c)}});
  });
  tabs.forEach(function(t){t.addEventListener('click',function(){cards.forEach(function(x){x.classList.toggle('sel',x.dataset.go===t.dataset.f)})})});
  var tt=document.getElementById('ucToast'),timer;
  function toast(m){tt.textContent=m;tt.hidden=false;clearTimeout(timer);timer=setTimeout(function(){tt.hidden=true},1800)}
  body.addEventListener('click',function(e){
    var b=e.target.closest('.uc-copy');if(!b)return;
    var v=b.dataset.c;
    if(navigator.clipboard){navigator.clipboard.writeText(v).then(function(){toast('Reference copied')},function(){toast('Could not copy')})}
    else{var t=document.createElement('textarea');t.value=v;document.body.appendChild(t);t.select();try{document.execCommand('copy');toast('Reference copied')}catch(x){toast('Could not copy')}t.remove()}
  });
  body.addEventListener('submit',function(e){
    var b=e.submitter;
    if(b&&b.value==='no'&&!confirm('Mark this payment as NOT received? The customer and the platform will be notified.'))e.preventDefault();
    if(b&&b.value==='ok'&&!confirm('Confirm that the money reached your UPI account?'))e.preventDefault();
  });
})();
</script>
<?php foot(); ?>
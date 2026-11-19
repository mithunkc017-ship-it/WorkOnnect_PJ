<?php // Pay by scanning UPI QR code(s). Platform mode: one QR to the platform UPI (admin confirms). Split mode: QR 1 = worker's share to the worker's own UPI ID, QR 2 = platform fee (10%) to the UPI ID in the admin profile; the worker and the admin each confirm.
require "config.php";
need("customer");
$u = me();
$qid = (int) ($_GET["q"] ?? 0);
$err = "";
$s = $pdo->prepare(
    "SELECT q.*,j.title,j.paid,w.name wn,w.upi_id wupi FROM qr_payments q JOIN jobs j ON j.id=q.job_id LEFT JOIN users w ON w.id=j.worker_id WHERE q.id=? AND q.user_id=?",
);
$s->execute([$qid, $u["id"]]);
$q = $s->fetch();
if (!$q || ($q["paid"] && $q["status"] != "approved")) {
    die('Nothing to pay. <a href="dashboard.php">Back</a>');
}
$split = $q["mode"] == "split";
$fee = (int) round($q["amount"] * 0.1);
$share = $q["amount"] - $fee;
$pu = platformUpi($pdo);
$lk = function ($pa, $pn, $am, $tn, $tr) {
    return "upi://pay?pa=" .
        rawurlencode($pa) .
        "&pn=" .
        rawurlencode($pn) .
        "&am=" .
        number_format($am, 2, ".", "") .
        "&cu=INR&tn=" .
        rawurlencode($tn) .
        "&tr=" .
        $tr;
};
if ($_SERVER["REQUEST_METHOD"] == "POST" && $q["status"] == "waiting") {
    if ($split) {
        $a = trim($_POST["worker_utr"] ?? "");
        $b = trim($_POST["fee_utr"] ?? "");
        if (!preg_match('/^\d{12}$/', $a) || !preg_match('/^\d{12}$/', $b)) {
            $err = "Enter both 12-digit UPI reference numbers (UTR).";
        } elseif ($a === $b) {
            $err = "The two reference numbers must be different.";
        } else {
            $d = $pdo->prepare(
                "SELECT 1 FROM qr_payments WHERE id<>? AND status IN('submitted','approved') AND (utr IN(?,?) OR worker_utr IN(?,?) OR fee_utr IN(?,?))",
            );
            $d->execute([$qid, $a, $b, $a, $b, $a, $b]);
            if ($d->fetch()) {
                $err = "One of these reference numbers was already used.";
            } else {
                $pdo->prepare(
                    "UPDATE qr_payments SET worker_utr=?,fee_utr=?,status='submitted' WHERE id=?",
                )->execute([$a, $b, $qid]);
                $jw = $pdo->prepare("SELECT worker_id FROM jobs WHERE id=?");
                $jw->execute([$q["job_id"]]);
                notify(
                    (int) $jw->fetchColumn(),
                    '<span class="material-icons mi">smartphone</span> ' .
                        $u["name"] .
                        " says they paid you ₹" .
                        $share .
                        " by UPI (ref " .
                        $a .
                        "). Please check your UPI app and confirm.",
                    "upi_confirm.php",
                );
                foreach (
                    $pdo->query("SELECT id FROM users WHERE role='admin'")
                    as $ad
                ) {
                    notify(
                        $ad["id"],
                        '<span class="material-icons mi">smartphone</span> Platform fee ₹' .
                            $fee .
                            " paid by UPI for job #" .
                            $q["job_id"] .
                            " (ref " .
                            $b .
                            "). Please confirm.",
                        "qr_payments.php",
                    );
                }
                logit("Submitted split UPI payment for job #" . $q["job_id"]);
                header("Location: pay_qr.php?q=$qid");
                exit();
            }
        }
    } else {
        $utr = trim($_POST["utr"] ?? "");
        if (!preg_match('/^\d{12}$/', $utr)) {
            $err =
                "Enter the 12-digit UPI reference number (UTR) shown in your payment app.";
        } else {
            $d = $pdo->prepare(
                "SELECT 1 FROM qr_payments WHERE utr=? AND status IN('submitted','approved')",
            );
            $d->execute([$utr]);
            if ($d->fetch()) {
                $err = "This reference number was already used.";
            } else {
                $pdo->prepare(
                    "UPDATE qr_payments SET utr=?,status='submitted' WHERE id=?",
                )->execute([$utr, $qid]);
                foreach (
                    $pdo->query("SELECT id FROM users WHERE role='admin'")
                    as $ad
                ) {
                    notify(
                        $ad["id"],
                        '<span class="material-icons mi">smartphone</span> QR payment of ₹' .
                            $q["amount"] .
                            " to verify (job #" .
                            $q["job_id"] .
                            ")",
                        "qr_payments.php",
                    );
                }
                logit(
                    "Submitted UPI QR payment reference for job #" .
                        $q["job_id"],
                );
                header("Location: pay_qr.php?q=$qid");
                exit();
            }
        }
    }
}
// [label, amount text, payee text, upi link, payee UPI id, raw amount, short title]
$parts = $split
    ? [
        [
            "Pay the worker",
            "₹" . $share,
            "to " . $q["wn"] . " (" . $q["wupi"] . ")",
            $lk(
                $q["wupi"],
                $q["wn"],
                $share,
                "Job #" . $q["job_id"],
                "WS" . $qid,
            ),
            $q["wupi"],
            $share,
            "Worker",
        ],
        [
            "Pay the platform fee (10%)",
            "₹" . $fee,
            "to " . PLATFORM_NAME . " (" . $pu . ")",
            $lk(
                $pu,
                PLATFORM_NAME,
                $fee,
                "Platform fee job #" . $q["job_id"],
                "WF" . $qid,
            ),
            $pu,
            $fee,
            "Platform fee",
        ],
    ]
    : [
        [
            "Scan & pay",
            "₹" . $q["amount"],
            "to " . PLATFORM_NAME . " (" . $pu . ")",
            $lk(
                $pu,
                PLATFORM_NAME,
                $q["amount"],
                "Job #" . $q["job_id"],
                "WQR" . $qid,
            ),
            $pu,
            $q["amount"],
            "Payment",
        ],
    ];
$status = $q["status"];
$step = $status == "waiting" ? 2 : ($status == "submitted" ? 3 : ($status == "approved" ? 4 : 2));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>Scan &amp; Pay</title>
<?php if ($status == "submitted") { ?><meta http-equiv="refresh" content="15"><?php } ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
<style>
:root{
  --blue:#2563eb;--blue-d:#1d4ed8;--blue-l:#eff6ff;--ink:#0f172a;--mut:#64748b;--line:#e2e8f0;
  --card:#ffffff;--bg1:#eef4ff;--bg2:#f8fbff;--ok:#16a34a;--okbg:#ecfdf5;--err:#dc2626;--errbg:#fef2f2;--warn:#d97706;
  --qr:clamp(200px,min(40vh,30vw),330px);
}
@media (prefers-color-scheme:dark){
  :root{--blue:#3b82f6;--blue-d:#2563eb;--blue-l:#14213d;--ink:#e8eefc;--mut:#94a3b8;--line:#243049;
  --card:#111a2e;--bg1:#0a1020;--bg2:#0d1526;--okbg:#0b2a1b;--errbg:#2b1212}
}
*{box-sizing:border-box;margin:0;padding:0}
html,body{min-height:100%}
body{font-family:Inter,system-ui,sans-serif;color:var(--ink);background:
  radial-gradient(900px 500px at 10% -10%,rgba(37,99,235,.18),transparent 60%),
  radial-gradient(700px 500px at 100% 0%,rgba(99,102,241,.16),transparent 60%),
  linear-gradient(180deg,var(--bg1),var(--bg2));
  min-height:100vh;min-height:100dvh;display:flex;justify-content:center;align-items:center;padding:16px;-webkit-font-smoothing:antialiased}
.mi{font-family:'Material Icons';font-size:20px;vertical-align:middle;line-height:1;font-weight:normal;font-style:normal;display:inline-block;letter-spacing:normal;text-transform:none;white-space:nowrap}
.card{width:100%;max-width:1120px;background:var(--card);border:1px solid var(--line);border-radius:26px;box-shadow:0 24px 60px -24px rgba(30,64,175,.4),0 2px 6px rgba(15,23,42,.04);overflow:hidden;animation:rise .45s ease both}
@keyframes rise{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:none}}

/* top bar */
.top{display:flex;align-items:center;justify-content:space-between;gap:14px;padding:20px 32px;border-bottom:1px solid var(--line)}
.ttl{display:flex;align-items:center;gap:14px;min-width:0}
.ttl .ic{width:46px;height:46px;border-radius:14px;background:linear-gradient(135deg,#1e40af,#3b82f6);color:#fff;display:flex;align-items:center;justify-content:center;flex:none}
.ttl .ic .mi{font-size:24px}
.ttl div{min-width:0}
.ttl b{display:block;font-size:18px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.ttl small{color:var(--mut);font-size:12px;font-weight:500;letter-spacing:.3px}
.steps{display:flex;gap:6px;flex:none}
.st{display:flex;align-items:center;gap:6px;font-size:11.5px;font-weight:600;color:var(--mut);padding:5px 10px 5px 6px;border-radius:99px;background:transparent}
.st i{width:18px;height:18px;border-radius:50%;background:var(--line);color:var(--mut);display:flex;align-items:center;justify-content:center;font-style:normal;font-size:10px;font-weight:700}
.st i .mi{font-size:12px}
.st.done i,.st.on i{background:var(--blue);color:#fff}
.st.on{background:var(--blue-l);color:var(--blue)}
.st.done{color:var(--ink)}

/* two columns */
.grid{display:grid;grid-template-columns:1fr 1fr}
.left{padding:28px 36px 30px;background:linear-gradient(180deg,var(--blue-l),transparent 75%);border-right:1px solid var(--line);display:flex;flex-direction:column;justify-content:center;align-items:center;min-width:0}
.left>*{width:100%;max-width:400px}
.right{padding:28px 40px 30px;display:flex;flex-direction:column;justify-content:center;min-width:0}

.tabs{display:flex;gap:8px;margin-bottom:6px}
.tab{flex:1;border:1.5px solid var(--line);background:var(--card);color:var(--mut);border-radius:14px;padding:12px 8px;font:600 14px Inter,sans-serif;cursor:pointer;transition:.2s;display:flex;align-items:center;justify-content:center;gap:5px}
.tab .mi{font-size:16px}
.tab.on{border-color:var(--blue);color:var(--blue);background:var(--blue-l)}
.pane{display:none;text-align:center;animation:rise .3s ease both}
.pane.on{display:block}
.pane h3{font-size:18px;margin-top:6px}
.pane .who{font-size:13px;color:var(--mut);word-break:break-all;margin-top:2px}
.qrframe{position:relative;width:var(--qr);height:var(--qr);margin:20px auto 18px;padding:16px;background:#fff;border-radius:18px;box-shadow:0 10px 28px -10px rgba(37,99,235,.5);border:1px solid #e5edff}
.qrb{width:100%;height:100%;line-height:0}
.qrb svg{width:100%;height:100%;display:block}
.qrframe .c{position:absolute;width:22px;height:22px;border:3px solid var(--blue)}
.c.tl{top:-6px;left:-6px;border-right:0;border-bottom:0;border-radius:11px 0 0 0}
.c.tr{top:-6px;right:-6px;border-left:0;border-bottom:0;border-radius:0 11px 0 0}
.c.bl{bottom:-6px;left:-6px;border-right:0;border-top:0;border-radius:0 0 0 11px}
.c.br{bottom:-6px;right:-6px;border-left:0;border-top:0;border-radius:0 0 11px 0}
.scan{position:absolute;left:12px;right:12px;height:2px;background:linear-gradient(90deg,transparent,var(--blue),transparent);box-shadow:0 0 10px var(--blue);animation:scan 2.6s ease-in-out infinite;border-radius:2px}
@keyframes scan{0%,100%{top:12px;opacity:.2}50%{top:calc(100% - 14px);opacity:1}}
.actions{display:grid;grid-template-columns:1fr 1fr;gap:10px;width:100%}
.actions .btn{grid-column:1/-1}
.chip{display:inline-flex;align-items:center;justify-content:center;gap:7px;border:1.5px solid var(--line);background:var(--card);color:var(--ink);border-radius:14px;padding:13px 12px;font:600 14px Inter,sans-serif;cursor:pointer;transition:.2s;white-space:nowrap;box-shadow:0 2px 6px rgba(15,23,42,.05)}
.chip:hover{border-color:var(--blue);color:var(--blue);background:var(--blue-l)}
.chip .mi{font-size:18px}
.chip.copied{background:var(--okbg);border-color:var(--ok);color:var(--ok)}
.btn,button.pay{display:flex;align-items:center;justify-content:center;gap:10px;width:100%;background:linear-gradient(180deg,#3b82f6,#2563eb);color:#fff;border:0;border-radius:14px;padding:16px 20px;font:700 16px Inter,sans-serif;text-decoration:none;cursor:pointer;box-shadow:0 10px 22px -8px rgba(37,99,235,.75),inset 0 1px 0 rgba(255,255,255,.25);transition:.2s}
.btn:hover,button.pay:hover{background:linear-gradient(180deg,#2563eb,#1d4ed8);transform:translateY(-1px)}
.btn:active,button.pay:active{transform:none}
button.pay:disabled{background:#94a3b8;box-shadow:none;cursor:not-allowed;transform:none}
.hint{display:flex;gap:8px;align-items:center;justify-content:center;font-size:12.5px;color:var(--mut);margin-top:16px;line-height:1.45;text-align:left}
.hint .mi{color:var(--blue);font-size:16px;flex:none}

/* summary */
.sum{border:1px solid var(--line);border-radius:16px;overflow:hidden}
.sum .tot{display:flex;justify-content:space-between;align-items:center;padding:20px 22px;background:linear-gradient(135deg,#1e40af,#2563eb 60%,#4f8cff);color:#fff}
.sum .tot span{font-size:14px;font-weight:600;opacity:.92;display:flex;align-items:center;gap:8px}
.sum .tot span .mi{font-size:20px}
.sum .tot b{font-size:38px;font-weight:800;letter-spacing:-1px}
.row{display:flex;justify-content:space-between;align-items:center;gap:10px;padding:14px 22px;font-size:14.5px;border-top:1px dashed var(--line)}
.row span{color:var(--mut)}
.row b{font-weight:700;white-space:nowrap}
.row.rec b{color:var(--ok)}
.timer{display:flex;align-items:center;justify-content:space-between;font-size:13px;color:var(--mut);margin:18px 2px 0}
.timer b{font-family:'JetBrains Mono',monospace;color:var(--ink);font-size:15px}
.bar{height:5px;background:var(--line);border-radius:5px;overflow:hidden;margin-top:8px}
.bar i{display:block;height:100%;width:100%;background:linear-gradient(90deg,var(--blue),#60a5fa);transition:width 1s linear}
form{margin-top:22px;padding-top:20px;border-top:1px solid var(--line)}
form h4{font-size:16px;margin-bottom:12px}
.err{color:var(--err);background:var(--errbg);border-radius:12px;padding:9px 12px;font-size:12.5px;margin-bottom:10px;display:flex;gap:8px;align-items:flex-start;line-height:1.4}
.err .mi{font-size:18px;flex:none}
.fld{position:relative;margin-bottom:12px}
.fld input{width:100%;border:1.5px solid var(--line);background:transparent;color:var(--ink);border-radius:14px;padding:16px 64px 16px 16px;font:600 16px 'JetBrains Mono',monospace;letter-spacing:2px;outline:0;transition:.2s}
.fld input::placeholder{font:500 13.5px Inter,sans-serif;letter-spacing:0;color:var(--mut)}
.fld input:focus{border-color:var(--blue);box-shadow:0 0 0 4px rgba(37,99,235,.15)}
.fld.valid input{border-color:var(--ok)}
.fld .cnt{position:absolute;right:12px;top:50%;transform:translateY(-50%);font:600 11.5px 'JetBrains Mono',monospace;color:var(--mut)}
.fld.valid .cnt{color:var(--ok)}
.fl{font-size:12.5px;font-weight:600;color:var(--mut);margin:0 0 6px;display:block}
.demo{margin-bottom:10px}

/* status screens */
.status{text-align:center;padding:4px 0}
.badge{width:64px;height:64px;border-radius:50%;margin:4px auto 12px;display:flex;align-items:center;justify-content:center}
.badge .mi{font-size:34px}
.badge.wait{background:var(--blue-l);color:var(--blue);position:relative}
.badge.wait:before{content:"";position:absolute;inset:-7px;border-radius:50%;border:2px solid var(--blue);opacity:.4;animation:ping 1.6s ease-out infinite}
@keyframes ping{from{transform:scale(.8);opacity:.6}to{transform:scale(1.3);opacity:0}}
.badge.good{background:var(--okbg);color:var(--ok);animation:pop .5s cubic-bezier(.2,1.6,.4,1) both}
.badge.bad{background:var(--errbg);color:var(--err)}
@keyframes pop{from{transform:scale(.3);opacity:0}to{transform:none;opacity:1}}
.status h3{font-size:18px;margin-bottom:5px}
.status p{color:var(--mut);font-size:13px;line-height:1.5;margin-bottom:14px}
.tl{text-align:left;margin:0 0 14px;border:1px solid var(--line);border-radius:14px;padding:2px 14px;background:var(--card)}
.tl div{display:flex;align-items:center;gap:10px;padding:9px 0;font-size:13px;border-bottom:1px dashed var(--line)}
.tl div:last-child{border:0}
.tl .mi{font-size:19px}
.tl .y{color:var(--ok)}.tl .n{color:var(--warn)}
.ref{font-family:'JetBrains Mono',monospace;background:var(--blue-l);color:var(--blue);padding:4px 10px;border-radius:8px;font-size:12.5px;display:inline-block;margin-bottom:12px}
.secure{display:flex;justify-content:center;align-items:center;gap:5px;color:var(--mut);font-size:11.5px;margin-top:12px}
.secure .mi{font-size:14px;color:var(--ok)}
.conf{position:fixed;inset:0;pointer-events:none;overflow:hidden}
.conf i{position:absolute;top:-12px;width:8px;height:14px;opacity:.9;animation:fall linear forwards}
@keyframes fall{to{transform:translateY(110vh) rotate(720deg)}}

/* tablets / phones: single column, compact */
@media (max-width:820px){
  body{padding:10px;align-items:flex-start}
  .card{border-radius:18px}
  .top{padding:12px 14px;flex-wrap:wrap;gap:8px}
  .ttl .ic{width:38px;height:38px;border-radius:12px}
  .ttl .ic .mi{font-size:20px}
  .ttl b{font-size:15px}
  .left>*{max-width:none}
  .left{align-items:stretch}
  .btn,button.pay{padding:14px 16px;font-size:15px}
  .chip{padding:11px 8px;font-size:13px}
  .tab{padding:9px 6px;font-size:12.5px}
  .pane h3{font-size:14.5px}
  .pane .who{font-size:12px}
  .timer{font-size:12px}
  .fld input{padding:13px 56px 13px 14px;font-size:15px}
  form{margin-top:14px;padding-top:14px}
  form h4{font-size:14px;margin-bottom:8px}
  .steps{width:100%;justify-content:space-between;gap:2px}
  .st{padding:3px 6px 3px 3px;font-size:10.5px;gap:4px}
  .st i{width:16px;height:16px}
  .grid{grid-template-columns:1fr}
  .left{border-right:0;border-bottom:1px solid var(--line);padding:12px 14px 14px}
  .right{padding:12px 14px 16px}
  :root{--qr:clamp(150px,46vw,200px)}
  .qrframe{margin:12px auto 8px;padding:10px}
  .pane h3{font-size:13.5px;margin-top:2px}
  .hint,.fl{display:none}
  .sum .tot{padding:13px 16px}
  .sum .tot b{font-size:26px}
  .sum .tot span{font-size:12.5px}
  .row{padding:8px 16px;font-size:13px}
  .timer{margin-top:10px}
  form{margin-top:12px}
}
@media (max-width:820px) and (max-height:700px){ :root{--qr:clamp(130px,38vw,170px)} }
@media (max-width:360px){ .st span.lb{display:none} .st.on span.lb{display:inline} }
</style>
</head>
<body>
<div class="card">

  <div class="top">
    <div class="ttl">
      <div class="ic"><span class="mi">qr_code_2</span></div>
      <div><b><?= e($q["title"]) ?></b><small>JOB #<?= (int) $q["job_id"] ?> · SECURE UPI CHECKOUT</small></div>
    </div>
    <div class="steps">
      <div class="st done"><i><span class="mi">check</span></i><span class="lb">Job</span></div>
      <div class="st <?= $step > 2 ? "done" : "on" ?>"><i><?= $step > 2 ? '<span class="mi">check</span>' : "2" ?></i><span class="lb">Pay</span></div>
      <div class="st <?= $step > 3 ? "done" : ($step == 3 ? "on" : "") ?>"><i><?= $step > 3 ? '<span class="mi">check</span>' : "3" ?></i><span class="lb">Verify</span></div>
      <div class="st <?= $step == 4 ? "on" : "" ?>"><i><?= $step == 4 ? '<span class="mi">check</span>' : "4" ?></i><span class="lb">Done</span></div>
    </div>
  </div>

  <div class="grid">

<?php if ($status == "waiting") { ?>

    <!-- LEFT: QR -->
    <section class="left">
<?php if ($split) { ?>
      <div class="tabs">
<?php foreach ($parts as $i => $p) { ?>
        <button type="button" class="tab<?= $i == 0 ? " on" : "" ?>" data-i="<?= $i ?>"><span class="mi"><?= $i == 0 ? "person" : "account_balance" ?></span><?= e($p[6]) ?> ₹<?= e($p[5]) ?></button>
<?php } ?>
      </div>
<?php } ?>
<?php foreach ($parts as $i => $p) { ?>
      <div class="pane<?= $i == 0 ? " on" : "" ?>" data-i="<?= $i ?>">
        <h3><?= $split ? ($i + 1) . ". " : "" ?><?= e($p[0]) ?> · <?= e($p[1]) ?></h3>
        <div class="who"><?= e($p[2]) ?></div>
        <div class="qrframe">
          <span class="c tl"></span><span class="c tr"></span><span class="c bl"></span><span class="c br"></span>
          <div class="scan"></div>
          <div id="qr<?= $i ?>" class="qrb"></div>
        </div>
        <div class="actions">
          <a class="btn" href="<?= e($p[3]) ?>"><span class="mi">smartphone</span>Open my UPI app (on phone)</a>
          <button type="button" class="chip" data-copy="<?= e($p[4]) ?>" title="Copy UPI ID"><span class="mi">content_copy</span>Copy UPI ID</button>
          <button type="button" class="chip" data-copy="<?= e(number_format($p[5], 2, ".", "")) ?>" title="Copy amount"><span class="mi">currency_rupee</span>Copy amount</button>
        </div>
      </div>
<?php } ?>
      <div class="hint"><span class="mi">qr_code_scanner</span><span>Open PhonePe, Google Pay, Paytm or any UPI app → Scan QR → pay exactly the amount shown.</span></div>
    </section>

    <!-- RIGHT: summary + form -->
    <section class="right">
<?php if ($pu === "yourname@upi" || !validUpi($pu)) { ?>
      <div class="err demo"><span class="mi">info</span><span>Demo QR: the admin must add a real UPI ID in "My UPI ID" (or PLATFORM_UPI in settings.php) before real use.</span></div>
<?php } ?>
      <div class="sum">
        <div class="tot"><span><span class="mi">payments</span>Total payable</span><b>₹<?= e($q["amount"]) ?></b></div>
        <div class="row"><span>Platform fee (10%) · <?= $split ? "to platform UPI" : "kept by " . e(PLATFORM_NAME) ?></span><b>₹<?= $fee ?></b></div>
        <div class="row rec"><span>Worker receives<?= $split ? " (directly)" : "" ?></span><b>₹<?= $share ?></b></div>
      </div>

      <div class="timer"><span>Complete payment within</span><b id="tm">10:00</b></div>
      <div class="bar"><i id="tb"></i></div>

      <form method="post" id="pf">
        <h4><?= $split ? "Enter your payment references" : "Confirm your payment" ?></h4>
<?php if ($err) { ?>
        <div class="err"><span class="mi">error_outline</span><span><?= e($err) ?></span></div>
<?php } ?>
<?php if ($split) { ?>
        <label class="fl">Payment 1 · Worker (UTR)</label>
        <div class="fld"><input name="worker_utr" inputmode="numeric" maxlength="12" pattern="[0-9]{12}" placeholder="UPI reference (UTR) of payment 1 – worker" autocomplete="off" required><span class="cnt">0/12</span></div>
        <label class="fl">Payment 2 · Platform fee (UTR)</label>
        <div class="fld"><input name="fee_utr" inputmode="numeric" maxlength="12" pattern="[0-9]{12}" placeholder="UPI reference (UTR) of payment 2 – platform fee" autocomplete="off" required><span class="cnt">0/12</span></div>
<?php } else { ?>
        <label class="fl">12-digit UPI reference number (UTR)</label>
        <div class="fld"><input name="utr" inputmode="numeric" maxlength="12" pattern="[0-9]{12}" placeholder="12-digit UPI reference number (UTR)" autocomplete="off" required><span class="cnt">0/12</span></div>
<?php } ?>
        <button class="pay" id="pb" disabled><span class="mi">check_circle</span>I have paid</button>
      </form>
      <div class="secure"><span class="mi">lock</span>Encrypted · Verified by admin &amp; worker</div>
    </section>

<script src="qrcode.js"></script>
<script>
var L=<?= json_encode(array_column($parts, 3)) ?>;
L.forEach(function(t,i){var qr=qrcode(0,"M");qr.addData(t);qr.make();document.getElementById("qr"+i).innerHTML=qr.createSvgTag(6,0)});

// tabs (split mode)
var tabs=document.querySelectorAll(".tab"),panes=document.querySelectorAll(".pane");
tabs.forEach(function(t){t.onclick=function(){var i=t.dataset.i;
  tabs.forEach(function(x){x.classList.toggle("on",x.dataset.i==i)});
  panes.forEach(function(x){x.classList.toggle("on",x.dataset.i==i)})}});

// copy chips
document.querySelectorAll(".chip").forEach(function(c){c.onclick=function(){
  var v=c.dataset.copy,o=c.innerHTML,done=function(){c.classList.add("copied");c.innerHTML='<span class="mi">check</span>Copied';
    setTimeout(function(){c.classList.remove("copied");c.innerHTML=o},1400)};
  if(navigator.clipboard){navigator.clipboard.writeText(v).then(done,done)}else{done()}}});

// live UTR validation (digits only, counter, enable button)
var ins=document.querySelectorAll(".fld input"),pb=document.getElementById("pb");
function chk(){var ok=true;ins.forEach(function(i){var f=i.parentNode,n=i.value.length;
  f.querySelector(".cnt").textContent=n+"/12";var v=/^\d{12}$/.test(i.value);f.classList.toggle("valid",v);if(!v)ok=false});
  pb.disabled=!ok}
ins.forEach(function(i){i.addEventListener("input",function(){i.value=i.value.replace(/\D/g,"").slice(0,12);chk()})});
chk();

// payment window countdown (visual guide only)
var total=600,left=total,tm=document.getElementById("tm"),tb=document.getElementById("tb");
setInterval(function(){if(left>0)left--;var m=Math.floor(left/60),s=left%60;
  tm.textContent=(m<10?"0":"")+m+":"+(s<10?"0":"")+s;tb.style.width=(left/total*100)+"%";
  if(left==0){tm.parentNode.innerHTML='<span style="color:var(--warn)">Window passed – if you already paid, you can still submit your reference.</span>'}},1000);
</script>

<?php } else { ?>

    <!-- LEFT: status -->
    <section class="left">
      <div class="status">
<?php if ($status == "submitted") { ?>
        <div class="badge wait"><span class="mi">hourglass_top</span></div>
        <h3>Payment submitted</h3>
        <p>You will get a notification once it is confirmed. This page refreshes automatically.</p>
<?php if (!$split) { ?>
        <div class="ref">Ref <?= e($q["utr"]) ?></div>
        <div class="tl"><div><span class="mi y">check_circle</span>Reference submitted</div><div><span class="mi n">hourglass_empty</span>The admin will confirm it shortly</div></div>
<?php } else { ?>
        <div class="tl">
          <div><span class="mi <?= $q["worker_ok"] ? "y" : "n" ?>"><?= $q["worker_ok"] ? "check_circle" : "hourglass_empty" ?></span>Worker <?= $q["worker_ok"] ? "confirmed" : "confirming…" ?></div>
          <div><span class="mi <?= $q["fee_ok"] ? "y" : "n" ?>"><?= $q["fee_ok"] ? "check_circle" : "hourglass_empty" ?></span>Platform <?= $q["fee_ok"] ? "confirmed" : "confirming…" ?></div>
        </div>
<?php } ?>
        <a class="btn" href="job.php?id=<?= $q["job_id"] ?>"><span class="mi">arrow_back</span>Back to job</a>
<?php } elseif ($status == "approved") { ?>
        <div class="badge good"><span class="mi">check</span></div>
        <h3>Payment confirmed</h3>
        <p>Thank you! Your payment has been verified.</p>
        <a class="btn" href="job.php?id=<?= $q["job_id"] ?>"><span class="mi">arrow_back</span>Back to job</a>
<?php } else { ?>
        <div class="badge bad"><span class="mi">close</span></div>
        <h3>Payment not accepted</h3>
        <p>This payment could not be verified. Please try again.</p>
        <a class="btn" href="pay.php?id=<?= $q["job_id"] ?>"><span class="mi">refresh</span>Try again</a>
<?php } ?>
      </div>
    </section>

    <section class="right">
      <div class="sum">
        <div class="tot"><span><span class="mi">payments</span>Total payable</span><b>₹<?= e($q["amount"]) ?></b></div>
        <div class="row"><span>Platform fee (10%) · <?= $split ? "to platform UPI" : "kept by " . e(PLATFORM_NAME) ?></span><b>₹<?= $fee ?></b></div>
        <div class="row rec"><span>Worker receives<?= $split ? " (directly)" : "" ?></span><b>₹<?= $share ?></b></div>
      </div>
      <div class="secure"><span class="mi">lock</span>Encrypted · Verified by admin &amp; worker</div>
    </section>

<?php if ($status == "approved") { ?>
    <div class="conf" id="cf"></div>
    <script>
    (function(){var c=document.getElementById("cf"),col=["#2563eb","#60a5fa","#16a34a","#f59e0b","#a78bfa"];
    for(var i=0;i<46;i++){var e=document.createElement("i");e.style.left=Math.random()*100+"%";e.style.background=col[i%5];
    e.style.animationDuration=(2+Math.random()*2.2)+"s";e.style.animationDelay=(Math.random()*.6)+"s";c.appendChild(e)}
    setTimeout(function(){c.remove()},5200)})();
    </script>
<?php } ?>

<?php } ?>

  </div>
</div>
</body>
</html>
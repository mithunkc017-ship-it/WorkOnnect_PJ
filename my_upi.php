<?php // My UPI ID. Worker: receives the worker's share directly from customers (split payment) and is the account the admin pays when settling. Admin: receives the platform fee (commission) and QR payments.
require "config.php";
need();
$u = me();
if ($u["role"] == "customer") {
    header("Location: dashboard.php");
    exit();
}
$msg = "";
$err = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $v = trim($_POST["upi"] ?? "");
    if ($v !== "" && !validUpi($v)) {
        $err = "Enter a valid UPI ID like name@okhdfcbank";
    } else {
        $pdo->prepare("UPDATE users SET upi_id=? WHERE id=?")->execute([
            $v !== "" ? $v : null,
            $u["id"],
        ]);
        $u["upi_id"] = $v;
        $msg = "UPI ID saved";
        logit("Updated UPI ID");
    }
}
$isAdmin = $u["role"] == "admin";
$display = trim($u["name"] ?? "") !== "" ? $u["name"] : ucfirst($u["role"]);
$initial = strtoupper(mb_substr($display, 0, 1));
head("My UPI ID");
?>
<style>
.upi-wrap{max-width:980px;margin:0 auto;padding:8px 4px 32px;display:grid;grid-template-columns:minmax(0,1.1fr) minmax(0,.9fr);gap:24px;align-items:start}
.upi-card{background:#fff;color:#1c2530;border:1px solid #e3e8ef;border-radius:18px;padding:32px;box-shadow:0 1px 2px rgba(16,24,40,.04),0 12px 32px -12px rgba(16,24,40,.12)}
.upi-head{display:flex;align-items:center;gap:14px;margin-bottom:6px}
.upi-ico{width:46px;height:46px;border-radius:12px;display:grid;place-items:center;background:#eef0ff;color:#4338ca;flex:none}
.upi-ico .material-icons{font-size:24px}
.upi-card h2{margin:0;font-size:22px;line-height:1.2;font-weight:700;color:#111827}
.upi-role{display:inline-block;margin-top:4px;padding:2px 10px;border-radius:999px;font-size:12px;font-weight:600;background:#f1f5f9;color:#475569}
.upi-lead{margin:18px 0 24px;font-size:14.5px;line-height:1.65;color:#475467;max-width:60ch}
.upi-alert{display:flex;align-items:flex-start;gap:10px;padding:12px 14px;border-radius:12px;font-size:14px;line-height:1.5;margin-bottom:20px}
.upi-alert .material-icons{font-size:20px;flex:none}
.upi-alert.ok{background:#ecfdf3;color:#067647;border:1px solid #abefc6}
.upi-alert.err{background:#fef3f2;color:#b42318;border:1px solid #fecdca}
.upi-label{display:block;font-size:13.5px;font-weight:600;color:#344054;margin-bottom:8px}
.upi-field{display:flex;align-items:center;gap:10px;background:#fff;border:1.5px solid #d0d5dd;border-radius:12px;padding:0 14px;transition:border-color .15s,box-shadow .15s}
.upi-field:focus-within{border-color:#4f46e5;box-shadow:0 0 0 4px rgba(79,70,229,.14)}
.upi-field.bad{border-color:#f04438}
.upi-field .material-icons{color:#98a2b3;font-size:20px}
.upi-field input{flex:1;min-width:0;border:0!important;outline:0!important;box-shadow:none!important;background:transparent!important;padding:14px 0!important;margin:0!important;font-size:16px;color:#101828;width:auto}
.upi-field input::placeholder{color:#98a2b3}
.upi-hint{margin:8px 0 0;font-size:12.5px;color:#667085}
.upi-actions{display:flex;align-items:center;gap:14px;margin-top:24px;flex-wrap:wrap}
.upi-btn{display:inline-flex;align-items:center;gap:8px;border:0;cursor:pointer;background:#4f46e5;color:#fff;font-size:15px;font-weight:600;padding:13px 24px;border-radius:12px;box-shadow:0 1px 2px rgba(16,24,40,.1),0 6px 14px -6px rgba(79,70,229,.55);transition:background .15s,transform .05s;width:auto}
.upi-btn:hover{background:#4338ca}
.upi-btn:active{transform:translateY(1px)}
.upi-btn:focus-visible{outline:3px solid #a5b4fc;outline-offset:2px}
.upi-btn .material-icons{font-size:18px}
.upi-note{font-size:12.5px;color:#667085;display:flex;align-items:center;gap:6px}
.upi-note .material-icons{font-size:16px;color:#12b76a}
.upi-side{display:flex;flex-direction:column;gap:20px}
.upi-pay{border-radius:18px;padding:24px;color:#fff;background:linear-gradient(145deg,#312e81 0%,#4338ca 55%,#6366f1 100%);box-shadow:0 18px 36px -16px rgba(67,56,202,.6)}
.upi-pay small{display:block;font-size:12.5px;opacity:.8;margin-bottom:14px}
.upi-who{display:flex;align-items:center;gap:12px;margin-bottom:18px}
.upi-av{width:44px;height:44px;border-radius:50%;background:rgba(255,255,255,.18);display:grid;place-items:center;font-weight:700;font-size:18px}
.upi-who b{display:block;font-size:16px}
.upi-who span{font-size:12.5px;opacity:.8}
.upi-id{background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.22);border-radius:12px;padding:14px 16px;font-size:17px;font-weight:600;letter-spacing:.2px;word-break:break-all;display:flex;align-items:center;gap:10px}
.upi-id .material-icons{font-size:20px}
.upi-id.empty{font-weight:400;opacity:.75;font-size:15px}
.upi-info{background:#fff;color:#1c2530;border:1px solid #e3e8ef;border-radius:18px;padding:22px 24px}
.upi-info h3{margin:0 0 14px;font-size:15px;font-weight:700;color:#111827}
.upi-info ul{list-style:none;margin:0;padding:0;display:grid;gap:14px}
.upi-info li{display:flex;gap:12px;font-size:13.5px;line-height:1.55;color:#475467}
.upi-info li .material-icons{font-size:20px;color:#4f46e5;flex:none;margin-top:1px}
@media (max-width:820px){.upi-wrap{grid-template-columns:1fr}.upi-card{padding:24px 20px}}
@media (prefers-reduced-motion:reduce){.upi-field,.upi-btn{transition:none}}
</style>

<div class="upi-wrap">
  <form class="upi-card" method="post" autocomplete="off">
    <div class="upi-head">
      <div class="upi-ico"><span class="material-icons">credit_card</span></div>
      <div>
        <h2>My UPI ID</h2>
        <span class="upi-role"><?= $isAdmin ? "Admin account" : "Worker account" ?></span>
      </div>
    </div>

    <p class="upi-lead">
      <?= $isAdmin
          ? "The platform fee (10% commission) from customer payments and the QR payments are received on this UPI ID. It is shown to customers when they scan the QR code."
          : "Customers can pay your share directly to this UPI ID (the platform fee is paid separately to the platform). The admin also uses it to pay your earnings." ?>
    </p>

    <?php if ($msg !== "") { ?>
      <div class="upi-alert ok ok-msg" role="status">
        <span class="material-icons">verified</span><span><?= e($msg) ?></span>
      </div>
    <?php } ?>
    <?php if ($err !== "") { ?>
      <div class="upi-alert err" role="alert">
        <span class="material-icons">error_outline</span><span><?= e($err) ?></span>
      </div>
    <?php } ?>

    <label class="upi-label" for="upi">UPI ID</label>
    <div class="upi-field<?= $err !== "" ? " bad" : "" ?>">
      <span class="material-icons">alternate_email</span>
      <input id="upi" name="upi" value="<?= e($u["upi_id"] ?? "") ?>" placeholder="yourname@okhdfcbank" autocomplete="off" inputmode="email" autocapitalize="off" spellcheck="false">
    </div>
    <p class="upi-hint">Format: name@bankhandle, for example name@okhdfcbank. Leave it empty to remove your UPI ID.</p>

    <div class="upi-actions">
      <button class="upi-btn"><span class="material-icons">check_circle</span>Save UPI ID</button>
      <span class="upi-note"><span class="material-icons">lock</span>Only you can change this</span>
    </div>
  </form>

  <aside class="upi-side">
    <div class="upi-pay" aria-live="polite">
      <small>How customers see it</small>
      <div class="upi-who">
        <div class="upi-av"><?= e($initial) ?></div>
        <div><b><?= e($display) ?></b><span><?= $isAdmin ? "Platform" : "Worker" ?></span></div>
      </div>
      <?php $cur = $u["upi_id"] ?? ""; ?>
      <div class="upi-id<?= $cur === "" ? " empty" : "" ?>" id="upiPreview">
        <span class="material-icons">account_balance_wallet</span>
        <span id="upiPreviewText"><?= $cur !== "" ? e($cur) : "No UPI ID added yet" ?></span>
      </div>
    </div>

    <div class="upi-info">
      <h3>Good to know</h3>
      <ul>
        <li><span class="material-icons">bolt</span><span><?= $isAdmin ? "Payments reach your bank account directly through UPI." : "Your share goes straight to you, with no waiting for settlement." ?></span></li>
        <li><span class="material-icons">fact_check</span><span>Double-check the ID. A wrong UPI ID sends money to the wrong person.</span></li>
        <li><span class="material-icons">history</span><span>Every change to your UPI ID is recorded in the activity log.</span></li>
      </ul>
    </div>
  </aside>
</div>

<script>
(function(){
  var i=document.getElementById('upi'),t=document.getElementById('upiPreviewText'),b=document.getElementById('upiPreview');
  if(!i||!t||!b)return;
  i.addEventListener('input',function(){
    var v=i.value.trim();
    t.textContent=v||'No UPI ID added yet';
    b.classList.toggle('empty',!v);
  });
})();
</script>
<?php foot(); ?>
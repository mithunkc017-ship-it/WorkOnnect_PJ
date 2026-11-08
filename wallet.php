<?php // Customer wallet: demo top-up, balance, history and referral code
require "config.php";
need("customer");
$u = me();
if (!$u["ref_code"]) {
    $rc = "WK" . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
    $pdo->prepare("UPDATE users SET ref_code=? WHERE id=?")->execute([
        $rc,
        $u["id"],
    ]);
    $u["ref_code"] = $rc;
}
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $a = (int) $_POST["amt"];
    if ($a >= 10 && $a <= 10000) {
        wallet($pdo, $u["id"], $a, "Wallet top-up (demo)");
        logit("Wallet top-up ₹" . $a);
    }
    header("Location: wallet.php");
    exit();
}
head("Wallet");
echo d_top("wallet.php");
?>
<style>
/* ===== Wallet page animations & styling (scoped to .wk) ===== */
.wk{--wk-a:#6d5efc;--wk-b:#00c2ff;--wk-g:#0a7a3a;--wk-r:#c00;max-width:900px}
.wk *{box-sizing:border-box}
.wk-title{display:flex;align-items:center;gap:10px;margin:0 0 18px;animation:wkDrop .6s cubic-bezier(.2,.8,.2,1) both}
.wk-title .mi{width:46px;height:46px;border-radius:14px;display:grid;place-items:center;color:#fff;font-size:26px;
  background:linear-gradient(135deg,var(--wk-a),var(--wk-b));box-shadow:0 8px 20px rgba(109,94,252,.35);animation:wkFloat 3.5s ease-in-out infinite}

/* Balance card */
.wk-balance{position:relative;overflow:hidden;border:0!important;color:#fff;padding:26px 24px!important;border-radius:20px!important;
  background:linear-gradient(120deg,#4f46e5,#6d5efc,#00a8ff,#4f46e5);background-size:300% 300%;
  animation:wkRise .7s cubic-bezier(.2,.8,.2,1) both,wkFlow 10s ease infinite;
  box-shadow:0 18px 40px rgba(79,70,229,.35)}
.wk-balance::before,.wk-balance::after{content:"";position:absolute;border-radius:50%;background:rgba(255,255,255,.12);pointer-events:none}
.wk-balance::before{width:220px;height:220px;right:-60px;top:-80px;animation:wkPulse 6s ease-in-out infinite}
.wk-balance::after{width:140px;height:140px;left:-40px;bottom:-60px;animation:wkPulse 7s ease-in-out infinite reverse}
.wk-balance>*{position:relative;z-index:1}
.wk-label{font-size:13px;opacity:.85;letter-spacing:.3px;margin-bottom:2px}
.wk-balance h1{margin:0 0 18px;font-size:clamp(36px,8vw,54px);color:#fff;line-height:1.1;text-shadow:0 4px 18px rgba(0,0,0,.2)}
.wk-balance small{display:block;margin-top:12px;opacity:.85}

/* Top-up form */
.wk-form{display:flex;gap:8px;flex-wrap:wrap}
.wk-form input{flex:1 1 200px;padding:13px 14px;border-radius:12px;border:2px solid transparent;background:rgba(255,255,255,.95);
  color:#222;font-size:15px;outline:none;transition:border-color .25s,box-shadow .25s,transform .25s}
.wk-form input:focus{border-color:#fff;box-shadow:0 0 0 4px rgba(255,255,255,.35);transform:translateY(-2px)}
.wk-form button{position:relative;overflow:hidden;padding:13px 22px;border:0;border-radius:12px;cursor:pointer;font-weight:700;font-size:15px;
  background:#fff;color:#4f46e5;transition:transform .2s,box-shadow .2s}
.wk-form button:hover{transform:translateY(-3px);box-shadow:0 10px 22px rgba(0,0,0,.25)}
.wk-form button:active{transform:scale(.96)}
.wk-form button::after{content:"";position:absolute;top:0;left:-80%;width:50%;height:100%;
  background:linear-gradient(90deg,transparent,rgba(109,94,252,.35),transparent);transform:skewX(-20deg);animation:wkShine 3s ease-in-out infinite}
.wk-ripple{position:absolute;border-radius:50%;background:rgba(79,70,229,.35);transform:scale(0);animation:wkRipple .6s linear;pointer-events:none}

/* Referral card */
.wk-ref{margin:16px 0!important;padding:18px 20px!important;border-radius:18px!important;position:relative;overflow:hidden;
  background:linear-gradient(135deg,#fff7e6,#ffe9f3);border:1px dashed #ff9ec4!important;color:#442;
  animation:wkRise .7s .15s cubic-bezier(.2,.8,.2,1) both;transition:transform .3s,box-shadow .3s}
.wk-ref:hover{transform:translateY(-4px);box-shadow:0 14px 30px rgba(255,120,170,.25)}
.wk-ref .mi{color:#ff4f93;vertical-align:middle;animation:wkWiggle 3s ease-in-out infinite;transform-origin:50% 80%}
.wk-code{display:inline-block;margin:4px 0;padding:4px 12px;border-radius:10px;font-size:18px;letter-spacing:2px;cursor:pointer;
  background:#fff;color:#d6246e;border:2px dashed #ff9ec4;transition:transform .2s,background .2s;user-select:all}
.wk-code:hover{transform:scale(1.07) rotate(-1.5deg);background:#fff0f6}
.wk-code.copied{background:#d9f7e4;color:var(--wk-g);border-color:#7fd8a1}

/* History */
.wk h3{margin:26px 0 10px;animation:wkRise .6s .25s both}
.wk-table-wrap{overflow-x:auto;border-radius:16px;box-shadow:0 8px 24px rgba(0,0,0,.08);animation:wkRise .7s .3s both}
.wk table{width:100%;border-collapse:separate;border-spacing:0;background:#fff;border-radius:16px;overflow:hidden}
.wk th{background:linear-gradient(90deg,#4f46e5,#00a8ff);color:#fff;padding:13px 14px;text-align:left;font-weight:600}
.wk td{padding:12px 14px;border-bottom:1px solid #eef0f6;transition:background .25s}
.wk tr.wk-row{opacity:0;animation:wkSlide .5s cubic-bezier(.2,.8,.2,1) forwards}
.wk tr.wk-row:hover td{background:#f4f2ff}
.wk tr.wk-row:hover .wk-amt{transform:scale(1.12)}
.wk-amt{display:inline-block;font-weight:700;transition:transform .25s;transform-origin:left center}
.wk-in{animation:wkPop .6s both}

/* Keyframes */
@keyframes wkRise{from{opacity:0;transform:translateY(28px) scale(.98)}to{opacity:1;transform:none}}
@keyframes wkDrop{from{opacity:0;transform:translateY(-18px)}to{opacity:1;transform:none}}
@keyframes wkSlide{from{opacity:0;transform:translateX(-24px)}to{opacity:1;transform:none}}
@keyframes wkFlow{0%{background-position:0% 50%}50%{background-position:100% 50%}100%{background-position:0% 50%}}
@keyframes wkPulse{0%,100%{transform:scale(1)}50%{transform:scale(1.18)}}
@keyframes wkFloat{0%,100%{transform:translateY(0)}50%{transform:translateY(-5px)}}
@keyframes wkShine{0%,60%{left:-80%}100%{left:140%}}
@keyframes wkWiggle{0%,86%,100%{transform:rotate(0)}90%{transform:rotate(-14deg)}94%{transform:rotate(12deg)}97%{transform:rotate(-6deg)}}
@keyframes wkRipple{to{transform:scale(4);opacity:0}}
@keyframes wkPop{0%{transform:scale(.85);opacity:0}60%{transform:scale(1.06)}100%{transform:scale(1);opacity:1}}

@media (prefers-reduced-motion:reduce){
  .wk *,.wk *::before,.wk *::after{animation-duration:.01ms!important;animation-iteration-count:1!important;transition-duration:.01ms!important}
  .wk tr.wk-row{opacity:1}
}
@media (max-width:520px){.wk-form button{width:100%}}
</style>

<div class="wk">
<h2 class="wk-title"><span class="material-icons mi">account_balance_wallet</span> My Wallet</h2>

<div class="card wk-balance">
<div class="wk-label">Available balance</div>
<h1 id="wkBal" data-val="<?= (int) $u["wallet"] ?>">₹<?= (int) $u[
    "wallet"
] ?></h1>
<form method="post" class="wk-form"><input type="number" name="amt" min="10" max="10000" placeholder="Amount (₹10 – ₹10,000)" required><button>Add money (demo)</button></form><small>Demo top-up: no real money is used.</small></div>

<div class="card wk-ref"><span class="material-icons mi">card_giftcard</span> Your referral code: <b class="wk-code" id="wkCode" title="Click to copy"><?= e(
    $u["ref_code"],
) ?></b><br><small>When a friend registers with your code and pays for their first job, you both get ₹50 in your wallet.</small></div>

<h3>History</h3><div class="wk-table-wrap"><table><tr><th>Date</th><th>Note</th><th>Amount</th></tr>
<?php
$s = $pdo->prepare(
    "SELECT * FROM wallet_tx WHERE user_id=? ORDER BY id DESC LIMIT 50",
);
$s->execute([$u["id"]]);
$i = 0;
foreach ($s as $x) {
    echo '<tr class="wk-row" style="animation-delay:' .
        min($i * 0.06, 1.5) .
        's"><td>' .
        $x["created"] .
        "</td><td>" .
        e($x["note"]) .
        '</td><td><span class="wk-amt" style="color:' .
        ($x["amount"] < 0 ? "#c00" : "#0a7a3a") .
        '">' .
        ($x["amount"] < 0 ? "-" : "+") .
        "₹" .
        abs($x["amount"]) .
        "</span></td></tr>";
    $i++;
}
?></table></div>
</div>

<script>
(function () {
  var reduce = window.matchMedia && matchMedia("(prefers-reduced-motion: reduce)").matches;

  // Count-up animation for the balance
  var bal = document.getElementById("wkBal");
  if (bal && !reduce) {
    var target = parseInt(bal.getAttribute("data-val"), 10) || 0;
    var start = null, dur = 1100;
    function step(ts) {
      if (start === null) start = ts;
      var p = Math.min((ts - start) / dur, 1);
      var eased = 1 - Math.pow(1 - p, 3);
      bal.textContent = "₹" + Math.round(target * eased);
      if (p < 1) requestAnimationFrame(step);
      else bal.classList.add("wk-in");
    }
    bal.textContent = "₹0";
    requestAnimationFrame(step);
  }

  // Ripple effect on the button (does not affect form submit)
  var btn = document.querySelector(".wk-form button");
  if (btn) {
    btn.addEventListener("click", function (e) {
      var r = btn.getBoundingClientRect(), d = Math.max(r.width, r.height);
      var s = document.createElement("span");
      s.className = "wk-ripple";
      s.style.width = s.style.height = d + "px";
      s.style.left = e.clientX - r.left - d / 2 + "px";
      s.style.top = e.clientY - r.top - d / 2 + "px";
      btn.appendChild(s);
      setTimeout(function () { s.remove(); }, 600);
    });
  }

  // Click referral code to copy
  var code = document.getElementById("wkCode");
  if (code) {
    code.addEventListener("click", function () {
      var txt = code.textContent.trim(), old = txt;
      function done() {
        code.classList.add("copied");
        code.textContent = "Copied!";
        setTimeout(function () { code.classList.remove("copied"); code.textContent = old; }, 1200);
      }
      if (navigator.clipboard) navigator.clipboard.writeText(txt).then(done, function () {});
    });
  }
})();
</script>
<?php foot();
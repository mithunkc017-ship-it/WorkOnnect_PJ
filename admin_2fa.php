<?php // Login code page (all roles): after the password the user enters the 6-digit code e-mailed to them
// (valid 5 minutes, max 5 wrong tries, 60 s resend cooldown). The login session is created ONLY here, after a correct code.
require "config.php";
$p = $_SESSION["pre2fa"] ?? null;
if (!$p || $p["t"] < time() - 600) {
    unset($_SESSION["pre2fa"], $_SESSION["otp_notice"]);
    header("Location: login.php");
    exit();
}
$s = $pdo->prepare("SELECT * FROM users WHERE id=?");
$s->execute([$p["id"]]);
$u = $s->fetch();
if (!$u || $u["banned"]) {
    unset($_SESSION["pre2fa"], $_SESSION["otp_notice"]);
    header("Location: login.php");
    exit();
}
$err = "";
$msg = $_SESSION["otp_notice"] ?? "";
unset($_SESSION["otp_notice"]);
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // (the CSRF token is checked for every POST in config.php)
    if (isset($_POST["resend"])) {
        $r = login_otp_issue($pdo, $u);
        if ($r["ok"]) {
            $msg = "A new code has been sent. The old code no longer works.";
            $_SESSION["pre2fa"]["t"] = time();
        } else {
            $err = $r["err"];
        }
    } else {
        $r = login_otp_check($pdo, $u["id"], $_POST["code"] ?? "");
        if ($r["ok"]) {
            unset($_SESSION["pre2fa"], $_SESSION["otp_notice"]);
            session_regenerate_id(true);
            $_SESSION["u"] = $u;
            logit("Logged in (e-mail code)", $u["id"]);
            lh($pdo, $u["id"], $u["email"], 1, "login (code ok)");
            $pdo->prepare("DELETE FROM login_attempts WHERE ip=?")->execute([
                $_SERVER["REMOTE_ADDR"],
            ]);
            header("Location: dash.php");
            exit();
        }
        $err = $r["err"];
        if ($r["state"] != "format") {
            lh($pdo, $u["id"], $u["email"], 0, "wrong or expired code");
        }
    }
}

// Display only: masked e-mail + seconds left on the client-side countdowns
$em = (string) ($u["email"] ?? "");
$at = strpos($em, "@");
$masked =
    $at !== false && $at > 0
        ? substr($em, 0, 1) .
            str_repeat("•", max(1, min(6, $at - 1))) .
            substr($em, $at)
        : $em;
$age = max(0, time() - (int) ($_SESSION["pre2fa"]["t"] ?? time()));
$left = max(0, 300 - $age);
$wait = max(0, 60 - $age);

// Optional "magic link" prefill: login_code.php?c=123456 (put this link in the e-mail).
// It only fills the box and submits the normal form; the pre-login session above is still required.
$pre =
    $_SERVER["REQUEST_METHOD"] == "GET" &&
    preg_match('/^\d{6}$/', (string) ($_GET["c"] ?? ""))
        ? $_GET["c"]
        : "";

head("Verification");
auth_open(
    "Verification",
    "Check your e-mail.",
    "Enter the 6-digit code we sent to your e-mail address. It is valid for 5 minutes.",
);
?>
<style>
.otp-page{--ac:#6366f1;--ac2:#8b5cf6;--ok:#10b981;--bad:#ef4444;--line:rgba(128,128,128,.28);
  max-width:300px;margin:0 auto;animation:otpIn .5s cubic-bezier(.2,.8,.2,1) both}
@keyframes otpIn{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:none}}

/* small mail icon */
.otp-icon{position:relative;width:38px;height:38px;margin:0 auto 10px;border-radius:50%;display:grid;place-items:center;
  color:#fff;background:linear-gradient(135deg,var(--ac),var(--ac2));box-shadow:0 6px 14px -6px rgba(99,102,241,.7)}
.otp-icon::before{content:"";position:absolute;inset:0;border-radius:50%;border:2px solid var(--ac);opacity:0;animation:otpPulse 2.4s ease-out infinite}
@keyframes otpPulse{0%{transform:scale(1);opacity:.5}100%{transform:scale(1.6);opacity:0}}
.otp-icon svg{width:18px;height:18px}

.otp-badge{display:flex;align-items:center;justify-content:center;margin:0 auto 14px;padding:5px 12px;width:fit-content;max-width:100%;
  border-radius:999px;background:rgba(99,102,241,.09);border:1px solid rgba(99,102,241,.22);font-size:.78rem;word-break:break-all}
.otp-badge b{font-weight:600;margin-left:4px}

.otp-page p.err:empty{display:none}
.otp-page p.err,.otp-page p.ok{margin:0 0 10px;padding:7px 11px;border-radius:10px;font-size:.8rem;animation:otpIn .4s both}
.otp-page p.err{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3)}
.otp-page p.ok{background:rgba(16,185,129,.1);border:1px solid rgba(16,185,129,.3)}

/* small code boxes (one real input sits on top, so autofill / paste / WebOTP keep working) */
.otp-label{display:block;text-align:center;font-size:.68rem;font-weight:600;letter-spacing:.08em;text-transform:uppercase;opacity:.6;margin-bottom:7px}
.otp-field{position:relative}
.otp-input{width:100%;box-sizing:border-box;text-align:center;font:600 1.3rem/1.2 ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;
  letter-spacing:.5em;padding:9px 6px 9px calc(6px + .5em);border-radius:10px;border:2px solid var(--line);background:transparent;color:inherit;
  outline:none;transition:border-color .2s,box-shadow .2s}
.otp-input:focus{border-color:var(--ac);box-shadow:0 0 0 3px rgba(99,102,241,.16)}
.otp-slots{display:none}
.otp-field.js .otp-slots{display:grid;grid-template-columns:repeat(6,34px);gap:6px;justify-content:center}
.otp-field.js .otp-input{position:absolute;inset:0;height:100%;opacity:0;cursor:text;z-index:2;font-size:16px}
.otp-slot{height:40px;display:grid;place-items:center;border-radius:10px;border:2px solid var(--line);
  font:700 1.15rem ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;transition:border-color .2s,box-shadow .2s,transform .2s,background .2s}
.otp-slot.on{border-color:var(--ac);box-shadow:0 0 0 3px rgba(99,102,241,.16);transform:translateY(-1px)}
.otp-slot.on:empty::after{content:"";width:2px;height:1.1em;background:var(--ac);animation:otpBlink 1s steps(1) infinite}
@keyframes otpBlink{50%{opacity:0}}
.otp-slot.fill{background:rgba(99,102,241,.07);animation:otpPop .22s ease-out}
@keyframes otpPop{50%{transform:scale(1.1)}}
.otp-field.full .otp-slot{border-color:var(--ok);background:rgba(16,185,129,.08);box-shadow:none;transform:none}
.otp-field.full .otp-input{border-color:var(--ok)}
.otp-field.bad .otp-slots,.otp-field.bad .otp-input{animation:otpShake .4s}
.otp-field.bad .otp-slot{border-color:var(--bad)}
@keyframes otpShake{20%,60%{transform:translateX(-6px)}40%,80%{transform:translateX(6px)}}

/* auto-fetch status + paste chip */
.otp-auto{display:flex;align-items:center;justify-content:center;gap:8px;margin:10px 0 0;font-size:.74rem;opacity:.8;min-height:26px}
.otp-dots{display:inline-flex;gap:3px}
.otp-dots i{width:5px;height:5px;border-radius:50%;background:var(--ac);animation:otpDot 1.2s ease-in-out infinite}
.otp-dots i:nth-child(2){animation-delay:.15s}.otp-dots i:nth-child(3){animation-delay:.3s}
@keyframes otpDot{0%,80%,100%{transform:scale(.6);opacity:.4}40%{transform:scale(1);opacity:1}}
.otp-auto.done{color:var(--ok);opacity:1;font-weight:600}
.otp-auto.done .otp-dots{display:none}
.otp-paste{display:none;align-items:center;gap:5px;padding:4px 10px;border-radius:999px;border:1px solid rgba(99,102,241,.35);
  background:rgba(99,102,241,.08);color:var(--ac);font:600 .72rem/1 inherit;font-family:inherit;cursor:pointer;transition:transform .15s,background .2s}
.otp-paste svg{width:12px;height:12px}
.otp-paste:hover{background:rgba(99,102,241,.16);transform:translateY(-1px)}
.otp-paste:active{transform:scale(.95)}
.otp-field.js ~ .otp-auto .otp-paste{display:inline-flex}

/* timer */
.otp-bar{height:3px;border-radius:3px;background:rgba(128,128,128,.18);overflow:hidden;margin:12px 0 6px}
.otp-bar i{display:block;height:100%;width:100%;background:linear-gradient(90deg,var(--ac),var(--ac2));transform-origin:left;transition:transform 1s linear}
.otp-bar.low i{background:linear-gradient(90deg,#f59e0b,var(--bad))}
.otp-meta{display:flex;justify-content:space-between;gap:8px;font-size:.72rem;opacity:.7;margin-bottom:14px;font-variant-numeric:tabular-nums}

/* small animated buttons */
.otp-page .cta,.otp-page .ghost{position:relative;display:flex;align-items:center;justify-content:center;gap:7px;
  box-sizing:border-box;font-family:inherit;cursor:pointer;overflow:hidden;
  transition:transform .15s ease,box-shadow .25s ease,background .25s ease,border-color .25s ease,color .25s ease}
/* Verify: full width of the card, clean and evenly padded */
.otp-page .cta{width:100%;height:42px;padding:0 16px;border-radius:11px;font-size:.92rem;font-weight:600;line-height:1;letter-spacing:.02em}
.otp-page .cta span{display:block}
.otp-page .cta .go{position:static!important;display:grid!important;place-items:center;flex:none;width:26px;height:26px;margin:0!important;padding:0!important;
  border-radius:8px;background:rgba(255,255,255,.22);box-shadow:inset 0 0 0 1px rgba(255,255,255,.3);line-height:0;font-size:0;
  transition:transform .25s ease,background .25s ease}
.otp-page .cta .go svg{display:block;width:16px;height:16px;margin:0;flex:none}
.otp-page .cta:hover .go{background:rgba(255,255,255,.34)}
.otp-page .cta{border:0;color:#fff;background:linear-gradient(120deg,var(--ac),var(--ac2),var(--ac));background-size:200% 100%;
  box-shadow:0 6px 14px -8px rgba(99,102,241,.8);animation:otpGrad 6s linear infinite}
@keyframes otpGrad{to{background-position:200% 0}}
.otp-page .cta::before{content:"";position:absolute;top:0;left:-70%;width:50%;height:100%;
  background:linear-gradient(100deg,transparent,rgba(255,255,255,.4),transparent);transform:skewX(-20deg);transition:left .6s ease}
.otp-page .cta:hover{transform:translateY(-1px);box-shadow:0 10px 18px -8px rgba(99,102,241,.9)}
.otp-page .cta:hover::before{left:130%}
.otp-page .cta .go{display:inline-block;font-weight:700;transition:transform .25s ease}
.otp-page .cta:hover .go{transform:translateX(5px)}
.otp-page .cta:active{transform:scale(.96)}
/* Resend: small pill, centered under Verify */
.otp-page .ghost{width:fit-content;height:28px;margin:10px auto 0;padding:0 12px;gap:5px;border-radius:999px;background:transparent;color:inherit;
  border:1.5px solid var(--line);font-weight:500;font-size:.72rem;line-height:1}
.otp-page .ghost svg{width:12px;height:12px;transition:transform .5s ease}
.otp-page .ghost:hover{border-color:var(--ac);color:var(--ac);background:rgba(99,102,241,.06)}
.otp-page .ghost:hover svg{transform:rotate(360deg)}
.otp-page .ghost:active{transform:scale(.96)}
.otp-page button:focus-visible{outline:3px solid rgba(99,102,241,.45);outline-offset:2px}

@media (max-width:340px){.otp-field.js .otp-slots{grid-template-columns:repeat(6,30px);gap:5px}.otp-slot{height:36px}}
@media (prefers-reduced-motion:reduce){.otp-page,.otp-page *,.otp-page *::before,.otp-page *::after{animation:none!important;transition:none!important}}
</style>

<div class="otp-page">
  <div class="otp-icon" aria-hidden="true">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="3"/><path d="m4 7 8 6 8-6"/></svg>
  </div>
  <div class="otp-badge" aria-live="polite">Sent to <b><?= e($masked) ?></b></div>

  <p class="err"><?= e($err) ?></p><?= $msg
      ? '<p class="ok">' . e($msg) . "</p>"
      : "" ?>

  <label class="otp-label" for="otp-code">Verification code</label>
  <div class="otp-field<?= $err ? " bad" : "" ?>" id="otp-field">
    <div class="otp-slots" aria-hidden="true">
      <div class="otp-slot"></div><div class="otp-slot"></div><div class="otp-slot"></div>
      <div class="otp-slot"></div><div class="otp-slot"></div><div class="otp-slot"></div>
    </div>
    <input id="otp-code" class="otp-input" name="code" placeholder="6-digit code"
           inputmode="numeric" maxlength="6" pattern="[0-9]{6}" autocomplete="one-time-code"
           autocapitalize="off" spellcheck="false" enterkeyhint="done" value="<?= e($pre) ?>" required autofocus>
  </div>

  <div class="otp-auto" id="otp-auto" aria-live="polite">
    <span class="otp-dots"><i></i><i></i><i></i></span>
    <span id="otp-auto-txt">Enter the code or tap the e-mail button</span>
  </div>

  <div class="otp-bar" id="otp-bar" role="presentation"><i></i></div>
  <div class="otp-meta">
    <span id="otp-exp">Valid for 5:00</span>
    <span id="otp-wait"></span>
  </div>

  <button class="cta" data-busy="<?= e(
      t("Verifying…"),
  ) ?>"><span>Verify</span><b class="go" aria-hidden="true"><svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M12 4l-1.41 1.41L16.17 11H4v2h12.17l-5.58 5.59L12 20l8-8z"/></svg></b></button>
  <button class="ghost" name="resend" value="1" formnovalidate>
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a9 9 0 1 1-3-6.7"/><path d="M21 3v6h-6"/></svg>
    <span>Resend code</span>
  </button>
</div>

<script>
(function () {
  var inp = document.getElementById("otp-code");
  if (!inp) return;
  var form = inp.form, field = document.getElementById("otp-field"), submitted = false;
  var slots = field.querySelectorAll(".otp-slot");
  var bar = document.getElementById("otp-bar"), barI = bar.firstElementChild;
  var expEl = document.getElementById("otp-exp"), waitEl = document.getElementById("otp-wait");
  var auto = document.getElementById("otp-auto"), autoTxt = document.getElementById("otp-auto-txt");
  var left = <?= (int) $left ?>, wait = <?= (int) $wait ?>, total = 300;

  field.classList.add("js"); // enables the 6-box look; without JS the plain input stays visible

  function fmt(s) { return Math.floor(s / 60) + ":" + ("0" + (s % 60)).slice(-2); }
  function tick() {
    expEl.textContent = left > 0 ? "Valid for " + fmt(left) : "Code expired — resend";
    barI.style.transform = "scaleX(" + Math.max(0, left / total) + ")";
    bar.classList.toggle("low", left <= 60);
    waitEl.textContent = wait > 0 ? "Resend in " + wait + "s" : "";
    if (left > 0) left--;
    if (wait > 0) wait--;
  }
  tick();
  setInterval(tick, 1000);

  function render() {
    var v = inp.value.replace(/\D/g, "").slice(0, 6);
    if (v !== inp.value) inp.value = v;
    var focused = document.activeElement === inp;
    for (var i = 0; i < 6; i++) {
      var s = slots[i], was = s.classList.contains("fill");
      s.textContent = v.charAt(i);
      s.classList.toggle("on", focused && (i === v.length || (v.length === 6 && i === 5)));
      s.classList.toggle("fill", i < v.length);
      if (i < v.length && !was) { s.style.animation = "none"; void s.offsetWidth; s.style.animation = ""; }
    }
    field.classList.toggle("full", v.length === 6);
    if (v.length) field.classList.remove("bad");
    return v;
  }
  function filled(src) {
    auto.classList.add("done");
    autoTxt.textContent = "✓ Code " + (src || "filled") + " — verifying…";
  }
  function setCode(code, src) {
    inp.value = code;
    render();
    filled(src);
    autoSubmit();
  }
  function autoSubmit() {
    if (submitted || render().length !== 6) return;
    submitted = true;
    if (form.requestSubmit) form.requestSubmit(); else form.submit();
  }

  ["input", "change"].forEach(function (ev) { inp.addEventListener(ev, autoSubmit); });
  ["focus", "blur", "keyup"].forEach(function (ev) { inp.addEventListener(ev, render); });
  inp.addEventListener("paste", function () { setTimeout(autoSubmit, 0); });
  field.addEventListener("click", function () { inp.focus(); });
  render();

  // 1) Link from the e-mail (?c=123456) arrives pre-filled -> submit straight away
  if (inp.value.length === 6) {
    // remove ?c=… from the address bar so the code does not stay in the URL / history
    try { history.replaceState(null, "", location.pathname); } catch (e) {}
    filled("from e-mail link");
    autoSubmit();
  }

  // 2) SMS auto-read (WebOTP, Chrome on Android): works for SMS ending with "@yourdomain #123456"
  if ("OTPCredential" in window) {
    var ac = new AbortController();
    form.addEventListener("submit", function () { ac.abort(); });
    navigator.credentials.get({ otp: { transport: ["sms"] }, signal: ac.signal })
      .then(function (o) { if (o && o.code) setCode(o.code, "received"); })
      .catch(function () {});
  }

  window.addEventListener("pageshow", function () { submitted = false; });
})();
</script>
<?php
auth_close('<a href="login.php">Back to login</a>');
foot();
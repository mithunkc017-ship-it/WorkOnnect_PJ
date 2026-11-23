<?php // Create account (customer or worker) - two-column card, e-mail + phone codes verified on this page, confirm password, strength meter and terms
require "config.php";
$err = "";
$v = function ($k) {
    return e($_POST[$k] ?? "");
};
$role = $_POST["role"] ?? "customer";
$kn = ($_SESSION["lang"] ?? "en") == "kn";
// Codes are checked by register_otp.php. Turn the phone code off with define("REG_PHONE_OTP", false); in settings.php.
$needPhone = !defined("REG_PHONE_OTP") || REG_PHONE_OTP;
$vo = $_SESSION["reg_otp"] ?? [];
$vEmail = !empty($vo["email"]["ok"]) ? (string) $vo["email"]["target"] : "";
$vPhone = !empty($vo["phone"]["ok"]) ? (string) $vo["phone"]["target"] : "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $pw = $_POST["password"] ?? "";
    if ($role == "worker" && ($_POST["skill"] ?? "") == "") {
        $err = "Workers must choose a skill.";
    } elseif (!preg_match('/^[0-9]{10}$/', trim($_POST["phone"] ?? ""))) {
        $err = "Enter a valid 10-digit phone number.";
    } elseif (!filter_var(trim($_POST["email"] ?? ""), FILTER_VALIDATE_EMAIL)) {
        $err = "Enter a valid email address.";
    } elseif (strlen($pw) < 6) {
        $err = "Password must be at least 6 characters.";
    } elseif ($pw !== ($_POST["password2"] ?? "")) {
        $err = "Passwords do not match.";
    } elseif (empty($_POST["terms"])) {
        $err = "Please accept the Terms and Privacy Policy.";
    } elseif ($needPhone && !hash_equals($vPhone, trim($_POST["phone"] ?? ""))) {
        $err = "Please verify your phone number with the code we send.";
    } else {
        try {
            $rb = $pdo->prepare("SELECT id FROM users WHERE ref_code=?");
            $rb->execute([strtoupper(trim($_POST["ref"] ?? ""))]);
            $rb = $rb->fetchColumn() ?: null;
            $pdo->prepare(
                "INSERT INTO users(name,email,phone,password,role,skill,referred_by)VALUES(?,?,?,?,?,?,?)",
            )->execute([
                trim($_POST["name"]),
                trim($_POST["email"]),
                trim($_POST["phone"]),
                password_hash($pw, PASSWORD_DEFAULT),
                $role == "worker" ? "worker" : "customer",
                $role == "worker" ? $_POST["skill"] : "",
                $rb,
            ]);
        } catch (Exception $x) {
            $err = "Email already registered.";
        }
        if (!$err) {
            $em = trim($_POST["email"]);
            if ($vEmail !== "" && hash_equals($vEmail, strtolower($em))) {
                // e-mail code was already confirmed on this page: mark the account verified and go to login
                $pdo->prepare("UPDATE users SET email_verified=1 WHERE email=?")->execute([$em]);
                unset($_SESSION["reg_otp"]);
                header("Location: login.php?ok=1");
                exit();
            }
            send_otp($pdo, $em);
            header("Location: verify_email.php?e=" . urlencode($em));
            exit();
        }
    }
}
// field with a "Send code" button, code box and status line (used for e-mail and phone)
$vcell = function ($type, $label, $icon, $input) {
    return '<div class="cell vcell" data-vt="' .
        $type .
        '">' .
        fld(
            $label,
            $icon,
            $input .
                '<button type="button" class="vsend">Send code</button><span class="vbadge" hidden><span class="material-icons mi">check_circle</span> Verified</span>',
        ) .
        '<div class="votp" hidden><input class="vcode" inputmode="numeric" maxlength="6" placeholder="6-digit code" autocomplete="one-time-code" aria-label="Verification code"><button type="button" class="vgo">Verify</button></div><small class="vmsg" aria-live="polite"></small></div>';
};
$phoneInput =
    '<input name="phone" value="' .
    $v("phone") .
    '" placeholder="10-digit mobile number" inputmode="numeric" pattern="[0-9]{10}" maxlength="10" autocomplete="tel" required>';
$phoneIcon = '<span class="material-icons mi">phone</span>';
head("Register");
auth_open(
    "Join Workonnect",
    "Create your account.",
    "Connect with Workonnect and get started.",
);
?>
<style>
.authcard{max-width:780px!important;width:100%!important}
.rgrid{display:grid;grid-template-columns:1fr 1fr;gap:4px 18px;align-items:start}
.rgrid .cell{min-width:0}
.rgrid .full{grid-column:1/-1}
#skillbox[hidden]+.ref{grid-column:1/-1}
.rgrid .fld:focus-within{box-shadow:0 0 0 3px rgba(26,107,124,.22);transition:box-shadow .2s}

/* progress pills */
.vprog{display:flex;flex-wrap:wrap;gap:10px;margin:2px 0 14px}
.vprog span{display:inline-flex;align-items:center;gap:8px;padding:6px 14px;border:1px solid rgba(128,128,128,.3);border-radius:999px;font-size:.85rem;opacity:.75;transition:all .25s}
.vprog i{width:8px;height:8px;border-radius:50%;background:rgba(128,128,128,.55);transition:all .25s}
.vprog span.on{opacity:1;border-color:#2e9e5b;background:rgba(46,158,91,.08)}
.vprog span.on i{background:#2e9e5b;box-shadow:0 0 0 4px rgba(46,158,91,.18)}

/* field with inline button */
.vcell .fld{display:flex;align-items:center}
.vcell .fld input{flex:1;min-width:0}

/* realistic buttons: soft gradient, top highlight, layered shadow, press effect */
.vsend,.vgo,.authcard .cta{
  -webkit-appearance:none;appearance:none;border:0;cursor:pointer;
  font-family:inherit;font-weight:600;letter-spacing:.2px;color:#fff;
  background:linear-gradient(180deg,#2488a0 0%,#1a6b7c 100%);
  box-shadow:inset 0 1px 0 rgba(255,255,255,.28),0 1px 2px rgba(0,0,0,.18),0 4px 10px rgba(26,107,124,.25);
  transition:transform .12s ease,box-shadow .12s ease,filter .15s ease;
  -webkit-tap-highlight-color:transparent;touch-action:manipulation;user-select:none}
.vsend,.vgo{flex:none;border-radius:10px;padding:0 14px;height:36px;margin-right:6px;font-size:.85rem;white-space:nowrap}
.vsend:hover:not(:disabled),.vgo:hover:not(:disabled),.authcard .cta:hover:not(:disabled){filter:brightness(1.07)}
.vsend:active:not(:disabled),.vgo:active:not(:disabled),.authcard .cta:active:not(:disabled){
  transform:translateY(1px) scale(.98);
  box-shadow:inset 0 2px 4px rgba(0,0,0,.25),0 1px 1px rgba(0,0,0,.12)}
.vsend:focus-visible,.vgo:focus-visible,.authcard .cta:focus-visible{outline:3px solid rgba(26,107,124,.4);outline-offset:2px}
.vsend:disabled,.vgo:disabled,.authcard .cta:disabled{
  opacity:.55;cursor:not-allowed;filter:grayscale(.25);
  box-shadow:inset 0 1px 0 rgba(255,255,255,.18)}

/* main Create account button */
.authcard .cta{width:100%;min-height:48px;border-radius:12px;font-size:1rem;display:flex;align-items:center;justify-content:center;gap:10px;margin-top:6px}
.authcard .cta .go{transition:transform .2s}
.authcard .cta:hover:not(:disabled) .go{transform:translateX(4px)}

/* verified badge */
.vbadge{flex:none;display:inline-flex;align-items:center;gap:4px;color:#2e9e5b;font-weight:600;font-size:.85rem;padding:4px 10px;margin-right:6px;border-radius:999px;background:rgba(46,158,91,.12);white-space:nowrap}
.vbadge .mi{font-size:18px}

/* code row */
.votp{display:flex;gap:8px;margin-top:8px}
.votp .vcode{flex:1;min-width:0;height:44px;letter-spacing:.35em;text-align:center;font-family:ui-monospace,Consolas,monospace;font-size:1.05rem;padding:0 10px;border:1px solid rgba(128,128,128,.4);border-radius:10px;background:transparent;color:inherit;box-shadow:inset 0 1px 2px rgba(0,0,0,.08);transition:border-color .2s,box-shadow .2s}
.votp .vcode:focus{outline:0;border-color:#1a6b7c;box-shadow:0 0 0 3px rgba(26,107,124,.22)}
.votp .vgo{margin-right:0;height:44px;padding:0 20px;border-radius:10px}
.vmsg{display:block;min-height:1.2em;margin:4px 0 8px;font-size:.82rem;opacity:.85}
.vmsg.bad{color:#d64545;opacity:1}
.votp[hidden],.vbadge[hidden],.vsend[hidden]{display:none!important}

/* tablet / mobile */
@media(max-width:700px){
  .rgrid{grid-template-columns:1fr;gap:2px}
  .vsend,.vgo{height:40px}
  .votp .vcode,.votp .vgo{height:48px}
  .authcard .cta{min-height:52px}
}
@media(max-width:380px){
  .vsend{padding:0 10px;font-size:.8rem}
  .votp{flex-direction:column}
  .votp .vgo{width:100%}
  .vprog span{font-size:.8rem;padding:5px 10px}
}
@media(prefers-reduced-motion:reduce){
  .vsend,.vgo,.authcard .cta,.authcard .cta .go{transition:none}
}
</style>
<p class="err"><?= e($err) ?></p>
<div class="vprog" aria-label="Verification progress"><span data-k="email"><i></i>Email</span><?= $needPhone ? '<span data-k="phone"><i></i>Phone</span>' : "" ?></div>
<div class="rgrid">
<div class="cell"><?= fld(
    "Full name",
    '<span class="material-icons mi">person</span>',
    '<input name="name" value="' .
        $v("name") .
        '" placeholder="Your full name" autocomplete="name" required>',
) ?></div>
<?= $vcell(
    "email",
    "Email address",
    '<span class="material-icons mi">mail</span>',
    '<input type="email" name="email" value="' .
        $v("email") .
        '" placeholder="you@example.com" autocomplete="email" required>',
) ?>
<?= $needPhone
    ? $vcell("phone", "Phone number", $phoneIcon, $phoneInput)
    : '<div class="cell">' . fld("Phone number", $phoneIcon, $phoneInput) . "</div>" ?>
<div class="cell"><?= fld(
    "Joining as",
    '<span class="material-icons mi">badge</span>',
    '<select name="role" id="role"><option value="customer"' .
        ($role == "customer" ? " selected" : "") .
        '>Customer</option><option value="worker"' .
        ($role == "worker" ? " selected" : "") .
        ">Worker</option></select>",
) ?></div>
<div class="cell"><?= pwf("Password", "password", "Create a password", true, "new-password") ?></div>
<div class="cell"><?= pwf(
    "Confirm password",
    "password2",
    "Type it again",
    false,
    "new-password",
) ?></div>
<div class="cell" id="skillbox"<?= $role == "worker" ? "" : " hidden" ?>><?php
$o = '<option value="">Skill (workers only)</option>';
foreach ($CATS as $c) {
    $o .=
        "<option" .
        (($_POST["skill"] ?? "") == $c ? " selected" : "") .
        ">" .
        e($c) .
        "</option>";
}
echo fld(
    "Your skill",
    '<span class="material-icons mi">hardware</span>',
    '<select name="skill">' . $o . "</select>",
);
?></div>
<div class="cell ref"><?= fld(
    "Referral code (optional)",
    "#",
    '<input name="ref" value="' .
        $v("ref") .
        '" placeholder="Have a code? Enter it" autocomplete="off">',
) ?></div>
<!-- <div class="full"><label class="chk"><input type="checkbox" name="terms" value="1" required><span><?= $kn
    ? 'ನಾನು Workonnect <a href="terms.php" target="_blank">ನಿಯಮಗಳು</a> ಮತ್ತು <a href="privacy.php" target="_blank">ಗೌಪ್ಯತಾ ನೀತಿ</a>ಯನ್ನು ಒಪ್ಪುತ್ತೇನೆ.'
    : 'I agree to the Workonnect <a href="terms.php" target="_blank">Terms</a> and <a href="privacy.php" target="_blank">Privacy Policy</a>.' ?></span></label></div>
<div class="full"><button class="cta" data-busy="<?= e(
    t("Creating…"),
) ?>"><span>Create account</span><b class="go">→</b></button></div>
</div> -->
<div class="full"><button class="cta" data-busy="<?= e(t("Creating…")) ?>"><span>Create account</span><b class="go">→</b></button></div>
<div class="full">
  <div class="odiv">or</div>
  <a class="gbtn" href="google_login.php?role=<?= e($role) ?>">
    <svg viewBox="0 0 48 48" aria-hidden="true">
      <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
      <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
      <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
      <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
    </svg>
    <span>Continue with Google</span>
  </a>
</div>
<script>role.onchange=function(){skillbox.hidden=this.value!="worker"}</script>
<script>
(function(){
  var f=document.querySelector(".authcard"),tk=f.querySelector("input[name=csrf]"),cta=f.querySelector(".cta");
  var PRE=<?= json_encode(["email" => $vEmail, "phone" => $vPhone], JSON_HEX_TAG | JSON_HEX_AMP) ?>;
  var NEED=<?= json_encode($needPhone) ?>;
  var V={email:false,phone:!NEED};
  function norm(t,v){v=(v||"").trim();return t=="email"?v.toLowerCase():v}
  function sync(){
    var ok=V.email&&V.phone;
    cta.disabled=!ok;
    cta.title=ok?"":"Verify your email"+(NEED?" and phone":"")+" first";
    f.querySelectorAll(".vprog [data-k]").forEach(function(s){s.classList.toggle("on",!!V[s.dataset.k])});
  }
  function call(d){
    var fd=new FormData();
    for(var k in d)fd.append(k,d[k]);
    fd.append("csrf",tk?tk.value:"");
    return fetch("register_otp.php",{method:"POST",body:fd,credentials:"same-origin"}).then(function(r){return r.json()});
  }
  f.querySelectorAll(".vcell").forEach(function(c){
    var t=c.dataset.vt,inp=c.querySelector(".fld input:not(.vcode)"),sb=c.querySelector(".vsend"),bd=c.querySelector(".vbadge"),
        box=c.querySelector(".votp"),code=c.querySelector(".vcode"),go=c.querySelector(".vgo"),msg=c.querySelector(".vmsg"),timer=null;
    function say(m,bad){msg.textContent=m||"";msg.className="vmsg"+(bad?" bad":"")}
    function done(){V[t]=true;bd.hidden=false;sb.hidden=true;box.hidden=true;say("");sync()}
    function undo(){clearTimeout(timer);V[t]=false;bd.hidden=true;sb.hidden=false;sb.disabled=false;sb.textContent="Send code";box.hidden=true;code.value="";say("");sync()}
    function cool(n){clearTimeout(timer);(function tick(){if(n<=0){sb.disabled=false;sb.textContent="Resend code";return}sb.disabled=true;sb.textContent="Resend in "+n+"s";n--;timer=setTimeout(tick,1000)})()}
    function fail(){say("Something went wrong. Refresh the page and try again.",1);sb.disabled=false;go.disabled=false}
    inp.addEventListener("input",function(){if(V[t]||!box.hidden)undo()});
    sb.onclick=function(){
      var v=norm(t,inp.value);
      if(!v||!inp.checkValidity()){say(t=="email"?"Enter a valid email address first.":"Enter a valid 10-digit phone number first.",1);inp.focus();return}
      sb.disabled=true;say("Sending…");
      call({act:"send",type:t,target:v}).then(function(r){
        if(r.ok){box.hidden=false;code.value="";code.focus();say(r.msg);cool(r.wait||30)}
        else{say(r.msg,1);if(r.wait)cool(r.wait);else sb.disabled=false}
      }).catch(fail);
    };
    go.onclick=function(){
      var cv=code.value.trim();
      if(!/^[0-9]{6}$/.test(cv)){say("Enter the 6-digit code.",1);return}
      go.disabled=true;
      call({act:"verify",type:t,target:norm(t,inp.value),code:cv}).then(function(r){
        go.disabled=false;
        if(r.ok)done();else say(r.msg,1);
      }).catch(fail);
    };
    code.addEventListener("input",function(){code.value=code.value.replace(/\D/g,"").slice(0,6)});
    code.addEventListener("keydown",function(e){if(e.key=="Enter"){e.preventDefault();go.click()}});
    if(PRE[t]&&PRE[t]===norm(t,inp.value))done();
  });
  sync();
})();
</script>
<?php
auth_close('Already have an account? <a href="login.php">Login</a>');
foot();
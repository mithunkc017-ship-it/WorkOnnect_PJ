<?php // Create account - clean blue card, white background
require "config.php";
$err = "";
$v = function ($k) {
    return e($_POST[$k] ?? "");
};
$role = $_POST["role"] ?? "customer";
$kn = ($_SESSION["lang"] ?? "en") == "kn";
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
            send_otp($pdo, trim($_POST["email"]));
            header("Location: verify_email.php?e=" . urlencode(trim($_POST["email"])));
            exit();
        }
    }
}
// icon helper
$mi = function ($n) {
    return '<span class="material-icons">' . $n . "</span>";
};
// one field: label with icon on top, rounded box with round icon badge
$wf = function ($label, $labelIcon, $inIcon, $inner) use ($mi) {
    return '<div class="wkl">' . $mi($labelIcon) . e($label) . '</div><div class="wkf"><span class="wki">' . $inIcon . "</span>" . $inner . "</div>";
};
head("Register");
auth_open(
    "Join Workonnect",
    "Create your account.",
    "Connect with Workonnect and get started.",
);
?>
<style>
body{background:#fff!important}
.authcard{max-width:600px!important;width:100%!important;padding:24px 26px!important;background:#fff!important;border-radius:20px!important;box-shadow:0 10px 36px rgba(37,99,235,.12)!important;border:1px solid #e1e9fb!important}

.rgrid{display:grid;grid-template-columns:1fr 1fr;gap:0 18px;align-items:start}
.rgrid .cell{min-width:0;margin-bottom:12px}
.rgrid .full{grid-column:1/-1}
#skillbox[hidden]+.ref{grid-column:1/-1}

/* label above field */
.wkl{display:flex;align-items:center;gap:8px;font-weight:600;font-size:.88rem;color:#0f1f44;margin:0 0 6px 2px}
.wkl .material-icons{font-size:20px;color:#2563eb}

/* rounded field */
.wkf{display:flex;align-items:center;gap:10px;min-height:46px;padding:0 14px 0 6px;background:#fff;border:1.5px solid #d6e2fb;border-radius:999px;box-shadow:0 2px 8px rgba(37,99,235,.06);transition:border-color .2s,box-shadow .2s}
.wkf:focus-within{border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.18)}
.wki{flex:none;width:32px;height:32px;border-radius:50%;background:#e8effd;color:#2563eb;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:1rem}
.wki .material-icons{font-size:18px}
.wkf input,.wkf select{flex:1;min-width:0;border:0!important;outline:0!important;background:transparent!important;box-shadow:none!important;font-size:.92rem;color:#0f1f44;padding:0!important;height:42px;font-family:inherit}
.wkf input::placeholder{color:#8392b3}
.wkf select{-webkit-appearance:none;appearance:none;cursor:pointer;font-weight:600;padding-right:28px!important;
  background:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24'%3E%3Cpath fill='none' stroke='%232563eb' stroke-width='3' stroke-linecap='round' stroke-linejoin='round' d='M5 9l7 7 7-7'/%3E%3C/svg%3E") no-repeat right 4px center!important}

/* terms / privacy tick box (own class, not affected by config.php) */
.tk{display:flex;align-items:center;gap:9px;margin:2px 0 14px;font-size:.8rem;line-height:1.4;color:#0f1f44;cursor:pointer;position:relative}
.tk input{position:absolute;opacity:0;width:16px;height:16px;margin:0;left:0;top:50%;transform:translateY(-50%);cursor:pointer}
.tk .box{flex:none;width:16px;height:16px;box-sizing:border-box;border:1.5px solid #9db3e6;border-radius:4px;background:#fff;position:relative;transition:background .15s,border-color .15s}
.tk input:checked+.box{background:#2563eb;border-color:#2563eb}
.tk input:checked+.box::after{content:"";position:absolute;left:4.5px;top:1px;width:4px;height:8px;border:solid #fff;border-width:0 2px 2px 0;transform:rotate(45deg)}
.tk input:focus-visible+.box{outline:2px solid rgba(37,99,235,.45);outline-offset:2px}
.tk a{color:#2563eb;font-weight:600;text-decoration:none}
.tk a:hover{text-decoration:underline}

/* Create account button */
.authcard .cta{position:relative;width:100%;min-height:50px;border:0;border-radius:999px;cursor:pointer;color:#fff;font-family:inherit;font-weight:700;font-size:1.02rem;
  display:flex!important;align-items:center;justify-content:center;gap:10px;
  background:linear-gradient(135deg,#3b82f6 0%,#2563eb 55%,#1d4ed8 100%)!important;
  box-shadow:inset 0 1px 0 rgba(255,255,255,.35),0 8px 20px rgba(37,99,235,.32)!important;
  transition:transform .12s ease,filter .15s ease}
.authcard .cta:hover:not(:disabled){filter:brightness(1.07)}
.authcard .cta:active:not(:disabled){transform:translateY(1px) scale(.99)}
.authcard .cta:disabled{opacity:.6;cursor:not-allowed}
.authcard .cta .rk{font-size:24px}
.authcard .cta .go{position:absolute;right:10px;top:50%;transform:translateY(-50%);width:32px;height:32px;border-radius:50%;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;font-size:1rem}

/* or divider + Google button */
.odv{display:flex;align-items:center;gap:12px;margin:16px 0 12px;color:#8392b3;font-size:.85rem}
.odv::before,.odv::after{content:"";flex:1;height:1px;background:#e1e9fb}
.gbt{display:flex;align-items:center;justify-content:center;gap:10px;min-height:46px;border:1.5px solid #d6e2fb;border-radius:999px;background:#fff;color:#0f1f44;font-weight:600;font-size:.95rem;text-decoration:none;transition:background .15s,box-shadow .15s}
.gbt:hover{background:#f5f8ff;box-shadow:0 2px 10px rgba(37,99,235,.12)}
.gbt svg{width:20px;height:20px;flex:none}

/* sign in line */
.wksign{display:flex;align-items:center;gap:12px;margin-top:14px;font-size:.88rem;color:#4a5b82;justify-content:center}
.wksign i{flex:1;height:1px;background:#e1e9fb;max-width:110px}
.wksign a{color:#2563eb;font-weight:700;text-decoration:none}

@media(max-width:700px){
  .authcard{padding:18px 16px!important}
  .rgrid{grid-template-columns:1fr}
}
@media(prefers-reduced-motion:reduce){
  .authcard .cta{transition:none}
}
</style>
<p class="err"><?= e($err) ?></p>
<div class="rgrid">
<div class="cell"><?= $wf(
    "Full name",
    "person_add",
    $mi("person"),
    '<input name="name" value="' . $v("name") . '" placeholder="Your full name" autocomplete="name" required>',
) ?></div>
<div class="cell"><?= $wf(
    "Email address",
    "mail",
    $mi("mail"),
    '<input type="email" name="email" value="' . $v("email") . '" placeholder="you@example.com" autocomplete="email" required>',
) ?></div>
<div class="cell"><?= $wf(
    "Phone number",
    "phone",
    $mi("phone"),
    '<input name="phone" value="' . $v("phone") . '" placeholder="10-digit mobile number" inputmode="numeric" pattern="[0-9]{10}" maxlength="10" autocomplete="tel" required>',
) ?></div>
<div class="cell"><?= $wf(
    "Joining as",
    "work",
    $mi("work"),
    '<select name="role" id="role"><option value="customer"' .
        ($role == "customer" ? " selected" : "") .
        '>Customer</option><option value="worker"' .
        ($role == "worker" ? " selected" : "") .
        ">Worker</option></select>",
) ?></div>
<div class="cell"><?= $wf(
    "Password",
    "lock",
    $mi("lock"),
    '<input type="password" name="password" placeholder="Create a password" autocomplete="new-password" required>',
) ?></div>
<div class="cell"><?= $wf(
    "Confirm password",
    "lock",
    $mi("lock"),
    '<input type="password" name="password2" placeholder="Type it again" autocomplete="new-password" required>',
) ?></div>
<div class="cell" id="skillbox"<?= $role == "worker" ? "" : " hidden" ?>><?php
$o = '<option value="">Skill (workers only)</option>';
foreach ($CATS as $c) {
    $o .= "<option" . (($_POST["skill"] ?? "") == $c ? " selected" : "") . ">" . e($c) . "</option>";
}
echo $wf("Your skill", "hardware", $mi("hardware"), '<select name="skill">' . $o . "</select>");
?></div>
<div class="cell ref"><?= $wf(
    "Referral code (optional)",
    "sell",
    "#",
    '<input name="ref" value="' . $v("ref") . '" placeholder="Have a code? Enter it" autocomplete="off">',
) ?></div>
<div class="full"><label class="tk"><input type="checkbox" name="terms" value="1" required><span class="box"></span><span><?= $kn
    ? 'ನಾನು Workonnect <a href="terms.php" target="_blank">ನಿಯಮಗಳು</a> ಮತ್ತು <a href="privacy.php" target="_blank">ಗೌಪ್ಯತಾ ನೀತಿ</a>ಯನ್ನು ಒಪ್ಪುತ್ತೇನೆ.'
    : 'I agree to the Workonnect <a href="terms.php" target="_blank">Terms</a> and <a href="privacy.php" target="_blank">Privacy Policy</a>.' ?></span></label></div>
<div class="full"><button class="cta" data-busy="<?= e(t("Creating…")) ?>"><b class="rk material-icons">rocket_launch</b><span>Create account</span><b class="go">→</b></button></div>
<div class="full">
  <div class="odv">or</div>
  <a class="gbt" href="google_login.php?role=<?= e($role) ?>">
    <svg viewBox="0 0 48 48" aria-hidden="true">
      <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
      <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
      <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
      <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
    </svg>
    <span>Continue with Google</span>
  </a>
</div>
</div>
<script>
var r=document.getElementById("role"),s=document.getElementById("skillbox"),g=document.querySelector(".gbt");
r.onchange=function(){
  s.hidden=this.value!="worker";
  g.href="google_login.php?role="+encodeURIComponent(this.value);
};
</script>
<?php
auth_close('<div class="wksign"><i></i><span>Already have an account? <a href="login.php">Sign in</a> ›</span><i></i></div>');
foot();
<?php // Create account - clean blue card, roomy layout, white background
require "config.php";
$err = "";
$v = function ($k) {
    return e($_POST[$k] ?? "");
};
$role = ($_POST["role"] ?? "customer") == "worker" ? "worker" : "customer";
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
// custom dropdown: $opts = [value => label]
$dd = function ($name, $id, $opts, $sel, $placeholder = "") use ($mi) {
    $txt = $placeholder;
    $li = "";
    foreach ($opts as $val => $lab) {
        if ($val === "") {
            continue; // placeholder row is not a real choice
        }
        $on = (string) $val === (string) $sel;
        if ($on) {
            $txt = $lab;
        }
        $li .= '<li role="option" data-v="' . e($val) . '"' . ($on ? ' class="on"' : "") . "><span>" . e($lab) . "</span></li>";
    }
    return '<div class="dd"><input type="hidden" name="' . $name . '" id="' . $id . '" value="' . e($sel) . '">' .
        '<button type="button" class="ddb" aria-haspopup="listbox" aria-expanded="false"><span class="ddt' . ($sel === "" ? " ph" : "") . '">' . e($txt) . "</span>" . $mi("expand_more") . "</button>" .
        '<ul class="ddl" role="listbox">' . $li . "</ul></div>";
};
$eye = '<button type="button" class="wkeye" aria-label="Show password">' . $mi("visibility_off") . "</button>";
head("Register");
auth_open(
    "Join Workonnect",
    "Create your account.",
    "Connect with Workonnect and get started.",
);
?>
<style>
body{background:#fff!important}
.authcard{max-width:720px!important;width:100%!important;padding:36px 38px!important;background:#fff!important;border-radius:24px!important;box-shadow:0 14px 44px rgba(37,99,235,.12)!important;border:1px solid #e1e9fb!important;overflow:visible!important}

/* even spacing between every box */
.rgrid{display:grid;grid-template-columns:1fr 1fr;gap:24px 22px;align-items:start}
.rgrid .cell{min-width:0;margin:0;position:relative}
.rgrid .cell[hidden]{display:none}
.rgrid .cell.ddopen{z-index:100}
.rgrid .full{grid-column:1/-1}
#skillbox[hidden]+.ref{grid-column:1/-1}

/* label above field */
.wkl{display:flex;align-items:center;gap:9px;font-weight:600;font-size:.95rem;color:#0f1f44;margin:0 0 9px 4px}
.wkl .material-icons{font-size:21px;color:#2563eb}

/* rounded field */
.wkf{position:relative;display:flex;align-items:center;gap:12px;min-height:56px;padding:0 16px 0 8px;background:#fff;border:1.5px solid #d6e2fb;border-radius:999px;box-shadow:0 2px 8px rgba(37,99,235,.06);transition:border-color .2s,box-shadow .2s}
.wkf:focus-within{border-color:#2563eb;box-shadow:0 0 0 4px rgba(37,99,235,.15)}
.wki{flex:none;width:40px;height:40px;border-radius:50%;background:#e8effd;color:#2563eb;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:1.05rem}
.wki .material-icons{font-size:21px}
.wkf input{flex:1;min-width:0;border:0!important;outline:0!important;background:transparent!important;box-shadow:none!important;font-size:1rem;color:#0f1f44;padding:0!important;height:52px;font-family:inherit}
.wkf input::placeholder{color:#8392b3}

/* password eye */
.wkeye{flex:none;border:0;background:transparent;cursor:pointer;color:#6b7fa8;display:flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:50%;padding:0;transition:background .15s,color .15s}
.wkeye:hover{background:#e8effd;color:#2563eb}
.wkeye.on{color:#2563eb}
.wkeye .material-icons{font-size:22px}

/* custom dropdown: list is positioned against the rounded box (.wkf) */
.dd{position:static;flex:1;min-width:0}
.ddb{display:flex!important;align-items:center;justify-content:space-between;gap:8px;width:100%;height:52px;border:0!important;background:transparent!important;box-shadow:none!important;cursor:pointer;font-family:inherit;font-size:1rem;font-weight:600;color:#0f1f44;padding:0!important;text-align:left}
.ddb .ddt.ph{color:#8392b3;font-weight:400}
.ddb .material-icons{font-size:24px;color:#2563eb;transition:transform .2s}
.dd.open .ddb .material-icons{transform:rotate(180deg)}
.ddl{position:absolute!important;left:0;right:0;top:calc(100% + 8px);z-index:999;list-style:none!important;margin:0!important;padding:8px!important;background:#fff;border:1.5px solid #d6e2fb;border-radius:20px;box-shadow:0 16px 40px rgba(37,99,235,.2);max-height:240px;overflow-y:auto;display:none}
.dd.open .ddl{display:block}
.ddl li{list-style:none!important;display:flex!important;align-items:center;justify-content:space-between;gap:10px;margin:0!important;padding:12px 16px!important;border-radius:14px;font-size:.98rem;font-weight:500;color:#0f1f44;cursor:pointer;transition:background .12s}
.ddl li::marker{content:""}
.ddl li+li{margin-top:2px!important}
.ddl li:hover{background:#eef3ff}
.ddl li.on{background:#e8effd;color:#2563eb;font-weight:700}
.ddl li.on::after{content:"✓";font-weight:700;color:#2563eb}

/* terms / privacy tick box */
.tk{display:flex;align-items:center;gap:10px;font-size:.88rem;line-height:1.4;color:#0f1f44;cursor:pointer;position:relative}
.tk input{position:absolute;opacity:0;width:18px;height:18px;margin:0;left:0;top:50%;transform:translateY(-50%);cursor:pointer}
.tk .box{flex:none;width:18px;height:18px;box-sizing:border-box;border:1.5px solid #9db3e6;border-radius:5px;background:#fff;position:relative;transition:background .15s,border-color .15s}
.tk input:checked+.box{background:#2563eb;border-color:#2563eb}
.tk input:checked+.box::after{content:"";position:absolute;left:5.5px;top:1.5px;width:4px;height:9px;border:solid #fff;border-width:0 2px 2px 0;transform:rotate(45deg)}
.tk input:focus-visible+.box{outline:2px solid rgba(37,99,235,.45);outline-offset:2px}
.tk a{color:#2563eb;font-weight:600;text-decoration:none}
.tk a:hover{text-decoration:underline}

/* Create account button */
.authcard .cta{position:relative;width:100%;min-height:58px;border:0;border-radius:999px;cursor:pointer;color:#fff;font-family:inherit;font-weight:700;font-size:1.08rem;
  display:flex!important;align-items:center;justify-content:center;gap:10px;
  background:linear-gradient(135deg,#3b82f6 0%,#2563eb 55%,#1d4ed8 100%)!important;
  box-shadow:inset 0 1px 0 rgba(255,255,255,.35),0 10px 24px rgba(37,99,235,.32)!important;
  transition:transform .12s ease,filter .15s ease}
.authcard .cta:hover:not(:disabled){filter:brightness(1.07)}
.authcard .cta:active:not(:disabled){transform:translateY(1px) scale(.99)}
.authcard .cta:disabled{opacity:.6;cursor:not-allowed}
.authcard .cta .rk{font-size:26px}
.authcard .cta .go{position:absolute;right:12px;top:50%;transform:translateY(-50%);width:36px;height:36px;border-radius:50%;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;font-size:1.05rem}

/* sign in line */
.wksign{display:flex;align-items:center;gap:12px;margin-top:22px;font-size:.92rem;color:#4a5b82;justify-content:center}
.wksign i{flex:1;height:1px;background:#e1e9fb;max-width:120px}
.wksign a{color:#2563eb;font-weight:700;text-decoration:none}

@media(max-width:700px){
  .authcard{padding:24px 18px!important}
  .rgrid{grid-template-columns:1fr;gap:20px}
}
@media(prefers-reduced-motion:reduce){
  .authcard .cta,.ddb .material-icons{transition:none}
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
    $dd("role", "role", ["customer" => "Customer", "worker" => "Worker"], $role),
) ?></div>
<div class="cell"><?= $wf(
    "Password",
    "lock",
    $mi("lock"),
    '<input type="password" name="password" placeholder="Create a password" autocomplete="new-password" required>' . $eye,
) ?></div>
<div class="cell"><?= $wf(
    "Confirm password",
    "lock",
    $mi("lock"),
    '<input type="password" name="password2" placeholder="Type it again" autocomplete="new-password" required>' . $eye,
) ?></div>
<div class="cell" id="skillbox"<?= $role == "worker" ? "" : " hidden" ?>><?php
$opts = [];
foreach ($CATS as $c) {
    $opts[$c] = $c;
}
echo $wf("Your skill", "hardware", $mi("hardware"), $dd("skill", "skill", $opts, $_POST["skill"] ?? "", "Select your skill"));
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
</div>
<script>
(function(){
  // show / hide password
  document.querySelectorAll(".wkeye").forEach(function(b){
    b.onclick=function(){
      var i=b.parentNode.querySelector("input"),show=i.type=="password";
      i.type=show?"text":"password";
      b.classList.toggle("on",show);
      b.setAttribute("aria-label",show?"Hide password":"Show password");
      b.firstChild.textContent=show?"visibility":"visibility_off";
    };
  });
  // custom dropdowns
  var skillbox=document.getElementById("skillbox");
  function shut(d){
    d.classList.remove("open");
    d.querySelector(".ddb").setAttribute("aria-expanded","false");
    var c=d.closest(".cell");if(c)c.classList.remove("ddopen");
  }
  function closeAll(except){
    document.querySelectorAll(".dd.open").forEach(function(d){if(d!==except)shut(d)});
  }
  document.querySelectorAll(".dd").forEach(function(d){
    var btn=d.querySelector(".ddb"),hid=d.querySelector("input[type=hidden]"),txt=d.querySelector(".ddt"),cell=d.closest(".cell");
    btn.onclick=function(e){
      e.stopPropagation();
      var open=!d.classList.contains("open");
      closeAll(d);
      d.classList.toggle("open",open);
      if(cell)cell.classList.toggle("ddopen",open);
      btn.setAttribute("aria-expanded",open?"true":"false");
    };
    d.querySelectorAll("li").forEach(function(li){
      li.onclick=function(e){
        e.stopPropagation();
        hid.value=li.dataset.v;
        txt.textContent=li.firstChild.textContent;
        txt.classList.remove("ph");
        d.querySelectorAll("li").forEach(function(x){x.classList.toggle("on",x===li)});
        shut(d);
        if(hid.id==="role")skillbox.hidden=hid.value!=="worker";
      };
    });
  });
  document.addEventListener("click",function(){closeAll()});
  document.addEventListener("keydown",function(e){if(e.key==="Escape")closeAll()});
})();
</script>
<?php
auth_close('<div class="wksign"><i></i><span>Already have an account? <a href="login.php">Sign in</a> ›</span><i></i></div>');
foot();
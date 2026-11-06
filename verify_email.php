<?php // Email OTP verification (6-digit code, valid 10 minutes, max 5 wrong tries)
require "config.php";
$em = trim($_GET["e"] ?? ($_POST["e"] ?? ""));
$err = "";
$msg = "";
$s = $pdo->prepare("SELECT *,otp_expires>NOW() live FROM users WHERE email=?");
$s->execute([$em]);
$u = $s->fetch();
if (!$u || $u["email_verified"]) {
    header("Location: login.php");
    exit();
}
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST["resend"])) {
        if (($_SESSION["otp_t"] ?? 0) > time() - 60) {
            $err = "Please wait a minute before asking for a new code.";
        } else {
            send_otp($pdo, $em);
            $_SESSION["otp_t"] = time();
            $msg = "A new code has been sent.";
        }
    } else {
        $c = trim($_POST["code"] ?? "");
        if ($u["otp_tries"] >= 5) {
            $err = "Too many wrong attempts. Ask for a new code.";
        } elseif (
            $u["otp"] &&
            $u["live"] &&
            hash_equals($u["otp"], hash("sha256", $c))
        ) {
            $pdo->prepare(
                "UPDATE users SET email_verified=1,otp=NULL,otp_tries=0 WHERE id=?",
            )->execute([$u["id"]]);
            logit("Email verified", $u["id"]);
            header("Location: login.php?ok=1");
            exit();
        } else {
            $pdo->prepare(
                "UPDATE users SET otp_tries=otp_tries+1 WHERE id=?",
            )->execute([$u["id"]]);
            $err = "Wrong or expired code.";
        }
    }
}
head("Verify Email");
auth_open(
    "Verify email",
    "Check your email.",
    "Enter the 6-digit code sent to your email.",
);
?>
<p class="alt" style="margin-top:-8px;font-size:13px"><b><?= e($em) ?></b></p>
<p class="err"><?= e($err) ?></p><?= $msg
    ? '<p class="ok">' . e($msg) . "</p>"
    : "" ?>
<?php if (SHOW_OTP_DEMO && SMTP_HOST === "" && !empty($_SESSION["demo_otp"])) {
    echo '<p class="ok">Demo mode: your code is <b>' .
        e($_SESSION["demo_otp"]) .
        "</b></p>";
} ?>
<input type="hidden" name="e" value="<?= e($em) ?>">
<?= fld(
    "Verification code",
    "#",
    '<input name="code" placeholder="6-digit code" inputmode="numeric" maxlength="6" pattern="[0-9]{6}" autocomplete="one-time-code" required>',
) ?>
<button class="cta" data-busy="<?= e(
    t("Verifying…"),
) ?>"><span>Verify</span><b class="go">→</b></button>
<button class="ghost" name="resend" value="1" formnovalidate>Resend code</button>
<?php
auth_close("");
foot();


<?php // Admin two-step login: after the password the admin enters a 6-digit code sent by e-mail (valid 10 minutes, max 5 wrong tries)
require "config.php";
$p = $_SESSION["pre2fa"] ?? null;
if (!$p || $p["t"] < time() - 600) {
    unset($_SESSION["pre2fa"]);
    header("Location: login.php");
    exit();
}
$s = $pdo->prepare(
    "SELECT *,otp_expires>NOW() live FROM users WHERE id=? AND role='admin'",
);
$s->execute([$p["id"]]);
$u = $s->fetch();
if (!$u) {
    header("Location: login.php");
    exit();
}
$err = "";
$msg = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST["resend"])) {
        if (($_SESSION["otp_t"] ?? 0) > time() - 60) {
            $err = "Please wait a minute before asking for a new code.";
        } else {
            send_otp($pdo, $u["email"]);
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
                "UPDATE users SET otp=NULL,otp_tries=0 WHERE id=?",
            )->execute([$u["id"]]);
            unset($_SESSION["pre2fa"], $_SESSION["demo_otp"]);
            session_regenerate_id(true);
            $_SESSION["u"] = $u;
            logit("Admin logged in (2-step)", $u["id"]);
            lh($pdo, $u["id"], $u["email"], 1, "admin login (2-step ok)");
            $pdo->prepare("DELETE FROM login_attempts WHERE ip=?")->execute([
                $_SERVER["REMOTE_ADDR"],
            ]);
            header("Location: dash.php");
            exit();
        } else {
            $pdo->prepare(
                "UPDATE users SET otp_tries=otp_tries+1 WHERE id=?",
            )->execute([$u["id"]]);
            lh($pdo, $u["id"], $u["email"], 0, "admin wrong 2-step code");
            $err = "Wrong or expired code.";
        }
    }
}
head("Admin verification");
auth_open(
    "Admin check",
    "Two-step login.",
    "Enter the 6-digit code sent to the admin email.",
);
?>
<p class="err"><?= e($err) ?></p><?= $msg
    ? '<p class="ok">' . e($msg) . "</p>"
    : "" ?>
<?php if (SHOW_OTP_DEMO && SMTP_HOST === "" && !empty($_SESSION["demo_otp"])) {
    echo '<p class="ok">Demo mode: your code is <b>' .
        e($_SESSION["demo_otp"]) .
        "</b></p>";
} ?>
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
auth_close('<a href="login.php">Back to login</a>');
foot();


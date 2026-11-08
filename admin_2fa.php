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
head("Verification");
auth_open(
    "Verification",
    "Check your e-mail.",
    "Enter the 6-digit code we sent to your e-mail address. It is valid for 5 minutes.",
);
?>
<p class="err"><?= e($err) ?></p><?= $msg
    ? '<p class="ok">' . e($msg) . "</p>"
    : "" ?>
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
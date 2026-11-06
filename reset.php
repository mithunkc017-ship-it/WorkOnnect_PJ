<?php // Set a new password using the reset link
require "config.php";
$t = $_GET["t"] ?? "";
$s = $pdo->prepare(
    "SELECT * FROM password_resets WHERE token=? AND expires>NOW()",
);
$s->execute([$t]);
$r = $s->fetch();
if (!$r) {
    die('Link invalid or expired. <a href="forgot.php">Try again</a>');
}
if ($_SERVER["REQUEST_METHOD"] == "POST" && strlen($_POST["password"]) >= 6) {
    $pdo->prepare("UPDATE users SET password=? WHERE email=?")->execute([
        password_hash($_POST["password"], PASSWORD_DEFAULT),
        $r["email"],
    ]);
    $pdo->prepare("DELETE FROM password_resets WHERE email=?")->execute([
        $r["email"],
    ]);
    header("Location: login.php");
    exit();
}
head("Reset Password");
auth_open("Reset password", "Choose a new password.");
?>
<?= pwf(
    "New Password",
    "password",
    "New password (min 6)",
    true,
    "new-password",
) ?>
<button class="cta" data-busy="<?= e(
    t("Saving…"),
) ?>"><span>Change Password</span><b class="go">→</b></button>
<?php
auth_close("");
foot();


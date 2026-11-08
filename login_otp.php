<?php
// Real e-mail login code (OTP) for Workonnect.
// Loaded at the end of config.php. Uses only plain SQL that the MongoPDO layer already runs elsewhere
// (INSERT / SELECT / UPDATE / DELETE), so the codes live in their own "login_otps" collection and the
// users collection is never touched.
//
// Rules: 6 digits from random_int(), only a salted hash is stored (never the code), valid 5 minutes,
// 5 wrong tries, 60 s resend cooldown, a new code deletes the old one, the code is never shown or logged.

if (!defined("OTP_TTL")) {
    define("OTP_TTL", 300); // seconds a code stays valid (5 minutes)
}
if (!defined("OTP_COOLDOWN")) {
    define("OTP_COOLDOWN", 60); // seconds before a new code may be requested
}
if (!defined("OTP_MAX_TRIES")) {
    define("OTP_MAX_TRIES", 5); // wrong attempts allowed per code
}

// Optional .env file (KEY=value per line) for hosts where real environment variables are hard to set (e.g. XAMPP).
// Real environment variables always win. Keep this file out of the web root or block it (see .htaccess.example).
(function () {
    $f = getenv("WORKONNECT_ENV") ?: __DIR__ . "/.env";
    if (!is_file($f) || !is_readable($f)) {
        return;
    }
    foreach (file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $l) {
        $l = trim($l);
        if ($l === "" || $l[0] === "#" || strpos($l, "=") === false) {
            continue;
        }
        [$k, $v] = explode("=", $l, 2);
        $k = trim($k);
        $v = trim($v, " \t\"'");
        if ($k !== "" && getenv($k) === false) {
            putenv("$k=$v");
        }
    }
})();

// Setting lookup: environment variable first, then an old settings.php constant, then the default.
if (!function_exists("wk_cfg")) {
    function wk_cfg($k, $d = "")
    {
        if (defined($k)) {
            return constant($k);
        }

        $v = getenv($k);

        if ($v !== false && $v !== "") {
            return $v;
        }

        return $d;
    }
}

function login_otp_secret()
{
    $s = (string) wk_cfg("OTP_SECRET");
    return strlen($s) >= 16 ? $s : "";
}

// Branded HTML e-mail sent with PHPMailer + Gmail SMTP. Never writes the code to any log.
if (!function_exists("otp_mail")) {
    function otp_mail($to, $name, $code)
    {
        $auto = __DIR__ . "/vendor/autoload.php";
        if (!is_file($auto)) {
            error_log("Workonnect: PHPMailer missing - run: composer require phpmailer/phpmailer");
            return false;
        }
        require_once $auto;
        $host = (string) wk_cfg("SMTP_HOST");
        $user = (string) wk_cfg("SMTP_USER");
        $pass = (string) wk_cfg("SMTP_PASS");
        $port = (int) (wk_cfg("SMTP_PORT") ?: 587);
        $from = (string) (wk_cfg("SMTP_FROM") ?: $user);
        if ($host === "" || $user === "" || $pass === "") {
            error_log("Workonnect: SMTP_HOST / SMTP_USER / SMTP_PASS are not set");
            return false;
        }
        $mins = (int) (OTP_TTL / 60);
        $n = htmlspecialchars(trim((string) $name) ?: "there", ENT_QUOTES, "UTF-8");
        $c = htmlspecialchars((string) $code, ENT_QUOTES, "UTF-8");
        $html =
            '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>' .
            '<body style="margin:0;padding:0;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif">' .
            '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:24px 12px"><tr><td align="center">' .
            '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:480px;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #e2e8f0">' .
            '<tr><td style="background:#1a6b7c;padding:22px 28px;color:#ffffff;font-size:22px;font-weight:bold;letter-spacing:-.3px">Work<span style="color:#bae6fd">onnect</span></td></tr>' .
            '<tr><td style="padding:28px 28px 8px;color:#0f172a;font-size:16px;line-height:1.6">Hi ' . $n . ',<br>Use this code to finish logging in to Workonnect:</td></tr>' .
            '<tr><td align="center" style="padding:8px 28px 8px"><div style="display:inline-block;background:#f0f9ff;border:2px dashed #1a6b7c;border-radius:12px;padding:16px 28px;font-size:34px;font-weight:bold;letter-spacing:10px;color:#0f172a;font-family:Consolas,Menlo,monospace">' . $c . '</div></td></tr>' .
            '<tr><td style="padding:12px 28px 4px;color:#475569;font-size:14px;line-height:1.6;text-align:center">This code expires in <b>' . $mins . ' minutes</b> and can be used only once.</td></tr>' .
            '<tr><td style="padding:20px 28px 28px"><div style="background:#fef3c7;border-radius:10px;padding:14px 16px;color:#92400e;font-size:13px;line-height:1.6"><b>Security notice:</b> Workonnect will never ask you for this code by phone, chat or e-mail. If you did not try to log in, ignore this message and consider changing your password.</div></td></tr>' .
            '<tr><td style="background:#f8fafc;padding:14px 28px;color:#94a3b8;font-size:12px;text-align:center">&copy; Workonnect &middot; This is an automated message, please do not reply.</td></tr>' .
            "</table></td></tr></table></body></html>";
        $alt =
            "Hi " . trim((string) $name) . ",\n\nYour Workonnect login verification code is: " . $code .
            "\n\nIt expires in " . $mins . " minutes and can be used only once.\n" .
            "If you did not try to log in, ignore this e-mail and consider changing your password.\n" .
            "Workonnect will never ask you for this code.";
        $m = new PHPMailer\PHPMailer\PHPMailer(true);
        try {
            $m->isSMTP();
            $m->Host = $host;
            $m->SMTPAuth = true;
            $m->Username = $user;
            $m->Password = $pass;
            $m->SMTPSecure = $port == 465
                ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
                : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $m->Port = $port;
            $m->Timeout = 15;
            $m->SMTPDebug = 0;
            $m->CharSet = "UTF-8";
            $m->setFrom($from, "Workonnect");
            $m->addAddress($to, (string) $name);
            $m->isHTML(true);
            $m->Subject = "Your Workonnect Login Verification Code";
            $m->Body = $html;
            $m->AltBody = $alt;
            $m->send();
            return true;
        } catch (Throwable $x) {
         error_log(
        "Workonnect OTP e-mail failed: " .
        $x->getMessage() .
        " | PHPMailer: " . $m->ErrorInfo
    );
    return false;
  }
}
}

// Create and e-mail a new code. Returns ["ok"=>true] or ["ok"=>false,"err"=>"...", "wait"=>seconds (cooldown only)].
function login_otp_issue($pdo, $u)
{
    if (login_otp_secret() === "") {
        error_log("Workonnect: OTP_SECRET is missing or shorter than 16 characters");
        return ["ok" => false, "err" => "Login codes are not set up on the server yet. Please contact the admin."];
    }
    $s = $pdo->prepare("SELECT created_at FROM login_otps WHERE user_id=? LIMIT 1");
    $s->execute([$u["id"]]);
    $last = $s->fetch();
    if ($last) {
        $wait = OTP_COOLDOWN - (time() - (int) $last["created_at"]);
        if ($wait > 0) {
            return ["ok" => false, "wait" => $wait, "err" => "Please wait " . $wait . " seconds before asking for a new code."];
        }
    }
    $code = sprintf("%06d", random_int(0, 999999));
    // a new code invalidates the previous one
    $pdo->prepare("DELETE FROM login_otps WHERE user_id=?")->execute([$u["id"]]);
    $pdo->prepare(
        "INSERT INTO login_otps(user_id,otp_hash,expires_at,created_at,tries)VALUES(?,?,?,?,0)",
    )->execute([
        $u["id"],
        password_hash(hash_hmac("sha256", $code, login_otp_secret()), PASSWORD_DEFAULT),
        time() + OTP_TTL,
        time(),
    ]);
    $sent = otp_mail($u["email"], $u["name"] ?? "", $code);
    $code = null;
    if (!$sent) {
        $pdo->prepare("DELETE FROM login_otps WHERE user_id=?")->execute([$u["id"]]);
        return ["ok" => false, "err" => "We could not send the verification e-mail. Please try again in a moment."];
    }
    return ["ok" => true];
}

// Check a typed code. Returns ["ok"=>true] or ["ok"=>false,"err"=>"...","state"=>none|expired|locked|wrong|format].
function login_otp_check($pdo, $uid, $code)
{
    $code = trim((string) $code);
    if (!preg_match('/^[0-9]{6}$/', $code)) {
        return ["ok" => false, "state" => "format", "err" => "Enter the 6-digit code from your e-mail."];
    }
    if (login_otp_secret() === "") {
        return ["ok" => false, "state" => "none", "err" => "Login codes are not set up on the server yet. Please contact the admin."];
    }
    $s = $pdo->prepare("SELECT otp_hash,expires_at,tries FROM login_otps WHERE user_id=? LIMIT 1");
    $s->execute([$uid]);
    $row = $s->fetch();
    if (!$row) {
        return ["ok" => false, "state" => "none", "err" => "No active code. Please request a new one."];
    }
    if (time() > (int) $row["expires_at"]) {
        $pdo->prepare("DELETE FROM login_otps WHERE user_id=?")->execute([$uid]);
        return ["ok" => false, "state" => "expired", "err" => "This code has expired. Please request a new one."];
    }
    if ((int) $row["tries"] >= OTP_MAX_TRIES) {
        return ["ok" => false, "state" => "locked", "err" => "Too many wrong attempts. Please request a new code."];
    }
    // count the try first, so parallel guesses cannot get around the limit
    $pdo->prepare("UPDATE login_otps SET tries=tries+1 WHERE user_id=?")->execute([$uid]);
    if (password_verify(hash_hmac("sha256", $code, login_otp_secret()), $row["otp_hash"])) {
        $pdo->prepare("DELETE FROM login_otps WHERE user_id=?")->execute([$uid]);
        return ["ok" => true];
    }
    $left = OTP_MAX_TRIES - ((int) $row["tries"] + 1);
    return $left > 0
        ? ["ok" => false, "state" => "wrong", "err" => "Wrong code. " . $left . " attempt" . ($left == 1 ? "" : "s") . " left."]
        : ["ok" => false, "state" => "locked", "err" => "Too many wrong attempts. Please request a new code."];
}
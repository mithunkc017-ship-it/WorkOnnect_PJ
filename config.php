<?php
// Shared setup: database, session, helper functions, page header/footer
session_start();
// include __DIR__ . "/ai_widget.php";   // ← delete this line
include_once __DIR__ . "/vendor/autoload.php";
require_once __DIR__ . "/settings.php";
if (!defined("PLATFORM_UPI")) {
    define("PLATFORM_UPI", "mithunshetty017@oksbi"); // the platform's UPI ID that receives customer payments (QR). Override it in settings.php
} // the platform's UPI ID that receives customer payments (QR). Override it in settings.php
if (!defined("PLATFORM_NAME")) {
    define("PLATFORM_NAME", "Workonnect");
}
if (!defined("ADMIN_2FA")) {
    define("ADMIN_2FA", true);
} // admin must enter an e-mail code after the password (set false in settings.php to turn off)
$_SESSION["csrf"] = $_SESSION["csrf"] ?? bin2hex(random_bytes(16));
// CSRF protection: every POST must carry the session token (chat.php is a JSON endpoint, exempt)
if (
    $_SERVER["REQUEST_METHOD"] == "POST" &&
    !in_array(basename($_SERVER["SCRIPT_NAME"]), ["chat.php", "ai_chat.php"]) &&
    !hash_equals($_SESSION["csrf"], $_POST["csrf"] ?? "")
) {
    die("Invalid request (CSRF check failed). Go back and try again.");
}
// Auto-insert the hidden token into every <form method="post">
// ob_start(function ($b) {
//     return preg_replace(
//         '/(<form[^>]*method="post"[^>]*>)/i',
//         '$1<input type="hidden" name="csrf" value="' . $_SESSION["csrf"] . '">',
//         $b,
//     );
// });
ob_start(function ($b) {
    $b = preg_replace(
        '/(<form[^>]*method="post"[^>]*>)/i',
        '$1<input type="hidden" name="csrf" value="' . $_SESSION["csrf"] . '">',
        $b,
    );
    // add the AI assistant just before </body> (not on login/register pages)
    if (!empty($GLOBALS["WAI_HTML"]) && strpos($b, 'class="authcard"') === false && ($p = strripos($b, "</body>")) !== false) {
        $b = substr($b, 0, $p) . $GLOBALS["WAI_HTML"] . substr($b, $p);
    }
    return $b;
});
// Database: MongoDB (set MONGO_URI / MONGO_DB in settings.php). $pdo keeps its old name: mongo_db.php runs the pages' SQL against MongoDB.
require_once __DIR__ . "/mongo_db.php";
if (!defined("MONGO_URI")) {
    define("MONGO_URI", "mongodb://127.0.0.1:27017");
}
if (!defined("MONGO_DB")) {
    define("MONGO_DB", "workonet");
}
try {
    $mongo = new MongoStore(MONGO_URI, MONGO_DB);
    $pdo = new MongoPDO($mongo);
    if (empty($_SESSION["mongo_ready"])) {
        $mongo->ensureSchema();
        $_SESSION["mongo_ready"] = 1;
    } // first visit of a session: create indexes + default categories (safe to repeat)
} catch (Exception $x) {
    http_response_code(500);
    die(
        "<h3>Cannot connect to MongoDB</h3><p>" .
            htmlspecialchars($x->getMessage()) .
            "</p><p>Start the MongoDB service, check MONGO_URI in settings.php and that the PHP mongodb extension is enabled (see README.txt).</p>"
    );
}
// Refresh the logged-in user from the database on every request (role/verified changes apply immediately)
if (!empty($_SESSION["u"])) {
    $r = $pdo->prepare("SELECT * FROM users WHERE id=?");
    $r->execute([$_SESSION["u"]["id"]]);
    $f = $r->fetch();
    if ($f && !$f["banned"]) {
        $_SESSION["u"] = $f;
    } else {
        unset($_SESSION["u"]);
    }
} // banned users are logged out at once
$CATS = [
    "Electrician",
    "Plumber",
    "Carpenter",
    "Painter",
    "Cleaner",
    "Driver",
    "Mechanic",
    "AC Technician",
];
try {
    $d = $pdo
        ->query("SELECT name FROM categories ORDER BY name")
        ->fetchAll(MongoPDO::FETCH_COLUMN);
    if ($d) {
        $CATS = $d;
    }
} catch (Exception $x) {
} // admin-managed list
function e($s)
{
    return htmlspecialchars($s ?? "");
}
function t($s)
{
    return $s;
} // text helper (English only): returns the text unchanged
function me()
{
    return $_SESSION["u"] ?? null;
}
function need($role = null)
{
    $u = me();
    if (!$u || ($role && $u["role"] != $role)) {
        header("Location: login.php");
        exit();
    }
}
define("AI_KEY", ""); // paste your Anthropic API key here for real AI replies (leave empty = simple built-in answers)
function notify($uid, $msg, $link = "dashboard.php")
{
    global $pdo;
    $pdo->prepare(
        "INSERT INTO notifications(user_id,msg,link)VALUES(?,?,?)",
    )->execute([$uid, $msg, $link]);
}
function top($r, $n)
{
    return $r >= 4.5 && $n >= 3
        ? '<span class="ok" style="background:#fff3e0;color:#e65100"><span class="material-icons mi-xs">emoji_events</span> Top Rated</span>'
        : "";
}
function rejected($pdo, $jid, $wid)
{
    $s = $pdo->prepare(
        "SELECT 1 FROM offers WHERE job_id=? AND worker_id=? AND status='rejected'",
    );
    $s->execute([$jid, $wid]);
    return (bool) $s->fetch();
}
function wallet($pdo, $uid, $amt, $note)
{
    $pdo->prepare("UPDATE users SET wallet=wallet+? WHERE id=?")->execute([
        $amt,
        $uid,
    ]);
    $pdo->prepare(
        "INSERT INTO wallet_tx(user_id,amount,note)VALUES(?,?,?)",
    )->execute([$uid, $amt, $note]);
}
function logit($act, $uid = null)
{
    global $pdo;
    $s = me();
    $pdo->prepare(
        "INSERT INTO activity_log(user_id,action)VALUES(?,?)",
    )->execute([$uid ?? ($s["id"] ?? null), $act]);
}
function works($w, $slot)
{
    if (!$slot) {
        return true;
    }
    $d = date("D", strtotime($slot));
    $t = date("H:i:s", strtotime($slot));
    if ($w["work_days"] && strpos($w["work_days"], $d) === false) {
        return false;
    }
    if (
        $w["work_from"] &&
        $w["work_to"] &&
        ($t < $w["work_from"] || $t > $w["work_to"])
    ) {
        return false;
    }
    return true;
}
function level($done, $r)
{
    if ($done >= 20 && $r >= 4.5) {
        return '<span class="ok" style="background:#fff8e1;color:#b8860b"><span class="material-icons mi-xs">workspace_premium</span> Gold</span>';
    }
    if ($done >= 10 && $r >= 4) {
        return '<span class="ok" style="background:#eceff1;color:#546e7a"><span class="material-icons mi-xs">military_tech</span> Silver</span>';
    }
    if ($done >= 3) {
        return '<span class="ok" style="background:#fbe9e7;color:#8d4e2b"><span class="material-icons mi-xs">sports</span> Bronze</span>';
    }
    return "";
}
function expireJobs($pdo)
{
    foreach (
        $pdo
            ->query(
                "SELECT id,customer_id,title FROM jobs WHERE status='open' AND created<DATE_SUB(NOW(),INTERVAL 14 DAY)",
            )
            ->fetchAll()
        as $x
    ) {
        // open jobs with no worker for 14 days
        $r = $pdo->prepare(
            "UPDATE jobs SET status='cancelled' WHERE id=? AND status='open'",
        );
        $r->execute([$x["id"]]);
        if ($r->rowCount()) {
            notify(
                $x["customer_id"],
                '<span class="material-icons mi-xs">hourglass_empty</span> "' .
                    $x["title"] .
                    '" expired after 14 days without a worker. You can book it again.',
                "job.php?id=" . $x["id"],
            );
        }
    }
}
function clash($pdo, $wid, $slot, $skip = 0)
{
    if (!$slot || !$wid) {
        return 0;
    }
    $s = $pdo->prepare(
        "SELECT id FROM jobs WHERE worker_id=? AND id<>? AND status IN('assigned','in_progress') AND slot IS NOT NULL AND ABS(TIMESTAMPDIFF(MINUTE,slot,?))<120 LIMIT 1",
    );
    $s->execute([$wid, $skip, $slot]);
    return (int) $s->fetchColumn();
}
function cancelFee($j)
{
    // cancellation policy: free until a worker accepts; then 5% (min Rs20), or 20% (min Rs50) if the visit is within 24 h / the worker is on the way
    if (!$j["worker_id"] || $j["status"] != "assigned") {
        return 0;
    }
    $base = (int) $j["price"];
    $late =
        $j["onway"] || ($j["slot"] && strtotime($j["slot"]) - time() < 86400);
    $fee = max($late ? 50 : 20, (int) round($base * ($late ? 0.2 : 0.05)));
    return $base > 0 ? min($fee, $base) : $fee;
}
function sendReminders($pdo)
{
    foreach (
        [
            ["rem1", "INTERVAL 1 HOUR", "in about 1 hour"],
            ["rem24", "INTERVAL 24 HOUR", "within 24 hours"],
        ]
        as $r
    ) {
        // booked time slots: reminder for both sides
        $s = $pdo
            ->query(
                "SELECT id,title,slot,customer_id,worker_id FROM jobs WHERE status='assigned' AND slot IS NOT NULL AND {$r[0]}=0 AND slot>NOW() AND slot<=DATE_ADD(NOW(),{$r[1]})",
            )
            ->fetchAll();
        foreach ($s as $j) {
            $pdo->prepare(
                "UPDATE jobs SET {$r[0]}=1" .
                    ($r[0] == "rem1" ? ",rem24=1" : "") .
                    " WHERE id=?",
            )->execute([$j["id"]]);
            foreach ([$j["customer_id"], $j["worker_id"]] as $p) {
                if ($p) {
                    notify(
                        $p,
                        '<span class="material-icons mi">alarm</span> Reminder: "' .
                            $j["title"] .
                            '" is booked for ' .
                            date("d M, h:i A", strtotime($j["slot"])) .
                            " (" .
                            $r[2] .
                            ")",
                        "job.php?id=" . $j["id"],
                    );
                }
            }
        }
    }
}
function announceBar()
{
    global $pdo;
    try {
        $a = $pdo
            ->query(
                "SELECT * FROM announcements WHERE active=1 ORDER BY id DESC LIMIT 1",
            )
            ->fetch();
    } catch (Exception $x) {
        return "";
    }
    if (!$a) {
        return "";
    }
    return '<div class="ann" id="ann"><span class="material-icons mi">campaign</span> ' .
        e($a["msg"]) .
        ($a["link"] ? ' <a href="' . e($a["link"]) . '">Learn more</a>' : "") .
        ' <button onclick="localStorage.setItem(\'ann\',\'' .
        $a["id"] .
        '\');this.parentNode.hidden=true" style="padding:0 8px">×</button></div><script>if(localStorage.getItem("ann")==="' .
        $a["id"] .
        '")document.getElementById("ann").hidden=true</script>';
}
function lh($pdo, $uid, $email, $ok, $note)
{
    try {
        $pdo->prepare(
            "INSERT INTO login_history(user_id,email,ip,ua,ok,note)VALUES(?,?,?,?,?,?)",
        )->execute([
            $uid,
            substr((string) $email, 0, 100),
            $_SERVER["REMOTE_ADDR"] ?? "",
            substr($_SERVER["HTTP_USER_AGENT"] ?? "", 0, 120),
            $ok ? 1 : 0,
            substr($note, 0, 40),
        ]);
    } catch (Exception $x) {
    }
} // login history
function validUpi($v)
{
    return (bool) preg_match(
        '/^[a-zA-Z0-9._-]{2,64}@[a-zA-Z0-9]{2,32}$/',
        (string) $v,
    );
}
function platformUpi($pdo)
{
    // the UPI ID in the ADMIN profile receives the platform fee / QR payments (settings.php PLATFORM_UPI is only the fallback)
    $s = $pdo
        ->query(
            "SELECT upi_id FROM users WHERE role='admin' AND upi_id IS NOT NULL AND upi_id<>'' ORDER BY id LIMIT 1",
        )
        ->fetchColumn();
    return $s ?: PLATFORM_UPI;
}
function finishSplit($pdo, $qid)
{
    // split UPI payment: when the worker AND the admin both confirmed their money, the job becomes paid (and the worker share already settled)
    $s = $pdo->prepare(
        "SELECT * FROM qr_payments WHERE id=? AND mode='split' AND status='submitted' AND worker_ok=1 AND fee_ok=1",
    );
    $s->execute([$qid]);
    $q = $s->fetch();
    if (!$q) {
        return "";
    }
    $j = $pdo->prepare("SELECT * FROM jobs WHERE id=?");
    $j->execute([$q["job_id"]]);
    $j = $j->fetch();
    $c = $pdo->prepare("SELECT * FROM users WHERE id=?");
    $c->execute([$q["user_id"]]);
    $c = $c->fetch();
    if ($j["price"] - $q["discount"] != $q["amount"]) {
        return "The job price changed after this payment was created. Reject it and ask the customer to pay again.";
    }
    $err = finish_payment(
        $pdo,
        $c,
        $j,
        "UPI split",
        $q["amount"],
        $q["discount"],
        $q["coupon"],
        substr($q["fee_utr"] . "/" . $q["worker_utr"], 0, 40),
    );
    if (!$err) {
        $pdo->prepare(
            "UPDATE qr_payments SET status='approved' WHERE id=?",
        )->execute([$q["id"]]);
        $pdo->prepare(
            "UPDATE jobs SET settled=1,settled_at=NOW(),settle_ref=? WHERE id=?",
        )->execute([$q["worker_utr"], $q["job_id"]]);
        notify(
            $q["user_id"],
            '<span class="material-icons mi-xs">check_circle</span> Your UPI payment of ₹' .
                $q["amount"] .
                " was confirmed",
            "job.php?id=" . $q["job_id"],
        );
        logit("Split UPI payment #" . $q["id"] . " confirmed");
    }
    return $err;
}
function badge($v)
{
    return $v
        ? '<span class="ok"><span class="material-icons mi-xs">verified</span> Verified</span>'
        : "";
}

function send_mail($to, $subj, $body, $log = true)
{
    if ($log) {
        @file_put_contents(
            __DIR__ . "/mail_log.txt",
            date("c") . " TO $to | $subj | $body\n",
            FILE_APPEND,
        );
    }
    if (SMTP_HOST !== "") {
        return smtp_send($to, $subj, $body);
    }
    return @mail($to, $subj, $body);
}
function smtp_send($to, $subj, $body)
{
    // small SMTP client (Gmail: smtp.gmail.com, port 587 + App Password)
    $ssl = SMTP_PORT == 465;
    $f = @stream_socket_client(
        ($ssl ? "ssl://" : "tcp://") . SMTP_HOST . ":" . SMTP_PORT,
        $en,
        $es,
        15,
    );
    if (!$f) {
        return false;
    }
    $cm = function ($c, $code) use ($f) {
        if ($c !== null) {
            fwrite($f, $c . "\r\n");
        }
        $r = "";
        while ($l = fgets($f, 515)) {
            $r .= $l;
            if (substr($l, 3, 1) == " ") {
                break;
            }
        }
        return strpos($r, (string) $code) === 0;
    };
    $ok = $cm(null, 220) && $cm("EHLO Workonnect", 250);
    if ($ok && !$ssl) {
        $ok =
            $cm("STARTTLS", 220) &&
            stream_socket_enable_crypto(
                $f,
                true,
                STREAM_CRYPTO_METHOD_TLS_CLIENT,
            ) &&
            $cm("EHLO Workonnect", 250);
    }
    $from = SMTP_FROM ?: SMTP_USER;
    $ok =
        $ok &&
        $cm("AUTH LOGIN", 334) &&
        $cm(base64_encode(SMTP_USER), 334) &&
        $cm(base64_encode(SMTP_PASS), 235) &&
        $cm("MAIL FROM:<$from>", 250) &&
        $cm("RCPT TO:<$to>", 250) &&
        $cm("DATA", 354);
    if ($ok) {
        $m =
            "From: Workonnect <$from>\r\nTo: $to\r\nSubject: =?UTF-8?B?" .
            base64_encode($subj) .
            "?=\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n" .
            preg_replace("/^\./m", "..", $body) .
            "\r\n.";
        $ok = $cm($m, 250);
    }
    @fwrite($f, "QUIT\r\n");
    fclose($f);
    return $ok;
}
function send_otp($pdo, $em)
{
    $o = sprintf("%06d", random_int(0, 999999));
    $pdo->prepare(
        "UPDATE users SET otp=?,otp_expires=DATE_ADD(NOW(),INTERVAL 10 MINUTE),otp_tries=0 WHERE email=?",
    )->execute([hash("sha256", $o), $em]);
    $_SESSION["otp_t"] = time();
    send_mail(
        $em,
        "Your Workonnect verification code",
        "Your Workonnect verification code is " .
            $o .
            ". It is valid for 10 minutes.",
        false, // never write the code to mail_log.txt
    );
}
function finish_payment($pdo, $u, $j, $m, $final, $disc, $code, $ref = "")
{
    // atomic: marks job paid once, takes wallet money, pays referral bonus
    $pdo->beginTransaction();
    try {
        $s = $pdo->prepare(
            "SELECT wallet,referred_by,ref_rewarded FROM users WHERE id=? FOR UPDATE",
        );
        $s->execute([$u["id"]]);
        $w = $s->fetch();
        if ($m == "Wallet" && $w["wallet"] < $final) {
            $pdo->rollBack();
            return "Not enough wallet balance (₹" .
                (int) $w["wallet"] .
                "). Top up your wallet first.";
        }
        $r = $pdo->prepare(
            "UPDATE jobs SET paid=1,pay_method=?,pay_ref=?,discount=?,price=?,commission=ROUND(?*0.10),escrow=IF(confirmed=1,0,1),paid_at=NOW() WHERE id=? AND paid=0",
        );
        $r->execute([$m, $ref, $disc, $final, $final, $j["id"]]);
        if (!$r->rowCount()) {
            $pdo->rollBack();
            return "This job is already paid.";
        }
        if ($code !== "") {
            $pdo->prepare(
                "UPDATE coupons SET used=used+1 WHERE code=?",
            )->execute([$code]);
        }
        if ($m == "Wallet") {
            wallet($pdo, $u["id"], -$final, "Paid for job #" . $j["id"]);
        }
        if ($w["referred_by"] && !$w["ref_rewarded"]) {
            wallet($pdo, $u["id"], 50, "Referral bonus");
            wallet($pdo, $w["referred_by"], 50, "Referral bonus (friend paid)");
            $pdo->prepare(
                "UPDATE users SET ref_rewarded=1 WHERE id=?",
            )->execute([$u["id"]]);
        }
        $pdo->commit();
    } catch (Exception $x) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return "Payment failed, please try again.";
    }
    notify(
        $j["worker_id"],
        '<span class="material-icons mi">payments</span> Payment of ₹' .
            $final .
            ' received for "' .
            $j["title"] .
            '"',
        "job.php?id=" . $j["id"],
    );
    logit("Paid ₹" . $final . " for job #" . $j["id"] . " via " . $m);
    return "";
}
function fld($label, $icon, $html)
{
    return '<label class="lb">' .
        t($label) .
        '</label><div class="fld"><i>' .
        $icon .
        "</i>" .
        $html .
        "</div>";
}
function pwf($label, $name, $ph, $meter = false, $auto = "current-password")
{
    static $eyeCss = false;
    $css = "";
    if (!$eyeCss) {
        $eyeCss = true;
        $css =
            '<style>.fld .eye{display:flex;align-items:center;justify-content:center;padding:4px 8px;background:none;border:0;box-shadow:none;cursor:pointer;color:#6b7c85;line-height:0}.fld .eye:hover{color:#1a6b7c}.fld .eye svg{width:20px;height:20px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}.fld .eye .ei-off{display:none}.fld .eye.on .ei-off{display:block}.fld .eye.on .ei-on{display:none}</style>';
    }
    return $css .
        '<label class="lb">' .
        t($label) .
        '</label><div class="fld"><span class="material-icons mi">lock</span><input type="password" name="' .
        $name .
        '" placeholder="' .
        e($ph) .
        '" autocomplete="' .
        $auto .
        '" minlength="6" required' .
        ($meter ? " data-meter" : "") .
        '><button type="button" class="eye" aria-label="' .
        e(t("Show")) .
        '" aria-pressed="false" data-s="' .
        e(t("Show")) .
        '" data-h="' .
        e(t("Hide")) .
        '"><svg class="ei-on" viewBox="0 0 24 24" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg><svg class="ei-off" viewBox="0 0 24 24" aria-hidden="true"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg></button></div>' .
        ($meter
            ? '<div class="meter"><i></i><i></i><i></i><i></i></div><small id="pwl"></small>'
            : "");
}
function auth_open($eyebrow, $title, $sub = "")
{
    $GLOBALS["wk_auth"] = true; // login/register-style pages: no floating chat button
    echo '<style>body>nav,body>footer{display:none}.authcard .mark{display:none!important}.authcard .cta{display:flex!important;align-items:center;justify-content:center;width:100%;border-radius:999px!important;font-weight:700!important;letter-spacing:.02em;padding:14px 20px!important;cursor:pointer}.authcard .cta .go{display:none!important}.authcard .alt{text-align:center;opacity:.8;font-size:.95rem}.authcard .alt a{color:inherit!important;text-decoration:underline!important;font-weight:inherit!important}.authcard .or + .alt a{color:#3b82f6!important;font-weight:700!important;text-decoration:none!important}.authcard .or + .alt a:hover{text-decoration:underline!important}/* ==========================================================
   Login page button – blue (separate file, CSS only)
   Does not touch any PHP / JS / form behaviour.
   Change --login-blue to restyle: #2563eb = pure blue,
   #4f46e5 = indigo (same as the rest of the app)
   ========================================================== */
.authcard {
    --login-blue:      #4f46e5;
    --login-blue-dark: #4338ca;
    --login-blue-glow: rgba(79, 70, 229, .45);
}

html body .authcard .cta {
    background: var(--login-blue);
    color: #fff;
    border: 0;
    box-shadow: 0 1px 2px var(--login-blue-glow), 0 8px 18px -8px var(--login-blue-glow);
    transition: background .15s ease, transform .1s ease, box-shadow .15s ease;
}
html body .authcard .cta:hover {
    background: var(--login-blue-dark);
    color: #fff;
    transform: translateY(-1px);
    box-shadow: 0 2px 4px var(--login-blue-glow), 0 12px 22px -8px var(--login-blue-glow);
}
html body .authcard .cta:active {
    transform: translateY(0);
    box-shadow: inset 0 2px 4px rgba(0, 0, 0, .25);
}
html body .authcard .cta:focus-visible {
    outline: 3px solid rgba(79, 70, 229, .4);
    outline-offset: 3px;
}
/* while "Logging in…" is showing (button is disabled by the existing script) */
html body .authcard .cta:disabled {
    background: var(--login-blue);
    opacity: .7;
    cursor: wait;
    transform: none;
    box-shadow: none;
}
/* ==========================================================
   Login page button – blue (separate file, CSS only)
   Does not touch any PHP / JS / form behaviour.
   Change --login-blue to restyle: #2563eb = pure blue,
   #4f46e5 = indigo (same as the rest of the app)
   ========================================================== */
.authcard {
    --login-blue:      #4f46e5;
    --login-blue-dark: #4338ca;
    --login-blue-glow: rgba(79, 70, 229, .45);
}

html body .authcard .cta {
    background: var(--login-blue);
    color: #fff;
    border: 0;
    box-shadow: 0 1px 2px var(--login-blue-glow), 0 8px 18px -8px var(--login-blue-glow);
    transition: background .15s ease, transform .1s ease, box-shadow .15s ease;
}
html body .authcard .cta:hover {
    background: var(--login-blue-dark);
    color: #fff;
    transform: translateY(-1px);
    box-shadow: 0 2px 4px var(--login-blue-glow), 0 12px 22px -8px var(--login-blue-glow);
}
html body .authcard .cta:active {
    transform: translateY(0);
    box-shadow: inset 0 2px 4px rgba(0, 0, 0, .25);
}
html body .authcard .cta:focus-visible {
    outline: 3px solid rgba(79, 70, 229, .4);
    outline-offset: 3px;
}
/* while "Logging in…" is showing (button is disabled by the existing script) */
html body .authcard .cta:disabled {
    background: var(--login-blue);
    opacity: .7;
    cursor: wait;
    transform: none;
    box-shadow: none;
}

/* ==========================================================
   Register page buttons – same blue as login
   (Send code, Verify, Create account). CSS only.
   ========================================================== */
html body .vsend,
html body .vgo {
    background: var(--login-blue);
    color: #fff;
    border: 0;
    box-shadow: 0 1px 2px var(--login-blue-glow), 0 4px 10px -4px var(--login-blue-glow);
    transition: background .15s ease, transform .1s ease, box-shadow .15s ease;
}
html body .vsend:hover:not(:disabled),
html body .vgo:hover:not(:disabled) {
    background: var(--login-blue-dark);
    filter: none;
    color: #fff;
    box-shadow: 0 2px 4px var(--login-blue-glow), 0 8px 16px -6px var(--login-blue-glow);
}
html body .vsend:active:not(:disabled),
html body .vgo:active:not(:disabled) {
    background: var(--login-blue-dark);
    box-shadow: inset 0 2px 4px rgba(0, 0, 0, .25);
}
html body .vsend:focus-visible,
html body .vgo:focus-visible {
    outline: 3px solid rgba(79, 70, 229, .4);
    outline-offset: 2px;
}
html body .vsend:disabled,
html body .vgo:disabled {
    background: var(--login-blue);
    opacity: .55;
    filter: none;
    box-shadow: none;
}
/* Create account button when still locked (before email/phone are verified) */
html body .authcard .cta:disabled { cursor: not-allowed; }

/* focus rings on register fields: teal -> blue */
html body .rgrid .fld:focus-within { box-shadow: 0 0 0 3px rgba(79, 70, 229, .2); }
html body .votp .vcode:focus {
    border-color: var(--login-blue);
    box-shadow: 0 0 0 3px rgba(79, 70, 229, .2);
}

/* terms checkbox: blue when ticked, white tick */
.chk{display:flex;align-items:flex-start;gap:10px;cursor:pointer;font-size:.9rem;line-height:1.4}
.chk input[type=checkbox]{
  -webkit-appearance:none;appearance:none;flex:none;
  width:20px;height:20px;margin:1px 0 0;cursor:pointer;
  border:2px solid rgba(128,128,128,.55);border-radius:6px;background:transparent;
  display:grid;place-content:center;
  transition:background .15s,border-color .15s,box-shadow .15s}
.chk input[type=checkbox]::after{
  content:"";width:10px;height:10px;transform:scale(0);transition:transform .15s ease;
  background:#fff;
  clip-path:polygon(14% 44%,0 58%,40% 100%,100% 18%,86% 4%,40% 70%)}
.chk input[type=checkbox]:checked{background:#1a73e8;border-color:#1a73e8}
.chk input[type=checkbox]:checked::after{transform:scale(1)}
.chk input[type=checkbox]:focus-visible{box-shadow:0 0 0 3px rgba(26,115,232,.35)}
.chk a{color:#1a73e8;text-decoration:none;font-weight:600}
.chk a:hover{text-decoration:underline}

/* divider + Google button */
.odiv{display:flex;align-items:center;gap:12px;margin:16px 0 12px;font-size:.85rem;opacity:.7}
.odiv::before,.odiv::after{content:"";flex:1;height:1px;background:rgba(128,128,128,.35)}
.gbtn{
  display:flex;align-items:center;justify-content:center;gap:12px;width:100%;min-height:48px;
  border:1px solid #dadce0;border-radius:12px;background:#fff;color:#3c4043;
  font-family:inherit;font-weight:600;font-size:.95rem;text-decoration:none;
  box-shadow:0 1px 2px rgba(0,0,0,.08);
  transition:box-shadow .15s,background .15s,transform .12s}
.gbtn:hover{background:#f8f9fa;box-shadow:0 2px 6px rgba(0,0,0,.15)}
.gbtn:active{transform:scale(.98)}
.gbtn:focus-visible{outline:3px solid rgba(26,115,232,.4);outline-offset:2px}
.gbtn svg{width:20px;height:20px;flex:none}


/* footer + Google button alignment */
.authcard a.gbtn{color:#3c4043;text-decoration:none}
.authcard a.gbtn:hover{color:#3c4043}

/* make everything after the form grid span the full card and center */
.authcard > *:not(.rgrid):not(.err):not(.vprog){
  grid-column:1/-1;
  width:100%;
  max-width:100%;
  margin-left:0;
  margin-right:0;
  text-align:center;
  justify-self:stretch;
}
.authcard > .back,
.authcard > a[href*="index"]{text-align:left}


</style><div class="auth"><form class="authcard" method="post"><p class="eyebrow">' .
        e(t($eyebrow)) .
        "</p><h1>" .
        e(t($title)) .
        "</h1>" .
        ($sub ? '<p class="sub">' . e(t($sub)) . "</p>" : "");
}
function auth_close($alt = "")
{
    $L = [
        t("Weak password"),
        t("Fair password"),
        t("Strong password"),
        t("Very strong password"),
    ];
    echo ($alt
        ? '<div class="or"><span>' .
            t("OR") .
            '</span></div><p class="alt">' .
            $alt .
            "</p>"
        : "") .
        '</form><a class="back" href="index.php">' .
        t("← Back to Workonnect") .
        "</a></div><script>var L=" .
        json_encode($L, JSON_UNESCAPED_UNICODE) .
        ';document.querySelectorAll(".authcard").forEach(function(f){f.addEventListener("submit",function(e){var b=e.submitter;if(b&&b.classList.contains("cta")){b.querySelector("span").textContent=b.dataset.busy;b.querySelector(".go").textContent="…";setTimeout(function(){b.disabled=true},0)}})});document.querySelectorAll(".eye").forEach(function(b){b.onclick=function(){var i=b.closest(".fld").querySelector("input"),s=i.type=="password";i.type=s?"text":"password";b.classList.toggle("on",s);b.setAttribute("aria-label",s?b.dataset.h:b.dataset.s);b.setAttribute("aria-pressed",s?"true":"false")}});var pw=document.querySelector("input[data-meter]");if(pw){var m=document.querySelectorAll(".meter i"),l=document.getElementById("pwl");pw.oninput=function(){var v=pw.value,n=0;if(v.length>=8)n++;if(/[a-z]/.test(v)&&/[A-Z]/.test(v))n++;if(/\\d/.test(v))n++;if(/[^A-Za-z0-9]/.test(v)||v.length>=12)n++;if(!v)n=0;m.forEach(function(x,k){x.className=k<n?"on s"+n:""});l.textContent=v?(L[n-1]||L[0]):""}}</script>';
}
function head($t)
{
    $u = me();
    $isHome = basename($_SERVER["SCRIPT_NAME"]) == "index.php";
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' .
        e($t) .
        ' - Workonnect</title><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@400;600&family=Gaegu:wght@700&display=swap" rel="stylesheet"><link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet"><link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet"><link rel="stylesheet" href="style.css?v=' . (int) @filemtime(__DIR__ . "/style.css") . '"><link rel="manifest" href="manifest.json"><meta name="theme-color" content="#1a6b7c"><link rel="icon" href="icons/icon-192.png"><link rel="apple-touch-icon" href="icons/icon-192.png"></head><body><nav><a class="logo" href="index.php">Work<span>onnect</span></a><button class="nvb" type="button" aria-label="Menu" onclick="this.parentNode.classList.toggle(`open`)"><span class="material-icons mi">menu</span></button><div>';
    if ($u) {
        global $pdo;
        $n = $pdo->prepare(
            "SELECT COUNT(*) FROM notifications WHERE user_id=? AND seen=0",
        );
        $n->execute([$u["id"]]);
        $n = $n->fetchColumn();
        echo '<a href="dash.php"><span class="material-icons mi">dashboard</span>' .
            t("Dashboard") .
            '</a><span id="bell" class="bellw"><a href="#" onclick="return tb()"><span class="material-icons mi">notifications</span><span class="nl" style="display:none">Notifications</span><b id="bc"' .
            ($n ? "" : " hidden") .
            ">" .
            $n .
            '</b></a><div id="np" hidden></div></span>';
        if ($u["role"] == "customer") {
            echo '<a href="post_job.php"><span class="material-icons mi">add_circle</span>' .
                t("Post Job") .
                '</a><a href="wallet.php"><span class="material-icons mi">account_balance_wallet</span> Wallet</a>';
        }
        if ($u["role"] == "worker") {
            echo '<a href="open_jobs.php"><span class="material-icons mi">lock_open</span> Open Jobs</a><a href="profile.php"><span class="material-icons mi">person</span>My Profile</a><a href="earnings.php"><span class="material-icons mi">payments</span> Earnings</a><a href="portfolio.php"><span class="material-icons mi">photo_library</span> My Work</a><a href="verify.php"><span class="material-icons mi">verified</span> Get Verified</a>';
        }
        if ($u["role"] == "admin") {
            // frequently used admin pages are one click away; "More Tools" opens the popup with the rest
            echo '<a href="dashboard.php"><span class="material-icons mi">tune</span>Manage</a>' .
                '<a href="analytics.php"><span class="material-icons mi">analytics</span>Analytics</a>' .
                '<a href="qr_payments.php"><span class="material-icons mi">payments</span>Payments</a>' .
                '<a href="settlements.php"><span class="material-icons mi">attach_money</span>Settlements</a>' .
                '<a href="disputes.php"><span class="material-icons mi">balance</span>Disputes</a>' .
                '<a href="verifications.php"><span class="material-icons mi">fact_check</span>Verifications</a>' .
                '<a href="complaints.php"><span class="material-icons mi">report</span>Complaints</a>' .
                '<a href="announcements.php"><span class="material-icons mi">campaign</span>Announcements</a>' .
                '<a href="log.php"><span class="material-icons mi">history</span>Activity</a>' .
                '<a href="dash.php" id="admBtn" onclick="return window.admTog ? admTog() : true"><span class="material-icons mi">apps</span>More Tools</a>';
        }
        echo '<a href="account_settings.php"><span class="material-icons mi">settings</span>Settings</a>';
        echo '<a href="logout.php"><span class="material-icons mi">logout</span>' .
            t("Logout") .
            "</a>";
    } else {
        echo '<a href="login.php"><span class="material-icons mi">login</span>' .
            t("Login") .
            '</a><a href="register.php"><span class="material-icons mi">person_add</span>' .
            t("Register") .
            "</a>";
    }
    echo '<a href="workers.php"><span class="material-icons mi">search</span>' .
        t("Find Workers") .
        '</a>' .
        ($isHome
            ? ""
            : (($u && $u["role"] == "admin")
                ? ""
                : '<a href="help.php"><span class="material-icons mi">help</span>Help</a>') .
            '<a href="#" id="dmBtn" onclick="document.body.classList.toggle(\'dark\');localStorage.d=document.body.classList.contains(\'dark\')?1:\'\';return false"><span class="material-icons mi" id="dmIcon">dark_mode</span><span class="nl" style="display:none">Dark mode</span></a>') .
        "</div></nav>" .
        announceBar() .
        "<main>";
}

function foot()
{
    $isHome = basename($_SERVER["SCRIPT_NAME"]) == "index.php";
    $isAuth = !empty($GLOBALS["wk_auth"]);
    echo '</main><footer>© Workonnect – Final Year BCA Project · <a href="#" id="inst" hidden><span class="material-icons mi">install_mobile</span> Install app</a></footer>';
    if (!$isHome && !$isAuth) {
        echo '<style>#cb{position:fixed;right:18px;bottom:18px;z-index:9}#cw{display:none;width:300px;background:#fff;border-radius:10px;box-shadow:0 4px 18px #0004;margin-bottom:8px}#cw header{background:#1a6b7c;color:#fff;padding:10px;border-radius:10px 10px 0 0}#cm{height:240px;overflow:auto;padding:8px;font-size:14px}#cm p{margin:6px 0;padding:6px 10px;border-radius:8px;background:#eef3ff}#cm p.me{background:#fff3e0;text-align:right}#cw input{margin:0;border-radius:0 0 10px 10px}</style>
<div id="cb"><div id="cw"><header><span class="material-icons mi">smart_toy</span> Workonnect Assistant</header><div id="cm"><p>Hi! Ask me how to post a job, send offers, pay or track progress.</p></div><input id="ci" placeholder="Type and press Enter"></div><button onclick="cw.style.display=cw.style.display==\'block\'?\'none\':\'block\'"><span class="material-icons mi">chat</span> Chat</button></div>';
    }
    echo '<script>if("serviceWorker" in navigator)navigator.serviceWorker.register("sw.js");var DP;addEventListener("beforeinstallprompt",function(e){e.preventDefault();DP=e;var i=document.getElementById("inst");i.hidden=false;i.onclick=function(){DP.prompt();return false}});';
    if (!$isHome) {
        if (!$isAuth) {
        echo 'ci.onkeydown=async e=>{if(e.key!="Enter"||!ci.value.trim())return;let t=ci.value;ci.value="";add(t,"me");let r=await fetch("chat.php",{method:"POST",body:JSON.stringify({msg:t})});add((await r.json()).reply)};function add(t,c){let p=document.createElement("p");p.className=c||"";p.textContent=t;cm.appendChild(p);cm.scrollTop=1e9}';
        }
        echo "if(localStorage.d)document.body.classList.add('dark');";
    }
        echo "</script>";
    notif_script();
    echo <<<'HTML'
<style>
nav a.nvon{background:linear-gradient(90deg,#3b82f6,#6366f1)!important;color:#fff!important}
nav a.nvon .material-icons{color:#fff!important}
</style>
<script>
(function(){
  var nav=document.querySelector("body>nav"); if(!nav) return;
  var links=[].slice.call(nav.querySelectorAll("a[href]"));
  var adm=document.getElementById("admBtn");
  var old=["active","on","current","sel","is-active","selected","nvon"];
  var cur=location.pathname.split("/").pop()||"index.php";
  function file(a){return (a.getAttribute("href")||"").split("#")[0].split("?")[0].split("/").pop();}
  function paint(){
    links.forEach(function(a){old.forEach(function(c){a.classList.remove(c)})});
    var open=document.body.classList.contains("adm-open"), pick=null;
    if(adm && open) pick=adm;
    if(!pick){
      pick=links.filter(function(a){return a!==adm && file(a)===cur})[0]||null;
    }
    if(pick) pick.classList.add("nvon");
  }
  paint();
  new MutationObserver(paint).observe(document.body,{attributes:true,attributeFilter:["class"]});
})();
</script>
<script>
(function(){
  // make sure a sun icon exists even if landing_icons.svg has no #i-sun
  if(!document.getElementById("i-sun")){
    var s=document.createElement("div");
    s.style.display="none";
    s.innerHTML='<svg xmlns="http://www.w3.org/2000/svg"><symbol id="i-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/></symbol></svg>';
    document.body.appendChild(s);
  }
  function isDark(){
    var d=document.documentElement,b=document.body;
    return b.classList.contains("dark")||d.classList.contains("dark")||
           b.classList.contains("dark-mode")||d.classList.contains("dark-mode")||
           d.getAttribute("data-theme")==="dark"||b.getAttribute("data-theme")==="dark";
  }
  function sync(){
    var dark=isDark();
    // 1) SVG icons (landing page): any <use> pointing to moon or sun
    document.querySelectorAll('use[href="#i-moon"],use[href="#i-sun"],use[*|href="#i-moon"],use[*|href="#i-sun"]').forEach(function(u){
      u.setAttribute("href",dark?"#i-sun":"#i-moon");
    });
    // 2) Material icons (inner pages)
    document.querySelectorAll(".material-icons").forEach(function(m){
      var t=m.textContent.trim();
      if(t==="dark_mode"||t==="light_mode") m.textContent=dark?"light_mode":"dark_mode";
    });
    var b=document.getElementById("dmBtn");
    if(b) b.setAttribute("aria-label",dark?"Switch to light mode":"Switch to dark mode");
  }
  sync();
  var mo=new MutationObserver(sync);
  mo.observe(document.body,{attributes:true,attributeFilter:["class","data-theme"]});
  mo.observe(document.documentElement,{attributes:true,attributeFilter:["class","data-theme"]});
  document.addEventListener("click",function(){setTimeout(sync,0);setTimeout(sync,150);});
})();
</script>

HTML;
    echo "</body></html>";
}

require_once __DIR__ . "/dash_lib.php";
require_once __DIR__ . "/login_otp.php";
require_once __DIR__ . "/notif_lib.php";
require_once __DIR__ . "/payment_flow.php";

// Render the AI assistant once for logged-in users; the buffer above injects it into every HTML page
if (me() && !in_array(basename($_SERVER["SCRIPT_NAME"]), ["ai_chat.php", "chat.php"])) {
    ob_start();
    include __DIR__ . "/ai_widget.php";
    $GLOBALS["WAI_HTML"] = ob_get_clean();
}
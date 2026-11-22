<?php // WorkOnnect Assistant - OFFLINE chat endpoint. No external API: predefined replies + your own database tools.
require "config.php";
need();
$u = me();
ini_set("display_errors", "0");
header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");

$RATE = 30;               // max messages per minute per session
$role = $u["role"];
$L = "en";

/* ================= helpers ================= */
function out($a, $c = 200) { http_response_code($c); echo json_encode($a, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE); exit; }
function err($m) { return ["error" => $m]; }
function col($r, $names) { foreach ($names as $n) { if (array_key_exists($n, $r) && $r[$n] !== null && $r[$n] !== "") return $r[$n]; } return null; }
function wstats($id) {
    global $pdo; $s = []; $done = 0;
    try { $q = $pdo->prepare("SELECT r.stars FROM reviews r JOIN jobs j ON j.id=r.job_id WHERE j.worker_id=?"); $q->execute([$id]); foreach ($q->fetchAll() as $x) $s[] = (float) $x["stars"]; } catch (Throwable $e) {}
    try { $q = $pdo->prepare("SELECT id,status FROM jobs WHERE worker_id=?"); $q->execute([$id]); foreach ($q->fetchAll() as $x) if ($x["status"] == "completed") $done++; } catch (Throwable $e) {}
    return [$s ? round(array_sum($s) / count($s), 1) : null, $done];
}
function wfix($r) { return $r + ["work_days" => null, "work_from" => null, "work_to" => null]; }
function wcard($r, $i, $stats) {
    $ph = (string) col($r, ["photo", "avatar", "image", "profile_pic", "pic"]);
    $hrs = trim(((string) ($r["work_days"] ?? "")) . " " . ($r["work_from"] ? substr($r["work_from"], 0, 5) . "-" . substr((string) $r["work_to"], 0, 5) : ""));
    return ["index" => $i, "id" => (int) $r["id"], "name" => $r["name"],
        "category" => col($r, ["category", "skill", "skills", "service", "services"]), "location" => col($r, ["location", "city", "area", "address"]),
        "experience" => col($r, ["experience", "exp", "years_exp"]), "verified" => !empty(col($r, ["verified", "is_verified"])),
        "rate" => col($r, ["rate", "hourly_rate", "charge", "base_price", "price"]), "hours" => $hrs ?: null,
        "available" => works(wfix($r), date("Y-m-d\TH:i")), "rating" => $stats[0], "jobs_done" => $stats[1],
        "photo" => preg_match('#^uploads/[\w./-]+$#', $ph) ? $ph : null];
}
function jobcard($r) { return ["id" => (int) $r["id"], "title" => $r["title"], "status" => $r["status"], "slot" => $r["slot"] ?? null, "price" => $r["price"] ?? null, "person" => $r["person"] ?? null]; }
function wid($a) { return !empty($a["worker_id"]) ? (int) $a["worker_id"] : (int) ($_SESSION["ai_last"][(int) ($a["index"] ?? 0) - 1] ?? 0); }
function getworker($id) {
    global $pdo; $q = $pdo->prepare("SELECT * FROM users WHERE id=?"); $q->execute([$id]); $r = $q->fetch();
    return ($r && $r["role"] == "worker" && empty($r["banned"])) ? $r : null;
}

/* ================= database tools ================= */
function t_search_workers($a) {
    global $pdo;
    $slot = null;
    if (!empty($a["slot"]) && ($d = date_create((string) $a["slot"]))) $slot = $d->format("Y-m-d\TH:i");
    $rows = $pdo->query("SELECT * FROM users WHERE role='worker'")->fetchAll();
    $hasCat = false; foreach ($rows as $r) if (col($r, ["category", "skill", "skills", "service", "services"]) !== null) { $hasCat = true; break; }
    $cand = [];
    foreach ($rows as $r) {
        if (!empty($r["banned"])) continue;
        $c = (string) col($r, ["category", "skill", "skills", "service", "services"]); $l = (string) col($r, ["location", "city", "area", "address"]);
        if (!empty($a["category"]) && $hasCat && stripos($c, $a["category"]) === false && stripos($a["category"], $c ?: "\0") === false) continue;
        if (!empty($a["location"]) && stripos($l, $a["location"]) === false) continue;
        if (!empty($a["verified_only"]) && empty(col($r, ["verified", "is_verified"]))) continue;
        if ($slot && (!works(wfix($r), $slot) || clash($pdo, $r["id"], $slot))) continue;
        $cand[] = $r;
    }
    $cand = array_slice($cand, 0, 25); $items = [];
    foreach ($cand as $r) { $st = wstats($r["id"]); $items[] = [$r, $st]; }
    usort($items, fn($x, $y) => [($y[1][0] ?? 0), $y[1][1]] <=> [($x[1][0] ?? 0), $x[1][1]]);
    $out = []; foreach (array_slice($items, 0, 5) as $i => $x) $out[] = wcard($x[0], $i + 1, $x[1]);
    $_SESSION["ai_last"] = array_column($out, "id");
    return ["count" => count($out), "workers" => $out, "_card" => ["type" => "workers", "items" => $out]];
}
function t_estimate_service_price($a) {
    global $pdo;
    $q = $pdo->prepare("SELECT price,category FROM jobs WHERE paid=1 ORDER BY id DESC LIMIT 400"); $q->execute([]);
    $p = [];
    foreach ($q->fetchAll() as $r) if ((int) $r["price"] > 0 && stripos((string) $r["category"], (string) ($a["category"] ?? "")) !== false) $p[] = (int) $r["price"];
    if (count($p) < 3) return ["note" => "few"];
    return ["_card" => ["type" => "estimate", "category" => $a["category"], "low" => min($p), "avg" => (int) round(array_sum($p) / count($p)), "high" => max($p), "samples" => count($p)]];
}
function t_prepare_booking($a) {
    global $pdo, $u;
    $wid = wid($a); $w = getworker($wid); if (!$w) return err("Worker not found.");
    $d = date_create((string) ($a["slot"] ?? ""));
    if (!$d || $d->getTimestamp() < time() + 1800) return err("slot:Please give a valid future date and time.");
    $slot = $d->format("Y-m-d\TH:i");
    $title = mb_substr(trim((string) ($a["title"] ?? "")), 0, 80); if ($title === "") return err("title:Please tell me what work you need.");
    if (!works(wfix($w), $slot)) return err("slot:This worker doesn't work at that day/time. Please choose another time.");
    if (clash($pdo, $wid, $slot)) return err("slot:This worker already has a job around that time. Please choose another time.");
    $tok = bin2hex(random_bytes(8));
    $cat = mb_substr((string) ($a["category"] ?? col($w, ["category", "skill", "service"]) ?? "Other"), 0, 40);
    $p = ["worker_id" => $wid, "worker" => $w["name"], "title" => $title, "category" => $cat, "details" => mb_substr((string) ($a["details"] ?? ""), 0, 500),
        "location" => mb_substr((string) ($a["location"] ?? col($w, ["location", "city", "area"]) ?? ""), 0, 120), "slot" => $slot];
    $_SESSION["ai_pending"][$tok] = $p + ["uid" => $u["id"], "exp" => time() + 600];
    return ["_card" => ["type" => "confirm", "token" => $tok] + array_diff_key($p, ["worker_id" => 1])];
}
function t_get_my_bookings($a) {
    global $pdo, $u;
    $q = $pdo->prepare("SELECT j.id,j.title,j.status,j.slot,j.price,w.name person FROM jobs j LEFT JOIN users w ON w.id=j.worker_id WHERE j.customer_id=? ORDER BY j.id DESC LIMIT 8");
    $q->execute([$u["id"]]);
    $rows = array_map("jobcard", $q->fetchAll());
    return ["n" => count($rows), "_card" => ["type" => "bookings", "title" => "Your bookings", "items" => $rows]];
}
function t_get_booking_status($a) {
    global $pdo, $u;
    $q = $pdo->prepare("SELECT j.id,j.title,j.status,j.slot,j.price,w.name person FROM jobs j LEFT JOIN users w ON w.id=j.worker_id WHERE j.id=? AND j.customer_id=?");
    $q->execute([(int) ($a["job_id"] ?? 0), $u["id"]]); $r = $q->fetch();
    if (!$r) return err("No such booking on this account.");
    $c = jobcard($r);
    return ["booking" => $c, "_card" => ["type" => "bookings", "title" => "Booking #" . $c["id"], "items" => [$c]]];
}
function t_prepare_cancel($a) {
    global $pdo, $u;
    $q = $pdo->prepare("SELECT * FROM jobs WHERE id=? AND customer_id=?"); $q->execute([(int) ($a["job_id"] ?? 0), $u["id"]]); $j = $q->fetch();
    if (!$j || !in_array($j["status"], ["open", "requested", "assigned"])) return err("This booking can't be cancelled.");
    $fee = (int) cancelFee($j);
    return ["fee" => $fee, "_card" => ["type" => "cancel", "job_id" => (int) $j["id"], "title" => $j["title"], "fee" => $fee]];
}
function t_get_worker_jobs($a) {
    global $pdo, $u;
    $q = $pdo->prepare("SELECT j.id,j.title,j.status,j.slot,j.price,c.name person FROM jobs j JOIN users c ON c.id=j.customer_id WHERE j.worker_id=? ORDER BY j.id DESC LIMIT 30");
    $q->execute([$u["id"]]);
    $st = in_array($a["status"] ?? "", ["requested", "assigned", "in_progress", "completed"]) ? $a["status"] : null;
    $rows = []; foreach ($q->fetchAll() as $r) if (!$st || $r["status"] == $st) $rows[] = jobcard($r);
    $rows = array_slice($rows, 0, 8);
    return ["n" => count($rows), "_card" => ["type" => "bookings", "title" => "Your jobs", "items" => $rows]];
}
function t_get_worker_earnings($a) {
    global $pdo, $u;
    $m = preg_match('/^\d{4}-\d{2}$/', $a["month"] ?? "") ? $a["month"] : null;
    $q = $pdo->prepare("SELECT price,commission,tip,settled,done_at FROM jobs WHERE worker_id=? AND paid=1"); $q->execute([$u["id"]]);
    $n = $net = $tips = $set = 0;
    foreach ($q->fetchAll() as $r) {
        if ($m && substr((string) $r["done_at"], 0, 7) != $m) continue;
        $n++; $v = (int) $r["price"] - (int) $r["commission"]; $net += $v; $tips += (int) $r["tip"]; if (!empty($r["settled"])) $set += $v;
    }
    return ["net" => $net, "_card" => ["type" => "stats", "title" => "Earnings" . ($m ? " · $m" : " · all time"), "rows" => [["Paid jobs", $n], ["Net earnings", "₹$net"], ["Tips", "₹$tips"], ["Already settled", "₹$set"]]]];
}
function t_get_worker_performance($a) {
    global $pdo, $u;
    $q = $pdo->prepare("SELECT status,created FROM jobs WHERE worker_id=?"); $q->execute([$u["id"]]);
    $done = $act = $can = 0; $ym = date("Y-m");
    foreach ($q->fetchAll() as $r) {
        if (substr((string) $r["created"], 0, 7) != $ym) continue;
        if ($r["status"] == "completed") $done++; elseif ($r["status"] == "cancelled") $can++; elseif (in_array($r["status"], ["requested", "assigned", "in_progress"])) $act++;
    }
    $st = wstats($u["id"]);
    return ["rating" => $st[0], "_card" => ["type" => "stats", "title" => "This month", "rows" => [["Completed", $done], ["In progress / upcoming", $act], ["Cancelled", $can], ["Average rating", $st[0] ?? "–"]]]];
}
function t_update_worker_availability($a) {
    global $pdo, $u;
    $days = array_values(array_intersect(array_map("trim", explode(",", (string) ($a["days"] ?? ""))), ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"]));
    $f = preg_match('/^\d{2}:\d{2}$/', $a["from"] ?? "") ? $a["from"] . ":00" : null; $t = preg_match('/^\d{2}:\d{2}$/', $a["to"] ?? "") ? $a["to"] . ":00" : null;
    if (!$days || !$f || !$t || $f >= $t) return err("bad");
    $pdo->prepare("UPDATE users SET work_days=?,work_from=?,work_to=? WHERE id=? AND role='worker'")->execute([implode(",", $days), $f, $t, $u["id"]]);
    return ["ok" => true, "days" => implode(",", $days), "from" => substr($f, 0, 5), "to" => substr($t, 0, 5)];
}
function t_get_worker_profile_suggestions($a) {
    global $u;
    $r = getworker($u["id"]); if (!$r) return err("Profile not found.");
    $c = wcard($r, 1, wstats($r["id"]));
    $missing = []; foreach (["category", "location", "experience", "photo", "rate", "hours"] as $k) if (empty($c[$k])) $missing[] = $k; if (!$c["verified"]) $missing[] = "verification";
    return ["missing" => $missing, "_card" => ["type" => "stats", "title" => "Profile strength", "rows" => [["Rating", $c["rating"] ?? "New"], ["Jobs done", $c["jobs_done"]], ["Verified", $c["verified"] ? "Yes" : "No"], ["Missing", $missing ? implode(", ", $missing) : "Nothing 🎉"]]]];
}
function run($fn, $args = []) { try { $r = ("t_" . $fn)($args); } catch (Throwable $e) { error_log("tool $fn: " . $e->getMessage()); $r = err("That action failed."); } $c = $r["_card"] ?? null; unset($r["_card"]); return [$r, $c]; }

/* ================= request handling ================= */
if ($_SERVER["REQUEST_METHOD"] !== "POST") out(err("POST only."), 405);
$in = json_decode(file_get_contents("php://input"), true) ?: [];
if (!hash_equals((string) ($_SESSION["ai_csrf"] ?? ""), (string) ($_SERVER["HTTP_X_CSRF"] ?? "x"))) out(err("Session expired. Please refresh the page."), 403);
$now = time();
$_SESSION["ai_rl"] = array_values(array_filter($_SESSION["ai_rl"] ?? [], fn($t) => $t > $now - 60));
if (count($_SESSION["ai_rl"]) >= $RATE) out(err("You're sending messages too fast. Please wait a moment."), 429);
$_SESSION["ai_rl"][] = $now;
$action = $in["action"] ?? "chat";
$L = ["en" => "en", "kn" => "kn", "hi" => "hi"][$in["lang"] ?? "en"] ?? "en";

if ($action == "reset") { unset($_SESSION["ai_flow"], $_SESSION["ai_last"], $_SESSION["ai_ctx"]); out(["ok" => true]); }

if ($action == "confirm_booking") {
    $tok = (string) ($in["token"] ?? ""); $p = $_SESSION["ai_pending"][$tok] ?? null;
    if ($role != "customer" || !$p || $p["uid"] != $u["id"] || $p["exp"] < time()) out(err("This confirmation expired. Please ask me to prepare the booking again."), 400);
    unset($_SESSION["ai_pending"][$tok]);
    try {
        if (!getworker($p["worker_id"])) out(err("This worker is no longer available."), 409);
        $pdo->beginTransaction();
        if (clash($pdo, $p["worker_id"], $p["slot"])) { $pdo->rollBack(); out(err("Sorry, that time was just taken. Please choose another time."), 409); }
        $pdo->prepare("INSERT INTO jobs(customer_id,worker_id,title,category,location,amount,details,phone,slot,status,price,paid,confirmed,tip,urgent,refunded,escrow,commission,discount,settled,rem1,rem24,onway,photo,created) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([$u["id"], $p["worker_id"], $p["title"], $p["category"], $p["location"], "", $p["details"], $u["phone"] ?? "", $p["slot"], "requested", 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, "", date("Y-m-d H:i:s")]);
        $jid = 0; try { $jid = (int) $pdo->lastInsertId(); } catch (Throwable $e) {}
        if (!$jid) { $f = $pdo->prepare("SELECT id FROM jobs WHERE customer_id=? AND worker_id=? ORDER BY id DESC LIMIT 1"); $f->execute([$u["id"], $p["worker_id"]]); $jid = (int) $f->fetchColumn(); }
        $pdo->commit();
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); error_log("ai booking: " . $e->getMessage()); out(err("The booking could not be saved. Nothing was created."), 500); }
    if (!$jid) out(err("The booking could not be saved. Nothing was created."), 500);
    notify($p["worker_id"], 'New booking request "' . $p["title"] . '"', "job.php?id=$jid");
    logit("AI booking job #$jid");
    unset($_SESSION["ai_flow"]);
    $done = ["en" => "🎉 Done! Request **#$jid** was sent to " . $p["worker"] . ". You'll be notified when they accept.",
             "kn" => "🎉 ಮುಗಿಯಿತು! ವಿನಂತಿ **#$jid** ಅನ್ನು " . $p["worker"] . " ಅವರಿಗೆ ಕಳುಹಿಸಲಾಗಿದೆ. ಅವರು ಒಪ್ಪಿದಾಗ ನಿಮಗೆ ತಿಳಿಸಲಾಗುತ್ತದೆ.",
             "hi" => "🎉 हो गया! अनुरोध **#$jid** " . $p["worker"] . " को भेज दिया गया है। स्वीकार होने पर आपको सूचना मिलेगी।"][$L];
    out(["cards" => [["type" => "done", "job_id" => $jid, "title" => $p["title"], "worker" => $p["worker"], "slot" => $p["slot"]]], "reply" => $done, "chips" => ["Show my bookings", "Find a verified painter"]]);
}

/* ================= offline brain ================= */
$msg = trim(mb_substr((string) ($in["message"] ?? ""), 0, 600));
if ($msg === "") out(err("Empty message."), 400);
$m = mb_strtolower($msg);

function has($m, $words) { foreach ($words as $w) if (mb_strpos($m, $w) !== false) return true; return false; }

/* predefined replies: English / ಕನ್ನಡ / हिन्दी. Add your own entries here. */
function aitx($k, $a = []) {
    global $L;
    static $S = null;
    if ($S === null) $S = [
        "hello" => ["en" => "Hi %s! 👋 I can find trusted workers, estimate prices and manage your bookings. What do you need?",
                    "kn" => "ನಮಸ್ಕಾರ %s! 👋 ನಾನು ವಿಶ್ವಾಸಾರ್ಹ ಕೆಲಸಗಾರರನ್ನು ಹುಡುಕಲು, ಬೆಲೆ ಅಂದಾಜಿಸಲು ಮತ್ತು ಬುಕಿಂಗ್ ನಿರ್ವಹಿಸಲು ಸಹಾಯ ಮಾಡುತ್ತೇನೆ. ನಿಮಗೇನು ಬೇಕು?",
                    "hi" => "नमस्ते %s! 👋 मैं भरोसेमंद वर्कर ढूँढने, कीमत का अंदाज़ा लगाने और बुकिंग संभालने में मदद करता हूँ। आपको क्या चाहिए?"],
        "ask_cat" => ["en" => "Which service do you need? Pick one or type it.", "kn" => "ಯಾವ ಸೇವೆ ಬೇಕು? ಒಂದನ್ನು ಆರಿಸಿ ಅಥವಾ ಟೈಪ್ ಮಾಡಿ.", "hi" => "आपको कौन सी सेवा चाहिए? एक चुनें या लिखें।"],
        "found" => ["en" => "Top **%d** %s options, ranked by rating and jobs done ⭐", "kn" => "ರೇಟಿಂಗ್ ಮತ್ತು ಪೂರ್ಣಗೊಂಡ ಕೆಲಸಗಳ ಆಧಾರದ ಮೇಲೆ ಅತ್ಯುತ್ತಮ **%d** %s ಆಯ್ಕೆಗಳು ⭐", "hi" => "रेटिंग और पूरे किए गए काम के आधार पर शीर्ष **%d** %s विकल्प ⭐"],
        "none" => ["en" => "I couldn't find any %s workers right now. Try another area or service.", "kn" => "ಈಗ %s ಕೆಲಸಗಾರರು ಸಿಗಲಿಲ್ಲ. ಬೇರೆ ಪ್ರದೇಶ ಅಥವಾ ಸೇವೆ ಪ್ರಯತ್ನಿಸಿ.", "hi" => "अभी कोई %s वर्कर नहीं मिला। कोई दूसरा क्षेत्र या सेवा आज़माएँ।"],
        "ask_slot" => ["en" => "Great choice! **When** should **%s** come? Pick a time or type a date and time (e.g. *tomorrow 5 pm*).", "kn" => "ಒಳ್ಳೆಯ ಆಯ್ಕೆ! **%s** ಯಾವಾಗ ಬರಬೇಕು? ಸಮಯ ಆರಿಸಿ ಅಥವಾ ದಿನಾಂಕ ಮತ್ತು ಸಮಯ ಟೈಪ್ ಮಾಡಿ (ಉದಾ: *tomorrow 5 pm*).", "hi" => "बढ़िया चुनाव! **%s** कब आएँ? समय चुनें या तारीख और समय लिखें (जैसे *tomorrow 5 pm*)।"],
        "bad_slot" => ["en" => "I couldn't read that time. Try *tomorrow 10 am* or *2026-11-25 16:00*.", "kn" => "ಆ ಸಮಯ ಅರ್ಥವಾಗಲಿಲ್ಲ. *tomorrow 10 am* ಅಥವಾ *2026-11-25 16:00* ಎಂದು ಪ್ರಯತ್ನಿಸಿ.", "hi" => "वह समय समझ नहीं आया। *tomorrow 10 am* या *2026-11-25 16:00* जैसा लिखें।"],
        "ask_title" => ["en" => "Got it: **%s**. What work do you need done? (a few words, e.g. *fan not working*)", "kn" => "ಸರಿ: **%s**. ಯಾವ ಕೆಲಸ ಬೇಕು? (ಕೆಲವು ಪದಗಳಲ್ಲಿ, ಉದಾ: *fan not working*)", "hi" => "ठीक है: **%s**। कौन सा काम करवाना है? (कुछ शब्दों में, जैसे *fan not working*)"],
        "review" => ["en" => "Almost done! Review the details and press **Confirm booking** 👇", "kn" => "ಬಹುತೇಕ ಮುಗಿಯಿತು! ವಿವರ ಪರಿಶೀಲಿಸಿ **Confirm booking** ಒತ್ತಿ 👇", "hi" => "लगभग हो गया! विवरण जाँचें और **Confirm booking** दबाएँ 👇"],
        "cancelled" => ["en" => "No problem, I've stopped that. Anything else?", "kn" => "ಸರಿ, ನಿಲ್ಲಿಸಿದ್ದೇನೆ. ಇನ್ನೇನಾದರೂ?", "hi" => "कोई बात नहीं, रोक दिया। और कुछ?"],
        "pick_first" => ["en" => "Let's find workers first — which service do you need?", "kn" => "ಮೊದಲು ಕೆಲಸಗಾರರನ್ನು ಹುಡುಕೋಣ — ಯಾವ ಸೇವೆ ಬೇಕು?", "hi" => "पहले वर्कर ढूँढते हैं — कौन सी सेवा चाहिए?"],
        "unknown" => ["en" => "I'm not sure I got that 🤔 Here's what I can do:", "kn" => "ನನಗೆ ಅರ್ಥವಾಗಲಿಲ್ಲ 🤔 ನಾನು ಇವುಗಳಲ್ಲಿ ಸಹಾಯ ಮಾಡಬಲ್ಲೆ:", "hi" => "मुझे समझ नहीं आया 🤔 मैं इनमें मदद कर सकता हूँ:"],
        "bye" => ["en" => "You're welcome! 😊 I'm here whenever you need me.", "kn" => "ಸ್ವಾಗತ! 😊 ಬೇಕಾದಾಗ ನಾನು ಇಲ್ಲಿದ್ದೇನೆ.", "hi" => "आपका स्वागत है! 😊 जब ज़रूरत हो, मैं यहाँ हूँ।"],
        "how" => ["en" => "**WorkOnnect** connects you with verified local workers in 3 steps:\n1️⃣ Search a service\n2️⃣ Compare rating, price and availability\n3️⃣ Book, track and pay on the job page.",
                  "kn" => "**WorkOnnect** ನಿಮ್ಮನ್ನು ಪರಿಶೀಲಿತ ಸ್ಥಳೀಯ ಕೆಲಸಗಾರರೊಂದಿಗೆ 3 ಹಂತಗಳಲ್ಲಿ ಸಂಪರ್ಕಿಸುತ್ತದೆ:\n1️⃣ ಸೇವೆಯನ್ನು ಹುಡುಕಿ\n2️⃣ ರೇಟಿಂಗ್, ಬೆಲೆ ಮತ್ತು ಲಭ್ಯತೆ ಹೋಲಿಸಿ\n3️⃣ ಬುಕ್ ಮಾಡಿ, ಟ್ರ್ಯಾಕ್ ಮಾಡಿ ಮತ್ತು ಜಾಬ್ ಪುಟದಲ್ಲಿ ಪಾವತಿಸಿ.",
                  "hi" => "**WorkOnnect** आपको सत्यापित स्थानीय वर्कर से 3 चरणों में जोड़ता है:\n1️⃣ सेवा खोजें\n2️⃣ रेटिंग, कीमत और उपलब्धता की तुलना करें\n3️⃣ बुक करें, ट्रैक करें और जॉब पेज पर भुगतान करें।"],
        "pay" => ["en" => "💳 Payment happens on the job page after the work is done. The price is agreed with the worker. I never ask for OTPs or card details — please don't share them with anyone.",
                  "kn" => "💳 ಕೆಲಸ ಮುಗಿದ ನಂತರ ಜಾಬ್ ಪುಟದಲ್ಲಿ ಪಾವತಿ ಮಾಡಲಾಗುತ್ತದೆ. ಬೆಲೆಯನ್ನು ಕೆಲಸಗಾರರೊಂದಿಗೆ ಒಪ್ಪಿಕೊಳ್ಳಲಾಗುತ್ತದೆ. ನಾನು ಎಂದಿಗೂ OTP ಅಥವಾ ಕಾರ್ಡ್ ವಿವರ ಕೇಳುವುದಿಲ್ಲ — ಯಾರೊಂದಿಗೂ ಹಂಚಿಕೊಳ್ಳಬೇಡಿ.",
                  "hi" => "💳 काम पूरा होने के बाद भुगतान जॉब पेज पर होता है। कीमत वर्कर के साथ तय होती है। मैं कभी OTP या कार्ड की जानकारी नहीं माँगता — इसे किसी के साथ साझा न करें।"],
        "safe" => ["en" => "🛡️ Workers with the **verified** badge have passed our ID checks. Ratings and jobs done are shown on every profile before you book.",
                   "kn" => "🛡️ **ಪರಿಶೀಲಿತ** ಬ್ಯಾಡ್ಜ್ ಇರುವ ಕೆಲಸಗಾರರು ನಮ್ಮ ಗುರುತಿನ ಪರಿಶೀಲನೆ ಪಾಸಾಗಿದ್ದಾರೆ. ಬುಕ್ ಮಾಡುವ ಮೊದಲು ಪ್ರತಿ ಪ್ರೊಫೈಲ್‌ನಲ್ಲಿ ರೇಟಿಂಗ್ ಮತ್ತು ಪೂರ್ಣಗೊಂಡ ಕೆಲಸಗಳನ್ನು ನೋಡಬಹುದು.",
                   "hi" => "🛡️ **वेरिफाइड** बैज वाले वर्कर हमारी ID जाँच पास कर चुके हैं। बुक करने से पहले हर प्रोफ़ाइल पर रेटिंग और पूरे किए गए काम देखें।"],
        "policy" => ["en" => "↩️ Cancelling is free until a worker accepts. After that a small fee applies: 5% (min ₹20), or 20% (min ₹50) if the visit is within 24 hours or the worker is on the way. Say *cancel booking #12* to see the exact fee.",
                     "kn" => "↩️ ಕೆಲಸಗಾರರು ಒಪ್ಪುವವರೆಗೆ ರದ್ದು ಮಾಡುವುದು ಉಚಿತ. ನಂತರ ಸಣ್ಣ ಶುಲ್ಕ: 5% (ಕನಿಷ್ಠ ₹20), ಭೇಟಿ 24 ಗಂಟೆಯೊಳಗಿದ್ದರೆ ಅಥವಾ ಕೆಲಸಗಾರರು ದಾರಿಯಲ್ಲಿದ್ದರೆ 20% (ಕನಿಷ್ಠ ₹50). ನಿಖರ ಶುಲ್ಕಕ್ಕೆ *cancel booking #12* ಎಂದು ಬರೆಯಿರಿ.",
                     "hi" => "↩️ वर्कर के स्वीकार करने तक रद्द करना मुफ़्त है। उसके बाद 5% (न्यूनतम ₹20) शुल्क लगता है, या 24 घंटे के भीतर / वर्कर के रास्ते में होने पर 20% (न्यूनतम ₹50)। सटीक शुल्क के लिए *cancel booking #12* लिखें।"],
        "wallet" => ["en" => "👛 Your wallet is on the **Wallet** page. Referral bonus: when a friend you referred pays for their first job, you both get ₹50.",
                     "kn" => "👛 ನಿಮ್ಮ ವಾಲೆಟ್ **Wallet** ಪುಟದಲ್ಲಿದೆ. ರೆಫರಲ್ ಬೋನಸ್: ನೀವು ಆಹ್ವಾನಿಸಿದ ಸ್ನೇಹಿತ ಮೊದಲ ಕೆಲಸಕ್ಕೆ ಪಾವತಿಸಿದಾಗ ನೀವಿಬ್ಬರೂ ₹50 ಪಡೆಯುತ್ತೀರಿ.",
                     "hi" => "👛 आपका वॉलेट **Wallet** पेज पर है। रेफ़रल बोनस: जब आपका बुलाया दोस्त अपने पहले जॉब का भुगतान करता है, तो आप दोनों को ₹50 मिलते हैं।"],
        "dev" => ["en" => "👨‍💻 WorkOnnect was built with care by our team of four. Thank you for asking! Please meet the developers:",
                  "kn" => "👨‍💻 WorkOnnect ಅನ್ನು ನಮ್ಮ ನಾಲ್ವರ ತಂಡ ಪ್ರೀತಿಯಿಂದ ಅಭಿವೃದ್ಧಿಪಡಿಸಿದೆ. ಕೇಳಿದ್ದಕ್ಕೆ ಧನ್ಯವಾದಗಳು! ದಯವಿಟ್ಟು ನಮ್ಮ ಡೆವಲಪರ್‌ಗಳನ್ನು ಭೇಟಿಯಾಗಿ:",
                  "hi" => "👨‍💻 WorkOnnect को हमारी चार सदस्यों की टीम ने लगन से बनाया है। पूछने के लिए धन्यवाद! कृपया हमारे डेवलपर्स से मिलिए:"],
        "clock" => ["en" => "🕒 Here's the current time and date for you:", "kn" => "🕒 ಪ್ರಸ್ತುತ ಸಮಯ ಮತ್ತು ದಿನಾಂಕ ಇಲ್ಲಿದೆ:", "hi" => "🕒 यह रहा वर्तमान समय और तारीख:"],
    ];
    $s = $S[$k][$L] ?? $S[$k]["en"] ?? $k;
    return $a ? vsprintf($s, $a) : $s;
}

/* service detection: your site's categories first, then synonyms (EN / ಕನ್ನಡ / हिन्दी) */
function detect_cat($m) {
    global $CATS;
    if (isset($CATS) && is_array($CATS)) foreach ($CATS as $c) if ($c !== "" && mb_stripos($m, mb_strtolower((string) $c)) !== false) return (string) $c;
    $SYN = [
        [["Electrician"], '/electric|wiring|\bswitch|\bfan\b|inverter|ಎಲೆಕ್ಟ್ರಿ|ವಿದ್ಯುತ್|इलेक्ट्री|बिजली/iu'],
        [["Plumber"], '/plumb|\bpipe|\btap\b|leak|bathroom|\btank\b|ಪ್ಲಂಬ|ನಲ್ಲಿ|प्लंबर|नल|पाइप/iu'],
        [["Painter"], '/paint|wall colou?r|ಪೇಂಟ|ಬಣ್ಣ|पेंट|रंग/iu'],
        [["Carpenter"], '/carpent|furniture|\bwood|\bdoor\b|ಬಡಗಿ|ಕಾರ್ಪೆಂಟ|बढ़ई|कारपेंटर|लकड़ी/iu'],
        [["Cleaner", "Cleaning"], '/clean|\bmaid\b|sweep|ಕ್ಲೀನ್|ಸ್ವಚ್ಛ|सफाई|क्लीन/iu'],
        [["AC Technician", "AC"], '/\bac\b|a\/c|air.?condition|cooler|ಎಸಿ|एसी/iu'],
        [["Mechanic"], '/mechanic|\bbike\b|car repair|ಮೆಕ್ಯಾನಿಕ್|मैकेनिक/iu'],
        [["Gardener"], '/garden|\blawn\b|ತೋಟ|माली|बगीचा/iu'],
        [["Cook"], '/\bcook|\bchef\b|ಅಡುಗೆ|रसोई|कुक/iu'],
        [["Driver"], '/driver|ಡ್ರೈವರ್|ड्राइवर/iu'],
    ];
    foreach ($SYN as [$names, $rx]) if (preg_match($rx, $m)) {
        if (isset($CATS) && is_array($CATS)) foreach ($CATS as $c) foreach ($names as $n) if (mb_stripos((string) $c, $n) !== false) return (string) $c;
        return $names[0];
    }
    return null;
}
function parse_slot($m) {
    if (!preg_match('/\d{4}-\d{2}-\d{2}|today|tomorrow|\d{1,2}(:\d{2})?\s*(am|pm)|\d{1,2}:\d{2}|ಇಂದು|ನಾಳೆ|आज|कल|\b(mon|tue|wed|thu|fri|sat|sun)/iu', $m)) return null;
    $m = str_ireplace(["ಇಂದು", "ನಾಳೆ", "आज", "कल"], ["today", "tomorrow", "today", "tomorrow"], $m);
    $m = preg_replace('/\b(at|on|for|by|around|please|book)\b/i', " ", $m);
    $ts = strtotime($m);
    if (!$ts) return null;
    if ($ts < time() + 1800 && !preg_match('/\d{4}-\d{2}-\d{2}/', $m)) $ts += 86400;
    return $ts >= time() + 1800 ? date("Y-m-d H:i", $ts) : null;
}
function h24($h, $ap) { $h = (int) $h; if ($ap == "pm" && $h < 12) $h += 12; if ($ap == "am" && $h == 12) $h = 0; return $h; }

function cat_chips() { return ["Electrician", "Plumber", "Painter", "Carpenter", "Cleaner", "AC Technician"]; }
function slot_chips() { return ["Today 6 PM", "Tomorrow 10 AM", "Tomorrow 5 PM", "Not now"]; }
function menu_chips($role) {
    return $role == "worker" ? ["Show my new job requests", "How much did I earn this month?", "How am I performing?", "Improve my profile", "Set availability Mon-Sat 9am to 6pm"]
        : ($role == "customer" ? ["Find an electrician near me", "Estimate a plumbing job", "Show my bookings", "Find a verified painter"] : ["How does WorkOnnect work?", "How do payments work?", "Is it safe?"]);
}
function reply($text, $cards = [], $chips = []) { out(["reply" => $text, "cards" => array_values(array_filter((array) $cards)), "chips" => $chips]); }

$first = explode(" ", trim((string) $u["name"]))[0];
$flow = $_SESSION["ai_flow"] ?? null;
$ctx = $_SESSION["ai_ctx"] ?? [];
$cancelWords = ["not now", "cancel this", "never mind", "start over", "ಬೇಡ", "रहने दो", "छोड़ो"];

/* 1. an active booking flow always gets the message first (so a job title can't trigger an FAQ by accident) */
if ($flow && $role == "customer") {
    if (has($m, $cancelWords)) { unset($_SESSION["ai_flow"]); reply(aitx("cancelled"), [], menu_chips("customer")); }
    if (($flow["step"] ?? "") == "slot") {
        $s = parse_slot($m);
        if ($s) {
            $flow["slot"] = $s; $flow["step"] = "title"; $_SESSION["ai_flow"] = $flow;
            reply(aitx("ask_title", [date("D, d M · h:i A", strtotime($s))]), [], [($flow["category"] ?: "General") . " service", "Repair work", "Installation", "Not now"]);
        }
        if (!detect_cat($m) && !has($m, ["find", "show", "booking", "status", "help", "how "])) reply(aitx("bad_slot"), [], slot_chips());
        unset($_SESSION["ai_flow"]); $flow = null;           // the person changed topic
    } elseif (($flow["step"] ?? "") == "title") {
        [$r, $c] = run("prepare_booking", ["worker_id" => $flow["wid"], "title" => mb_substr($msg, 0, 80), "details" => $msg, "category" => $flow["category"] ?: null, "slot" => $flow["slot"], "location" => $ctx["location"] ?? null]);
        if (isset($r["error"])) {
            $e = (string) $r["error"];
            if (strpos($e, "slot:") === 0) { $flow["step"] = "slot"; $_SESSION["ai_flow"] = $flow; reply(substr($e, 5), [], slot_chips()); }
            reply(preg_replace('/^title:/', "", $e), [], ["Not now"]);
        }
        $flow["step"] = "confirm"; $_SESSION["ai_flow"] = $flow;
        reply(aitx("review"), [$c], []);
    }
}

/* 2. small talk + FAQ (all roles) */
if (preg_match('/^(hi|hello|hey|namaste|namaskara|good (morning|afternoon|evening))\b|^(ನಮಸ್ಕಾರ|ಹಲೋ|नमस्ते|हाय)/iu', $m)) reply(aitx("hello", [$first]), [], menu_chips($role));
if (preg_match('/^(thanks|thank you|thx|ok thanks|ಧನ್ಯವಾದ|शुक्रिया|धन्यवाद)/iu', $m)) reply(aitx("bye"), [], menu_chips($role));
/* time & date: the card shows the live time from the person's own device */
if (preg_match('/\b(time now|current time|current date|what(\'s| is)? the (time|date|day)|what time|what date|what day|today\'?s date|date today|day today|tell me the (time|date)|time please|date please)\b|ಸಮಯ ಎಷ್ಟು|ಈಗ ಸಮಯ|ಇಂದಿನ ದಿನಾಂಕ|ಇಂದು ಯಾವ ದಿನ|ದಿನಾಂಕ ಎಷ್ಟು|कितने बजे|समय क्या|आज की तारीख|आज कौन सा दिन|तारीख क्या/iu', $m)) reply(aitx("clock"), [["type" => "clock"]], menu_chips($role));
/* developers: show photos + names (put photos in the /team folder: mithun.jpg, pavan.jpg, sachin.jpg, yashwanth.jpg) */
if (has($m, ["developer", "developed", "who made", "who built", "who created", "who develop", "created this", "made this", "your team", "our team", "about team", "ಡೆವಲಪರ್", "ಅಭಿವೃದ್ಧಿ", "डेवलपर", "किसने बनाया", "किसने बनाई"])) {
    $items = [];
    foreach ([["Mithun K C", "mithun"], ["Pavan S K", "pavan"], ["Sachin M", "sachin"], ["Yashwanth P", "yashwanth"]] as $d) {
        $ph = null;
        foreach (["jpg", "jpeg", "png", "webp"] as $x) if (is_file(__DIR__ . "/team/" . $d[1] . "." . $x)) { $ph = "team/" . $d[1] . "." . $x; break; }
        $items[] = ["name" => $d[0], "photo" => $ph];
    }
    reply(aitx("dev"), [["type" => "team", "items" => $items]], menu_chips($role));
}
if (has($m, ["how does", "how it works", "what is workonnect", "how do i use", "ಹೇಗೆ ಕೆಲಸ", "कैसे काम"])) reply(aitx("how"), [], menu_chips($role));
if (has($m, ["cancellation", "cancel policy", "cancel fee", "ರದ್ದು ಶುಲ್ಕ", "रद्द शुल्क"]) || ($role == "customer" && has($m, ["cancel"]) && !preg_match('/\d/', $m))) reply(aitx("policy"), [], ["Show my bookings"]);
if (has($m, ["wallet", "referral", "refer ", "ವಾಲೆಟ್", "वॉलेट", "रेफ़रल"])) reply(aitx("wallet"), [], menu_chips($role));
if (has($m, ["payment", "refund", "commission", "upi", "how to pay", "how do i pay", "ಪಾವತಿ", "भुगतान", "पेमेंट"])) reply(aitx("pay"), [], menu_chips($role));
if (has($m, ["safe", "trust", "verified mean", "secure", "ಸುರಕ್ಷ", "सुरक्षित"])) reply(aitx("safe"), [], menu_chips($role));
if (has($m, ["help", "what can you do", "menu", "ಸಹಾಯ", "मदद"])) reply(aitx("unknown"), [], menu_chips($role));

/* ================= ADMIN ================= */
if ($role == "admin") reply(aitx("unknown"), [], menu_chips("admin"));

/* ================= WORKER ================= */
if ($role == "worker") {
    if (has($m, ["availab", "working hours", "work days", "working days", "schedule", "ಲಭ್ಯ", "उपलब्ध"])) {
        $days = [];
        $map = ["mon" => "Mon", "tue" => "Tue", "wed" => "Wed", "thu" => "Thu", "fri" => "Fri", "sat" => "Sat", "sun" => "Sun"];
        $all = array_values($map);
        if (preg_match('/\b(mon|tue|wed|thu|fri|sat|sun)[a-z]*\s*(?:-|to|through)\s*(mon|tue|wed|thu|fri|sat|sun)/i', $m, $x)) {
            $a = array_search($map[strtolower($x[1])], $all); $b = array_search($map[strtolower($x[2])], $all);
            for ($i = $a; ; $i = ($i + 1) % 7) { $days[] = $all[$i]; if ($i == $b) break; }
        } elseif (has($m, ["weekday"])) $days = ["Mon", "Tue", "Wed", "Thu", "Fri"];
        elseif (has($m, ["every day", "daily", "all days"])) $days = $all;
        else foreach ($map as $k => $v) if (preg_match('/\b' . $k . '/i', $m)) $days[] = $v;
        $f = $t = null;
        if (preg_match('/(\d{1,2})(?::(\d{2}))?\s*(am|pm)?\s*(?:to|-|till|until)\s*(\d{1,2})(?::(\d{2}))?\s*(am|pm)?/i', $m, $x)) {
            $a1 = strtolower($x[3] ?? ""); $a2 = strtolower($x[6] ?? "");
            if (!$a1 && $a2) $a1 = ($a2 == "pm" && (int) $x[1] > (int) $x[4]) ? "am" : $a2;
            $f = sprintf("%02d:%02d", h24($x[1], $a1), (int) ($x[2] ?: 0)); $t = sprintf("%02d:%02d", h24($x[4], $a2), (int) ($x[5] ?: 0));
        }
        if (!$days || !$f) reply("Tell me your working **days and hours**, for example: *Mon-Sat 9am to 6pm* ⏰", [], ["Mon-Sat 9am to 6pm", "Weekdays 10am to 7pm", "Every day 8am to 8pm"]);
        [$r] = run("update_worker_availability", ["days" => implode(",", $days), "from" => $f, "to" => $t]);
        if (isset($r["error"])) reply("The end time must be later than the start time. Try again, e.g. *Mon-Sat 9am to 6pm*.", [], ["Mon-Sat 9am to 6pm"]);
        reply("✅ Availability updated: **" . $r["days"] . "**, " . $r["from"] . " – " . $r["to"] . ". Customers can only book you in these hours.", [], menu_chips("worker"));
    }
    if (has($m, ["earn", "income", "money", "salary", "paid", "payout", "ಗಳಿಕೆ", "ಆದಾಯ", "कमाई", "आमदनी"])) {
        $mo = has($m, ["this month", "month", "ಈ ತಿಂಗಳು", "इस महीने"]) ? date("Y-m") : null;
        [$r, $c] = run("get_worker_earnings", ["month" => $mo]);
        reply($mo ? "💰 Your income for **this month**:" : "💰 Your all-time income:", [$c], ["Earnings all time", "How am I performing?", "Show my new job requests"]);
    }
    if (has($m, ["perform", "rating", "review", "how am i doing", "ರೇಟಿಂಗ್", "रेटिंग", "प्रदर्शन"])) {
        [$r, $c] = run("get_worker_performance");
        reply("📈 Your month at a glance. " . (($r["rating"] ?? 0) >= 4.5 ? "Excellent rating — keep it up! 🌟" : "Quick replies and punctuality are the fastest ways to raise your rating."), [$c], ["Improve my profile", "How much did I earn this month?"]);
    }
    if (has($m, ["profile", "improve", "verify", "verification", "photo", "ಪ್ರೊಫೈಲ್", "प्रोफ़ाइल", "प्रोफाइल"])) {
        [$r, $c] = run("get_worker_profile_suggestions");
        if (isset($r["error"])) reply($r["error"]);
        $tips = ["category" => "choose your service category", "location" => "add your service area", "experience" => "add your years of experience", "photo" => "upload a clear profile photo", "rate" => "set your rate", "hours" => "set working hours", "verification" => "complete verification for the blue badge"];
        $lines = []; foreach ($r["missing"] as $k) $lines[] = "• " . ucfirst($tips[$k] ?? $k);
        reply($lines ? "Complete these to get more bookings:\n" . implode("\n", $lines) : "Your profile looks complete 🎉 Keep your availability up to date and reply quickly to requests.", [$c], ["Set availability Mon-Sat 9am to 6pm", "Show my new job requests"]);
    }
    if (has($m, ["job", "request", "booking", "work", "order", "ಕೆಲಸ", "काम", "बुकिंग"])) {
        $st = has($m, ["new", "pending", "request"]) ? "requested" : (has($m, ["complete", "done", "finished"]) ? "completed" : (has($m, ["progress", "ongoing", "upcoming"]) ? "in_progress" : null));
        [$r, $c] = run("get_worker_jobs", ["status" => $st]);
        reply($r["n"] ? "📋 Your " . ($st ? str_replace("_", " ", $st) . " " : "") . "jobs. Tap a status to open the job:" : "No jobs here yet. Keep your availability on and profile complete to receive requests ✨", [$c], ["Show my new job requests", "Completed jobs", "How much did I earn this month?"]);
    }
    reply(aitx("unknown"), [], menu_chips("worker"));
}

/* ================= CUSTOMER ================= */
/* cancel / status of a numbered booking */
if (has($m, ["cancel"]) && preg_match('/#?(\d{1,9})/', $m, $x)) {
    [$r, $c] = run("prepare_cancel", ["job_id" => (int) $x[1]]);
    if (isset($r["error"])) reply($r["error"], [], ["Show my bookings"]);
    reply("Review the cancellation below. " . ($r["fee"] ? "⚠️ A fee of **₹{$r["fee"]}** applies." : "✅ No cancellation fee applies."), [$c], ["Show my bookings"]);
}
if (has($m, ["status", "track", "where is"]) && preg_match('/#?(\d{1,9})/', $m, $x)) {
    [$r, $c] = run("get_booking_status", ["job_id" => (int) $x[1]]);
    if (isset($r["error"])) reply($r["error"], [], ["Show my bookings"]);
    reply("Latest on booking **#" . $x[1] . "** 📦", [$c], ["Show my bookings", "Cancel booking #" . $x[1]]);
}

/* start booking: "book worker #2" */
if (has($m, ["book", "hire", "ಬುಕ್", "बुक"]) && preg_match('/(?:worker\s*#?|#|book\s+|no\.?\s*)(\d)\b/i', $m, $x)) {
    $idx = (int) $x[1]; $id = (int) ($_SESSION["ai_last"][$idx - 1] ?? 0); $w = $id ? getworker($id) : null;
    if (!$w) reply(aitx("pick_first"), [], cat_chips());
    $_SESSION["ai_flow"] = ["step" => "slot", "wid" => $id, "category" => (string) (col($w, ["category", "skill", "service"]) ?? ($ctx["category"] ?? ""))];
    reply(aitx("ask_slot", [$w["name"]]), [], slot_chips());
}

/* my bookings */
if (has($m, ["my booking", "bookings", "my order", "my job", "my request", "ಬುಕಿಂಗ್", "बुकिंग", "मेरी बुक"])) {
    [$r, $c] = run("get_my_bookings");
    reply($r["n"] ? "📅 Your latest bookings. Tap a status to open it:" : "You have no bookings yet. Let's find someone! 🔍", [$c], $r["n"] ? ["Find an electrician near me", "Estimate a plumbing job"] : cat_chips());
}

/* price estimate */
$cat = detect_cat($m);
if (has($m, ["price", "cost", "estimate", "how much", "rate", "charge", "fee", "ಬೆಲೆ", "ಎಷ್ಟು", "कीमत", "भाव", "खर्च", "कितना"])) {
    $cat = $cat ?: ($ctx["category"] ?? null);
    if (!$cat) reply(aitx("ask_cat"), [], cat_chips());
    [$r, $c] = run("estimate_service_price", ["category" => $cat]);
    $ctx["category"] = $cat; $_SESSION["ai_ctx"] = $ctx;
    if (!$c) reply("I don't have enough paid **$cat** jobs yet for a reliable estimate 🙏 The best way is to find a worker and agree on the price directly.", [], ["Find " . $cat, "Show my bookings"]);
    reply("💡 Typical price range for **$cat**, from real WorkOnnect jobs:", [$c], ["Find " . $cat, "Find a verified " . $cat]);
}

/* find workers */
$wantsFind = has($m, ["find", "search", "show", "need", "want", "looking", "near me", "nearby", "available", "worker", "verified", "best", "top", "ಬೇಕು", "ಹುಡುಕ", "चाहिए", "ढूँढ", "खोज"]);
if ($cat || ($wantsFind && !has($m, ["booking"]))) {
    $verified = has($m, ["verified", "trusted", "ಪರಿಶೀಲಿತ", "वेरिफाइड", "सत्यापित"]);
    $cat = $cat ?: ($ctx["category"] ?? null);
    if (!$cat) reply(aitx("ask_cat"), [], cat_chips());
    $loc = null;
    if (preg_match('/\b(?:in|near|around)\s+([a-z][a-z .]{2,24}?)(?:\s+(?:for|with|today|tomorrow|please|who|that)\b|[?.!,]|$)/i', $msg, $x) && !preg_match('/^(me|my|here|the|a)\b/i', trim($x[1]))) $loc = trim($x[1]);
    $slot = parse_slot($m);
    [$r, $c] = run("search_workers", ["category" => $cat, "location" => $loc, "verified_only" => $verified, "slot" => $slot]);
    $ctx = ["category" => $cat, "location" => $loc]; $_SESSION["ai_ctx"] = $ctx;
    if (!$r["count"]) reply(aitx("none", [$cat]), [], array_merge(["Find " . $cat . " (any area)"], array_slice(cat_chips(), 0, 3)));
    $chips = []; foreach ($r["workers"] as $w) { if (count($chips) < 3) $chips[] = "Book worker #" . $w["index"] . " (" . explode(" ", $w["name"])[0] . ")"; }
    if (!$verified) $chips[] = "Only verified " . $cat;
    $chips[] = "Estimate price for " . $cat;
    reply(aitx("found", [$r["count"], $cat]) . ($loc ? " 📍 $loc" : "") . ($verified ? " ✔ verified only" : ""), [$c], $chips);
}

reply(aitx("unknown"), [], menu_chips("customer"));
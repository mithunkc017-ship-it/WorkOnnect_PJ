<?php // Notification bar data (JSON): latest 6 notifications + unread count. A POST marks everything as read.
require "config.php";
header("Content-Type: application/json");
$u = me();
if (!$u) {
    echo json_encode(["unread" => 0, "items" => []]);
    exit();
}
if (time() - ($_SESSION["rem"] ?? 0) > 60) {
    $_SESSION["rem"] = time();
    sendReminders($pdo);
} // booking reminders (checked at most once a minute per user)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $pdo->prepare("UPDATE notifications SET seen=1 WHERE user_id=?")->execute([
        $u["id"],
    ]);
}
$s = $pdo->prepare(
    "SELECT id,msg,link,seen,created FROM notifications WHERE user_id=? ORDER BY id DESC LIMIT 6",
);
$s->execute([$u["id"]]);
$n = $pdo->prepare(
    "SELECT COUNT(*) FROM notifications WHERE user_id=? AND seen=0",
);
$n->execute([$u["id"]]);
$items = $s->fetchAll();
foreach ($items as &$it) {
    $p = notif_parts($it["msg"]);
    $it["icon"] = $p["icon"];
    $it["text"] = $p["text"];
    $it["ago"] = time_ago($it["created"]);
    $it["link"] = $it["link"] ?: "notifications.php";
}
unset($it);
echo json_encode(
    ["unread" => (int) $n->fetchColumn(), "items" => $items],
    JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE,
);
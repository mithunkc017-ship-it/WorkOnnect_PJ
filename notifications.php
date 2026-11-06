<?php // List of notifications for the user
require "config.php";
need();
$u = me();
$s = $pdo->prepare(
    "SELECT * FROM notifications WHERE user_id=? ORDER BY id DESC LIMIT 50",
);
$s->execute([$u["id"]]);
$rows = $s->fetchAll();
$pdo->prepare("UPDATE notifications SET seen=1 WHERE user_id=?")->execute([
    $u["id"],
]);
head("Notifications");
echo '<h2><span class="material-icons mi">notifications</span> Notifications</h2>';
foreach ($rows as $n) {
    echo '<div class="card" style="margin:8px 0;' .
        ($n["seen"] ? "" : "border-left:4px solid #FF9800") .
        '"><a href="' .
        e($n["link"]) .
        '">' .
        e($n["msg"]) .
        "</a><br><small>" .
        $n["created"] .
        "</small></div>";
}
if (!$rows) {
    echo "<p>No notifications yet.</p>";
}
foot();

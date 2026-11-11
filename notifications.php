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
echo '<style>
.nt-wrap{max-width:720px;margin:0 auto}
.nt-item{display:flex;gap:14px;align-items:flex-start;padding:14px 16px;margin:10px 0;border-radius:14px;background:#fff;box-shadow:0 1px 4px rgba(0,0,0,.08);text-decoration:none;color:inherit;transition:transform .15s,box-shadow .15s}
.nt-item:hover{transform:translateY(-2px);box-shadow:0 6px 18px rgba(0,0,0,.12)}
.nt-item.new{border-left:4px solid #2f5bff;background:#f3f6ff}
.nt-ic{flex:none;width:42px;height:42px;border-radius:50%;background:#e8efff;color:#2f5bff;display:flex;align-items:center;justify-content:center}
.nt-ic .material-icons{color:#2f5bff}
.nt-tx{display:block;font-size:15px;line-height:1.4}
.nt-tm{display:block;margin-top:3px;color:#6b7280;font-size:12px}
.nt-empty{text-align:center;padding:50px 10px;color:#6b7280}
.nt-empty .material-icons{font-size:56px;opacity:.4}
body.dark .nt-item{background:#1e2330;color:#e5e7eb}
body.dark .nt-item.new{background:#26304a}
</style><div class="nt-wrap"><h2><span class="material-icons mi">notifications</span> Notifications</h2>';
foreach ($rows as $n) {
    $p = notif_parts($n["msg"]);
    $when = strtotime((string) $n["created"]);
    echo '<a class="nt-item' .
        ($n["seen"] ? "" : " new") .
        '" href="' .
        e($n["link"] ?: "dashboard.php") .
        '"><span class="nt-ic"><span class="material-icons mi">' .
        e($p["icon"]) .
        '</span></span><span><span class="nt-tx">' .
        e($p["text"]) .
        '</span><small class="nt-tm">' .
        e(time_ago($n["created"])) .
        ($when ? " · " . e(date("d M Y, h:i A", $when)) : "") .
        "</small></span></a>";
}
if (!$rows) {
    echo '<div class="nt-empty"><span class="material-icons mi">notifications_off</span><p>No notifications yet.</p></div>';
}
echo "</div>";
foot();
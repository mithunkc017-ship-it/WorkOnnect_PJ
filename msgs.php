<?php // Returns chat messages (called every 4 seconds by job.php)
require "config.php";
need();
$u = me();
$id = (int) ($_GET["id"] ?? 0); // returns chat messages as HTML (polled by job.php)
$q = $pdo->prepare("SELECT customer_id,worker_id FROM jobs WHERE id=?");
$q->execute([$id]);
$j = $q->fetch();
if (!$j || ($j["customer_id"] != $u["id"] && $j["worker_id"] != $u["id"])) {
    die("");
}
$ms = $pdo->prepare(
    "SELECT m.*,u.name FROM messages m JOIN users u ON u.id=m.sender_id WHERE job_id=? ORDER BY m.id",
);
$ms->execute([$id]);
foreach ($ms as $m) {
    echo '<p style="margin:6px 0"><b>' .
        e($m["name"]) .
        ":</b> " .
        e($m["msg"]) .
        ($m["photo"]
            ? '<br><img class="p" style="max-height:140px" src="' .
                e($m["photo"]) .
                '">'
            : "") .
        " <small>" .
        $m["created"] .
        "</small></p>";
}

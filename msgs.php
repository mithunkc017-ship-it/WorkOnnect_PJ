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

$rows = $ms->fetchAll();
if (!$rows) {
    echo '<div style="text-align:center;color:#94a3b8;padding:40px 10px;font-family:system-ui,sans-serif">
            <div style="font-size:34px;margin-bottom:6px">💬</div>
            <div style="font-size:14px">No messages yet. Say hello to get started!</div>
          </div>';
    return;
}

$lastDay = "";
foreach ($rows as $m) {
    $mine = $m["sender_id"] == $u["id"];
    $ts = strtotime($m["created"]);
    $day = $ts ? date("Y-m-d", $ts) : "";

    // Day divider (Today / Yesterday / date)
    if ($day && $day !== $lastDay) {
        $lastDay = $day;
        $label =
            $day === date("Y-m-d")
                ? "Today"
                : ($day === date("Y-m-d", strtotime("-1 day"))
                    ? "Yesterday"
                    : date("d M Y", $ts));
        echo '<div style="text-align:center;margin:14px 0 8px">
                <span style="background:#e2e8f0;color:#64748b;font:600 11px system-ui,sans-serif;padding:4px 12px;border-radius:20px">' .
            $label .
            "</span></div>";
    }

    $time = $ts ? date("h:i A", $ts) : e($m["created"]);
    $initial = e(mb_strtoupper(mb_substr($m["name"], 0, 1)));

    $bubble = $mine
        ? "background:linear-gradient(135deg,#2563eb,#1d4ed8);color:#fff;border-radius:16px 16px 4px 16px"
        : "background:#fff;color:#0f172a;border:1px solid #e2e8f0;border-radius:16px 16px 16px 4px";
    $avatar = $mine ? "#2563eb" : "#10b981";

    echo '<div style="display:flex;gap:8px;margin:8px 0;align-items:flex-end;' .
        ($mine ? "flex-direction:row-reverse;" : "") .
        'font-family:system-ui,-apple-system,Segoe UI,sans-serif">';

    // Avatar
    echo '<div style="flex:none;width:32px;height:32px;border-radius:50%;background:' .
        $avatar .
        ';color:#fff;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700">' .
        $initial .
        "</div>";

    // Message bubble
    echo '<div style="max-width:75%;padding:9px 13px;box-shadow:0 1px 2px rgba(0,0,0,.08);' .
        $bubble .
        '">';
    echo '<div style="font-size:12px;font-weight:700;margin-bottom:2px;opacity:.85">' .
        e($m["name"]) .
        "</div>";

    if ($m["msg"] !== "" && $m["msg"] !== null) {
        echo '<div style="font-size:14px;line-height:1.45;word-wrap:break-word;white-space:pre-wrap">' .
            e($m["msg"]) .
            "</div>";
    }

    if ($m["photo"]) {
        echo '<img class="p" style="max-height:140px;max-width:100%;border-radius:10px;margin-top:6px;display:block;object-fit:cover" src="' .
            e($m["photo"]) .
            '">';
    }

    echo '<div style="font-size:10px;margin-top:4px;text-align:right;opacity:.65">' .
        $time .
        "</div>";
    echo "</div></div>";
}
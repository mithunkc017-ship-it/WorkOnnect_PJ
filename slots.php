<?php // Free time slots of one worker for one day (JSON), used by the booking picker on hire.php
require "config.php";
need();
header("Content-Type: application/json");
$w = (int) ($_GET["w"] ?? 0);
$d = $_GET["d"] ?? "";
$s = $pdo->prepare(
    "SELECT work_days,work_from,work_to FROM users WHERE id=? AND role='worker'",
);
$s->execute([$w]);
$x = $s->fetch();
if (
    !$x ||
    !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) ||
    $d < date("Y-m-d") ||
    $d > date("Y-m-d", strtotime("+30 days"))
) {
    echo json_encode([
        "slots" => [],
        "note" => "Pick a date within the next 30 days.",
    ]);
    exit();
}
if (
    $x["work_days"] &&
    strpos($x["work_days"], date("D", strtotime($d))) === false
) {
    echo json_encode([
        "slots" => [],
        "note" =>
            "The worker does not work on this day (" . $x["work_days"] . ").",
    ]);
    exit();
}
$from = (int) substr($x["work_from"] ?: "09:00", 0, 2);
$to = (int) substr($x["work_to"] ?: "18:00", 0, 2);
$b = $pdo->prepare(
    "SELECT slot FROM jobs WHERE worker_id=? AND status IN('requested','assigned','in_progress') AND slot IS NOT NULL AND DATE(slot)=?",
);
$b->execute([$w, $d]);
$busy = array_map("strtotime", $b->fetchAll(MongoPDO::FETCH_COLUMN));
$out = [];
for ($h = $from; $h < $to; $h++) {
    $t = sprintf("%02d:00", $h);
    $ts = strtotime("$d $t");
    $free = $ts > time() + 3600;
    foreach ($busy as $bt) {
        if (abs($bt - $ts) < 7200) {
            $free = false;
        }
    }
    $out[] = ["t" => $t, "free" => $free];
}
echo json_encode([
    "slots" => $out,
    "note" => sprintf("Working hours %02d:00–%02d:00", $from, $to),
]);

<?php // Worker schedule: upcoming jobs in time order, with double-booking warnings (two jobs less than 2 hours apart)
require "config.php";
need("worker");
$u = me();
$s = $pdo->prepare(
    "SELECT j.*,c.name cn FROM jobs j JOIN users c ON c.id=j.customer_id WHERE j.worker_id=? AND j.status IN('requested','assigned','in_progress') ORDER BY j.slot IS NULL,j.slot",
);
$s->execute([$u["id"]]);
head("My Schedule");
echo d_top("schedule.php");
?><h2><span class="material-icons mi">calendar_today</span> My Schedule</h2><table><tr><th>When</th><th>Job</th><th>Customer</th><th>Status</th><th></th></tr>
<?php
$prev = null;
foreach ($s as $j) {
    $w = "";
    if ($j["slot"] && $prev && abs(strtotime($j["slot"]) - $prev) < 7200) {
        $w =
            ' <b class="err"><span class="material-icons mi-xs">warning</span> Clash with the previous job</b>';
    }
    if ($j["slot"]) {
        $prev = strtotime($j["slot"]);
    }
    echo "<tr><td>" .
        ($j["slot"]
            ? e(date("d M, h:i A", strtotime($j["slot"])))
            : "Not fixed") .
        $w .
        '</td><td><a href="job.php?id=' .
        $j["id"] .
        '">' .
        e($j["title"]) .
        "</a></td><td>" .
        e($j["cn"]) .
        "</td><td>" .
        e($j["status"]) .
        '</td><td><span class="material-icons mi">place</span> ' .
        e($j["location"]) .
        "</td></tr>";
}
?></table><?php foot();

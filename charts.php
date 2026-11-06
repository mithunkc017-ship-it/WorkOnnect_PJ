<?php // Admin charts: jobs and income for the last 6 months, job status breakdown (plain HTML/CSS bars)
require "config.php";
need("admin");
$m = array_reverse(
    $pdo
        ->query(
            "SELECT DATE_FORMAT(MIN(created),'%b %y') l,COUNT(*) n,SUM(CASE WHEN paid=1 THEN price ELSE 0 END) inc FROM jobs GROUP BY YEAR(created),MONTH(created) ORDER BY YEAR(created) DESC,MONTH(created) DESC LIMIT 6",
        )
        ->fetchAll(),
);
$st = $pdo
    ->query("SELECT status l,COUNT(*) n FROM jobs GROUP BY status")
    ->fetchAll();
function chart($rows, $k, $col, $pre = "")
{
    $mx = max(1, max(array_column($rows, $k) ?: [1]));
    echo '<div class="card"><div style="display:flex;align-items:flex-end;gap:14px;height:180px">';
    foreach ($rows as $r) {
        echo '<div style="flex:1;text-align:center"><small>' .
            $pre .
            (int) $r[$k] .
            '</small><div class="bar" style="height:' .
            max(3, round(($r[$k] / $mx) * 140)) .
            "px;background:" .
            $col .
            '"></div><small>' .
            e($r["l"]) .
            "</small></div>";
    }
    echo "</div></div>";
}
head("Charts");
?><h2><span class="material-icons mi">trending_up</span> Jobs per month</h2><?php chart(
    $m,
    "n",
    "#1a6b7c",
); ?><h2><span class="material-icons mi">payments</span> Income per month</h2><?php chart(
    $m,
    "inc",
    "#FF9800",
    "₹",
); ?><h2><span class="material-icons mi">assignment</span> Jobs by status</h2><?php
chart($st, "n", "#7B5CFF");
foot();


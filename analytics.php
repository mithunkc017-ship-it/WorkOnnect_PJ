<?php // Admin analytics: conversion KPIs, 12-month revenue, busiest hours and weekdays, revenue by category, payment methods, top areas
require "config.php";
require_once "dash_lib.php";
need("admin");
$u = me();
$PAL = [
    "#2f5bff",
    "#22c55e",
    "#f59e0b",
    "#a855f7",
    "#ef4444",
    "#06b6d4",
    "#94a3b8",
];
$M = d_months(12);
$mk = array_keys($M);
$ml = array_values($M);
$jobs = (int) d_n($pdo, "SELECT COUNT(*) FROM jobs");
$paid = (int) d_n(
    $pdo,
    "SELECT COUNT(*) FROM jobs WHERE paid=1 AND refunded=0",
);
$avg = d_n(
    $pdo,
    "SELECT COALESCE(AVG(price),0) FROM jobs WHERE paid=1 AND refunded=0",
);
$cust = (int) d_n($pdo, "SELECT COUNT(DISTINCT customer_id) FROM jobs");
$rep = (int) d_n(
    $pdo,
    "SELECT COUNT(*) FROM (SELECT customer_id FROM jobs GROUP BY customer_id HAVING COUNT(*)>=2) t",
);
$ttf = d_n(
    $pdo,
    "SELECT AVG(m) FROM (SELECT TIMESTAMPDIFF(MINUTE,j.created,MIN(o.created)) m FROM jobs j JOIN offers o ON o.job_id=j.id GROUP BY j.id HAVING m BETWEEN 0 AND 4320) t",
);
$rev = [];
foreach (
    $pdo
        ->query(
            "SELECT DATE_FORMAT(COALESCE(paid_at,created),'%Y-%m') m,SUM(commission) com,SUM(price) col FROM jobs WHERE paid=1 AND refunded=0 GROUP BY m",
        )
        ->fetchAll()
    as $r
) {
    $rev[$r["m"]] = $r;
}
$hrs = array_fill(0, 24, 0);
foreach (
    $pdo
        ->query("SELECT HOUR(created) h,COUNT(*) n FROM jobs GROUP BY h")
        ->fetchAll()
    as $r
) {
    $hrs[(int) $r["h"]] = (int) $r["n"];
}
$wd = array_fill(1, 7, 0);
foreach (
    $pdo
        ->query("SELECT DAYOFWEEK(created) d,COUNT(*) n FROM jobs GROUP BY d")
        ->fetchAll()
    as $r
) {
    $wd[(int) $r["d"]] = (int) $r["n"];
}
$cr = [];
foreach (
    $pdo
        ->query(
            "SELECT category,COALESCE(SUM(commission),0) c,COUNT(*) n FROM jobs WHERE paid=1 AND refunded=0 GROUP BY category ORDER BY c DESC LIMIT 6",
        )
        ->fetchAll()
    as $r
) {
    $cr[] = [
        e($r["category"]),
        (float) $r["c"],
        d_money($r["c"]) . " · " . $r["n"] . " jobs",
    ];
}
$pm = [];
foreach (
    $pdo
        ->query(
            "SELECT pay_method,COUNT(*) n FROM jobs WHERE paid=1 AND pay_method IS NOT NULL GROUP BY pay_method ORDER BY n DESC LIMIT 6",
        )
        ->fetchAll()
    as $i => $r
) {
    $pm[] = [$r["pay_method"], (int) $r["n"], $PAL[$i]];
}
$ar = [];
foreach ($pdo->query("SELECT location FROM jobs")->fetchAll() as $r) {
    $l = strtolower(trim($r["location"]));
    if (preg_match("/^-?\d+(\.\d+)?\s*,\s*-?\d+/", $l)) {
        $l = "gps pin";
    }
    $l = substr($l, 0, 25);
    if ($l !== "") {
        $ar[$l] = ($ar[$l] ?? 0) + 1;
    }
}
arsort($ar);
$ab = [];
foreach (array_slice($ar, 0, 6, true) as $l => $n) {
    $ab[] = [e($l), $n];
}
$s1 = [
    [
        "name" => "Platform fee",
        "color" => "#2f5bff",
        "v" => d_series($rev, $mk, "com"),
    ],
    [
        "name" => "Collected",
        "color" => "#22c55e",
        "v" => d_series($rev, $mk, "col"),
    ],
];
head("Analytics");
echo d_top("analytics.php");
echo '<div class="pd">';
echo '<div class="pd-head"><div><h1>Analytics</h1><p>How jobs turn into revenue.</p></div></div>';
echo '<div class="pd-g4">' .
    d_kpi(
        "Posted → paid",
        $jobs ? round(($paid / $jobs) * 100) . "%" : "—",
        $paid . " of " . $jobs . " jobs",
        '<span class="material-icons mi">gps_fixed</span>',
        true,
    ) .
    d_kpi(
        "Average job value",
        d_money($avg),
        "Paid jobs",
        '<span class="material-icons mi">payments</span>',
    ) .
    d_kpi(
        "Repeat customers",
        $cust ? round(($rep / $cust) * 100) . "%" : "—",
        $rep . " of " . $cust . " booked 2+ times",
        '<span class="material-icons mi-xs">refresh</span>',
    ) .
    d_kpi(
        "First offer in",
        $ttf !== null && $ttf !== false && $ttf !== 0
            ? (round($ttf) < 60
                ? round($ttf) . " min"
                : round($ttf / 60, 1) . " h")
            : "—",
        "Average time to the first offer",
        '<span class="material-icons mi">bolt</span>',
    ) .
    "</div>";
echo '<div class="pd-card" style="margin-bottom:16px"><div class="pd-ct">Revenue – last 12 months</div>' .
    d_legend($s1) .
    d_line($ml, $s1, 1000, 260, true) .
    "</div>";
echo '<div class="pd-g2"><div class="pd-card"><div class="pd-ct">Busiest hours (jobs posted)</div>' .
    d_cols(array_map("strval", array_keys($hrs)), array_values($hrs)) .
    '</div><div class="pd-card"><div class="pd-ct">Busiest weekdays</div>' .
    d_cols(
        ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"],
        array_values($wd),
        "#22c55e",
    ) .
    "</div></div>";
echo '<div class="pd-g3"><div class="pd-card"><div class="pd-ct">Platform fee by category</div>' .
    ($cr ? d_bars($cr) : '<p class="pd-mute">No paid jobs yet.</p>') .
    '</div><div class="pd-card"><div class="pd-ct">Payment methods</div>' .
    d_donut($pm, (string) array_sum(array_column($pm, 1)), "payments") .
    '</div><div class="pd-card"><div class="pd-ct">Top areas</div>' .
    ($ab ? d_bars($ab, "#a855f7") : '<p class="pd-mute">No jobs yet.</p>') .
    "</div></div>";
echo "</div>";
foot();

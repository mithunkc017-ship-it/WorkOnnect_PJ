<?php // Helpers for the pro dashboard: tab bar, KPI cards, charts drawn as inline SVG (no libraries), avatars, status pills
function d_top($active)
{
    $u = me();
    if (!$u) {
        return "";
    }
    $T =
        [
            "admin" => [
                ["dash.php", "Overview"],
                ["dashboard.php", "Manage"],
                ["analytics.php", "Analytics"],
                ["qr_payments.php", "Payments"],
                ["settlements.php", "Settlements"],
            ],
            "worker" => [
                ["dash.php", "Overview"],
                ["open_jobs.php", "Open jobs"],
                ["dashboard.php", "My jobs"],
                ["earnings.php", "Earnings"],
                ["schedule.php", "Schedule"],
            ],
            "customer" => [
                ["dash.php", "Overview"],
                ["dashboard.php", "My jobs"],
                ["workers.php", "Find workers"],
                ["match.php", "Smart match"],
                ["wallet.php", "Wallet"],
            ],
        ][$u["role"]] ?? [];
    $o =
        '<link rel="stylesheet" href="dash.css"><div class="pd"><div class="pd-tabs">';
    foreach ($T as $t) {
        $o .=
            '<a href="' .
            $t[0] .
            '"' .
            ($t[0] == $active ? ' class="on"' : "") .
            ">" .
            e($t[1]) .
            "</a>";
    }
    return $o . "</div></div>";
}
function d_money($v)
{
    return "₹" . number_format((float) $v);
}
function d_short($v)
{
    $v = (float) $v;
    if ($v >= 100000) {
        return round($v / 100000, 1) . "L";
    }
    if ($v >= 1000) {
        return round($v / 1000, 1) . "k";
    }
    return (string) round($v);
}
function d_pct($now, $prev)
{
    return $prev > 0 ? (($now - $prev) / $prev) * 100 : ($now > 0 ? 100 : null);
}
function d_n($pdo, $sql, $args = [])
{
    try {
        $s = $pdo->prepare($sql);
        $s->execute($args);
        return $s->fetchColumn();
    } catch (Exception $x) {
        return 0;
    }
}
function d_months($k = 6)
{
    $m = [];
    for ($i = $k - 1; $i >= 0; $i--) {
        $t = strtotime("-$i month", strtotime(date("Y-m-01")));
        $m[date("Y-m", $t)] = date("M", $t);
    }
    return $m;
}
function d_series($map, $keys, $col)
{
    $o = [];
    foreach ($keys as $k) {
        $o[] = (float) ($map[$k][$col] ?? 0);
    }
    return $o;
}
function d_av($name, $s = 30)
{
    $c = [
        "#2f5bff",
        "#22c55e",
        "#f59e0b",
        "#a855f7",
        "#ef4444",
        "#06b6d4",
        "#ec4899",
    ];
    $i = abs(crc32((string) $name)) % count($c);
    $p = preg_split("/\s+/", trim((string) $name));
    $f = function ($t) {
        return function_exists("mb_substr")
            ? mb_substr($t, 0, 1)
            : substr($t, 0, 1);
    };
    $ini = strtoupper($f($p[0] ?? "?") . (isset($p[1]) ? $f($p[1]) : ""));
    return '<span class="pd-av" style="width:' .
        $s .
        "px;height:" .
        $s .
        "px;background:" .
        $c[$i] .
        '">' .
        e($ini) .
        "</span>";
}
function d_pill($st)
{
    $m = [
        "open" => "blue",
        "requested" => "amber",
        "assigned" => "violet",
        "in_progress" => "amber",
        "completed" => "green",
        "cancelled" => "red",
    ];
    return '<span class="pd-pill ' .
        ($m[$st] ?? "gray") .
        '">' .
        e(ucfirst(str_replace("_", " ", (string) $st))) .
        "</span>";
}
function d_kpi(
    $label,
    $value,
    $sub = "",
    $icon = "",
    $hero = false,
    $delta = null,
) {
    $d = "";
    if ($delta !== null) {
        $d =
            '<span class="pd-delta ' .
            ($delta >= 0 ? "up" : "down") .
            '">' .
            ($delta >= 0
                ? '<span class="material-icons mi-xs">arrow_upward</span> +'
                : '<span class="material-icons mi-xs">arrow_downward</span> ') .
            round($delta, 1) .
            "%</span>";
    }
    return '<div class="pd-card pd-kpi' .
        ($hero ? " hero" : "") .
        '"><div class="pd-kt"><span class="pd-ic">' .
        $icon .
        "</span>" .
        $d .
        '</div><div class="pd-kv">' .
        $value .
        '</div><div class="pd-kl">' .
        e($label) .
        '</div><div class="pd-ks">' .
        $sub .
        "</div></div>";
}
function d_line($labels, $series, $w = 600, $h = 230, $money = false)
{
    static $n = 0;
    $n++; // smooth multi-series line chart (inline SVG)
    $pl = 46;
    $pr = 14;
    $pt = 14;
    $pb = 28;
    $iw = $w - $pl - $pr;
    $ih = $h - $pt - $pb;
    $c = count($labels);
    if (!$c) {
        return "";
    }
    $max = 1;
    foreach ($series as $s) {
        foreach ($s["v"] as $v) {
            $max = max($max, (float) $v);
        }
    }
    $mag = pow(10, floor(log10($max)));
    $max = (ceil(($max / $mag) * 2) / 2) * $mag;
    $x = function ($i) use ($c, $pl, $iw) {
        return $c > 1 ? $pl + ($iw * $i) / ($c - 1) : $pl + $iw / 2;
    };
    $y = function ($v) use ($pt, $ih, $max) {
        return $pt + $ih - ($v / $max) * $ih;
    };
    $base = $pt + $ih;
    $o = '<svg viewBox="0 0 ' . $w . " " . $h . '" class="pd-line" role="img">';
    for ($g = 0; $g <= 4; $g++) {
        $gy = $pt + ($ih * $g) / 4;
        $o .=
            '<line x1="' .
            $pl .
            '" x2="' .
            ($w - $pr) .
            '" y1="' .
            round($gy, 1) .
            '" y2="' .
            round($gy, 1) .
            '" class="grid"/><text x="' .
            ($pl - 8) .
            '" y="' .
            round($gy + 4, 1) .
            '" class="ax" text-anchor="end">' .
            ($money ? "₹" : "") .
            d_short(($max * (4 - $g)) / 4) .
            "</text>";
    }
    foreach ($labels as $i => $l) {
        $o .=
            '<text x="' .
            round($x($i), 1) .
            '" y="' .
            ($h - 8) .
            '" class="ax" text-anchor="middle">' .
            e($l) .
            "</text>";
    }
    foreach ($series as $k => $s) {
        $pts = [];
        foreach ($s["v"] as $i => $v) {
            $pts[] = [$x($i), $y((float) $v)];
        }
        $cnt = count($pts);
        $d = "M" . round($pts[0][0], 1) . "," . round($pts[0][1], 1);
        for ($i = 0; $i < $cnt - 1; $i++) {
            $p0 = $pts[max(0, $i - 1)];
            $p1 = $pts[$i];
            $p2 = $pts[$i + 1];
            $p3 = $pts[min($cnt - 1, $i + 2)];
            $c1y = max($pt, min($base, $p1[1] + ($p2[1] - $p0[1]) / 6));
            $c2y = max($pt, min($base, $p2[1] - ($p3[1] - $p1[1]) / 6));
            $d .=
                " C" .
                round($p1[0] + ($p2[0] - $p0[0]) / 6, 1) .
                "," .
                round($c1y, 1) .
                " " .
                round($p2[0] - ($p3[0] - $p1[0]) / 6, 1) .
                "," .
                round($c2y, 1) .
                " " .
                round($p2[0], 1) .
                "," .
                round($p2[1], 1);
        }
        $col = $s["color"];
        $gid = "lg" . $n . "_" . $k;
        if ($k == 0 && $cnt > 1) {
            $o .=
                '<defs><linearGradient id="' .
                $gid .
                '" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="' .
                $col .
                '" stop-opacity=".25"/><stop offset="1" stop-color="' .
                $col .
                '" stop-opacity="0"/></linearGradient></defs><path d="' .
                $d .
                " L" .
                round($pts[$cnt - 1][0], 1) .
                "," .
                $base .
                " L" .
                round($pts[0][0], 1) .
                "," .
                $base .
                ' Z" fill="url(#' .
                $gid .
                ')" class="area"/>';
        }
        $o .=
            '<path d="' .
            $d .
            '" fill="none" stroke="' .
            $col .
            '" stroke-width="2.6" stroke-linecap="round" class="ln" pathLength="1"/>';
        foreach ($pts as $i => $p) {
            $o .=
                '<circle cx="' .
                round($p[0], 1) .
                '" cy="' .
                round($p[1], 1) .
                '" r="4" fill="' .
                $col .
                '" class="dot"><title>' .
                e(
                    $s["name"] .
                        " · " .
                        $labels[$i] .
                        ": " .
                        ($money ? "₹" : "") .
                        number_format((float) $s["v"][$i]),
                ) .
                "</title></circle>";
        }
    }
    return $o . "</svg>";
}
function d_legend($series)
{
    $o = '<div class="pd-key">';
    foreach ($series as $s) {
        $o .=
            '<span><i style="background:' .
            $s["color"] .
            '"></i>' .
            e($s["name"]) .
            "</span>";
    }
    return $o . "</div>";
}
function d_donut($items, $center = "", $sub = "")
{
    // items: [label,value,color]
    $tot = 0;
    foreach ($items as $it) {
        $tot += $it[1];
    }
    if ($tot <= 0) {
        return '<p class="pd-mute">No data yet.</p>';
    }
    $cf = 2 * M_PI * 42;
    $off = 0;
    $o =
        '<div class="pd-dn"><svg viewBox="0 0 120 120" class="pd-donut"><circle cx="60" cy="60" r="42" fill="none" class="trk" stroke-width="14"/>';
    foreach ($items as $it) {
        if ($it[1] <= 0) {
            continue;
        }
        $len = ($cf * $it[1]) / $tot;
        $o .=
            '<circle cx="60" cy="60" r="42" fill="none" stroke="' .
            $it[2] .
            '" stroke-width="14" stroke-dasharray="' .
            round($len, 2) .
            " " .
            round($cf - $len, 2) .
            '" stroke-dashoffset="' .
            round(-$off, 2) .
            '" transform="rotate(-90 60 60)" class="seg"><title>' .
            e($it[0]) .
            ": " .
            $it[1] .
            "</title></circle>";
        $off += $len;
    }
    $o .=
        '<text x="60" y="60" text-anchor="middle" class="dc">' .
        e($center) .
        '</text><text x="60" y="76" text-anchor="middle" class="ds">' .
        e($sub) .
        '</text></svg><ul class="pd-lg">';
    foreach ($items as $it) {
        if ($it[1] > 0) {
            $o .=
                '<li><i style="background:' .
                $it[2] .
                '"></i><span>' .
                e($it[0]) .
                "</span><b>" .
                round(($it[1] / $tot) * 100) .
                "%</b></li>";
        }
    }
    return $o . "</ul></div>";
}
function d_bars($rows, $color = "#2f5bff")
{
    // rows: [label (HTML), value, right text (HTML, optional)]
    $mx = 1;
    foreach ($rows as $r) {
        $mx = max($mx, $r[1]);
    }
    $o = '<div class="pd-bars">';
    foreach ($rows as $r) {
        $o .=
            '<div class="pd-br"><div class="t"><span>' .
            $r[0] .
            "</span><b>" .
            ($r[2] ?? e($r[1])) .
            '</b></div><div class="b"><i style="width:' .
            max(2, round(($r[1] / $mx) * 100)) .
            "%;background:" .
            $color .
            '"></i></div></div>';
    }
    return $o . "</div>";
}
function d_cols($labels, $vals, $color = "#2f5bff")
{
    $mx = max(1, max($vals ?: [1]));
    $o = '<div class="pd-cols">';
    foreach ($vals as $i => $v) {
        $o .=
            '<div class="c" title="' .
            e($labels[$i] . ": " . $v) .
            '"><i style="height:' .
            max(3, round(($v / $mx) * 100)) .
            "%;background:" .
            $color .
            '"></i><small>' .
            e($labels[$i]) .
            "</small></div>";
    }
    return $o . "</div>";
}

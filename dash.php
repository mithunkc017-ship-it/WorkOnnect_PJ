<?php // Pro dashboard (overview): KPI cards, charts, tables. Different content for admin / worker / customer. The old list pages are under "Manage" / "My jobs" (dashboard.php).
require "config.php";
need();
$u = me();
expireJobs($pdo);
$M = d_months();
$mk = array_keys($M);
$ml = array_values($M);
$cur = date("Y-m");
$prev = date("Y-m", strtotime("-1 month", strtotime(date("Y-m-01"))));
$hr = (int) date("G");
$hi =
    $hr < 12 ? "Good morning" : ($hr < 17 ? "Good afternoon" : "Good evening");
$PAL = [
    "#2f5bff",
    "#22c55e",
    "#f59e0b",
    "#a855f7",
    "#ef4444",
    "#06b6d4",
    "#94a3b8",
];
function d_head($title, $sub, $btns = "")
{
    echo '<div class="pd-head"><div><h1>' .
        e($title) .
        "</h1><p>" .
        e($sub) .
        '</p></div><div class="pd-btns">' .
        $btns .
        "</div></div>";
}
function d_jobs_table($rows, $who = "cn")
{
    $o =
        '<table class="pd-tb"><tr><th>Job</th><th>' .
        ($who == "cn" ? "Customer" : "Worker") .
        "</th><th>Status</th><th>Amount</th><th>Date</th></tr>";
    foreach ($rows as $r) {
        $nm = $r[$who] ?? "—";
        $o .=
            '<tr><td><a href="job.php?id=' .
            $r["id"] .
            '">' .
            e($r["title"]) .
            '</a><br><small class="pd-mute">' .
            e($r["category"]) .
            '</small></td><td><span class="pd-who">' .
            d_av($nm, 28) .
            e($nm) .
            "</span></td><td>" .
            d_pill($r["status"]) .
            "</td><td>" .
            ($r["price"] ? d_money($r["price"]) : "—") .
            "</td><td><small>" .
            e(date("d M", strtotime($r["created"]))) .
            "</small></td></tr>";
    }
    return $o .
        (!$rows
            ? '<tr><td colspan="5" class="pd-mute">Nothing here yet.</td></tr>'
            : "") .
        "</table>";
}
head("Dashboard");
echo d_top("dash.php");
echo '<div class="pd">';
if ($u["role"] == "admin") {
    $T = $pdo
        ->query(
            "SELECT COUNT(*) jobs,COALESCE(SUM(status='completed'),0) done,COALESCE(SUM(CASE WHEN paid=1 AND refunded=0 THEN commission END),0) com,COALESCE(SUM(CASE WHEN paid=1 AND refunded=0 THEN price END),0) col FROM jobs",
        )
        ->fetch();
    $U = $pdo
        ->query(
            "SELECT COALESCE(SUM(role='worker'),0) w,COALESCE(SUM(role='worker' AND verified=1),0) vw,COALESCE(SUM(role='customer'),0) c FROM users",
        )
        ->fetch();
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
    $pj = [];
    foreach (
        $pdo
            ->query(
                "SELECT DATE_FORMAT(created,'%Y-%m') m,COUNT(*) n,COALESCE(SUM(status='completed'),0) d FROM jobs GROUP BY m",
            )
            ->fetchAll()
        as $r
    ) {
        $pj[$r["m"]] = $r;
    }
    d_head(
        $hi . ", " . $u["name"] . " ",
        "Here is how the platform is doing.",
        '<a class="pd-b dark" href="settlements.php"><span class="material-icons mi">attach_money</span> Settlements</a><a class="pd-b" href="analytics.php"><span class="material-icons mi">trending_up</span> Analytics</a><a class="pd-b" href="dashboard.php">Manage</a>',
    );
    echo '<div class="pd-g4">' .
        d_kpi(
            "Platform revenue (10% fee)",
            d_money($T["com"]),
            "Collected " . d_money($T["col"]),
            '<span class="material-icons mi">payments</span>',
            true,
            d_pct($rev[$cur]["com"] ?? 0, $rev[$prev]["com"] ?? 0),
        ) .
        d_kpi(
            "Jobs",
            (int) $T["jobs"],
            "Completed " .
                ($T["jobs"] ? round(($T["done"] / $T["jobs"]) * 100) : 0) .
                "%",
            '<span class="material-icons mi">assignment</span>',
        ) .
        d_kpi(
            "Workers",
            (int) $U["w"],
            "Verified " . (int) $U["vw"],
            '<span class="material-icons mi">engineering</span>',
        ) .
        d_kpi(
            "Customers",
            (int) $U["c"],
            "Registered",
            '<span class="material-icons mi">people</span>',
        ) .
        "</div>";
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
    $s2 = [
        [
            "name" => "Posted",
            "color" => "#2f5bff",
            "v" => d_series($pj, $mk, "n"),
        ],
        [
            "name" => "Completed",
            "color" => "#22c55e",
            "v" => d_series($pj, $mk, "d"),
        ],
    ];
    echo '<div class="pd-g2"><div class="pd-card"><div class="pd-ct">Revenue</div>' .
        d_legend($s1) .
        d_line($ml, $s1, 600, 230, true) .
        '</div><div class="pd-card"><div class="pd-ct">Jobs: posted vs completed</div>' .
        d_legend($s2) .
        d_line($ml, $s2) .
        "</div></div>";
    $cat = [];
    foreach (
        $pdo
            ->query(
                "SELECT category,COUNT(*) n FROM jobs GROUP BY category ORDER BY n DESC LIMIT 6",
            )
            ->fetchAll()
        as $i => $r
    ) {
        $cat[] = [$r["category"], (int) $r["n"], $PAL[$i]];
    }
    $fn = [
        ["Posted", (int) $T["jobs"]],
        [
            "Received offers",
            (int) d_n($pdo, "SELECT COUNT(DISTINCT job_id) FROM offers"),
        ],
        [
            "Worker assigned",
            (int) d_n(
                $pdo,
                "SELECT COUNT(*) FROM jobs WHERE worker_id IS NOT NULL",
            ),
        ],
        ["Completed", (int) $T["done"]],
        [
            "Paid",
            (int) d_n(
                $pdo,
                "SELECT COUNT(*) FROM jobs WHERE paid=1 AND refunded=0",
            ),
        ],
    ];
    $fr = [];
    foreach ($fn as $f) {
        $fr[] = [
            e($f[0]),
            $f[1],
            $f[1] .
            " · " .
            ($T["jobs"] ? round(($f[1] / $T["jobs"]) * 100) : 0) .
            "%",
        ];
    }
    $tw = [];
    foreach (
        $pdo
            ->query(
                "SELECT u.id,u.name,u.skill,COUNT(j.id) n,(SELECT ROUND(AVG(stars),1) FROM reviews WHERE worker_id=u.id) r FROM users u JOIN jobs j ON j.worker_id=u.id AND j.status='completed' WHERE u.role='worker' GROUP BY u.id,u.name,u.skill ORDER BY n DESC LIMIT 5",
            )
            ->fetchAll()
        as $r
    ) {
        $tw[] = [
            '<span class="pd-who">' .
            d_av($r["name"], 24) .
            '<a href="worker.php?id=' .
            $r["id"] .
            '">' .
            e($r["name"]) .
            "</a></span>",
            (int) $r["n"],
            $r["n"] .
            " jobs" .
            ($r["r"]
                ? " · " .
                    $r["r"] .
                    ' <span class="material-icons mi-xs">star</span>'
                : ""),
        ];
    }
    echo '<div class="pd-g3"><div class="pd-card"><div class="pd-ct">Jobs by category</div>' .
        d_donut($cat, (string) (int) $T["jobs"], "jobs") .
        '</div><div class="pd-card"><div class="pd-ct">Job funnel</div>' .
        d_bars($fr) .
        '</div><div class="pd-card"><div class="pd-ct">Top workers</div>' .
        ($tw
            ? d_bars($tw, "#22c55e")
            : '<p class="pd-mute">No completed jobs yet.</p>') .
        "</div></div>";
    $A = [
        [
            "disputes.php",
            "Open disputes",
            d_n($pdo, "SELECT COUNT(*) FROM disputes WHERE status='open'"),
        ],
        [
            "qr_payments.php",
            "UPI / QR payments to confirm",
            d_n(
                $pdo,
                "SELECT COUNT(*) FROM qr_payments WHERE status='submitted'",
            ),
        ],
        [
            "verifications.php",
            "Worker ID verifications",
            d_n(
                $pdo,
                "SELECT COUNT(*) FROM verifications WHERE status='pending'",
            ),
        ],
        [
            "certificates.php",
            "Certificates to review",
            d_n(
                $pdo,
                "SELECT COUNT(*) FROM certificates WHERE status='pending'",
            ),
        ],
        [
            "complaints.php",
            "Open complaints",
            d_n($pdo, "SELECT COUNT(*) FROM complaints WHERE status='open'"),
        ],
        [
            "settlements.php",
            "Workers waiting for payment (₹)",
            (int) d_n(
                $pdo,
                "SELECT COALESCE(SUM(j.price-j.commission+j.tip),0) FROM jobs j WHERE j.paid=1 AND j.refunded=0 AND j.disputed=0 AND (j.escrow=0 OR j.confirmed=1) AND j.settled=0",
            ),
        ],
    ];
    $al = "";
    foreach ($A as $a) {
        $al .=
            '<a href="' .
            $a[0] .
            '"><span>' .
            e($a[1]) .
            '</span><b class="' .
            ($a[2] ? "" : "ok") .
            '">' .
            ($a[2]
                ? (int) $a[2]
                : '<span class="material-icons mi-xs">verified</span>') .
            "</b></a>";
    }
    $rj = $pdo
        ->query(
            "SELECT j.*,c.name cn,w.name wn FROM jobs j JOIN users c ON c.id=j.customer_id LEFT JOIN users w ON w.id=j.worker_id ORDER BY j.id DESC LIMIT 8",
        )
        ->fetchAll();
    echo '<div class="pd-g2" style="grid-template-columns:1fr 2fr"><div class="pd-card"><div class="pd-ct">Needs attention</div><div class="pd-list">' .
        $al .
        '</div></div><div class="pd-card"><div class="pd-ct">Recent jobs</div><div style="overflow-x:auto">' .
        d_jobs_table($rj) .
        "</div></div></div>";
} elseif ($u["role"] == "worker") {
    $id = $u["id"];
    $cond =
        "worker_id=? AND paid=1 AND refunded=0 AND disputed=0 AND (escrow=0 OR confirmed=1)";
    $E = d_n(
        $pdo,
        "SELECT COALESCE(SUM(price-commission+tip),0) FROM jobs WHERE $cond",
        [$id],
    );
    $P = d_n(
        $pdo,
        "SELECT COALESCE(SUM(price-commission+tip),0) FROM jobs WHERE $cond AND settled=0",
        [$id],
    );
    $mon = [];
    $s = $pdo->prepare(
        "SELECT DATE_FORMAT(COALESCE(paid_at,created),'%Y-%m') m,SUM(price-commission+tip) e,COUNT(*) n FROM jobs WHERE $cond GROUP BY m",
    );
    $s->execute([$id]);
    foreach ($s->fetchAll() as $r) {
        $mon[$r["m"]] = $r;
    }
    $done = (int) d_n(
        $pdo,
        "SELECT COUNT(*) FROM jobs WHERE worker_id=? AND status='completed'",
        [$id],
    );
    $rt = $pdo->prepare(
        "SELECT ROUND(AVG(stars),1) r,COUNT(*) n FROM reviews WHERE worker_id=?",
    );
    $rt->execute([$id]);
    $rt = $rt->fetch();
    $hr2 = $pdo->prepare(
        "SELECT COUNT(*) sent,COALESCE(SUM(j.worker_id=o.worker_id AND j.status<>'cancelled'),0) won FROM offers o JOIN jobs j ON j.id=o.job_id WHERE o.worker_id=?",
    );
    $hr2->execute([$id]);
    $hr2 = $hr2->fetch();
    $stt = [];
    $s = $pdo->prepare(
        "SELECT status,COUNT(*) n FROM jobs WHERE worker_id=? GROUP BY status",
    );
    $s->execute([$id]);
    foreach ($s->fetchAll() as $i => $r) {
        $stt[] = [
            ucfirst(str_replace("_", " ", $r["status"])),
            (int) $r["n"],
            $PAL[$i % 7],
        ];
    }
    d_head(
        $hi . ", " . $u["name"] . " ",
        "Your work and earnings at a glance.",
        '<a class="pd-b blue" href="open_jobs.php"><span class="material-icons mi">lock_open</span> Find work</a><a class="pd-b" href="my_upi.php"><span class="material-icons mi">credit_card</span> ' .
            ($u["upi_id"] ? "My UPI ID" : "Add UPI ID") .
            '</a><a class="pd-b" href="dashboard.php">My jobs</a>',
    );
    if (!validUpi($u["upi_id"] ?? "")) {
        echo '<div class="pd-card" style="margin-bottom:16px;background:#fef3c7;color:#92400e"><span class="material-icons mi-xs">warning</span> Add your UPI ID so customers and the admin can pay you. <a href="my_upi.php">Add now</a></div>';
    }
    echo '<div class="pd-g4">' .
        d_kpi(
            "Your earnings (after 10% fee)",
            d_money($E),
            "Waiting to be paid: " . d_money($P),
            '<span class="material-icons mi">payments</span>',
            true,
            d_pct(
                (float) ($mon[$cur]["e"] ?? 0),
                (float) ($mon[$prev]["e"] ?? 0),
            ),
        ) .
        d_kpi(
            "Jobs completed",
            $done,
            "All time",
            '<span class="material-icons mi-xs">check_circle</span>',
        ) .
        d_kpi(
            "Rating",
            $rt["n"]
                ? $rt["r"] . ' <span class="material-icons mi-xs">star</span>'
                : "New",
            $rt["n"] . " review(s)",
            '<span class="material-icons mi-xs">star</span>',
        ) .
        d_kpi(
            "Hired on",
            $hr2["sent"]
                ? round((100 * $hr2["won"]) / $hr2["sent"]) . "%"
                : "—",
            (int) $hr2["sent"] . " offers sent",
            '<span class="material-icons mi">email</span>',
        ) .
        "</div>";
    $s1 = [
        [
            "name" => "Earnings",
            "color" => "#2f5bff",
            "v" => d_series($mon, $mk, "e"),
        ],
    ];
    echo '<div class="pd-g2"><div class="pd-card"><div class="pd-ct">Earnings by month</div>' .
        d_line($ml, $s1, 600, 230, true) .
        '</div><div class="pd-card"><div class="pd-ct">My jobs by status</div>' .
        d_donut($stt, (string) array_sum(array_column($stt, 1)), "jobs") .
        "</div></div>";
    $up = $pdo->prepare(
        "SELECT j.*,c.name cn FROM jobs j JOIN users c ON c.id=j.customer_id WHERE j.worker_id=? AND j.status IN('requested','assigned','in_progress') ORDER BY j.slot IS NULL,j.slot,j.id LIMIT 6",
    );
    $up->execute([$id]);
    $om = $pdo->prepare(
        "SELECT j.*,c.name cn FROM jobs j JOIN users c ON c.id=j.customer_id WHERE j.status='open' AND j.category=? ORDER BY j.urgent DESC,j.id DESC LIMIT 6",
    );
    $om->execute([$u["skill"] ?? ""]);
    echo '<div class="pd-g2"><div class="pd-card"><div class="pd-ct">Upcoming work</div><div style="overflow-x:auto">' .
        d_jobs_table($up->fetchAll()) .
        '</div></div><div class="pd-card"><div class="pd-ct">Open jobs for your skill (' .
        e($u["skill"] ?? "—") .
        ')</div><div style="overflow-x:auto">' .
        d_jobs_table($om->fetchAll()) .
        "</div></div></div>";
} else {
    $id = $u["id"];
    $pc = "customer_id=? AND paid=1 AND refunded=0";
    $S = d_n($pdo, "SELECT COALESCE(SUM(price+tip),0) FROM jobs WHERE $pc", [
        $id,
    ]);
    $act = (int) d_n(
        $pdo,
        "SELECT COUNT(*) FROM jobs WHERE customer_id=? AND status IN('open','requested','assigned','in_progress')",
        [$id],
    );
    $cmp = (int) d_n(
        $pdo,
        "SELECT COUNT(*) FROM jobs WHERE customer_id=? AND status='completed'",
        [$id],
    );
    $mon = [];
    $s = $pdo->prepare(
        "SELECT DATE_FORMAT(COALESCE(paid_at,created),'%Y-%m') m,SUM(price+tip) sp FROM jobs WHERE $pc GROUP BY m",
    );
    $s->execute([$id]);
    foreach ($s->fetchAll() as $r) {
        $mon[$r["m"]] = $r;
    }
    $cat = [];
    $s = $pdo->prepare(
        "SELECT category,COUNT(*) n FROM jobs WHERE customer_id=? GROUP BY category ORDER BY n DESC LIMIT 6",
    );
    $s->execute([$id]);
    foreach ($s->fetchAll() as $i => $r) {
        $cat[] = [$r["category"], (int) $r["n"], $PAL[$i]];
    }
    d_head(
        $hi . ", " . $u["name"] . " ",
        "Your jobs, spending and workers in one place.",
        '<a class="pd-b blue" href="post_job.php">+ Post a job</a><a class="pd-b" href="match.php"><span class="material-icons mi">gps_fixed</span> Smart match</a><a class="pd-b" href="dashboard.php">My jobs</a>',
    );
    echo '<div class="pd-g4">' .
        d_kpi(
            "Total spent",
            d_money($S),
            "On paid jobs",
            '<span class="material-icons mi">credit_card</span>',
            true,
            d_pct(
                (float) ($mon[$cur]["sp"] ?? 0),
                (float) ($mon[$prev]["sp"] ?? 0),
            ),
        ) .
        d_kpi(
            "Active jobs",
            $act,
            "Open or in progress",
            '<span class="material-icons mi">handyman</span>',
        ) .
        d_kpi(
            "Completed jobs",
            $cmp,
            "All time",
            '<span class="material-icons mi-xs">check_circle</span>',
        ) .
        d_kpi(
            "Wallet balance",
            d_money($u["wallet"] ?? 0),
            '<a href="wallet.php">Top up</a>',
            '<span class="material-icons mi">account_balance_wallet</span>',
        ) .
        "</div>";
    $s1 = [
        [
            "name" => "Spending",
            "color" => "#2f5bff",
            "v" => d_series($mon, $mk, "sp"),
        ],
    ];
    echo '<div class="pd-g2"><div class="pd-card"><div class="pd-ct">Spending by month</div>' .
        d_line($ml, $s1, 600, 230, true) .
        '</div><div class="pd-card"><div class="pd-ct">My jobs by category</div>' .
        d_donut($cat, (string) array_sum(array_column($cat, 1)), "jobs") .
        "</div></div>";
    $todo = [];
    $s = $pdo->prepare(
        "SELECT j.id,j.title,COUNT(o.id) n FROM jobs j JOIN offers o ON o.job_id=j.id AND o.status='pending' WHERE j.customer_id=? AND j.status='open' GROUP BY j.id,j.title",
    );
    $s->execute([$id]);
    foreach ($s->fetchAll() as $r) {
        $todo[] = [
            "job.php?id=" . $r["id"],
            $r["title"] . " – " . $r["n"] . " offer(s) to compare",
            $r["n"],
        ];
    }
    $s = $pdo->prepare(
        "SELECT id,title,confirmed,paid FROM jobs WHERE customer_id=? AND status='completed' AND refunded=0 AND (confirmed=0 OR paid=0)",
    );
    $s->execute([$id]);
    foreach ($s->fetchAll() as $r) {
        $todo[] = [
            "job.php?id=" . $r["id"],
            $r["title"] .
            ($r["confirmed"] ? " – ready to pay" : " – confirm the work"),
            "!",
        ];
    }
    $al = "";
    foreach ($todo as $a) {
        $al .=
            '<a href="' .
            e($a[0]) .
            '"><span>' .
            e($a[1]) .
            "</span><b>" .
            e($a[2]) .
            "</b></a>";
    }
    if (!$al) {
        $al =
            '<a href="post_job.php"><span>Nothing waiting for you</span><b class="ok"><span class="material-icons mi-xs">verified</span></b></a>';
    }
    $fv = $pdo->prepare(
        "SELECT u.id,u.name,u.skill FROM favorites f JOIN users u ON u.id=f.worker_id WHERE f.customer_id=? LIMIT 5",
    );
    $fv->execute([$id]);
    $fl = "";
    foreach ($fv->fetchAll() as $r) {
        $fl .=
            '<a href="worker.php?id=' .
            $r["id"] .
            '"><span class="pd-who">' .
            d_av($r["name"], 28) .
            e($r["name"]) .
            '</span><small class="pd-mute">' .
            e($r["skill"]) .
            "</small></a>";
    }
    $rj = $pdo->prepare(
        "SELECT j.*,w.name wn FROM jobs j LEFT JOIN users w ON w.id=j.worker_id WHERE j.customer_id=? ORDER BY j.id DESC LIMIT 6",
    );
    $rj->execute([$id]);
    echo '<div class="pd-g3"><div class="pd-card"><div class="pd-ct">To do</div><div class="pd-list">' .
        $al .
        '</div></div><div class="pd-card"><div class="pd-ct">Favorite workers</div><div class="pd-list">' .
        ($fl ?:
            '<p class="pd-mute">Save workers from their profile with <span class="material-icons mi">favorite</span>️.</p>') .
        "</div></div></div>";
    echo '<div class="pd-card"><div class="pd-ct">Recent jobs</div><div style="overflow-x:auto">' .
        d_jobs_table($rj->fetchAll(), "wn") .
        "</div></div>";
}
echo "</div>";
foot();

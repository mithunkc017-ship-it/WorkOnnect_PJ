<?php // Role based dashboard (customer / worker / admin)
require "config.php";
need();
$u = me();
expireJobs($pdo);
if ($u["role"] == "worker" && isset($_POST["alerts"])) {
    $pdo->prepare(
        "UPDATE users SET job_alerts=1-job_alerts WHERE id=?",
    )->execute([$u["id"]]);
    header("Location: dashboard.php");
    exit();
}
if ($u["role"] == "admin" && isset($_POST["ban"])) {
    $pdo->prepare(
        "UPDATE users SET banned=1-banned,ban_reason=? WHERE id=? AND role<>'admin'",
    )->execute([substr($_POST["why"] ?? "", 0, 200), (int) $_POST["ban"]]);
    logit("Ban/unban user #" . (int) $_POST["ban"]);
    header("Location: dashboard.php");
    exit();
}
if ($u["role"] == "admin" && isset($_POST["v"])) {
    $pdo->prepare("UPDATE users SET verified=1-verified WHERE id=?")->execute([
        (int) $_POST["v"],
    ]);
    notify(
        (int) $_POST["v"],
        "Your verified badge status was updated by admin",
    );
    header("Location: dashboard.php");
    exit();
}
head("Dashboard");
echo d_top("dashboard.php");
?>
<style>
/* ===== Dashboard animations & styling (scoped to .dash) ===== */
.dash{--d-a:#4f46e5;--d-b:#00a8ff;--d-c:#ff4f93;position:relative}
.dash *{box-sizing:border-box}

/* Welcome banner */
.dash>h2:first-of-type{position:relative;overflow:hidden;margin:0 0 20px;padding:22px 24px;border-radius:20px;color:#fff;
  background:linear-gradient(120deg,#4f46e5,#6d5efc,#00a8ff,#4f46e5);background-size:300% 300%;
  box-shadow:0 16px 36px rgba(79,70,229,.33);animation:dDrop .7s cubic-bezier(.2,.8,.2,1) both,dFlow 10s ease infinite}
.dash>h2:first-of-type::after{content:"";position:absolute;width:200px;height:200px;border-radius:50%;right:-50px;top:-90px;
  background:rgba(255,255,255,.14);animation:dPulse 6s ease-in-out infinite}

/* Section headings */
.dash h3{display:flex;align-items:center;gap:8px;margin:28px 0 12px;position:relative;padding-bottom:8px;animation:dRise .6s both}
.dash h3::after{content:"";position:absolute;left:0;bottom:0;height:3px;width:56px;border-radius:3px;
  background:linear-gradient(90deg,var(--d-a),var(--d-b));animation:dGrow .9s .2s both}
.dash h3 .mi{color:var(--d-a)}

/* Cards */
.dash .card{position:relative;overflow:hidden;border-radius:18px;transition:transform .3s cubic-bezier(.2,.8,.2,1),box-shadow .3s}
.dash .card::before{content:"";position:absolute;left:0;top:0;height:100%;width:4px;
  background:linear-gradient(180deg,var(--d-a),var(--d-b));transform:scaleY(0);transform-origin:top;transition:transform .35s}
.dash .grid>.card:hover{transform:translateY(-6px);box-shadow:0 18px 36px rgba(79,70,229,.18)}
.dash .grid>.card:hover::before{transform:scaleY(1)}

/* Scroll reveal (class added by JS only, so no-JS stays visible) */
.dash .rv{opacity:0;transform:translateY(26px) scale(.98)}
.dash .rv.in{opacity:1;transform:none;transition:opacity .6s cubic-bezier(.2,.8,.2,1),transform .6s cubic-bezier(.2,.8,.2,1);transition-delay:var(--d,0s)}
.dash .grid>.card.rv.in:hover{transform:translateY(-6px)}

/* Buttons */
.dash .btn,.dash button{position:relative;overflow:hidden;transition:transform .2s,box-shadow .25s,filter .2s}
.dash .btn{display:inline-block;margin:3px 2px}
.dash .btn:hover,.dash button:hover{transform:translateY(-3px);box-shadow:0 10px 22px rgba(15,98,254,.28);filter:brightness(1.06)}
.dash .btn:active,.dash button:active{transform:scale(.95)}
.dash .btn .mi,.dash .btn .mi-xs{transition:transform .3s}
.dash .btn:hover .mi,.dash .btn:hover .mi-xs{transform:scale(1.25) rotate(-8deg)}
.d-ripple{position:absolute;border-radius:50%;background:rgba(255,255,255,.55);transform:scale(0);animation:dRipple .6s linear;pointer-events:none}

/* Emergency badge */
.dash .emg{display:inline-block;animation:dEmg 1.4s ease-in-out infinite}

/* Inputs */
.dash input,.dash select{transition:border-color .25s,box-shadow .25s,transform .25s}
.dash input:focus,.dash select:focus{outline:none;border-color:var(--d-a);box-shadow:0 0 0 4px rgba(79,70,229,.18);transform:translateY(-1px)}

/* Admin stats */
.dash .stat{font-size:clamp(26px,5vw,38px);margin:6px 0 0;background:linear-gradient(90deg,var(--d-a),var(--d-b));
  -webkit-background-clip:text;background-clip:text;color:transparent;display:inline-block}
.dash .bar{transform-origin:left center;border-radius:8px;background:linear-gradient(90deg,#0F62FE,#00a8ff)!important;
  animation:dBar 1s cubic-bezier(.2,.8,.2,1) both}

/* Tables */
.dash .tw{overflow-x:auto;border-radius:16px;box-shadow:0 8px 24px rgba(0,0,0,.08);animation:dRise .7s both}
.dash table{width:100%;border-collapse:separate;border-spacing:0;background:#fff;border-radius:16px;overflow:hidden}
.dash th{background:linear-gradient(90deg,#4f46e5,#00a8ff);color:#fff;padding:13px 12px;text-align:left;font-weight:600;white-space:nowrap}
.dash td{padding:11px 12px;border-bottom:1px solid #eef0f6;transition:background .25s}
.dash tr.rv.in:hover td,.dash tr:hover td{background:#f4f2ff}
.dash td form{margin:0;display:flex;gap:6px;align-items:center;flex-wrap:wrap}

@keyframes dRise{from{opacity:0;transform:translateY(24px)}to{opacity:1;transform:none}}
@keyframes dDrop{from{opacity:0;transform:translateY(-22px)}to{opacity:1;transform:none}}
@keyframes dGrow{from{width:0}to{width:56px}}
@keyframes dFlow{0%{background-position:0% 50%}50%{background-position:100% 50%}100%{background-position:0% 50%}}
@keyframes dPulse{0%,100%{transform:scale(1)}50%{transform:scale(1.2)}}
@keyframes dBar{from{transform:scaleX(0)}to{transform:scaleX(1)}}
@keyframes dEmg{0%,100%{box-shadow:0 0 0 0 rgba(204,0,0,.55)}50%{box-shadow:0 0 0 8px rgba(204,0,0,0)}}
@keyframes dRipple{to{transform:scale(4);opacity:0}}

@media (prefers-reduced-motion:reduce){
  .dash *,.dash *::before,.dash *::after{animation-duration:.01ms!important;animation-iteration-count:1!important;transition-duration:.01ms!important}
  .dash .rv{opacity:1;transform:none}
}
@media (max-width:600px){.dash>h2:first-of-type{padding:18px}}
</style>
<?php
echo '<div class="dash">';
echo "<h2>Welcome, " . e($u["name"]) . " (" . e($u["role"]) . ")</h2>";
function jobs($rows)
{
    foreach ($rows as $j) {
        echo '<div class="card">' .
            ($j["urgent"]
                ? '<span class="emg" style="background:#c00;color:#fff;padding:2px 8px;border-radius:10px;font-size:12px"><span class="material-icons mi">emergency</span> EMERGENCY</span><br>'
                : "") .
            "<b>" .
            e($j["title"]) .
            "</b> – " .
            e($j["category"]) .
            '<br><span class="material-icons mi">place</span> ' .
            e($j["location"]) .
            " · " .
            e($j["amount"]) .
            "<br>Status: <b>" .
            e($j["status"]) .
            '</b><br><br><a class="btn" href="job.php?id=' .
            $j["id"] .
            '">Open</a></div>';
    }
}
if ($u["role"] == "customer") {
    echo '<a class="btn" href="post_job.php"><span class="material-icons mi">add_circle</span> Post New Job</a> <a class="btn" href="match.php"><span class="material-icons mi">gps_fixed</span> Smart Match</a> <a class="btn" href="addresses.php"><span class="material-icons mi">place</span> Addresses</a> <a class="btn" href="leaderboard.php"><span class="material-icons mi">emoji_events</span> Top workers</a><h3><span class="material-icons mi">favorite</span> Favorite Workers</h3><div class="grid">';
    $fv = $pdo->prepare(
        "SELECT u.* FROM favorites f JOIN users u ON u.id=f.worker_id WHERE f.customer_id=?",
    );
    $fv->execute([$u["id"]]);
    foreach ($fv as $w) {
        echo '<div class="card"><b>' .
            e($w["name"]) .
            "</b> " .
            badge($w["verified"]) .
            "<br>" .
            e($w["skill"]) .
            '<br><br><a class="btn" href="worker.php?id=' .
            $w["id"] .
            '">View</a></div>';
    }
    echo '</div><h3>My Jobs</h3><div class="grid">';
    $s = $pdo->prepare(
        "SELECT * FROM jobs WHERE customer_id=? ORDER BY id DESC",
    );
    $s->execute([$u["id"]]);
    jobs($s->fetchAll());
    echo "</div>";
} elseif ($u["role"] == "worker") {
    $me = $pdo->prepare("SELECT verified FROM users WHERE id=?");
    $me->execute([$u["id"]]);
    echo badge($me->fetchColumn());
    echo ' <a class="btn" href="schedule.php"><span class="material-icons mi">calendar_today</span> My Schedule</a> <a class="btn" href="my_upi.php"><span class="material-icons mi">credit_card</span> ' .
        ($u["upi_id"]
            ? "My UPI ID"
            : 'Add UPI ID <span class="material-icons mi-xs">warning</span>') .
        '</a> <a class="btn" href="upi_confirm.php"><span class="material-icons mi">smartphone</span> UPI confirmations</a> <a class="btn" href="certificates.php"><span class="material-icons mi">school</span> Certificates</a> <a class="btn" href="heatmap.php"><span class="material-icons mi">local_fire_department</span> Demand map</a> <a class="btn" href="leaderboard.php"><span class="material-icons mi-xs">emoji_events</span> Top workers</a> <form method="post" style="display:inline"><button name="alerts" value="1">' .
        ($u["job_alerts"]
            ? '<span class="material-icons mi">notifications</span> New-job alerts: ON'
            : '<span class="material-icons mi">notifications_off</span> New-job alerts: OFF') .
        "</button></form>";
    echo '<h3><span class="material-icons mi">lock_open</span> Open Jobs</h3><form method="get" style="display:flex;gap:8px"><input name="q" placeholder="Search title or location" value="' .
        e($_GET["q"] ?? "") .
        '"><select name="cat"><option value="">All categories</option>';
    foreach ($CATS as $c) {
        echo "<option" .
            (($_GET["cat"] ?? "") == $c ? " selected" : "") .
            ">" .
            $c .
            "</option>";
    }
    echo '</select><button>Search</button></form><div class="grid">';
    $k = "%" . ($_GET["q"] ?? "") . "%";
    $c = $_GET["cat"] ?? "";
    $s = $pdo->prepare(
        "SELECT * FROM jobs WHERE status='open' AND (title LIKE ? OR location LIKE ?) AND (?='' OR category=?) ORDER BY urgent DESC,(category=?) DESC,id DESC LIMIT 50",
    );
    $s->execute([$k, $k, $c, $c, $u["skill"]]);
    jobs($s->fetchAll());
    echo "</div>";
    echo '<p><a class="btn" href="open_jobs.php">See all open jobs →</a></p>';
    echo '<h3>My Assigned Jobs</h3><div class="grid">';
    $s = $pdo->prepare("SELECT * FROM jobs WHERE worker_id=? ORDER BY id DESC");
    $s->execute([$u["id"]]);
    jobs($s->fetchAll());
    echo "</div>";
} else {
    $c = fn($q) => $pdo->query($q)->fetchColumn();
    echo '<h3><span class="material-icons mi">analytics</span> Analytics</h3><div class="grid"><div class="card"><span class="material-icons mi">people</span> Users<h2 class="stat">' .
        $c("SELECT COUNT(*) FROM users") .
        '</h2></div><div class="card"><span class="material-icons mi">engineering</span> Workers<h2 class="stat">' .
        $c("SELECT COUNT(*) FROM users WHERE role='worker'") .
        '</h2></div><div class="card"><span class="material-icons mi">assignment</span> Jobs<h2 class="stat">' .
        $c("SELECT COUNT(*) FROM jobs") .
        '</h2></div><div class="card"><span class="material-icons mi-xs">check_circle</span> Completed<h2 class="stat">' .
        $c("SELECT COUNT(*) FROM jobs WHERE status='completed'") .
        '</h2></div><div class="card"><span class="material-icons mi">payments</span> Income<h2 class="stat">₹' .
        (int) $c("SELECT SUM(price) FROM jobs WHERE paid=1") .
        '</h2></div><div class="card"><span class="material-icons mi">account_balance</span> Platform fee (10%)<h2 class="stat">₹' .
        (int) $c("SELECT SUM(commission) FROM jobs WHERE paid=1") .
        '</h2></div></div><div class="card"><b>Jobs by category</b>';
    foreach (
        $pdo->query("SELECT category,COUNT(*) n FROM jobs GROUP BY category")
        as $x
    ) {
        echo "<div>" .
            e($x["category"]) .
            ' <span class="bar" style="display:inline-block;background:#0F62FE;height:12px;width:' .
            $x["n"] * 30 .
            'px;max-width:60%"></span> ' .
            $x["n"] .
            "</div>";
    }
    echo '</div><p><a class="btn" href="export.php?t=users"><span class="material-icons mi-xs">arrow_downward</span> Users CSV</a> <a class="btn" href="export.php?t=jobs"><span class="material-icons mi-xs">arrow_downward</span> Jobs CSV</a> <a class="btn" href="export.php?t=payments"><span class="material-icons mi-xs">arrow_downward</span> Payments CSV</a> <a class="btn" href="charts.php"><span class="material-icons mi">trending_up</span> Charts</a> <a class="btn" href="announcements.php"><span class="material-icons mi">campaign</span> Announcements</a> <a class="btn" href="qr_payments.php"><span class="material-icons mi">smartphone</span> QR payments</a> <a class="btn" href="settlements.php"><span class="material-icons mi">attach_money</span> Settlements</a> <a class="btn" href="login_history.php"><span class="material-icons mi">lock</span> Login history</a> <a class="btn" href="my_upi.php"><span class="material-icons mi">credit_card</span> My UPI ID</a> <a class="btn" href="disputes.php"><span class="material-icons mi">balance</span> Disputes</a> <a class="btn" href="certificates.php"><span class="material-icons mi">school</span> Certificates</a> <a class="btn" href="heatmap.php"><span class="material-icons mi">local_fire_department</span> Demand map</a></p><h3>Users</h3><div class="tw"><table><tr><th>Name</th><th>Email</th><th>Role</th><th>Skill</th><th>Verified</th><th>Suspend</th></tr>';
    foreach ($pdo->query("SELECT * FROM users ORDER BY id DESC") as $x) {
        echo "<tr><td>" .
            e($x["name"]) .
            "</td><td>" .
            e($x["email"]) .
            "</td><td>" .
            $x["role"] .
            "</td><td>" .
            e($x["skill"]) .
            "</td><td>";
        if ($x["role"] == "worker") {
            echo '<form method="post"><input type="hidden" name="v" value="' .
                $x["id"] .
                '"><button>' .
                ($x["verified"] ? "Remove badge" : "Verify") .
                "</button></form>";
        }
        echo "</td><td>";
        if ($x["role"] != "admin") {
            echo '<form method="post"><input type="hidden" name="ban" value="' .
                $x["id"] .
                '">' .
                ($x["banned"]
                    ? '<b class="err">Suspended</b> <button>Unban</button>'
                    : '<input name="why" placeholder="Reason" required><button style="background:#c00">Ban</button>') .
                "</form>";
        }
        echo "</td></tr>";
    }
    echo '</table></div><h3>All Jobs</h3><div class="grid">';
    jobs($pdo->query("SELECT * FROM jobs ORDER BY id DESC")->fetchAll());
    echo "</div>";
}
echo '</div>';
?>
<script>
(function () {
  var root = document.querySelector(".dash");
  if (!root) return;
  var reduce = window.matchMedia && matchMedia("(prefers-reduced-motion: reduce)").matches;

  // Scroll reveal for cards and table rows, staggered inside each group
  if (!reduce && "IntersectionObserver" in window) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (en.isIntersecting) { en.target.classList.add("in"); io.unobserve(en.target); }
      });
    }, { threshold: 0.08 });
    root.querySelectorAll(".grid, .tw table").forEach(function (group) {
      var items = group.matches(".grid") ? group.children : group.querySelectorAll("tr:not(:first-child)");
      Array.prototype.forEach.call(items, function (el, i) {
        el.classList.add("rv");
        el.style.setProperty("--d", Math.min(i * 0.06, 0.6) + "s");
        io.observe(el);
      });
    });
    root.querySelectorAll(":scope > .card").forEach(function (el) { el.classList.add("rv"); io.observe(el); });
  }

  // Count-up for admin analytics numbers
  if (!reduce) {
    root.querySelectorAll(".stat").forEach(function (el) {
      var m = el.textContent.trim().match(/^(\D*)(\d+)$/);
      if (!m) return;
      var pre = m[1], target = parseInt(m[2], 10), t0 = null, dur = 1100;
      el.textContent = pre + "0";
      function tick(ts) {
        if (t0 === null) t0 = ts;
        var p = Math.min((ts - t0) / dur, 1);
        el.textContent = pre + Math.round(target * (1 - Math.pow(1 - p, 3)));
        if (p < 1) requestAnimationFrame(tick);
      }
      requestAnimationFrame(tick);
    });
  }

  // Ripple on buttons and links (does not block navigation or submit)
  root.addEventListener("click", function (e) {
    var b = e.target.closest(".btn, button");
    if (!b || reduce) return;
    var r = b.getBoundingClientRect(), d = Math.max(r.width, r.height);
    var s = document.createElement("span");
    s.className = "d-ripple";
    s.style.width = s.style.height = d + "px";
    s.style.left = (e.clientX - r.left - d / 2) + "px";
    s.style.top = (e.clientY - r.top - d / 2) + "px";
    b.appendChild(s);
    setTimeout(function () { s.remove(); }, 600);
  });
})();
</script>
<?php foot();
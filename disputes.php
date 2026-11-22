<?php // Admin: list of all disputes
require_once __DIR__ . "/config.php";
need("admin");
head("Disputes");
?>
<style>
/* hide the top menu + Chat button on this page, show the dark sidebar instead */
#cb { display: none !important; }
/* keep the top menu in the page (so the old More Tools popup still works) but hide its visible links */
body > nav { background:none !important; box-shadow:none !important; border:0 !important; padding:0 !important; min-height:0 !important; height:0 !important; overflow:visible !important; }
body > nav > a, body > nav > button, body > nav > div > a, body > nav > div > span { display:none !important; }
body > main { margin-left: 280px !important; }

.dp-side { position:fixed !important; left:0 !important; top:0 !important; bottom:0 !important; width:280px; z-index:850 !important;
  display:flex !important; flex-direction:column; visibility:visible !important; opacity:1;
  height:100vh; height:100dvh;
  overflow-y:auto; overflow-x:hidden;      /* whole sidebar scrolls, so Logout is always reachable */
  padding:16px 16px 40px;
  background:linear-gradient(180deg,#0f1b3d,#1b1450); box-shadow:6px 0 24px rgba(0,0,0,.25);
  animation:dpSlide .5s cubic-bezier(.2,.8,.2,1) both; }
.dp-side::-webkit-scrollbar { width:4px; }
.dp-side::-webkit-scrollbar-thumb { background:rgba(255,255,255,.2); border-radius:4px; }
@keyframes dpSlide { from{transform:translateX(-100%)} to{transform:none} }

.dp-brand { flex-shrink:0; font:800 1.6rem Inter,sans-serif; color:#fff; text-decoration:none; padding:4px 4px 14px;
  border-bottom:1px solid rgba(255,255,255,.1); margin-bottom:12px; }
.dp-brand span { color:#38bdf8; }

.dp-side a.dp-i { flex-shrink:0; display:flex; align-items:center; gap:14px; padding:12px 16px; margin-bottom:3px; border-radius:12px;
  color:#cbd5f5; text-decoration:none; font-weight:600; transition:background .25s, transform .25s, color .25s; }
.dp-side a.dp-i:hover { background:rgba(255,255,255,.08); color:#fff; transform:translateX(4px); }
.dp-side a.dp-i.on { background:linear-gradient(90deg,#3b82f6,#6366f1); color:#fff; box-shadow:0 6px 16px rgba(79,70,229,.45); }
.dp-side a.dp-i .material-icons { font-size:22px; }

/* Logout sits directly below Dark mode */
.dp-side a.dp-out { margin-top:8px; background:rgba(239,68,68,.15); color:#fca5a5 !important; }
.dp-side a.dp-out:hover { background:rgba(239,68,68,.3) !important; color:#fff !important; }

.dp-more { display:none; margin:0 0 6px 18px; padding-left:8px; border-left:1px solid rgba(255,255,255,.14); }
.dp-more.show { display:block; }
.dp-more a.dp-i { padding:9px 12px; font-size:.92rem; font-weight:500; }
.dp-more .dp-msg { color:#94a3d8; font-size:.85rem; padding:8px 12px; }
#dpMore .dp-arr { margin-left:auto; transition:transform .25s; }
#dpMore.open .dp-arr { transform:rotate(180deg); }

@media (max-height:760px) { .dp-side a.dp-i { padding:9px 14px; margin-bottom:2px; } }
@media (max-width:800px) {
  .dp-side { width:64px; padding:10px 6px 40px; } .dp-side .dp-t, .dp-brand { display:none; }
  .dp-side a.dp-i { justify-content:center; padding:12px 0; } body > main { margin-left:64px !important; }
}

.dp-wrap { --p:#4f46e5; --p2:#7c3aed; --bg:#f6f7fd; --txt:#1e293b; --mut:#64748b;
  animation: dpFade .5s ease both; }
@keyframes dpFade { from{opacity:0; transform:translateY(12px)} to{opacity:1; transform:none} }
@keyframes dpRow  { from{opacity:0; transform:translateX(-16px)} to{opacity:1; transform:none} }
@keyframes dpGrad { 0%,100%{background-position:0% 50%} 50%{background-position:100% 50%} }
@keyframes dpPulse { 0%,100%{box-shadow:0 0 0 0 rgba(245,158,11,.5)} 50%{box-shadow:0 0 0 7px rgba(245,158,11,0)} }

.dp-title { display:flex; align-items:center; gap:10px; color:#fff; margin:0 0 16px;
  padding:16px 22px; border-radius:16px; font-size:1.3rem;
  background:linear-gradient(120deg,var(--p),var(--p2),#ec4899); background-size:200% 200%;
  animation:dpGrad 8s ease infinite; box-shadow:0 10px 25px rgba(79,70,229,.28); }

.dp-card { background:#fff; border-radius:16px; padding:6px; box-shadow:0 8px 30px rgba(30,41,59,.08); overflow-x:auto; }
.dp-card table { width:100%; border-collapse:separate; border-spacing:0 6px; }
.dp-card th { text-align:left; padding:12px 14px; font-size:.75rem; letter-spacing:.06em;
  text-transform:uppercase; color:var(--mut); background:none; border:0; }
.dp-card td { padding:13px 14px; background:var(--bg); color:var(--txt); border:0; vertical-align:top; }
.dp-card td:first-child { border-radius:12px 0 0 12px; }
.dp-card td:last-child  { border-radius:0 12px 12px 0; }
.dp-card tr.dp-r { animation:dpRow .45s ease both; transition:transform .25s; }
.dp-card tr.dp-r:hover { transform:translateY(-2px); }
.dp-card tr.dp-r:hover td { background:#eceefe; }
.dp-card a { color:var(--p); font-weight:600; text-decoration:none; }
.dp-card a:hover { text-decoration:underline; }

.dp-badge { display:inline-block; padding:4px 12px; border-radius:999px; font-size:.78rem;
  font-weight:600; text-transform:capitalize; background:#e2e8f0; color:#334155; }
.dp-badge.open { background:#fef3c7; color:#b45309; animation:dpPulse 1.8s infinite; }
.dp-badge.closed, .dp-badge.resolved { background:#dcfce7; color:#15803d; }

body.dark .dp-card { background:#1e293b; }
body.dark .dp-card td { background:#273449; color:#e2e8f0; }
body.dark .dp-card tr.dp-r:hover td { background:#2f3e57; }
</style>

<!-- Every admin page is listed directly here, so nothing depends on the hidden top menu or the popup -->
<aside class="dp-side" id="dpSide">
  <a class="dp-brand" href="index.php">Work<span>onnect</span></a>
  <a class="dp-i" href="dash.php"><span class="material-icons">dashboard</span><span class="dp-t">Dashboard</span></a>
  <a class="dp-i" href="dashboard.php"><span class="material-icons">tune</span><span class="dp-t">Manage</span></a>
  <a class="dp-i" href="analytics.php"><span class="material-icons">analytics</span><span class="dp-t">Analytics</span></a>
  <a class="dp-i" href="qr_payments.php"><span class="material-icons">payments</span><span class="dp-t">Payments</span></a>
  <a class="dp-i on" href="disputes.php"><span class="material-icons">balance</span><span class="dp-t">Disputes</span></a>
  <a class="dp-i" href="verifications.php"><span class="material-icons">fact_check</span><span class="dp-t">Verifications</span></a>
  <a class="dp-i" href="complaints.php"><span class="material-icons">report</span><span class="dp-t">Complaints</span></a>
  <a class="dp-i" href="announcements.php"><span class="material-icons">campaign</span><span class="dp-t">Announcements</span></a>
  <a class="dp-i" href="log.php"><span class="material-icons">history</span><span class="dp-t">Activity</span></a>
  <a class="dp-i" href="dash.php" id="dpMore"><span class="material-icons">apps</span><span class="dp-t">More Tools</span><span class="material-icons dp-arr dp-t">expand_more</span></a>
  <div class="dp-more" id="dpMoreBox"></div>
  <a class="dp-i" href="account_settings.php"><span class="material-icons">settings</span><span class="dp-t">Settings</span></a>
  <a class="dp-i" href="workers.php"><span class="material-icons">search</span><span class="dp-t">Find Workers</span></a>
  <a class="dp-i" href="#" id="dpDark"><span class="material-icons">dark_mode</span><span class="dp-t">Dark mode</span></a>
  <a class="dp-i dp-out" href="logout.php"><span class="material-icons">logout</span><span class="dp-t">Logout</span></a>
</aside>

<div class="dp-wrap">
<h2 class="dp-title"><span class="material-icons mi">balance</span> Disputes</h2>
<div class="dp-card"><table><tr><th>Job</th><th>Opened by</th><th>Reason</th><th>Status</th><th>Decision</th></tr>
<?php $i = 0; foreach (
    $pdo->query(
        "SELECT d.*,u.name FROM disputes d JOIN users u ON u.id=d.opened_by ORDER BY d.status='open' DESC,d.id DESC LIMIT 100",
    )
    as $x
) {
    echo '<tr class="dp-r" style="animation-delay:' . min($i++ * 0.05, 1.5) . 's"><td><a href="dispute.php?job=' .
        $x["job_id"] .
        '">#' .
        $x["job_id"] .
        "</a></td><td>" .
        e($x["name"]) .
        "</td><td>" .
        e($x["reason"]) .
        '</td><td><span class="dp-badge ' . e(strtolower($x["status"])) . '">' .
        e($x["status"]) .
        "</span></td><td>" .
        e($x["decision"]) .
        "</td></tr>";
} ?></table></div></div>

<script>
// move the sidebar out of <main> so no parent style can hide or clip it
document.body.appendChild(document.getElementById("dpSide"));
// More Tools: open the list right inside the sidebar (the old popup's options, read from admin_sidebar.php)
(function () {
  var more = document.getElementById("dpMore"), box = document.getElementById("dpMoreBox"), loaded = false;
  var skip = /logout|^#|^javascript:/i;
  function have() {                       // pages already listed in the sidebar
    var o = {};
    document.querySelectorAll("#dpSide > a[href]").forEach(function (a) { o[(a.getAttribute("href") || "").split("?")[0]] = 1; });
    return o;
  }
  function grab(url) {
    return fetch(url, { credentials: "same-origin" }).then(function (r) { return r.text(); }).then(function (h) {
      var d = new DOMParser().parseFromString(h, "text/html"), out = [], seen = have();
      d.querySelectorAll("a[href]").forEach(function (a) {
        var href = a.getAttribute("href") || "", key = href.split("?")[0];
        if (skip.test(href) || seen[key] || /^https?:/i.test(href)) return;
        var ic = a.querySelector(".material-icons"), icon = ic ? ic.textContent.trim() : "chevron_right";
        var c = a.cloneNode(true);
        c.querySelectorAll(".material-icons,.mi,script,style").forEach(function (n) { n.remove(); });
        var label = c.textContent.replace(/\s+/g, " ").trim();
        if (!label) return;
        seen[key] = 1; out.push({ href: href, label: label, icon: icon });
      });
      return out;
    });
  }
  function render(list) {
    box.innerHTML = "";
    if (!list.length) { var m = document.createElement("div"); m.className = "dp-msg"; m.textContent = "No more tools found."; box.appendChild(m); return; }
    list.forEach(function (x) {
      var a = document.createElement("a"); a.className = "dp-i"; a.href = x.href;
      var i = document.createElement("span"); i.className = "material-icons"; i.textContent = x.icon;
      var t = document.createElement("span"); t.className = "dp-t"; t.textContent = x.label;
      a.appendChild(i); a.appendChild(t); box.appendChild(a);
    });
  }
  more.onclick = function (e) {
    e.preventDefault();
    var show = box.classList.toggle("show"); more.classList.toggle("open", show);
    if (show && !loaded) {
      loaded = true;
      box.innerHTML = '<div class="dp-msg">Loading…</div>';
      grab("admin_sidebar.php").then(function (l) { return l.length ? l : grab("dash.php"); })
        .then(render)
        .catch(function () { location.href = "dash.php"; });
    }
  };
})();
// Dark mode uses the existing toggle
document.getElementById("dpDark").onclick = function (e) { e.preventDefault(); var b = document.getElementById("dmBtn"); if (b) b.click(); };
</script>
<?php foot();
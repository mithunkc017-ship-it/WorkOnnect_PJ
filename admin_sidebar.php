<?php // Admin: list of all disputes
require_once __DIR__ . "/config.php";
need("admin");
head("Disputes");
?>
<style>
/* hide the top menu + Chat button on this page, show the dark sidebar instead */
body > nav, #cb { display: none !important; }
body > main { margin-left: 280px !important; }
.dp-side { position:fixed !important; left:0 !important; top:0 !important; bottom:0 !important; width:280px; z-index:850 !important; display:flex !important; visibility:visible !important; opacity:1; flex-direction:column; height:100vh; overflow-y:auto;
  padding:16px; background:linear-gradient(180deg,#0f1b3d,#1b1450); box-shadow:6px 0 24px rgba(0,0,0,.25);
  animation:dpSlide .5s cubic-bezier(.2,.8,.2,1) both; }
@keyframes dpSlide { from{transform:translateX(-100%)} to{transform:none} }
.dp-brand { font:800 1.6rem Inter,sans-serif; color:#fff; text-decoration:none; padding:4px 4px 14px;
  border-bottom:1px solid rgba(255,255,255,.1); margin-bottom:12px; }
.dp-brand span { color:#38bdf8; }
.dp-side a.dp-i { display:flex; align-items:center; gap:14px; padding:14px 16px; margin-bottom:4px; border-radius:12px;
  color:#cbd5f5; text-decoration:none; font-weight:600; transition:background .25s, transform .25s, color .25s; }
.dp-side a.dp-i:hover { background:rgba(255,255,255,.08); color:#fff; transform:translateX(4px); }
.dp-side a.dp-i.on { background:linear-gradient(90deg,#3b82f6,#6366f1); color:#fff; box-shadow:0 6px 16px rgba(79,70,229,.45); }
.dp-side .dp-out { margin-top:auto; background:rgba(239,68,68,.12); color:#fca5a5 !important; }
.dp-side .dp-out:hover { background:rgba(239,68,68,.25) !important; }
@media (max-width:800px) {
  .dp-side { width:64px; padding:10px 6px; } .dp-side .dp-t, .dp-brand { display:none; }
  .dp-side a.dp-i { justify-content:center; padding:14px 0; } body > main { margin-left:64px !important; }
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

<aside class="dp-side" id="dpSide">
  <a class="dp-brand" href="index.php">Work<span>onnect</span></a>
  <a class="dp-i" href="dash.php"><span class="material-icons">dashboard</span><span class="dp-t">Dashboard</span></a>
  <a class="dp-i on" href="#" id="dpTools"><span class="material-icons">apps</span><span class="dp-t">Admin Tools</span></a>
  <a class="dp-i" href="account_settings.php"><span class="material-icons">settings</span><span class="dp-t">Settings</span></a>
  <a class="dp-i" href="workers.php"><span class="material-icons">search</span><span class="dp-t">Find Workers</span></a>
  <a class="dp-i" href="help.php"><span class="material-icons">help</span><span class="dp-t">Help</span></a>
  <a class="dp-i" href="#" id="dpDark"><span class="material-icons">dark_mode</span><span class="dp-t">Dark mode</span></a>
  <!-- <a class="dp-i dp-out" href="logout.php"><span class="material-icons">logout</span><span class="dp-t">Logout</span></a> -->
   <a href="logout.php" class="YOUR_SAME_ITEM_CLASS"><span class="material-icons">logout</span><span>Logout</span></a>
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
// "Admin Tools" opens the existing popup (admin_sidebar.php) where Disputes and all other pages are listed
document.getElementById("dpTools").onclick = function (e) { e.preventDefault(); document.body.classList.toggle("adm-open"); };
// Dark mode uses the existing toggle
document.getElementById("dpDark").onclick = function (e) { e.preventDefault(); var b = document.getElementById("dmBtn"); if (b) b.click(); };
</script>
<?php foot();
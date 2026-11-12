<?php // Worker schedule: upcoming jobs in time order, with double-booking warnings (two jobs less than 2 hours apart)
require "config.php";
need("worker");
$u = me();
$s = $pdo->prepare(
    "SELECT j.*,c.name cn FROM jobs j JOIN users c ON c.id=j.customer_id WHERE j.worker_id=? AND j.status IN('requested','assigned','in_progress') ORDER BY j.slot IS NULL,j.slot",
);
$s->execute([$u["id"]]);
$rows = $s->fetchAll();
head("My Schedule");
echo d_top("schedule.php");
?>
<style>
.sc{--sc-accent:#2563eb;--sc-ink:#1f2937;--sc-mute:#6b7280;--sc-line:#e5e7eb;--sc-bg:#fff;--sc-head:#f8fafc;--sc-hover:#f9fafb}
@media (prefers-color-scheme:dark){.sc{--sc-ink:#e5e7eb;--sc-mute:#9ca3af;--sc-line:#334155;--sc-bg:#1e293b;--sc-head:#172033;--sc-hover:#243047}}

.sc-head{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin:0 0 16px}
.sc-head h2{margin:0;display:flex;align-items:center;gap:8px;font-size:22px}
.sc-head h2 .mi{color:var(--sc-accent)}
.sc-count{color:var(--sc-mute);font-size:14px}

.sc-wrap{overflow-x:auto;background:var(--sc-bg);border:1px solid var(--sc-line);border-radius:12px;
  box-shadow:0 1px 3px rgba(0,0,0,.05);animation:sc-fade .4s ease both}
.sc table{width:100%;min-width:640px;border-collapse:collapse;background:transparent}
.sc th{background:var(--sc-head);color:var(--sc-mute);padding:12px 16px;text-align:left;font-size:12px;font-weight:600;
  letter-spacing:.5px;text-transform:uppercase;border-bottom:1px solid var(--sc-line);white-space:nowrap}
.sc td{padding:14px 16px;color:var(--sc-ink);font-size:14px;border-bottom:1px solid var(--sc-line);vertical-align:middle}
.sc tr:last-child td{border-bottom:0}
.sc-row{transition:background .2s}
.sc-row:hover{background:var(--sc-hover)}

.sc-when{display:flex;flex-direction:column;gap:6px;align-items:flex-start}
.sc-time{font-weight:600}
.sc-nofix{color:var(--sc-mute)}
.sc-clashtag{display:inline-flex;align-items:center;gap:4px;color:#b91c1c;background:#fef2f2;border:1px solid #fecaca;
  font-size:12px;font-weight:600;padding:3px 8px;border-radius:6px}
.sc-clashtag .mi-xs,.sc-clashtag .mi{font-size:14px}

.sc-job{color:var(--sc-accent);font-weight:600;text-decoration:none}
.sc-job:hover{text-decoration:underline}

.sc-st{display:inline-block;padding:3px 10px;border-radius:6px;font-size:12px;font-weight:600;text-transform:capitalize;
  background:var(--sc-head);color:var(--sc-mute);border:1px solid var(--sc-line)}
.sc-loc{display:inline-flex;align-items:center;gap:4px;color:var(--sc-mute)}
.sc-loc .mi{font-size:17px}

.sc-empty{text-align:center;padding:44px 16px;color:var(--sc-mute);background:var(--sc-bg);border:1px dashed var(--sc-line);border-radius:12px}
.sc-empty .mi{font-size:40px;display:block;margin-bottom:6px}

@keyframes sc-fade{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}
@media (prefers-reduced-motion:reduce){.sc *{animation:none!important;transition:none!important}}
</style>

<div class="sc">
<div class="sc-head">
    <h2><span class="material-icons mi">calendar_today</span> My Schedule</h2>
    <span class="sc-count"><?= count($rows) ?> upcoming</span>
</div>
<?php if (!$rows): ?>
<div class="sc-empty"><span class="material-icons mi">event_available</span>No upcoming jobs. Your schedule is clear.</div>
<?php else: ?>
<div class="sc-wrap"><table><tr><th>When</th><th>Job</th><th>Customer</th><th>Status</th><th>Location</th></tr>
<?php
$prev = null;
foreach ($rows as $j) {
    $w = "";
    if ($j["slot"] && $prev && abs(strtotime($j["slot"]) - $prev) < 7200) {
        $w =
            '<b class="err sc-clashtag"><span class="material-icons mi-xs">warning</span> Clash with the previous job</b>';
    }
    if ($j["slot"]) {
        $prev = strtotime($j["slot"]);
    }
    echo '<tr class="sc-row"><td><div class="sc-when">' .
        ($j["slot"]
            ? '<span class="sc-time">' . e(date("d M, h:i A", strtotime($j["slot"]))) . "</span>"
            : '<span class="sc-nofix">Not fixed</span>') .
        $w .
        '</div></td><td><a class="sc-job" href="job.php?id=' .
        $j["id"] .
        '">' .
        e($j["title"]) .
        "</a></td><td>" .
        e($j["cn"]) .
        '</td><td><span class="sc-st">' .
        e(str_replace("_", " ", $j["status"])) .
        '</span></td><td><span class="sc-loc"><span class="material-icons mi">place</span> ' .
        e($j["location"]) .
        "</span></td></tr>";
}
?></table></div>
<?php endif; ?>
</div>
<?php foot();
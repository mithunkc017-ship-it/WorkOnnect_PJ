<?php // Open jobs board: every job waiting for a worker, with search, filters and paging (workers and admin)
require "config.php";
need();
$u = me();
if ($u["role"] == "customer") {
    header("Location: dashboard.php");
    exit();
}
$q = trim($_GET["q"] ?? "");
$c = $_GET["cat"] ?? "";
$urg = isset($_GET["urgent"]) ? 1 : 0;
$mine = isset($_GET["mine"]) ? 1 : 0;
$p = max(1, (int) ($_GET["p"] ?? 1));
$per = 12;
$w =
    "j.status='open' AND (j.title LIKE ? OR j.location LIKE ?) AND (?='' OR j.category=?) AND (?=0 OR j.urgent=1) AND (?=0 OR j.category=?)";
$args = [
    "%" . $q . "%",
    "%" . $q . "%",
    $c,
    $c,
    $urg,
    $mine,
    $u["skill"] ?? "",
];
$n = $pdo->prepare("SELECT COUNT(*) FROM jobs j WHERE $w");
$n->execute($args);
$tot = (int) $n->fetchColumn();
$s = $pdo->prepare(
    "SELECT j.*,(SELECT COUNT(*) FROM offers o WHERE o.job_id=j.id) oc FROM jobs j WHERE $w ORDER BY j.urgent DESC,j.id DESC LIMIT $per OFFSET " .
        ($p - 1) * $per,
);
$s->execute($args);
head("Open Jobs");
echo d_top("open_jobs.php");
?>
<style>
.oj{--oj-blue:#2563eb;--oj-blue2:#1d4ed8;--oj-ink:#0f172a;--oj-mute:#64748b;--oj-line:#e2e8f0;--oj-bg:#fff;--oj-chip:#f1f5f9}
@media (prefers-color-scheme:dark){.oj{--oj-ink:#e5e7eb;--oj-mute:#94a3b8;--oj-line:#334155;--oj-bg:#1e293b;--oj-chip:#0f172a}}

/* Header */
.oj-head{display:flex;align-items:center;gap:12px;margin:0 0 16px;animation:oj-down .5s ease both}
.oj-head h2{margin:0;display:flex;align-items:center;gap:8px;font-size:24px}
.oj-head .oj-ico{width:42px;height:42px;border-radius:12px;display:grid;place-items:center;color:#fff;background:linear-gradient(135deg,var(--oj-blue),#7c3aed);box-shadow:0 6px 16px rgba(37,99,235,.35)}
.oj-count{background:var(--oj-chip);color:var(--oj-mute);font:600 13px system-ui,sans-serif;padding:4px 12px;border-radius:20px}

/* Filter bar */
.oj-filter{display:flex;gap:10px;flex-wrap:wrap;align-items:center;background:var(--oj-bg);border:1px solid var(--oj-line);border-radius:16px;padding:12px;box-shadow:0 4px 14px rgba(0,0,0,.05);animation:oj-down .55s .05s ease both}
.oj-filter input[name=q],.oj-filter select{flex:1 1 180px;min-width:0;width:auto;margin:0;padding:10px 14px;border:1px solid var(--oj-line);border-radius:12px;background:var(--oj-bg);color:var(--oj-ink);font-size:14px;transition:border-color .2s,box-shadow .2s}
.oj-filter input[name=q]:focus,.oj-filter select:focus{outline:0;border-color:var(--oj-blue);box-shadow:0 0 0 4px rgba(37,99,235,.15)}
.oj-chipbox{display:inline-flex;align-items:center;gap:6px;white-space:nowrap;margin:0;padding:9px 14px;border:1px solid var(--oj-line);border-radius:999px;background:var(--oj-chip);color:var(--oj-ink);font-size:14px;cursor:pointer;transition:all .2s;user-select:none}
.oj-chipbox:hover{transform:translateY(-1px);border-color:var(--oj-blue)}
.oj-chipbox:has(input:checked){background:var(--oj-blue);border-color:var(--oj-blue);color:#fff}
.oj-chipbox input{width:auto;margin:0;accent-color:#fff}
.oj-chipbox .mi{font-size:18px}
.oj-filter button{border:0;cursor:pointer;padding:10px 22px;border-radius:12px;color:#fff;font-weight:600;font-size:14px;background:linear-gradient(135deg,var(--oj-blue),var(--oj-blue2));box-shadow:0 4px 12px rgba(37,99,235,.35);transition:transform .15s,box-shadow .2s}
.oj-filter button:hover{transform:translateY(-2px);box-shadow:0 8px 18px rgba(37,99,235,.4)}
.oj-filter button:active{transform:translateY(0) scale(.97)}

/* Cards */
.oj .grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(290px,1fr));gap:16px;margin-top:18px}
.oj-card{position:relative;display:flex;flex-direction:column;gap:8px;background:var(--oj-bg);color:var(--oj-ink);border:1px solid var(--oj-line);border-radius:18px;padding:18px 18px 16px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.05);opacity:0;animation:oj-up .5s ease forwards;animation-delay:calc(var(--i)*60ms);transition:transform .25s,box-shadow .25s,border-color .25s}
.oj-card::before{content:"";position:absolute;left:0;top:0;bottom:0;width:4px;background:linear-gradient(var(--oj-blue),#7c3aed);transition:width .25s}
.oj-card.oj-urgent::before{background:linear-gradient(#ef4444,#f97316)}
.oj-card:hover{transform:translateY(-6px);box-shadow:0 16px 30px rgba(15,23,42,.14);border-color:var(--oj-blue)}
.oj-card:hover::before{width:7px}
.oj-title{font-size:17px;font-weight:700;line-height:1.3;margin:0}
.oj-cat{align-self:flex-start;background:var(--oj-chip);color:var(--oj-blue);font:600 12px system-ui,sans-serif;padding:3px 10px;border-radius:20px}
.oj-sos{align-self:flex-start;display:inline-flex;align-items:center;gap:4px;background:linear-gradient(135deg,#ef4444,#dc2626);color:#fff;font:700 11px system-ui,sans-serif;letter-spacing:.5px;padding:4px 10px;border-radius:20px;animation:oj-pulse 1.6s infinite}
.oj-sos .mi{font-size:15px}
.oj-meta{display:flex;align-items:center;gap:8px;font-size:13.5px;color:var(--oj-mute)}
.oj-meta .mi{font-size:18px;color:var(--oj-blue);flex:none}
.oj-meta b{color:var(--oj-ink)}
.oj-foot{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-top:auto;padding-top:12px;border-top:1px dashed var(--oj-line)}
.oj-offers{display:inline-flex;align-items:center;gap:5px;background:var(--oj-chip);color:var(--oj-mute);font:600 12px system-ui,sans-serif;padding:5px 11px;border-radius:20px}
.oj-offers .mi{font-size:15px}
.oj-card .btn{display:inline-flex;align-items:center;gap:4px;text-decoration:none;margin:0;transition:transform .2s,box-shadow .2s}
.oj-card:hover .btn{transform:translateX(3px);box-shadow:0 6px 14px rgba(37,99,235,.35)}

/* Empty state */
.oj-empty{grid-column:1/-1;text-align:center;padding:50px 16px;color:var(--oj-mute);background:var(--oj-bg);border:2px dashed var(--oj-line);border-radius:18px;animation:oj-up .5s ease both}
.oj-empty .mi{font-size:46px;display:block;margin-bottom:6px;animation:oj-float 3s ease-in-out infinite}

/* Pagination */
.oj-pager{display:flex;align-items:center;justify-content:center;gap:12px;flex-wrap:wrap;margin:22px 0;color:var(--oj-mute);font-size:14px;animation:oj-up .5s ease both}
.oj-pager .btn{text-decoration:none;transition:transform .2s}
.oj-pager .btn:hover{transform:translateY(-2px)}
.oj-page{background:var(--oj-chip);padding:6px 14px;border-radius:20px;font-weight:600}

@keyframes oj-up{from{opacity:0;transform:translateY(18px)}to{opacity:1;transform:none}}
@keyframes oj-down{from{opacity:0;transform:translateY(-12px)}to{opacity:1;transform:none}}
@keyframes oj-pulse{0%{box-shadow:0 0 0 0 rgba(239,68,68,.55)}70%{box-shadow:0 0 0 9px rgba(239,68,68,0)}100%{box-shadow:0 0 0 0 rgba(239,68,68,0)}}
@keyframes oj-float{0%,100%{transform:translateY(0)}50%{transform:translateY(-6px)}}
@media (prefers-reduced-motion:reduce){.oj *{animation:none!important;transition:none!important;opacity:1!important}}
@media (max-width:560px){.oj-filter button{flex:1 1 100%}}
</style>

<div class="oj">
<div class="oj-head">
    <span class="oj-ico"><span class="material-icons mi">lock_open</span></span>
    <h2>Open Jobs <span class="oj-count"><?= $tot ?> available</span></h2>
</div>

<form method="get" class="oj-filter"><input name="q" placeholder="Search title or location" value="<?= e(
    $q,
) ?>">
<select name="cat"><option value="">All categories</option><?php foreach (
    $CATS
    as $x
) {
    echo "<option" . ($c == $x ? " selected" : "") . ">" . e($x) . "</option>";
} ?></select>
<label class="oj-chipbox"><input type="checkbox" name="urgent" style="width:auto"<?= $urg
    ? " checked"
    : "" ?>> <span class="material-icons mi">emergency</span> Emergency only</label>
<?php if ($u["role"] == "worker") {
    echo '<label class="oj-chipbox"><input type="checkbox" name="mine" style="width:auto"' .
        ($mine ? " checked" : "") .
        "> My skill only</label>";
} ?>
<button>Search</button></form>

<div class="grid"><?php
$i = 0;
foreach ($s as $j) {
    echo '<div class="card oj-card' .
        ($j["urgent"] ? " oj-urgent" : "") .
        '" style="--i:' .
        $i++ .
        '">' .
        ($j["urgent"]
            ? '<span class="oj-sos"><span class="material-icons mi">emergency</span> EMERGENCY</span>'
            : "") .
        '<p class="oj-title">' .
        e($j["title"]) .
        "</p>" .
        '<span class="oj-cat">' .
        e($j["category"]) .
        "</span>" .
        '<div class="oj-meta"><span class="material-icons mi">place</span> ' .
        e($j["location"]) .
        " · <b>" .
        e($j["amount"]) .
        "</b></div>" .
        '<div class="oj-meta"><span class="material-icons mi">schedule</span> ' .
        date("d M, h:i A", strtotime($j["created"])) .
        "</div>" .
        ($j["slot"]
            ? '<div class="oj-meta"><span class="material-icons mi">calendar_today</span> ' .
                e(date("d M, h:i A", strtotime($j["slot"]))) .
                "</div>"
            : "") .
        '<div class="oj-foot"><span class="oj-offers"><span class="material-icons mi">chat</span> ' .
        $j["oc"] .
        ' offer(s)</span><a class="btn" href="job.php?id=' .
        $j["id"] .
        '">View &amp; send offer</a></div></div>';
}
if (!$tot) {
    echo '<div class="oj-empty"><span class="material-icons mi">search_off</span><p>No open jobs match your search.</p></div>';
}
?></div>
<?php
if ($tot > $per) {
    echo '<p class="oj-pager">';
    if ($p > 1) {
        echo '<a class="btn" href="?' .
            e(http_build_query(array_merge($_GET, ["p" => $p - 1]))) .
            '">← Previous</a> ';
    }
    echo '<span class="oj-page">Page ' . $p . " of " . ceil($tot / $per) . "</span>";
    if ($p * $per < $tot) {
        echo ' <a class="btn" href="?' .
            e(http_build_query(array_merge($_GET, ["p" => $p + 1]))) .
            '">Next →</a>';
    }
    echo "</p>";
}
?>
</div>
<?php foot();
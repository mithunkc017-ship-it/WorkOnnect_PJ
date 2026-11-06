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
<h2><span class="material-icons mi">lock_open</span> Open Jobs <small>(<?= $tot ?>)</small></h2>
<form method="get" style="display:flex;gap:8px;flex-wrap:wrap"><input name="q" placeholder="Search title or location" value="<?= e(
    $q,
) ?>">
<select name="cat"><option value="">All categories</option><?php foreach (
    $CATS
    as $x
) {
    echo "<option" . ($c == $x ? " selected" : "") . ">" . e($x) . "</option>";
} ?></select>
<label style="white-space:nowrap"><input type="checkbox" name="urgent" style="width:auto"<?= $urg
    ? " checked"
    : "" ?>> <span class="material-icons mi">emergency</span> Emergency only</label>
<?php if ($u["role"] == "worker") {
    echo '<label style="white-space:nowrap"><input type="checkbox" name="mine" style="width:auto"' .
        ($mine ? " checked" : "") .
        "> My skill only</label>";
} ?>
<button>Search</button></form>
<div class="grid"><?php
foreach ($s as $j) {
    echo '<div class="card">' .
        ($j["urgent"]
            ? '<span style="background:#c00;color:#fff;padding:2px 8px;border-radius:10px;font-size:12px"><span class="material-icons mi">emergency</span> EMERGENCY</span><br>'
            : "") .
        "<b>" .
        e($j["title"]) .
        "</b> – " .
        e($j["category"]) .
        '<br><span class="material-icons mi">place</span> ' .
        e($j["location"]) .
        " · " .
        e($j["amount"]) .
        '<br><span class="material-icons mi">schedule</span> ' .
        date("d M, h:i A", strtotime($j["created"])) .
        ($j["slot"]
            ? '<br><span class="material-icons mi">calendar_today</span> ' .
                e(date("d M, h:i A", strtotime($j["slot"])))
            : "") .
        '<br><span class="material-icons mi">chat</span> ' .
        $j["oc"] .
        ' offer(s)<br><br><a class="btn" href="job.php?id=' .
        $j["id"] .
        '">View &amp; send offer</a></div>';
}
if (!$tot) {
    echo "<p>No open jobs match your search.</p>";
}
?></div>
<?php
if ($tot > $per) {
    echo "<p>";
    if ($p > 1) {
        echo '<a class="btn" href="?' .
            e(http_build_query(array_merge($_GET, ["p" => $p - 1]))) .
            '">← Previous</a> ';
    }
    echo "Page " . $p . " of " . ceil($tot / $per);
    if ($p * $per < $tot) {
        echo ' <a class="btn" href="?' .
            e(http_build_query(array_merge($_GET, ["p" => $p + 1]))) .
            '">Next →</a>';
    }
    echo "</p>";
}
foot();


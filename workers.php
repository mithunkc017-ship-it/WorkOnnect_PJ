<?php // Search / filter workers by name, skill, availability and distance from the visitor ("Near me")
require "config.php";
$k = "%" . ($_GET["q"] ?? "") . "%";
$c = $_GET["cat"] ?? "";
$ar = trim($_GET["ar"] ?? "");
$la = (float) ($_GET["lat"] ?? 0);
$ln = (float) ($_GET["lng"] ?? 0);
$s = $pdo->prepare(
    "SELECT u.*,(SELECT ROUND(AVG(stars),1) FROM reviews WHERE worker_id=u.id) r,(SELECT COUNT(*) FROM reviews WHERE worker_id=u.id) rc,(SELECT COUNT(*) FROM jobs WHERE worker_id=u.id AND status='completed') done,
 IF(u.lat IS NULL,NULL,ROUND(6371*ACOS(LEAST(1,COS(RADIANS(?))*COS(RADIANS(u.lat))*COS(RADIANS(u.lng)-RADIANS(?))+SIN(RADIANS(?))*SIN(RADIANS(u.lat)))),1)) dist
 FROM users u WHERE role='worker' AND name LIKE ? AND (?='' OR skill=?) AND (?=0 OR available=1) AND (?='' OR area LIKE ?) ORDER BY " .
        ($la ? "dist IS NULL,dist," : "") .
        "verified DESC,r DESC",
);
$s->execute([
    $la,
    $ln,
    $la,
    $k,
    $c,
    $c,
    isset($_GET["av"]) ? 1 : 0,
    $ar,
    "%" . $ar . "%",
]);
head("Find Workers");
echo d_top("workers.php");
?>
<h2>Find Workers</h2><form method="get" style="display:flex;gap:8px;flex-wrap:wrap"><input name="q" placeholder="Search by name" value="<?= e(
    $_GET["q"] ?? "",
) ?>">
<select name="cat"><option value="">All skills</option><?php foreach (
    $CATS
    as $x
) {
    echo "<option" . ($c == $x ? " selected" : "") . ">" . $x . "</option>";
} ?></select>
<label style="white-space:nowrap"><input type="checkbox" name="av" style="width:auto"> Available only</label>
<input type="hidden" name="lat" id="lat" value="<?= $la ?:
    "" ?>"><input type="hidden" name="lng" id="lng" value="<?= $ln ?: "" ?>">
<input name="ar" placeholder="Area (e.g. Sagar)" value="<?= e(
    $ar,
) ?>"><button>Search</button><button type="button" onclick="navigator.geolocation.getCurrentPosition(p=>{lat.value=p.coords.latitude;lng.value=p.coords.longitude;this.form.submit()})"><span class="material-icons mi">place</span> Near me</button></form>
<div class="grid"><?php foreach ($s as $w) {
    echo '<div class="card"><b>' .
        e($w["name"]) .
        "</b> " .
        badge($w["verified"]) .
        top($w["r"], $w["rc"]) .
        level($w["done"], $w["r"]) .
        "<br>" .
        e($w["skill"]) .
        " · " .
        ($w["available"]
            ? '<span class="dot-green"></span> Available'
            : '<span class="dot-red"></span> Busy') .
        "<br>" .
        ($w["r"]
            ? $w["r"] . ' <span class="material-icons mi-xs">star</span>'
            : "No reviews") .
        "<br>" .
        ($la && $w["dist"] !== null
            ? '<span class="material-icons mi">place</span> ' .
                $w["dist"] .
                " km away<br>"
            : "") .
        '<br><a class="btn" href="worker.php?id=' .
        $w["id"] .
        '">View Profile</a></div>';
} ?></div>
<?php foot();

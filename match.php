<?php // Smart worker match: scores workers by rating, verification, availability, experience and distance
require "config.php";
need("customer");
$c = $_GET["cat"] ?? "";
$la = (float) ($_GET["lat"] ?? 0);
$ln = (float) ($_GET["lng"] ?? 0);
$res = [];
if ($c !== "") {
    $s = $pdo->prepare(
        "SELECT u.*,(SELECT AVG(stars) FROM reviews WHERE worker_id=u.id) r,(SELECT COUNT(*) FROM jobs WHERE worker_id=u.id AND status='completed') done,(SELECT MIN(price) FROM service_prices WHERE worker_id=u.id) minp FROM users u WHERE role='worker' AND skill=?",
    );
    $s->execute([$c]);
    foreach ($s as $w) {
        $d = null;
        if ($la && $w["lat"] !== null) {
            $d =
                6371 *
                acos(
                    min(
                        1,
                        cos(deg2rad($la)) *
                            cos(deg2rad($w["lat"])) *
                            cos(deg2rad($w["lng"]) - deg2rad($ln)) +
                            sin(deg2rad($la)) * sin(deg2rad($w["lat"])),
                    ),
                );
        }
        $w["d"] = $d;
        $w["sc"] = round(
            ($w["r"] ? ($w["r"] / 5) * 40 : 15) +
                ($w["verified"] ? 15 : 0) +
                ($w["available"] ? 15 : 0) +
                (min($w["done"], 20) / 20) * 15 +
                ($d !== null ? max(0, 1 - $d / 25) * 15 : 7),
        );
        $res[] = $w;
    }
    usort($res, function ($a, $b) {
        return $b["sc"] <=> $a["sc"];
    });
    $res = array_slice($res, 0, 5);
}
head("Smart Match");
echo d_top("match.php");
?>
<h2><span class="material-icons mi">gps_fixed</span> Smart Worker Match</h2><form method="get" style="display:flex;gap:8px;flex-wrap:wrap"><select name="cat"><?php foreach (
    $CATS
    as $x
) {
    echo "<option" . ($c == $x ? " selected" : "") . ">" . $x . "</option>";
} ?></select>
<input type="hidden" name="lat" id="lat" value="<?= $la ?:
    "" ?>"><input type="hidden" name="lng" id="lng" value="<?= $ln ?:
    "" ?>"><button>Find best workers</button>
<button type="button" onclick="navigator.geolocation.getCurrentPosition(p=>{lat.value=p.coords.latitude;lng.value=p.coords.longitude;this.form.submit()})"><span class="material-icons mi">place</span> Use my location</button></form>
<div class="grid"><?php
foreach ($res as $w) {
    echo '<div class="card"><b>' .
        e($w["name"]) .
        "</b> " .
        badge($w["verified"]) .
        "<h2>" .
        $w["sc"] .
        "% match</h2>" .
        ($w["r"]
            ? round($w["r"], 1) .
                ' <span class="material-icons mi-xs">star</span>'
            : "No reviews") .
        " · " .
        $w["done"] .
        " jobs<br>" .
        ($w["available"]
            ? '<span class="dot-green"></span> Available'
            : '<span class="dot-red"></span> Busy') .
        ($w["d"] !== null
            ? ' · <span class="material-icons mi">place</span> ' .
                round($w["d"], 1) .
                " km"
            : "") .
        ($w["minp"] ? "<br>From ₹" . $w["minp"] : "") .
        '<br><br><a class="btn" href="hire.php?w=' .
        $w["id"] .
        '">Hire</a> <a class="btn" href="worker.php?id=' .
        $w["id"] .
        '">Profile</a></div>';
}
if ($c !== "" && !$res) {
    echo "<p>No workers found for this skill.</p>";
}
?></div><?php foot();

<?php // Worker edits own profile and availability
require "config.php";
need("worker");
$u = me();
$msg = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $photo = $u["photo"];
    if (
        !empty($_FILES["photo"]["name"]) &&
        preg_match('/\.(jpe?g|png|webp)$/i', $_FILES["photo"]["name"]) &&
        @getimagesize($_FILES["photo"]["tmp_name"]) &&
        $_FILES["photo"]["size"] < 2000000
    ) {
        $photo = "uploads/p" . $u["id"] . "_" . time() . ".jpg";
        move_uploaded_file($_FILES["photo"]["tmp_name"], $photo);
    }
    $pdo->prepare(
        "UPDATE users SET experience=?,bio=?,photo=?,skill=?,available=?,lat=?,lng=?,work_days=?,work_from=?,work_to=?,area=? WHERE id=?",
    )->execute([
        $_POST["experience"],
        $_POST["bio"],
        $photo,
        $_POST["skill"],
        (int) $_POST["available"],
        $_POST["lat"] !== "" ? (float) $_POST["lat"] : null,
        $_POST["lng"] !== "" ? (float) $_POST["lng"] : null,
        implode(",", $_POST["work_days"] ?? []),
        $_POST["work_from"] ?: null,
        $_POST["work_to"] ?: null,
        substr($_POST["area"], 0, 150),
        $u["id"],
    ]);
    $s = $pdo->prepare("SELECT * FROM users WHERE id=?");
    $s->execute([$u["id"]]);
    $_SESSION["u"] = $u = $s->fetch();
    $msg = 'Profile saved <span class="material-icons mi-xs">verified</span>';
}
head("My Profile");
?>
<form class="card" method="post" enctype="multipart/form-data"><h2>My Worker Profile</h2><p class="ok"><?= $msg ?></p>
<select name="skill"><?php foreach ($CATS as $c) {
    echo "<option" .
        ($u["skill"] == $c ? " selected" : "") .
        ">" .
        $c .
        "</option>";
} ?></select>
<select name="available"><option value="1"><span class="dot-green"></span> Available for work</option><option value="0"<?= $u[
    "available"
]
    ? ""
    : " selected" ?>><span class="dot-red"></span> Busy</option></select><input name="experience" placeholder="Experience (e.g. 5 years)" value="<?= e(
    $u["experience"],
) ?>">
<textarea name="bio" rows="3" placeholder="About you and your work"><?= e(
    $u["bio"],
) ?></textarea>
<label>Working days</label><div><?php foreach (
    ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"]
    as $d
) {
    echo '<label style="margin-right:8px"><input type="checkbox" style="width:auto" name="work_days[]" value="' .
        $d .
        '"' .
        (strpos($u["work_days"] ?? "", $d) !== false ? " checked" : "") .
        "> " .
        $d .
        "</label>";
} ?></div>
<label>Working hours</label><div style="display:flex;gap:8px"><input type="time" name="work_from" value="<?= e(
    $u["work_from"],
) ?>"><input type="time" name="work_to" value="<?= e($u["work_to"]) ?>"></div>
<input name="area" placeholder="Service areas (e.g. Sagar, Shimoga)" value="<?= e(
    $u["area"],
) ?>"><label>Profile photo</label><input type="file" name="photo" accept="image/*">
<input type="hidden" name="lat" id="lat" value="<?= e(
    $u["lat"],
) ?>"><input type="hidden" name="lng" id="lng" value="<?= e(
    $u["lng"],
) ?>"><button type="button" onclick="navigator.geolocation.getCurrentPosition(p=>{lat.value=p.coords.latitude;lng.value=p.coords.longitude;alert('Location captured. Now click Save Profile.')})"><span class="material-icons mi">place</span> Save my location</button> <button>Save Profile</button> <a href="worker.php?id=<?= $u[
    "id"
] ?>">View public profile</a> · <a href="portfolio.php">My work</a></form><?php foot();

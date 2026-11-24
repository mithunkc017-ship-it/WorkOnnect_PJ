<?php // Worker: manage portfolio photos and the service price list
require "config.php";
need("worker");
$u = me();
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $a = $_POST["a"] ?? "";
    if (
        $a == "photo" &&
        !empty($_FILES["photo"]["name"]) &&
        ($i = @getimagesize($_FILES["photo"]["tmp_name"])) &&
        $_FILES["photo"]["size"] < 2000000 &&
        in_array($i[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG])
    ) {
        $f =
            "uploads/w" .
            $u["id"] .
            "_" .
            bin2hex(random_bytes(6)) .
            ($i[2] == IMAGETYPE_PNG ? ".png" : ".jpg");
        move_uploaded_file($_FILES["photo"]["tmp_name"], $f);
        $pdo->prepare(
            "INSERT INTO portfolio(worker_id,photo,caption)VALUES(?,?,?)",
        )->execute([$u["id"], $f, substr($_POST["caption"], 0, 100)]);
    }
    if ($a == "delphoto") {
        $pdo->prepare(
            "DELETE FROM portfolio WHERE id=? AND worker_id=?",
        )->execute([(int) $_POST["id"], $u["id"]]);
    }
    if ($a == "price" && (int) $_POST["price"] > 0) {
        $pdo->prepare(
            "INSERT INTO service_prices(worker_id,service,price)VALUES(?,?,?)",
        )->execute([
            $u["id"],
            substr($_POST["service"], 0, 60),
            (int) $_POST["price"],
        ]);
    }
    if ($a == "delprice") {
        $pdo->prepare(
            "DELETE FROM service_prices WHERE id=? AND worker_id=?",
        )->execute([(int) $_POST["id"], $u["id"]]);
    }
    header("Location: portfolio.php");
    exit();
}
$s = $pdo->prepare("SELECT * FROM service_prices WHERE worker_id=?");
$s->execute([$u["id"]]);
$prices = $s->fetchAll();
$s = $pdo->prepare("SELECT * FROM portfolio WHERE worker_id=?");
$s->execute([$u["id"]]);
$photos = $s->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>My Work</title>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
<style>
*{box-sizing:border-box}
:root{--ac:#2563eb;--ac2:#1d4ed8;--ink:#1f2937;--mute:#6b7280;--line:#e5e7eb;--bg:#fff;--soft:#f8fafc;--page:#f1f5f9}
@media (prefers-color-scheme:dark){:root{--ink:#e5e7eb;--mute:#9ca3af;--line:#334155;--bg:#1e293b;--soft:#172033;--page:#0f172a}}
body{margin:0;padding:72px 16px 48px;font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;
  background:var(--page);color:var(--ink)}
.material-icons{font-size:20px;vertical-align:middle}

.back{position:fixed;top:16px;left:16px;z-index:50;display:inline-flex;align-items:center;gap:4px;
  background:var(--ac);color:#fff;text-decoration:none;font-size:14px;font-weight:600;
  padding:9px 16px 9px 10px;border-radius:999px;box-shadow:0 6px 16px rgba(37,99,235,.35);transition:.25s}
.back:hover{background:var(--ac2);transform:translateX(-3px)}
.back .material-icons{font-size:18px}

.wrap{max-width:860px;margin:0 auto}
.hero{background:linear-gradient(135deg,#1d4ed8,#2563eb 60%,#3b82f6);color:#fff;border-radius:20px;
  padding:26px 28px;display:flex;align-items:center;gap:16px;margin-bottom:22px;animation:up .5s ease both}
.hero .ic{width:56px;height:56px;border-radius:16px;background:rgba(255,255,255,.18);display:grid;place-items:center;flex:none}
.hero .ic .material-icons{font-size:30px}
.hero h1{margin:0;font-size:24px}
.hero p{margin:3px 0 0;opacity:.9;font-size:14px}

.card{background:var(--bg);border:1px solid var(--line);border-radius:18px;padding:22px 24px;
  box-shadow:0 4px 18px rgba(15,23,42,.06);margin-bottom:22px;animation:up .6s .1s ease both}
.card h2{margin:0 0 4px;font-size:18px;display:flex;align-items:center;gap:8px}
.card h2 .material-icons{color:var(--ac)}
.sub{margin:0 0 16px;color:var(--mute);font-size:13px}

/* forms */
.add{display:flex;gap:10px;flex-wrap:wrap;align-items:stretch;margin-bottom:16px}
.add input[type=text],.add input[type=number],.add input:not([type]){
  flex:1 1 160px;min-width:0;height:44px;padding:0 14px;font-size:14px;font-family:inherit;
  color:var(--ink);background:var(--bg);border:1px solid var(--line);border-radius:12px;transition:.2s}
.add input:focus{outline:0;border-color:var(--ac);box-shadow:0 0 0 3px rgba(37,99,235,.15)}
.add input.pr{flex:0 1 130px}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;height:44px;padding:0 20px;border:0;
  border-radius:12px;background:var(--ac);color:#fff;font-size:14px;font-weight:600;cursor:pointer;
  font-family:inherit;transition:background .2s,transform .15s}
.btn:hover{background:var(--ac2)}
.btn:active{transform:scale(.97)}
.btn .material-icons{font-size:18px}
.btn.sm{height:36px;padding:0 12px;font-size:13px;border-radius:10px}

/* file picker */
.file{flex:1 1 220px;display:flex;align-items:center;gap:10px;height:44px;padding:0 14px;cursor:pointer;
  border:2px dashed #93b4f5;border-radius:12px;background:var(--soft);color:var(--mute);font-size:14px;
  overflow:hidden;transition:.2s}
.file:hover{border-color:var(--ac);background:rgba(37,99,235,.06)}
.file .material-icons{color:var(--ac)}
.file span.n{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.file input{display:none}

/* price list */
.plist{list-style:none;margin:0;padding:0;display:grid;gap:10px}
.pitem{display:flex;align-items:center;gap:12px;padding:12px 14px;border:1px solid var(--line);
  border-radius:14px;background:var(--soft);transition:.2s;animation:up .4s ease both}
.pitem:hover{border-color:var(--ac);transform:translateY(-2px);box-shadow:0 6px 16px rgba(37,99,235,.1)}
.pitem .ico{width:38px;height:38px;border-radius:10px;background:rgba(37,99,235,.1);color:var(--ac);display:grid;place-items:center;flex:none}
.pitem .nm{flex:1;font-weight:600;font-size:15px;min-width:0;overflow-wrap:anywhere}
.pitem .pr{font-weight:700;color:var(--ac);background:rgba(37,99,235,.1);padding:6px 12px;border-radius:999px;font-size:14px;white-space:nowrap}
.pitem form{margin:0}

/* portfolio grid */
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:16px}
.ph{border:1px solid var(--line);border-radius:16px;overflow:hidden;background:var(--bg);
  transition:.25s;animation:up .45s ease both}
.ph:hover{transform:translateY(-4px);box-shadow:0 12px 26px rgba(15,23,42,.14)}
.ph .im{aspect-ratio:4/3;background:var(--soft);overflow:hidden}
.ph img{width:100%;height:100%;object-fit:cover;display:block;transition:transform .4s;cursor:zoom-in}
.ph:hover img{transform:scale(1.06)}
.ph .bd{padding:12px 14px;display:flex;align-items:center;gap:10px}
.ph .cap{flex:1;font-size:14px;min-width:0;overflow-wrap:anywhere}
.ph .cap.no{color:var(--mute);font-style:italic}
.ph form{margin:0}

.empty{text-align:center;padding:26px 10px;color:var(--mute);font-size:14px;border:2px dashed var(--line);border-radius:14px}
.empty .material-icons{font-size:44px;color:var(--ac);opacity:.7;display:block;margin:0 auto 6px}

/* lightbox */
.lb{position:fixed;inset:0;z-index:100;background:rgba(15,23,42,.85);display:none;align-items:center;justify-content:center;padding:20px}
.lb.open{display:flex;animation:fade .2s ease both}
.lb img{max-width:100%;max-height:90vh;border-radius:12px;box-shadow:0 20px 50px rgba(0,0,0,.5)}

@keyframes up{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:none}}
@keyframes fade{from{opacity:0}to{opacity:1}}
@media (max-width:560px){
  .hero{padding:20px}.card{padding:18px}
  .add .btn{flex:1 1 100%}
  .add input.pr{flex:1 1 100%}
}
@media (prefers-reduced-motion:reduce){*{animation:none!important;transition:none!important}}
</style>
</head>
<body>

<a class="back" href="javascript:history.back()"><span class="material-icons">arrow_back</span> Back</a>

<div class="wrap">
  <div class="hero">
    <div class="ic"><span class="material-icons">work</span></div>
    <div>
      <h1>My Work</h1>
      <p>Show your prices and past jobs so customers can trust you and book you.</p>
    </div>
  </div>

  <!-- Price list -->
  <div class="card">
    <h2><span class="material-icons">payments</span> My Price List</h2>
    <p class="sub">Add the services you offer and what you charge.</p>
    <form method="post" class="add">
      <input type="hidden" name="a" value="price">
      <input name="service" placeholder="Service (e.g. Fan repair)" maxlength="60" required>
      <input class="pr" type="number" name="price" min="1" placeholder="₹ price" required>
      <button class="btn"><span class="material-icons">add</span> Add</button>
    </form>

    <?php if ($prices): ?>
    <ul class="plist">
      <?php foreach ($prices as $x): ?>
      <li class="pitem">
        <span class="ico"><span class="material-icons">build</span></span>
        <span class="nm"><?= e($x["service"]) ?></span>
        <span class="pr">₹<?= (int) $x["price"] ?></span>
        <form method="post" onsubmit="return confirm('Delete this service?')">
          <input type="hidden" name="a" value="delprice">
          <input type="hidden" name="id" value="<?= (int) $x["id"] ?>">
          <button class="btn sm"><span class="material-icons">delete</span> Delete</button>
        </form>
      </li>
      <?php endforeach; ?>
    </ul>
    <?php else: ?>
    <div class="empty"><span class="material-icons">sell</span>No services yet. Add your first one above.</div>
    <?php endif; ?>
  </div>

  <!-- Portfolio -->
  <div class="card">
    <h2><span class="material-icons">photo_library</span> My Portfolio</h2>
    <p class="sub">Upload clear photos of your finished work (JPG or PNG, under 2 MB).</p>
    <form method="post" enctype="multipart/form-data" class="add">
      <input type="hidden" name="a" value="photo">
      <label class="file">
        <span class="material-icons">cloud_upload</span>
        <span class="n" id="fn">Choose a photo</span>
        <input type="file" name="photo" id="ph" accept="image/png,image/jpeg" required>
      </label>
      <input name="caption" placeholder="Caption" maxlength="100">
      <button class="btn"><span class="material-icons">upload</span> Upload</button>
    </form>

    <?php if ($photos): ?>
    <div class="grid">
      <?php foreach ($photos as $x): ?>
      <div class="ph">
        <div class="im"><img src="<?= e($x["photo"]) ?>" alt="<?= e($x["caption"]) ?>"></div>
        <div class="bd">
          <span class="cap<?= $x["caption"] ? "" : " no" ?>"><?= $x["caption"] ? e($x["caption"]) : "No caption" ?></span>
          <form method="post" onsubmit="return confirm('Delete this photo?')">
            <input type="hidden" name="a" value="delphoto">
            <input type="hidden" name="id" value="<?= (int) $x["id"] ?>">
            <button class="btn sm"><span class="material-icons">delete</span></button>
          </form>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="empty"><span class="material-icons">add_photo_alternate</span>No photos yet. Upload photos of your past jobs.</div>
    <?php endif; ?>
  </div>
</div>

<div class="lb" id="lb"><img id="lbi" alt=""></div>

<script>
var ph=document.getElementById('ph');
ph.addEventListener('change',function(){
  document.getElementById('fn').textContent=ph.files[0]?ph.files[0].name:'Choose a photo';
});
var lb=document.getElementById('lb'),lbi=document.getElementById('lbi');
document.querySelectorAll('.ph img').forEach(function(im){
  im.addEventListener('click',function(){lbi.src=im.src;lb.classList.add('open')});
});
lb.addEventListener('click',function(){lb.classList.remove('open')});
document.addEventListener('keydown',function(e){if(e.key==='Escape')lb.classList.remove('open')});
</script>
</body>
</html>
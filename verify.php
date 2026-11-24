<?php // Worker uploads ID proof to request verification
require "config.php";
need("worker");
$u = me();
$msg = "";
$r = $pdo->prepare("SELECT verified FROM users WHERE id=?");
$r->execute([$u["id"]]);
$ver = $r->fetchColumn();
$s = $pdo->prepare(
    "SELECT * FROM verifications WHERE user_id=? ORDER BY id DESC LIMIT 1",
);
$s->execute([$u["id"]]);
$v = $s->fetch();
if (
    $_SERVER["REQUEST_METHOD"] == "POST" &&
    !$ver &&
    (!$v || $v["status"] != "pending")
) {
    $f = $_FILES["doc"] ?? null;
    if (
        $f &&
        $f["name"] &&
        ($i = @getimagesize($f["tmp_name"])) &&
        $f["size"] < 3000000 &&
        in_array($i[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG])
    ) {
        $name =
            "uploads/id/" .
            bin2hex(random_bytes(8)) .
            ($i[2] == IMAGETYPE_PNG ? ".png" : ".jpg");
        move_uploaded_file($f["tmp_name"], $name);
        $pdo->prepare(
            "INSERT INTO verifications(user_id,doc)VALUES(?,?)",
        )->execute([$u["id"], $name]);
        foreach ($pdo->query("SELECT id FROM users WHERE role='admin'") as $a) {
            notify(
                $a["id"],
                $u["name"] . " requested verification",
                "verifications.php",
            );
        }
        header("Location: verify.php");
        exit();
    }
    $msg = "Please upload a clear JPG or PNG image under 3 MB.";
}
$pending = $v && $v["status"] == "pending";
$rejected = $v && $v["status"] == "rejected";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Get Verified</title>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;
  background:linear-gradient(120deg,#eaf2ff,#f6f0ff,#e6f7ff,#eaf2ff);
  background-size:300% 300%;animation:bg 14s ease infinite;
  min-height:100vh;padding:20px 16px 40px;color:#1e293b;overflow-x:hidden;position:relative}
@keyframes bg{0%{background-position:0% 50%}50%{background-position:100% 50%}100%{background-position:0% 50%}}

/* floating blobs */
.blob{position:fixed;border-radius:50%;filter:blur(50px);opacity:.35;z-index:0;animation:float 9s ease-in-out infinite}
.blob.b1{width:240px;height:240px;background:#60a5fa;top:-60px;left:-60px}
.blob.b2{width:280px;height:280px;background:#a78bfa;bottom:-80px;right:-80px;animation-delay:-4s}
@keyframes float{0%,100%{transform:translateY(0) scale(1)}50%{transform:translateY(-26px) scale(1.08)}}

.wrap{max-width:560px;margin:auto;position:relative;z-index:1}

/* top bar: back button on the right corner */
.topbar{position:fixed;top:16px;left:16px;z-index:10;
  animation:fadeDown .6s ease both}
.back{display:inline-flex;align-items:center;gap:4px;background:#2563eb;color:#fff;
  text-decoration:none;font-size:14px;font-weight:600;padding:9px 16px 9px 10px;
  border-radius:999px;box-shadow:0 6px 16px rgba(37,99,235,.35);transition:.25s}
.back:hover{background:#1d4ed8;transform:translateX(-3px)}
.back .material-icons{font-size:18px;transition:.25s}
.back:hover .material-icons{transform:translateX(-3px)}

.hero{background:linear-gradient(135deg,#1d4ed8,#2563eb 60%,#3b82f6);color:#fff;
  border-radius:20px 20px 0 0;padding:32px 24px;text-align:center;
  position:relative;overflow:hidden;animation:fadeUp .7s ease both}
.hero::after{content:"";position:absolute;top:0;left:-60%;width:40%;height:100%;
  background:linear-gradient(90deg,transparent,rgba(255,255,255,.22),transparent);
  transform:skewX(-20deg);animation:shine 4.5s ease-in-out infinite}
@keyframes shine{0%{left:-60%}60%,100%{left:130%}}
.hero .ic{width:76px;height:76px;border-radius:50%;background:rgba(255,255,255,.18);
  display:flex;align-items:center;justify-content:center;margin:0 auto 14px;
  position:relative;animation:bob 3s ease-in-out infinite}
.hero .ic::before{content:"";position:absolute;inset:0;border-radius:50%;
  border:2px solid rgba(255,255,255,.6);animation:ring 2.2s ease-out infinite}
.hero .ic .material-icons{font-size:44px}
@keyframes ring{0%{transform:scale(1);opacity:.9}100%{transform:scale(1.7);opacity:0}}
@keyframes bob{0%,100%{transform:translateY(0)}50%{transform:translateY(-7px)}}
.hero h1{font-size:26px;margin-bottom:6px}
.hero p{opacity:.9;font-size:15px}

.card{background:#fff;border-radius:0 0 20px 20px;padding:24px;
  box-shadow:0 12px 32px rgba(30,64,175,.14);animation:fadeUp .8s .15s ease both}
h3{font-size:16px;margin:0 0 12px}

.benefits{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:22px}
.b{background:#f1f6ff;border-radius:12px;padding:12px;display:flex;gap:10px;
  align-items:flex-start;font-size:13px;transition:.25s;
  opacity:0;animation:pop .5s ease forwards}
.b:nth-child(1){animation-delay:.35s}.b:nth-child(2){animation-delay:.5s}
.b:nth-child(3){animation-delay:.65s}.b:nth-child(4){animation-delay:.8s}
.b:hover{transform:translateY(-4px);box-shadow:0 8px 18px rgba(37,99,235,.18);background:#e6efff}
.b .material-icons{color:#2563eb;font-size:22px}
.b strong{display:block;font-size:14px;margin-bottom:2px}
@keyframes pop{from{opacity:0;transform:scale(.85) translateY(10px)}to{opacity:1;transform:none}}

.steps{list-style:none;margin-bottom:22px}
.steps li{display:flex;gap:12px;align-items:flex-start;padding:10px 0;font-size:14px;
  opacity:0;animation:slideIn .55s ease forwards}
.steps li:nth-child(1){animation-delay:.9s}.steps li:nth-child(2){animation-delay:1.05s}
.steps li:nth-child(3){animation-delay:1.2s}
.steps .n{flex:0 0 28px;height:28px;border-radius:50%;background:#2563eb;color:#fff;
  font-weight:700;font-size:14px;display:flex;align-items:center;justify-content:center;
  animation:pulse 2s ease-in-out infinite}
@keyframes slideIn{from{opacity:0;transform:translateX(-24px)}to{opacity:1;transform:none}}
@keyframes pulse{0%,100%{box-shadow:0 0 0 0 rgba(37,99,235,.45)}50%{box-shadow:0 0 0 8px rgba(37,99,235,0)}}

.tips{background:#fff8e6;border-left:4px solid #f59e0b;border-radius:8px;
  padding:12px 14px;font-size:13px;margin-bottom:22px;
  opacity:0;animation:fadeUp .6s 1.35s ease forwards}
.tips ul{margin:6px 0 0 18px}

.drop{display:block;border:2px dashed #93b4f5;border-radius:16px;background:#f8fbff;
  text-align:center;padding:26px 16px;cursor:pointer;transition:.25s;
  animation:dash 1.6s linear infinite}
.drop:hover,.drop.over{background:#eef4ff;border-color:#2563eb;transform:scale(1.02)}
.drop .material-icons{font-size:44px;color:#2563eb;animation:hop 1.8s ease-in-out infinite}
.drop span.t{display:block;font-weight:600;margin-top:4px}
.drop span.s{font-size:12px;color:#64748b}
.drop input{display:none}
@keyframes hop{0%,100%{transform:translateY(0)}50%{transform:translateY(-8px)}}
@keyframes dash{0%,100%{border-color:#93b4f5}50%{border-color:#2563eb}}
#preview{display:none;max-width:100%;max-height:220px;border-radius:12px;margin:14px auto 0;
  animation:pop .5s ease both;box-shadow:0 8px 20px rgba(0,0,0,.15)}
#fname{font-size:13px;color:#2563eb;margin-top:8px;word-break:break-all}

.btn{width:100%;margin-top:16px;padding:14px;border:0;border-radius:12px;
  background:#2563eb;color:#fff;font-size:16px;font-weight:600;cursor:pointer;
  display:flex;align-items:center;justify-content:center;gap:8px;
  position:relative;overflow:hidden;transition:.25s;box-shadow:0 8px 20px rgba(37,99,235,.35)}
.btn::after{content:"";position:absolute;top:0;left:-70%;width:40%;height:100%;
  background:linear-gradient(90deg,transparent,rgba(255,255,255,.35),transparent);
  transform:skewX(-20deg);animation:shine 3s ease-in-out infinite}
.btn:hover{background:#1d4ed8;transform:translateY(-2px)}
.btn:active{transform:scale(.98)}
.btn .material-icons{transition:.3s}
.btn:hover .material-icons{transform:translateX(5px) rotate(-15deg)}

.err{background:#fef2f2;color:#b91c1c;border-radius:10px;padding:10px 14px;
  font-size:14px;margin:0 0 16px;animation:shake .5s ease}
@keyframes shake{0%,100%{transform:translateX(0)}20%,60%{transform:translateX(-7px)}40%,80%{transform:translateX(7px)}}

.status{text-align:center;padding:10px 0}
.status .material-icons{font-size:72px}
.status h2{margin:8px 0 6px}
.status p{color:#475569;font-size:15px}
.ok .material-icons{color:#16a34a;animation:tick .8s cubic-bezier(.2,1.4,.4,1) both}
.wait .material-icons{color:#f59e0b;animation:spin 3s ease-in-out infinite}
@keyframes tick{from{transform:scale(0) rotate(-90deg)}to{transform:scale(1) rotate(0)}}
@keyframes spin{0%{transform:rotate(0)}50%{transform:rotate(180deg)}100%{transform:rotate(180deg)}}

.lock{display:flex;gap:8px;align-items:center;justify-content:center;
  font-size:12px;color:#64748b;margin-top:18px}
.lock .material-icons{font-size:16px}

@keyframes fadeUp{from{opacity:0;transform:translateY(28px)}to{opacity:1;transform:none}}
@keyframes fadeDown{from{opacity:0;transform:translateY(-16px)}to{opacity:1;transform:none}}
@media(max-width:480px){.benefits{grid-template-columns:1fr}}
@media(prefers-reduced-motion:reduce){*{animation:none!important;transition:none!important;opacity:1!important}}
</style>
</head>
<body>
<div class="blob b1"></div><div class="blob b2"></div>

<div class="blob b1"></div><div class="blob b2"></div>

<div class="topbar">
  <a class="back" href="javascript:history.back()"><span class="material-icons">arrow_back</span> Back</a>
</div>

<div class="wrap">

  <div class="hero">
    <div class="ic"><span class="material-icons">verified_user</span></div>
    <h1>Get Verified</h1>
    <p>Earn the trusted badge and win more work</p>
  </div>

  <div class="card">
  <?php if ($ver): ?>
    <div class="status ok">
      <span class="material-icons">check_circle</span>
      <h2>You are verified!</h2>
      <p><?= badge(1) ?> Customers can now see your trusted badge.</p>
    </div>

  <?php elseif ($pending): ?>
    <div class="status wait">
      <span class="material-icons">hourglass_top</span>
      <h2>Request pending</h2>
      <p>Your ID has been submitted. The admin will review it soon, and you will get a notification.</p>
    </div>

  <?php else: ?>
    <?php if ($rejected): ?>
      <div class="err"><strong>Rejected:</strong> <?= e($v["note"]) ?>. You can apply again below.</div>
    <?php endif; ?>

    <h3>Why get verified?</h3>
    <div class="benefits">
      <div class="b"><span class="material-icons">verified</span><div><strong>Trusted badge</strong>Shown on your profile</div></div>
      <div class="b"><span class="material-icons">trending_up</span><div><strong>More bookings</strong>Customers prefer verified workers</div></div>
      <div class="b"><span class="material-icons">star</span><div><strong>Better ranking</strong>Stand out from others</div></div>
      <div class="b"><span class="material-icons">handshake</span><div><strong>Customer trust</strong>Safe and reliable work</div></div>
    </div>

    <h3>How it works</h3>
    <ul class="steps">
      <li><span class="n">1</span><div>Take a clear photo of a valid ID proof (Aadhaar, Voter ID, Driving Licence).</div></li>
      <li><span class="n">2</span><div>Upload the photo below and tap <b>Submit for Verification</b>.</div></li>
      <li><span class="n">3</span><div>The admin checks it and your badge appears on your profile.</div></li>
    </ul>

    <div class="tips">
      <strong>Photo tips</strong>
      <ul>
        <li>All four corners and the text must be visible</li>
        <li>Good light, no blur or glare</li>
        <li>JPG or PNG only, under 3 MB</li>
      </ul>
    </div>

    <?php if ($msg): ?><div class="err"><?= e($msg) ?></div><?php endif; ?>

    <form method="post" enctype="multipart/form-data">
      <label class="drop" id="drop" for="doc">
        <span class="material-icons">cloud_upload</span>
        <span class="t">Tap to choose your ID photo</span>
        <span class="s">JPG or PNG, max 3 MB</span>
        <input type="file" name="doc" id="doc" accept="image/png,image/jpeg" required>
      </label>
      <div style="text-align:center">
        <div id="fname"></div>
        <img id="preview" alt="Preview">
      </div>
      <button class="btn"><span class="material-icons">send</span> Submit for Verification</button>
    </form>

    <div class="lock"><span class="material-icons">lock</span> Only the admin can see your ID. It is used only to verify you.</div>
  <?php endif; ?>
  </div>
</div>

<script>
const inp=document.getElementById('doc'),drop=document.getElementById('drop');
if(inp){
  inp.addEventListener('change',()=>{
    const f=inp.files[0],p=document.getElementById('preview');
    if(!f)return;
    document.getElementById('fname').textContent=f.name;
    p.src=URL.createObjectURL(f);p.style.display='block';
  });
  ['dragenter','dragover'].forEach(e=>drop.addEventListener(e,ev=>{ev.preventDefault();drop.classList.add('over')}));
  ['dragleave','drop'].forEach(e=>drop.addEventListener(e,()=>drop.classList.remove('over')));
}
</script>
</body>
</html>
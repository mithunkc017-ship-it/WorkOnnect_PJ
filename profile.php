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
$ph = $u["photo"] ?? "";
$initial = e(mb_strtoupper(mb_substr((string) ($u["name"] ?? "?"), 0, 1)));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>My Profile</title>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
<style>
*{box-sizing:border-box}
body{margin:0;padding:72px 16px 40px;font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;
  background:#f1f5f9;color:#1f2937}
@media (prefers-color-scheme:dark){body{background:#0f172a;color:#e5e7eb}}
.material-icons.mi{font-size:20px;vertical-align:middle}
.mi-xs{font-size:16px;vertical-align:middle}

/* Back button: fixed at the left edge of the screen */
.pf-back{position:fixed;top:16px;left:16px;z-index:50;display:inline-flex;align-items:center;gap:4px;
  background:#2563eb;color:#fff;text-decoration:none;font-size:14px;font-weight:600;
  padding:9px 16px 9px 10px;border-radius:999px;box-shadow:0 6px 16px rgba(37,99,235,.35);transition:.25s}
.pf-back:hover{background:#1d4ed8;transform:translateX(-3px)}
.pf-back .material-icons{font-size:18px}

.pf{--pf-accent:#2563eb;--pf-ink:#1f2937;--pf-mute:#6b7280;--pf-line:#e5e7eb;--pf-bg:#fff;--pf-soft:#f8fafc;--pf-page:transparent}
@media (prefers-color-scheme:dark){.pf{--pf-ink:#e5e7eb;--pf-mute:#9ca3af;--pf-line:#334155;--pf-bg:#1e293b;--pf-soft:#172033}}

.pf{max-width:760px;margin:0 auto}
.pf-card{background:var(--pf-bg)!important;color:var(--pf-ink);border:1px solid var(--pf-line);border-radius:16px;
  padding:0!important;overflow:hidden;box-shadow:0 4px 18px rgba(15,23,42,.06);animation:pf-fade .4s ease both}

/* Header */
.pf-top{display:flex;align-items:center;gap:16px;padding:24px 28px;border-bottom:1px solid var(--pf-line);background:var(--pf-soft)}
.pf-av{width:64px;height:64px;border-radius:50%;flex:none;display:grid;place-items:center;overflow:hidden;
  background:var(--pf-accent);color:#fff;font:700 26px system-ui,sans-serif}
.pf-av img{width:100%;height:100%;object-fit:cover;display:block}
.pf-top h2{margin:0;font-size:22px;color:var(--pf-ink)}
.pf-top p{margin:2px 0 0;color:var(--pf-mute);font-size:14px}

/* Saved message */
.pf-msg{margin:18px 28px 0!important;padding:10px 14px;border-radius:10px;background:#ecfdf5;color:#047857!important;
  border:1px solid #a7f3d0;font-size:14px;font-weight:600;display:flex;align-items:center;gap:6px}

/* Sections */
.pf-sec{padding:22px 28px;border-bottom:1px solid var(--pf-line)}
.pf-sec:last-of-type{border-bottom:0}
.pf-sec h4{margin:0 0 14px;font-size:13px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--pf-mute);
  display:flex;align-items:center;gap:6px}
.pf-sec h4 .mi{font-size:18px;color:var(--pf-accent)}
.pf-row{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.pf-field{display:flex;flex-direction:column;gap:6px;margin-bottom:14px}
.pf-field:last-child{margin-bottom:0}
.pf-lab{font-size:13px;font-weight:600;color:var(--pf-ink)}

/* Inputs */
.pf input[type=text],.pf input:not([type]),.pf input[type=time],.pf input[type=file],.pf select,.pf textarea{
  width:100%;margin:0;box-sizing:border-box;padding:10px 12px;font-size:14px;color:var(--pf-ink);background:var(--pf-bg);
  border:1px solid var(--pf-line);border-radius:10px;transition:border-color .2s,box-shadow .2s;font-family:inherit}
.pf textarea{resize:vertical;min-height:90px;line-height:1.5}
.pf select{height:42px}
.pf input:focus,.pf select:focus,.pf textarea:focus{outline:0;border-color:var(--pf-accent);box-shadow:0 0 0 3px rgba(37,99,235,.15)}
.pf input[type=file]{padding:8px;background:var(--pf-soft);cursor:pointer}
.pf-hours{display:flex;gap:10px;align-items:center}
.pf-hours>span{color:var(--pf-mute);font-size:13px}

/* Time field that opens the clock */
.pf-t{position:relative;flex:1}
.pf-t input[type=time]{padding-right:40px;cursor:pointer}
.pf-t input[type=time]::-webkit-calendar-picker-indicator{display:none}
.pf-t-ic{position:absolute;right:11px;top:50%;transform:translateY(-50%);color:var(--pf-accent);pointer-events:none;font-size:20px}

/* Working-day chips (checkboxes stay real inputs) */
.pf-days{display:flex;flex-wrap:wrap;gap:8px}
.pf-day{position:relative;margin:0!important;padding:8px 14px;border:1px solid var(--pf-line);border-radius:999px;background:var(--pf-soft);
  color:var(--pf-ink);font-size:13px;font-weight:600;cursor:pointer;user-select:none;transition:background .2s,border-color .2s,color .2s}
.pf-day input{position:absolute;opacity:0;width:0!important;height:0;margin:0}
.pf-day:hover{border-color:var(--pf-accent)}
.pf-day:has(input:checked){background:var(--pf-accent);border-color:var(--pf-accent);color:#fff}
.pf-day:has(input:focus-visible){box-shadow:0 0 0 3px rgba(37,99,235,.3)}

/* Actions */
.pf-actions{display:flex;flex-wrap:wrap;align-items:center;gap:10px;padding:20px 28px;background:var(--pf-soft);border-top:1px solid var(--pf-line)}
.pf .pf-btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;height:42px;margin:0;padding:0 18px;
  font-size:14px;font-weight:600;line-height:1;border-radius:10px;cursor:pointer;text-decoration:none;box-sizing:border-box;
  transition:background .2s,border-color .2s,transform .15s}
.pf .pf-btn .mi{font-size:18px}
.pf .pf-primary{background:var(--pf-accent);color:#fff;border:1px solid var(--pf-accent);box-shadow:none}
.pf .pf-primary:hover{background:#1d4ed8}
.pf .pf-ghost{background:var(--pf-bg);color:var(--pf-ink);border:1px solid var(--pf-line);box-shadow:none}
.pf .pf-ghost:hover{border-color:var(--pf-accent);color:var(--pf-accent)}
.pf .pf-btn:active{transform:scale(.97)}

/* Quick-link tiles */
.pf-tiles{display:grid;grid-template-columns:1fr 1fr;gap:14px;padding:20px 28px 0}
.pf-tile{display:flex;align-items:center;gap:12px;padding:14px 16px;border:1px solid var(--pf-line);border-radius:12px;
  background:var(--pf-bg);color:var(--pf-ink);text-decoration:none;transition:border-color .2s,box-shadow .2s,transform .2s}
.pf-tile:hover{border-color:var(--pf-accent);box-shadow:0 6px 16px rgba(37,99,235,.1);transform:translateY(-2px)}
.pf-tile-ico{flex:none;width:40px;height:40px;border-radius:10px;display:grid;place-items:center;
  background:rgba(37,99,235,.1);color:var(--pf-accent)}
.pf-tile b{display:block;font-size:14px}
.pf-tile small{color:var(--pf-mute);font-size:12px}
.pf-tile .pf-go{margin-left:auto;color:var(--pf-mute);font-size:20px;transition:transform .2s,color .2s}
.pf-tile:hover .pf-go{transform:translateX(3px);color:var(--pf-accent)}

/* ---------- Clock picker ---------- */
.pk-ov{position:fixed;inset:0;z-index:100;background:rgba(15,23,42,.55);display:none;align-items:center;justify-content:center;padding:16px}
.pk-ov.open{display:flex;animation:pk-in .2s ease both}
.pk{width:100%;max-width:310px;background:var(--pf-bg);color:var(--pf-ink);border-radius:20px;overflow:hidden;
  box-shadow:0 20px 50px rgba(0,0,0,.35)}
.pk-head{background:var(--pf-accent);color:#fff;padding:18px 20px;display:flex;align-items:center;justify-content:center;gap:10px}
.pk-seg{font-size:46px;font-weight:600;line-height:1;opacity:.6;cursor:pointer;border:0;background:none;color:#fff;padding:2px 6px;border-radius:8px}
.pk-seg.on{opacity:1;background:rgba(255,255,255,.18)}
.pk-colon{font-size:42px;font-weight:600;opacity:.8}
.pk-ap{display:flex;flex-direction:column;gap:4px;margin-left:6px}
.pk-ap button{border:0;background:none;color:#fff;opacity:.6;font-size:14px;font-weight:700;cursor:pointer;padding:4px 8px;border-radius:6px}
.pk-ap button.on{opacity:1;background:rgba(255,255,255,.22)}
.pk-dial{padding:18px 0 6px;display:flex;justify-content:center}
.pk-dial svg{width:260px;height:260px;touch-action:none;cursor:pointer}
.pk-face{fill:var(--pf-soft)}
.pk-num{font:500 15px system-ui,sans-serif;fill:var(--pf-ink);text-anchor:middle;dominant-baseline:central;pointer-events:none;user-select:none}
.pk-num.sel{fill:#fff}
.pk-hand{stroke:var(--pf-accent);stroke-width:2}
.pk-knob{fill:var(--pf-accent)}
.pk-foot{display:flex;gap:8px;padding:10px 14px 16px}
.pk-foot button{height:40px;border-radius:10px;font-size:14px;font-weight:600;cursor:pointer;border:1px solid var(--pf-line);
  background:var(--pf-bg);color:var(--pf-ink);padding:0 14px}
.pk-foot .pk-ok{margin-left:auto;background:var(--pf-accent);border-color:var(--pf-accent);color:#fff;padding:0 22px}
.pk-foot .pk-ok:hover{background:#1d4ed8}
@keyframes pk-in{from{opacity:0}to{opacity:1}}

@keyframes pf-fade{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}
@media (max-width:600px){
  .pf-tiles{grid-template-columns:1fr;padding:16px 18px 0}
  .pf-row{grid-template-columns:1fr}
  .pf-top,.pf-sec,.pf-actions{padding-left:18px;padding-right:18px}
  .pf-msg{margin-left:18px!important;margin-right:18px!important}
  .pf .pf-btn{flex:1 1 100%}
}
@media (prefers-reduced-motion:reduce){.pf *,.pk-ov{animation:none!important;transition:none!important}}
</style>
</head>
<body>

<a class="pf-back" href="javascript:history.back()"><span class="material-icons">arrow_back</span> Back</a>

<div class="pf">
<form class="card pf-card" method="post" enctype="multipart/form-data">
  <div class="pf-top">
    <div class="pf-av"><?= $ph ? '<img src="' . e($ph) . '" alt="">' : $initial ?></div>
    <div>
      <h2>My Worker Profile</h2>
      <p>Keep your details up to date so customers can find and trust you.</p>
    </div>
  </div>
  <div class="pf-tiles">
    <a class="pf-tile" href="worker.php?id=<?= $u["id"] ?>">
      <span class="pf-tile-ico"><span class="material-icons mi">person</span></span>
      <span><b>View public profile</b><small>See what customers see</small></span>
      <span class="material-icons mi pf-go">arrow_forward</span>
    </a>
    <a class="pf-tile" href="portfolio.php">
      <span class="pf-tile-ico"><span class="material-icons mi">photo_library</span></span>
      <span><b>My work</b><small>Photos of your past jobs</small></span>
      <span class="material-icons mi pf-go">arrow_forward</span>
    </a>
  </div>

  <?php if ($msg): ?><p class="ok pf-msg"><?= $msg ?></p><?php endif; ?>

  <div class="pf-sec">
    <h4><span class="material-icons mi">work</span> Work details</h4>
    <div class="pf-row">
      <div class="pf-field">
        <span class="pf-lab">Skill</span>
        <select name="skill"><?php foreach ($CATS as $c) {
            echo "<option" .
                ($u["skill"] == $c ? " selected" : "") .
                ">" .
                $c .
                "</option>";
        } ?></select>
      </div>
      <div class="pf-field">
        <span class="pf-lab">Availability</span>
        <select name="available"><option value="1">Available for work</option><option value="0"<?= $u[
            "available"
        ]
            ? ""
            : " selected" ?>>Busy</option></select>
      </div>
    </div>
    <div class="pf-field">
      <span class="pf-lab">Experience</span>
      <input name="experience" placeholder="Experience (e.g. 5 years)" value="<?= e($u["experience"]) ?>">
    </div>
    <div class="pf-field">
      <span class="pf-lab">About you</span>
      <textarea name="bio" rows="3" placeholder="About you and your work"><?= e($u["bio"]) ?></textarea>
    </div>
  </div>

  <div class="pf-sec">
    <h4><span class="material-icons mi">calendar_today</span> Schedule</h4>
    <div class="pf-field">
      <span class="pf-lab">Working days</span>
      <div class="pf-days"><?php foreach (
          ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"]
          as $d
      ) {
          echo '<label class="pf-day"><input type="checkbox" style="width:auto" name="work_days[]" value="' .
              $d .
              '"' .
              (strpos($u["work_days"] ?? "", $d) !== false ? " checked" : "") .
              "> " .
              $d .
              "</label>";
      } ?></div>
    </div>
    <div class="pf-field">
      <span class="pf-lab">Working hours <small style="font-weight:400;color:var(--pf-mute)">(tap to set time)</small></span>
      <div class="pf-hours">
        <div class="pf-t"><input type="time" name="work_from" value="<?= e($u["work_from"]) ?>"><span class="material-icons pf-t-ic">schedule</span></div>
        <span>to</span>
        <div class="pf-t"><input type="time" name="work_to" value="<?= e($u["work_to"]) ?>"><span class="material-icons pf-t-ic">schedule</span></div>
      </div>
    </div>
  </div>

  <div class="pf-sec">
    <h4><span class="material-icons mi">place</span> Area &amp; photo</h4>
    <div class="pf-field">
      <span class="pf-lab">Service areas</span>
      <input name="area" placeholder="Service areas (e.g. Sagar, Shimoga)" value="<?= e($u["area"]) ?>">
    </div>
    <div class="pf-field">
      <span class="pf-lab">Profile photo</span>
      <input type="file" name="photo" accept="image/*">
    </div>
  </div>

  <input type="hidden" name="lat" id="lat" value="<?= e($u["lat"]) ?>"><input type="hidden" name="lng" id="lng" value="<?= e($u["lng"]) ?>">

  <div class="pf-actions">
    <button type="button" class="pf-btn pf-ghost" onclick="navigator.geolocation.getCurrentPosition(p=>{lat.value=p.coords.latitude;lng.value=p.coords.longitude;alert('Location captured. Now click Save Profile.')})"><span class="material-icons mi">place</span> Save my location</button>
    <button class="pf-btn pf-primary"><span class="material-icons mi">check</span> Save Profile</button>
  </div>
</form>

<!-- Clock picker popup -->
<div class="pk-ov" id="pkOv">
  <div class="pk" role="dialog" aria-label="Choose time">
    <div class="pk-head">
      <button type="button" class="pk-seg on" id="pkH">09</button>
      <span class="pk-colon">:</span>
      <button type="button" class="pk-seg" id="pkM">00</button>
      <div class="pk-ap">
        <button type="button" id="pkAM" class="on">AM</button>
        <button type="button" id="pkPM">PM</button>
      </div>
    </div>
    <div class="pk-dial"><svg id="pkSvg" viewBox="0 0 260 260"></svg></div>
    <div class="pk-foot">
      <button type="button" id="pkClear">Clear</button>
      <button type="button" id="pkCancel">Cancel</button>
      <button type="button" class="pk-ok" id="pkOk">OK</button>
    </div>
  </div>
</div>
</div>

<script>
(function(){
  var ov=document.getElementById('pkOv'),svg=document.getElementById('pkSvg'),
      bH=document.getElementById('pkH'),bM=document.getElementById('pkM'),
      bAM=document.getElementById('pkAM'),bPM=document.getElementById('pkPM');
  var target=null,h=9,m=0,pm=false,mode='h',NS='http://www.w3.org/2000/svg';
  var p2=function(n){return (n<10?'0':'')+n};

  function render(){
    bH.textContent=p2(h);bM.textContent=p2(m);
    bH.classList.toggle('on',mode==='h');bM.classList.toggle('on',mode==='m');
    bAM.classList.toggle('on',!pm);bPM.classList.toggle('on',pm);
    var sel=mode==='h'?h%12:m/5, ang=mode==='h'?(h%12)*30:m*6;
    var r=95,hx=130+r*Math.sin(ang*Math.PI/180),hy=130-r*Math.cos(ang*Math.PI/180);
    var s='<circle class="pk-face" cx="130" cy="130" r="128"/>'+
          '<line class="pk-hand" x1="130" y1="130" x2="'+hx+'" y2="'+hy+'"/>'+
          '<circle class="pk-knob" cx="130" cy="130" r="4"/>'+
          '<circle class="pk-knob" cx="'+hx+'" cy="'+hy+'" r="19"/>';
    if(mode==='m'&&m%5!==0) s+='<circle fill="#fff" cx="'+hx+'" cy="'+hy+'" r="3"/>';
    for(var i=0;i<12;i++){
      var a=i*30*Math.PI/180,x=130+r*Math.sin(a),y=130-r*Math.cos(a);
      var label=mode==='h'?(i===0?12:i):p2(i*5);
      var on=(mode==='h'&&i===h%12)||(mode==='m'&&i*5===m);
      s+='<text class="pk-num'+(on?' sel':'')+'" x="'+x+'" y="'+y+'">'+label+'</text>';
    }
    svg.innerHTML=s;
  }

  function fromPoint(e){
    var b=svg.getBoundingClientRect(),
        dx=e.clientX-(b.left+b.width/2),dy=e.clientY-(b.top+b.height/2),
        a=Math.atan2(dx,-dy)*180/Math.PI;
    if(a<0)a+=360;
    if(mode==='h'){h=Math.round(a/30)%12;if(h===0)h=12;}
    else m=Math.round(a/6)%60;
    render();
  }
  var down=false;
  svg.addEventListener('pointerdown',function(e){down=true;svg.setPointerCapture(e.pointerId);fromPoint(e)});
  svg.addEventListener('pointermove',function(e){if(down)fromPoint(e)});
  svg.addEventListener('pointerup',function(){
    if(!down)return;down=false;
    if(mode==='h'){mode='m';render();}
  });

  bH.onclick=function(){mode='h';render()};
  bM.onclick=function(){mode='m';render()};
  bAM.onclick=function(){pm=false;render()};
  bPM.onclick=function(){pm=true;render()};

  function open(inp){
    target=inp;
    var v=(inp.value||'').slice(0,5).split(':'),H=parseInt(v[0],10),M=parseInt(v[1],10);
    if(isNaN(H)){H=9;M=0;}
    pm=H>=12;h=H%12;if(h===0)h=12;m=isNaN(M)?0:M;mode='h';
    render();ov.classList.add('open');
  }
  function close(){ov.classList.remove('open');target=null}

  document.getElementById('pkOk').onclick=function(){
    if(target){
      var H=h%12+(pm?12:0);
      target.value=p2(H)+':'+p2(m);
      target.dispatchEvent(new Event('change',{bubbles:true}));
    }
    close();
  };
  document.getElementById('pkClear').onclick=function(){
    if(target){target.value='';target.dispatchEvent(new Event('change',{bubbles:true}));}
    close();
  };
  document.getElementById('pkCancel').onclick=close;
  ov.addEventListener('click',function(e){if(e.target===ov)close()});
  document.addEventListener('keydown',function(e){if(e.key==='Escape')close()});

  document.querySelectorAll('.pf-t input[type=time]').forEach(function(inp){
    inp.readOnly=true;               // value still submits normally
    inp.addEventListener('click',function(e){e.preventDefault();open(inp)});
    inp.addEventListener('keydown',function(e){
      if(e.key==='Enter'||e.key===' '){e.preventDefault();open(inp)}
    });
  });
})();
</script>
</body>
</html>
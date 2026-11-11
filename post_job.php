<?php // Customer posts a new job
require "config.php";
need("customer");
$u = me();
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!preg_match('/^[0-9]{10}$/', $_POST["phone"])) {
        die("Enter a valid 10-digit phone number. Go back and try again.");
    }
    $photo = "";
    if (
        !empty($_FILES["photo"]["name"]) &&
        preg_match('/\.(jpe?g|png|webp)$/i', $_FILES["photo"]["name"]) &&
        @getimagesize($_FILES["photo"]["tmp_name"]) &&
        $_FILES["photo"]["size"] < 2000000
    ) {
        $photo =
            "uploads/" .
            time() .
            "_" .
            preg_replace("/[^a-z0-9.]/i", "", $_FILES["photo"]["name"]);
        move_uploaded_file($_FILES["photo"]["tmp_name"], $photo);
    }
    $pdo->prepare(
        "INSERT INTO jobs(customer_id,title,category,details,amount,location,phone,photo,urgent,slot)VALUES(?,?,?,?,?,?,?,?,?,?)",
    )->execute([
        $u["id"],
        $_POST["title"],
        $_POST["category"],
        $_POST["details"],
        $_POST["amount"],
        $_POST["location"],
        $_POST["phone"],
        $photo,
        isset($_POST["urgent"]) ? 1 : 0,
        $_POST["slot"] ?: null,
    ]);
    $nid = $pdo->lastInsertId();
    $ug = isset($_POST["urgent"]) ? 1 : 0;
    $w = $pdo->prepare(
        "SELECT id FROM users WHERE role='worker' AND skill=? AND (job_alerts=1 OR ?=1)",
    );
    $w->execute([$_POST["category"], $ug]); // alert matching workers
    foreach ($w as $x) {
        notify(
            $x["id"],
            ($ug
                ? '<span class="material-icons mi">emergency</span> Emergency job: '
                : '<span class="material-icons mi-xs">new_releases</span> New job for you: ') .
                $_POST["title"],
            "job.php?id=" . $nid,
        );
    }
    logit("Posted a job");
    header("Location: dashboard.php");
    exit();
}
// Render the normal page head, but strip the top navigation bar from it
ob_start();
head("Post Job");
$__h = ob_get_clean();
echo preg_replace('#<(nav|header)\b[^>]*>.*?</\1>#is', "", $__h);
?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
/* hide any leftover top bar (login / dashboard / menu links) */
nav, header, .nav, .navbar, .topbar, .top, .menu, .header, #nav, #menu, #topbar{display:none !important}
body{background:#f3f7f8 !important;margin:0}

/* ===== Post Job – scoped styles ===== */
.pj{
  --ink:#122b30; --muted:#6a8085; --line:#e2ecee; --wash:#f6fafb; --paper:#fff;
  --teal:#0e8c9a; --teal-d:#0a6f7b; --tint:#e6f5f7; --red:#e5484d;
  --ease:cubic-bezier(.2,.8,.2,1);
  max-width:680px; margin:28px auto 56px; padding:0 !important; overflow:hidden;
  background:var(--paper); color:var(--ink);
  border:1px solid var(--line) !important; border-radius:18px !important;
  box-shadow:0 1px 2px rgba(16,42,46,.04), 0 18px 40px -22px rgba(16,42,46,.25) !important;
  font:400 14.5px/1.5 'Plus Jakarta Sans',system-ui,sans-serif;
  animation:pjcard .5s var(--ease) both;
}
.pj *{box-sizing:border-box}
@keyframes pjcard{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:none}}

/* small, quiet icons everywhere */
.pj .material-icons{font-size:16px !important;line-height:1;vertical-align:middle}

/* header */
.pj-head{position:relative;padding:26px 30px 22px;border-bottom:1px solid var(--line)}
.pj-head::after{ /* one-time progress sweep */
  content:"";position:absolute;left:0;bottom:-1px;height:2px;width:100%;
  background:linear-gradient(90deg,var(--teal),#3cc4cc);transform-origin:left;
  animation:pjsweep 1s .25s var(--ease) both;
}
@keyframes pjsweep{from{transform:scaleX(0)}to{transform:scaleX(1)}}
.pj-head h2{margin:0;font:800 24px/1.2 'Plus Jakarta Sans',sans-serif;letter-spacing:-.02em;color:var(--ink)}
.pj-head p{margin:4px 0 0;color:var(--muted);font-size:14px}

.pj-body{padding:8px 30px 30px}

/* sections */
.pj-sec{
  padding:22px 0; border-bottom:1px solid var(--line);
  opacity:0;transform:translateY(8px);animation:pjin .5s var(--ease) forwards;
}
.pj-sec:last-child{border-bottom:0;padding-bottom:0}
.pj-sec:nth-child(1){animation-delay:.1s}.pj-sec:nth-child(2){animation-delay:.18s}
.pj-sec:nth-child(3){animation-delay:.26s}.pj-sec:nth-child(4){animation-delay:.34s}
.pj-sec:nth-child(5){animation-delay:.42s}
@keyframes pjin{to{opacity:1;transform:none}}
.pj-sec > h3{
  display:flex;align-items:center;gap:8px;margin:0 0 14px;
  font:700 14px/1.2 'Plus Jakarta Sans',sans-serif;color:var(--ink);
}
.pj-sec > h3 .material-icons{
  width:24px;height:24px;display:inline-grid;place-items:center;border-radius:7px;
  background:var(--tint);color:var(--teal-d);
}
.pj-grid{display:grid;gap:10px}
@media(min-width:620px){.pj-grid.two{grid-template-columns:1fr 1fr}}

/* fields */
.pj input:not([type=checkbox]):not([type=file]),.pj select,.pj textarea{
  width:100%;padding:11px 13px;font:500 14px 'Plus Jakarta Sans',sans-serif;color:var(--ink);
  background:var(--wash);border:1px solid var(--line);border-radius:10px;outline:none;
  transition:border-color .2s,box-shadow .2s,background .2s;
}
.pj textarea{resize:vertical;min-height:88px}
.pj ::placeholder{color:#97aaae}
.pj input:hover,.pj select:hover,.pj textarea:hover{border-color:#c5dadd}
.pj input:focus,.pj select:focus,.pj textarea:focus{
  background:#fff;border-color:var(--teal);box-shadow:0 0 0 3px rgba(14,140,154,.14);
}
.pj label{display:block;margin:0 0 6px;font-weight:600;font-size:13px;color:var(--muted)}
.pj small{display:block;color:var(--muted);font-size:12.5px}
.pj input[type=file]{
  width:100%;padding:8px;background:var(--wash);border:1px dashed #b9d3d7;border-radius:10px;
  color:var(--muted);font:500 13px 'Plus Jakarta Sans',sans-serif;cursor:pointer;transition:border-color .2s,background .2s;
}
.pj input[type=file]:hover{border-color:var(--teal);background:#fff}
.pj input[type=file]::file-selector-button{
  margin-right:10px;padding:6px 12px;border:0;border-radius:8px;cursor:pointer;
  background:var(--tint);color:var(--teal-d);font:600 12.5px 'Plus Jakarta Sans',sans-serif;transition:background .2s;
}
.pj input[type=file]::file-selector-button:hover{background:#cfeaee}

/* price guide + hint */
#pg:not(:empty),#hint:not(:empty){
  display:block;padding:8px 12px;border-radius:9px;background:#fff8e8;color:#7a5200;
  border:1px solid #f8e3a8;font-weight:500;animation:pjpop .3s var(--ease);
}
#hint:not(:empty){margin-top:2px}
@keyframes pjpop{from{opacity:0;transform:translateY(-4px)}to{opacity:1;transform:none}}

/* AI box */
.pj .ai{padding:14px;border-radius:12px;background:var(--tint);border:1px solid #cdeaee}
.pj .ai b{display:flex;align-items:flex-start;gap:7px;margin-bottom:10px;font-size:13.5px;font-weight:700;color:var(--teal-d)}
.pj .ai b .material-icons{margin-top:2px}
.pj .ai textarea{background:#fff;min-height:64px}
.pj .ai-row{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-top:8px}
.pj .ai-row input[type=file]{flex:1 1 200px;width:auto !important;background:#fff}
#aio{margin-top:8px}
#aio p{margin:0 0 8px;font-weight:600;color:var(--teal-d);animation:pjpop .35s var(--ease)}
#aio ul{margin:0;padding:0;list-style:none;display:grid;gap:5px}
#aio li{position:relative;padding:7px 10px 7px 30px;background:#fff;border:1px solid var(--line);border-radius:8px;font-size:13px;animation:pjpop .35s var(--ease) both}
#aio li::before{content:"✓";position:absolute;left:11px;color:var(--teal);font-weight:700}
#aio li:nth-child(2){animation-delay:.05s}#aio li:nth-child(3){animation-delay:.1s}
#aio li:nth-child(4){animation-delay:.15s}#aio li:nth-child(5){animation-delay:.2s}
#aio li:nth-child(n+6){animation-delay:.25s}

/* buttons */
.pj button{
  display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:10px 16px;border:0;border-radius:10px;
  cursor:pointer;font:600 13.5px 'Plus Jakarta Sans',sans-serif;color:#fff;background:var(--teal);
  transition:background .2s,transform .2s var(--ease),box-shadow .2s;
}
.pj button:hover{background:var(--teal-d);transform:translateY(-1px);box-shadow:0 8px 16px -10px rgba(14,140,154,.9)}
.pj button:active{transform:scale(.98)}
.pj button.ghost{background:#fff;color:var(--teal-d);border:1px solid #bfdde1}
.pj button.ghost:hover{background:var(--tint);box-shadow:none}
#aibtn:hover .material-icons{animation:pjspark .6s var(--ease)}
@keyframes pjspark{50%{transform:scale(1.35) rotate(18deg)}}
.pj button.submit{width:100%;padding:14px;font-size:15px;font-weight:700;border-radius:12px;box-shadow:0 10px 20px -12px rgba(14,140,154,.9)}

/* emergency toggle */
.pj .urgent{
  display:flex;align-items:center;gap:10px;margin:0;padding:11px 13px;cursor:pointer;
  background:#fff;border:1px solid var(--line);border-radius:10px;color:var(--ink);
  transition:background .25s,border-color .25s;
}
.pj .urgent input{position:absolute;opacity:0;width:0;height:0}
.pj .urgent .sw{flex:none;width:36px;height:20px;border-radius:99px;background:#d4dfe1;position:relative;transition:background .25s}
.pj .urgent .sw::after{content:"";position:absolute;top:2px;left:2px;width:16px;height:16px;border-radius:50%;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.25);transition:transform .3s var(--ease)}
.pj .urgent .t{font-weight:600;font-size:13.5px}
.pj .urgent .material-icons{color:#9aa9ad;transition:color .25s}
.pj .urgent:has(input:checked){background:#fff4f4;border-color:#f3b9bb}
.pj .urgent:has(input:checked) .sw{background:var(--red)}
.pj .urgent:has(input:checked) .sw::after{transform:translateX(16px)}
.pj .urgent:has(input:checked) .material-icons{color:var(--red);animation:pjpulse 1.2s ease-in-out infinite}
.pj .urgent:has(input:focus-visible){outline:2px solid rgba(229,72,77,.45);outline-offset:2px}
@keyframes pjpulse{50%{transform:scale(1.2)}}

.pj :focus-visible{outline:2px solid rgba(14,140,154,.5);outline-offset:2px}

@media(max-width:560px){
  .pj{margin:10px 8px 32px;border-radius:14px !important}
  .pj-head{padding:22px 18px 18px}.pj-body{padding:4px 18px 24px}
}
@media(prefers-reduced-motion:reduce){
  .pj,.pj *,.pj *::before,.pj *::after{animation:none !important;transition:none !important}
  .pj-sec{opacity:1;transform:none}
}
</style>

<form class="card pj" method="post" enctype="multipart/form-data">
<div class="pj-head">
  <h2>Post a Job</h2>
  <p>Tell us what needs doing and nearby workers will be notified.</p>
</div>

<div class="pj-body">
<?php
$pg = [];
foreach (
    $pdo->query(
        "SELECT category,ROUND(AVG(price)) a,MIN(price) mn,MAX(price) mx,COUNT(*) n FROM jobs WHERE paid=1 AND price>0 GROUP BY category",
    )
    as $x
) {
    $pg[$x["category"]] = $x;
}
?>

<!-- AI helper -->
<div class="pj-sec">
<div class="ai"><b><span class="material-icons mi">smart_toy</span> Not sure what to write? Describe the problem and let AI fill the form</b>
<textarea id="aitx" rows="2" maxlength="500" placeholder="e.g. My ceiling fan makes a loud noise and runs slowly"></textarea>
<div class="ai-row">
<input type="file" id="aiph" accept="image/*"> <button type="button" id="aibtn"><span class="material-icons mi-xs">auto_awesome</span> Suggest</button></div>
<small id="aim" style="margin-top:8px"></small><div id="aio"></div></div>
</div>

<script>aibtn.onclick=async function(){if(!aitx.value.trim()){aim.textContent="Describe the problem first";return}aim.textContent="Thinking…";let fd=new FormData();fd.append("csrf","<?= $_SESSION[
    "csrf"
] ?>");fd.append("text",aitx.value);if(aiph.files[0])fd.append("photo",aiph.files[0]);
let d=await (await fetch("ai_job.php",{method:"POST",body:fd})).json();if(d.error){aim.textContent=d.error;return}
let f=document.querySelector("form.card").elements;f["title"].value=d.title;f["category"].value=d.category;f["details"].value=d.details;f["amount"].value=d.amount;
aim.textContent=d.ai?"Filled by AI – please check it":"Filled (simple mode) – please check it";aio.innerHTML="";
let p=document.createElement("p");p.innerHTML='<span class="material-icons mi">payments</span> Estimated price: ₹'+Number(d.low)+" – ₹"+Number(d.high)+(d.db?" · similar paid jobs averaged ₹"+Number(d.db.a):"");aio.appendChild(p);
let ul=document.createElement("ul");d.checklist.forEach(function(c){let l=document.createElement("li");l.textContent=c;ul.appendChild(l)});aio.appendChild(ul)}</script>
<script>const PG=<?= json_encode(
    $pg,
) ?>;addEventListener("load",function(){let s=document.querySelector("[name=category]");function f(){let g=PG[s.value];document.getElementById("pg").innerHTML=g?'<span class="material-icons mi-xs">lightbulb</span> Price guide: similar '+s.value.replace(/[<>&]/g,"")+" jobs cost about ₹"+Number(g.a)+" (₹"+Number(g.mn)+" – ₹"+Number(g.mx)+", "+Number(g.n)+" paid jobs)":""}s.onchange=f;f()})</script>

<!-- Job details -->
<div class="pj-sec">
<h3><span class="material-icons">handyman</span> What needs doing?</h3>
<div class="pj-grid">
<select onchange="tp(this.value)"><option value="">Quick template (optional)</option><option value="0">Fan repair</option><option value="1">Tap leak repair</option><option value="2">AC service</option><option value="3">House cleaning</option><option value="4">Wall painting</option><option value="5">Furniture repair</option><option value="6">Bike service</option></select><small id="hint"></small>
<script>const T=[["Fan repair","Electrician","Ceiling fan is not working or makes noise. Please check and repair.","1 fan","₹200–₹400"],["Tap leak repair","Plumber","Water leaking from a tap or pipe. Please fix it.","1 tap","₹150–₹350"],["AC service","AC Technician","Regular AC cleaning and gas check.","1 AC","₹500–₹900"],["House cleaning","Cleaner","Full house cleaning including floors and bathrooms.","1 house","₹800–₹1500"],["Wall painting","Painter","Painting of walls in a room.","1 room","₹2000–₹4000"],["Furniture repair","Carpenter","Repair of a broken table, chair or door.","1 item","₹300–₹800"],["Bike service","Mechanic","General service and oil change.","1 bike","₹400–₹700"]];
function tp(i){if(i==="")return;let f=document.forms[0].elements;f["title"].value=T[i][0];f["category"].value=T[i][1];f["details"].value=T[i][2];f["amount"].value=T[i][3];hint.textContent=" Suggested price: "+T[i][4]}</script>
<input name="title" placeholder="Job title" required>
<div class="pj-grid two">
<select name="category"><?php foreach ($CATS as $c) {
    echo "<option>$c</option>";
} ?></select>
<input name="amount" placeholder="Amount of work (e.g. 3 rooms, 5 fans)" required>
</div>
<small id="pg"></small>
<textarea name="details" rows="4" placeholder="How should the work be done? Instructions..." required></textarea>
</div>
</div>

<!-- Location + phone -->
<div class="pj-sec">
<h3><span class="material-icons">place</span> Where &amp; how to reach you</h3>
<div class="pj-grid">
<?php
$ad = $pdo->prepare("SELECT * FROM addresses WHERE user_id=?");
$ad->execute([$u["id"]]);
$ad = $ad->fetchAll();
if ($ad) {
    echo '<select onchange="loc.value=this.value"><option value="">Saved addresses</option>';
    foreach ($ad as $x) {
        echo '<option value="' .
            e($x["address"]) .
            '">' .
            e($x["label"]) .
            ": " .
            e($x["address"]) .
            "</option>";
    }
    echo "</select>";
}
?><input id="loc" name="location" placeholder="Location (address or area)" required><button type="button" class="ghost" onclick="navigator.geolocation.getCurrentPosition(p=>loc.value=p.coords.latitude+','+p.coords.longitude)"><span class="material-icons mi">my_location</span> Use my current location</button>
<input name="phone" pattern="[0-9]{10}" placeholder="Phone (10 digits)" value="<?= e(
    $u["phone"],
) ?>" required>
</div>
</div>

<!-- Schedule -->
<div class="pj-sec">
<h3><span class="material-icons">event</span> When?</h3>
<div class="pj-grid">
<div><label>Preferred date &amp; time (optional)</label><input type="datetime-local" name="slot"></div>
<label class="urgent"><input type="checkbox" name="urgent"><span class="sw"></span><span class="material-icons mi">emergency</span><span class="t">Emergency – need a worker immediately</span></label>
</div>
</div>

<!-- Photo + submit -->
<div class="pj-sec">
<h3><span class="material-icons">photo_camera</span> Photo</h3>
<div class="pj-grid">
<div><label>Photo of the work (optional)</label><input type="file" name="photo" accept="image/"></div>
<button class="submit">Post Job</button>
</div>
</div>

</div>
</form><?php foot();
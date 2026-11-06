<?php // Customer posts a new job
require 'config.php';need('customer');$u=me();
if($_SERVER['REQUEST_METHOD']=='POST'){
 if(!preg_match('/^[0-9]{10}$/',$_POST['phone']))die('Enter a valid 10-digit phone number. Go back and try again.');$photo='';
 if(!empty($_FILES['photo']['name'])&&preg_match('/\.(jpe?g|png|webp)$/i',$_FILES['photo']['name'])&&@getimagesize($_FILES['photo']['tmp_name'])&&$_FILES['photo']['size']<2000000){
  $photo='uploads/'.time().'_'.preg_replace('/[^a-z0-9.]/i','',$_FILES['photo']['name']);move_uploaded_file($_FILES['photo']['tmp_name'],$photo);}
 $pdo->prepare("INSERT INTO jobs(customer_id,title,category,details,amount,location,phone,photo,urgent,slot)VALUES(?,?,?,?,?,?,?,?,?,?)")
 ->execute([$u['id'],$_POST['title'],$_POST['category'],$_POST['details'],$_POST['amount'],$_POST['location'],$_POST['phone'],$photo,isset($_POST['urgent'])?1:0,$_POST['slot']?:null]);
 $nid=$pdo->lastInsertId();$ug=isset($_POST['urgent'])?1:0;$w=$pdo->prepare("SELECT id FROM users WHERE role='worker' AND skill=? AND (job_alerts=1 OR ?=1)");$w->execute([$_POST['category'],$ug]); // alert matching workers
 foreach($w as $x)notify($x['id'],($ug?'<span class="material-icons mi">emergency</span> Emergency job: ':'<span class="material-icons mi-xs">new_releases</span> New job for you: ').$_POST['title'],'job.php?id='.$nid);
 logit('Posted a job');header('Location: dashboard.php');exit;}
head('Post Job');?>
<form class="card" method="post" enctype="multipart/form-data"><h2>Post a Job</h2>
<?php $pg=[];foreach($pdo->query("SELECT category,ROUND(AVG(price)) a,MIN(price) mn,MAX(price) mx,COUNT(*) n FROM jobs WHERE paid=1 AND price>0 GROUP BY category") as $x)$pg[$x['category']]=$x;?>
<small id="pg"></small>
<div class="card" style="margin:8px 0;background:#f2fbfc"><b><span class="material-icons mi">smart_toy</span> Not sure what to write? Describe the problem and let AI fill the form</b>
<textarea id="aitx" rows="2" maxlength="500" placeholder="e.g. My ceiling fan makes a loud noise and runs slowly"></textarea>
<input type="file" id="aiph" accept="image/*" style="width:auto"> <button type="button" id="aibtn"><span class="material-icons mi-xs">auto_awesome</span> Suggest</button> <small id="aim"></small><div id="aio"></div></div>
<script>aibtn.onclick=async function(){if(!aitx.value.trim()){aim.textContent="Describe the problem first";return}aim.textContent="Thinking…";let fd=new FormData();fd.append("csrf","<?=$_SESSION['csrf']?>");fd.append("text",aitx.value);if(aiph.files[0])fd.append("photo",aiph.files[0]);
let d=await (await fetch("ai_job.php",{method:"POST",body:fd})).json();if(d.error){aim.textContent=d.error;return}
let f=document.querySelector("form.card").elements;f["title"].value=d.title;f["category"].value=d.category;f["details"].value=d.details;f["amount"].value=d.amount;
aim.textContent=d.ai?"Filled by AI – please check it":"Filled (simple mode) – please check it";aio.innerHTML="";
let p=document.createElement("p");p.textContent="<span class="material-icons mi">payments</span> Estimated price: ₹"+d.low+" – ₹"+d.high+(d.db?" · similar paid jobs averaged ₹"+d.db.a:"");aio.appendChild(p);
let ul=document.createElement("ul");d.checklist.forEach(function(c){let l=document.createElement("li");l.textContent=c;ul.appendChild(l)});aio.appendChild(ul)}</script><script>const PG=<?=json_encode($pg)?>;addEventListener("load",function(){let s=document.querySelector("[name=category]");function f(){let g=PG[s.value];document.getElementById("pg").textContent=g?"<span class="material-icons mi-xs">lightbulb</span> Price guide: similar "+s.value+" jobs cost about ₹"+g.a+" (₹"+g.mn+" – ₹"+g.mx+", "+g.n+" paid jobs)":""}s.onchange=f;f()})</script>
<select onchange="tp(this.value)"><option value=""><span class="material-icons mi">bolt</span> Quick template (optional)</option><option value="0">Fan repair</option><option value="1">Tap leak repair</option><option value="2">AC service</option><option value="3">House cleaning</option><option value="4">Wall painting</option><option value="5">Furniture repair</option><option value="6">Bike service</option></select><small id="hint"></small>
<script>const T=[["Fan repair","Electrician","Ceiling fan is not working or makes noise. Please check and repair.","1 fan","₹200–₹400"],["Tap leak repair","Plumber","Water leaking from a tap or pipe. Please fix it.","1 tap","₹150–₹350"],["AC service","AC Technician","Regular AC cleaning and gas check.","1 AC","₹500–₹900"],["House cleaning","Cleaner","Full house cleaning including floors and bathrooms.","1 house","₹800–₹1500"],["Wall painting","Painter","Painting of walls in a room.","1 room","₹2000–₹4000"],["Furniture repair","Carpenter","Repair of a broken table, chair or door.","1 item","₹300–₹800"],["Bike service","Mechanic","General service and oil change.","1 bike","₹400–₹700"]];
function tp(i){if(i==="")return;let f=document.forms[0].elements;f["title"].value=T[i][0];f["category"].value=T[i][1];f["details"].value=T[i][2];f["amount"].value=T[i][3];hint.textContent=" Suggested price: "+T[i][4]}</script>
<input name="title" placeholder="Job title" required>
<select name="category"><?php foreach($CATS as $c)echo "<option>$c</option>";?></select>
<input name="amount" placeholder="Amount of work (e.g. 3 rooms, 5 fans)" required>
<textarea name="details" rows="4" placeholder="How should the work be done? Instructions..." required></textarea>
<?php $ad=$pdo->prepare("SELECT * FROM addresses WHERE user_id=?");$ad->execute([$u['id']]);$ad=$ad->fetchAll();
if($ad){echo '<select onchange="loc.value=this.value"><option value=""><span class="material-icons mi">place</span> Saved addresses</option>';foreach($ad as $x)echo '<option value="'.e($x['address']).'">'.e($x['label']).': '.e($x['address']).'</option>';echo '</select>';}?><input id="loc" name="location" placeholder="Location (address or area)" required><button type="button" onclick="navigator.geolocation.getCurrentPosition(p=>loc.value=p.coords.latitude+','+p.coords.longitude)"><span class="material-icons mi">place</span> Use my current location</button><br><br><input name="phone" pattern="[0-9]{10}" placeholder="Phone (10 digits)" value="<?=e($u['phone'])?>" required>
<label>Preferred date & time (optional)</label><input type="datetime-local" name="slot"><label><input type="checkbox" name="urgent" style="width:auto"> <span class="material-icons mi">emergency</span> Emergency – need a worker immediately</label><br><br><label>Photo of the work (optional)</label><input type="file" name="photo" accept="image/*">
<button>Post Job</button></form><?php foot();

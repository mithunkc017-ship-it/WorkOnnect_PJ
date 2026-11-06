<?php // Customer sends a job request directly to one chosen worker (worker accepts or declines on the job page)
require 'config.php';need('customer');$u=me();$w=(int)($_GET['w']??0);
$q=$pdo->prepare("SELECT * FROM users WHERE id=? AND role='worker'");$q->execute([$w]);$wk=$q->fetch();if(!$wk)die('Worker not found');
if($_SERVER['REQUEST_METHOD']=='POST'){
 if(!preg_match('/^[0-9]{10}$/',$_POST['phone'])||(int)$_POST['price']<=0)die('Enter a valid 10-digit phone number and a price. Go back and try again.');
 if(!works($wk,$_POST['slot']))die('This worker is not available at that day/time. Check their working hours on their profile and go back.');
 if($_POST['slot']&&clash($pdo,$w,$_POST['slot']))die('That time was just taken. Go back and pick another slot.');
 $pdo->prepare("INSERT INTO jobs(customer_id,title,category,details,amount,location,phone,price,slot,status,worker_id)VALUES(?,?,?,?,?,?,?,?,?,'requested',?)")
 ->execute([$u['id'],$_POST['title'],$wk['skill'],$_POST['details'],$_POST['amount'],$_POST['location'],$_POST['phone'],(int)$_POST['price'],$_POST['slot']?:null,$w]);
 $id=$pdo->lastInsertId();notify($w,$u['name'].' sent you a job request: "'.$_POST['title'].'"',"job.php?id=$id");header("Location: job.php?id=$id");exit;}
head('Hire Worker');?>
<form class="card" method="post"><h2><span class="material-icons mi">handshake</span> Hire <?=e($wk['name'])?> (<?=e($wk['skill'])?>)</h2>
<input name="title" placeholder="Job title" required><input name="amount" placeholder="Amount of work (e.g. 3 fans)" required>
<textarea name="details" rows="3" placeholder="How should the work be done?" required></textarea>
<input name="location" placeholder="Location" required><input name="phone" pattern="[0-9]{10}" placeholder="Phone (10 digits)" value="<?=e($u['phone'])?>" required>
<input type="number" name="price" min="1" placeholder="Price you offer (₹)" required>
<label>Book a time slot (optional)</label><input type="date" id="sd" min="<?=date('Y-m-d')?>" max="<?=date('Y-m-d',strtotime('+30 days'))?>">
<div id="sl" class="slots"><small>Pick a date to see the free times.</small></div><input type="hidden" name="slot" id="slot">
<script>sd.onchange=async function(){let box=document.getElementById("sl");box.textContent="Loading…";slot.value="";let d=await (await fetch("slots.php?w=<?=$w?>&d="+sd.value)).json();box.innerHTML="";
if(!d.slots.length){let m=document.createElement("small");m.textContent=d.note;box.appendChild(m);return}
let m=document.createElement("small");m.textContent=d.note+" · grey = already booked";box.appendChild(m);box.appendChild(document.createElement("br"));
d.slots.forEach(function(s){let b=document.createElement("button");b.type="button";b.className="slot";b.textContent=s.t;b.disabled=!s.free;b.onclick=function(){document.querySelectorAll(".slot").forEach(function(x){x.classList.remove("sel")});b.classList.add("sel");slot.value=sd.value+" "+s.t};box.appendChild(b)})}</script>
<button>Send Request</button></form><?php foot();

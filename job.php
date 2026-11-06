<?php // Job details: offers, chat, progress tracker, payment, review, report
require 'config.php';need();$u=me();$id=(int)($_GET['id']??0);
$q=$pdo->prepare("SELECT j.*,c.name cname,w.name wname FROM jobs j JOIN users c ON c.id=j.customer_id LEFT JOIN users w ON w.id=j.worker_id WHERE j.id=?");$q->execute([$id]);$j=$q->fetch();
if(!$j)die('Job not found');
if($_SERVER['REQUEST_METHOD']=='POST'){$a=$_POST['a'];
 if($a=='msg'&&($j['customer_id']==$u['id']||$j['worker_id']==$u['id'])&&(trim($_POST['m'])!==''||!empty($_FILES['photo']['name']))){$ph='';if(!empty($_FILES['photo']['name'])&&($ii=@getimagesize($_FILES['photo']['tmp_name']))&&$_FILES['photo']['size']<2000000&&in_array($ii[2],[IMAGETYPE_JPEG,IMAGETYPE_PNG])){$ph='uploads/c'.$id.'_'.bin2hex(random_bytes(6)).($ii[2]==IMAGETYPE_PNG?'.png':'.jpg');move_uploaded_file($_FILES['photo']['tmp_name'],$ph);}
 $pdo->prepare("INSERT INTO messages(job_id,sender_id,msg,photo)VALUES(?,?,?,?)")->execute([$id,$u['id'],substr($_POST['m'],0,300),$ph]);$to=$u['id']==$j['customer_id']?$j['worker_id']:$j['customer_id'];if($to)notify($to,'New message on "'.$j['title'].'"',"job.php?id=$id");}
 if($a=='respond'&&$j['worker_id']==$u['id']&&$j['status']=='requested'){$ok=$_POST['r']=='accept';$pdo->prepare("UPDATE jobs SET status=? WHERE id=?")->execute([$ok?'assigned':'cancelled',$id]);notify($j['customer_id'],$u['name'].($ok?' accepted':' declined').' your request "'.$j['title'].'"',"job.php?id=$id");}
 if($a=='confirm'&&$j['customer_id']==$u['id']&&$j['status']=='completed'){$pdo->prepare("UPDATE jobs SET confirmed=1 WHERE id=?")->execute([$id]);notify($j['worker_id'],'Customer confirmed the work for "'.$j['title'].'"'.($j['paid']?' – your payment is released':' – payment can be made now'),"job.php?id=$id");}
 if($a=='tip'&&$j['customer_id']==$u['id']&&$j['paid']&&!$j['tip']&&(int)$_POST['tip']>0&&(int)$_POST['tip']<=5000){$pdo->prepare("UPDATE jobs SET tip=? WHERE id=?")->execute([(int)$_POST['tip'],$id]);notify($j['worker_id'],'💝 You received a ₹'.(int)$_POST['tip'].' tip for "'.$j['title'].'"',"job.php?id=$id");logit('Tip ₹'.(int)$_POST['tip'].' on job #'.$id);}
 if($a=='resched'&&in_array($u['id'],[$j['customer_id'],$j['worker_id']])&&$j['status']=='assigned'&&$_POST['slot']){$pdo->prepare("UPDATE jobs SET reschedule_slot=?,reschedule_by=? WHERE id=?")->execute([$_POST['slot'],$u['id'],$id]);notify($u['id']==$j['customer_id']?$j['worker_id']:$j['customer_id'],'New time requested for "'.$j['title'].'"',"job.php?id=$id");}
 if($a=='rr'&&$j['reschedule_by']&&$j['reschedule_by']!=$u['id']&&in_array($u['id'],[$j['customer_id'],$j['worker_id']])){$acc=$_POST['r']=='accept';
  $pdo->prepare($acc?"UPDATE jobs SET slot=reschedule_slot,reschedule_slot=NULL,reschedule_by=NULL WHERE id=?":"UPDATE jobs SET reschedule_slot=NULL,reschedule_by=NULL WHERE id=?")->execute([$id]);
  notify($j['reschedule_by'],'Your time change request was '.($acc?'accepted':'declined'),"job.php?id=$id");}
 include 'job_actions.php';
 if($a=='cancel'&&$j['customer_id']==$u['id']&&in_array($j['status'],['open','requested','assigned'])){$fee=cancelFee($j);$ok=true; // free before a worker accepts, otherwise a cancellation fee (policy in terms.php)
 if($fee>0){
  if($j['paid']){wallet($pdo,$u['id'],$j['price']+$j['tip']-$fee,'Refund for cancelled job #'.$id.' (cancellation fee Rs'.$fee.' kept)');$pdo->prepare("UPDATE jobs SET status='cancelled',cancel_fee=?,price=?,commission=ROUND(?*0.10),tip=0,escrow=0,confirmed=1 WHERE id=?")->execute([$fee,$fee,$fee,$id]);}
  else{$f=$pdo->prepare("SELECT wallet FROM users WHERE id=?");$f->execute([$u['id']]);
   if((int)$f->fetchColumn()<$fee)$ok=false;else{wallet($pdo,$u['id'],-$fee,'Cancellation fee for job #'.$id);$pdo->prepare("UPDATE jobs SET status='cancelled',cancel_fee=?,price=?,paid=1,pay_method='Cancellation fee',commission=ROUND(?*0.10),escrow=0,confirmed=1 WHERE id=?")->execute([$fee,$fee,$fee,$id]);}}
 }else $pdo->prepare("UPDATE jobs SET status='cancelled' WHERE id=?")->execute([$id]);
 if($ok){if($j['worker_id'])notify($j['worker_id'],'Job "'.$j['title'].'" was cancelled'.($fee?' - you get a compensation of Rs'.($fee-round($fee*0.10)):''),"job.php?id=$id");logit('Cancelled job #'.$id.($fee?' (fee Rs'.$fee.')':''));}
 else $_SESSION['flash']='Not enough wallet balance for the cancellation fee of Rs'.$fee.'. Please top up your wallet first.';}
 if($a=='reject'&&$j['customer_id']==$u['id']&&$j['status']=='open'){$pdo->prepare("UPDATE offers SET status='rejected' WHERE job_id=? AND worker_id=?")->execute([$id,(int)$_POST['w']]);notify((int)$_POST['w'],'Your offer on "'.$j['title'].'" was declined',"job.php?id=$id");}
 if($a=='offer'&&$u['role']=='worker'&&$j['status']=='open'&&(int)$_POST['price']>0&&!rejected($pdo,$id,$u['id'])){$pdo->prepare("REPLACE INTO offers(job_id,worker_id,price,message)VALUES(?,?,?,?)")->execute([$id,$u['id'],(int)$_POST['price'],$_POST['msg']]);notify($j['customer_id'],$u['name'].' sent an offer on "'.$j['title'].'"',"job.php?id=$id");}
 if($a=='accept'&&$j['customer_id']==$u['id']&&$j['status']=='open'){$pdo->prepare("UPDATE jobs SET worker_id=?,status='assigned',price=(SELECT price FROM offers WHERE job_id=? AND worker_id=?) WHERE id=?")->execute([(int)$_POST['w'],$id,(int)$_POST['w'],$id]);notify((int)$_POST['w'],'Your offer was accepted for "'.$j['title'].'"',"job.php?id=$id");}
 if($a=='progress'&&$j['worker_id']==$u['id']&&in_array($_POST['s'],['in_progress','completed'])){$pdo->prepare("UPDATE jobs SET status=?,done_at=IF(?='completed',NOW(),done_at) WHERE id=?")->execute([$_POST['s'],$_POST['s'],$id]);notify($j['customer_id'],'Job "'.$j['title'].'" is now '.str_replace('_',' ',$_POST['s']),"job.php?id=$id");}
 header("Location: job.php?id=$id");exit;}
head($j['title']);$st=['open','assigned','in_progress','completed'];$at=array_search($j['status'],$st);?>
<div class="card"><h2><?=e($j['title'])?> <?=$j['urgent']?'<span class="err">🚨 EMERGENCY</span>':''?></h2>
<div class="steps"><?php foreach($st as $i=>$s)echo '<div class="'.($i<=$at?'on':'').'">'.ucfirst(str_replace('_',' ',$s)).'</div>';?></div>
<p><b><?=e($j['category'])?></b> · 📍 <?=e($j['location'])?> · Work: <?=e($j['amount'])?></p>
<p><?=nl2br(e($j['details']))?></p>
<iframe title="map" width="100%" height="220" style="border:0;border-radius:8px" loading="lazy" src="https://www.google.com/maps?q=<?=urlencode($j['location'])?>&output=embed"></iframe>
<p><a href="https://www.google.com/maps/search/?api=1&query=<?=urlencode($j['location'])?>" target="_blank">📍 Open in Google Maps</a></p>
<?php if($j['photo'])echo '<img class="p" src="'.e($j['photo']).'">';?>
<p>Posted by <?=e($j['cname'])?><?php if($j['customer_id']==$u['id']||($j['worker_id']==$u['id']&&$j['status']!='requested')||$u['role']=='admin')echo ' · 📞 <a href="tel:'.e($j['phone']).'">'.e($j['phone']).'</a>';else echo ' · 📞 <small>shown after the job is assigned to you</small>';?></p>
<?php if($j['customer_id']==$u['id']&&$j['status']=='open')echo '<a class="btn" href="edit_job.php?id='.$id.'">✏️ Edit</a> ';
if($j['customer_id']==$u['id']&&in_array($j['status'],['open','requested','assigned']))echo '<form method="post" style="display:inline" onsubmit="return confirm(\'Cancel this job?'.(cancelFee($j)?' A cancellation fee of Rs'.cancelFee($j).' will be charged.':'').'\')"><input type="hidden" name="a" value="cancel"><button style="background:#c00">Cancel Job</button></form>';
if($j['status']=='cancelled')echo '<p class="err"><b>This job was cancelled.</b></p>';?>
<?php if($j['status']=='requested'&&$j['worker_id']==$u['id'])echo '<form method="post"><input type="hidden" name="a" value="respond"><button name="r" value="accept">✅ Accept Request</button> <button name="r" value="decline" style="background:#c00">Decline</button></form>';
if($j['status']=='requested'&&$j['customer_id']==$u['id'])echo '<p>⏳ Waiting for the worker to accept your request.</p>';
if($j['customer_id']==$u['id']&&$j['status']=='completed'&&!$j['confirmed'])echo '<form method="post"><input type="hidden" name="a" value="confirm"><button>✔ Confirm the work is done</button></form>';
if($j['slot'])echo '<p>🗓 Preferred time: <b>'.e($j['slot']).'</b></p>';?>
<?php if($j['status']=='assigned'&&in_array($u['id'],[$j['customer_id'],$j['worker_id']])){
 if($j['reschedule_by']&&$j['reschedule_by']!=$u['id'])echo '<p>🔁 New time requested: <b>'.e($j['reschedule_slot']).'</b></p><form method="post"><input type="hidden" name="a" value="rr"><button name="r" value="accept">Accept</button> <button name="r" value="decline" style="background:#c00">Decline</button></form>';
 elseif($j['reschedule_by'])echo '<p>🔁 Waiting for the other person to answer your time change request.</p>';
 else echo '<form method="post" style="display:flex;gap:8px"><input type="hidden" name="a" value="resched"><input type="datetime-local" name="slot" required><button>Request new time</button></form>';}
if($j['customer_id']==$u['id']&&$j['paid']&&!$j['tip']&&!$j['refunded'])echo '<form method="post" style="display:flex;gap:8px"><input type="hidden" name="a" value="tip"><input type="number" name="tip" min="1" max="5000" placeholder="Tip ₹" required><button>💝 Add tip</button></form>';
if($j['customer_id']==$u['id']&&in_array($j['status'],['completed','cancelled']))echo '<form method="post" action="repeat.php?id='.$id.'" style="display:inline"><button>🔁 Book again</button></form>';?>
<?php include 'job_extras.php';?>
<?php if($j['price'])echo '<p>Agreed price: <b>₹'.$j['price'].'</b> · '.($j['paid']?'<span class="ok">Paid via '.e($j['pay_method']).'</span> <a class="btn" href="invoice.php?id='.$id.'">🧾 Invoice</a>':'Unpaid').($j['tip']?' · 💝 Tip ₹'.$j['tip']:'').($j['refunded']?' · <b class="err">Refunded</b>':'').'</p>';
if($j['customer_id']==$u['id']&&!$j['paid']&&$j['confirmed']&&!$j['refunded'])echo '<a class="btn" href="pay.php?id='.$id.'">Pay Now</a>';?>
<?php if($j['customer_id']==$u['id']&&$j['status']=='completed'){$rv=$pdo->prepare('SELECT stars FROM reviews WHERE job_id=?');$rv->execute([$id]);$rv=$rv->fetchColumn();
echo $rv?'<p>Your rating: '.str_repeat('⭐',$rv).'</p>':'<form method="post" action="review.php?id='.$id.'"><h3>Rate the worker</h3><select name="stars"><option value="5">5 – Excellent</option><option value="4">4 – Good</option><option value="3">3 – Okay</option><option value="2">2 – Poor</option><option value="1">1 – Bad</option></select><input name="comment" placeholder="Write a short review"><button>Submit Review</button></form>';}?>
<?php if($j['wname'])echo '<p>Assigned to: <b><a href="worker.php?id='.$j['worker_id'].'">'.e($j['wname']).'</a></b></p>';?>
<?php if($u['role']=='worker'&&$j['worker_id']==$u['id']&&in_array($j['status'],['assigned','in_progress'])){?>
<form method="post"><input type="hidden" name="a" value="progress"><input type="hidden" name="s" value="<?=$j['status']=='assigned'?'in_progress':'completed'?>">
<button><?=$j['status']=='assigned'?'Start Work':'Mark Completed'?></button></form><?php }?>
<?php if($u['role']=='worker'&&$j['status']=='open'){?>
<form method="post"><h3>Send Offer</h3><input type="hidden" name="a" value="offer"><input type="number" name="price" placeholder="Your price (₹)" required>
<input name="msg" placeholder="Short message" required><button>Send Offer</button></form><?php }?></div>
<?php if(($j['customer_id']==$u['id']||$j['worker_id']==$u['id'])&&$j['worker_id']){$ms=$pdo->prepare("SELECT m.*,u.name FROM messages m JOIN users u ON u.id=m.sender_id WHERE job_id=? ORDER BY m.id");$ms->execute([$id]);?>
<h3>💬 Job Chat</h3><div class="card" id="chat" style="max-height:260px;overflow:auto"><?php foreach($ms as $m)echo '<p style="margin:6px 0"><b>'.e($m['name']).':</b> '.e($m['msg']).($m['photo']?'<br><img class="p" style="max-height:140px" src="'.e($m['photo']).'">':'').' <small>'.$m['created'].'</small></p>';?></div>
<form method="post" enctype="multipart/form-data" style="display:flex;gap:8px;margin-top:8px"><input type="hidden" name="a" value="msg"><input name="m" placeholder="Type a message"><input type="file" name="photo" accept="image/*" style="width:auto"><button>Send</button></form>
<script>chat.scrollTop=1e9;setInterval(async()=>{let r=await fetch("msgs.php?id=<?=$id?>");chat.innerHTML=await r.text();chat.scrollTop=1e9},4000)</script><?php }?>
<?php if($j['customer_id']==$u['id']||$u['role']=='admin'){echo '<h3>Offers</h3>';
 include 'offer_compare.php';}
if(($j['customer_id']==$u['id']||$j['worker_id']==$u['id'])&&$j['worker_id']){?><details class="card" style="margin-top:12px"><summary>🚩 Report a problem</summary><form method="post" action="report.php?id=<?=$id?>"><textarea name="msg" rows="2" required placeholder="Describe the problem"></textarea><button>Send Report</button></form></details><?php }
foot();

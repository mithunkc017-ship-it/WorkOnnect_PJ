<?php // Dispute center: either side opens a dispute and adds evidence (text + photo); the admin decides (refund / release / dismiss)
require 'config.php';need();$u=me();$jid=(int)($_GET['job']??0);
$q=$pdo->prepare("SELECT j.*,c.name cn,w.name wn FROM jobs j JOIN users c ON c.id=j.customer_id LEFT JOIN users w ON w.id=j.worker_id WHERE j.id=?");$q->execute([$jid]);$j=$q->fetch();
$part=$j&&($u['id']==$j['customer_id']||$u['id']==$j['worker_id']);
if(!$j||(!$part&&$u['role']!='admin'))die('Not allowed');
$d=$pdo->prepare("SELECT * FROM disputes WHERE job_id=? ORDER BY id DESC LIMIT 1");$d->execute([$jid]);$d=$d->fetch();
if($_SERVER['REQUEST_METHOD']=='POST'){$a=$_POST['a']??'';
 if($a=='open'&&$part&&$j['worker_id']&&(!$d||$d['status']!='open')&&in_array($j['status'],['assigned','in_progress','completed'])&&trim($_POST['reason'])!==''){
  $pdo->prepare("INSERT INTO disputes(job_id,opened_by,reason)VALUES(?,?,?)")->execute([$jid,$u['id'],substr($_POST['reason'],0,300)]);$pdo->prepare("UPDATE jobs SET disputed=1 WHERE id=?")->execute([$jid]);
  $other=$u['id']==$j['customer_id']?$j['worker_id']:$j['customer_id'];notify($other,'<span class="material-icons mi">balance</span> A dispute was opened on "'.$j['title'].'". Please add your evidence.',"dispute.php?job=$jid");
  foreach($pdo->query("SELECT id FROM users WHERE role='admin'") as $ad)notify($ad['id'],'<span class="material-icons mi">balance</span> New dispute on job #'.$jid,"dispute.php?job=$jid");logit('Opened dispute on job #'.$jid);}
 if($a=='evidence'&&$part&&$d&&$d['status']=='open'){$ph='';
  if(!empty($_FILES['photo']['name'])&&($i=@getimagesize($_FILES['photo']['tmp_name']))&&$_FILES['photo']['size']<3000000&&in_array($i[2],[IMAGETYPE_JPEG,IMAGETYPE_PNG])){$ph='uploads/ev'.$d['id'].'_'.bin2hex(random_bytes(6)).($i[2]==IMAGETYPE_PNG?'.png':'.jpg');move_uploaded_file($_FILES['photo']['tmp_name'],$ph);}
  if(trim($_POST['note'])!==''||$ph)$pdo->prepare("INSERT INTO dispute_evidence(dispute_id,user_id,note,photo)VALUES(?,?,?,?)")->execute([$d['id'],$u['id'],substr($_POST['note'],0,300),$ph]);}
 if($a=='decide'&&$u['role']=='admin'&&$d&&$d['status']=='open'){
  $dec=in_array($_POST['dec'],['refund','release','dismiss'])?$_POST['dec']:'dismiss';$amt=$j['price']+$j['tip'];
  if($dec=='refund'){if($j['paid']){wallet($pdo,$j['customer_id'],$amt,'Dispute refund for job #'.$jid);$pdo->prepare("UPDATE jobs SET paid=0,refunded=1,commission=0,tip=0,disputed=0 WHERE id=?")->execute([$jid]);}
   else $pdo->prepare("UPDATE jobs SET status='cancelled',disputed=0 WHERE id=?")->execute([$jid]);}
  elseif($dec=='release')$pdo->prepare("UPDATE jobs SET confirmed=1,disputed=0 WHERE id=?")->execute([$jid]);
  else $pdo->prepare("UPDATE jobs SET disputed=0 WHERE id=?")->execute([$jid]);
  $pdo->prepare("UPDATE disputes SET status='resolved',decision=?,admin_note=? WHERE id=?")->execute([$dec,substr($_POST['note'],0,200),$d['id']]);
  foreach([$j['customer_id'],$j['worker_id']] as $p)if($p)notify($p,'<span class="material-icons mi">balance</span> Dispute on "'.$j['title'].'" decided: '.$dec.'. '.substr($_POST['note'],0,100),"dispute.php?job=$jid");logit('Dispute on job #'.$jid.' decided: '.$dec);}
 header("Location: dispute.php?job=$jid");exit;}
head('Dispute');?>
<div class="card"><h2><span class="material-icons mi">balance</span> Dispute – <?=e($j['title'])?></h2><p>Customer: <?=e($j['cn'])?> · Worker: <?=e($j['wn']??'—')?> · Job status: <b><?=e($j['status'])?></b> · ₹<?=$j['price']?><?=$j['paid']?' (paid'.($j['escrow']&&!$j['confirmed']?', held in escrow':'').')':''?></p>
<?php if(!$d||$d['status']!='open'){if($d)echo '<p>Last dispute: <b>'.e($d['decision']).'</b> – '.e($d['admin_note']).'</p>';
 if($part&&$j['worker_id']&&in_array($j['status'],['assigned','in_progress','completed']))echo '<form method="post"><input type="hidden" name="a" value="open"><textarea name="reason" rows="3" maxlength="300" placeholder="What is the problem?" required></textarea><button style="background:#c00">Open dispute</button><small> The payment is put on hold until the admin decides.</small></form>';
}else{echo '<p><b>Reason:</b> '.e($d['reason']).' <small>('.$d['created'].')</small></p><h3>Evidence</h3>';
 $ev=$pdo->prepare("SELECT e.*,u.name FROM dispute_evidence e JOIN users u ON u.id=e.user_id WHERE dispute_id=? ORDER BY e.id");$ev->execute([$d['id']]);
 foreach($ev as $x)echo '<div class="card" style="margin:6px 0"><b>'.e($x['name']).'</b> <small>'.$x['created'].'</small><br>'.e($x['note']).($x['photo']?'<br><img class="p" src="'.e($x['photo']).'" style="max-height:200px">':'').'</div>';
 if($part)echo '<form method="post" enctype="multipart/form-data"><input type="hidden" name="a" value="evidence"><textarea name="note" rows="2" maxlength="300" placeholder="Add your side of the story"></textarea><input type="file" name="photo" accept="image/*"><button>Add evidence</button></form>';
 if($u['role']=='admin')echo '<form method="post"><input type="hidden" name="a" value="decide"><h3>Admin decision</h3><input name="note" placeholder="Note for both people" required><button name="dec" value="refund" style="background:#c00">Refund customer</button> <button name="dec" value="release">Release to worker</button> <button name="dec" value="dismiss" style="background:#777">Dismiss</button></form>';}?></div>
<?php foot();

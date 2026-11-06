<?php // Pay by scanning UPI QR code(s). Platform mode: one QR to the platform UPI (admin confirms). Split mode: QR 1 = worker's share to the worker's own UPI ID, QR 2 = platform fee (10%) to the UPI ID in the admin profile; the worker and the admin each confirm.
require 'config.php';need('customer');$u=me();$qid=(int)($_GET['q']??0);$err='';
$s=$pdo->prepare("SELECT q.*,j.title,j.paid,w.name wn,w.upi_id wupi FROM qr_payments q JOIN jobs j ON j.id=q.job_id LEFT JOIN users w ON w.id=j.worker_id WHERE q.id=? AND q.user_id=?");$s->execute([$qid,$u['id']]);$q=$s->fetch();
if(!$q||($q['paid']&&$q['status']!='approved'))die('Nothing to pay. <a href="dashboard.php">Back</a>');
$split=$q['mode']=='split';$fee=(int)round($q['amount']*0.10);$share=$q['amount']-$fee;$pu=platformUpi($pdo);
$lk=function($pa,$pn,$am,$tn,$tr){return 'upi://pay?pa='.rawurlencode($pa).'&pn='.rawurlencode($pn).'&am='.number_format($am,2,'.','').'&cu=INR&tn='.rawurlencode($tn).'&tr='.$tr;};
if($_SERVER['REQUEST_METHOD']=='POST'&&$q['status']=='waiting'){
 if($split){$a=trim($_POST['worker_utr']??'');$b=trim($_POST['fee_utr']??'');
  if(!preg_match('/^\d{12}$/',$a)||!preg_match('/^\d{12}$/',$b))$err='Enter both 12-digit UPI reference numbers (UTR).';
  elseif($a===$b)$err='The two reference numbers must be different.';
  else{$d=$pdo->prepare("SELECT 1 FROM qr_payments WHERE id<>? AND status IN('submitted','approved') AND (utr IN(?,?) OR worker_utr IN(?,?) OR fee_utr IN(?,?))");$d->execute([$qid,$a,$b,$a,$b,$a,$b]);
   if($d->fetch())$err='One of these reference numbers was already used.';
   else{$pdo->prepare("UPDATE qr_payments SET worker_utr=?,fee_utr=?,status='submitted' WHERE id=?")->execute([$a,$b,$qid]);
    $jw=$pdo->prepare("SELECT worker_id FROM jobs WHERE id=?");$jw->execute([$q['job_id']]);notify((int)$jw->fetchColumn(),'📱 '.$u['name'].' says they paid you ₹'.$share.' by UPI (ref '.$a.'). Please check your UPI app and confirm.','upi_confirm.php');
    foreach($pdo->query("SELECT id FROM users WHERE role='admin'") as $ad)notify($ad['id'],'📱 Platform fee ₹'.$fee.' paid by UPI for job #'.$q['job_id'].' (ref '.$b.'). Please confirm.','qr_payments.php');
    logit('Submitted split UPI payment for job #'.$q['job_id']);header("Location: pay_qr.php?q=$qid");exit;}}}
 else{$utr=trim($_POST['utr']??'');
  if(!preg_match('/^\d{12}$/',$utr))$err='Enter the 12-digit UPI reference number (UTR) shown in your payment app.';
  else{$d=$pdo->prepare("SELECT 1 FROM qr_payments WHERE utr=? AND status IN('submitted','approved')");$d->execute([$utr]);
   if($d->fetch())$err='This reference number was already used.';
   else{$pdo->prepare("UPDATE qr_payments SET utr=?,status='submitted' WHERE id=?")->execute([$utr,$qid]);
    foreach($pdo->query("SELECT id FROM users WHERE role='admin'") as $ad)notify($ad['id'],'📱 QR payment of ₹'.$q['amount'].' to verify (job #'.$q['job_id'].')','qr_payments.php');
    logit('Submitted UPI QR payment reference for job #'.$q['job_id']);header("Location: pay_qr.php?q=$qid");exit;}}}}
$parts=$split?[['1️⃣ Pay the worker','₹'.$share,'to '.$q['wn'].' ('.$q['wupi'].')',$lk($q['wupi'],$q['wn'],$share,'Job #'.$q['job_id'],'WS'.$qid)],['2️⃣ Pay the platform fee (10%)','₹'.$fee,'to '.PLATFORM_NAME.' ('.$pu.')',$lk($pu,PLATFORM_NAME,$fee,'Platform fee job #'.$q['job_id'],'WF'.$qid)]]
 :[['Scan &amp; pay','₹'.$q['amount'],'to '.PLATFORM_NAME.' ('.$pu.')',$lk($pu,PLATFORM_NAME,$q['amount'],'Job #'.$q['job_id'],'WQR'.$qid)]];
head('Scan & Pay');?>
<div class="card" style="max-width:520px;margin:auto;text-align:center"><h2>📱 <?=$split?'Pay directly with 2 QR codes':'Scan &amp; Pay'?> – ₹<?=$q['amount']?></h2><p><b><?=e($q['title'])?></b></p>
<table style="text-align:left"><tr><td>Amount you pay</td><td><b>₹<?=$q['amount']?></b></td></tr><tr><td>Platform fee (10%) – <?=$split?'goes to the platform UPI':'kept by '.e(PLATFORM_NAME)?></td><td>₹<?=$fee?></td></tr><tr><td>Worker receives<?=$split?' (directly)':''?></td><td>₹<?=$share?></td></tr></table>
<?php if($q['status']=='waiting'){
 if($pu==='yourname@upi'||!validUpi($pu))echo '<p class="err"><small>Demo QR: the admin must add a real UPI ID in "My UPI ID" (or PLATFORM_UPI in settings.php) before real use.</small></p>';
 foreach($parts as $i=>$p)echo '<hr><h3>'.$p[0].' '.$p[1].'</h3><small>'.e($p[2]).'</small><div id="qr'.$i.'" class="qrb" style="margin:10px auto;max-width:230px"></div><a class="btn" href="'.e($p[3]).'">Open my UPI app (on phone)</a>';?>
<style>.qrb svg{width:100%;height:auto}</style><p><small>Open PhonePe, Google Pay, Paytm or any UPI app → Scan QR → pay exactly the amount shown.</small></p>
<form method="post"><p class="err"><?=e($err)?></p>
<?php if($split){?><input name="worker_utr" inputmode="numeric" maxlength="12" pattern="[0-9]{12}" placeholder="UPI reference (UTR) of payment 1 – worker" required><input name="fee_utr" inputmode="numeric" maxlength="12" pattern="[0-9]{12}" placeholder="UPI reference (UTR) of payment 2 – platform fee" required>
<?php }else{?><input name="utr" inputmode="numeric" maxlength="12" pattern="[0-9]{12}" placeholder="12-digit UPI reference number (UTR)" required><?php }?><button>✅ I have paid</button></form>
<script src="qrcode.js"></script><script>var L=<?=json_encode(array_column($parts,3))?>;L.forEach(function(t,i){var qr=qrcode(0,"M");qr.addData(t);qr.make();document.getElementById("qr"+i).innerHTML=qr.createSvgTag(6,0)})</script>
<?php }elseif($q['status']=='submitted'){?><p class="ok">⏳ Payment submitted<?=$split?'':' (reference '.e($q['utr']).')'?>. <?=$split?'Waiting for confirmation – worker: '.($q['worker_ok']?'✅':'⏳').' · platform: '.($q['fee_ok']?'✅':'⏳'):'The admin will confirm it shortly'?>. You will get a notification.</p><a class="btn" href="job.php?id=<?=$q['job_id']?>">Back to job</a>
<?php }elseif($q['status']=='approved'){?><p class="ok">✅ Payment confirmed. Thank you!</p><a class="btn" href="job.php?id=<?=$q['job_id']?>">Back to job</a>
<?php }else{?><p class="err">This payment was not accepted.</p><a class="btn" href="pay.php?id=<?=$q['job_id']?>">Try again</a><?php }?></div><?php foot();

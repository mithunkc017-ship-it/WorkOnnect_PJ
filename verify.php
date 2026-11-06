<?php // Worker uploads ID proof to request verification
require 'config.php';need('worker');$u=me();$msg='';
$r=$pdo->prepare("SELECT verified FROM users WHERE id=?");$r->execute([$u['id']]);$ver=$r->fetchColumn();
$s=$pdo->prepare("SELECT * FROM verifications WHERE user_id=? ORDER BY id DESC LIMIT 1");$s->execute([$u['id']]);$v=$s->fetch();
if($_SERVER['REQUEST_METHOD']=='POST'&&!$ver&&(!$v||$v['status']!='pending')){
 $f=$_FILES['doc']??null;
 if($f&&$f['name']&&($i=@getimagesize($f['tmp_name']))&&$f['size']<3000000&&in_array($i[2],[IMAGETYPE_JPEG,IMAGETYPE_PNG])){
  $name='uploads/id/'.bin2hex(random_bytes(8)).($i[2]==IMAGETYPE_PNG?'.png':'.jpg');
  move_uploaded_file($f['tmp_name'],$name);
  $pdo->prepare("INSERT INTO verifications(user_id,doc)VALUES(?,?)")->execute([$u['id'],$name]);
  foreach($pdo->query("SELECT id FROM users WHERE role='admin'") as $a)notify($a['id'],$u['name'].' requested verification','verifications.php');
  header('Location: verify.php');exit;}
 $msg='Please upload a clear JPG or PNG image under 3 MB.';}
head('Get Verified');?>
<div class="card" style="max-width:520px;margin:auto"><h2>✔ Get Verified</h2>
<?php if($ver)echo '<p>'.badge(1).' You are a verified worker.</p>';
elseif($v&&$v['status']=='pending')echo '<p>⏳ Your request is pending. The admin will review it soon.</p>';
else{if($v&&$v['status']=='rejected')echo '<p class="err">Rejected: '.e($v['note']).'. You can apply again.</p>';?>
<p>Upload a photo of a valid ID proof (JPG/PNG). Only the admin can see it. It is used only to verify you.</p><p class="err"><?=e($msg)?></p>
<form method="post" enctype="multipart/form-data" style="box-shadow:none;padding:0"><input type="file" name="doc" accept="image/png,image/jpeg" required><button>Submit for Verification</button></form><?php }?></div>
<?php foot();

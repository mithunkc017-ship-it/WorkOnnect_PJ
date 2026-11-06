<?php // My UPI ID. Worker: receives the worker's share directly from customers (split payment) and is the account the admin pays when settling. Admin: receives the platform fee (commission) and QR payments.
require 'config.php';need();$u=me();if($u['role']=='customer'){header('Location: dashboard.php');exit;}$msg='';$err='';
if($_SERVER['REQUEST_METHOD']=='POST'){$v=trim($_POST['upi']??'');
 if($v!==''&&!validUpi($v))$err='Enter a valid UPI ID like name@okhdfcbank';
 else{$pdo->prepare("UPDATE users SET upi_id=? WHERE id=?")->execute([$v!==''?$v:null,$u['id']]);$u['upi_id']=$v;$msg='UPI ID saved ✔';logit('Updated UPI ID');}}
head('My UPI ID');?><form class="card" method="post" style="max-width:520px;margin:auto"><h2>💳 My UPI ID</h2><p class="ok"><?=e($msg)?></p><p class="err"><?=e($err)?></p>
<p><small><?=$u['role']=='admin'?'The platform fee (10% commission) from customer payments and the QR payments are received on this UPI ID. It is shown to customers when they scan the QR code.':'Customers can pay your share directly to this UPI ID (the platform fee is paid separately to the platform). The admin also uses it to pay your earnings.'?></small></p>
<input name="upi" value="<?=e($u['upi_id']??'')?>" placeholder="yourname@okhdfcbank" autocomplete="off"><button>Save UPI ID</button></form><?php foot();

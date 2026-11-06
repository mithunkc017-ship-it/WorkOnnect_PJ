<?php // Admin approves / rejects verification requests
require 'config.php';need('admin');
if($_SERVER['REQUEST_METHOD']=='POST'){
 $s=$pdo->prepare("SELECT * FROM verifications WHERE id=? AND status='pending'");$s->execute([(int)$_POST['id']]);$v=$s->fetch();
 if($v){$ok=$_POST['a']=='approve';
  $pdo->prepare("UPDATE verifications SET status=?,note=? WHERE id=?")->execute([$ok?'approved':'rejected',substr($_POST['note'],0,200),$v['id']]);
  if($ok)$pdo->prepare("UPDATE users SET verified=1 WHERE id=?")->execute([$v['user_id']]);logit(($ok?'Approved':'Rejected').' verification of user #'.$v['user_id']);
  notify($v['user_id'],$ok?'✔ You are now a verified worker!':'Verification rejected: '.$_POST['note'],'verify.php');}
 header('Location: verifications.php');exit;}
head('Verifications');?><h2>✔ Verification Requests</h2>
<table><tr><th>Worker</th><th>Skill</th><th>ID proof</th><th>Status</th><th>Action</th></tr>
<?php foreach($pdo->query("SELECT v.*,u.name,u.skill FROM verifications v JOIN users u ON u.id=v.user_id ORDER BY v.status='pending' DESC,v.id DESC") as $x){
echo '<tr><td>'.e($x['name']).'</td><td>'.e($x['skill']).'</td><td><a target="_blank" href="viewdoc.php?id='.$x['id'].'">View</a></td><td>'.$x['status'].'</td><td>';
if($x['status']=='pending')echo '<form method="post"><input type="hidden" name="id" value="'.$x['id'].'"><input name="note" placeholder="Note (for reject)"><button name="a" value="approve">Approve</button> <button name="a" value="reject" style="background:#c00">Reject</button></form>';
echo '</td></tr>';}?></table><?php foot();

<?php // Admin: view and resolve complaints
require 'config.php';need('admin');
if(isset($_POST['refund'])){$s=$pdo->prepare("SELECT * FROM jobs WHERE id=? AND paid=1");$s->execute([(int)$_POST['refund']]);$jb=$s->fetch();
 if($jb){$amt=$jb['price']+$jb['tip'];wallet($pdo,$jb['customer_id'],$amt,'Refund for job #'.$jb['id']);$pdo->prepare("UPDATE jobs SET paid=0,refunded=1,commission=0,tip=0 WHERE id=?")->execute([$jb['id']]);
  notify($jb['customer_id'],'Refund of ₹'.$amt.' added to your wallet','wallet.php');notify($jb['worker_id'],'Job "'.$jb['title'].'" was refunded by the admin','job.php?id='.$jb['id']);logit('Refunded job #'.$jb['id']);}
 header('Location: complaints.php');exit;}
if(isset($_POST['r'])){$s=$pdo->prepare("SELECT user_id,job_id FROM complaints WHERE id=?");$s->execute([(int)$_POST['r']]);$c=$s->fetch();
 $pdo->prepare("UPDATE complaints SET status='resolved' WHERE id=?")->execute([(int)$_POST['r']]);
 if($c)notify($c['user_id'],'Your complaint on job #'.$c['job_id'].' was resolved','job.php?id='.$c['job_id']);header('Location: complaints.php');exit;}
head('Complaints');?><h2><span class="material-icons mi">flag</span> Complaints</h2><table><tr><th>Job</th><th>By</th><th>Problem</th><th>Status</th><th></th></tr>
<?php foreach($pdo->query("SELECT c.*,u.name,j.paid jp FROM complaints c JOIN users u ON u.id=c.user_id JOIN jobs j ON j.id=c.job_id ORDER BY c.status,c.id DESC") as $x)
echo '<tr><td><a href="job.php?id='.$x['job_id'].'">#'.$x['job_id'].'</a></td><td>'.e($x['name']).'</td><td>'.e($x['msg']).'</td><td>'.$x['status'].'</td><td>'.($x['status']=='open'?'<form method="post"><input type="hidden" name="r" value="'.$x['id'].'"><button>Resolve</button></form>':'').($x['jp']?'<form method="post"><input type="hidden" name="refund" value="'.$x['job_id'].'"><button style="background:#c00">Refund</button></form>':'').'</td></tr>';?></table>
<?php foot();

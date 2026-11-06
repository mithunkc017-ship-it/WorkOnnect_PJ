<?php // Worker earnings dashboard
require 'config.php';need('worker');$u=me();
function sum($pdo,$sql,$id){$s=$pdo->prepare($sql);$s->execute([$id]);return (int)$s->fetchColumn();}
head('Earnings');echo d_top('earnings.php');?>
<h2><span class="material-icons mi">payments</span> My Earnings</h2><div class="grid">
<div class="card">Total earned (after 10% fee)<h2>₹<?=sum($pdo,"SELECT SUM(price-commission+tip) FROM jobs WHERE worker_id=? AND paid=1 AND (escrow=0 OR confirmed=1) AND disputed=0",$u['id'])?></h2></div>
<div class="card">Pending payment<h2>₹<?=sum($pdo,"SELECT SUM(price) FROM jobs WHERE worker_id=? AND (paid=0 OR (escrow=1 AND confirmed=0)) AND refunded=0 AND status<>'cancelled'",$u['id'])?></h2></div>
<div class="card">Jobs completed<h2><?=sum($pdo,"SELECT COUNT(*) FROM jobs WHERE worker_id=? AND status='completed'",$u['id'])?></h2></div>
<div class="card">This month<h2>₹<?=sum($pdo,"SELECT SUM(price-commission+tip) FROM jobs WHERE worker_id=? AND paid=1 AND (escrow=0 OR confirmed=1) AND disputed=0 AND MONTH(created)=MONTH(NOW()) AND YEAR(created)=YEAR(NOW())",$u['id'])?></h2></div></div>
<div class="card"><b>Earnings by month</b>
<?php $s=$pdo->prepare("SELECT DATE_FORMAT(created,'%b %Y') m,SUM(price-commission+tip) t FROM jobs WHERE worker_id=? AND paid=1 AND (escrow=0 OR confirmed=1) AND disputed=0 GROUP BY m ORDER BY MIN(created) DESC LIMIT 6");$s->execute([$u['id']]);
foreach($s as $x)echo '<div>'.$x['m'].' <span style="display:inline-block;background:#FF9800;height:12px;width:'.min(60,$x['t']/50).'%"></span> ₹'.$x['t'].'</div>';?></div>
<h3><span class="material-icons mi">attach_money</span> Payment status</h3><div style="overflow-x:auto"><table><tr><th>Job</th><th>Your share</th><th>Status</th></tr>
<?php $s=$pdo->prepare("SELECT id,title,price-commission+tip amt,settled,settled_at,settle_ref FROM jobs WHERE worker_id=? AND paid=1 AND refunded=0 AND disputed=0 AND (escrow=0 OR confirmed=1) ORDER BY id DESC LIMIT 30");$s->execute([$u['id']]);
foreach($s as $x)echo '<tr><td><a href="job.php?id='.$x['id'].'">'.e($x['title']).'</a></td><td>₹'.$x['amt'].'</td><td>'.($x['settled']?'<span class="ok"><span class="material-icons mi-xs">check_circle</span> Paid on '.date('d M Y',strtotime($x['settled_at'])).' (ref '.e($x['settle_ref']).')</span>':'<span class="material-icons mi-xs">hourglass_empty</span> Pending – the admin will pay you').'</td></tr>';?></table></div>
<?php foot();

<?php // Public worker profile with rating and reviews
require 'config.php';$id=(int)($_GET['id']??0);
$s=$pdo->prepare("SELECT u.*,(SELECT ROUND(AVG(stars),1) FROM reviews WHERE worker_id=u.id) r,(SELECT COUNT(*) FROM reviews WHERE worker_id=u.id) rc,(SELECT COUNT(*) FROM jobs WHERE worker_id=u.id AND status='completed') done FROM users u WHERE id=? AND role='worker'");
$s->execute([$id]);$w=$s->fetch();if(!$w)die('Worker not found');
$u=me();$isf=false;
if($u&&$u['role']=='customer'){$f=$pdo->prepare("SELECT 1 FROM favorites WHERE customer_id=? AND worker_id=?");$f->execute([$u['id'],$id]);$isf=$f->fetch();
 if($_SERVER['REQUEST_METHOD']=='POST'){if($isf)$pdo->prepare("DELETE FROM favorites WHERE customer_id=? AND worker_id=?")->execute([$u['id'],$id]);else $pdo->prepare("INSERT INTO favorites(customer_id,worker_id)VALUES(?,?)")->execute([$u['id'],$id]);header("Location: worker.php?id=$id");exit;}}
head($w['name']);?>
<div class="card"><h2><span class="material-icons mi">engineering</span> <?=e($w['name'])?> <?=badge($w['verified'])?> <?=top($w['r'],$w['rc'])?> <?=level($w['done'],$w['r'])?></h2>
<?php if($w['photo'])echo '<img class="p" style="max-height:120px" src="'.e($w['photo']).'">';?>
<p>Experience: <b><?=e($w['experience']?:'Not added')?></b></p><p><?=e($w['bio'])?></p><p><span class="material-icons mi">schedule</span> <?=e($w['work_days']?:'Any day')?> <?=$w['work_from']?substr($w['work_from'],0,5).'–'.substr($w['work_to'],0,5):''?> · <span class="material-icons mi">place</span> <?=e($w['area']?:'Area not set')?></p>
<p><?=$w['available']?'<span class="dot-green"></span> Available':'<span class="dot-red"></span> Busy'?> · Skill: <b><?=e($w['skill'])?></b> · Jobs completed: <b><?=$w['done']?></b></p>
<p>Rating: <b><?=$w['r']?$w['r'].' <span class="material-icons mi-xs">star</span> ('.$w['rc'].' reviews)':'No reviews yet'?></b></p><?php include 'worker_stats.php';?><?php if($u&&$u['role']=='customer')echo '<a class="btn" href="hire.php?w='.$id.'"><span class="material-icons mi">handshake</span> Hire this worker</a> <form method="post" style="display:inline"><button>'.($isf?'<span class="material-icons mi">heart_broken</span> Remove favorite':'<span class="material-icons mi">favorite</span>️ Save as favorite').'</button></form>';?></div>
<?php $pp=$pdo->prepare("SELECT * FROM service_prices WHERE worker_id=?");$pp->execute([$id]);$pp=$pp->fetchAll();
if($pp){echo '<h3><span class="material-icons mi">payments</span> Price List</h3><table>';foreach($pp as $x)echo '<tr><td>'.e($x['service']).'</td><td>₹'.$x['price'].'</td></tr>';echo '</table>';}
$pf=$pdo->prepare("SELECT * FROM portfolio WHERE worker_id=?");$pf->execute([$id]);$pf=$pf->fetchAll();
if($pf){echo '<h3><span class="material-icons mi">photo_library</span> Portfolio</h3><div class="grid">';foreach($pf as $x)echo '<div class="card"><img class="p" src="'.e($x['photo']).'"><br>'.e($x['caption']).'</div>';echo '</div>';}?>
<?php $ce=$pdo->prepare("SELECT * FROM certificates WHERE worker_id=? AND status='approved'");$ce->execute([$id]);foreach($ce as $x)echo '<p><span class="material-icons mi">school</span> <b>Certified:</b> '.e($x['title']).' <a href="'.e($x['photo']).'" target="_blank">view</a></p>';?>
<h3>Reviews</h3>
<?php $r=$pdo->prepare("SELECT r.*,u.name FROM reviews r JOIN users u ON u.id=r.customer_id WHERE worker_id=? ORDER BY r.id DESC");$r->execute([$id]);
foreach($r as $x)echo '<div class="card" style="margin:8px 0">'.str_repeat('<span class="material-icons mi-xs">star</span>',$x['stars']).' <b>'.e($x['name']).'</b><br>'.e($x['comment']).($x['reply']?'<div style="margin:8px 0 0 14px;border-left:3px solid #ccc;padding-left:8px"><small><span class="material-icons mi">chat</span> Reply from the worker:</small><br>'.e($x['reply']).'</div>':(($u&&$u['id']==$id)?'<form method="post" action="review_reply.php" style="display:flex;gap:8px;margin-top:8px"><input type="hidden" name="rid" value="'.$x['id'].'"><input name="reply" maxlength="300" placeholder="Write a reply" required><button>Reply</button></form>':'')).'</div>';
foot();

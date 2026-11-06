<?php // Saves a complaint about a job
require 'config.php';need();$u=me();$id=(int)($_GET['id']??0);
$q=$pdo->prepare("SELECT * FROM jobs WHERE id=? AND (customer_id=? OR worker_id=?)");$q->execute([$id,$u['id'],$u['id']]);$j=$q->fetch();
if($j&&$_SERVER['REQUEST_METHOD']=='POST'&&trim($_POST['msg'])!==''){
 $pdo->prepare("INSERT INTO complaints(job_id,user_id,msg)VALUES(?,?,?)")->execute([$id,$u['id'],substr($_POST['msg'],0,300)]);
 foreach($pdo->query("SELECT id FROM users WHERE role='admin'") as $a)notify($a['id'],'New complaint on job "'.$j['title'].'"','complaints.php');}
header("Location: job.php?id=$id");

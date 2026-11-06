<?php // Customer books the same job again (to the same worker if the job was completed)
require 'config.php';need('customer');$u=me();$id=(int)($_GET['id']??0);
$q=$pdo->prepare("SELECT * FROM jobs WHERE id=? AND customer_id=? AND status IN('completed','cancelled')");$q->execute([$id,$u['id']]);$j=$q->fetch();
if($j&&$_SERVER['REQUEST_METHOD']=='POST'){
 $same=$j['status']=='completed'&&$j['worker_id'];
 $pdo->prepare("INSERT INTO jobs(customer_id,title,category,details,amount,location,phone,price,status,worker_id)VALUES(?,?,?,?,?,?,?,?,?,?)")
 ->execute([$u['id'],$j['title'],$j['category'],$j['details'],$j['amount'],$j['location'],$j['phone'],$same?$j['price']+$j['discount']:0,$same?'requested':'open',$same?$j['worker_id']:null]);
 $new=$pdo->lastInsertId();if($same)notify($j['worker_id'],$u['name'].' booked you again: "'.$j['title'].'"',"job.php?id=$new");
 logit('Repeat booking of job #'.$id);header("Location: job.php?id=$new");exit;}
header('Location: dashboard.php');

<?php // Live worker location: the worker posts coordinates (POST), the customer reads them (GET, JSON)
require 'config.php';need();$u=me();header('Content-Type: application/json');
if($_SERVER['REQUEST_METHOD']=='POST'){$id=(int)$_POST['job'];$la=(float)$_POST['lat'];$ln=(float)$_POST['lng'];
 if($u['role']=='worker'&&abs($la)<=90&&abs($ln)<=180)$pdo->prepare("UPDATE jobs SET wlat=?,wlng=?,wtime=NOW() WHERE id=? AND worker_id=? AND status IN('assigned','in_progress')")->execute([$la,$ln,$id,$u['id']]);
 echo json_encode(['ok'=>1]);exit;}
$s=$pdo->prepare("SELECT customer_id,worker_id,wlat,wlng,TIMESTAMPDIFF(SECOND,wtime,NOW()) age,status FROM jobs WHERE id=?");$s->execute([(int)($_GET['id']??0)]);$j=$s->fetch();
if(!$j||(!in_array($u['id'],[$j['customer_id'],$j['worker_id']])&&$u['role']!='admin')||$j['wlat']===null||!in_array($j['status'],['assigned','in_progress'])||$j['age']>600){echo json_encode([]);exit;}
echo json_encode(['lat'=>(float)$j['wlat'],'lng'=>(float)$j['wlng'],'age'=>(int)$j['age']]);

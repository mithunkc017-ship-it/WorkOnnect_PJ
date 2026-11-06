<?php // Admin: download data as a CSV file (?t=users | jobs | payments); open in Excel
require 'config.php';need('admin');$t=$_GET['t']??'';
$Q=['users'=>"SELECT id,name,email,phone,role,skill,verified,banned FROM users",'jobs'=>"SELECT id,title,category,location,status,price,paid,created FROM jobs",'payments'=>"SELECT id,title,price,discount,commission,pay_method,created FROM jobs WHERE paid=1"];
if(!isset($Q[$t]))die('Unknown export');
ob_end_clean();header('Content-Type: text/csv');header("Content-Disposition: attachment; filename=$t.csv");
$o=fopen('php://output','w');$first=true;
foreach($pdo->query($Q[$t]) as $row){if($first){fputcsv($o,array_keys($row));$first=false;}fputcsv($o,$row);}

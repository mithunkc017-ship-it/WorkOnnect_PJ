<?php // Shows an ID proof image to the admin only
require 'config.php';need('admin'); // ID proofs are served only to the admin
$s=$pdo->prepare("SELECT doc FROM verifications WHERE id=?");$s->execute([(int)($_GET['id']??0)]);$d=$s->fetchColumn();
if(!$d||!is_file($d))die('Not found');
ob_end_clean();header('Content-Type: '.getimagesize($d)['mime']);readfile($d);

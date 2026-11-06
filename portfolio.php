<?php // Worker: manage portfolio photos and the service price list
require 'config.php';need('worker');$u=me();
if($_SERVER['REQUEST_METHOD']=='POST'){$a=$_POST['a']??'';
 if($a=='photo'&&!empty($_FILES['photo']['name'])&&($i=@getimagesize($_FILES['photo']['tmp_name']))&&$_FILES['photo']['size']<2000000&&in_array($i[2],[IMAGETYPE_JPEG,IMAGETYPE_PNG])){
  $f='uploads/w'.$u['id'].'_'.bin2hex(random_bytes(6)).($i[2]==IMAGETYPE_PNG?'.png':'.jpg');move_uploaded_file($_FILES['photo']['tmp_name'],$f);
  $pdo->prepare("INSERT INTO portfolio(worker_id,photo,caption)VALUES(?,?,?)")->execute([$u['id'],$f,substr($_POST['caption'],0,100)]);}
 if($a=='delphoto')$pdo->prepare("DELETE FROM portfolio WHERE id=? AND worker_id=?")->execute([(int)$_POST['id'],$u['id']]);
 if($a=='price'&&(int)$_POST['price']>0)$pdo->prepare("INSERT INTO service_prices(worker_id,service,price)VALUES(?,?,?)")->execute([$u['id'],substr($_POST['service'],0,60),(int)$_POST['price']]);
 if($a=='delprice')$pdo->prepare("DELETE FROM service_prices WHERE id=? AND worker_id=?")->execute([(int)$_POST['id'],$u['id']]);
 header('Location: portfolio.php');exit;}
head('My Work');?>
<h2><span class="material-icons mi">payments</span> My Price List</h2>
<form method="post" style="display:flex;gap:8px"><input type="hidden" name="a" value="price"><input name="service" placeholder="Service (e.g. Fan repair)" required><input type="number" name="price" min="1" placeholder="₹ price" required><button>Add</button></form>
<table><?php $s=$pdo->prepare("SELECT * FROM service_prices WHERE worker_id=?");$s->execute([$u['id']]);
foreach($s as $x)echo '<tr><td>'.e($x['service']).'</td><td>₹'.$x['price'].'</td><td><form method="post"><input type="hidden" name="a" value="delprice"><input type="hidden" name="id" value="'.$x['id'].'"><button style="background:#c00">Delete</button></form></td></tr>';?></table>
<h2><span class="material-icons mi">photo_library</span> My Portfolio</h2>
<form method="post" enctype="multipart/form-data" style="display:flex;gap:8px"><input type="hidden" name="a" value="photo"><input type="file" name="photo" accept="image/png,image/jpeg" required><input name="caption" placeholder="Caption"><button>Upload</button></form>
<div class="grid"><?php $s=$pdo->prepare("SELECT * FROM portfolio WHERE worker_id=?");$s->execute([$u['id']]);
foreach($s as $x)echo '<div class="card"><img class="p" src="'.e($x['photo']).'"><br>'.e($x['caption']).'<form method="post"><input type="hidden" name="a" value="delphoto"><input type="hidden" name="id" value="'.$x['id'].'"><button style="background:#c00">Delete</button></form></div>';?></div>
<?php foot();

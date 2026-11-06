<?php // Admin: add / delete service categories
require 'config.php';need('admin');
if(isset($_POST['add'])&&trim($_POST['add'])!=='')$pdo->prepare("INSERT IGNORE INTO categories(name)VALUES(?)")->execute([trim($_POST['add'])]);
if(isset($_POST['del']))$pdo->prepare("DELETE FROM categories WHERE name=?")->execute([$_POST['del']]);
if($_POST){header('Location: categories.php');exit;}
head('Categories');?><h2><span class="material-icons mi">handyman</span> Manage Service Categories</h2>
<form method="post" style="display:flex;gap:8px"><input name="add" placeholder="New service (e.g. Welder)" required><button>Add</button></form>
<div class="grid"><?php foreach($CATS as $c)echo '<div class="card">'.e($c).'<form method="post"><input type="hidden" name="del" value="'.e($c).'"><button style="background:#c00">Delete</button></form></div>';?></div>
<?php foot();

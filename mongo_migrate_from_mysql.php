<?php // Command line only. Copies the data of your OLD MySQL database into MongoDB (ids are kept, so links between records stay valid).
//   php mongo_migrate_from_mysql.php [mysql_host] [mysql_db] [mysql_user] [mysql_password]
//   defaults:                         localhost    workerconnect  root         (empty)
// Needs the PHP pdo_mysql extension (only for this one-off script). Existing documents with the same id are skipped.
if(PHP_SAPI!=='cli')die('Run this from the command line.');
require_once __DIR__.'/settings.php';require_once __DIR__.'/mongo_db.php';
if(!defined('MONGO_URI'))define('MONGO_URI','mongodb://127.0.0.1:27017');if(!defined('MONGO_DB'))define('MONGO_DB','workonet');
[$h,$d,$u,$p]=[$argv[1]??'localhost',$argv[2]??'workerconnect',$argv[3]??'root',$argv[4]??''];
$my=new PDO("mysql:host=$h;dbname=$d;charset=utf8mb4",$u,$p,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$store=new MongoStore(MONGO_URI,MONGO_DB);$store->ensureSchema();$mg=new MongoPDO($store);
foreach($store->schema() as $t=>$def){
 try{$rows=$my->query("SELECT * FROM `$t`")->fetchAll();}catch(Exception $e){echo str_pad($t,18)."not in MySQL, skipped\n";continue;}
 if($t==='categories'&&$rows)$mg->exec("DELETE FROM categories"); // replace the default seed by your own list
 $ok=0;$skip=0;
 foreach($rows as $r){
  $cols=array_values(array_intersect(array_keys($r),array_keys($def['cols'])));
  try{$mg->prepare("INSERT INTO `$t`(".implode(',',$cols).")VALUES(".implode(',',array_fill(0,count($cols),'?')).")")->execute(array_map(function($c)use($r){return $r[$c];},$cols));$ok++;}
  catch(Exception $e){$skip++;}
 }
 $store->syncCounter($t);
 echo str_pad($t,18).str_pad("$ok copied",14).($skip?"$skip skipped (already there / duplicate)":'')."\n";
}
echo "Done. You can now stop using MySQL for this project.\n";

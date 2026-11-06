<?php // One-time MongoDB setup: creates the indexes (UNIQUE keys etc.) and the default service categories.
// Run it from the browser (http://localhost/workonet/mongo_setup.php) or:  php mongo_setup.php
// It is safe to run again. (config.php also does this automatically on the first visit of every session.)
require_once __DIR__.'/settings.php';
require_once __DIR__.'/mongo_db.php';
if(!defined('MONGO_URI'))define('MONGO_URI','mongodb://127.0.0.1:27017');
if(!defined('MONGO_DB'))define('MONGO_DB','workonet');
header('Content-Type: text/plain; charset=utf-8');
try{
 $s=new MongoStore(MONGO_URI,MONGO_DB);$s->ensureSchema();
 echo "OK - MongoDB database \"".MONGO_DB."\" is ready (".count($s->schema())." collections, indexes created).\n";
 echo "Next: open the site, register, then make yourself admin (see README.txt, 'MongoDB setup').\n";
}catch(Exception $e){http_response_code(500);echo "FAILED: ".$e->getMessage()."\n";}

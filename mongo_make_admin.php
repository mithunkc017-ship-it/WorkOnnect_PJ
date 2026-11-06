<?php // Command line only:  php mongo_make_admin.php your@email.com   -> gives that (already registered) account the admin role
if (PHP_SAPI !== "cli") {
    die(
        "Run this from the command line: php mongo_make_admin.php your@email.com"
    );
}
require_once __DIR__ . "/settings.php";
require_once __DIR__ . "/mongo_db.php";
if (!defined("MONGO_URI")) {
    define("MONGO_URI", "mongodb://127.0.0.1:27017");
}
if (!defined("MONGO_DB")) {
    define("MONGO_DB", "workonet");
}
$em = $argv[1] ?? "";
if (!filter_var($em, FILTER_VALIDATE_EMAIL)) {
    die("Usage: php mongo_make_admin.php your@email.com\n");
}
$pdo = new MongoPDO(new MongoStore(MONGO_URI, MONGO_DB));
$r = $pdo->prepare("UPDATE users SET role='admin' WHERE email=?");
$r->execute([$em]);
echo $r->rowCount()
    ? "Done: $em is now an admin.\n"
    : "No change: register $em on the website first (or it is already an admin).\n";

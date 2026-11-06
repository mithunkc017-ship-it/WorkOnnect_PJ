<?php // Customer address book (Home, Office...) used as quick options when posting a job
require "config.php";
need("customer");
$u = me();
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST["address"]) && trim($_POST["address"]) !== "") {
        $pdo->prepare(
            "INSERT INTO addresses(user_id,label,address)VALUES(?,?,?)",
        )->execute([
            $u["id"],
            substr($_POST["label"], 0, 30),
            substr($_POST["address"], 0, 150),
        ]);
    }
    if (isset($_POST["del"])) {
        $pdo->prepare(
            "DELETE FROM addresses WHERE id=? AND user_id=?",
        )->execute([(int) $_POST["del"], $u["id"]]);
    }
    header("Location: addresses.php");
    exit();
}
head("Addresses");
?><h2><span class="material-icons mi">place</span> My Addresses</h2>
<form method="post" style="display:flex;gap:8px"><input name="label" placeholder="Label (Home / Office)" required><input name="address" placeholder="Full address" required><button>Save</button></form>
<div class="grid"><?php
$s = $pdo->prepare("SELECT * FROM addresses WHERE user_id=?");
$s->execute([$u["id"]]);
foreach ($s as $x) {
    echo '<div class="card"><b>' .
        e($x["label"]) .
        "</b><br>" .
        e($x["address"]) .
        '<form method="post"><button name="del" value="' .
        $x["id"] .
        '" style="background:#c00">Delete</button></form></div>';
}
?></div><?php foot();

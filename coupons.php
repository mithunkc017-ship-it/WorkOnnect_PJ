<?php // Admin: create, enable/disable and delete discount coupons
require "config.php";
need("admin");
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (
        isset($_POST["code"]) &&
        trim($_POST["code"]) !== "" &&
        (int) $_POST["percent"] > 0 &&
        (int) $_POST["percent"] <= 90
    ) {
        $pdo->prepare(
            "INSERT IGNORE INTO coupons(code,percent)VALUES(?,?)",
        )->execute([strtoupper(trim($_POST["code"])), (int) $_POST["percent"]]);
    }
    if (isset($_POST["toggle"])) {
        $pdo->prepare(
            "UPDATE coupons SET active=1-active WHERE code=?",
        )->execute([$_POST["toggle"]]);
    }
    if (isset($_POST["del"])) {
        $pdo->prepare("DELETE FROM coupons WHERE code=?")->execute([
            $_POST["del"],
        ]);
    }
    header("Location: coupons.php");
    exit();
}
head("Coupons");
?><h2><span class="material-icons mi">confirmation_number</span> Coupons</h2>
<form method="post" style="display:flex;gap:8px"><input name="code" placeholder="Code (e.g. FIRST50)" required><input type="number" name="percent" min="1" max="90" placeholder="Discount %" required><button>Create</button></form>
<table><tr><th>Code</th><th>Discount</th><th>Used</th><th>Status</th><th></th></tr>
<?php foreach ($pdo->query("SELECT * FROM coupons ORDER BY code") as $x) {
    echo "<tr><td>" .
        e($x["code"]) .
        "</td><td>" .
        $x["percent"] .
        "%</td><td>" .
        $x["used"] .
        "</td><td>" .
        ($x["active"] ? "Active" : "Disabled") .
        '</td><td><form method="post" style="display:flex;gap:6px"><button name="toggle" value="' .
        e($x["code"]) .
        '">' .
        ($x["active"] ? "Disable" : "Enable") .
        '</button><button name="del" value="' .
        e($x["code"]) .
        '" style="background:#c00">Delete</button></form></td></tr>';
} ?></table>
<?php foot();

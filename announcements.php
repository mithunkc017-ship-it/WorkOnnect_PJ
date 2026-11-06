<?php // Admin: announcement banner shown at the top of every page (users can close it)
require "config.php";
need("admin");
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST["msg"]) && trim($_POST["msg"]) !== "") {
        $pdo->prepare("UPDATE announcements SET active=0")->execute();
        $pdo->prepare(
            "INSERT INTO announcements(msg,link)VALUES(?,?)",
        )->execute([
            substr(trim($_POST["msg"]), 0, 200),
            substr(trim($_POST["link"] ?? ""), 0, 100),
        ]);
        logit("Published an announcement");
    }
    if (isset($_POST["off"])) {
        $pdo->prepare("UPDATE announcements SET active=0 WHERE id=?")->execute([
            (int) $_POST["off"],
        ]);
    }
    if (isset($_POST["del"])) {
        $pdo->prepare("DELETE FROM announcements WHERE id=?")->execute([
            (int) $_POST["del"],
        ]);
    }
    header("Location: announcements.php");
    exit();
}
head("Announcements");
?><h2><span class="material-icons mi">campaign</span> Announcements</h2>
<form class="card" method="post"><p><small>The newest active announcement is shown to everyone. Publishing a new one switches the old one off.</small></p><input name="msg" maxlength="200" placeholder="Message (e.g. Festival offer: use code DIWALI10 for 10% off)" required><input name="link" placeholder="Optional link (e.g. help.php)"><button>Publish</button></form>
<table><tr><th>Message</th><th>Status</th><th></th></tr><?php foreach (
    $pdo->query("SELECT * FROM announcements ORDER BY id DESC LIMIT 20")
    as $x
) {
    echo "<tr><td>" .
        e($x["msg"]) .
        "</td><td>" .
        ($x["active"] ? "Live" : "Off") .
        '</td><td><form method="post" style="display:flex;gap:6px">' .
        ($x["active"]
            ? '<button name="off" value="' . $x["id"] . '">Switch off</button>'
            : "") .
        '<button name="del" value="' .
        $x["id"] .
        '" style="background:#c00">Delete</button></form></td></tr>';
} ?></table><?php foot();

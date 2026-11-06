<?php // Worker replies once to a review on their own profile
require "config.php";
need("worker");
$u = me();
if (
    $_SERVER["REQUEST_METHOD"] == "POST" &&
    trim($_POST["reply"] ?? "") !== ""
) {
    $r = $pdo->prepare(
        "SELECT * FROM reviews WHERE id=? AND worker_id=? AND reply IS NULL",
    );
    $r->execute([(int) $_POST["rid"], $u["id"]]);
    $r = $r->fetch();
    if ($r) {
        $pdo->prepare(
            "UPDATE reviews SET reply=?,reply_at=NOW() WHERE id=?",
        )->execute([substr(trim($_POST["reply"]), 0, 300), $r["id"]]);
        notify(
            $r["customer_id"],
            '<span class="material-icons mi">chat</span> ' .
                $u["name"] .
                " replied to your review",
            "worker.php?id=" . $u["id"],
        );
    }
}
header("Location: worker.php?id=" . $u["id"]);

<?php // Customer edits an open job
require "config.php";
need("customer");
$u = me();
$id = (int) ($_GET["id"] ?? 0);
$q = $pdo->prepare(
    "SELECT * FROM jobs WHERE id=? AND customer_id=? AND status='open'",
);
$q->execute([$id, $u["id"]]);
$j = $q->fetch();
if (!$j) {
    die('Job cannot be edited. <a href="dashboard.php">Back</a>');
}
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $pdo->prepare(
        "UPDATE jobs SET title=?,amount=?,details=?,location=?,phone=? WHERE id=?",
    )->execute([
        $_POST["title"],
        $_POST["amount"],
        $_POST["details"],
        $_POST["location"],
        $_POST["phone"],
        $id,
    ]);
    header("Location: job.php?id=$id");
    exit();
}
head("Edit Job");
?>
<form class="card" method="post"><h2>Edit Job</h2>
<input name="title" value="<?= e(
    $j["title"],
) ?>" required><input name="amount" value="<?= e($j["amount"]) ?>" required>
<textarea name="details" rows="4" required><?= e($j["details"]) ?></textarea>
<input name="location" value="<?= e(
    $j["location"],
) ?>" required><input name="phone" value="<?= e($j["phone"]) ?>" required>
<button>Save Changes</button></form><?php foot();

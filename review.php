<?php // Saves a customer review for a worker
require "config.php";
need("customer");
$u = me();
$id = (int) ($_GET["id"] ?? 0);
$q = $pdo->prepare(
    "SELECT * FROM jobs WHERE id=? AND customer_id=? AND status='completed' AND worker_id IS NOT NULL",
);
$q->execute([$id, $u["id"]]);
$j = $q->fetch();
if ($j && $_SERVER["REQUEST_METHOD"] == "POST") {
    $st = max(1, min(5, (int) $_POST["stars"]));
    try {
        $pdo->prepare(
            "INSERT INTO reviews(job_id,worker_id,customer_id,stars,comment)VALUES(?,?,?,?,?)",
        )->execute([
            $id,
            $j["worker_id"],
            $u["id"],
            $st,
            substr($_POST["comment"], 0, 255),
        ]);
        notify(
            $j["worker_id"],
            "You got a " .
                $st .
                '<span class="material-icons mi-xs">star</span> review for "' .
                $j["title"] .
                '"',
            "worker.php?id=" . $j["worker_id"],
        );
    } catch (Exception $x) {
    } // one review per job
}
header("Location: job.php?id=$id");

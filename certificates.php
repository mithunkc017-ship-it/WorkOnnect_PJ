<?php // Skill certificates: workers upload, admin approves -> "Certified" badge on the public profile
require "config.php";
need();
$u = me();
$a = $_POST["a"] ?? "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (
        $u["role"] == "worker" &&
        $a == "add" &&
        trim($_POST["title"]) !== "" &&
        !empty($_FILES["photo"]["name"]) &&
        ($i = @getimagesize($_FILES["photo"]["tmp_name"])) &&
        $_FILES["photo"]["size"] < 3000000 &&
        in_array($i[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG])
    ) {
        $f =
            "uploads/cert_" .
            $u["id"] .
            "_" .
            bin2hex(random_bytes(6)) .
            ($i[2] == IMAGETYPE_PNG ? ".png" : ".jpg");
        move_uploaded_file($_FILES["photo"]["tmp_name"], $f);
        $pdo->prepare(
            "INSERT INTO certificates(worker_id,title,photo)VALUES(?,?,?)",
        )->execute([$u["id"], substr($_POST["title"], 0, 100), $f]);
        foreach (
            $pdo->query("SELECT id FROM users WHERE role='admin'")
            as $ad
        ) {
            notify(
                $ad["id"],
                $u["name"] . " uploaded a certificate",
                "certificates.php",
            );
        }
    }
    if ($u["role"] == "worker" && $a == "del") {
        $pdo->prepare(
            "DELETE FROM certificates WHERE id=? AND worker_id=?",
        )->execute([(int) $_POST["id"], $u["id"]]);
    }
    if ($u["role"] == "admin" && $a == "review") {
        $ok = $_POST["r"] == "approve";
        $s = $pdo->prepare("SELECT * FROM certificates WHERE id=?");
        $s->execute([(int) $_POST["id"]]);
        $c = $s->fetch();
        if ($c) {
            $pdo->prepare(
                "UPDATE certificates SET status=? WHERE id=?",
            )->execute([$ok ? "approved" : "rejected", $c["id"]]);
            notify(
                $c["worker_id"],
                ($ok
                    ? '<span class="material-icons mi">school</span> Your certificate was approved: '
                    : "Your certificate was rejected: ") . $c["title"],
                "certificates.php",
            );
            logit(
                ($ok ? "Approved" : "Rejected") . " certificate #" . $c["id"],
            );
        }
    }
    header("Location: certificates.php");
    exit();
}
head("Certificates");
?><h2><span class="material-icons mi">school</span> Skill Certificates</h2>
<?php
if (
    $u["role"] == "worker"
) { ?><form class="card" method="post" enctype="multipart/form-data"><input type="hidden" name="a" value="add"><input name="title" placeholder="Certificate name (e.g. ITI Electrician)" required><input type="file" name="photo" accept="image/png,image/jpeg" required><button>Upload for review</button></form><?php }
$s =
    $u["role"] == "admin"
        ? $pdo->query(
            "SELECT c.*,u.name FROM certificates c JOIN users u ON u.id=c.worker_id ORDER BY c.status='pending' DESC,c.id DESC LIMIT 100",
        )
        : $pdo->prepare(
            "SELECT c.*,'' name FROM certificates c WHERE worker_id=? ORDER BY id DESC",
        );
if ($u["role"] != "admin") {
    $s->execute([$u["id"]]);
}
?>
<div style="overflow-x:auto"><table><tr><th>Worker</th><th>Certificate</th><th>Status</th><th></th></tr>
<?php foreach ($s as $x) {
    echo "<tr><td>" .
        e($x["name"]) .
        "</td><td>" .
        e($x["title"]) .
        ' <a href="' .
        e($x["photo"]) .
        '" target="_blank">view</a></td><td>' .
        $x["status"] .
        "</td><td>";
    if ($u["role"] == "admin" && $x["status"] == "pending") {
        echo '<form method="post"><input type="hidden" name="a" value="review"><input type="hidden" name="id" value="' .
            $x["id"] .
            '"><button name="r" value="approve">Approve</button> <button name="r" value="reject" style="background:#c00">Reject</button></form>';
    }
    if ($u["role"] == "worker") {
        echo '<form method="post"><input type="hidden" name="a" value="del"><input type="hidden" name="id" value="' .
            $x["id"] .
            '"><button style="background:#c00">Delete</button></form>';
    }
    echo "</td></tr>";
} ?></table></div><?php foot();

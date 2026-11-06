<?php // POST actions for job.php: quote, extra work, on the way, SOS, share link (included by job.php)
$cu = $j["customer_id"] == $u["id"];
$wk = $j["worker_id"] == $u["id"];
$acc = ($_POST["r"] ?? "") == "accept";
if (
    $a == "quote" &&
    $wk &&
    !$j["paid"] &&
    $j["status"] == "assigned" &&
    (int) $_POST["lab"] >= 0 &&
    (int) $_POST["mat"] >= 0 &&
    (int) $_POST["lab"] + (int) $_POST["mat"] > 0
) {
    $pdo->prepare(
        "UPDATE jobs SET quote_labour=?,quote_mat=?,quote_status='proposed' WHERE id=?",
    )->execute([(int) $_POST["lab"], (int) $_POST["mat"], $id]);
    notify(
        $j["customer_id"],
        '<span class="material-icons mi-xs">edit_note</span> Quote received for "' .
            $j["title"] .
            '"',
        "job.php?id=$id",
    );
}
if ($a == "qa" && $cu && !$j["paid"] && $j["quote_status"] == "proposed") {
    if ($acc) {
        $pdo->prepare(
            "UPDATE jobs SET quote_status='approved',price=quote_labour+quote_mat WHERE id=?",
        )->execute([$id]);
    } else {
        $pdo->prepare(
            "UPDATE jobs SET quote_status='none' WHERE id=?",
        )->execute([$id]);
    }
    notify(
        $j["worker_id"],
        "Your quote was " . ($acc ? "approved" : "declined"),
        "job.php?id=$id",
    );
}
if (
    $a == "extra" &&
    $wk &&
    in_array($j["status"], ["assigned", "in_progress"]) &&
    !$j["paid"] &&
    (int) $_POST["amt"] > 0
) {
    $pdo->prepare(
        "INSERT INTO job_extras(job_id,descr,amount)VALUES(?,?,?)",
    )->execute([$id, substr($_POST["descr"], 0, 100), (int) $_POST["amt"]]);
    notify(
        $j["customer_id"],
        '<span class="material-icons mi-xs">add</span> Extra work proposed on "' .
            $j["title"] .
            '"',
        "job.php?id=$id",
    );
}
if ($a == "ex" && $cu && !$j["paid"]) {
    $s = $pdo->prepare(
        "SELECT * FROM job_extras WHERE id=? AND job_id=? AND status='proposed'",
    );
    $s->execute([(int) $_POST["id"], $id]);
    $x = $s->fetch();
    if ($x) {
        $pdo->prepare("UPDATE job_extras SET status=? WHERE id=?")->execute([
            $acc ? "approved" : "declined",
            $x["id"],
        ]);
        if ($acc) {
            $pdo->prepare("UPDATE jobs SET price=price+? WHERE id=?")->execute([
                $x["amount"],
                $id,
            ]);
        }
        notify(
            $j["worker_id"],
            'Extra work "' .
                $x["descr"] .
                '" was ' .
                ($acc ? "approved" : "declined"),
            "job.php?id=$id",
        );
    }
}
if ($a == "onway" && $wk && $j["status"] == "assigned") {
    $pdo->prepare("UPDATE jobs SET onway=1 WHERE id=?")->execute([$id]);
    notify(
        $j["customer_id"],
        '<span class="material-icons mi">directions_car</span> Your worker is on the way for "' .
            $j["title"] .
            '"',
        "job.php?id=$id",
    );
}
if (
    $a == "wphoto" &&
    $wk &&
    in_array($j["status"], ["assigned", "in_progress", "completed"]) &&
    in_array($_POST["kind"] ?? "", ["before", "after"]) &&
    !empty($_FILES["photo"]["name"]) &&
    ($ii = @getimagesize($_FILES["photo"]["tmp_name"])) &&
    $_FILES["photo"]["size"] < 3000000 &&
    in_array($ii[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG])
) {
    $f =
        "uploads/wp" .
        $id .
        "_" .
        bin2hex(random_bytes(6)) .
        ($ii[2] == IMAGETYPE_PNG ? ".png" : ".jpg");
    move_uploaded_file($_FILES["photo"]["tmp_name"], $f);
    $pdo->prepare(
        "INSERT INTO job_photos(job_id,user_id,kind,photo)VALUES(?,?,?,?)",
    )->execute([$id, $u["id"], $_POST["kind"], $f]);
    if ($_POST["kind"] == "after") {
        notify(
            $j["customer_id"],
            '<span class="material-icons mi">photo_camera</span> The worker added an "after" photo for "' .
                $j["title"] .
                '"',
            "job.php?id=$id",
        );
    }
}
if ($a == "wphotodel" && $wk && $j["status"] != "completed") {
    $pdo->prepare(
        "DELETE FROM job_photos WHERE id=? AND job_id=? AND user_id=?",
    )->execute([(int) $_POST["pid"], $id, $u["id"]]);
}
if ($a == "sos" && ($cu || $wk)) {
    foreach ($pdo->query("SELECT id FROM users WHERE role='admin'") as $ad) {
        notify(
            $ad["id"],
            '<span class="material-icons mi-xs">sos</span> SOS alert on job #' .
                $id .
                ' ("' .
                $j["title"] .
                '")',
            "job.php?id=$id",
        );
    }
    logit("SOS alert on job #" . $id);
}
if ($a == "share" && ($cu || $wk) && !$j["share_token"]) {
    $pdo->prepare("UPDATE jobs SET share_token=? WHERE id=?")->execute([
        bin2hex(random_bytes(8)),
        $id,
    ]);
}

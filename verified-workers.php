<?php
require "config.php";
$workers = $pdo
    ->query(
        "SELECT u.id,u.name,u.skill,u.area,u.available,
            (SELECT ROUND(AVG(stars),1) FROM reviews WHERE worker_id=u.id) rating,
            (SELECT COUNT(*) FROM reviews WHERE worker_id=u.id) rc
         FROM users u
         WHERE u.role='worker' AND u.verified=1
         ORDER BY rating DESC, rc DESC LIMIT 12",
    )
    ->fetchAll();
ob_start();
head("Verified Workers");
$pageHead = ob_get_clean();
$pageHead = preg_replace(
    '#<nav>.*?</nav>#s',
    '<nav aria-label="Page header"><a class="logo" href="index.php">Work<span>onnect</span></a></nav>',
    $pageHead,
    1,
    $navReplaced,
);
if ($navReplaced !== 1) {
    throw new RuntimeException("Could not simplify the Verified Workers page header.");
}
echo $pageHead;
?>
<section aria-labelledby="workers-title">
    <h1 id="workers-title">Meet verified local workers</h1>
    <p>Browse workers whose submitted ID has been approved. Check each profile for skills and more details before you book.</p>

    <?php if ($workers) { ?>
        <div class="grid">
            <?php foreach ($workers as $worker) { ?>
                <article class="card">
                    <h2><?= e($worker["name"]) ?> <?= badge(1) ?></h2>
                    <p><?= e($worker["skill"]) ?></p>
                    <?php if ($worker["area"]) { ?>
                        <p><span class="material-icons mi-xs" aria-hidden="true">place</span><?= e(
                            $worker["area"],
                        ) ?></p>
                    <?php } ?>
                    <p>
                        <?php if ($worker["rating"]) { ?>
                            <span class="material-icons mi-xs" aria-hidden="true">star</span>
                            <?= e($worker["rating"]) ?> · <?= (int) $worker[
     "rc"
 ] ?> reviews
                        <?php } else { ?>
                            New to Workonnect
                        <?php } ?>
                    </p>
                    <p><?= $worker["available"]
                        ? "Available for requests"
                        : "Currently busy" ?></p>
                    <a class="btn" href="worker.php?id=<?= (int) $worker[
                        "id"
                    ] ?>">View Profile</a>
                </article>
            <?php } ?>
        </div>
    <?php } else { ?>
        <div class="card">
            <h2>More verified profiles are on the way</h2>
            <p>There are no verified worker profiles to show right now. You can still browse all worker profiles.</p>
            <a class="btn" href="workers.php">Browse Workers</a>
        </div>
    <?php } ?>
</section>
<?php foot(); ?>

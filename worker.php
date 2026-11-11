<?php // Public worker profile with rating and reviews
require "config.php";
$id = (int) ($_GET["id"] ?? 0);
$s = $pdo->prepare(
    "SELECT u.*,(SELECT ROUND(AVG(stars),1) FROM reviews WHERE worker_id=u.id) r,(SELECT COUNT(*) FROM reviews WHERE worker_id=u.id) rc,(SELECT COUNT(*) FROM jobs WHERE worker_id=u.id AND status='completed') done FROM users u WHERE id=? AND role='worker'",
);
$s->execute([$id]);
$w = $s->fetch();
if (!$w) {
    die("Worker not found");
}
$u = me();
$isf = false;
if ($u && $u["role"] == "customer") {
    $f = $pdo->prepare(
        "SELECT 1 FROM favorites WHERE customer_id=? AND worker_id=?",
    );
    $f->execute([$u["id"], $id]);
    $isf = $f->fetch();
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        if ($isf) {
            $pdo->prepare(
                "DELETE FROM favorites WHERE customer_id=? AND worker_id=?",
            )->execute([$u["id"], $id]);
        } else {
            $pdo->prepare(
                "INSERT INTO favorites(customer_id,worker_id)VALUES(?,?)",
            )->execute([$u["id"], $id]);
        }
        header("Location: worker.php?id=$id");
        exit();
    }
}
ob_start();
head($w["name"]);
$pageHead = ob_get_clean();
$pageHead = preg_replace(
    '#<nav>.*?</nav>#s',
    '<nav aria-label="Page header"><a class="logo" href="index.php">Work<span>onnect</span></a></nav>',
    $pageHead,
    1,
    $navReplaced,
);
if ($navReplaced !== 1) {
    throw new RuntimeException("Could not simplify the Worker Profile page header.");
}
echo $pageHead;
$wkInitial = function_exists("mb_substr")
    ? mb_strtoupper(mb_substr((string) $w["name"], 0, 1))
    : strtoupper(substr((string) $w["name"], 0, 1));
?>
<style>
/* ===== Worker profile – simple, compact, blue theme ===== */
.wk-profile {
    /* Change this one colour to re-theme the whole page. */
    --wk-a: #2563eb;
    --wk-soft: color-mix(in srgb, var(--wk-a) 7%, #ffffff);
    --wk-mid:  color-mix(in srgb, var(--wk-a) 28%, #ffffff);
    --wk-b:    #38bdf8;
    --wk-ink: #0f172a;
    --wk-muted: #5b6678;
    --wk-line: #e5e9f0;
    max-width: 820px;
    margin: 16px auto 36px;
    padding: 0 clamp(12px, 3vw, 20px);
}
.wk-profile * { box-sizing: border-box; }
/* one size for every icon on this page */
.wk-profile .material-icons { font-size: 18px; vertical-align: middle; }
.wk-profile .mi-xs { font-size: 15px; }

@keyframes wk-fade-up {
    from { opacity: 0; transform: translateY(8px); }
    to   { opacity: 1; transform: translateY(0); }
}
@keyframes wk-ping-g {
    0%   { box-shadow: 0 0 0 0 rgba(34,197,94,.5); }
    100% { box-shadow: 0 0 0 7px rgba(34,197,94,0); }
}
@keyframes wk-ping-r {
    0%   { box-shadow: 0 0 0 0 rgba(239,68,68,.4); }
    100% { box-shadow: 0 0 0 6px rgba(239,68,68,0); }
}

/* ---------- hero ---------- */
.wk-profile .wk-hero {
    margin: 0 0 8px;
    padding: 0;
    border-radius: 16px;
    background: #fff;
    border: 1px solid var(--wk-line);
    box-shadow: 0 8px 24px -18px color-mix(in srgb, var(--wk-a) 50%, transparent);
    overflow: hidden;
    animation: wk-fade-up .4s ease both;
}
.wk-profile .wk-head {
    display: flex;
    gap: 16px;
    align-items: center;
    padding: 22px clamp(16px, 3vw, 26px) 0;
}
.wk-profile .wk-avatar {
    flex: 0 0 auto;
    width: 76px;
    height: 76px;
    border-radius: 50%;
    border: 3px solid #fff;
    box-shadow: 0 0 0 2px var(--wk-mid);
    object-fit: cover;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.9rem;
    font-weight: 700;
    color: #fff;
    background: linear-gradient(135deg, var(--wk-a), var(--wk-b));
}
.wk-profile .wk-id { min-width: 0; }
.wk-profile .wk-id h2 {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 6px;
    margin: 0 0 6px;
    font-size: clamp(1.25rem, 3.2vw, 1.55rem);
    letter-spacing: -.01em;
    line-height: 1.25;
    color: var(--wk-ink);
}
.wk-profile .wk-id h2 .material-icons { color: var(--wk-a); }
.wk-profile .wk-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    align-items: center;
}
.wk-profile .wk-chip {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 11px;
    border-radius: 999px;
    font-size: .82rem;
    font-weight: 600;
    color: var(--wk-a);
    background: var(--wk-soft);
    border: 1px solid var(--wk-mid);
}
.wk-profile .wk-chip.wk-status { color: var(--wk-ink); background: #fff; border-color: var(--wk-line); }
.wk-profile .dot-green { animation: wk-ping-g 2s ease-out infinite; }
.wk-profile .dot-red   { animation: wk-ping-r 2.6s ease-out infinite; }
.wk-profile .wk-rating { margin: 8px 0 0; font-size: .9rem; color: var(--wk-muted); }
.wk-profile .wk-rating .material-icons { color: #f59e0b; }

.wk-profile .wk-body { padding: 16px clamp(16px, 3vw, 26px) 22px; }
.wk-profile .wk-bio {
    margin: 0 0 16px;
    line-height: 1.65;
    color: var(--wk-muted);
    font-size: .96rem;
    max-width: 70ch;
}

/* key facts */
.wk-profile .wk-info {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 10px;
    margin: 0 0 18px;
}
.wk-profile .wk-tile {
    display: flex;
    gap: 10px;
    align-items: center;
    padding: 10px 12px;
    border-radius: 12px;
    background: var(--wk-soft);
    border: 1px solid transparent;
    transition: border-color .2s ease;
}
.wk-profile .wk-tile:hover { border-color: var(--wk-mid); }
.wk-profile .wk-tile .material-icons {
    flex: 0 0 auto;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 30px;
    height: 30px;
    border-radius: 9px;
    font-size: 17px;
    color: var(--wk-a);
    background: #fff;
    border: 1px solid var(--wk-mid);
}
.wk-profile .wk-tile small {
    display: block;
    font-size: .72rem;
    color: var(--wk-muted);
}
.wk-profile .wk-tile b { font-size: .92rem; color: var(--wk-ink); word-break: break-word; }

/* action buttons: small, simple */
.wk-profile .wk-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
}
.wk-profile .wk-actions form { display: inline-flex; margin: 0; }
.wk-profile .wk-actions .btn,
.wk-profile .wk-actions button,
.wk-profile .wk-actions .wk-ghost {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 7px 14px;
    border-radius: 8px;
    font: inherit;
    font-size: .86rem;
    font-weight: 600;
    line-height: 1.3;
    text-decoration: none;
    cursor: pointer;
    transition: background .2s ease, border-color .2s ease;
}
.wk-profile .wk-actions .btn {
    border: 1px solid var(--wk-a);
    color: #fff;
    background: var(--wk-a);
}
.wk-profile .wk-actions .btn:hover { background: color-mix(in srgb, var(--wk-a) 82%, #000); }
.wk-profile .wk-actions button,
.wk-profile .wk-actions .wk-ghost {
    color: var(--wk-ink);
    background: #fff;
    border: 1px solid var(--wk-line);
}
.wk-profile .wk-actions button:hover,
.wk-profile .wk-actions .wk-ghost:hover {
    background: var(--wk-soft);
    border-color: var(--wk-mid);
}
.wk-profile .wk-actions .material-icons { font-size: 16px; color: var(--wk-a); }
.wk-profile .wk-actions .btn .material-icons { color: #fff; }
.wk-profile .wk-actions form button .material-icons { color: #e11d48; }

/* ---------- section headings ---------- */
.wk-profile h3 {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 26px 0 12px;
    font-size: 1.08rem;
    color: var(--wk-ink);
}
.wk-profile h3 .material-icons {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 8px;
    font-size: 16px;
    color: var(--wk-a);
    background: var(--wk-soft);
    border: 1px solid var(--wk-mid);
}
.wk-profile h3::after {
    content: "";
    flex: 1 1 auto;
    height: 1px;
    background: var(--wk-line);
}

/* price list */
.wk-profile .wk-table-wrap {
    overflow-x: auto;
    border-radius: 12px;
    border: 1px solid var(--wk-line);
    background: #fff;
}
.wk-profile .wk-table-wrap table { width: 100%; border-collapse: collapse; }
.wk-profile .wk-table-wrap td { padding: 10px 14px; border-top: 1px solid var(--wk-line); font-size: .92rem; color: var(--wk-ink); }
.wk-profile .wk-table-wrap tr:first-child td { border-top: 0; }
.wk-profile .wk-table-wrap td:last-child { text-align: right; font-weight: 700; color: var(--wk-a); white-space: nowrap; }
.wk-profile .wk-table-wrap tr:hover { background: var(--wk-soft); }

/* portfolio */
.wk-profile .grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
    gap: 12px;
}
.wk-profile .wk-work {
    margin: 0;
    padding: 0;
    overflow: hidden;
    border-radius: 12px;
    background: #fff;
    border: 1px solid var(--wk-line);
    font-size: .88rem;
    color: var(--wk-muted);
    transition: box-shadow .25s ease;
    animation: wk-fade-up .4s ease both;
}
.wk-profile .wk-work:hover { box-shadow: 0 10px 22px -16px color-mix(in srgb, var(--wk-a) 60%, transparent); }
.wk-profile .wk-work .wk-imgbox { overflow: hidden; background: var(--wk-soft); }
.wk-profile .wk-work img {
    display: block;
    width: 100%;
    height: 120px;
    object-fit: cover;
    transition: transform .4s ease;
}
.wk-profile .wk-work:hover img { transform: scale(1.04); }
.wk-profile .wk-work .wk-cap { display: block; padding: 8px 12px 12px; line-height: 1.45; }

/* reviews */
.wk-profile .wk-review {
    margin: 0 0 10px !important;
    padding: 12px 14px;
    border-radius: 12px;
    background: #fff;
    border: 1px solid var(--wk-line);
    font-size: .92rem;
    color: var(--wk-muted);
    line-height: 1.55;
}
.wk-profile .wk-review .mi-xs { color: #f59e0b; }
.wk-profile .wk-review b { color: var(--wk-ink); }
.wk-profile .wk-review form { align-items: center; }
.wk-profile .wk-review form input {
    flex: 1 1 auto;
    min-width: 0;
    padding: 7px 11px;
    border-radius: 8px;
    border: 1px solid var(--wk-line);
    font: inherit;
    font-size: .88rem;
}
.wk-profile .wk-review form input:focus {
    outline: none;
    border-color: var(--wk-a);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--wk-a) 15%, transparent);
}
.wk-profile .wk-review form button {
    padding: 7px 14px;
    border: 1px solid var(--wk-a);
    border-radius: 8px;
    font: inherit;
    font-size: .86rem;
    font-weight: 600;
    color: #fff;
    cursor: pointer;
    background: var(--wk-a);
}
.wk-profile .wk-review form button:hover { background: color-mix(in srgb, var(--wk-a) 82%, #000); }

.wk-profile a:focus-visible,
.wk-profile button:focus-visible,
.wk-profile input:focus-visible {
    outline: 2px solid var(--wk-a);
    outline-offset: 2px;
}
@media (max-width: 640px) {
    .wk-profile .wk-head { flex-direction: column; text-align: center; gap: 10px; }
    .wk-profile .wk-id h2, .wk-profile .wk-chips { justify-content: center; }
    .wk-profile .wk-actions { justify-content: center; }
}
@media (prefers-reduced-motion: reduce) {
    .wk-profile *, .wk-profile *::before, .wk-profile *::after {
        animation: none !important;
        transition: none !important;
    }
}
</style>

<div class="wk-profile">
<div class="card wk-hero">
<div class="wk-head">
<?php if ($w["photo"]) {
    echo '<img class="wk-avatar" src="' . e($w["photo"]) . '" alt="' . e($w["name"]) . '">';
} else {
    echo '<span class="wk-avatar" aria-hidden="true">' . e($wkInitial) . "</span>";
} ?>
<div class="wk-id">
<h2><span class="material-icons mi">engineering</span> <?= e(
    $w["name"],
) ?> <?= badge($w["verified"]) ?> <?= top($w["r"], $w["rc"]) ?> <?= level(
     $w["done"],
     $w["r"],
 ) ?></h2>
<div class="wk-chips"><span class="wk-chip"><?= e($w["skill"]) ?></span><span class="wk-chip wk-status"><?= $w[
    "available"
]
    ? '<span class="dot-green"></span> Available'
    : '<span class="dot-red"></span> Busy' ?></span></div>
<p class="wk-rating">Rating: <b><?= $w["r"]
    ? $w["r"] .
        ' <span class="material-icons mi-xs">star</span> (' .
        $w["rc"] .
        " reviews)"
    : "No reviews yet" ?></b></p>
</div>
</div>
<div class="wk-body">
<p class="wk-bio"><?= e($w["bio"]) ?></p>
<div class="wk-info">
<div class="wk-tile"><span class="material-icons mi">workspace_premium</span><div><small>Experience</small><b><?= e(
    $w["experience"] ?: "Not added",
) ?></b></div></div>
<div class="wk-tile"><span class="material-icons mi">place</span><div><small>Area</small><b><?= e(
    $w["area"] ?: "Area not set",
) ?></b></div></div>
<div class="wk-tile"><span class="material-icons mi">task_alt</span><div><small>Jobs completed</small><b><?= $w[
    "done"
] ?></b></div></div>
</div>
<div class="wk-actions">
<?php if ($u && $u["role"] == "customer") {
    echo '<a class="btn" href="hire.php?w=' .
        $id .
        '"><span class="material-icons mi">handshake</span> Hire this worker</a> <form method="post" style="display:inline"><button>' .
        ($isf
            ? '<span class="material-icons mi">heart_broken</span> Remove favorite'
            : '<span class="material-icons mi">favorite</span> Save as favorite') .
        "</button></form>";
} ?>
<a class="wk-ghost" href="workers.php"><span class="material-icons mi">arrow_back</span> All workers</a>
<button type="button" class="wk-share" id="wkShare"><span class="material-icons mi">share</span> <span class="wk-share-t">Share profile</span></button>
</div>
</div>
</div>
<?php
$pp = $pdo->prepare("SELECT * FROM service_prices WHERE worker_id=?");
$pp->execute([$id]);
$pp = $pp->fetchAll();
if ($pp) {
    echo '<h3><span class="material-icons mi">payments</span> Price List</h3><div class="wk-table-wrap"><table>';
    foreach ($pp as $x) {
        echo "<tr><td>" .
            e($x["service"]) .
            "</td><td>₹" .
            $x["price"] .
            "</td></tr>";
    }
    echo "</table></div>";
}
$pf = $pdo->prepare("SELECT * FROM portfolio WHERE worker_id=?");
$pf->execute([$id]);
$pf = $pf->fetchAll();
if ($pf) {
    echo '<h3><span class="material-icons mi">photo_library</span> Portfolio</h3><div class="grid">';
    $wkI = 0;
    foreach ($pf as $x) {
        echo '<div class="card wk-work" style="animation-delay:' .
            min($wkI++, 10) * 0.06 .
            's"><div class="wk-imgbox"><img class="p" src="' .
            e($x["photo"]) .
            '" alt=""></div><span class="wk-cap">' .
            e($x["caption"]) .
            "</span></div>";
    }
    echo "</div>";
}
?>
<h3><span class="material-icons mi">reviews</span> Reviews</h3>
<?php
$r = $pdo->prepare(
    "SELECT r.*,u.name FROM reviews r JOIN users u ON u.id=r.customer_id WHERE worker_id=? ORDER BY r.id DESC",
);
$r->execute([$id]);
foreach ($r as $x) {
    echo '<div class="card wk-review" style="margin:8px 0">' .
        str_repeat(
            '<span class="material-icons mi-xs">star</span>',
            $x["stars"],
        ) .
        " <b>" .
        e($x["name"]) .
        "</b><br>" .
        e($x["comment"]) .
        ($x["reply"]
            ? '<div style="margin:8px 0 0 14px;border-left:3px solid var(--wk-mid,#ccc);padding-left:8px"><small>Reply from the worker:</small><br>' .
                e($x["reply"]) .
                "</div>"
            : ($u && $u["id"] == $id
                ? '<form method="post" action="review_reply.php" style="display:flex;gap:8px;margin-top:8px"><input type="hidden" name="rid" value="' .
                    $x["id"] .
                    '"><input name="reply" maxlength="300" placeholder="Write a reply" required><button>Reply</button></form>'
                : "")) .
        "</div>";
}
?>
</div>

<script>
/* Cosmetic helper: "Share profile" copies this page's link. */
(function () {
    var b = document.getElementById('wkShare');
    if (!b) return;
    b.addEventListener('click', function () {
        var label = b.querySelector('.wk-share-t');
        var done = function () {
            label.textContent = 'Link copied!';
            setTimeout(function () { label.textContent = 'Share profile'; }, 2000);
        };
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(location.href).then(done, function () {
                window.prompt('Copy this link:', location.href);
            });
        } else {
            window.prompt('Copy this link:', location.href);
        }
    });
})();
</script>
<?php
foot();
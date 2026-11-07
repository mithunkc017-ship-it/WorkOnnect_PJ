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
/* ===== Worker profile – compact, blue theme, animated ===== */
.wk-profile {
    /* Change this one colour to re-theme the whole page. */
    --wk-a: #2563eb;
    --wk-soft: color-mix(in srgb, var(--wk-a) 8%, #ffffff);
    --wk-mid:  color-mix(in srgb, var(--wk-a) 30%, #ffffff);
    --wk-b:    #38bdf8;
    --wk-ink: #0f172a;
    --wk-muted: #5b6678;
    --wk-line: #e3e8f0;
    max-width: 900px;
    margin: 20px auto 40px;
    padding: 0 clamp(12px, 3vw, 24px);
}
@keyframes wk-fade-up {
    from { opacity: 0; transform: translateY(18px); }
    to   { opacity: 1; transform: translateY(0); }
}
@keyframes wk-draw { from { transform: scaleX(0); } to { transform: scaleX(1); } }
@keyframes wk-ring {
    0%   { box-shadow: 0 0 0 0 color-mix(in srgb, var(--wk-a) 45%, transparent); }
    100% { box-shadow: 0 0 0 16px color-mix(in srgb, var(--wk-a) 0%, transparent); }
}
@keyframes wk-ping-g {
    0%   { box-shadow: 0 0 0 0 rgba(34,197,94,.55); }
    100% { box-shadow: 0 0 0 9px rgba(34,197,94,0); }
}
@keyframes wk-ping-r {
    0%   { box-shadow: 0 0 0 0 rgba(239,68,68,.4); }
    100% { box-shadow: 0 0 0 8px rgba(239,68,68,0); }
}

/* ---------- hero ---------- */
.wk-profile .wk-hero {
    position: relative;
    margin: 0 0 8px;
    padding: 0;
    border-radius: 24px;
    background: #fff;
    border: 1px solid var(--wk-line);
    box-shadow: 0 24px 50px -30px color-mix(in srgb, var(--wk-a) 55%, transparent);
    overflow: hidden;
    animation: wk-fade-up .7s cubic-bezier(.2,.7,.2,1) both;
}
.wk-profile .wk-head {
    display: flex;
    gap: 22px;
    align-items: flex-end;
    padding: 28px clamp(18px, 3vw, 32px) 0;
}
.wk-profile .wk-avatar {
    flex: 0 0 auto;
    width: 108px;
    height: 108px;
    border-radius: 50%;
    border: 5px solid #fff;
    object-fit: cover;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.7rem;
    font-weight: 800;
    color: #fff;
    background: linear-gradient(135deg, var(--wk-a), var(--wk-b));
    animation: wk-ring 2.6s ease-out infinite;
}
.wk-profile .wk-id { padding-bottom: 6px; min-width: 0; }
.wk-profile .wk-id h2 {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    margin: 0 0 8px;
    font-size: clamp(1.5rem, 3.6vw, 2rem);
    letter-spacing: -.02em;
    line-height: 1.2;
    color: var(--wk-ink);
}
.wk-profile .wk-id h2 .material-icons { color: var(--wk-a); }
.wk-profile .wk-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
}
.wk-profile .wk-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 13px;
    border-radius: 999px;
    font-size: .92rem;
    font-weight: 600;
    color: var(--wk-a);
    background: var(--wk-soft);
    border: 1px solid var(--wk-mid);
}
.wk-profile .wk-chip.wk-status { color: var(--wk-ink); background: #fff; border-color: var(--wk-line); }
.wk-profile .dot-green { animation: wk-ping-g 2s ease-out infinite; }
.wk-profile .dot-red   { animation: wk-ping-r 2.6s ease-out infinite; }
.wk-profile .wk-rating { margin: 10px 0 0; color: var(--wk-muted); }
.wk-profile .wk-rating .material-icons { color: #f59e0b; }

.wk-profile .wk-body { padding: 20px clamp(18px, 3vw, 32px) clamp(20px, 3vw, 28px); }
.wk-profile .wk-bio {
    margin: 0 0 18px;
    line-height: 1.75;
    color: var(--wk-muted);
    font-size: 1.02rem;
}

/* key facts */
.wk-profile .wk-info {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
    gap: 12px;
    margin: 0 0 22px;
}
.wk-profile .wk-tile {
    display: flex;
    gap: 12px;
    align-items: center;
    padding: 14px 16px;
    border-radius: 16px;
    background: var(--wk-soft);
    border: 1px solid transparent;
    transition: transform .3s ease, border-color .3s ease, box-shadow .3s ease;
    animation: wk-fade-up .6s cubic-bezier(.2,.7,.2,1) both;
}
.wk-profile .wk-tile:nth-child(1) { animation-delay: .15s; }
.wk-profile .wk-tile:nth-child(2) { animation-delay: .22s; }
.wk-profile .wk-tile:nth-child(3) { animation-delay: .29s; }
.wk-profile .wk-tile:hover {
    transform: translateY(-3px);
    border-color: var(--wk-mid);
    box-shadow: 0 12px 24px -16px color-mix(in srgb, var(--wk-a) 60%, transparent);
}
.wk-profile .wk-tile .material-icons {
    flex: 0 0 auto;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border-radius: 12px;
    color: #fff;
    background: linear-gradient(135deg, var(--wk-a), var(--wk-b));
}
.wk-profile .wk-tile small {
    display: block;
    font-size: .76rem;
    text-transform: uppercase;
    letter-spacing: .05em;
    color: var(--wk-muted);
}
.wk-profile .wk-tile b { color: var(--wk-ink); word-break: break-word; }

/* action buttons */
.wk-profile .wk-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    align-items: center;
}
.wk-profile .wk-actions form { display: inline-flex; margin: 0; }
.wk-profile .wk-actions .btn,
.wk-profile .wk-actions button,
.wk-profile .wk-actions .wk-ghost {
    position: relative;
    overflow: hidden;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 24px;
    border-radius: 999px;
    font: inherit;
    font-weight: 700;
    text-decoration: none;
    cursor: pointer;
    transition: transform .3s ease, box-shadow .3s ease, background .3s ease, border-color .3s ease;
}
.wk-profile .wk-actions .btn {
    border: 0;
    color: #fff;
    background: linear-gradient(135deg, var(--wk-a), color-mix(in srgb, var(--wk-a) 70%, #000));
    box-shadow: 0 14px 28px -12px color-mix(in srgb, var(--wk-a) 80%, transparent);
}
.wk-profile .wk-actions .btn::after {
    content: "";
    position: absolute;
    top: 0; left: -80%;
    width: 50%; height: 100%;
    background: linear-gradient(100deg, transparent, rgba(255,255,255,.5), transparent);
    transform: skewX(-20deg);
    transition: left .7s ease;
}
.wk-profile .wk-actions .btn:hover { transform: translateY(-3px); }
.wk-profile .wk-actions .btn:hover::after { left: 130%; }
.wk-profile .wk-actions button,
.wk-profile .wk-actions .wk-ghost {
    color: var(--wk-a);
    background: #fff;
    border: 1.5px solid var(--wk-mid);
}
.wk-profile .wk-actions button:hover,
.wk-profile .wk-actions .wk-ghost:hover {
    transform: translateY(-3px);
    background: var(--wk-soft);
    border-color: var(--wk-a);
}
.wk-profile .wk-actions form button .material-icons { color: #e11d48; }

/* ---------- section headings ---------- */
.wk-profile h3 {
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 32px 0 16px;
    font-size: 1.35rem;
    letter-spacing: -.01em;
    color: var(--wk-ink);
    animation: wk-fade-up .6s cubic-bezier(.2,.7,.2,1) both;
}
.wk-profile h3 .material-icons {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 38px;
    height: 38px;
    border-radius: 12px;
    font-size: 22px;
    color: #fff;
    background: linear-gradient(135deg, var(--wk-a), var(--wk-b));
}
.wk-profile h3::after {
    content: "";
    flex: 1 1 auto;
    height: 3px;
    border-radius: 3px;
    background: linear-gradient(90deg, var(--wk-mid), transparent);
    transform-origin: left;
    animation: wk-draw .8s .2s cubic-bezier(.2,.7,.2,1) both;
}

/* price list */
.wk-profile .wk-table-wrap {
    overflow-x: auto;
    border-radius: 16px;
    border: 1px solid var(--wk-line);
    background: #fff;
    box-shadow: 0 14px 34px -24px color-mix(in srgb, var(--wk-a) 45%, transparent);
    animation: wk-fade-up .6s cubic-bezier(.2,.7,.2,1) both;
}
.wk-profile .wk-table-wrap table { width: 100%; border-collapse: collapse; }
.wk-profile .wk-table-wrap td { padding: 14px 18px; border-top: 1px solid var(--wk-line); color: var(--wk-ink); }
.wk-profile .wk-table-wrap tr:first-child td { border-top: 0; }
.wk-profile .wk-table-wrap td:last-child { text-align: right; font-weight: 800; color: var(--wk-a); white-space: nowrap; }
.wk-profile .wk-table-wrap tr { transition: background .25s ease; }
.wk-profile .wk-table-wrap tr:hover { background: var(--wk-soft); }

/* portfolio */
.wk-profile .grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(210px, 1fr));
    gap: 18px;
}
.wk-profile .wk-work {
    margin: 0;
    padding: 0;
    overflow: hidden;
    border-radius: 18px;
    background: #fff;
    border: 1px solid var(--wk-line);
    color: var(--wk-muted);
    transition: transform .35s cubic-bezier(.2,.7,.2,1), box-shadow .35s ease;
    animation: wk-fade-up .6s cubic-bezier(.2,.7,.2,1) both;
}
.wk-profile .wk-work:hover {
    transform: translateY(-6px);
    box-shadow: 0 20px 36px -20px color-mix(in srgb, var(--wk-a) 60%, transparent);
}
.wk-profile .wk-work .wk-imgbox { overflow: hidden; background: var(--wk-soft); }
.wk-profile .wk-work img {
    display: block;
    width: 100%;
    height: 160px;
    object-fit: cover;
    transition: transform .6s cubic-bezier(.2,.7,.2,1);
}
.wk-profile .wk-work:hover img { transform: scale(1.08); }
.wk-profile .wk-work .wk-cap { display: block; padding: 12px 16px 16px; line-height: 1.5; }

/* reviews */
.wk-profile .wk-review {
    margin: 0 0 14px !important;
    padding: 18px 20px;
    border-radius: 18px;
    background: #fff;
    border: 1px solid var(--wk-line);
    color: var(--wk-muted);
    line-height: 1.65;
    transition: transform .3s ease, box-shadow .3s ease, border-color .3s ease;
    animation: wk-fade-up .6s cubic-bezier(.2,.7,.2,1) both;
}
.wk-profile .wk-review:hover {
    transform: translateY(-3px);
    border-color: var(--wk-mid);
    box-shadow: 0 16px 30px -22px color-mix(in srgb, var(--wk-a) 55%, transparent);
}
.wk-profile .wk-review .mi-xs { color: #f59e0b; transition: transform .3s ease; }
.wk-profile .wk-review:hover .mi-xs { transform: rotate(-12deg) scale(1.15); }
.wk-profile .wk-review b { color: var(--wk-ink); }
.wk-profile .wk-review form input {
    flex: 1 1 auto;
    padding: 11px 14px;
    border-radius: 12px;
    border: 1.5px solid var(--wk-line);
    font: inherit;
    transition: border-color .25s ease, box-shadow .25s ease;
}
.wk-profile .wk-review form input:focus {
    outline: none;
    border-color: var(--wk-a);
    box-shadow: 0 0 0 4px color-mix(in srgb, var(--wk-a) 18%, transparent);
}
.wk-profile .wk-review form button {
    padding: 11px 22px;
    border: 0;
    border-radius: 999px;
    font: inherit;
    font-weight: 700;
    color: #fff;
    cursor: pointer;
    background: linear-gradient(135deg, var(--wk-a), color-mix(in srgb, var(--wk-a) 70%, #000));
    transition: transform .3s ease;
}
.wk-profile .wk-review form button:hover { transform: translateY(-2px); }

.wk-profile a:focus-visible,
.wk-profile button:focus-visible,
.wk-profile input:focus-visible {
    outline: 3px solid var(--wk-mid);
    outline-offset: 2px;
}
@media (max-width: 640px) {
    .wk-profile .wk-head { flex-direction: column; align-items: center; text-align: center; gap: 12px; }
    .wk-profile .wk-id h2, .wk-profile .wk-chips { justify-content: center; }
    .wk-profile .wk-avatar { width: 92px; height: 92px; font-size: 2.3rem; }
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
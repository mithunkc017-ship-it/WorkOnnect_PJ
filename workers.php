<?php // Search / filter workers by name, skill, availability and distance from the visitor ("Near me")
require "config.php";
$k = "%" . ($_GET["q"] ?? "") . "%";
$c = $_GET["cat"] ?? "";
$ar = trim($_GET["ar"] ?? "");
$la = (float) ($_GET["lat"] ?? 0);
$ln = (float) ($_GET["lng"] ?? 0);
$s = $pdo->prepare(
    "SELECT u.*,(SELECT ROUND(AVG(stars),1) FROM reviews WHERE worker_id=u.id) r,(SELECT COUNT(*) FROM reviews WHERE worker_id=u.id) rc,(SELECT COUNT(*) FROM jobs WHERE worker_id=u.id AND status='completed') done,
 IF(u.lat IS NULL,NULL,ROUND(6371*ACOS(LEAST(1,COS(RADIANS(?))*COS(RADIANS(u.lat))*COS(RADIANS(u.lng)-RADIANS(?))+SIN(RADIANS(?))*SIN(RADIANS(u.lat)))),1)) dist
 FROM users u WHERE role='worker' AND name LIKE ? AND (?='' OR skill=?) AND (?=0 OR available=1) AND (?='' OR area LIKE ?) ORDER BY " .
        ($la ? "dist IS NULL,dist," : "") .
        "verified DESC,r DESC",
);
$s->execute([
    $la,
    $ln,
    $la,
    $k,
    $c,
    $c,
    isset($_GET["av"]) ? 1 : 0,
    $ar,
    "%" . $ar . "%",
]);
ob_start();
head("Find Workers");
$pageHead = ob_get_clean();
$pageHead = preg_replace(
    '#<nav>.*?</nav>#s',
    '<nav aria-label="Page header"><a class="logo" href="index.php">Work<span>onnect</span></a></nav>',
    $pageHead,
    1,
    $navReplaced,
);
if ($navReplaced !== 1) {
    throw new RuntimeException("Could not simplify the Find Workers page header.");
}
echo $pageHead;
echo d_top("workers.php");
?>
<style>
/* ===== Find Workers – scoped visual enhancements ===== */
.wk-workers {
    --wk-a: #4f46e5;
    --wk-b: #06b6d4;
    --wk-c: #8b5cf6;
    --wk-ink: #0f172a;
    --wk-muted: #475569;
    position: relative;
}
@keyframes wk-fade-up {
    from { opacity: 0; transform: translateY(22px); }
    to   { opacity: 1; transform: translateY(0); }
}
@keyframes wk-shine { to { background-position: 250% 0; } }
@keyframes wk-grow  { from { transform: scaleX(0); } to { transform: scaleX(1); } }
@keyframes wk-blink { 50% { border-color: transparent; } }

/* animated heading */
.wk-workers .wk-title {
    display: inline-block;
    margin: 0 0 6px;
    font-size: clamp(1.7rem, 4vw, 2.6rem);
    letter-spacing: -.02em;
    line-height: 1.15;
    background: linear-gradient(90deg, var(--wk-a), var(--wk-c), var(--wk-b), var(--wk-a));
    background-size: 250% 100%;
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
    animation: wk-fade-up .7s cubic-bezier(.2,.7,.2,1) both,
               wk-shine 7s linear infinite;
}
.wk-workers .wk-title-bar {
    display: block;
    width: 90px;
    height: 5px;
    margin: 0 0 22px;
    border-radius: 5px;
    background: linear-gradient(90deg, var(--wk-a), var(--wk-b));
    transform-origin: left;
    animation: wk-grow .9s .3s cubic-bezier(.2,.7,.2,1) both;
}

/* filter bar */
.wk-workers .wk-filter {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    align-items: center;
    padding: 16px;
    margin-bottom: 26px;
    border-radius: 20px;
    background: rgba(255,255,255,.85);
    -webkit-backdrop-filter: blur(8px);
    backdrop-filter: blur(8px);
    border: 1px solid rgba(148,163,184,.3);
    box-shadow: 0 14px 34px -18px rgba(79,70,229,.35);
    animation: wk-fade-up .7s .12s cubic-bezier(.2,.7,.2,1) both;
}
.wk-workers .wk-filter input:not([type=checkbox]):not([type=hidden]),
.wk-workers .wk-filter select {
    flex: 1 1 160px;
    min-width: 0;
    padding: 12px 14px;
    border-radius: 12px;
    border: 1.5px solid rgba(148,163,184,.45);
    background: #fff;
    font: inherit;
    transition: border-color .25s ease, box-shadow .25s ease, transform .25s ease;
}
.wk-workers .wk-filter input:focus,
.wk-workers .wk-filter select:focus {
    outline: none;
    border-color: var(--wk-a);
    box-shadow: 0 0 0 4px rgba(99,102,241,.18);
    transform: translateY(-1px);
}
.wk-workers .wk-filter label {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 10px 14px;
    border-radius: 12px;
    background: rgba(99,102,241,.08);
    color: var(--wk-ink);
    cursor: pointer;
    transition: background .25s ease;
}
.wk-workers .wk-filter label:hover { background: rgba(99,102,241,.16); }
.wk-workers .wk-filter button {
    position: relative;
    overflow: hidden;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 12px 22px;
    border: 0;
    border-radius: 999px;
    font: inherit;
    font-weight: 700;
    color: #fff;
    cursor: pointer;
    background: linear-gradient(135deg, var(--wk-a), var(--wk-c));
    box-shadow: 0 10px 22px -10px rgba(79,70,229,.8);
    transition: transform .3s ease, box-shadow .3s ease;
}
.wk-workers .wk-filter button[type=button] {
    background: linear-gradient(135deg, var(--wk-b), var(--wk-a));
}
.wk-workers .wk-filter button::after {
    content: "";
    position: absolute;
    top: 0; left: -80%;
    width: 50%; height: 100%;
    background: linear-gradient(100deg, transparent, rgba(255,255,255,.55), transparent);
    transform: skewX(-20deg);
    transition: left .7s ease;
}
.wk-workers .wk-filter button:hover {
    transform: translateY(-2px) scale(1.04);
    box-shadow: 0 16px 28px -10px rgba(79,70,229,.8);
}
.wk-workers .wk-filter button:hover::after { left: 130%; }
.wk-workers .wk-filter button[type=button] .material-icons {
    animation: wk-bob 1.8s ease-in-out infinite;
}
@keyframes wk-bob {
    0%, 100% { transform: translateY(0); }
    50%      { transform: translateY(-3px); }
}

/* worker cards */
.wk-workers .grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
    gap: 22px;
}
.wk-workers .wk-wcard {
    position: relative;
    display: flex;
    flex-direction: column;
    gap: 8px;
    padding: 24px 22px 22px;
    border-radius: 20px;
    background: rgba(255,255,255,.92);
    border: 1px solid rgba(148,163,184,.28);
    box-shadow: 0 6px 20px -10px rgba(15,23,42,.18);
    overflow: hidden;
    color: var(--wk-muted);
    line-height: 1.5;
    transition: transform .35s cubic-bezier(.2,.7,.2,1),
                box-shadow .35s ease,
                border-color .35s ease;
    animation: wk-fade-up .65s cubic-bezier(.2,.7,.2,1) both;
    animation-delay: calc(.2s + var(--i, 0) * .07s);
}
.wk-workers .wk-wcard::before {
    content: "";
    position: absolute;
    inset: 0 0 auto 0;
    height: 4px;
    background: linear-gradient(90deg, var(--wk-a), var(--wk-b));
    transform: scaleX(0);
    transform-origin: left;
    transition: transform .45s ease;
}
.wk-workers .wk-wcard::after {
    content: "";
    position: absolute;
    inset: 0;
    background: radial-gradient(260px circle at var(--mx, 50%) var(--my, 0%), rgba(99,102,241,.13), transparent 60%);
    opacity: 0;
    transition: opacity .3s ease;
    pointer-events: none;
}
.wk-workers .wk-wcard:hover,
.wk-workers .wk-wcard:focus-within {
    transform: translateY(-8px);
    border-color: rgba(99,102,241,.45);
    box-shadow: 0 22px 40px -16px rgba(79,70,229,.35);
}
.wk-workers .wk-wcard:hover::before,
.wk-workers .wk-wcard:focus-within::before { transform: scaleX(1); }
.wk-workers .wk-wcard:hover::after { opacity: 1; }

.wk-workers .wk-wtop {
    display: flex;
    align-items: center;
    gap: 14px;
}
.wk-workers .wk-avatar {
    flex: 0 0 auto;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 52px;
    height: 52px;
    border-radius: 50%;
    font-weight: 800;
    font-size: 1.3rem;
    color: #fff;
    background: linear-gradient(135deg, var(--wk-a), var(--wk-b));
    box-shadow: 0 10px 20px -8px rgba(79,70,229,.6), 0 0 0 4px rgba(255,255,255,.9);
    transition: transform .4s cubic-bezier(.3,1.6,.5,1);
}
.wk-workers .wk-wcard:nth-child(even) .wk-avatar {
    background: linear-gradient(135deg, var(--wk-c), var(--wk-a));
}
.wk-workers .wk-wcard:hover .wk-avatar { transform: scale(1.12) rotate(-8deg); }
.wk-workers .wk-wname b {
    color: var(--wk-ink);
    font-size: 1.08rem;
}
.wk-workers .wk-meta {
    display: flex;
    align-items: center;
    gap: 4px;
    flex-wrap: wrap;
}
.wk-workers .wk-meta .material-icons { color: var(--wk-a); }
.wk-workers .wk-meta .material-icons.mi-xs { color: #f59e0b; }

/* pulsing availability dots */
.wk-workers .dot-green,
.wk-workers .dot-red { position: relative; }
.wk-workers .dot-green { animation: wk-ping-g 2s ease-out infinite; }
.wk-workers .dot-red   { animation: wk-ping-r 2.6s ease-out infinite; }
@keyframes wk-ping-g {
    0%   { box-shadow: 0 0 0 0 rgba(34,197,94,.55); }
    100% { box-shadow: 0 0 0 9px rgba(34,197,94,0); }
}
@keyframes wk-ping-r {
    0%   { box-shadow: 0 0 0 0 rgba(239,68,68,.4); }
    100% { box-shadow: 0 0 0 8px rgba(239,68,68,0); }
}

.wk-workers .wk-wcard .btn {
    position: relative;
    overflow: hidden;
    align-self: flex-start;
    margin-top: auto;
    display: inline-block;
    padding: 11px 24px;
    border-radius: 999px;
    font-weight: 700;
    text-decoration: none;
    color: #fff;
    background: linear-gradient(135deg, var(--wk-a), var(--wk-c));
    box-shadow: 0 10px 22px -10px rgba(79,70,229,.7);
    transition: transform .3s ease, box-shadow .3s ease;
}
.wk-workers .wk-wcard .btn::after {
    content: "";
    position: absolute;
    top: 0; left: -80%;
    width: 50%; height: 100%;
    background: linear-gradient(100deg, transparent, rgba(255,255,255,.55), transparent);
    transform: skewX(-20deg);
    transition: left .7s ease;
}
.wk-workers .wk-wcard .btn:hover {
    transform: translateY(-3px) scale(1.05);
    box-shadow: 0 16px 28px -10px rgba(79,70,229,.8);
}
.wk-workers .wk-wcard .btn:hover::after { left: 130%; }

.wk-workers a:focus-visible,
.wk-workers button:focus-visible {
    outline: 3px solid rgba(99,102,241,.55);
    outline-offset: 3px;
}
@media (max-width: 560px) {
    .wk-workers .wk-filter { padding: 12px; }
    .wk-workers .wk-filter button { flex: 1 1 100%; justify-content: center; }
}
@media (prefers-reduced-motion: reduce) {
    .wk-workers *, .wk-workers *::before, .wk-workers *::after {
        animation: none !important;
        transition: none !important;
    }
}
</style>

<div class="wk-workers">
<h2 class="wk-title">Find Workers</h2><span class="wk-title-bar" aria-hidden="true"></span>
<form method="get" class="wk-filter"><input name="q" placeholder="Search by name" value="<?= e(
    $_GET["q"] ?? "",
) ?>">
<select name="cat"><option value="">All skills</option><?php foreach (
    $CATS
    as $x
) {
    echo "<option" . ($c == $x ? " selected" : "") . ">" . $x . "</option>";
} ?></select>
<label style="white-space:nowrap"><input type="checkbox" name="av" style="width:auto"> Available only</label>
<input type="hidden" name="lat" id="lat" value="<?= $la ?:
    "" ?>"><input type="hidden" name="lng" id="lng" value="<?= $ln ?: "" ?>">
<input name="ar" placeholder="Area (e.g. Sagar)" value="<?= e(
    $ar,
) ?>"><button>Search</button><button type="button" onclick="navigator.geolocation.getCurrentPosition(p=>{lat.value=p.coords.latitude;lng.value=p.coords.longitude;this.form.submit()})"><span class="material-icons mi">place</span> Near me</button></form>
<div class="grid"><?php
$wkIdx = 0;
foreach ($s as $w) {
    $wkInitial = function_exists("mb_substr")
        ? mb_strtoupper(mb_substr((string) $w["name"], 0, 1))
        : strtoupper(substr((string) $w["name"], 0, 1));
    echo '<div class="card wk-wcard" style="--i:' .
        min($wkIdx++, 12) .
        '">' .
        '<div class="wk-wtop"><span class="wk-avatar" aria-hidden="true">' .
        e($wkInitial) .
        '</span><div class="wk-wname"><b>' .
        e($w["name"]) .
        "</b> " .
        badge($w["verified"]) .
        top($w["r"], $w["rc"]) .
        level($w["done"], $w["r"]) .
        "</div></div>" .
        '<div class="wk-meta">' .
        e($w["skill"]) .
        " · " .
        ($w["available"]
            ? '<span class="dot-green"></span> Available'
            : '<span class="dot-red"></span> Busy') .
        "</div>" .
        '<div class="wk-meta">' .
        ($w["r"]
            ? $w["r"] . ' <span class="material-icons mi-xs">star</span>'
            : "No reviews") .
        "</div>" .
        ($la && $w["dist"] !== null
            ? '<div class="wk-meta"><span class="material-icons mi">place</span> ' .
                $w["dist"] .
                " km away</div>"
            : "") .
        '<a class="btn" href="worker.php?id=' .
        $w["id"] .
        '">View Profile</a></div>';
} ?></div>
</div>

<script>
/* Cosmetic only: cursor-following glow on worker cards. */
(function () {
    document.querySelectorAll('.wk-workers .wk-wcard').forEach(function (card) {
        card.addEventListener('pointermove', function (e) {
            var r = card.getBoundingClientRect();
            card.style.setProperty('--mx', (e.clientX - r.left) + 'px');
            card.style.setProperty('--my', (e.clientY - r.top) + 'px');
        });
    });
})();
</script>
<?php foot(); ?>
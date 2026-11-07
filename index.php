<?php
// Homepage v15: premium landing design. Same data, links and form as the previous homepage. English only - no Kannada on this page.
// Styles: landing.css (scoped to body.wk-page) | behaviour: landing.js | icons: landing_icons.svg | nav/footer: landing_nav.php, landing_footer.php
require "config.php";

if (($_SESSION["lang"] ?? "en") == "kn" && ob_get_level() > 1) {
    // English-only page: close config.php's Kannada translation buffer. The saved language preference is not changed; other pages stay as they are.
    ob_end_flush();
}

$c = fn($q) => (float) $pdo->query($q)->fetchColumn();
$nW = (int) $c("SELECT COUNT(*) FROM users WHERE role='worker'");
$nD = (int) $c("SELECT COUNT(*) FROM jobs WHERE status='completed'");
$nV = (int) $c("SELECT COUNT(*) FROM users WHERE role='worker' AND verified=1");
$avg = round($c("SELECT AVG(stars) FROM reviews"), 1);
$q = $pdo
    ->query(
        "SELECT r.stars,r.comment,u.name FROM reviews r JOIN users u ON u.id=r.customer_id WHERE r.stars>=4 AND r.comment<>'' ORDER BY r.id DESC LIMIT 3",
    )
    ->fetchAll();

// ---- presentation helpers (no database writes, no business logic) ----
$u = me();
$wkIco = [
    "Electrician" => "bolt",
    "Plumber" => "drop",
    "Carpenter" => "ruler",
    "Painter" => "roller",
    "Cleaner" => "sparkle",
    "Driver" => "car",
    "Mechanic" => "wrench",
    "AC Technician" => "snow",
];
$wkIconOf = function ($cat) use ($wkIco) {
    return $wkIco[$cat] ?? "tools";
};
$wkSvg = function ($id, $cls = "wk-i") {
    return '<svg class="' .
        $cls .
        '" aria-hidden="true" focusable="false"><use href="#i-' .
        $id .
        '"/></svg>';
};
$wkStars = function ($n) {
    $o = "";
    for ($i = 1; $i <= 5; $i++) {
        $o .=
            '<svg class="wk-star' .
            ($i <= (int) $n ? " on" : "") .
            '" aria-hidden="true" focusable="false"><use href="#i-star"/></svg>';
    }
    return $o;
};
$wkIni = function ($name) {
    return preg_match("/./u", (string) $name, $m) ? $m[0] : "?";
}; // first character (UTF-8 safe)
$wkAvg = $avg ? $avg : "–";
$wkLinks = [
    ["index.php", "Home", " is-active", ' aria-current="page"'],
    ["workers.php", "Find Workers", "", ""],
    ["how-it-works.php", "How It Works", "", ""],
    ["why-workonnect.php", "Why Workonnect", "", ""],
    ["quick-answers.php", "Quick Answers", "", ""],
];
$wkMenuIco = [
    "post_job.php" => "file",
    "wallet.php" => "wallet",
    "open_jobs.php" => "tools",
    "profile.php" => "user",
    "earnings.php" => "wallet",
    "portfolio.php" => "roller",
    "verify.php" => "shield",
    "categories.php" => "tools",
    "complaints.php" => "alert",
    "verifications.php" => "shield",
    "coupons.php" => "wallet",
    "log.php" => "activity",
];

// ---- shared header: reuse head(), swap its generic <nav> for the landing navigation ----
ob_start();
head("Home");
$wkH = ob_get_clean();
// Take the signed-in links (bell, role links, logout) from head()'s own nav so every existing destination and role rule is kept.
$wkBell = "";
$wkMenu = [];
$wkLogout = null;
$wkHelp = false;
if (preg_match('#<nav>(.*?)</nav>#s', $wkH, $m)) {
    $wkIn = $m[1];
    if (preg_match('#<span id="bell".*?</span>#s', $wkIn, $b)) {
        $wkBell = str_replace("🔔", $wkSvg("bell"), $b[0]);
        $wkIn = str_replace($b[0], "", $wkIn);
    }
    if (preg_match_all('#<a\s([^>]*)>(.*?)</a>#s', $wkIn, $mm, PREG_SET_ORDER)) {
        foreach ($mm as $a) {
            if (!preg_match('#href="([^"]*)"#', $a[1], $h)) {
                continue;
            }
            $href = $h[1];
            if (
                $href === "" ||
                $href[0] == "#" ||
                strpos($href, "lang=") !== false
            ) {
                continue; // skip bell/dark toggle/language links
            }
            $raw = trim(strip_tags($a[2]));
            $lab = preg_replace('/^[^\p{L}\p{N}]+/u', "", $raw);
            $label = trim($lab === null ? $raw : $lab);
            if ($label === "") {
                continue;
            }
            $f = strtok($href, "?");
            if ($f == "logout.php") {
                $wkLogout = [$href, $label];
            } elseif ($f == "help.php") {
                $wkHelp = true;
            } elseif (
                !in_array(
                    $f,
                    ["index.php", "dash.php", "workers.php", "login.php", "register.php"],
                    true,
                )
            ) {
                $wkMenu[] = [$href, $label, $wkMenuIco[$f] ?? "arrow"];
            }
        }
    }
}
if ($u && $wkLogout === null) {
    $wkLogout = ["logout.php", "Logout"];
}
$wkH = str_replace(
    "</head>",
    '<meta name="description" content="Workonnect connects you with trusted local professionals. Post a job, get offers and track the work in one place."><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&family=Outfit:wght@500;600;700&display=swap" rel="stylesheet"><link rel="stylesheet" href="landing.css"><script>document.documentElement.classList.add("wk-js")</script></head>',
    $wkH,
);
$wkH = preg_replace_callback("#<body\b([^>]*)>#i", function ($m) {
    if (preg_match('/class="([^"]*)"/', $m[1])) {
        return "<body" .
            preg_replace('/class="([^"]*)"/', 'class="$1 wk-page"', $m[1], 1) .
            ">";
    }
    return '<body class="wk-page"' . $m[1] . ">";
}, $wkH, 1);
ob_start();
include __DIR__ . "/landing_nav.php";
$wkNav = ob_get_clean();
$wkH2 = preg_replace_callback(
    "#<nav>.*?</nav>#s",
    function ($m) use ($wkNav) {
        return $wkNav;
    },
    $wkH,
    1,
    $wkDone,
);
echo $wkDone && $wkH2 !== null ? $wkH2 : $wkH; // if the generic nav is ever not found, the page simply keeps it
readfile(__DIR__ . "/landing_icons.svg");
?>
<div id="sp"></div>
<section class="wk-hero" id="top" tabindex="-1" aria-labelledby="home-title">
    <div class="wk-hero-bg" aria-hidden="true">
        <i class="wk-blob wk-b1"></i>
        <i class="wk-blob wk-b2"></i>
        <i class="wk-dots"></i>
    </div>
    <div class="wk-wrap wk-hero-in">
        <div class="wk-hero-copy">
            <span class="wk-pill"><i></i><span>Trusted local professionals · Karnataka</span></span>
            <h1 id="home-title"><span>Find Skilled Workers</span> <span class="wk-h1b">Near You</span></h1>
            <p class="wk-lead">Post a job, get offers, track progress – all in one place.</p>
            <form class="wk-search" action="workers.php" method="get" role="search">
                <label class="wk-f">
                    <span class="wk-sr">Service category</span><?= $wkSvg("tools") ?>
                    <select name="cat">
                        <option value="">All services</option>
                        <?php foreach ($CATS as $x) { ?>
                            <option><?= e($x) ?></option>
                        <?php } ?>
                    </select>
                </label>
                <label class="wk-f">
                    <span class="wk-sr">Search area</span><?= $wkSvg("pin") ?>
                    <input name="ar" placeholder="Your area (e.g. Sagar)" autocomplete="off">
                </label>
                <button type="submit" class="wk-btn wk-btn-accent">
                    <?= $wkSvg("search") ?><span>Search</span>
                </button>
            </form>
            <div class="wk-cta-row">
                <a class="wk-btn wk-btn-light" href="post_job.php">
                    <span>Post a Job</span><?= $wkSvg("arrow") ?>
                </a>
                <a class="wk-btn wk-btn-outline" href="register.php">
                    <span>Join as Worker</span>
                </a>
            </div>
            <ul class="wk-trust">
                <li><?= $wkSvg("shield") ?><span>ID-verified workers</span></li>
                <li><?= $wkSvg("alert") ?><span>Emergency booking</span></li>
                <li><?= $wkSvg("chat") ?><span>Live chat</span></li>
            </ul>
        </div>
        <div class="wk-hero-art" aria-hidden="true">
            <div class="wk-orb">
                <i class="wk-ring wk-r1"></i>
                <i class="wk-ring wk-r2"></i>
                <div class="wk-core"><?= $wkSvg("pin") ?></div>
            </div>
            <?php foreach (array_slice($CATS, 0, 6) as $i => $x) { ?>
                <span class="wk-chip wk-c<?= $i ?>">
                    <b><?= $wkSvg($wkIconOf($x)) ?></b>
                    <span><?= e($x) ?></span>
                </span>
            <?php } ?>
        </div>
    </div>
</section>

<section class="wk-stats-wrap" aria-label="Platform statistics">
    <div class="wk-wrap">
        <ul class="wk-stats wk-reveal">
            <li class="wk-stat">
                <span class="wk-ic"><?= $wkSvg("users") ?></span>
                <b class="wk-num" data-n="<?= $nW ?>"><?= $nW ?></b>
                <span class="wk-stat-l">Workers</span>
            </li>
            <li class="wk-stat">
                <span class="wk-ic"><?= $wkSvg("shield") ?></span>
                <b class="wk-num" data-n="<?= $nV ?>"><?= $nV ?></b>
                <span class="wk-stat-l">Verified</span>
            </li>
            <li class="wk-stat">
                <span class="wk-ic"><?= $wkSvg("check") ?></span>
                <b class="wk-num" data-n="<?= $nD ?>"><?= $nD ?></b>
                <span class="wk-stat-l">Jobs completed</span>
            </li>
            <li class="wk-stat">
                <span class="wk-ic"><?= $wkSvg("star") ?></span>
                <b class="wk-num"><?= $wkAvg ?></b>
                <span class="wk-stat-l">Average rating</span>
            </li>
        </ul>
    </div>
</section>

<section class="wk-sec" id="services" aria-labelledby="services-title">
    <div class="wk-wrap">
        <div class="wk-head wk-reveal">
            <div>
                <h2 id="services-title">Services</h2>
                <p class="wk-sub">Pick a service to see the workers who offer it.</p>
            </div>
            <a class="wk-textlink wk-dark-link" href="workers.php">
                <span>Find Workers</span><?= $wkSvg("arrow") ?>
            </a>
        </div>
        <ul class="wk-svc-grid wk-reveal">
            <?php foreach ($CATS as $x) { ?>
                <li>
                    <a class="wk-svc" href="workers.php?cat=<?= urlencode($x) ?>">
                        <span class="wk-svc-ic"><?= $wkSvg($wkIconOf($x)) ?></span>
                        <span class="wk-svc-n"><?= e($x) ?></span>
                        <?= $wkSvg("arrow", "wk-i wk-go") ?>
                    </a>
                </li>
            <?php } ?>
        </ul>
    </div>
</section>

<section class="wk-sec wk-band" id="explore" aria-labelledby="explore-title">
    <div class="wk-wrap">
        <div class="wk-head wk-reveal">
            <div>
                <h2 id="explore-title">Explore Workonnect</h2>
                <p class="wk-sub">Learn how the marketplace works, what to expect, and how to get started.</p>
            </div>
        </div>
        <ul class="wk-feats wk-reveal">
            <li>
                <a class="wk-feat" href="how-it-works.php">
                    <span class="wk-ic"><?= $wkSvg("activity") ?></span>
                    <h3>How it works</h3>
                    <p>Follow the steps from posting a job to completing and paying for it.</p>
                    <span class="wk-gol"><span>See how it works</span><?= $wkSvg("arrow") ?></span>
                </a>
            </li>
            <li>
                <a class="wk-feat" href="why-workonnect.php">
                    <span class="wk-ic"><?= $wkSvg("users") ?></span>
                    <h3>Why Workonnect?</h3>
                    <p>See the tools that help customers and local professionals work together.</p>
                    <span class="wk-gol"><span>Why choose us</span><?= $wkSvg("arrow") ?></span>
                </a>
            </li>
            <li>
                <a class="wk-feat" href="verified-workers.php">
                    <span class="wk-ic"><?= $wkSvg("shield") ?></span>
                    <h3>Verified workers</h3>
                    <p>Browse profiles, skills, customer ratings, and verification details.</p>
                    <span class="wk-gol"><span>Meet the workers</span><?= $wkSvg("arrow") ?></span>
                </a>
            </li>
            <li>
                <a class="wk-feat" href="quick-answers.php">
                    <span class="wk-ic"><?= $wkSvg("chat") ?></span>
                    <h3>Quick answers</h3>
                    <p>Get clear answers about accounts, bookings, payments, and support.</p>
                    <span class="wk-gol"><span>Read the FAQs</span><?= $wkSvg("arrow") ?></span>
                </a>
            </li>
        </ul>
    </div>
</section>

<?php if ($q) { ?>
<section class="wk-sec" id="reviews" aria-labelledby="reviews-title">
    <div class="wk-wrap">
        <div class="wk-head wk-reveal">
            <div>
                <h2 id="reviews-title">What customers say</h2>
            </div>
        </div>
        <ul class="wk-voices wk-reveal">
            <?php foreach ($q as $x) { ?>
                <li class="wk-voice">
                    <?= $wkSvg("quote", "wk-i wk-qi") ?>
                    <span class="wk-stars" role="img" aria-label="<?= (int) $x["stars"] ?> / 5">
                        <?= $wkStars($x["stars"]) ?>
                    </span>
                    <blockquote>
                        <p><?= e($x["comment"]) ?></p>
                    </blockquote>
                    <div class="wk-by">
                        <span class="wk-av wk-av-sm"><?= e($wkIni($x["name"])) ?></span>
                        <span><?= e($x["name"]) ?></span>
                    </div>
                </li>
            <?php } ?>
        </ul>
    </div>
</section>
<?php } ?>

<section class="wk-sec wk-cta-sec" aria-labelledby="cta-title">
    <div class="wk-wrap">
        <div class="wk-cta wk-reveal">
            <i class="wk-blob wk-b1" aria-hidden="true"></i>
            <i class="wk-blob wk-b2" aria-hidden="true"></i>
            <h2 id="cta-title">Find Skilled Workers Near You</h2>
            <p>Post a job, get offers, track progress – all in one place.</p>
            <div class="wk-cta-btns">
                <a class="wk-btn wk-btn-accent" href="post_job.php">
                    <span>Post a Job</span>
                </a>
                <a class="wk-btn wk-btn-light" href="register.php">
                    <span>Join as Worker</span><?= $wkSvg("arrow") ?>
                </a>
            </div>
        </div>
    </div>
</section>

<button
    id="up"
    type="button"
    onclick="scrollTo({top:0,behavior:'smooth'})"
    title="Back to top"
    aria-label="Back to top"
    hidden
><?= $wkSvg("up") ?></button>
<script src="landing.js" defer></script>
<?php
// ---- shared footer: reuse foot() (chat widget, install prompt, notifications, dark-mode script), swap its generic <footer> for the landing footer ----
ob_start();
foot();
$wkF = ob_get_clean();
ob_start();
include __DIR__ . "/landing_footer.php";
$wkFoot = ob_get_clean();
$wkF2 = preg_replace_callback(
    "#<footer\b[^>]*>.*?</footer>#s",
    function ($m) use ($wkFoot) {
        return $wkFoot;
    },
    $wkF,
    1,
    $wkDone,
);
echo $wkDone && $wkF2 !== null ? $wkF2 : $wkF;

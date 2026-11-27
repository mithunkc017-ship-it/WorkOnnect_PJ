<?php // Admin: view and resolve complaints
require "config.php";
need("admin");
if (isset($_POST["refund"])) {
    $s = $pdo->prepare("SELECT * FROM jobs WHERE id=? AND paid=1");
    $s->execute([(int) $_POST["refund"]]);
    $jb = $s->fetch();
    if ($jb) {
        $amt = $jb["price"] + $jb["tip"];
        wallet($pdo, $jb["customer_id"], $amt, "Refund for job #" . $jb["id"]);
        $pdo->prepare(
            "UPDATE jobs SET paid=0,refunded=1,commission=0,tip=0 WHERE id=?",
        )->execute([$jb["id"]]);
        notify(
            $jb["customer_id"],
            "Refund of ₹" . $amt . " added to your wallet",
            "wallet.php",
        );
        notify(
            $jb["worker_id"],
            'Job "' . $jb["title"] . '" was refunded by the admin',
            "job.php?id=" . $jb["id"],
        );
        logit("Refunded job #" . $jb["id"]);
    }
    header("Location: complaints.php");
    exit();
}
if (isset($_POST["r"])) {
    $s = $pdo->prepare("SELECT user_id,job_id FROM complaints WHERE id=?");
    $s->execute([(int) $_POST["r"]]);
    $c = $s->fetch();
    $pdo->prepare(
        "UPDATE complaints SET status='resolved' WHERE id=?",
    )->execute([(int) $_POST["r"]]);
    if ($c) {
        notify(
            $c["user_id"],
            "Your complaint on job #" . $c["job_id"] . " was resolved",
            "job.php?id=" . $c["job_id"],
        );
    }
    header("Location: complaints.php");
    exit();
}

/* ---------- presentation only: sidebar built from head()'s own links ---------- */
$cpIcons = [
    "index.php" => "home",
    "dash.php" => "dashboard",
    "workers.php" => "groups",
    "post_job.php" => "post_add",
    "open_jobs.php" => "work",
    "wallet.php" => "account_balance_wallet",
    "earnings.php" => "payments",
    "profile.php" => "person",
    "portfolio.php" => "photo_library",
    "verify.php" => "verified_user",
    "verifications.php" => "verified_user",
    "categories.php" => "category",
    "complaints.php" => "flag",
    "coupons.php" => "local_offer",
    "log.php" => "article",
    "help.php" => "help",
];
$cpLinks = [];
$cpTools = [];
$cpBell = "";
$cpLogout = null;

ob_start();
head("Complaints");
$cpH = ob_get_clean();

if (preg_match('#<nav>(.*?)</nav>#s', $cpH, $m)) {
    $in = $m[1];
    if (preg_match('#<span id="bell".*?</span>#s', $in, $b)) {
        $cpBell = $b[0];
        $in = str_replace($b[0], "", $in);
    }
    if (preg_match_all('#<a\s([^>]*)>(.*?)</a>#s', $in, $mm, PREG_SET_ORDER)) {
        foreach ($mm as $a) {
            if (!preg_match('#href="([^"]*)"#', $a[1], $h)) {
                continue;
            }
            $href = $h[1];
            if ($href === "" || $href[0] == "#" || strpos($href, "lang=") !== false) {
                $cpTools[] = $a[0]; // dark-mode / language links: kept exactly as they were
                continue;
            }
            $raw = trim(
                strip_tags(
                    preg_replace('#<span class="material-icons[^"]*">.*?</span>#s', "", $a[2]),
                ),
            );
            $lab = preg_replace('/^[^\p{L}\p{N}]+/u', "", $raw);
            $label = trim($lab === null ? $raw : $lab);
            if ($label === "") {
                continue;
            }
            $f = strtok($href, "?");
            if ($f == "logout.php") {
                $cpLogout = [$href, $label];
            } else {
                $cpLinks[$href] = [$label, $cpIcons[$f] ?? "chevron_right", $f];
            }
        }
    }
}
if ($cpLogout === null && function_exists("me") && me()) {
    $cpLogout = ["logout.php", "Logout"];
}
$cpHere = basename($_SERVER["SCRIPT_NAME"] ?? "complaints.php");
$cpA = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, "UTF-8", false);

ob_start();
?>
<aside class="cp-side" id="cpSide" aria-label="Admin navigation">
  <div class="cp-brand">
    <span class="cp-logo"><span class="material-icons">handyman</span></span>
    <span class="cp-brand-t">Workonnect<small>Admin</small></span>
    <?= $cpBell ?>
  </div>
  <nav class="cp-menu">
    <?php $i = 0; foreach ($cpLinks as $href => [$label, $ico, $f]) { ?>
      <a class="cp-link<?= $f === $cpHere ? " is-active" : "" ?>" href="<?= $cpA($href) ?>" style="--i:<?= $i++ ?>">
        <span class="material-icons"><?= $ico ?></span><span><?= $label ?></span>
      </a>
    <?php } ?>
  </nav>
  <div class="cp-foot">
    <?php if ($cpTools) { ?><div class="cp-tools"><?= implode("", $cpTools) ?></div><?php } ?>
    <?php if ($cpLogout) { ?>
      <a class="cp-link cp-out" href="<?= $cpA($cpLogout[0]) ?>"><span class="material-icons">logout</span><span><?= $cpLogout[1] ?></span></a>
    <?php } ?>
  </div>
</aside>
<button class="cp-burger" id="cpBurger" type="button" aria-label="Open menu" aria-controls="cpSide" aria-expanded="false"><span class="material-icons">menu</span></button>
<div class="cp-scrim" id="cpScrim"></div>
<?php
$cpSide = ob_get_clean();

if ($cpLinks) {
    $cpH2 = preg_replace_callback("#<nav>.*?</nav>#s", fn($m) => $cpSide, $cpH, 1, $cpDone);
    if ($cpDone && $cpH2 !== null) {
        $cpH = preg_replace_callback("#<body\b([^>]*)>#i", function ($m) {
            if (preg_match('/class="([^"]*)"/', $m[1])) {
                return "<body" . preg_replace('/class="([^"]*)"/', 'class="$1 cp-page"', $m[1], 1) . ">";
            }
            return '<body class="cp-page"' . $m[1] . ">";
        }, $cpH2, 1);
    }
}
$cpStyled = strpos($cpH, "cp-page") !== false;

$cpRows = $pdo
    ->query(
        "SELECT c.*,u.name,j.paid jp FROM complaints c JOIN users u ON u.id=c.user_id JOIN jobs j ON j.id=c.job_id ORDER BY c.status,c.id DESC",
    )
    ->fetchAll();
$cpOpen = 0;
foreach ($cpRows as $x) {
    if ($x["status"] == "open") {
        $cpOpen++;
    }
}
$cpTotal = count($cpRows);

echo $cpH;
?>
<?php if ($cpStyled) { ?>
<style>
  body.cp-page { --ink:#0f3d4c; --ink2:#14515f; --blue:#1d4ed8; --blue-dark:#1e40af; --line:#e3eaee; --muted:#64748b; }
  /* sidebar */
  .cp-side { position:fixed; inset:0 auto 0 0; width:252px; z-index:1000; display:flex; flex-direction:column;
    background:linear-gradient(180deg,var(--ink),#0b2f3b); color:#e6f1f4; padding:18px 14px; overflow-y:auto;
    animation:cp-side-in .45s ease both; }
  .cp-brand { display:flex; align-items:center; gap:10px; padding:4px 8px 18px; border-bottom:1px solid rgba(255,255,255,.1); margin-bottom:12px; }
  .cp-logo { width:38px; height:38px; border-radius:12px; display:grid; place-items:center; background:var(--blue); color:#fff; }
  .cp-brand-t { font-weight:700; font-size:1.05rem; color:#fff; display:flex; flex-direction:column; line-height:1.15; flex:1; }
  .cp-brand-t small { font-weight:500; font-size:.75rem; color:#9fc3cf; }
  .cp-menu { display:flex; flex-direction:column; gap:4px; }
  .cp-link { display:flex; align-items:center; gap:12px; padding:11px 12px; border-radius:12px; color:#cfe3ea !important; text-decoration:none !important;
    font-size:.95rem; font-weight:500; transition:background .18s, color .18s, transform .18s; animation:cp-link-in .4s ease both; animation-delay:calc(var(--i,0) * 40ms + 150ms); }
  .cp-link .material-icons { font-size:21px; opacity:.9; }
  .cp-link:hover { background:rgba(255,255,255,.09); color:#fff !important; transform:translateX(3px); }
  .cp-link.is-active { background:var(--blue); color:#fff !important; box-shadow:0 8px 20px -10px rgba(29,78,216,.9); }
  .cp-link:focus-visible { outline:3px solid rgba(147,197,253,.7); outline-offset:2px; }
  .cp-foot { margin-top:auto; padding-top:14px; border-top:1px solid rgba(255,255,255,.1); display:flex; flex-direction:column; gap:6px; }
  .cp-tools { display:flex; flex-wrap:wrap; gap:8px; padding:0 4px 8px; }
  .cp-tools a { color:#cfe3ea !important; font-size:.85rem; text-decoration:none !important; padding:6px 10px; border-radius:10px; background:rgba(255,255,255,.07); }
  .cp-out:hover { background:rgba(255,255,255,.09); }
  .cp-burger { display:none; position:fixed; top:12px; left:12px; z-index:1100; width:44px; height:44px; border:0; border-radius:12px; background:var(--blue); color:#fff; cursor:pointer; box-shadow:0 8px 20px -8px rgba(29,78,216,.8); }
  .cp-scrim { display:none; position:fixed; inset:0; background:rgba(8,24,30,.5); z-index:999; }
  body.cp-page { padding-left:252px; }
  @media (max-width:900px) {
    body.cp-page { padding-left:0; }
    .cp-side { transform:translateX(-100%); transition:transform .25s ease; animation:none; }
    .cp-side.open { transform:none; }
    .cp-burger { display:grid; place-items:center; }
    .cp-scrim.show { display:block; }
    .cp-main { padding-top:56px; }
  }

  /* page */
  .cp-main { max-width:1100px; margin:0 auto; padding:26px 22px 40px; }
  .cp-main h2 { display:flex; align-items:center; gap:10px; margin:0 0 18px; color:var(--ink); animation:cp-up .45s ease both; }
  .cp-cards { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:14px; margin-bottom:20px; }
  .cp-card { display:flex; align-items:center; gap:14px; padding:16px 18px; background:#fff; border:1px solid var(--line); border-radius:16px;
    box-shadow:0 10px 26px -20px rgba(15,61,76,.5); animation:cp-up .5s ease both; animation-delay:calc(var(--i,0) * 70ms + 100ms); }
  .cp-card .ic { width:44px; height:44px; border-radius:12px; display:grid; place-items:center; background:#e3eeff; color:var(--blue); }
  .cp-card b { display:block; font-size:1.6rem; line-height:1.1; color:var(--ink); }
  .cp-card span.l { color:var(--muted); font-size:.88rem; }
  .cp-card.warn .ic { background:#fdf1de; color:#b45309; }
  .cp-card.ok .ic { background:#e7f6ec; color:#15803d; }

  .cp-wrap { background:#fff; border:1px solid var(--line); border-radius:16px; overflow:auto; box-shadow:0 10px 30px -20px rgba(15,61,76,.45); animation:cp-up .5s ease .2s both; }
  .cp-table { width:100%; border-collapse:collapse; min-width:640px; }
  .cp-table th { text-align:left; padding:13px 16px; font-size:.85rem; color:var(--muted); background:#f6f9fb; border-bottom:1px solid var(--line); white-space:nowrap; }
  .cp-table td { padding:14px 16px; border-bottom:1px solid #eef3f6; vertical-align:middle; background:transparent; }
  .cp-table tbody tr { animation:cp-row .4s ease both; animation-delay:calc(var(--i,0) * 35ms + 250ms); transition:background .15s; }
  .cp-table tbody tr:hover { background:#f8fbfd; }
  .cp-table tbody tr:last-child td { border-bottom:0; }
  .cp-table tr.empty td { text-align:center; color:var(--muted); padding:40px; }
  .cp-job { display:inline-flex; align-items:center; gap:6px; font-weight:700; color:var(--blue); text-decoration:none; padding:5px 10px; border-radius:10px; background:#e8f0ff; }
  .cp-job:hover { background:#d6e4ff; }
  .cp-by { display:inline-flex; align-items:center; gap:10px; font-weight:600; color:var(--ink); }
  .cp-av { width:30px; height:30px; border-radius:50%; display:grid; place-items:center; background:#dbe7ff; color:var(--blue); font-size:.85rem; font-weight:700; flex:none; }
  .cp-msg { max-width:420px; line-height:1.45; color:#27363d; overflow-wrap:anywhere; }
  .cp-badge { display:inline-flex; align-items:center; gap:7px; padding:5px 12px; border-radius:999px; font-size:.85rem; font-weight:600; }
  .cp-badge i { width:8px; height:8px; border-radius:50%; background:currentColor; }
  .cp-badge.open { color:#b45309; background:#fdf1de; }
  .cp-badge.open i { animation:cp-pulse 1.6s ease-in-out infinite; }
  .cp-badge.resolved { color:#15803d; background:#e7f6ec; }
  .cp-act { display:flex; flex-wrap:wrap; gap:8px; }
  .cp-act form { margin:0; }
  .cp-act button { display:inline-flex; align-items:center; gap:6px; margin:0; padding:9px 16px; border:0; border-radius:11px; background:var(--blue); color:#fff; font:inherit; font-weight:600; font-size:.9rem; cursor:pointer; transition:background .15s, transform .1s; }
  .cp-act button:hover { background:var(--blue-dark); }
  .cp-act button:active { transform:scale(.96); }
  .cp-act button:focus-visible { outline:3px solid rgba(29,78,216,.35); outline-offset:2px; }
  .cp-act .material-icons { font-size:18px; }

  @keyframes cp-side-in { from { transform:translateX(-24px); opacity:0; } to { transform:none; opacity:1; } }
  @keyframes cp-link-in { from { opacity:0; transform:translateX(-10px); } to { opacity:1; transform:none; } }
  @keyframes cp-up { from { opacity:0; transform:translateY(10px); } to { opacity:1; transform:none; } }
  @keyframes cp-row { from { opacity:0; transform:translateY(8px); } to { opacity:1; transform:none; } }
  @keyframes cp-pulse { 50% { opacity:.3; } }
  @media (prefers-reduced-motion:reduce) { .cp-side, .cp-link, .cp-main *, .cp-badge i { animation:none !important; transition:none !important; } }
</style>
<?php } ?>
<div class="cp-main">
<h2><span class="material-icons mi">flag</span> Complaints</h2>

<?php if ($cpStyled) { ?>
<div class="cp-cards">
  <div class="cp-card" style="--i:0"><span class="ic"><span class="material-icons">forum</span></span><div><b><?= $cpTotal ?></b><span class="l">Total complaints</span></div></div>
  <div class="cp-card warn" style="--i:1"><span class="ic"><span class="material-icons">pending_actions</span></span><div><b><?= $cpOpen ?></b><span class="l">Open</span></div></div>
  <div class="cp-card ok" style="--i:2"><span class="ic"><span class="material-icons">task_alt</span></span><div><b><?= $cpTotal - $cpOpen ?></b><span class="l">Resolved</span></div></div>
</div>
<?php } ?>

<div class="cp-wrap"><table class="cp-table"><thead><tr><th>Job</th><th>By</th><th>Problem</th><th>Status</th><th></th></tr></thead><tbody>
<?php
if (!$cpRows) {
    echo '<tr class="empty"><td colspan="5">No complaints yet.</td></tr>';
}
$i = 0;
foreach ($cpRows as $x) {
    $ini = preg_match("/./u", (string) $x["name"], $mi) ? $mi[0] : "?";
    echo '<tr style="--i:' . min($i++, 20) . '"><td><a class="cp-job" href="job.php?id=' .
        (int) $x["job_id"] .
        '">#' .
        (int) $x["job_id"] .
        '</a></td><td><span class="cp-by"><span class="cp-av">' .
        e($ini) .
        "</span>" .
        e($x["name"]) .
        '</span></td><td><div class="cp-msg">' .
        e($x["msg"]) .
        '</div></td><td><span class="cp-badge ' .
        e($x["status"]) .
        '"><i></i>' .
        e($x["status"]) .
        '</span></td><td><div class="cp-act">' .
        ($x["status"] == "open"
            ? '<form method="post"><input type="hidden" name="r" value="' .
                (int) $x["id"] .
                '"><button><span class="material-icons">check_circle</span>Resolve</button></form>'
            : "") .
        ($x["jp"]
            ? '<form method="post"><input type="hidden" name="refund" value="' .
                (int) $x["job_id"] .
                '"><button><span class="material-icons">undo</span>Refund</button></form>'
            : "") .
        "</div></td></tr>";
}
?></tbody></table></div>
</div>

<?php if ($cpStyled) { ?>
<script>
(function () {
  var side = document.getElementById('cpSide'), burger = document.getElementById('cpBurger'), scrim = document.getElementById('cpScrim');
  if (!side || !burger) return;
  function set(open) {
    side.classList.toggle('open', open);
    scrim.classList.toggle('show', open);
    burger.setAttribute('aria-expanded', open ? 'true' : 'false');
  }
  burger.addEventListener('click', function () { set(!side.classList.contains('open')); });
  scrim.addEventListener('click', function () { set(false); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') set(false); });
})();
</script>
<?php } ?>
<?php foot();
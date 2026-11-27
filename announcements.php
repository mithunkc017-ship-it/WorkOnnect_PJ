<?php // Admin: announcements (banner at the top of pages, with optional video / image)
require "config.php";
require_once __DIR__ . "/announce_lib.php";
need("admin");
$GLOBALS["wk_auth"] = true; // no floating chat button on this page

$themes = ["info" => "Info", "offer" => "Offer", "success" => "Good news", "warning" => "Heads up", "urgent" => "Urgent"];
$auds = ["all" => "Everyone", "customer" => "Customers", "worker" => "Workers"];
$durations = [0 => "Until I switch it off", 1 => "1 hour", 24 => "24 hours", 72 => "3 days", 168 => "7 days"];

function ann_media_url($u, &$err)
{
    $u = trim($u);
    if ($u === "") {
        return null;
    }
    if (!preg_match('~^https?://~i', $u)) {
        $err = "The video or image link must start with https://";
        return null;
    }
    if (preg_match('~(?:youtu\.be/|youtube\.com/(?:watch\?(?:.*&)?v=|shorts/|embed/))([\w-]{11})~i', $u, $m)) {
        return ["embed", "https://www.youtube-nocookie.com/embed/" . $m[1]];
    }
    if (preg_match('~vimeo\.com/(?:video/)?(\d+)~i', $u, $m)) {
        return ["embed", "https://player.vimeo.com/video/" . $m[1]];
    }
    $p = strtolower(parse_url($u, PHP_URL_PATH) ?? "");
    if (preg_match('~\.(mp4|webm|ogg)$~', $p)) {
        return ["video", $u];
    }
    if (preg_match('~\.(jpe?g|png|gif|webp)$~', $p)) {
        return ["image", $u];
    }
    $err = "Use a YouTube or Vimeo link, or a direct .mp4 or image link.";
    return null;
}
function ann_upload($f, &$err)
{
    if (empty($f["name"]) || $f["error"] == UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($f["error"] !== UPLOAD_ERR_OK || $f["size"] > 30 * 1048576) {
        $err = "Upload failed or the file is over 30 MB.";
        return null;
    }
    $ok = ["video/mp4" => ["mp4", "video"], "video/webm" => ["webm", "video"], "image/jpeg" => ["jpg", "image"], "image/png" => ["png", "image"], "image/gif" => ["gif", "image"], "image/webp" => ["webp", "image"]];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f["tmp_name"]);
    if (!isset($ok[$mime])) {
        $err = "Upload an MP4, WebM, JPG, PNG, GIF or WebP file.";
        return null;
    }
    $dir = __DIR__ . "/uploads/announcements";
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
        $err = "Could not create the uploads/announcements folder.";
        return null;
    }
    $name = bin2hex(random_bytes(8)) . "." . $ok[$mime][0];
    if (!move_uploaded_file($f["tmp_name"], "$dir/$name")) {
        $err = "Could not save the uploaded file.";
        return null;
    }
    return [$ok[$mime][1], "uploads/announcements/$name"];
}
function ann_off($pdo, $aud)
{ // a new announcement replaces live ones for the same audience (or all of them when it is for everyone)
    foreach ($pdo->query("SELECT * FROM announcements WHERE active=1")->fetchAll() as $o) {
        if ($aud == "all" || ($o["audience"] ?? "all") == $aud) {
            $pdo->prepare("UPDATE announcements SET active=0 WHERE id=?")->execute([(int) $o["id"]]);
        }
    }
}

/* ---------- Actions ---------- */
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $done = "";
    $err = "";
    if (isset($_POST["msg"]) && trim($_POST["msg"]) !== "") {
        $style = isset($themes[$_POST["style"] ?? ""]) ? $_POST["style"] : "info";
        $aud = isset($auds[$_POST["audience"] ?? ""]) ? $_POST["audience"] : "all";
        $hours = (int) ($_POST["hours"] ?? 0);
        if (!isset($durations[$hours])) {
            $hours = 0;
        }
        $link = substr(trim($_POST["link"] ?? ""), 0, 100);
        if (preg_match('/^\s*(javascript|data|vbscript):/i', $link)) {
            $link = "";
        }
        $media = ann_upload($_FILES["file"] ?? [], $err) ?: ($err ? null : ann_media_url($_POST["media"] ?? "", $err));
        $hex = function ($v, $d) {
            return preg_match('/^#[0-9a-f]{6}$/i', $v ?? "") ? $v : $d;
        };
        $design = json_encode(["a" => $hex($_POST["pa"] ?? "", "#4f46e5"), "b" => $hex($_POST["pb"] ?? "", "#c026d3"), "t" => $hex($_POST["pt"] ?? "", "#ffffff"), "al" => in_array($_POST["pal"] ?? "", ["left", "center", "right"]) ? $_POST["pal"] : "center", "em" => mb_substr(trim($_POST["pem"] ?? ""), 0, 4)]);
        if (!$err) {
            ann_off($pdo, $aud);
            $pdo->prepare("INSERT INTO announcements(msg,title,cta,link,style,layout,audience,media_type,media_url,closable,fx,design,expires_at,pub_at)VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)")->execute([
                substr(trim($_POST["msg"]), 0, 200),
                substr(trim($_POST["title"] ?? ""), 0, 60),
                substr(trim($_POST["cta"] ?? ""), 0, 20),
                $link,
                $style,
                in_array($_POST["layout"] ?? "", ["bar", "big", "poster"]) ? $_POST["layout"] : "bar",
                $aud,
                $media[0] ?? "",
                $media[1] ?? "",
                isset($_POST["closable"]) ? 1 : 0,
                isset($_POST["fx"]) ? 1 : 0,
                $design,
                $hours > 0 ? date("Y-m-d H:i:s", time() + $hours * 3600) : "",
                time(),
            ]);
            logit("Published an announcement");
            $done = "published";
        }
    }
    if (isset($_POST["again"])) {
        $s = $pdo->prepare("SELECT * FROM announcements WHERE id=?");
        $s->execute([(int) $_POST["again"]]);
        if ($o = $s->fetch()) {
            ann_off($pdo, $o["audience"] ?? "all");
            $pdo->prepare("UPDATE announcements SET active=1, expires_at=?, pub_at=? WHERE id=?")->execute(["", time(), (int) $o["id"]]);
            logit("Re-published an announcement");
            $done = "published";
        }
    }
    if (isset($_POST["off"])) {
        $pdo->prepare("UPDATE announcements SET active=0 WHERE id=?")->execute([(int) $_POST["off"]]);
        logit("Switched off an announcement");
        $done = "off";
    }
    if (isset($_POST["del"])) {
        $s = $pdo->prepare("SELECT * FROM announcements WHERE id=?");
        $s->execute([(int) $_POST["del"]]);
        if (($o = $s->fetch()) && preg_match('~^uploads/announcements/[a-f0-9]+\.\w+$~', $o["media_url"] ?? "")) {
            @unlink(__DIR__ . "/" . $o["media_url"]);
        }
        $pdo->prepare("DELETE FROM announcements WHERE id=?")->execute([(int) $_POST["del"]]);
        logit("Deleted an announcement");
        $done = "deleted";
    }
    header("Location: announcements.php?" . ($err ? "err=" . urlencode($err) : "done=$done"));
    exit();
}

/* ---------- Data ---------- */
$rows = $pdo->query("SELECT * FROM announcements ORDER BY id DESC LIMIT 24")->fetchAll();
$liveN = 0;
foreach ($rows as $i => $r) {
    $past = !empty($r["expires_at"]) && strtotime($r["expires_at"]) <= time();
    if (!empty($r["active"]) && $past) {
        $pdo->prepare("UPDATE announcements SET active=0 WHERE id=?")->execute([(int) $r["id"]]);
    }
    $rows[$i]["live"] = !empty($r["active"]) && !$past;
    $rows[$i]["expired"] = !$rows[$i]["live"] && $past;
    $liveN += $rows[$i]["live"] ? 1 : 0;
}
$flash = ["published" => "Published. Visitors see it now.", "off" => "Switched off.", "deleted" => "Deleted."][$_GET["done"] ?? ""] ?? "";
$error = $_GET["err"] ?? "";

head("Announcements");
echo ann_assets();

function card($x, $themes, $auds)
{
    $pill = $x["live"] ? '<span class="pill live">Live</span>' : ($x["expired"] ? '<span class="pill ex">Expired</span>' : '<span class="pill off">Off</span>');
    $id = (int) $x["id"];
    $st = $x["live"] ? "live" : ($x["expired"] ? "expired" : "off");
    echo '<div class="card reveal" data-st="' . $st . '" data-t="' . e($x["title"] ?? "") . '" data-m="' . e($x["msg"]) . '" data-s="' . e($x["style"] ?? "info") . '" data-l="' . e($x["layout"] ?? "bar") . '" data-c="' . e($x["cta"] ?? "") . '" data-k="' . e($x["link"] ?? "") . '" data-d="' . e($x["design"] ?? "") . '">' . ann_banner($x) . '<div class="meta">' . $pill . '<span>' . e($themes[$x["style"] ?? "info"] ?? "Info") . ' · ' . e($auds[$x["audience"] ?? "all"] ?? "Everyone") .
        (!empty($x["created"]) ? " · " . date("d M, g:i a", strtotime($x["created"])) : "") .
        ($x["live"] && !empty($x["expires_at"]) ? " · ends " . date("d M, g:i a", strtotime($x["expires_at"])) : "") .
        '</span><form method="post"><button type="button" class="ib" data-reuse title="Reuse as template"><i class="material-icons">edit_note</i></button>' .
        ($x["live"]
            ? '<button class="ib" name="off" value="' . $id . '" title="Switch off"><i class="material-icons">visibility_off</i></button>'
            : '<button class="ib" name="again" value="' . $id . '" title="Publish again"><i class="material-icons">replay</i></button>') .
        '<button class="ib del" name="del" value="' . $id . '" title="Delete" onclick="return confirm(\'Delete this announcement?\')"><i class="material-icons">delete_outline</i></button></form></div></div>';
}
?>
<style>
body>nav,body>footer,#ann{display:none!important}
main{max-width:none!important;margin:0!important;padding:0!important}
.an{--ink:#1c2036;--mute:#6a7090;--line:#e2e4f0;--card:#fff;--page:#f2f1fa;--ac:#4f46e5;background:var(--page);color:var(--ink);min-height:100vh;font-family:Inter,system-ui,sans-serif}
body.dark .an{--ink:#e8eaf6;--mute:#9aa1c0;--line:#2c3150;--card:#1c2036;--page:#11131f}
.an *{box-sizing:border-box}
.hero{position:relative;overflow:hidden;padding:26px 24px 70px;background:linear-gradient(135deg,#312e81,#6d28d9 55%,#c026d3);color:#fff}
.hero i.b{position:absolute;border-radius:50%;filter:blur(60px);opacity:.55;animation:drift 14s ease-in-out infinite alternate}
.hero i.b1{width:340px;height:340px;background:#22d3ee;left:-80px;top:-120px}
.hero i.b2{width:300px;height:300px;background:#f472b6;right:5%;top:-60px;animation-duration:18s}
.hero i.b3{width:260px;height:260px;background:#818cf8;left:45%;bottom:-160px;animation-duration:22s}
@keyframes drift{to{transform:translate(70px,40px) scale(1.2)}}
.hero>div{position:relative;max-width:1180px;margin:0 auto}
.back{display:inline-flex;align-items:center;gap:6px;color:#fff;text-decoration:none;opacity:.85;font-weight:600}
.back:hover{opacity:1}
.hero h1{font:600 2.4rem/1.15 Poppins,sans-serif;margin:18px 0 6px;animation:rise .8s cubic-bezier(.2,.9,.3,1) both}
.hero p{margin:0;max-width:56ch;opacity:.9;animation:rise .8s .12s cubic-bezier(.2,.9,.3,1) both}
.stats{display:flex;gap:12px;margin-top:20px;animation:rise .8s .24s cubic-bezier(.2,.9,.3,1) both}
.stats span{background:rgba(255,255,255,.16);backdrop-filter:blur(8px);border:1px solid rgba(255,255,255,.28);border-radius:14px;padding:8px 16px}
.stats b{font-size:1.4rem;margin-right:6px}
@keyframes rise{from{opacity:0;transform:translateY(22px)}}
.wrap{max-width:1180px;margin:-44px auto 0;padding:0 24px 48px;position:relative}
.grid{display:grid;grid-template-columns:minmax(0,1fr) 420px;gap:22px;align-items:start}
.box{background:var(--card);border:1px solid var(--line);border-radius:20px;padding:22px;box-shadow:0 10px 30px -12px rgba(49,46,129,.25)}
.box h3{margin:0 0 4px;font:600 1.1rem Poppins,sans-serif}
.box .hint,.sec-h small{color:var(--mute);font-size:.86rem}
.an label.f{display:block;font-weight:600;font-size:.86rem;margin:16px 0 6px}
.an .count{float:right;font-weight:400;color:var(--mute)}
.an input[type=text],.an select{width:100%;padding:11px 14px;border:1.5px solid var(--line);border-radius:12px;background:var(--card);color:var(--ink);font:inherit;transition:border-color .2s,box-shadow .2s}
.an input[type=text]:focus,.an select:focus{outline:0;border-color:var(--ac);box-shadow:0 0 0 4px rgba(79,70,229,.16)}
.r2{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.chips{display:flex;flex-wrap:wrap;gap:8px}
.chips input{position:absolute;opacity:0;pointer-events:none}
.chips label{display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border:1.5px solid var(--line);border-radius:999px;cursor:pointer;font-size:.9rem;transition:all .18s}
.chips .s-info{--c:#2563eb;--bg:#e8f0ff}.chips .s-offer{--c:#7c3aed;--bg:#f1eaff}.chips .s-success{--c:#059669;--bg:#e2f6ee}.chips .s-warning{--c:#b45309;--bg:#fff1d6}.chips .s-urgent{--c:#dc2626;--bg:#ffe6e6}
.chips span label i{font-size:18px;color:var(--c)}
.chips span label:hover{border-color:var(--c)}
.chips input:checked+label{background:var(--bg,#e9e8ff);border-color:var(--c,var(--ac));color:var(--c,var(--ac));font-weight:600;transform:scale(1.05)}
.chips input:focus-visible+label{outline:3px solid var(--ac);outline-offset:2px}
.drop{display:flex;align-items:center;gap:10px;padding:14px;border:1.5px dashed var(--line);border-radius:14px;color:var(--mute);cursor:pointer;transition:all .2s}
.drop:hover{border-color:var(--ac);color:var(--ac);background:rgba(79,70,229,.05)}
.drop input{display:none}
.chk{display:flex;gap:8px;align-items:center;margin-top:16px;cursor:pointer}
.go{margin-top:20px;display:inline-flex;align-items:center;gap:8px;padding:13px 26px;border:0;border-radius:14px;background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#fff;font:inherit;font-weight:700;cursor:pointer;box-shadow:0 10px 22px -8px rgba(79,70,229,.7);transition:transform .15s,box-shadow .15s}
.go:hover{transform:translateY(-2px);box-shadow:0 14px 26px -8px rgba(79,70,229,.8)}
.prev{position:sticky;top:16px;background:linear-gradient(160deg,#e7e5fb,#f6e9fb);border-radius:20px;padding:14px;border:1px solid var(--line)}
body.dark .prev{background:#191c30}
.prev .bar{display:flex;gap:6px;align-items:center;margin-bottom:10px}
.prev .bar i{width:10px;height:10px;border-radius:50%;background:#c9cce0}
.prev .bar span{margin-left:8px;flex:1;background:#fff;border-radius:7px;padding:3px 10px;font-size:.75rem;color:#6a7090}
.prev .page{background:#fff;border-radius:12px;padding:10px 0 16px;min-height:230px}
.prev .page .wbn{margin:6px 10px}
.prev .gh{height:10px;border-radius:6px;background:#eceef6;margin:14px 14px 0}
.empty{margin:6px 10px;padding:16px;text-align:center;color:#8a90ad;border:1.5px dashed #dfe2ef;border-radius:12px}
.sec-h{display:flex;align-items:baseline;gap:10px;margin:30px 0 12px}
.sec-h h2{margin:0;font:600 1.3rem Poppins,sans-serif}
.cards{display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:16px}
.card{background:var(--card);border:1px solid var(--line);border-radius:18px;padding:12px 4px 12px;transition:transform .2s,box-shadow .2s}
.card:hover{transform:translateY(-3px);box-shadow:0 14px 28px -14px rgba(49,46,129,.4)}
.card .wbn{animation:none;margin:0 10px}
.meta{display:flex;align-items:center;gap:8px;padding:10px 14px 0;color:var(--mute);font-size:.82rem;flex-wrap:wrap}
.meta form{margin:0 0 0 auto;display:flex;gap:4px}
.pill{padding:2px 10px;border-radius:999px;font-weight:700;font-size:.75rem}
.pill.live{background:#dcfce7;color:#166534;position:relative;padding-left:20px}
.pill.live::before{content:"";position:absolute;left:8px;top:50%;width:7px;height:7px;margin-top:-3.5px;border-radius:50%;background:#16a34a;animation:pulse 1.8s infinite}
@keyframes pulse{0%{box-shadow:0 0 0 0 rgba(22,163,74,.6)}80%,100%{box-shadow:0 0 0 8px rgba(22,163,74,0)}}
.pill.off{background:#eceef6;color:#5b6180}.pill.ex{background:#fff1d6;color:#92400e}
.ib{width:34px;height:34px;border:0;border-radius:10px;background:rgba(120,125,170,.14);color:var(--ink);cursor:pointer;display:grid;place-items:center;padding:0;transition:all .15s}
.ib:hover{background:rgba(79,70,229,.2);transform:translateY(-1px)}.ib.del:hover{background:#ffd9d9;color:#c00}
.ib i{font-size:19px}
.toast{position:fixed;top:16px;left:50%;transform:translateX(-50%);z-index:50;padding:11px 18px;border-radius:14px;color:#fff;background:#0f7b4f;box-shadow:0 12px 30px -8px #0006;animation:toast 4.5s both}
.toast.bad{background:#c0262d}
@keyframes toast{0%{opacity:0;transform:translate(-50%,-20px)}8%,88%{opacity:1;transform:translate(-50%,0)}100%{opacity:0;transform:translate(-50%,-20px)}}

[hidden]{display:none!important}
.reveal{opacity:0}.reveal.in{opacity:1;animation:up .65s var(--d,0s) cubic-bezier(.2,.9,.3,1) backwards}
@keyframes up{from{opacity:0;transform:translateY(26px) scale(.97)}}
.tpl{display:flex;flex-wrap:wrap;align-items:center;gap:8px;margin:14px 0 2px}
.tpl span{color:var(--mute);font-size:.85rem;margin-right:2px}
.blast,.sw button{border:1.5px solid var(--line)}
.blast{border-radius:999px;padding:7px 16px;background:var(--card);color:var(--ink);font:inherit;cursor:pointer}.blast:hover{border-color:var(--ac);color:var(--ac)}
.sw{display:flex;gap:8px}.sw button{width:38px;height:38px;border-radius:12px;cursor:pointer;transition:transform .15s}.sw button:hover{transform:scale(1.15) rotate(-6deg)}
.an input[type=color]{width:100%;height:44px;border:1.5px solid var(--line);border-radius:12px;background:none;padding:3px;cursor:pointer}
.tpl button,.emo button,.tabs button,.dev button{border:1.5px solid var(--line);background:var(--card);color:var(--ink);border-radius:999px;padding:6px 12px;font:inherit;font-size:.85rem;cursor:pointer;transition:all .18s}
.tpl button:hover{border-color:var(--ac);color:var(--ac);transform:translateY(-2px);box-shadow:0 6px 14px -8px var(--ac)}
.emo{display:flex;gap:4px;margin-top:8px}
.emo button{padding:3px 8px;font-size:1.05rem;border-color:transparent;background:rgba(120,125,170,.1)}
.emo button:hover{transform:scale(1.3) rotate(-6deg);background:rgba(79,70,229,.15)}
.dev{margin-left:8px;display:flex;gap:4px}
.dev button{padding:2px 8px;font-size:.9rem;background:#fff;color:#333}
.dev button.on,.tabs button.on{background:var(--ac);border-color:var(--ac);color:#fff}
.tabs{margin-left:auto;display:flex;gap:6px}
.prev .page{max-width:100%;transition:max-width .45s cubic-bezier(.2,.9,.3,1)}
.prev.phone .page{max-width:290px;margin:0 auto}
.go{position:relative;overflow:hidden}
.go::after{content:"";position:absolute;inset:0;background:linear-gradient(110deg,transparent 35%,rgba(255,255,255,.4) 50%,transparent 65%);transform:translateX(-120%)}
.go:hover::after{transform:translateX(120%);transition:transform .7s}
.box{transition:box-shadow .3s}.box:focus-within{box-shadow:0 18px 40px -14px rgba(79,70,229,.45)}
.hero i.b4{width:90px;height:90px;border-radius:24px;background:rgba(255,255,255,.14);filter:none;right:14%;top:40px;opacity:1;animation:spin 20s linear infinite}
@keyframes spin{to{transform:rotate(360deg) translateY(14px)}}
#cf{position:fixed;inset:0;pointer-events:none;z-index:60;overflow:hidden}
#cf i{position:absolute;top:-14px;width:9px;height:14px;border-radius:2px;animation:fall var(--t,2.6s) var(--dl,0s) cubic-bezier(.3,.6,.5,1) forwards}
@keyframes fall{to{transform:translate(var(--x,0),105vh) rotate(var(--r,720deg));opacity:.9}}
@media(max-width:940px){.grid{grid-template-columns:1fr}.prev{position:static;order:-1}}
@media(max-width:520px){.r2{grid-template-columns:1fr}.hero h1{font-size:1.8rem}.cards{grid-template-columns:1fr}}
@media(prefers-reduced-motion:reduce){.an *,.an *::before{animation:none!important;transition:none!important}}
</style>

<div class="an">
<?php if ($flash): ?><div class="toast"><?= e($flash) ?></div><?php endif; ?>
<?php if (($_GET["done"] ?? "") == "published"): ?><div id="cf" data-go="1"></div><?php endif; ?>
<?php if ($error): ?><div class="toast bad"><?= e($error) ?></div><?php endif; ?>

<header class="hero"><i class="b b1"></i><i class="b b2"></i><i class="b b3"></i><i class="b b4"></i><div>
  <a class="back" href="dashboard.php"><i class="material-icons">arrow_back</i> Back to dashboard</a>
  <h1>Announcements</h1>
  <p>Put a message, image or video at the top of every page. Choose who sees it and when it ends.</p>
  <div class="stats"><span><b data-n="<?= $liveN ?>">0</b>live now</span><span><b data-n="<?= count($rows) ?>">0</b>in history</span></div>
</div></header>

<div class="wrap">
<div class="grid">
  <form class="box" method="post" id="anForm" enctype="multipart/form-data" autocomplete="off">
    <h3>New announcement</h3>
    <p class="hint">The preview updates as you type.</p>
    <div class="tpl"><span>Start from</span>
      <button type="button" data-tpl="0">🎉 Festival offer</button><button type="button" data-tpl="1">🛠 Maintenance</button><button type="button" data-tpl="2">✨ New feature</button><button type="button" data-tpl="3">👋 Welcome</button><button type="button" data-tpl="4">🚨 Service issue</button>
    </div>

    <label class="f" for="t">Title (optional)</label>
    <input type="text" id="t" name="title" maxlength="60" placeholder="Diwali offer is live">
    <label class="f" for="m">Message <span class="count" id="cnt">0 / 200</span></label>
    <input type="text" id="m" name="msg" maxlength="200" required placeholder="Use code DIWALI10 for 10% off your first booking">
    <div class="emo"><?php foreach (["🎉","🔥","🎁","✅","⚠️","⭐","🛠","📢"] as $em): ?><button type="button" data-emo="<?= $em ?>"><?= $em ?></button><?php endforeach; ?></div>

    <label class="f">Theme</label>
    <div class="chips">
      <?php foreach ($themes as $k => $l): ?><span class="s-<?= $k ?>"><input type="radio" name="style" id="s-<?= $k ?>" value="<?= $k ?>" <?= $k == "info" ? "checked" : "" ?>><label for="s-<?= $k ?>"><i class="material-icons"><?= ANN_ICONS[$k] ?></i><?= $l ?></label></span><?php endforeach; ?>
    </div>

    <label class="f">Layout</label>
    <div class="chips">
      <span><input type="radio" name="layout" id="l-bar" value="bar" checked><label for="l-bar"><i class="material-icons">view_headline</i>Slim bar</label></span>
      <span><input type="radio" name="layout" id="l-big" value="big"><label for="l-big"><i class="material-icons">view_agenda</i>Big with media</label></span>
      <span><input type="radio" name="layout" id="l-poster" value="poster"><label for="l-poster"><i class="material-icons">celebration</i>Poster popup</label></span>
    </div>

    <div id="posterOpts" hidden>
      <label class="f">Poster design</label>
      <div class="sw"><?php foreach (["#4f46e5,#c026d3", "#f97316,#db2777", "#0ea5e9,#2563eb", "#059669,#0d9488", "#f59e0b,#ef4444", "#1e1b4b,#4338ca"] as $pr): [$c1, $c2] = explode(",", $pr); ?><button type="button" data-pre="<?= $pr ?>" style="background:linear-gradient(135deg,<?= $c1 ?>,<?= $c2 ?>)" aria-label="Preset"></button><?php endforeach; ?></div>
      <div class="r2"><div><label class="f">Color 1</label><input type="color" name="pa" value="#4f46e5"></div><div><label class="f">Color 2</label><input type="color" name="pb" value="#c026d3"></div></div>
      <div class="r2"><div><label class="f">Text color</label><div class="chips"><span><input type="radio" name="pt" id="pt1" value="#ffffff" checked><label for="pt1">Light</label></span><span><input type="radio" name="pt" id="pt2" value="#1b2133"><label for="pt2">Dark</label></span></div></div>
      <div><label class="f">Align</label><div class="chips"><span><input type="radio" name="pal" id="pa1" value="left"><label for="pa1">Left</label></span><span><input type="radio" name="pal" id="pa2" value="center" checked><label for="pa2">Center</label></span></div></div></div>
      <label class="f" for="pem">Big sticker (emoji)</label><input type="text" id="pem" name="pem" maxlength="4" value="🎉">
    </div>
    <label class="f" for="mu">Video or image link</label>
    <input type="text" id="mu" name="media" placeholder="YouTube, Vimeo, .mp4 or image link">
    <label class="f">Or upload a file</label>
    <label class="drop"><i class="material-icons">cloud_upload</i><span id="fn">MP4, WebM, JPG, PNG, GIF or WebP, up to 30 MB</span><input type="file" name="file" id="file" accept="video/mp4,video/webm,image/jpeg,image/png,image/gif,image/webp"></label>

    <div class="r2">
      <div><label class="f" for="cta">Button text</label><input type="text" id="cta" name="cta" maxlength="20" placeholder="Learn more"></div>
      <div><label class="f" for="lk">Button link</label><input type="text" id="lk" name="link" maxlength="100" placeholder="help.php"></div>
      <div><label class="f" for="au">Show to</label><select id="au" name="audience"><?php foreach ($auds as $k => $l): ?><option value="<?= $k ?>"><?= $l ?></option><?php endforeach; ?></select></div>
      <div><label class="f" for="hr">Show for</label><select id="hr" name="hours"><?php foreach ($durations as $h => $l): ?><option value="<?= $h ?>"><?= $l ?></option><?php endforeach; ?></select></div>
    </div>
    <label class="chk"><input type="checkbox" name="closable" id="cl" checked> Let people close it</label>
    <label class="chk"><input type="checkbox" name="fx" checked> Paper blast when visitors first see it</label>
    <button class="go"><i class="material-icons">send</i> Publish</button>
  </form>

  <aside class="prev" aria-label="Preview">
    <div class="bar"><i></i><i></i><i></i><span>yoursite.com</span>
      <div class="dev"><button type="button" class="on" data-dev="">🖥</button><button type="button" data-dev="phone">📱</button></div></div>
    <div class="page"><div id="slot"></div><div class="gh" style="width:70%;margin-top:22px"></div><div class="gh"></div><div class="gh" style="width:55%"></div><div class="gh"></div></div>
  <div style="text-align:center;margin-top:10px"><button type="button" class="blast" onclick="wkConfetti()">🎊 Test paper blast</button></div></aside>
</div>

<?php $liveRows = array_filter($rows, fn($r) => $r["live"]); $oldRows = array_filter($rows, fn($r) => !$r["live"]); ?>
<?php if ($liveRows): ?>
<div class="sec-h"><h2>Live now</h2><small>What visitors see right now</small></div>
<div class="cards" id="liveg"><?php foreach ($liveRows as $x) { card($x, $themes, $auds); } ?></div>
<?php endif; ?>
<div class="sec-h"><h2>Earlier</h2><small>Publish again with one click</small>
  <div class="tabs"><button type="button" class="on" data-f="">All</button><button type="button" data-f="off">Off</button><button type="button" data-f="expired">Expired</button></div></div>
<?php if (!$oldRows): ?><div class="box" style="text-align:center;color:var(--mute)">Nothing here yet. Write your first announcement above.</div><?php endif; ?>
<div class="cards" id="old"><?php foreach ($oldRows as $x) { card($x, $themes, $auds); } ?></div>
</div>
</div>

<script>
(function(){
var I={info:"info",offer:"local_offer",success:"check_circle",warning:"warning",urgent:"campaign"},
f=document.getElementById("anForm"),slot=document.getElementById("slot"),furl=null,ftype="";
function esc(s){var d=document.createElement("div");d.textContent=s;return d.innerHTML}
function pick(n){return f.querySelector("input[name="+n+"]:checked").value}
function media(){
  if(furl)return{t:ftype,u:furl};
  var u=f.media.value.trim(),m;
  if(m=u.match(/(?:youtu\.be\/|youtube\.com\/(?:watch\?(?:.*&)?v=|shorts\/|embed\/))([\w-]{11})/i))return{t:"embed",u:"https://www.youtube-nocookie.com/embed/"+m[1],th:"https://i.ytimg.com/vi/"+m[1]+"/hqdefault.jpg"};
  if(m=u.match(/vimeo\.com\/(?:video\/)?(\d+)/i))return{t:"embed",u:"https://player.vimeo.com/video/"+m[1]};
  if(/^https?:\/\/[^?#]+\.(mp4|webm|ogg)(\?.*)?$/i.test(u))return{t:"video",u:u};
  if(/^https?:\/\/[^?#]+\.(jpe?g|png|gif|webp)(\?.*)?$/i.test(u))return{t:"image",u:u};
  return null}
function draw(){
  var s=pick("style"),big=pick("layout")=="big",t=f.elements.title.value.trim(),m=f.msg.value.trim(),md=media(),first=!slot.querySelector(".wbn");
  document.getElementById("cnt").textContent=f.msg.value.length+" / 200";
  if(!m&&!t){slot.innerHTML='<div class="empty">Your banner shows here</div>';return}
  document.getElementById("posterOpts").hidden=pick("layout")!="poster";
  if(pick("layout")=="poster"){var c=f.link.value.trim();
    slot.innerHTML='<div class="wpo card" style="--pa:'+f.elements.pa.value+';--pb:'+f.elements.pb.value+';--pt:'+pick("pt")+';text-align:'+pick("pal")+'"><i class="wpo-b1"></i><i class="wpo-b2"></i>'+(f.elements.pem.value?'<div class="wpo-em">'+esc(f.elements.pem.value)+'</div>':'')+(t?'<div class="wpo-h">'+esc(t)+'</div>':'')+'<div class="wpo-p">'+esc(m||"Your message")+'</div>'+(md?'<button type="button" class="wpo-m" data-annv data-t="'+md.t+'" data-u="'+esc(md.u)+'"><i class="material-icons">play_circle</i> '+(md.t=="image"?"View":"Watch")+'</button>':'')+(c?'<a class="wbtn" href="#" onclick="return false">'+esc(f.cta.value.trim()||"Learn more")+'</a>':'')+'</div>';return}
  var h='<div class="wbn s-'+s+(big?" big":"")+'"'+(first?"":' style="animation:none"')+'><span class="ic"><i class="material-icons">'+I[s]+'</i></span><div class="tx">'+(t?"<b>"+esc(t)+"</b>":"")+esc(m)+(f.link.value.trim()?'<a class="wbtn" href="#" onclick="return false">'+esc(f.cta.value.trim()||"Learn more")+"</a>":"")+"</div>";
  if(md){var d=' data-annv data-t="'+md.t+'" data-u="'+esc(md.u)+'"',img=md.t=="image";
    if(big){var inn=img?'<img src="'+esc(md.u)+'">':md.t=="video"?'<video src="'+esc(md.u)+'#t=.5" preload="metadata" muted></video>':md.th?'<img src="'+md.th+'">':"";
      h+='<button type="button" class="th"'+d+">"+inn+'<span class="pl"><i class="material-icons">'+(img?"zoom_in":"play_arrow")+"</i></span></button>"}
    else h+='<button type="button" class="vid"'+d+'><i class="material-icons">'+(img?"image":"play_circle")+"</i> "+(img?"View":"Watch")+"</button>"}
  if(f.closable.checked)h+='<button class="x" type="button" aria-label="Close" onclick="this.parentNode.remove()">×</button>';
  slot.innerHTML=h+"</div>"}
f.addEventListener("input",draw);f.addEventListener("change",draw);
document.getElementById("file").addEventListener("change",function(){
  if(furl)URL.revokeObjectURL(furl);var x=this.files[0];
  furl=x?URL.createObjectURL(x):null;ftype=x?(x.type.indexOf("video")==0?"video":"image"):"";
  document.getElementById("fn").textContent=x?x.name:"MP4, WebM, JPG, PNG, GIF or WebP, up to 30 MB";draw()});
draw()})();
</script>
<script>
(function(){
var f=document.getElementById("anForm"),m=f.elements.msg;
function fire(){f.dispatchEvent(new Event("input",{bubbles:true}));f.dispatchEvent(new Event("change",{bubbles:true}))}
function fill(o){f.elements.title.value=o.t||"";m.value=o.m||"";f.elements.cta.value=o.c||"";f.elements.link.value=o.k||"";
  var a=f.querySelector("input[name=style][value="+(o.s||"info")+"]"),b=f.querySelector("input[name=layout][value="+(o.l||"bar")+"]");
  if(a)a.checked=true;if(b)b.checked=true;
  try{var d=JSON.parse(o.d||"{}");if(d.a)f.elements.pa.value=d.a;if(d.b)f.elements.pb.value=d.b;f.elements.pem.value=d.em||"";
   [["pt",d.t],["pal",d.al]].forEach(function(x){var r=x[1]&&f.querySelector("input[name="+x[0]+"][value='"+x[1]+"']");if(r)r.checked=true})}catch(e){}
  fire();scrollTo({top:0,behavior:"smooth"});m.focus()}
var T=[{t:"Festival offer is live",m:"Use code DIWALI10 for 10% off your next booking.",s:"offer",l:"big",c:"Book now",k:"post_job.php"},
{t:"Scheduled maintenance",m:"The app may be slow tonight from 11 PM to 12 AM. Thanks for your patience.",s:"warning",l:"bar"},
{t:"New: live worker tracking",m:"See your worker on the way, in real time, from the job page.",s:"info",l:"big",c:"See how",k:"help.php"},
{t:"Welcome to Workonnect",m:"Post a job and get offers from verified workers near you.",s:"success",l:"bar",c:"Post a job",k:"post_job.php"},
{t:"Service issue",m:"We are fixing a payment problem. Your money is safe.",s:"urgent",l:"bar"}];
document.addEventListener("click",function(e){var b;
  if(b=e.target.closest("[data-tpl]"))fill(T[b.dataset.tpl]);
  else if(b=e.target.closest("[data-reuse]")){var c=b.closest(".card").dataset;fill({t:c.t,m:c.m,s:c.s,l:c.l,c:c.c,k:c.k,d:c.d})}
  else if(b=e.target.closest("[data-pre]")){var q=b.dataset.pre.split(",");f.elements.pa.value=q[0];f.elements.pb.value=q[1];fire()}
  else if(b=e.target.closest("[data-emo]")){var p=m.selectionStart==null?m.value.length:m.selectionStart;m.value=m.value.slice(0,p)+b.dataset.emo+m.value.slice(p);m.focus();fire()}
  else if(b=e.target.closest("[data-dev]")){document.querySelector(".prev").classList.toggle("phone",!!b.dataset.dev);document.querySelectorAll("[data-dev]").forEach(function(x){x.classList.toggle("on",x===b)})}
  else if(b=e.target.closest("[data-f]")){document.querySelectorAll("[data-f]").forEach(function(x){x.classList.toggle("on",x===b)});
    document.querySelectorAll("#old .card").forEach(function(c){c.hidden=!!b.dataset.f&&c.dataset.st!==b.dataset.f})}});
// count-up stats
document.querySelectorAll("[data-n]").forEach(function(el){var n=+el.dataset.n,t0=null;
  function st(t){t0=t0||t;var k=Math.min(1,(t-t0)/900);el.textContent=Math.round(n*(1-Math.pow(1-k,3)));if(k<1)requestAnimationFrame(st)}requestAnimationFrame(st)});
// cards reveal as they scroll into view, one after another
var io=new IntersectionObserver(function(es){es.forEach(function(x){if(x.isIntersecting){x.target.classList.add("in");io.unobserve(x.target)}})},{threshold:.08});
document.querySelectorAll(".reveal").forEach(function(c,i){c.style.setProperty("--d",(i%6)*.07+"s");io.observe(c)});
// confetti after publishing
var cf=document.getElementById("cf");if(cf){var cs=["#4f46e5","#c026d3","#22d3ee","#f59e0b","#10b981","#f43f5e"];
  for(var i=0;i<70;i++){var p=document.createElement("i");p.style.cssText="left:"+Math.random()*100+"%;background:"+cs[i%6]+";--x:"+(Math.random()*160-80)+"px;--r:"+(Math.random()*900-450)+"deg;--t:"+(2+Math.random()*1.6)+"s;--dl:"+Math.random()*.5+"s";cf.appendChild(p)}
  setTimeout(function(){cf.remove()},4500)}
})();
</script>
<?php foot();
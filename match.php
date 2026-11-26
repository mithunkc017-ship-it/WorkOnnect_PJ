<?php // Smart worker match: scores workers by rating, verification, availability, experience and distance
require "config.php";
need("customer");
$c = $_GET["cat"] ?? "";
$la = (float) ($_GET["lat"] ?? 0);
$ln = (float) ($_GET["lng"] ?? 0);
$res = [];
if ($c !== "") {
    $s = $pdo->prepare(
        "SELECT u.*,(SELECT AVG(stars) FROM reviews WHERE worker_id=u.id) r,(SELECT COUNT(*) FROM jobs WHERE worker_id=u.id AND status='completed') done,(SELECT MIN(price) FROM service_prices WHERE worker_id=u.id) minp FROM users u WHERE role='worker' AND skill=?",
    );
    $s->execute([$c]);
    foreach ($s as $w) {
        $d = null;
        if ($la && $w["lat"] !== null) {
            $d =
                6371 *
                acos(
                    min(
                        1,
                        cos(deg2rad($la)) *
                            cos(deg2rad($w["lat"])) *
                            cos(deg2rad($w["lng"]) - deg2rad($ln)) +
                            sin(deg2rad($la)) * sin(deg2rad($w["lat"])),
                    ),
                );
        }
        $w["d"] = $d;
        $w["sc"] = round(
            ($w["r"] ? ($w["r"] / 5) * 40 : 15) +
                ($w["verified"] ? 15 : 0) +
                ($w["available"] ? 15 : 0) +
                (min($w["done"], 20) / 20) * 15 +
                ($d !== null ? max(0, 1 - $d / 25) * 15 : 7),
        );
        $res[] = $w;
    }
    usort($res, function ($a, $b) {
        return $b["sc"] <=> $a["sc"];
    });
    $res = array_slice($res, 0, 5);
}
head("Smart Match");
echo d_top("match.php");
?>
<style>
/* ===== Smart Match, right-side content only (scoped to .mm) ===== */
.mm{--ac:#2563eb;--ac2:#1d4ed8;--ink:#0f172a;--mute:#64748b;--line:#e2e8f0;--bg:#fff;--soft:#eff5ff;--page:#f1f5f9;--ok:#16a34a;--bad:#dc2626;
  max-width:880px;margin:0 auto;padding:8px 4px 40px;color:var(--ink)}
@media (prefers-color-scheme:dark){.mm{--ink:#e5e7eb;--mute:#9ca3af;--line:#334155;--bg:#1e293b;--soft:#172554;--page:#0f172a}}
.mm *{box-sizing:border-box}
.mm .material-icons{font-size:18px;vertical-align:middle}


.mm-hero{background:linear-gradient(135deg,#1d4ed8,#2563eb 60%,#3b82f6);color:#fff;border-radius:24px;padding:28px 24px 24px;
  position:relative;animation:mm-up .5s ease both}
.mm-hbg{position:absolute;inset:0;border-radius:24px;overflow:hidden;pointer-events:none}
.mm-hbg::after{content:"";position:absolute;top:0;left:-60%;width:40%;height:100%;background:linear-gradient(90deg,transparent,rgba(255,255,255,.2),transparent);
  transform:skewX(-20deg);animation:mm-shine 5s ease-in-out infinite}
@keyframes mm-shine{0%{left:-60%}60%,100%{left:140%}}
.mm-hero>*{position:relative}
.mm-ttl{display:flex;align-items:center;gap:14px;margin-bottom:18px}
.mm-ic{width:52px;height:52px;border-radius:16px;background:rgba(255,255,255,.18);display:grid;place-items:center;flex:none;position:relative}
.mm-ic::after{content:"";position:absolute;inset:0;border-radius:16px;border:2px solid rgba(255,255,255,.6);animation:mm-ring 2.2s ease-out infinite}
@keyframes mm-ring{0%{transform:scale(1);opacity:.9}100%{transform:scale(1.5);opacity:0}}
.mm-ic .material-icons{font-size:28px}
.mm-ttl h1{margin:0;font-size:clamp(1.3rem,3.4vw,1.8rem);letter-spacing:-.02em;color:#fff}
.mm-ttl p{margin:2px 0 0;opacity:.9;font-size:14px;color:#fff}

.mm-form{display:flex;gap:10px;flex-wrap:wrap;margin:0;padding:0;background:none;box-shadow:none}
.mm-sel{position:relative;flex:1 1 220px}
.mm-sel>.material-icons{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--ac);pointer-events:none}
.mm-sel select{width:100%;height:50px;margin:0;padding:0 14px 0 44px;border:0;border-radius:14px;font:inherit;font-size:15px;font-weight:600;color:#0f172a;
  background:#fff;box-shadow:0 8px 20px rgba(0,0,0,.16);cursor:pointer;outline:0}
.mm-sel select:focus{box-shadow:0 0 0 4px rgba(255,255,255,.4),0 8px 20px rgba(0,0,0,.16)}
.mm-btn{display:inline-flex;align-items:center;justify-content:center;gap:7px;height:50px;margin:0;padding:0 22px;border:0;border-radius:14px;
  font:inherit;font-size:15px;font-weight:700;cursor:pointer;text-decoration:none;transition:.2s;width:auto}
.mm-btn.pri{background:#fff;color:var(--ac2);box-shadow:0 8px 20px rgba(0,0,0,.16)}
.mm-btn.pri:hover{background:#fff;transform:translateY(-2px);box-shadow:0 12px 24px rgba(0,0,0,.22)}
.mm-btn.loc{background:rgba(255,255,255,.18);color:#fff;border:1.5px solid rgba(255,255,255,.55);box-shadow:none}
.mm-btn.loc:hover{background:rgba(255,255,255,.3);transform:translateY(-2px)}
.mm-btn:active{transform:scale(.97)}

.mm-btn.busy{pointer-events:none;opacity:.85}
.mm-btn.busy .material-icons{animation:mm-spin .8s linear infinite}
@keyframes mm-spin{to{transform:rotate(360deg)}}
.mm-lstat{display:flex;align-items:center;gap:6px;margin-top:12px;font-size:13px;opacity:.95;min-height:20px;color:#fff}
.mm-lstat .material-icons{font-size:17px}
.mm-lstat.err{color:#fecaca}

.mm-rhead{display:flex;align-items:center;gap:10px;margin:26px 2px 14px;flex-wrap:wrap}
.mm-rhead h2{margin:0;font-size:1.2rem;color:var(--ink)}
.mm-pill{background:var(--soft);color:var(--ac);font-size:12.5px;font-weight:700;padding:4px 12px;border-radius:999px}
.mm-pill.g{background:#dcfce7;color:#166534}

.mm-list{display:grid;gap:16px}
.mm-card{position:relative;overflow:hidden;display:grid;grid-template-columns:auto 1fr auto;gap:18px;align-items:center;
  background:var(--bg);border:1px solid var(--line);border-radius:20px;padding:20px 22px;margin:0;
  box-shadow:0 1px 2px rgba(16,24,40,.04),0 14px 30px -18px rgba(37,99,235,.35);transition:.3s;
  opacity:0;animation:mm-up .6s ease forwards;animation-delay:calc(var(--i,0)*.1s + .05s)}
.mm-card:hover{transform:translateY(-4px);border-color:#93b4f5;box-shadow:0 20px 38px -18px rgba(37,99,235,.5)}
.mm-card.top{border-color:var(--ac);box-shadow:0 18px 38px -18px rgba(37,99,235,.55)}
.mm-ribbon{position:absolute;top:0;left:0;background:linear-gradient(90deg,#f59e0b,#f97316);color:#fff;font-size:11px;font-weight:800;
  letter-spacing:.04em;padding:5px 14px 5px 12px;border-radius:0 0 14px 0;display:flex;align-items:center;gap:4px}
.mm-ribbon .material-icons{font-size:14px}
.mm-card.top .mm-who{margin-top:14px}

.mm-avw{position:relative;flex:none}
.mm-av{width:64px;height:64px;border-radius:50%;overflow:hidden;display:grid;place-items:center;color:#fff;font-weight:800;font-size:26px;
  background:linear-gradient(135deg,var(--ac),#60a5fa)}
.mm-av img{width:100%;height:100%;object-fit:cover;display:block}
.mm-rank{position:absolute;left:-2px;bottom:-2px;width:24px;height:24px;border-radius:50%;background:var(--bg);color:var(--ac);font-size:12px;font-weight:800;
  display:grid;place-items:center;border:2px solid var(--ac)}

.mm-who{min-width:0}
.mm-nm{display:flex;align-items:center;gap:8px;flex-wrap:wrap;font-size:18px;font-weight:800;margin-bottom:8px;color:var(--ink)}
.mm-nm .n{overflow-wrap:anywhere}
.mm-meta{display:flex;flex-wrap:wrap;gap:8px}
.mm-tag{display:inline-flex;align-items:center;gap:5px;padding:5px 11px;border-radius:999px;font-size:12.5px;font-weight:600;background:var(--page);color:var(--ink);border:1px solid var(--line)}
.mm-tag .material-icons{font-size:15px;color:var(--ac)}
.mm-tag.star .material-icons{color:#f59e0b}
.mm-tag.on{background:#dcfce7;color:#166534;border-color:#bbf7d0}
.mm-tag.on .material-icons{color:var(--ok)}
.mm-tag.off{background:#fee2e2;color:#991b1b;border-color:#fecaca}
.mm-tag.off .material-icons{color:var(--bad)}
.mm-tag.price{background:var(--soft);color:var(--ac2);border-color:#bfd3fb}

.mm-side{display:flex;flex-direction:column;align-items:center;gap:12px;min-width:150px}
.mm-ringw{position:relative;width:84px;height:84px;margin-bottom:14px}
.mm-ringw svg{width:84px;height:84px;transform:rotate(-90deg)}
.mm-ringw .tr{fill:none;stroke:var(--line);stroke-width:8}
.mm-ringw .pr{fill:none;stroke:var(--ac);stroke-width:8;stroke-linecap:round;stroke-dasharray:226.2;stroke-dashoffset:226.2;
  animation:mm-fill 1.2s .3s cubic-bezier(.2,.8,.2,1) forwards}
@keyframes mm-fill{to{stroke-dashoffset:var(--off)}}
.mm-ringw b{position:absolute;inset:0;display:grid;place-items:center;font-size:20px;font-weight:800;color:var(--ac)}
.mm-ringw small{position:absolute;left:0;right:0;bottom:-18px;text-align:center;font-size:11px;color:var(--mute);font-weight:600}
.mm-acts{display:flex;gap:8px;margin-top:6px}
.mm-ab{display:inline-flex;align-items:center;justify-content:center;gap:5px;height:40px;padding:0 16px;border-radius:12px;font-size:14px;font-weight:700;
  text-decoration:none;transition:.2s;background:var(--ac);color:#fff;border:1.5px solid var(--ac);box-shadow:0 8px 16px -10px rgba(37,99,235,.9)}
.mm-ab:hover{background:var(--ac2);border-color:var(--ac2);transform:translateY(-2px);color:#fff}
.mm-ab.ghost{background:var(--soft);color:var(--ac2);border-color:#bfd3fb;box-shadow:none}
.mm-ab.ghost:hover{background:#dbe8ff;color:var(--ac2)}
.mm-ab .material-icons{font-size:17px}

.mm-empty{text-align:center;padding:40px 18px;border:2px dashed #93b4f5;border-radius:22px;background:var(--bg);color:var(--mute);animation:mm-up .5s ease both}
.mm-empty .material-icons{font-size:54px;color:var(--ac);display:block;margin:0 auto 10px;opacity:.85}
.mm-empty b{display:block;color:var(--ink);font-size:18px;margin-bottom:6px}
.mm-how{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-top:16px}
.mm-how div{background:var(--bg);border:1px solid var(--line);border-radius:16px;padding:14px;font-size:13px;color:var(--mute)}
.mm-how b{display:flex;align-items:center;gap:6px;color:var(--ink);font-size:14px;margin-bottom:4px}
.mm-how .material-icons{color:var(--ac)}

@keyframes mm-up{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:none}}
@media (max-width:640px){
  .mm-card{grid-template-columns:auto 1fr;gap:14px;padding:18px 16px}
  .mm-side{grid-column:1/-1;flex-direction:row;justify-content:space-between;min-width:0}
  .mm-ringw small{display:none}
  .mm-ringw{margin-bottom:0}
  .mm-acts{margin-top:0}
  .mm-btn{width:100%}
  .mm-how{grid-template-columns:1fr}
}
@media (prefers-reduced-motion:reduce){.mm *{animation:none!important;transition:none!important;opacity:1!important}.mm .mm-ringw .pr{stroke-dashoffset:var(--off)}}
</style>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

<div class="mm">


  <div class="mm-hero">
    <div class="mm-hbg"></div>
    <div class="mm-ttl">
      <div class="mm-ic"><span class="material-icons">gps_fixed</span></div>
      <div>
        <h1>Smart Worker Match</h1>
        <p>We rank workers by rating, verification, availability, experience and distance.</p>
      </div>
    </div>

    <form method="get" class="mm-form" id="mmsf">
      <div class="mm-sel">
        <span class="material-icons">handyman</span>
        <select name="cat" aria-label="Choose a skill"><?php foreach ($CATS as $x) {
            echo "<option" . ($c == $x ? " selected" : "") . ">" . e($x) . "</option>";
        } ?></select>
      </div>
      <input type="hidden" name="lat" id="lat" value="<?= $la ?: "" ?>"><input type="hidden" name="lng" id="lng" value="<?= $ln ?: "" ?>">
      <button class="mm-btn pri" id="mmgo"><span class="material-icons">search</span> Find best workers</button>
      <button type="button" class="mm-btn loc" id="mmloc"><span class="material-icons">my_location</span> Use my location</button>
    </form>
    <div class="mm-lstat" id="mmst">
      <?php if ($la): ?><span class="material-icons">location_on</span> Using your location to show distance<?php else: ?><span class="material-icons">info</span> Tip: use your location to see who is closest<?php endif; ?>
    </div>
  </div>

  <?php if ($c === ""): ?>
    <div class="mm-how">
      <div><b><span class="material-icons">touch_app</span> 1. Pick a skill</b>Choose the kind of worker you need.</div>
      <div><b><span class="material-icons">calculate</span> 2. We score them</b>Rating, jobs done, verified and nearby workers score higher.</div>
      <div><b><span class="material-icons">handshake</span> 3. Hire</b>Open a profile or hire the best match directly.</div>
    </div>
  <?php else: ?>
    <div class="mm-rhead">
      <h2>Best <?= e($c) ?> workers</h2>
      <?php if ($res): ?><span class="mm-pill"><?= count($res) ?> match<?= count($res) > 1 ? "es" : "" ?></span><?php endif; ?>
      <?php if ($la): ?><span class="mm-pill g"><span class="material-icons" style="font-size:14px">location_on</span> Distance on</span><?php endif; ?>
    </div>

    <?php if ($res): ?>
    <div class="mm-list">
      <?php foreach ($res as $i => $w):
          $sc = max(0, min(100, (int) $w["sc"]));
          $ph = $w["photo"] ?? "";
          $ini = e(mb_strtoupper(mb_substr((string) $w["name"], 0, 1)));
      ?>
      <div class="mm-card<?= $i === 0 ? " top" : "" ?>" style="--i:<?= $i ?>">
        <?php if ($i === 0): ?><span class="mm-ribbon"><span class="material-icons">star</span> BEST MATCH</span><?php endif; ?>
        <div class="mm-avw">
          <div class="mm-av"><?= $ph ? '<img src="' . e($ph) . '" alt="">' : $ini ?></div>
          <span class="mm-rank"><?= $i + 1 ?></span>
        </div>
        <div class="mm-who">
          <div class="mm-nm"><span class="n"><?= e($w["name"]) ?></span> <?= badge($w["verified"]) ?></div>
          <div class="mm-meta">
            <span class="mm-tag star"><span class="material-icons">star</span><?= $w["r"] ? round($w["r"], 1) : "No reviews" ?></span>
            <span class="mm-tag"><span class="material-icons">task_alt</span><?= (int) $w["done"] ?> jobs</span>
            <?php if ($w["available"]): ?>
              <span class="mm-tag on"><span class="material-icons">check_circle</span>Available</span>
            <?php else: ?>
              <span class="mm-tag off"><span class="material-icons">schedule</span>Busy</span>
            <?php endif; ?>
            <?php if ($w["d"] !== null): ?>
              <span class="mm-tag"><span class="material-icons">place</span><?= round($w["d"], 1) ?> km</span>
            <?php endif; ?>
            <?php if ($w["minp"]): ?>
              <span class="mm-tag price"><span class="material-icons">payments</span>From ₹<?= (int) $w["minp"] ?></span>
            <?php endif; ?>
          </div>
        </div>
        <div class="mm-side">
          <div class="mm-ringw" style="--off:<?= round(226.2 * (1 - $sc / 100), 1) ?>">
            <svg viewBox="0 0 84 84"><circle class="tr" cx="42" cy="42" r="36"/><circle class="pr" cx="42" cy="42" r="36"/></svg>
            <b data-n="<?= $sc ?>"><?= $sc ?>%</b>
            <small>match</small>
          </div>
          <div class="mm-acts">
            <a class="mm-ab" href="hire.php?w=<?= (int) $w["id"] ?>"><span class="material-icons">handshake</span> Hire</a>
            <a class="mm-ab ghost" href="worker.php?id=<?= (int) $w["id"] ?>"><span class="material-icons">person</span> Profile</a>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
      <div class="mm-empty"><span class="material-icons">person_search</span><b>No workers found for this skill</b>Try a different skill, or post a job and let workers send you offers.</div>
    <?php endif; ?>
  <?php endif; ?>

</div>

<script>
(function () {
  var $ = function (id) { return document.getElementById(id); };
  var form = $('mmsf'), go = $('mmgo'), loc = $('mmloc'), st = $('mmst');
  var reduce = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;

  function msg(icon, text, err) {
    st.className = 'mm-lstat' + (err ? ' err' : '');
    st.innerHTML = '<span class="material-icons">' + icon + '</span> ' + text;
  }

  form.addEventListener('submit', function () {
    go.classList.add('busy');
    go.innerHTML = '<span class="material-icons">autorenew</span> Searching...';
  });

  loc.addEventListener('click', function () {
    if (!navigator.geolocation) { msg('error_outline', 'Your browser does not support location.', true); return; }
    loc.classList.add('busy');
    loc.innerHTML = '<span class="material-icons">autorenew</span> Locating...';
    msg('my_location', 'Getting your location...');
    navigator.geolocation.getCurrentPosition(function (p) {
      $('lat').value = p.coords.latitude;
      $('lng').value = p.coords.longitude;
      msg('location_on', 'Location found. Searching...');
      form.submit();
    }, function () {
      loc.classList.remove('busy');
      loc.innerHTML = '<span class="material-icons">my_location</span> Use my location';
      msg('location_off', 'Could not get your location. Allow location access and try again.', true);
    }, { timeout: 10000 });
  });

  if (!reduce) {
    document.querySelectorAll('.mm-ringw b').forEach(function (el, i) {
      var target = +el.getAttribute('data-n'), t0 = null;
      el.textContent = '0%';
      setTimeout(function () {
        requestAnimationFrame(function tick(ts) {
          if (t0 === null) t0 = ts;
          var p = Math.min((ts - t0) / 1100, 1);
          el.textContent = Math.round(target * (1 - Math.pow(1 - p, 3))) + '%';
          if (p < 1) requestAnimationFrame(tick);
        });
      }, 250 + i * 100);
    });
  }
})();
</script>
<?php foot();
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
/* ===== Smart Match – scoped to .mt ===== */
.mt{--m-a:#4f46e5;--m-b:#00a8ff;--m-g:#12b76a;--ease:cubic-bezier(.2,.8,.2,1);
  position:relative;isolation:isolate;overflow:hidden;padding:22px;border-radius:22px;
  background:linear-gradient(160deg,#eef0ff 0%,#e8f6ff 55%,#f1fbff 100%);
  border:1px solid #dfe5fb;animation:mFade .6s var(--ease) both}
.mt *{box-sizing:border-box}
@keyframes mFade{from{opacity:0}to{opacity:1}}

/* ---- AI neural-network background (inline SVG, no external images) ---- */
.mt-bg{position:absolute;inset:0;z-index:-1;pointer-events:none;overflow:hidden}
.mt-bg svg{position:absolute;inset:0;width:100%;height:100%}
.mt-bg .ln{stroke:#4f46e5;stroke-opacity:.16;stroke-width:1;stroke-dasharray:4 6;animation:mDash 14s linear infinite}
.mt-bg .nd{fill:#4f46e5;fill-opacity:.35;transform-box:fill-box;transform-origin:center;animation:mNode 3.6s ease-in-out infinite}
.mt-bg .nd.b{fill:#00a8ff}
.mt-bg .nd:nth-of-type(2n){animation-delay:-1.2s}.mt-bg .nd:nth-of-type(3n){animation-delay:-2.4s}
.mt-bg .orb{position:absolute;border-radius:50%;filter:blur(40px);opacity:.55;animation:mOrb 12s ease-in-out infinite}
.mt-bg .o1{width:260px;height:260px;right:-60px;top:-80px;background:#9aa6ff}
.mt-bg .o2{width:220px;height:220px;left:-60px;bottom:-70px;background:#7fd6ff;animation-delay:-6s}
@keyframes mDash{to{stroke-dashoffset:-200}}
@keyframes mNode{0%,100%{transform:scale(1);fill-opacity:.3}50%{transform:scale(1.8);fill-opacity:.7}}
@keyframes mOrb{0%,100%{transform:translate(0,0)}50%{transform:translate(24px,18px)}}

/* ---- ICONS: small & consistent ---- */
.mt .material-icons{font-size:16px !important;line-height:1;vertical-align:-3px}
.mt .mi-xs{font-size:14px !important;color:#f5a300;vertical-align:-2px;display:inline-block;animation:mStar 3s ease-in-out infinite}

/* Title */
.mt-title{display:flex;align-items:center;gap:10px;margin:0 0 16px;font-size:20px;line-height:1.2;
  animation:mDrop .6s var(--ease) both}
.mt-title .mi{position:relative;flex:none;width:36px;height:36px;border-radius:11px;display:grid;place-items:center;color:#fff;
  font-size:18px !important;vertical-align:baseline;
  background:linear-gradient(135deg,var(--m-a),var(--m-b));box-shadow:0 6px 14px rgba(79,70,229,.3)}
.mt-title .mi::after{content:"";position:absolute;inset:0;border-radius:11px;border:2px solid var(--m-b);animation:mRadar 2.2s ease-out infinite}

/* Search form */
.mt form.mt-form{padding:14px;border-radius:16px;background:linear-gradient(120deg,#4f46e5,#6d5efc,#00a8ff,#4f46e5);background-size:300% 300%;
  box-shadow:0 12px 26px rgba(79,70,229,.28);animation:mRise .7s .05s var(--ease) both,mFlow 10s ease infinite}
.mt-form select{flex:1 1 200px;padding:11px 13px;border-radius:11px;border:2px solid transparent;background:rgba(255,255,255,.96);color:#222;font-size:14.5px;
  transition:border-color .25s,box-shadow .25s,transform .25s}
.mt-form select:focus{outline:none;border-color:#fff;box-shadow:0 0 0 4px rgba(255,255,255,.35);transform:translateY(-1px)}
.mt-form button{position:relative;overflow:hidden;display:inline-flex;align-items:center;gap:6px;padding:11px 18px;border:0;border-radius:11px;cursor:pointer;
  font-weight:700;font-size:14px;background:#fff;color:var(--m-a);transition:transform .2s,box-shadow .2s}
.mt-form button:hover{transform:translateY(-2px);box-shadow:0 8px 18px rgba(0,0,0,.22)}
.mt-form button:active{transform:scale(.97)}
.mt-form button .mi{transition:transform .3s}
.mt-form button:hover .mi{transform:scale(1.2) rotate(-10deg)}
.mt-form button.busy::before{content:"";display:inline-block;width:12px;height:12px;border-radius:50%;
  border:2px solid var(--m-a);border-top-color:transparent;animation:mSpin .7s linear infinite}
.m-ripple{position:absolute;border-radius:50%;background:rgba(79,70,229,.35);transform:scale(0);animation:mRipple .6s linear;pointer-events:none}

/* Result cards */
.mt .grid{margin-top:16px}
.mt .grid>.card{position:relative;overflow:hidden;border-radius:18px;padding-top:16px;background:rgba(255,255,255,.92);
  backdrop-filter:blur(4px);animation:mRise .7s var(--ease) both;transition:transform .3s var(--ease),box-shadow .3s}
.mt .grid>.card:nth-child(1){animation-delay:.1s}.mt .grid>.card:nth-child(2){animation-delay:.2s}
.mt .grid>.card:nth-child(3){animation-delay:.3s}.mt .grid>.card:nth-child(4){animation-delay:.4s}
.mt .grid>.card:nth-child(5){animation-delay:.5s}
.mt .grid>.card:hover{transform:translateY(-6px);box-shadow:0 18px 36px rgba(79,70,229,.2)}
.mt .grid>.card::before{content:"";position:absolute;left:0;top:0;width:100%;height:4px;
  background:linear-gradient(90deg,var(--m-a),var(--m-b));transform:scaleX(0);transform-origin:left;transition:transform .4s}
.mt .grid>.card:hover::before{transform:scaleX(1)}
.mt .grid>.card h2{margin:6px 0 4px;font-size:clamp(22px,3.6vw,26px);background:linear-gradient(90deg,var(--m-a),var(--m-b));
  -webkit-background-clip:text;background-clip:text;color:transparent;display:inline-block}

/* Best match highlight */
.mt .grid>.card:first-child{box-shadow:0 12px 28px rgba(79,70,229,.22);outline:2px solid rgba(79,70,229,.35);outline-offset:-2px}
.mt .grid>.card:first-child::before{transform:scaleX(1)}
.mt .grid>.card:first-child::after{content:"★ Best match";position:absolute;right:-34px;top:14px;transform:rotate(35deg);
  padding:3px 40px;font-size:11px;font-weight:700;color:#fff;background:linear-gradient(90deg,#ff9a3d,#ff4f93);animation:mGlow 2.4s ease-in-out infinite}

/* Score bar */
.m-bar{height:7px;border-radius:7px;background:#e3e7fa;overflow:hidden;margin:2px 0 10px}
.m-bar i{display:block;height:100%;border-radius:7px;background:linear-gradient(90deg,var(--m-a),var(--m-b));
  transform-origin:left;animation:mBar 1.1s .3s var(--ease) both}

/* Status dot */
.mt .dot-green{animation:mDot 1.6s ease-in-out infinite}

/* Buttons in cards */
.mt .btn{position:relative;overflow:hidden;display:inline-block;transition:transform .2s,box-shadow .25s,filter .2s}
.mt .btn:hover{transform:translateY(-2px);box-shadow:0 8px 18px rgba(15,98,254,.28);filter:brightness(1.07)}
.mt .btn:active{transform:scale(.96)}
.m-ripple.w{background:rgba(255,255,255,.55)}

/* Empty state */
.mt .grid>p{grid-column:1/-1;padding:24px;text-align:center;border-radius:16px;border:2px dashed #c5c9f0;background:rgba(247,248,255,.9);
  animation:mRise .6s both}

@keyframes mRise{from{opacity:0;transform:translateY(26px) scale(.97)}to{opacity:1;transform:none}}
@keyframes mDrop{from{opacity:0;transform:translateY(-18px)}to{opacity:1;transform:none}}
@keyframes mFlow{0%{background-position:0% 50%}50%{background-position:100% 50%}100%{background-position:0% 50%}}
@keyframes mRadar{0%{transform:scale(1);opacity:.8}100%{transform:scale(1.5);opacity:0}}
@keyframes mBar{from{transform:scaleX(0)}to{transform:scaleX(1)}}
@keyframes mDot{0%,100%{box-shadow:0 0 0 0 rgba(18,183,106,.55)}50%{box-shadow:0 0 0 7px rgba(18,183,106,0)}}
@keyframes mStar{0%,100%{transform:rotate(0) scale(1)}50%{transform:rotate(14deg) scale(1.15)}}
@keyframes mGlow{0%,100%{filter:brightness(1)}50%{filter:brightness(1.25)}}
@keyframes mSpin{to{transform:rotate(360deg)}}
@keyframes mRipple{to{transform:scale(4);opacity:0}}

@media (prefers-reduced-motion:reduce){
  .mt *,.mt *::before,.mt *::after{animation-duration:.01ms!important;animation-iteration-count:1!important;transition-duration:.01ms!important}
}
@media (max-width:520px){.mt{padding:14px}.mt-form button{width:100%;justify-content:center}}
</style>
<div class="mt">
<div class="mt-bg" aria-hidden="true">
  <span class="orb o1"></span><span class="orb o2"></span>
  <svg viewBox="0 0 800 600" preserveAspectRatio="xMidYMid slice" xmlns="http://www.w3.org/2000/svg">
    <g>
      <line class="ln" x1="80" y1="90" x2="240" y2="170"/><line class="ln" x1="240" y1="170" x2="420" y2="110"/>
      <line class="ln" x1="420" y1="110" x2="610" y2="190"/><line class="ln" x1="610" y1="190" x2="730" y2="90"/>
      <line class="ln" x1="240" y1="170" x2="190" y2="330"/><line class="ln" x1="420" y1="110" x2="470" y2="300"/>
      <line class="ln" x1="610" y1="190" x2="560" y2="360"/><line class="ln" x1="190" y1="330" x2="470" y2="300"/>
      <line class="ln" x1="470" y1="300" x2="560" y2="360"/><line class="ln" x1="190" y1="330" x2="90" y2="470"/>
      <line class="ln" x1="190" y1="330" x2="330" y2="500"/><line class="ln" x1="470" y1="300" x2="330" y2="500"/>
      <line class="ln" x1="560" y1="360" x2="700" y2="480"/><line class="ln" x1="330" y1="500" x2="520" y2="540"/>
      <line class="ln" x1="520" y1="540" x2="700" y2="480"/>
      <circle class="nd" cx="80" cy="90" r="5"/><circle class="nd b" cx="240" cy="170" r="6"/>
      <circle class="nd" cx="420" cy="110" r="5"/><circle class="nd b" cx="610" cy="190" r="6"/>
      <circle class="nd" cx="730" cy="90" r="4"/><circle class="nd b" cx="190" cy="330" r="6"/>
      <circle class="nd" cx="470" cy="300" r="7"/><circle class="nd b" cx="560" cy="360" r="5"/>
      <circle class="nd" cx="90" cy="470" r="4"/><circle class="nd b" cx="330" cy="500" r="6"/>
      <circle class="nd" cx="520" cy="540" r="4"/><circle class="nd b" cx="700" cy="480" r="5"/>
    </g>
  </svg>
</div>
<h2 class="mt-title"><span class="material-icons mi">gps_fixed</span> Smart Worker Match</h2><form method="get" class="mt-form" style="display:flex;gap:8px;flex-wrap:wrap"><select name="cat"><?php foreach (
    $CATS
    as $x
) {
    echo "<option" . ($c == $x ? " selected" : "") . ">" . $x . "</option>";
} ?></select>
<input type="hidden" name="lat" id="lat" value="<?= $la ?:
    "" ?>"><input type="hidden" name="lng" id="lng" value="<?= $ln ?:
    "" ?>"><button>Find best workers</button>
<button type="button" onclick="navigator.geolocation.getCurrentPosition(p=>{lat.value=p.coords.latitude;lng.value=p.coords.longitude;this.form.submit()})"><span class="material-icons mi">place</span> Use my location</button></form>
<div class="grid"><?php
foreach ($res as $w) {
    echo '<div class="card"><b>' .
        e($w["name"]) .
        "</b> " .
        badge($w["verified"]) .
        "<h2>" .
        $w["sc"] .
        '% match</h2><div class="m-bar"><i style="width:' .
        max(0, min(100, (int) $w["sc"])) .
        '%"></i></div>' .
        ($w["r"]
            ? round($w["r"], 1) .
                ' <span class="material-icons mi-xs">star</span>'
            : "No reviews") .
        " · " .
        $w["done"] .
        " jobs<br>" .
        ($w["available"]
            ? '<span class="dot-green"></span> Available'
            : '<span class="dot-red"></span> Busy') .
        ($w["d"] !== null
            ? ' · <span class="material-icons mi">place</span> ' .
                round($w["d"], 1) .
                " km"
            : "") .
        ($w["minp"] ? "<br>From ₹" . $w["minp"] : "") .
        '<br><br><a class="btn" href="hire.php?w=' .
        $w["id"] .
        '">Hire</a> <a class="btn" href="worker.php?id=' .
        $w["id"] .
        '">Profile</a></div>';
}
if ($c !== "" && !$res) {
    echo "<p>No workers found for this skill.</p>";
}
?></div></div>
<script>
(function () {
  var root = document.querySelector(".mt");
  if (!root) return;
  var reduce = window.matchMedia && matchMedia("(prefers-reduced-motion: reduce)").matches;

  // Count-up for the match percentage
  if (!reduce) {
    root.querySelectorAll(".grid>.card h2").forEach(function (el, i) {
      var m = el.textContent.match(/^(\d+)(% match)$/);
      if (!m) return;
      var target = parseInt(m[1], 10), suffix = m[2], t0 = null, dur = 1100, delay = 150 + i * 100;
      el.textContent = "0" + suffix;
      setTimeout(function () {
        requestAnimationFrame(function tick(ts) {
          if (t0 === null) t0 = ts;
          var p = Math.min((ts - t0) / dur, 1);
          el.textContent = Math.round(target * (1 - Math.pow(1 - p, 3))) + suffix;
          if (p < 1) requestAnimationFrame(tick);
        });
      }, delay);
    });
  }

  // Loading spinner on the search buttons (does not block submit or geolocation)
  var form = root.querySelector("form");
  if (form) {
    form.addEventListener("submit", function () {
      var b = form.querySelector("button:not([type])");
      if (b) b.classList.add("busy");
    });
    var geo = form.querySelector('button[type="button"]');
    if (geo) geo.addEventListener("click", function () { geo.classList.add("busy"); });
  }

  // Ripple on buttons and links (navigation and submit still work)
  root.addEventListener("click", function (e) {
    var b = e.target.closest(".btn, button");
    if (!b || reduce) return;
    var r = b.getBoundingClientRect(), d = Math.max(r.width, r.height);
    var s = document.createElement("span");
    s.className = "m-ripple" + (b.classList.contains("btn") ? " w" : "");
    s.style.width = s.style.height = d + "px";
    s.style.left = (e.clientX - r.left - d / 2) + "px";
    s.style.top = (e.clientY - r.top - d / 2) + "px";
    b.appendChild(s);
    setTimeout(function () { s.remove(); }, 600);
  });
})();
</script>
<?php foot();
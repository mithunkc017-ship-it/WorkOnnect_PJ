<?php // Homepage v14: animated hero, live stats, services, features, testimonials, FAQ (same data & functionality as v13)
require "config.php";
head("Home");
$c = fn($q) => (float) $pdo->query($q)->fetchColumn();
$nW = (int) $c("SELECT COUNT(*) FROM users WHERE role='worker'");
$nD = (int) $c("SELECT COUNT(*) FROM jobs WHERE status='completed'");
$nV = (int) $c("SELECT COUNT(*) FROM users WHERE role='worker' AND verified=1");
$avg = round($c("SELECT AVG(stars) FROM reviews"), 1);
$w = $pdo
    ->query(
        "SELECT u.name,u.skill,(SELECT ROUND(AVG(stars),1) FROM reviews WHERE worker_id=u.id) r,(SELECT COUNT(*) FROM reviews WHERE worker_id=u.id) rc FROM users u WHERE role='worker' AND verified=1 ORDER BY r DESC LIMIT 6",
    )
    ->fetchAll();
$q = $pdo
    ->query(
        "SELECT r.stars,r.comment,u.name FROM reviews r JOIN users u ON u.id=r.customer_id WHERE r.stars>=4 AND r.comment<>'' ORDER BY r.id DESC LIMIT 3",
    )
    ->fetchAll();
$ico = [
    "Electrician" => "bolt",
    "Plumber" => "plumbing",
    "Carpenter" => "carpenter",
    "Painter" => "palette",
    "Cleaner" => "cleaning_services",
    "Driver" => "directions_car",
    "Mechanic" => "build",
    "AC Technician" => "ac_unit",
];
?>
<style>/* ===== Landing page v14 (scoped to .lp so other pages are untouched) ===== */
.lp {
  --o: #ff9800;
  --t: #1a6b7c;
  --t2: #2f8a9b;
  --v: #7b5cff;
}
.lp h2 {
  position: relative;
  display: inline-block;
  margin: 46px 0 4px;
  padding-bottom: 10px;
}
.lp h2::after {
  content: "";
  position: absolute;
  left: 0;
  bottom: 0;
  width: 48px;
  height: 4px;
  border-radius: 4px;
  background: linear-gradient(90deg, var(--o), #ffcc70);
  transition: width 0.6s ease;
}
.lp h2.in::after {
  width: 84px;
}
.lp .grid .card {
  animation: none;
}

/* reveal on scroll (only when JS is running, so content is never hidden without it) */
.lp.js .rv {
  opacity: 0;
  transform: translateY(26px);
  transition:
    opacity 0.7s ease,
    transform 0.7s cubic-bezier(0.2, 0.8, 0.2, 1);
}
.lp.js .rv.in {
  opacity: 1;
  transform: none;
}
.lp.js .grid.rv,
.lp.js .stats.rv {
  opacity: 1;
  transform: none;
}
.lp.js .grid.rv > *,
.lp.js .stats.rv > *,
.lp.js details.rv {
  opacity: 0;
  transform: translateY(28px) scale(0.97);
  transition:
    opacity 0.6s ease,
    transform 0.6s cubic-bezier(0.2, 0.8, 0.2, 1),
    box-shadow 0.25s,
    border-color 0.25s;
}
.lp.js .grid.rv.in > *,
.lp.js .stats.rv.in > *,
.lp.js details.rv.in {
  opacity: 1;
  transform: none;
}
.lp.js .rv.in > :nth-child(2) {
  transition-delay: 0.08s;
}
.lp.js .rv.in > :nth-child(3) {
  transition-delay: 0.16s;
}
.lp.js .rv.in > :nth-child(4) {
  transition-delay: 0.24s;
}
.lp.js .rv.in > :nth-child(5) {
  transition-delay: 0.32s;
}
.lp.js .rv.in > :nth-child(6) {
  transition-delay: 0.4s;
}
.lp.js .rv.in > :nth-child(n + 7) {
  transition-delay: 0.48s;
}
.lp.js .grid.rv.in > .card:hover {
  transform: translateY(-6px);
  transition-delay: 0s;
}

/* hero */
.lp .hero {
  padding: clamp(54px, 9vw, 96px) 20px clamp(46px, 7vw, 70px);
  border-radius: 28px;
  background: linear-gradient(
    125deg,
    #115564,
    #1a6b7c 35%,
    #2f8a9b 62%,
    #6a54e8
  );
  background-size: 260% 260%;
  box-shadow: 0 24px 60px #1a6b7c45;
}
.lp .hero::before {
  width: 300px;
  height: 300px;
  left: -90px;
  top: -110px;
  background: radial-gradient(circle, #ffffff2e, #ffffff05 70%);
}
.lp .hero::after {
  width: 260px;
  height: 260px;
  right: -70px;
  bottom: -90px;
  background: radial-gradient(circle, #ffb347aa, #ff980000 70%);
}
.lp .orb {
  position: absolute;
  border-radius: 50%;
  filter: blur(2px);
  pointer-events: none;
  animation: floaty 11s ease-in-out infinite;
}
.lp .orb.a {
  width: 90px;
  height: 90px;
  left: 12%;
  top: 58%;
  background: #ffffff14;
  animation-delay: -2s;
}
.lp .orb.b {
  width: 56px;
  height: 56px;
  right: 16%;
  top: 18%;
  background: #ff980040;
  animation-delay: -6s;
}
.lp .orb.c {
  width: 34px;
  height: 34px;
  left: 24%;
  top: 14%;
  background: #ffffff22;
  animation-delay: -4s;
}
.lp .fi {
  position: absolute;
  font-size: clamp(22px, 3.4vw, 38px);
  opacity: 0.9;
  pointer-events: none;
  filter: drop-shadow(0 6px 10px #0004);
  animation: bob 6s ease-in-out infinite;
  z-index: 0;
}
.lp .fi.f1 {
  left: 6%;
  top: 20%;
}
.lp .fi.f2 {
  right: 7%;
  top: 30%;
  animation-delay: -1.5s;
}
.lp .fi.f3 {
  left: 10%;
  bottom: 14%;
  animation-delay: -3s;
}
.lp .fi.f4 {
  right: 11%;
  bottom: 12%;
  animation-delay: -4.5s;
}
.lp .pill {
  position: relative;
  z-index: 1;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: #ffffff1f;
  border: 1px solid #ffffff40;
  backdrop-filter: blur(6px);
  -webkit-backdrop-filter: blur(6px);
  padding: 6px 16px;
  border-radius: 999px;
  font-size: 13px;
  margin-bottom: 16px;
  animation: fadeUp 0.7s ease both;
}
.lp .pill i {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #5cffb1;
  box-shadow: 0 0 0 0 #5cffb199;
  animation: ping 1.8s ease infinite;
}
.lp .hero h1 {
  margin: 0 0 10px;
  background: linear-gradient(
    90deg,
    #fff 0%,
    #fff 40%,
    #ffd9a0 55%,
    #fff 70%,
    #fff 100%
  );
  background-size: 250% 100%;
  -webkit-background-clip: text;
  background-clip: text;
  -webkit-text-fill-color: transparent;
  color: transparent;
  animation:
    fadeUp 0.8s 0.1s ease both,
    shine 6s 1.2s linear infinite;
  text-shadow: none;
}
.lp .hero p {
  font-size: clamp(15px, 2.4vw, 18px);
  max-width: 560px;
  margin: 0 auto;
  opacity: 0.95;
  animation: fadeUp 0.8s 0.22s ease both;
}
.lp .hs {
  max-width: 700px;
  margin: 28px auto 18px;
  padding: 10px;
  border-radius: 20px;
  box-shadow: 0 18px 44px #0000004d;
  transition:
    box-shadow 0.3s,
    transform 0.3s;
  animation: fadeUp 0.8s 0.34s ease both;
  text-align: left;
}
.lp .hs:focus-within {
  box-shadow:
    0 0 0 4px #ff980055,
    0 18px 44px #0000004d;
  transform: translateY(-2px);
}
.lp .hs select,
.lp .hs input {
  border-radius: 12px;
  padding: 13px 14px;
}
.lp .hs button {
  border-radius: 12px;
  padding: 13px 24px;
  background: linear-gradient(135deg, #ff9800, #ff6f3c);
  font-weight: 600;
}
.lp .hero > .btn {
  padding: 13px 26px;
  border-radius: 12px;
  font-weight: 600;
  margin: 4px;
  animation: fadeUp 0.8s 0.46s ease both;
  transition:
    transform 0.2s,
    box-shadow 0.25s;
}
.lp .hero > .btn:first-of-type {
  background: linear-gradient(135deg, #ff9800, #ff6f3c);
  animation:
    fadeUp 0.8s 0.46s ease both,
    glow 2.8s 1.4s ease infinite;
}
.lp .hero > .btn + .btn {
  background: #ffffff1f;
  border: 1.5px solid #ffffff80;
  backdrop-filter: blur(6px);
  -webkit-backdrop-filter: blur(6px);
}
.lp .hero > .btn + .btn:hover {
  background: #fff;
  color: var(--t);
  box-shadow: 0 8px 20px #0003;
}
.lp .trust {
  margin-top: 22px;
  gap: 10px;
  animation: fadeUp 0.8s 0.58s ease both;
}
.lp .trust span {
  background: #ffffff1a;
  border: 1px solid #ffffff33;
  padding: 6px 14px;
  border-radius: 999px;
  transition:
    transform 0.2s,
    background 0.2s;
}
.lp .trust span:hover {
  transform: translateY(-3px);
  background: #ffffff33;
}

/* stats */
.lp .stats {
  margin: -26px 14px 0;
  position: relative;
  z-index: 2;
}
.lp .stat {
  border-bottom: 0;
  border-radius: 18px;
  padding: 20px 12px;
  background: #ffffffee;
  backdrop-filter: blur(8px);
  -webkit-backdrop-filter: blur(8px);
  box-shadow: 0 12px 30px #1a6b7c22;
  position: relative;
  overflow: hidden;
  transition:
    transform 0.25s,
    box-shadow 0.25s;
}
.lp .stat::after {
  content: "";
  position: absolute;
  left: 0;
  right: 0;
  bottom: 0;
  height: 4px;
  background: linear-gradient(90deg, var(--o), #ffcc70, var(--t2));
  background-size: 200% 100%;
  animation: slide 4s linear infinite;
}
.lp .stat:hover {
  transform: translateY(-5px) !important;
  box-shadow: 0 18px 36px #1a6b7c33;
}
.lp .stat b {
  font-size: clamp(26px, 4vw, 36px);
  background: linear-gradient(135deg, var(--t), var(--v));
  -webkit-background-clip: text;
  background-clip: text;
  -webkit-text-fill-color: transparent;
}

/* cards */
.lp .card {
  border: 1px solid #1a6b7c14;
  position: relative;
  overflow: hidden;
}
.lp .card::before {
  content: "";
  position: absolute;
  inset: 0;
  background: radial-gradient(
    260px circle at var(--mx, 50%) var(--my, 0%),
    #ff98001c,
    transparent 60%
  );
  opacity: 0;
  transition: opacity 0.3s;
  pointer-events: none;
}
.lp .card:hover::before {
  opacity: 1;
}
.lp .card:hover {
  border-color: #1a6b7c40;
}
.lp a.svc {
  padding: 24px 14px;
}
.lp .svc i {
  width: 62px;
  height: 62px;
  line-height: 62px;
  margin: 0 auto 12px;
  border-radius: 20px;
  background: linear-gradient(135deg, #e6f3f5, #fff1dc);
  font-size: 30px;
  transition:
    transform 0.35s cubic-bezier(0.3, 1.6, 0.5, 1),
    background 0.3s;
}
.lp .svc:hover i {
  transform: scale(1.15) rotate(-8deg);
  background: linear-gradient(135deg, #fff1dc, #ffe0b0);
}
.lp .feat {
  border-top: 0;
}
.lp .feat::after {
  content: "";
  position: absolute;
  left: 0;
  top: 0;
  right: 0;
  height: 4px;
  background: linear-gradient(90deg, var(--t), var(--t2), var(--o));
}
.lp .feat i {
  width: 54px;
  height: 54px;
  line-height: 54px;
  text-align: center;
  border-radius: 16px;
  background: #e6f3f5;
  font-size: 26px;
  margin-bottom: 12px;
  transition: transform 0.35s cubic-bezier(0.3, 1.6, 0.5, 1);
}
.lp .feat:hover i {
  transform: scale(1.12) rotate(6deg);
}
.lp .feat b {
  font-size: 16px;
}
.lp .step {
  padding-top: 26px;
  font-weight: 600;
  counter-increment: st;
}
.lp .step::after {
  content: "";
  position: absolute;
  right: -30px;
  bottom: -30px;
  width: 90px;
  height: 90px;
  border-radius: 50%;
  background: radial-gradient(circle, #ff980030, transparent 70%);
}
.lp .how {
  counter-reset: st;
}
.lp .vw {
  text-align: center;
  padding: 22px 14px;
}
.lp .av {
  width: 58px;
  height: 58px;
  margin: 0 auto 10px;
  border-radius: 50%;
  display: grid;
  place-items: center;
  color: #fff;
  font-weight: 700;
  font-size: 22px;
  background: linear-gradient(135deg, var(--t), var(--v));
  box-shadow:
    0 0 0 4px #fff,
    0 0 0 6px #1a6b7c33;
  transition: transform 0.3s;
}
.lp .vw:hover .av {
  transform: scale(1.1) rotate(-6deg);
}
.lp .vw b {
  font-size: 16px;
}
.lp .quote {
  border-left: 0;
  padding-top: 30px;
}
.lp .quote::after {
  content: "“";
  position: absolute;
  right: 14px;
  top: -6px;
  font-size: 84px;
  line-height: 1;
  color: #ff980030;
  font-family: Georgia, serif;
}
.lp details.card {
  margin: 10px 0;
  transition:
    box-shadow 0.25s,
    border-color 0.25s;
}
.lp details.card summary {
  list-style: none;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 10px;
}
.lp details.card summary::-webkit-details-marker {
  display: none;
}
.lp details.card summary::after {
  content: "+";
  flex: none;
  width: 28px;
  height: 28px;
  border-radius: 50%;
  display: grid;
  place-items: center;
  background: #e6f3f5;
  color: var(--t);
  font-size: 20px;
  transition:
    transform 0.3s,
    background 0.3s;
}
.lp details[open].card summary::after {
  transform: rotate(135deg);
  background: var(--o);
  color: #fff;
}
.lp details[open].card {
  border-color: #ff980066;
  box-shadow: 0 10px 24px #ff98001f;
}
.lp details[open] p {
  animation: fadeUp 0.35s ease;
}

/* closing call-to-action */
.lp .lpcta {
  margin: 54px 0 10px;
  text-align: center;
  color: #fff;
  border-radius: 26px;
  padding: clamp(34px, 6vw, 56px) 20px;
  background: linear-gradient(125deg, #115564, #1a6b7c, #6a54e8);
  background-size: 220% 220%;
  animation: gradientShift 10s ease infinite;
  position: relative;
  overflow: hidden;
  box-shadow: 0 20px 50px #1a6b7c40;
}
.lp .lpcta::before {
  content: "";
  position: absolute;
  width: 260px;
  height: 260px;
  right: -60px;
  top: -100px;
  border-radius: 50%;
  background: radial-gradient(circle, #ffb347aa, transparent 70%);
  animation: floaty 9s ease-in-out infinite;
}
.lp .lpcta h2 {
  margin: 0 0 8px;
  color: #fff;
  padding: 0;
  display: block;
}
.lp .lpcta h2::after {
  display: none;
}
.lp .lpcta p {
  margin: 0 0 20px;
  opacity: 0.92;
  position: relative;
}
.lp .lpcta .btn {
  position: relative;
  margin: 4px;
  padding: 13px 26px;
  border-radius: 12px;
  font-weight: 600;
  background: linear-gradient(135deg, #ff9800, #ff6f3c);
}
.lp .lpcta .btn + .btn {
  background: #fff;
  color: var(--t);
}

/* scroll progress + back-to-top polish */
#sp {
  position: fixed;
  left: 0;
  top: 0;
  height: 3px;
  width: 0;
  z-index: 100;
  background: linear-gradient(90deg, #ff9800, #7b5cff);
  transition: width 0.1s linear;
}
#up {
  box-shadow: 0 8px 18px #1a6b7c55;
  animation: pop 0.4s ease;
}

/* dark mode */
.dark .lp .stat {
  background: #1e2638ee;
}
.dark .lp .stat b {
  background: linear-gradient(135deg, #7fe0f0, #b3a1ff);
  -webkit-background-clip: text;
  background-clip: text;
}
.dark .lp .svc i,
.dark .lp .feat i {
  background: linear-gradient(135deg, #26405a, #3a3320);
}
.dark .lp .card {
  border-color: #ffffff14;
}
.dark .lp .av {
  box-shadow:
    0 0 0 4px #1e2638,
    0 0 0 6px #7fe0f044;
}
.dark .lp details.card summary::after {
  background: #2a3350;
  color: #8ab4ff;
}

@keyframes bob {
  0%,
  100% {
    transform: translateY(0) rotate(-4deg);
  }
  50% {
    transform: translateY(-14px) rotate(6deg);
  }
}
@keyframes shine {
  0% {
    background-position: 150% 0;
  }
  100% {
    background-position: -100% 0;
  }
}
@keyframes glow {
  0%,
  100% {
    box-shadow: 0 0 0 0 #ff980066;
  }
  50% {
    box-shadow: 0 0 0 12px #ff980000;
  }
}
@keyframes ping {
  0% {
    box-shadow: 0 0 0 0 #5cffb199;
  }
  80%,
  100% {
    box-shadow: 0 0 0 9px #5cffb100;
  }
}
@keyframes slide {
  to {
    background-position: 200% 0;
  }
}
@media (max-width: 700px) {
  .lp .fi {
    display: none;
  }
  .lp .stats {
    margin: -20px 4px 0;
  }
  .lp .hs {
    border-radius: 16px;
  }
}
@media (prefers-reduced-motion: reduce) {
  .lp.js .rv,
  .lp.js .rv > *,
  .lp.js details.rv {
    opacity: 1 !important;
    transform: none !important;
  }
  .lp .hero h1 {
    -webkit-text-fill-color: #fff;
  }
}
</style>
<div id="sp"></div>
<div class="lp">
    <script>
        document.currentScript.parentNode.classList.add("js");
    </script>

    <section class="hero" aria-labelledby="home-title">
        <span class="orb a"></span>
        <span class="orb b"></span>
        <span class="orb c"></span>
        <span class="material-icons fi f1" aria-hidden="true">bolt</span>
        <span class="material-icons fi f2" aria-hidden="true">build</span>
        <span class="material-icons fi f3" aria-hidden="true">palette</span>
        <span class="material-icons fi f4" aria-hidden="true">cleaning_services</span>

        <span class="pill"><i></i><span class="material-icons mi-xs" aria-hidden="true">verified</span>Trusted local professionals · Karnataka</span>
        <h1 id="home-title"><?= t("Find Skilled Workers Near You") ?></h1>
        <p><?= t(
            "Post a job, get offers, track progress – all in one place.",
        ) ?></p>

        <form class="hs" action="workers.php" method="get">
            <select name="cat">
                <option value="">All services</option>
                <?php foreach ($CATS as $x) { ?>
                    <option><?= e($x) ?></option>
                <?php } ?>
            </select>
            <input name="ar" placeholder="Your area (e.g. Sagar)">
            <button><span class="material-icons mi" aria-hidden="true">search</span>Search</button>
        </form>

        <a class="btn" href="post_job.php"><?= t("Post a Job") ?></a>
        <a class="btn" href="register.php"><?= t("Join as Worker") ?></a>

        <div class="trust">
            <span><span class="material-icons mi-xs" aria-hidden="true">verified</span>ID-verified workers</span>
            <span><span class="material-icons mi-xs" aria-hidden="true">emergency</span>Emergency booking</span>
            <span><span class="material-icons mi-xs" aria-hidden="true">chat</span>Live chat</span>
            <span><span class="material-icons mi-xs" aria-hidden="true">translate</span>ಕನ್ನಡ + English</span>
        </div>
    </section>

    <div class="stats rv">
        <div class="stat">
            <b data-n="<?= $nW ?>">0</b>
            <span>Workers</span>
        </div>
        <div class="stat">
            <b data-n="<?= $nV ?>">0</b>
            <span>Verified</span>
        </div>
        <div class="stat">
            <b data-n="<?= $nD ?>">0</b>
            <span>Jobs completed</span>
        </div>
        <div class="stat">
            <b><?= $avg ?:
                "–" ?> <span class="material-icons mi-xs" aria-label="stars">star</span></b>
            <span>Average rating</span>
        </div>
    </div>

    <section aria-labelledby="services-title">
        <h2 id="services-title" class="rv"><?= t("Services") ?></h2>
        <div class="grid rv">
            <?php foreach ($CATS as $x) { ?>
                <a class="card svc" href="workers.php?cat=<?= urlencode($x) ?>">
                    <i><span class="material-icons" aria-hidden="true"><?= $ico[
                        $x
                    ] ?? "build" ?></span></i>
                    <?= e($x) ?>
                </a>
            <?php } ?>
        </div>
    </section>

    <section aria-labelledby="how-title">
        <h2 id="how-title" class="rv"><?= t("How it works") ?></h2>
        <div class="grid how rv">
            <div class="card step"><span class="material-icons mi" aria-hidden="true">post_add</span><?= t(
                "1. Customer posts a job",
            ) ?></div>
            <div class="card step"><span class="material-icons mi" aria-hidden="true">local_offer</span><?= t(
                "2. Workers send offers",
            ) ?></div>
            <div class="card step"><span class="material-icons mi" aria-hidden="true">task_alt</span><?= t(
                "3. Accept the best offer",
            ) ?></div>
            <div class="card step"><span class="material-icons mi" aria-hidden="true">track_changes</span><?= t(
                "4. Track job progress",
            ) ?></div>
        </div>
    </section>

    <section aria-labelledby="features-title">
        <h2 id="features-title" class="rv">Why Workonnect?</h2>
        <div class="grid rv">
            <div class="card feat">
                <i><span class="material-icons" aria-hidden="true">security</span></i>
                <b>Verified &amp; trusted</b>
                <p>Workers upload ID proof; Top Rated, Gold, Silver and Bronze levels show real performance.</p>
            </div>
            <div class="card feat">
                <i><span class="material-icons" aria-hidden="true">emergency</span></i>
                <b>Emergency jobs</b>
                <p>Mark a job urgent and it appears first for workers.</p>
            </div>
            <div class="card feat">
                <i><span class="material-icons" aria-hidden="true">place</span></i>
                <b>Nearby workers</b>
                <p>Use "Near me" to find the closest available worker.</p>
            </div>
            <div class="card feat">
                <i><span class="material-icons" aria-hidden="true">payments</span></i>
                <b>Fair prices</b>
                <p>Compare offers, apply coupons, pay from wallet. Only a 10% platform fee.</p>
            </div>
            <div class="card feat">
                <i><span class="material-icons" aria-hidden="true">chat</span></i>
                <b>Chat &amp; tracking</b>
                <p>Talk to your worker and follow every step of the job.</p>
            </div>
            <div class="card feat">
                <i><span class="material-icons" aria-hidden="true">notifications</span></i>
                <b>Instant alerts</b>
                <p>Live notification bell for offers, payments and updates.</p>
            </div>
        </div>
    </section>

    <section aria-labelledby="workers-title">
        <h2 id="workers-title" class="rv"><?= t("Verified Workers") ?></h2>
        <div class="grid rv">
            <?php foreach ($w as $x) { ?>
                <div class="card vw">
                    <div class="av"><?= e(
                        mb_strtoupper(mb_substr(trim($x["name"]), 0, 1)),
                    ) ?></div>
                    <b><?= e($x["name"]) ?></b><br>
                    <?= e($x["skill"]) ?><br>
                    <?php if ($x["r"]) { ?>
                        <?= $x[
                            "r"
                        ] ?> <span class="material-icons mi-xs" aria-label="stars">star</span> (<?= $x[
     "rc"
 ] ?>)<br>
                    <?php } ?>
                    <?= badge(1) ?>
                </div>
            <?php } ?>
            <?php if (!$w) { ?>
                <p>No verified workers yet.</p>
            <?php } ?>
        </div>
    </section>

    <?php if ($q) { ?>
        <section aria-labelledby="reviews-title">
            <h2 id="reviews-title" class="rv">What customers say</h2>
            <div class="grid rv">
                <?php foreach ($q as $x) { ?>
                    <div class="card quote">
                        <?php for ($i = 0; $i < (int) $x["stars"]; $i++) { ?>
                            <span class="material-icons mi-xs" aria-hidden="true">star</span>
                        <?php } ?>
                        <q><?= e($x["comment"]) ?></q>
                        <small>– <?= e($x["name"]) ?></small>
                    </div>
                <?php } ?>
            </div>
        </section>
    <?php } ?>

    <section aria-labelledby="faq-title">
        <h2 id="faq-title" class="rv">Quick answers</h2>
        <details class="card rv">
            <summary>Is payment safe?</summary>
            <p>Pay by UPI, card, net banking, cash or wallet. We never store your card or UPI details.</p>
        </details>
        <details class="card rv">
            <summary>How do I become a verified worker?</summary>
            <p>Register as a worker, open "Get Verified" and upload your ID. Admin approves it and your badge appears.</p>
        </details>
        <details class="card rv">
            <summary>What if the job goes wrong?</summary>
            <p>You can cancel, request a refund, or file a complaint from the job page.</p>
        </details>
    </section>

    <section class="lpcta rv" aria-labelledby="cta-title">
        <h2 id="cta-title"><?= t("Find Skilled Workers Near You") ?></h2>
        <p><?= t(
            "Post a job, get offers, track progress – all in one place.",
        ) ?></p>
        <a class="btn" href="post_job.php"><?= t("Post a Job") ?></a>
        <a class="btn" href="register.php"><?= t("Join as Worker") ?></a>
    </section>
</div>
<button id="up" onclick="scrollTo({top:0,behavior:'smooth'})" title="Back to top" aria-label="Back to top" hidden><span class="material-icons" aria-hidden="true">arrow_upward</span></button>
<script>// animated counters (ease-out), same data-n values as before
document.querySelectorAll(".stat b[data-n]").forEach(function (b) {
  var n = +b.dataset.n,
    done = 0;
  function run() {
    if (done) return;
    done = 1;
    if (!n) {
      b.textContent = 0;
      return;
    }
    var t0 = performance.now(),
      d = 1400;
    (function f(t) {
      var p = Math.min(1, (t - t0) / d),
        e = 1 - Math.pow(1 - p, 3);
      b.textContent = Math.round(n * e);
      if (p < 1) requestAnimationFrame(f);
    })(t0);
  }
  b._run = run;
});
// scroll reveal
var io =
  "IntersectionObserver" in window
    ? new IntersectionObserver(
        function (es) {
          es.forEach(function (x) {
            if (x.isIntersecting) {
              x.target.classList.add("in");
              x.target.querySelectorAll(".stat b").forEach(function (b) {
                b._run && b._run();
              });
              io.unobserve(x.target);
            }
          });
        },
        { threshold: 0.15 },
      )
    : null;
document.querySelectorAll(".lp .rv").forEach(function (el) {
  io ? io.observe(el) : el.classList.add("in");
});
if (!io)
  document.querySelectorAll(".stat b[data-n]").forEach(function (b) {
    b._run();
  });
// card spotlight follows the pointer
document.querySelectorAll(".lp .card").forEach(function (c) {
  c.addEventListener("pointermove", function (e) {
    var r = c.getBoundingClientRect();
    c.style.setProperty("--mx", e.clientX - r.left + "px");
    c.style.setProperty("--my", e.clientY - r.top + "px");
  });
});
// back-to-top + scroll progress bar
var up = document.getElementById("up"),
  sp = document.getElementById("sp");
addEventListener(
  "scroll",
  function () {
    up.hidden = scrollY < 400;
    var h = document.documentElement.scrollHeight - innerHeight;
    sp.style.width = (h > 0 ? (scrollY / h) * 100 : 0) + "%";
  },
  { passive: true },
);
</script>
<?php foot();

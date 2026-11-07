<?php
require "config.php";
ob_start();
head("How it works");
$pageHead = ob_get_clean();
$pageHead = preg_replace(
    '#<nav>.*?</nav>#s',
    '<nav aria-label="Page header"><a class="logo" href="index.php">Work<span>onnect</span></a></nav>',
    $pageHead,
    1,
    $navReplaced,
);
if ($navReplaced !== 1) {
    throw new RuntimeException("Could not simplify the How It Works page header.");
}
echo $pageHead;
?>
<style>
/* ===== How it works – scoped visual enhancements ===== */
.wk-how {
    --wk-a: #4f46e5;
    --wk-b: #06b6d4;
    --wk-c: #8b5cf6;
    --wk-ink: #0f172a;
    --wk-muted: #475569;
    position: relative;
    overflow: hidden;
    isolation: isolate;
    max-width: 1120px;
    margin: 24px auto;
    padding: clamp(28px, 5vw, 64px) clamp(18px, 4vw, 48px);
    border-radius: 28px;
    background:
        radial-gradient(1100px 400px at 0% -10%, rgba(6,182,212,.12), transparent 60%),
        radial-gradient(900px 400px at 100% 0%, rgba(139,92,246,.12), transparent 60%),
        #ffffff;
    box-shadow: 0 20px 60px -20px rgba(30,41,59,.25);
}
.wk-how::before,
.wk-how::after {
    content: "";
    position: absolute;
    z-index: -1;
    border-radius: 50%;
    filter: blur(60px);
    opacity: .4;
    pointer-events: none;
}
.wk-how::before {
    width: 340px; height: 340px;
    top: -100px; right: -90px;
    background: linear-gradient(135deg, var(--wk-c), var(--wk-a));
    animation: wk-float 16s ease-in-out infinite;
}
.wk-how::after {
    width: 320px; height: 320px;
    bottom: -110px; left: -90px;
    background: linear-gradient(135deg, var(--wk-b), var(--wk-c));
    animation: wk-float 20s ease-in-out infinite reverse;
}
@keyframes wk-float {
    0%, 100% { transform: translate(0, 0) scale(1); }
    50%      { transform: translate(-40px, 30px) scale(1.12); }
}
@keyframes wk-fade-up {
    from { opacity: 0; transform: translateY(22px); }
    to   { opacity: 1; transform: translateY(0); }
}
@keyframes wk-shine { to { background-position: 250% 0; } }

/* hero */
.wk-how > h1 {
    margin: 0 0 14px;
    font-size: clamp(1.9rem, 4.6vw, 3.1rem);
    line-height: 1.12;
    letter-spacing: -.02em;
    text-align: center;
    color: var(--wk-ink);
    background: linear-gradient(90deg, var(--wk-a), var(--wk-c), var(--wk-b), var(--wk-a));
    background-size: 250% 100%;
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
    animation: wk-fade-up .8s cubic-bezier(.2,.7,.2,1) both,
               wk-shine 8s linear infinite;
}
.wk-how > h1 + p {
    max-width: 640px;
    margin: 0 auto 44px;
    text-align: center;
    font-size: clamp(1rem, 1.6vw, 1.15rem);
    line-height: 1.65;
    color: var(--wk-muted);
    animation: wk-fade-up .8s .12s cubic-bezier(.2,.7,.2,1) both;
}

/* steps grid with connecting timeline */
.wk-how .grid {
    position: relative;
    display: grid;
    grid-template-columns: 1fr;
    gap: 22px;
}
@media (min-width: 600px) {
    .wk-how .grid { grid-template-columns: repeat(2, 1fr); }
}
@media (min-width: 1000px) {
    .wk-how .grid { grid-template-columns: repeat(4, 1fr); }
    .wk-how .grid::before {
        content: "";
        position: absolute;
        top: 58px;
        left: 12%;
        right: 12%;
        height: 4px;
        border-radius: 4px;
        background: linear-gradient(90deg, var(--wk-a), var(--wk-b), var(--wk-c), var(--wk-a));
        background-size: 300% 100%;
        transform-origin: left;
        animation: wk-draw 1.2s .35s cubic-bezier(.2,.7,.2,1) both,
                   wk-flow 6s linear infinite;
        z-index: 0;
    }
}
@keyframes wk-draw { from { transform: scaleX(0); } to { transform: scaleX(1); } }
@keyframes wk-flow { to { background-position: 300% 0; } }

.wk-how .grid .card {
    position: relative;
    z-index: 1;
    padding: 28px 24px 26px;
    border-radius: 20px;
    background: rgba(255,255,255,.9);
    -webkit-backdrop-filter: blur(8px);
    backdrop-filter: blur(8px);
    border: 1px solid rgba(148,163,184,.28);
    box-shadow: 0 6px 20px -10px rgba(15,23,42,.18);
    overflow: hidden;
    transition: transform .35s cubic-bezier(.2,.7,.2,1),
                box-shadow .35s ease,
                border-color .35s ease;
    animation: wk-fade-up .7s cubic-bezier(.2,.7,.2,1) both;
}
.wk-how .grid .card:nth-child(1) { animation-delay: .20s; }
.wk-how .grid .card:nth-child(2) { animation-delay: .32s; }
.wk-how .grid .card:nth-child(3) { animation-delay: .44s; }
.wk-how .grid .card:nth-child(4) { animation-delay: .56s; }

.wk-how .grid .card::before {
    content: "";
    position: absolute;
    inset: 0 0 auto 0;
    height: 4px;
    background: linear-gradient(90deg, var(--wk-a), var(--wk-b));
    transform: scaleX(0);
    transform-origin: left;
    transition: transform .45s ease;
}
.wk-how .grid .card::after {
    content: "";
    position: absolute;
    inset: 0;
    background: radial-gradient(260px circle at var(--mx, 50%) var(--my, 0%), rgba(99,102,241,.14), transparent 60%);
    opacity: 0;
    transition: opacity .3s ease;
    pointer-events: none;
}
.wk-how .grid .card:hover,
.wk-how .grid .card:focus-within {
    transform: translateY(-8px);
    border-color: rgba(99,102,241,.45);
    box-shadow: 0 22px 40px -16px rgba(79,70,229,.35);
}
.wk-how .grid .card:hover::before,
.wk-how .grid .card:focus-within::before { transform: scaleX(1); }
.wk-how .grid .card:hover::after { opacity: 1; }

.wk-how .grid .card .material-icons {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 60px;
    height: 60px;
    margin-bottom: 16px;
    border-radius: 50%;
    font-size: 30px;
    color: #fff;
    background: linear-gradient(135deg, var(--wk-a), var(--wk-b));
    box-shadow: 0 10px 20px -8px rgba(79,70,229,.6), 0 0 0 6px rgba(255,255,255,.9);
    transition: transform .4s cubic-bezier(.3,1.6,.5,1);
}
.wk-how .grid .card:nth-child(even) .material-icons {
    background: linear-gradient(135deg, var(--wk-c), var(--wk-a));
}
.wk-how .grid .card:hover .material-icons { transform: scale(1.14) rotate(-8deg); }
.wk-how .grid .card h2 {
    margin: 0 0 8px;
    font-size: 1.15rem;
    color: var(--wk-ink);
}
.wk-how .grid .card p {
    margin: 0;
    line-height: 1.6;
    color: var(--wk-muted);
    font-size: .98rem;
}

/* worker callout */
.wk-how .wk-worker {
    margin: 36px 0 0;
    padding: 22px 26px;
    border-radius: 20px;
    color: #fff;
    line-height: 1.65;
    background: linear-gradient(120deg, var(--wk-a), var(--wk-c), var(--wk-b));
    background-size: 200% 200%;
    box-shadow: 0 18px 36px -16px rgba(79,70,229,.6);
    border: 0;
    animation: wk-fade-up .8s .7s cubic-bezier(.2,.7,.2,1) both,
               wk-grad 10s ease infinite;
}
.wk-how .wk-worker b { font-size: 1.1rem; }
@keyframes wk-grad {
    0%, 100% { background-position: 0% 50%; }
    50%      { background-position: 100% 50%; }
}

/* CTA buttons */
.wk-how .wk-cta {
    margin: 32px 0 0;
    display: flex;
    flex-wrap: wrap;
    gap: 14px;
    align-items: center;
    justify-content: center;
    animation: wk-fade-up .8s .85s cubic-bezier(.2,.7,.2,1) both;
}
.wk-how .wk-cta .btn {
    position: relative;
    overflow: hidden;
    display: inline-block;
    padding: 14px 30px;
    border-radius: 999px;
    font-weight: 700;
    text-decoration: none;
    color: #fff;
    background: linear-gradient(135deg, var(--wk-a), var(--wk-c));
    box-shadow: 0 14px 28px -10px rgba(79,70,229,.7);
    transition: transform .3s ease, box-shadow .3s ease;
}
.wk-how .wk-cta .btn:first-child { animation: wk-pulse 2.8s ease-in-out infinite; }
.wk-how .wk-cta .btn + .btn {
    background: linear-gradient(135deg, var(--wk-b), var(--wk-a));
}
.wk-how .wk-cta .btn::after {
    content: "";
    position: absolute;
    top: 0; left: -80%;
    width: 50%; height: 100%;
    background: linear-gradient(100deg, transparent, rgba(255,255,255,.55), transparent);
    transform: skewX(-20deg);
    transition: left .7s ease;
}
.wk-how .wk-cta .btn:hover {
    transform: translateY(-3px) scale(1.04);
    box-shadow: 0 20px 34px -10px rgba(79,70,229,.8);
}
.wk-how .wk-cta .btn:hover::after { left: 130%; }
@keyframes wk-pulse {
    0%, 100% { box-shadow: 0 14px 28px -10px rgba(79,70,229,.7), 0 0 0 0 rgba(99,102,241,.35); }
    50%      { box-shadow: 0 14px 28px -10px rgba(79,70,229,.7), 0 0 0 12px rgba(99,102,241,0); }
}

.wk-how a:focus-visible {
    outline: 3px solid rgba(99,102,241,.55);
    outline-offset: 3px;
    border-radius: 10px;
}
@media (max-width: 560px) {
    .wk-how { margin: 12px; border-radius: 20px; }
}
@media (prefers-reduced-motion: reduce) {
    .wk-how *, .wk-how::before, .wk-how::after, .wk-how .grid::before {
        animation: none !important;
        transition: none !important;
    }
}
</style>

<section class="wk-how" aria-labelledby="how-title">
    <h1 id="how-title">A clearer way to get local work done</h1>
    <p>Post what you need, compare responses, and keep the job details in one place.</p>

    <div class="grid">
        <article class="card">
            <span class="material-icons mi-lg" aria-hidden="true">post_add</span>
            <h2>1. Describe the job</h2>
            <p>Sign in as a customer and share the service, location, preferred time, and details workers need to respond.</p>
        </article>
        <article class="card">
            <span class="material-icons mi-lg" aria-hidden="true">forum</span>
            <h2>2. Review worker offers</h2>
            <p>Workers can respond to your request. Review their profile, experience, rating, and offer before deciding.</p>
        </article>
        <article class="card">
            <span class="material-icons mi-lg" aria-hidden="true">handshake</span>
            <h2>3. Choose the right fit</h2>
            <p>Compare the available offers and accept the one that best matches your needs and schedule.</p>
        </article>
        <article class="card">
            <span class="material-icons mi-lg" aria-hidden="true">task_alt</span>
            <h2>4. Follow the job through</h2>
            <p>Use the job page to follow updates, communicate, and review payment and completion details.</p>
        </article>
    </div>

    <p class="card wk-worker"><b>Are you a worker?</b> Create a worker account, complete your profile, and browse open jobs to find requests that match your skills.</p>
    <p class="wk-cta"><a class="btn" href="post_job.php">Post a Job</a> <a class="btn" href="register.php">Join as Worker</a></p>
</section>

<script>
/* Cosmetic only: cursor-following glow on step cards. */
(function () {
    document.querySelectorAll('.wk-how .grid .card').forEach(function (card) {
        card.addEventListener('pointermove', function (e) {
            var r = card.getBoundingClientRect();
            card.style.setProperty('--mx', (e.clientX - r.left) + 'px');
            card.style.setProperty('--my', (e.clientY - r.top) + 'px');
        });
    });
})();
</script>
<?php foot(); ?>
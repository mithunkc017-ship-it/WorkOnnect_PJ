<?php
require "config.php";
ob_start();
head("Why Workonnect");
$pageHead = ob_get_clean();
$pageHead = preg_replace(
    '#<nav>.*?</nav>#s',
    '<nav aria-label="Page header"><a class="logo" href="index.php">Work<span>onnect</span></a></nav>',
    $pageHead,
    1,
    $navReplaced,
);
if ($navReplaced !== 1) {
    throw new RuntimeException("Could not simplify the Why Workonnect page header.");
}
echo $pageHead;
?>
<style>
/* ===== Why Workonnect – scoped visual enhancements ===== */
.wk-why {
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
        radial-gradient(1200px 400px at 10% -10%, rgba(79,70,229,.10), transparent 60%),
        radial-gradient(900px 400px at 100% 0%, rgba(6,182,212,.12), transparent 60%),
        #ffffff;
    box-shadow: 0 20px 60px -20px rgba(30,41,59,.25);
}

/* floating blobs */
.wk-why::before,
.wk-why::after {
    content: "";
    position: absolute;
    z-index: -1;
    border-radius: 50%;
    filter: blur(60px);
    opacity: .45;
    pointer-events: none;
}
.wk-why::before {
    width: 320px; height: 320px;
    top: -90px; left: -80px;
    background: linear-gradient(135deg, var(--wk-a), var(--wk-c));
    animation: wk-float 14s ease-in-out infinite;
}
.wk-why::after {
    width: 360px; height: 360px;
    bottom: -120px; right: -100px;
    background: linear-gradient(135deg, var(--wk-b), var(--wk-a));
    animation: wk-float 18s ease-in-out infinite reverse;
}
@keyframes wk-float {
    0%, 100% { transform: translate(0, 0) scale(1); }
    50%      { transform: translate(40px, 30px) scale(1.12); }
}

/* hero text */
.wk-why > h1 {
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
@keyframes wk-shine {
    to { background-position: 250% 0; }
}
.wk-why > h1 + p {
    max-width: 680px;
    margin: 0 auto 38px;
    text-align: center;
    font-size: clamp(1rem, 1.6vw, 1.15rem);
    line-height: 1.65;
    color: var(--wk-muted);
    animation: wk-fade-up .8s .12s cubic-bezier(.2,.7,.2,1) both;
}
@keyframes wk-fade-up {
    from { opacity: 0; transform: translateY(22px); }
    to   { opacity: 1; transform: translateY(0); }
}

/* card grid */
.wk-why .grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 22px;
}
.wk-why .card {
    position: relative;
    padding: 28px 24px 26px;
    border-radius: 20px;
    background: rgba(255,255,255,.82);
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
.wk-why .card:nth-child(1) { animation-delay: .20s; }
.wk-why .card:nth-child(2) { animation-delay: .30s; }
.wk-why .card:nth-child(3) { animation-delay: .40s; }
.wk-why .card:nth-child(4) { animation-delay: .50s; }
.wk-why .card:nth-child(5) { animation-delay: .60s; }

/* animated top accent line */
.wk-why .card::before {
    content: "";
    position: absolute;
    inset: 0 0 auto 0;
    height: 4px;
    background: linear-gradient(90deg, var(--wk-a), var(--wk-b));
    transform: scaleX(0);
    transform-origin: left;
    transition: transform .45s ease;
}
/* pointer-following glow (set by script, harmless without it) */
.wk-why .card::after {
    content: "";
    position: absolute;
    inset: 0;
    background: radial-gradient(260px circle at var(--mx, 50%) var(--my, 0%), rgba(99,102,241,.14), transparent 60%);
    opacity: 0;
    transition: opacity .3s ease;
    pointer-events: none;
}
.wk-why .card:hover,
.wk-why .card:focus-within {
    transform: translateY(-8px);
    border-color: rgba(99,102,241,.45);
    box-shadow: 0 22px 40px -16px rgba(79,70,229,.35);
}
.wk-why .card:hover::before,
.wk-why .card:focus-within::before { transform: scaleX(1); }
.wk-why .card:hover::after { opacity: 1; }

/* icon badge */
.wk-why .card .material-icons {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 60px;
    height: 60px;
    margin-bottom: 16px;
    border-radius: 18px;
    font-size: 30px;
    color: #fff;
    background: linear-gradient(135deg, var(--wk-a), var(--wk-b));
    box-shadow: 0 10px 20px -8px rgba(79,70,229,.6);
    transition: transform .4s cubic-bezier(.3,1.6,.5,1);
}
.wk-why .card:nth-child(even) .material-icons {
    background: linear-gradient(135deg, var(--wk-c), var(--wk-a));
}
.wk-why .card:hover .material-icons {
    transform: rotate(-8deg) scale(1.12);
}
.wk-why .card h2 {
    margin: 0 0 8px;
    font-size: 1.15rem;
    color: var(--wk-ink);
}
.wk-why .card p {
    margin: 0;
    line-height: 1.6;
    color: var(--wk-muted);
    font-size: .98rem;
}

/* call-to-action row */
.wk-why .wk-cta {
    margin: 42px 0 0;
    display: flex;
    flex-wrap: wrap;
    gap: 14px;
    align-items: center;
    justify-content: center;
    animation: wk-fade-up .8s .75s cubic-bezier(.2,.7,.2,1) both;
}
.wk-why .wk-cta .btn {
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
    animation: wk-pulse 2.8s ease-in-out infinite;
}
.wk-why .wk-cta .btn::after {
    content: "";
    position: absolute;
    top: 0; left: -80%;
    width: 50%; height: 100%;
    background: linear-gradient(100deg, transparent, rgba(255,255,255,.55), transparent);
    transform: skewX(-20deg);
    transition: left .7s ease;
}
.wk-why .wk-cta .btn:hover {
    transform: translateY(-3px) scale(1.04);
    box-shadow: 0 20px 34px -10px rgba(79,70,229,.8);
}
.wk-why .wk-cta .btn:hover::after { left: 130%; }
@keyframes wk-pulse {
    0%, 100% { box-shadow: 0 14px 28px -10px rgba(79,70,229,.7), 0 0 0 0 rgba(99,102,241,.35); }
    50%      { box-shadow: 0 14px 28px -10px rgba(79,70,229,.7), 0 0 0 12px rgba(99,102,241,0); }
}
.wk-why .wk-cta a:not(.btn) {
    position: relative;
    padding: 6px 4px;
    font-weight: 600;
    color: var(--wk-a);
    text-decoration: none;
}
.wk-why .wk-cta a:not(.btn)::after {
    content: "";
    position: absolute;
    left: 0; right: 0; bottom: 0;
    height: 2px;
    background: currentColor;
    transform: scaleX(0);
    transform-origin: right;
    transition: transform .35s ease;
}
.wk-why .wk-cta a:not(.btn):hover::after {
    transform: scaleX(1);
    transform-origin: left;
}

/* accessibility */
.wk-why a:focus-visible {
    outline: 3px solid rgba(99,102,241,.55);
    outline-offset: 3px;
    border-radius: 10px;
}
@media (max-width: 560px) {
    .wk-why { margin: 12px; border-radius: 20px; }
}
@media (prefers-reduced-motion: reduce) {
    .wk-why *, .wk-why::before, .wk-why::after {
        animation: none !important;
        transition: none !important;
    }
}
</style>

<section class="wk-why" aria-labelledby="why-title">
    <h1 id="why-title">Local services, with the details in one place</h1>
    <p>Workonnect helps customers find local professionals and gives workers a place to share their services and respond to jobs.</p>

    <div class="grid">
        <article class="card">
            <span class="material-icons mi-lg" aria-hidden="true">manage_search</span>
            <h2>Find a relevant service</h2>
            <p>Browse skills and worker profiles, then narrow your search by service or area.</p>
        </article>
        <article class="card">
            <span class="material-icons mi-lg" aria-hidden="true">compare_arrows</span>
            <h2>Compare before choosing</h2>
            <p>Review worker profiles and the offers you receive before accepting a job offer.</p>
        </article>
        <article class="card">
            <span class="material-icons mi-lg" aria-hidden="true">verified_user</span>
            <h2>See verification status</h2>
            <p>Profiles display a verification badge when the worker's submitted ID has been approved.</p>
        </article>
        <article class="card">
            <span class="material-icons mi-lg" aria-hidden="true">timeline</span>
            <h2>Keep job updates together</h2>
            <p>Use the job page to follow progress, communicate, and review next steps.</p>
        </article>
        <article class="card">
            <span class="material-icons mi-lg" aria-hidden="true">support_agent</span>
            <h2>Know where to get help</h2>
            <p>Visit Help for common questions or contact the team if you need assistance.</p>
        </article>
    </div>

    <p class="wk-cta"><a class="btn" href="workers.php">Find Workers</a> <a href="help.php">Visit Help</a></p>
</section>

<script>
/* Cosmetic only: cursor-following glow on cards. No effect on page logic. */
(function () {
    document.querySelectorAll('.wk-why .card').forEach(function (card) {
        card.addEventListener('pointermove', function (e) {
            var r = card.getBoundingClientRect();
            card.style.setProperty('--mx', (e.clientX - r.left) + 'px');
            card.style.setProperty('--my', (e.clientY - r.top) + 'px');
        });
    });
})();
</script>
<?php foot(); ?>
<?php
require "config.php";
ob_start();
head("Quick answers");
$pageHead = ob_get_clean();
$pageHead = preg_replace(
    '#<nav>.*?</nav>#s',
    '<nav aria-label="Page header"><a class="logo" href="index.php">Work<span>onnect</span></a></nav>',
    $pageHead,
    1,
    $navReplaced,
);
if ($navReplaced !== 1) {
    throw new RuntimeException("Could not simplify the Quick Answers page header.");
}
echo $pageHead;
?>
<style>
/* ===== Quick answers – clean, animated, brand-matched ===== */
.wk-faq {
    /* Accent is auto-matched to your site's logo colour by the script below.
       This value is only the fallback. */
    --wk-a: #0f766e;
    --wk-soft: color-mix(in srgb, var(--wk-a) 10%, #ffffff);
    --wk-mid:  color-mix(in srgb, var(--wk-a) 35%, #ffffff);
    --wk-b:    color-mix(in srgb, var(--wk-a) 65%, #ffffff);
    --wk-ink: #0f172a;
    --wk-muted: #5b6678;
    --wk-line: #e5e9f0;
    position: relative;
    isolation: isolate;
    max-width: 820px;
    margin: 28px auto;
    padding: clamp(22px, 5vw, 56px) clamp(16px, 4vw, 40px);
    counter-reset: wk-q;
}

/* soft animated background glow */
.wk-faq::before {
    content: "";
    position: absolute;
    z-index: -1;
    inset: -40px -20px auto -20px;
    height: 320px;
    background: radial-gradient(500px 220px at 50% 0%, var(--wk-soft), transparent 70%);
    animation: wk-breathe 7s ease-in-out infinite;
    pointer-events: none;
}
@keyframes wk-breathe {
    0%, 100% { opacity: .75; transform: scale(1); }
    50%      { opacity: 1;   transform: scale(1.06); }
}
@keyframes wk-fade-up {
    from { opacity: 0; transform: translateY(18px); }
    to   { opacity: 1; transform: translateY(0); }
}
@keyframes wk-draw { from { transform: scaleX(0); } to { transform: scaleX(1); } }
@keyframes wk-open {
    from { opacity: 0; transform: translateY(-8px); }
    to   { opacity: 1; transform: translateY(0); }
}

/* heading */
.wk-faq > h1 {
    position: relative;
    margin: 0 0 12px;
    text-align: center;
    font-size: clamp(2rem, 5vw, 3rem);
    letter-spacing: -.025em;
    line-height: 1.1;
    color: var(--wk-ink);
    animation: wk-fade-up .7s cubic-bezier(.2,.7,.2,1) both;
}
.wk-faq > h1::after {
    content: "";
    display: block;
    width: 72px;
    height: 5px;
    margin: 14px auto 0;
    border-radius: 5px;
    background: linear-gradient(90deg, var(--wk-a), var(--wk-b));
    transform-origin: center;
    animation: wk-draw .8s .35s cubic-bezier(.2,.7,.2,1) both;
}
.wk-faq > h1 + p {
    max-width: 560px;
    margin: 0 auto 34px;
    text-align: center;
    line-height: 1.65;
    font-size: 1.05rem;
    color: var(--wk-muted);
    animation: wk-fade-up .7s .12s cubic-bezier(.2,.7,.2,1) both;
}

/* accordion items */
.wk-faq details.card {
    counter-increment: wk-q;
    position: relative;
    margin: 0 0 14px;
    padding: 0;
    border-radius: 16px;
    background: #fff;
    border: 1px solid var(--wk-line);
    box-shadow: 0 1px 2px rgba(15,23,42,.04);
    overflow: hidden;
    transition: box-shadow .3s ease, border-color .3s ease, transform .3s ease;
    animation: wk-fade-up .6s cubic-bezier(.2,.7,.2,1) both;
}
.wk-faq details.card:nth-of-type(1) { animation-delay: .20s; }
.wk-faq details.card:nth-of-type(2) { animation-delay: .28s; }
.wk-faq details.card:nth-of-type(3) { animation-delay: .36s; }
.wk-faq details.card:nth-of-type(4) { animation-delay: .44s; }
.wk-faq details.card:nth-of-type(5) { animation-delay: .52s; }

/* left accent bar that grows when open */
.wk-faq details.card::before {
    content: "";
    position: absolute;
    left: 0; top: 0; bottom: 0;
    width: 4px;
    background: linear-gradient(180deg, var(--wk-a), var(--wk-b));
    transform: scaleY(0);
    transform-origin: top;
    transition: transform .4s ease;
}
.wk-faq details.card:hover {
    transform: translateY(-2px);
    border-color: var(--wk-mid);
    box-shadow: 0 12px 28px -14px color-mix(in srgb, var(--wk-a) 45%, transparent);
}
.wk-faq details.card[open] {
    border-color: var(--wk-mid);
    box-shadow: 0 16px 34px -16px color-mix(in srgb, var(--wk-a) 50%, transparent);
}
.wk-faq details.card[open]::before { transform: scaleY(1); }

/* question row */
.wk-faq details.card > summary {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 18px 20px;
    cursor: pointer;
    list-style: none;
    font-weight: 700;
    font-size: 1.05rem;
    color: var(--wk-ink);
    user-select: none;
    transition: color .25s ease, background .25s ease;
}
.wk-faq details.card > summary::-webkit-details-marker { display: none; }
.wk-faq details.card > summary:hover { background: var(--wk-soft); }
.wk-faq details.card[open] > summary { color: var(--wk-a); }

/* numbered badge */
.wk-faq details.card > summary::before {
    content: counter(wk-q);
    flex: 0 0 auto;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    border-radius: 50%;
    font-size: .95rem;
    font-weight: 800;
    color: var(--wk-a);
    background: var(--wk-soft);
    transition: transform .4s cubic-bezier(.3,1.6,.5,1), background .3s ease, color .3s ease;
}
.wk-faq details.card:hover > summary::before { transform: scale(1.1) rotate(-6deg); }
.wk-faq details.card[open] > summary::before {
    color: #fff;
    background: linear-gradient(135deg, var(--wk-a), var(--wk-b));
}

/* plus icon that rotates into a cross */
.wk-faq details.card > summary::after {
    content: "+";
    margin-left: auto;
    flex: 0 0 auto;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    font-size: 1.4rem;
    font-weight: 400;
    line-height: 1;
    color: var(--wk-a);
    border: 1.5px solid var(--wk-mid);
    transition: transform .4s cubic-bezier(.3,1.4,.5,1), background .3s ease, color .3s ease;
}
.wk-faq details.card[open] > summary::after {
    transform: rotate(135deg);
    color: #fff;
    background: var(--wk-a);
    border-color: var(--wk-a);
}

/* answer */
.wk-faq details.card > p {
    margin: 0;
    padding: 2px 22px 22px 68px;
    line-height: 1.7;
    color: var(--wk-muted);
}
.wk-faq details.card[open] > p {
    animation: wk-open .45s cubic-bezier(.2,.7,.2,1) both;
}

/* call to action */
.wk-faq > p:last-child {
    margin: 34px 0 0;
    text-align: center;
    animation: wk-fade-up .7s .7s cubic-bezier(.2,.7,.2,1) both;
}
.wk-faq .btn {
    position: relative;
    overflow: hidden;
    display: inline-block;
    padding: 14px 30px;
    border-radius: 999px;
    font-weight: 700;
    text-decoration: none;
    color: #fff;
    background: linear-gradient(135deg, var(--wk-a), color-mix(in srgb, var(--wk-a) 70%, #000));
    box-shadow: 0 14px 28px -12px color-mix(in srgb, var(--wk-a) 80%, transparent);
    transition: transform .3s ease, box-shadow .3s ease;
}
.wk-faq .btn::after {
    content: "";
    position: absolute;
    top: 0; left: -80%;
    width: 50%; height: 100%;
    background: linear-gradient(100deg, transparent, rgba(255,255,255,.5), transparent);
    transform: skewX(-20deg);
    transition: left .7s ease;
}
.wk-faq .btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 20px 34px -12px color-mix(in srgb, var(--wk-a) 85%, transparent);
}
.wk-faq .btn:hover::after { left: 130%; }

.wk-faq summary:focus-visible,
.wk-faq .btn:focus-visible {
    outline: 3px solid var(--wk-mid);
    outline-offset: 2px;
}
@media (max-width: 560px) {
    .wk-faq details.card > summary { padding: 15px 14px; font-size: 1rem; gap: 10px; }
    .wk-faq details.card > p { padding: 0 16px 18px 16px; }
}
@media (prefers-reduced-motion: reduce) {
    .wk-faq *, .wk-faq::before {
        animation: none !important;
        transition: none !important;
    }
}
</style>

<section class="wk-faq" aria-labelledby="answers-title">
    <h1 id="answers-title">Quick answers</h1>
    <p>Helpful details about finding a worker, arranging a job, and getting support.</p>

    <details class="card">
        <summary>How do I post a job?</summary>
        <p>Register or sign in as a customer, choose Post Job, and enter the service and job details. Workers can then respond with offers.</p>
    </details>
    <details class="card">
        <summary>Can I choose a specific worker?</summary>
        <p>Yes. Browse worker profiles and use the Hire option on a profile to send a request to that worker.</p>
    </details>
    <details class="card">
        <summary>How do I become a verified worker?</summary>
        <p>Create a worker account, open Get Verified, and submit your ID for admin review. The verified badge appears after approval.</p>
    </details>
    <details class="card">
        <summary>How do payments work?</summary>
        <p>Review the payment options and amount shown on the job's payment page. Payment methods can vary by job and configuration.</p>
    </details>
    <details class="card">
        <summary>How can I report a problem with a job?</summary>
        <p>Open the job page and use Report a problem, or contact the team from the Help page.</p>
    </details>

    <p><a class="btn" href="help.php">More help and contact options</a></p>
</section>

<script>
/* Cosmetic only: pick up the site's own brand colour from the logo so this
   page matches the rest of the site. Falls back to the CSS default. */
(function () {
    try {
        var logo = document.querySelector('.logo span');
        var faq = document.querySelector('.wk-faq');
        if (!logo || !faq) return;
        var c = getComputedStyle(logo).color;
        if (c && c !== 'rgba(0, 0, 0, 0)' && c !== 'transparent') {
            faq.style.setProperty('--wk-a', c);
        }
    } catch (e) {}
})();
</script>
<?php foot(); ?>
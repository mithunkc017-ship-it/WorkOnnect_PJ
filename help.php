<?php // Help / FAQ page and Contact Us form (the admin also sees the received messages here)
require "config.php";
$u = me();
$sent = false;
if ($_SERVER["REQUEST_METHOD"] == "POST" && trim($_POST["msg"] ?? "") !== "") {
    $pdo->prepare("INSERT INTO contacts(name,email,msg)VALUES(?,?,?)")->execute(
        [
            substr($_POST["name"], 0, 80),
            substr($_POST["email"], 0, 100),
            substr($_POST["msg"], 0, 500),
        ],
    );
    foreach ($pdo->query("SELECT id FROM users WHERE role='admin'") as $a) {
        notify(
            $a["id"],
            "New contact message from " . $_POST["name"],
            "help.php",
        );
    }
    $sent = true;
}
ob_start();
head("Help");
$pageHead = ob_get_clean();
$pageHead = preg_replace(
    '#<nav>.*?</nav>#s',
    '<nav aria-label="Page header"><a class="logo" href="index.php">Work<span>onnect</span></a></nav>',
    $pageHead,
    1,
    $navReplaced,
);
if ($navReplaced !== 1) {
    throw new RuntimeException("Could not simplify the Help page header.");
}
echo $pageHead;
?>
<style>
/* ===== Help – clean, animated, brand-matched ===== */
.wk-help {
    /* Auto-matched to the site's logo colour by the script below (fallback here). */
    --wk-a: #0f766e;
    --wk-soft: color-mix(in srgb, var(--wk-a) 10%, #ffffff);
    --wk-mid:  color-mix(in srgb, var(--wk-a) 35%, #ffffff);
    --wk-b:    color-mix(in srgb, var(--wk-a) 65%, #ffffff);
    --wk-ink: #0f172a;
    --wk-muted: #5b6678;
    --wk-line: #e5e9f0;
    position: relative;
    isolation: isolate;
    max-width: 860px;
    margin: 24px auto;
    padding: clamp(18px, 4vw, 40px) clamp(14px, 3.5vw, 36px);
    counter-reset: wk-q;
}
.wk-help::before {
    content: "";
    position: absolute;
    z-index: -1;
    inset: -30px -20px auto -20px;
    height: 300px;
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
@keyframes wk-pop {
    0%   { opacity: 0; transform: scale(.9) translateY(-6px); }
    60%  { transform: scale(1.03); }
    100% { opacity: 1; transform: scale(1) translateY(0); }
}
@keyframes wk-bob {
    0%, 100% { transform: translateY(0); }
    50%      { transform: translateY(-3px); }
}

/* section headings */
.wk-help > h2 {
    display: flex;
    align-items: center;
    gap: 12px;
    margin: 0 0 18px;
    font-size: clamp(1.5rem, 3.6vw, 2.1rem);
    letter-spacing: -.02em;
    color: var(--wk-ink);
    animation: wk-fade-up .7s cubic-bezier(.2,.7,.2,1) both;
}
.wk-help > h2:not(:first-child) { margin-top: 44px; }
.wk-help > h2 .material-icons {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 46px;
    height: 46px;
    border-radius: 14px;
    font-size: 26px;
    color: #fff;
    background: linear-gradient(135deg, var(--wk-a), var(--wk-b));
    box-shadow: 0 10px 20px -8px color-mix(in srgb, var(--wk-a) 70%, transparent);
    animation: wk-bob 3s ease-in-out infinite;
}
.wk-help > h2::after {
    content: "";
    flex: 1 1 auto;
    height: 3px;
    border-radius: 3px;
    background: linear-gradient(90deg, var(--wk-mid), transparent);
    transform-origin: left;
    animation: wk-draw .9s .3s cubic-bezier(.2,.7,.2,1) both;
}

/* FAQ accordion (answers are plain text inside <details>) */
.wk-help details.card {
    counter-increment: wk-q;
    position: relative;
    margin: 0 0 14px;
    padding: 0 22px 20px 22px;
    border-radius: 16px;
    background: #fff;
    border: 1px solid var(--wk-line);
    box-shadow: 0 1px 2px rgba(15,23,42,.04);
    overflow: hidden;
    line-height: 1.7;
    color: var(--wk-muted);
    transition: box-shadow .3s ease, border-color .3s ease, transform .3s ease;
    animation: wk-fade-up .6s cubic-bezier(.2,.7,.2,1) both;
}
.wk-help details.card:not([open]) { padding-bottom: 0; }
.wk-help details.card:nth-of-type(1) { animation-delay: .15s; }
.wk-help details.card:nth-of-type(2) { animation-delay: .22s; }
.wk-help details.card:nth-of-type(3) { animation-delay: .29s; }
.wk-help details.card:nth-of-type(4) { animation-delay: .36s; }
.wk-help details.card:nth-of-type(5) { animation-delay: .43s; }
.wk-help details.card::before {
    content: "";
    position: absolute;
    left: 0; top: 0; bottom: 0;
    width: 4px;
    background: linear-gradient(180deg, var(--wk-a), var(--wk-b));
    transform: scaleY(0);
    transform-origin: top;
    transition: transform .4s ease;
}
.wk-help details.card:hover {
    transform: translateY(-2px);
    border-color: var(--wk-mid);
    box-shadow: 0 12px 28px -14px color-mix(in srgb, var(--wk-a) 45%, transparent);
}
.wk-help details.card[open] {
    border-color: var(--wk-mid);
    box-shadow: 0 16px 34px -16px color-mix(in srgb, var(--wk-a) 50%, transparent);
}
.wk-help details.card[open]::before { transform: scaleY(1); }
.wk-help details.card[open]::details-content {
    animation: wk-open .45s cubic-bezier(.2,.7,.2,1) both;
}

.wk-help details.card > summary {
    display: flex;
    align-items: center;
    gap: 14px;
    margin: 0 -22px;
    padding: 18px 22px;
    cursor: pointer;
    list-style: none;
    font-weight: 700;
    font-size: 1.05rem;
    color: var(--wk-ink);
    user-select: none;
    transition: color .25s ease, background .25s ease;
}
.wk-help details.card > summary::-webkit-details-marker { display: none; }
.wk-help details.card > summary:hover { background: var(--wk-soft); }
.wk-help details.card[open] > summary { color: var(--wk-a); }
.wk-help details.card > summary::before {
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
.wk-help details.card:hover > summary::before { transform: scale(1.1) rotate(-6deg); }
.wk-help details.card[open] > summary::before {
    color: #fff;
    background: linear-gradient(135deg, var(--wk-a), var(--wk-b));
}
.wk-help details.card > summary::after {
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
.wk-help details.card[open] > summary::after {
    transform: rotate(135deg);
    color: #fff;
    background: var(--wk-a);
    border-color: var(--wk-a);
}

/* contact form */
.wk-help form.card {
    display: flex;
    flex-direction: column;
    gap: 14px;
    padding: clamp(18px, 3vw, 28px);
    border-radius: 20px;
    background: #fff;
    border: 1px solid var(--wk-line);
    box-shadow: 0 18px 40px -22px color-mix(in srgb, var(--wk-a) 45%, transparent);
    animation: wk-fade-up .7s .2s cubic-bezier(.2,.7,.2,1) both;
}
.wk-help form.card input,
.wk-help form.card textarea {
    width: 100%;
    box-sizing: border-box;
    padding: 13px 15px;
    border-radius: 12px;
    border: 1.5px solid var(--wk-line);
    background: #fafbfd;
    font: inherit;
    color: var(--wk-ink);
    transition: border-color .25s ease, box-shadow .25s ease, background .25s ease, transform .25s ease;
}
.wk-help form.card textarea { resize: vertical; min-height: 110px; }
.wk-help form.card input:focus,
.wk-help form.card textarea:focus {
    outline: none;
    background: #fff;
    border-color: var(--wk-a);
    box-shadow: 0 0 0 4px color-mix(in srgb, var(--wk-a) 18%, transparent);
    transform: translateY(-1px);
}
.wk-help form.card button {
    position: relative;
    overflow: hidden;
    align-self: flex-start;
    padding: 13px 34px;
    border: 0;
    border-radius: 999px;
    font: inherit;
    font-weight: 700;
    color: #fff;
    cursor: pointer;
    background: linear-gradient(135deg, var(--wk-a), color-mix(in srgb, var(--wk-a) 70%, #000));
    box-shadow: 0 14px 28px -12px color-mix(in srgb, var(--wk-a) 80%, transparent);
    transition: transform .3s ease, box-shadow .3s ease;
}
.wk-help form.card button::after {
    content: "";
    position: absolute;
    top: 0; left: -80%;
    width: 50%; height: 100%;
    background: linear-gradient(100deg, transparent, rgba(255,255,255,.5), transparent);
    transform: skewX(-20deg);
    transition: left .7s ease;
}
.wk-help form.card button:hover {
    transform: translateY(-3px);
    box-shadow: 0 20px 34px -12px color-mix(in srgb, var(--wk-a) 85%, transparent);
}
.wk-help form.card button:hover::after { left: 130%; }
.wk-help form.card button:active { transform: translateY(0) scale(.97); }

/* success message */
.wk-help .ok {
    margin: 0;
    padding: 14px 18px;
    border-radius: 12px;
    font-weight: 600;
    color: #166534;
    background: #ecfdf3;
    border: 1px solid #bbf7d0;
    animation: wk-pop .6s cubic-bezier(.2,.7,.2,1) both;
}

/* admin messages table */
.wk-help .wk-table-wrap {
    overflow-x: auto;
    border-radius: 16px;
    border: 1px solid var(--wk-line);
    background: #fff;
    box-shadow: 0 14px 34px -22px color-mix(in srgb, var(--wk-a) 45%, transparent);
    animation: wk-fade-up .7s .15s cubic-bezier(.2,.7,.2,1) both;
}
.wk-help .wk-table-wrap table {
    width: 100%;
    border-collapse: collapse;
    min-width: 560px;
}
.wk-help .wk-table-wrap th {
    padding: 14px 16px;
    text-align: left;
    font-size: .85rem;
    letter-spacing: .04em;
    text-transform: uppercase;
    color: #fff;
    background: linear-gradient(135deg, var(--wk-a), var(--wk-b));
}
.wk-help .wk-table-wrap td {
    padding: 14px 16px;
    vertical-align: top;
    color: var(--wk-muted);
    border-top: 1px solid var(--wk-line);
    line-height: 1.55;
}
.wk-help .wk-table-wrap tr:nth-child(even) td { background: #fafbfd; }
.wk-help .wk-table-wrap tr:not(:first-child) {
    animation: wk-fade-up .5s cubic-bezier(.2,.7,.2,1) both;
    animation-delay: calc(.25s + var(--r, 0) * .04s);
}
.wk-help .wk-table-wrap tr:hover td { background: var(--wk-soft); }

.wk-help summary:focus-visible,
.wk-help button:focus-visible {
    outline: 3px solid var(--wk-mid);
    outline-offset: 2px;
}
@media (max-width: 560px) {
    .wk-help details.card { padding: 0 16px 16px 16px; }
    .wk-help details.card > summary { margin: 0 -16px; padding: 15px 16px; font-size: 1rem; gap: 10px; }
    .wk-help form.card button { align-self: stretch; }
}
@media (prefers-reduced-motion: reduce) {
    .wk-help *, .wk-help *::before, .wk-help *::after, .wk-help::before {
        animation: none !important;
        transition: none !important;
    }
}
</style>

<div class="wk-help">
<h2><span class="material-icons mi">help</span> Help &amp; FAQ</h2>
<details class="card"><summary>How do I post a job?</summary>Register as a customer, click Post Job, fill the details and submit. Workers will send you offers.</details>
<details class="card"><summary>Can I hire one particular worker?</summary>Yes. Open the worker's profile and click Hire this worker. The worker can accept or decline.</details>
<details class="card"><summary>When do I pay?</summary>After the worker marks the job completed and you confirm it. Use a coupon code on the payment page for a discount.</details>
<details class="card"><summary>How do workers get the Verified badge?</summary>Workers upload an ID proof under Get Verified. The admin reviews it.</details>
<details class="card"><summary>Something went wrong with a job. What now?</summary>Open the job and click Report a problem. The admin will look into it.</details>
<h2><span class="material-icons mi">mail</span> Contact Us</h2>
<form class="card" method="post"><?php if ($sent) {
    echo '<p class="ok">Thanks! Your message was sent to the admin.</p>';
} ?>
<input name="name" placeholder="Your name" value="<?= e(
    $u["name"] ?? "",
) ?>" required><input type="email" name="email" placeholder="Your email" value="<?= e(
    $u["email"] ?? "",
) ?>" required>
<textarea name="msg" rows="3" maxlength="500" placeholder="Your message" required></textarea><button>Send</button></form>
<?php
if ($u && $u["role"] == "admin") {
    echo '<h2><span class="material-icons mi">inbox</span> Messages</h2><div class="wk-table-wrap"><table><tr><th>From</th><th>Email</th><th>Message</th><th>Date</th></tr>';
    $wkRow = 0;
    foreach (
        $pdo->query("SELECT * FROM contacts ORDER BY id DESC LIMIT 50")
        as $x
    ) {
        echo '<tr style="--r:' .
            min($wkRow++, 15) .
            '"><td>' .
            e($x["name"]) .
            "</td><td>" .
            e($x["email"]) .
            "</td><td>" .
            e($x["msg"]) .
            "</td><td>" .
            $x["created"] .
            "</td></tr>";
    }
    echo "</table></div>";
}
?>
</div>

<script>
/* Cosmetic only: pick up the site's brand colour from the logo so this page
   matches the rest of the site. Falls back to the CSS default. */
(function () {
    try {
        var logo = document.querySelector('.logo span');
        var box = document.querySelector('.wk-help');
        if (!logo || !box) return;
        var c = getComputedStyle(logo).color;
        if (c && c !== 'rgba(0, 0, 0, 0)' && c !== 'transparent') {
            box.style.setProperty('--wk-a', c);
        }
    } catch (e) {}
})();
</script>
<?php
foot();
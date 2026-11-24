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
$isAdmin = $u && $u["role"] == "admin";
$msgs = $isAdmin
    ? $pdo->query("SELECT * FROM contacts ORDER BY id DESC LIMIT 50")->fetchAll()
    : [];

$cats = [
    "account" => ["Account & Login", "manage_accounts"],
    "jobs" => ["Jobs", "work"],
    "payments" => ["Payments", "payments"],
    "workers" => ["For Workers", "engineering"],
    "problems" => ["Problems", "report_problem"],
];

// cat, question, answer, steps (optional), keywords (used only by search)
$faqs = [
    // ---------- Account & Login ----------
    ["account", "How do I create an account?",
     "You can join as a Customer (to post jobs and hire) or as a Worker (to get hired). Choose the role carefully, because it decides which features you see.",
     ["Open the Register page and pick Customer or Worker.", "Enter your name, email, phone and a password.", "Submit the form, then log in with the same email and password."],
     "register signup sign up join new account create start"],
    ["account", "I can’t log in. What should I do?",
     "Most login problems come from a small typing or role mistake. Go through these checks in order.",
     ["Check the email for typos or extra spaces at the end.", "Check that Caps Lock is off and re-type the password slowly.", "Make sure you are using the same email you registered with.", "Try another browser, or clear your cache and cookies.", "If it still fails, reset your password. If that does not work either, message the admin below with your registered email."],
     "login log in sign in signin cant cannot unable wrong password incorrect invalid not working denied access locked error credentials"],
    ["account", "I forgot my password. How do I reset it?",
     "Use the Forgot password option on the login page if your site has one. Follow the steps and use a password you have not used before.",
     ["Go to the login page and click Forgot password.", "Enter the email you registered with.", "Follow the instructions to set a new password.", "No reset option, or no email arrives? Send us a message below with your registered email and phone number, and the admin will help you."],
     "forgot forgotten reset recover lost password pass pwd cant remember new password"],
    ["account", "How do I change my password?",
     "You can change your password while logged in, from your account or profile settings. Choose something at least 8 characters long that mixes letters and numbers.",
     ["Log in to your account.", "Open your profile or account settings.", "Enter your old password, then the new one, and save.", "Log out and log in again to check that it works."],
     "change update password security safe new pwd settings"],
    ["account", "How do I update my name, phone number or email?",
     "Keep your contact details current so customers, workers and the admin can reach you. Workers can edit their details on the My Profile page. Customers can use their account or profile page.",
     null,
     "update edit change name phone mobile number email address profile details contact"],
    ["account", "My account is blocked or shows an access error. What now?",
     "An account can be blocked if it breaks the platform rules or if there was a suspicious activity report. This is reviewed by the admin.",
     ["Note the exact message you see.", "Send us a message below with your registered email and what you were doing.", "The admin will review it and reply."],
     "blocked banned suspended disabled deactivated access denied not allowed account error restricted"],

    // ---------- Jobs ----------
    ["jobs", "How do I post a job?",
     "Register as a customer, click Post Job, fill in the details and submit. Workers will send you offers.",
     ["Click Post Job from your dashboard.", "Choose the service type and describe the work clearly.", "Add your area and the date or time you need it.", "Submit, then compare the offers that come in and pick a worker."],
     "post job create request new work book booking hire need service"],
    ["jobs", "Can I hire one particular worker?",
     "Yes. Open the worker’s profile and click Hire this worker. The worker can accept or decline, and you will get a notification either way.",
     null,
     "hire direct particular specific choose select worker book profile"],
    ["jobs", "I posted a job but I am not getting offers. Why?",
     "Offers depend on workers in your area seeing your job. A clearer job usually gets faster replies.",
     ["Add more detail: what is broken, how big the work is, and when you need it.", "Check the area is correct and not too narrow.", "Look at worker profiles and use Hire this worker on someone available.", "Wait a little, since workers may be busy or offline."],
     "no offers not getting response reply nobody waiting job posted bids quotes slow"],
    ["jobs", "How do I edit or cancel a job I posted?",
     "Open the job from your dashboard. If you see edit or cancel options, use them before a worker is confirmed. After a worker is confirmed, talk to the worker first.",
     ["Open the job from your dashboard.", "Use the edit or cancel option if shown.", "If it is not available, send us a message below with the job details."],
     "cancel edit delete remove change job modify close withdraw stop"],
    ["jobs", "How do I know a worker is available?",
     "Each worker profile shows an availability status (Available or Busy), working days and working hours. Workers keep this updated themselves.",
     null,
     "available availability busy working hours days time schedule free"],

    // ---------- Payments ----------
    ["payments", "When do I pay?",
     "After the worker marks the job completed and you confirm it. Use a coupon code on the payment page for a discount.",
     null,
     "pay payment when how much cost charge price money checkout"],
    ["payments", "My payment failed, or money was deducted but the job shows unpaid.",
     "This is usually a delay between the bank and the platform. Do not pay twice straight away.",
     ["Wait 10 to 15 minutes and refresh the job page.", "Check your bank or UPI app to see if the money was really deducted.", "If it was, send us a message below with the job, the date, the amount and a screenshot of the payment.", "The admin will check and fix it."],
     "payment failed fail money deducted debited charged twice double unpaid refund transaction upi stuck pending not updated"],
    ["payments", "How do I use a coupon code?",
     "Enter the code on the payment page before you pay. The discount shows in the total.",
     ["Open the payment page for your completed job.", "Type the coupon code exactly, with no spaces.", "Apply it and check that the total goes down before paying."],
     "coupon code discount offer promo voucher apply invalid expired"],
    ["payments", "Can I get a refund?",
     "Refunds are decided by the admin after reviewing the job. If the work was not done or was wrong, report it first so there is a record.",
     ["Open the job and click Report a problem.", "Explain what happened, with photos if you have them.", "The admin will review it and tell you the outcome."],
     "refund money back return cancel dispute wrong work not done"],

    // ---------- For Workers ----------
    ["workers", "How do workers get the Verified badge?",
     "Workers upload an ID proof under Get Verified. The admin reviews it, and the badge appears on your profile once it is approved.",
     ["Open Get Verified from your worker area.", "Upload a clear JPG or PNG photo of a valid ID, under 3 MB.", "Wait for the admin to review it. You will get a notification."],
     "verified verification badge id proof aadhaar voter license trusted get verified"],
    ["workers", "My verification was rejected. What do I do?",
     "The rejection note tells you why. The most common reasons are a blurry photo, a cut-off corner, or glare on the ID.",
     ["Read the rejection note on the Get Verified page.", "Take a new photo in good light with all four corners visible.", "Upload it again. You can apply as many times as needed."],
     "rejected rejection verification denied declined failed id unclear blur reapply"],
    ["workers", "How do I set my working days, hours and availability?",
     "Go to My Profile. Choose your skill, switch between Available and Busy, tick your working days, and set your working hours using the clock picker. Click Save Profile to keep your changes.",
     null,
     "working days hours time schedule availability busy available set profile skill area save"],
    ["workers", "How do I add my prices and photos of past work?",
     "Open My Work. Add each service with its price in the price list, and upload clear photos of finished jobs in the portfolio (JPG or PNG, under 2 MB). A good portfolio helps customers trust you.",
     null,
     "price list portfolio photos upload work rates service charge gallery past jobs"],
    ["workers", "My photo or ID upload is not working.",
     "Uploads are rejected when the file is the wrong type or too big.",
     ["Use JPG or PNG only (ID proof: under 3 MB, other photos: under 2 MB).", "Reduce the size by taking a screenshot of the photo, or use a lower camera quality.", "Check your internet connection and try again.", "Still failing? Tell us which page and what error you see."],
     "upload photo image file not working failed size large jpg png error picture"],

    // ---------- Problems ----------
    ["problems", "Something went wrong with a job. What now?",
     "Open the job and click Report a problem. The admin will look into it.",
     ["Open the job.", "Click Report a problem.", "Describe what happened clearly, including dates and what was agreed."],
     "problem issue wrong report complaint job went wrong dispute"],
    ["problems", "The worker did not show up, or the customer is not responding.",
     "Try contacting them once more through the job page. If there is still no reply, report it so the admin can step in.",
     ["Message or call the other person from the job page.", "Wait a reasonable time for a reply.", "Click Report a problem on the job, and mention the time you were expecting them."],
     "no show not come late absent not responding unreachable ignoring customer worker did not arrive"],
    ["problems", "I am not getting notifications.",
     "Notifications appear inside the platform when something happens on your jobs. If you are missing them, the cause is usually your browser or an old page.",
     ["Refresh the page or log out and in again.", "Check that your browser allows the site to run normally.", "Make sure your registered email and phone are correct.", "Still nothing? Message us below."],
     "notification notifications alert bell not receiving missing updates message"],
    ["problems", "The website is slow, blank or shows an error.",
     "These are usually temporary or caused by an old cached page.",
     ["Refresh with Ctrl+F5 (or pull down on your phone).", "Try another browser or turn off ad blockers for this site.", "Check your internet connection.", "If the error stays, send us a message with a screenshot and the page name."],
     "slow loading blank white page error crash bug not opening website down broken 404 500 stuck"],
    ["problems", "How do I report a fake, rude or unsafe user?",
     "Your safety matters. Report any user who asks for money outside the platform, behaves badly, or looks fake.",
     ["Do not share OTPs, bank details or passwords with anyone.", "Report the job, or message us below with the user’s name and what happened.", "The admin will review it and take action."],
     "report fake scam fraud rude abuse unsafe harassment cheat spam user block complaint"],
    ["problems", "Is my ID and personal information safe?",
     "Yes. An ID photo you upload for verification can only be seen by the admin and is used only to verify you. Never share your password or OTP with anyone, including people who say they are from Workonnect.",
     null,
     "safe secure privacy data personal information id private security protect"],
];

$popular = ["can’t login", "forgot password", "change password", "payment failed", "no offers", "verification rejected", "worker didn’t show up", "upload not working"];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Help</title>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
<style>
*{box-sizing:border-box}
:root{--ac:#2563eb;--ac2:#1d4ed8;--ink:#0f172a;--mute:#5b6678;--line:#e5e9f0;--bg:#fff;--soft:#eff5ff;--page:#f1f5f9}
@media (prefers-color-scheme:dark){:root{--ink:#e5e7eb;--mute:#9ca3af;--line:#334155;--bg:#1e293b;--soft:#172554;--page:#0f172a}}
html{scroll-behavior:smooth}
body{margin:0;padding:76px 16px 56px;font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;
  background:var(--page);color:var(--ink);overflow-x:hidden}
.material-icons{font-size:20px;vertical-align:middle}

.blob{position:fixed;border-radius:50%;filter:blur(60px);opacity:.3;z-index:0;animation:float 10s ease-in-out infinite;pointer-events:none}
.b1{width:260px;height:260px;background:#60a5fa;top:-70px;left:-70px}
.b2{width:300px;height:300px;background:#a78bfa;bottom:-90px;right:-90px;animation-delay:-5s}
@keyframes float{0%,100%{transform:translateY(0) scale(1)}50%{transform:translateY(-26px) scale(1.08)}}

.back{position:fixed;top:16px;left:16px;z-index:50;display:inline-flex;align-items:center;gap:4px;
  background:var(--ac);color:#fff;text-decoration:none;font-size:14px;font-weight:600;
  padding:9px 16px 9px 10px;border-radius:999px;box-shadow:0 6px 16px rgba(37,99,235,.35);transition:.25s}
.back:hover{background:var(--ac2);transform:translateX(-3px)}
.back .material-icons{font-size:18px}
.brand{position:fixed;top:16px;right:16px;z-index:50;text-decoration:none;font-weight:800;font-size:18px;
  color:var(--ink);background:var(--bg);border:1px solid var(--line);padding:7px 16px;border-radius:999px;
  box-shadow:0 4px 14px rgba(15,23,42,.08)}
.brand span{color:var(--ac)}

.wrap{max-width:840px;margin:0 auto;position:relative;z-index:1}

/* hero */
.hero{background:linear-gradient(135deg,#1d4ed8,#2563eb 60%,#3b82f6);color:#fff;border-radius:24px;
  padding:34px 24px 28px;text-align:center;position:relative;animation:up .6s ease both}
.hero-bg{position:absolute;inset:0;border-radius:24px;overflow:hidden;pointer-events:none}
.hero-bg::after{content:"";position:absolute;top:0;left:-60%;width:40%;height:100%;
  background:linear-gradient(90deg,transparent,rgba(255,255,255,.2),transparent);
  transform:skewX(-20deg);animation:shine 5s ease-in-out infinite}
@keyframes shine{0%{left:-60%}60%,100%{left:130%}}
.hero>*{position:relative}
.hero .ic{width:72px;height:72px;border-radius:50%;background:rgba(255,255,255,.18);display:grid;place-items:center;
  margin:0 auto 12px;animation:bob 3s ease-in-out infinite}
.hero .ic::before{content:"";position:absolute;inset:0;border-radius:50%;border:2px solid rgba(255,255,255,.6);animation:ring 2.2s ease-out infinite}
.hero .ic .material-icons{font-size:40px}
@keyframes ring{0%{transform:scale(1);opacity:.9}100%{transform:scale(1.7);opacity:0}}
@keyframes bob{0%,100%{transform:translateY(0)}50%{transform:translateY(-6px)}}
.hero h1{margin:0 0 6px;font-size:clamp(1.6rem,4vw,2.2rem);letter-spacing:-.02em}
.hero p{margin:0 auto 18px;opacity:.92;max-width:520px;font-size:15px}

/* search */
.search{position:relative;max-width:560px;margin:0 auto;display:flex;align-items:center;background:#fff;border-radius:999px;
  box-shadow:0 10px 24px rgba(0,0,0,.18);padding:5px;transition:box-shadow .25s}
.search:focus-within{box-shadow:0 0 0 4px rgba(255,255,255,.4),0 10px 24px rgba(0,0,0,.18)}
.search>.material-icons{color:var(--ac);font-size:24px;margin:0 6px 0 14px}
.search input{flex:1;min-width:0;height:44px;border:0;background:transparent;font:inherit;font-size:15px;color:#0f172a;outline:0}
.search input::-webkit-search-cancel-button{display:none}
.search .x{display:none;border:0;background:none;color:#64748b;cursor:pointer;padding:6px;border-radius:50%}
.search .x.on{display:block}
.search .x:hover{background:#f1f5f9}
.search .go{height:44px;padding:0 22px;border:0;border-radius:999px;background:var(--ac);color:#fff;font:inherit;font-weight:700;
  cursor:pointer;display:inline-flex;align-items:center;gap:6px;transition:.25s}
.search .go:hover{background:var(--ac2)}
.search .go:active{transform:scale(.96)}
.pop{display:flex;flex-wrap:wrap;justify-content:center;gap:8px;margin-top:14px}
.pop .lbl{font-size:12px;opacity:.85;align-self:center}
.chip{border:0;background:#fff;color:var(--ac);font:inherit;font-size:12.5px;font-weight:600;padding:6px 13px;border-radius:999px;
  cursor:pointer;transition:.2s;box-shadow:0 2px 8px rgba(0,0,0,.12)}
.chip:hover{transform:translateY(-2px);box-shadow:0 6px 14px rgba(0,0,0,.2)}

/* tiles */
.tiles{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin:20px 0 6px}
.tile{display:flex;flex-direction:column;align-items:center;gap:6px;text-align:center;padding:18px 10px;
  background:var(--bg);border:1px solid var(--line);border-radius:18px;color:var(--ink);text-decoration:none;
  font-size:13px;font-weight:600;box-shadow:0 4px 14px rgba(15,23,42,.05);transition:.25s;opacity:0;animation:pop .5s ease forwards}
.tile:nth-child(1){animation-delay:.2s}.tile:nth-child(2){animation-delay:.32s}.tile:nth-child(3){animation-delay:.44s}
.tile .ti{width:46px;height:46px;border-radius:14px;display:grid;place-items:center;background:var(--soft);color:var(--ac);transition:.3s}
.tile .ti .material-icons{font-size:26px}
.tile small{font-weight:400;color:var(--mute);font-size:12px}
.tile:hover{transform:translateY(-5px);border-color:var(--ac);box-shadow:0 14px 28px rgba(37,99,235,.16)}
.tile:hover .ti{background:var(--ac);color:#fff;transform:rotate(-8deg) scale(1.08)}
@keyframes pop{from{opacity:0;transform:scale(.88) translateY(12px)}to{opacity:1;transform:none}}

h2.sec{display:flex;align-items:center;gap:12px;margin:38px 0 16px;font-size:clamp(1.3rem,3.2vw,1.8rem);
  letter-spacing:-.02em;animation:up .6s ease both;scroll-margin-top:80px}
h2.sec .si{width:44px;height:44px;border-radius:14px;display:grid;place-items:center;color:#fff;flex:none;
  background:linear-gradient(135deg,var(--ac),#3b82f6);box-shadow:0 10px 20px -8px rgba(37,99,235,.6);animation:bob 3s ease-in-out infinite}
h2.sec::after{content:"";flex:1;height:3px;border-radius:3px;background:linear-gradient(90deg,#93b4f5,transparent);
  transform-origin:left;animation:draw .9s .2s ease both}
@keyframes draw{from{transform:scaleX(0)}to{transform:scaleX(1)}}

/* category filter */
.cats{display:flex;gap:8px;overflow-x:auto;padding:2px 2px 10px;margin-bottom:6px;scrollbar-width:thin}
.cat{flex:none;display:inline-flex;align-items:center;gap:6px;border:1px solid var(--line);background:var(--bg);color:var(--ink);
  font:inherit;font-size:13px;font-weight:600;padding:8px 14px;border-radius:999px;cursor:pointer;transition:.2s}
.cat .material-icons{font-size:17px;color:var(--ac)}
.cat:hover{border-color:var(--ac)}
.cat.on{background:var(--ac);border-color:var(--ac);color:#fff}
.cat.on .material-icons{color:#fff}

/* result bar */
.rinfo{display:none;align-items:center;gap:10px;margin:0 0 14px;padding:12px 16px;border-radius:14px;background:var(--soft);
  color:var(--ink);font-size:14px;border:1px solid #bfd3fb;animation:open .3s ease both}
.rinfo.on{display:flex}
.rinfo .material-icons{color:var(--ac)}
.rinfo b{color:var(--ac)}
.rinfo button{margin-left:auto;border:0;background:var(--ac);color:#fff;font:inherit;font-size:12.5px;font-weight:600;
  padding:6px 14px;border-radius:999px;cursor:pointer}
.rinfo button:hover{background:var(--ac2)}

/* accordion */
.faq{counter-reset:q}
details.q{counter-increment:q;position:relative;margin:0 0 12px;background:var(--bg);border:1px solid var(--line);
  border-radius:16px;overflow:hidden;box-shadow:0 1px 2px rgba(15,23,42,.04);transition:.3s;animation:up .5s ease both}
details.q.best{border-color:var(--ac);box-shadow:0 14px 30px -14px rgba(37,99,235,.55)}
details.q::before{content:"";position:absolute;left:0;top:0;bottom:0;width:4px;background:linear-gradient(180deg,var(--ac),#60a5fa);
  transform:scaleY(0);transform-origin:top;transition:transform .35s}
details.q:hover{transform:translateY(-2px);border-color:#93b4f5;box-shadow:0 12px 26px -14px rgba(37,99,235,.45)}
details.q[open]{border-color:#93b4f5;box-shadow:0 16px 32px -16px rgba(37,99,235,.5)}
details.q[open]::before,details.q.best::before{transform:scaleY(1)}
details.q summary{display:flex;align-items:center;gap:14px;padding:16px 18px;cursor:pointer;list-style:none;
  font-weight:700;font-size:1.02rem;user-select:none;transition:.25s}
details.q summary::-webkit-details-marker{display:none}
details.q summary:hover{background:var(--soft)}
details.q[open] summary{color:var(--ac)}
details.q summary::before{content:counter(q);flex:none;width:34px;height:34px;border-radius:50%;display:grid;place-items:center;
  font-size:.92rem;font-weight:800;color:var(--ac);background:var(--soft);transition:.35s cubic-bezier(.3,1.6,.5,1)}
details.q:hover summary::before{transform:scale(1.1) rotate(-6deg)}
details.q[open] summary::before{color:#fff;background:var(--ac)}
details.q summary::after{content:"+";margin-left:auto;flex:none;width:30px;height:30px;border-radius:50%;display:grid;place-items:center;
  font-size:1.4rem;line-height:1;color:var(--ac);border:1.5px solid #93b4f5;transition:.35s cubic-bezier(.3,1.4,.5,1)}
details.q[open] summary::after{transform:rotate(135deg);color:#fff;background:var(--ac);border-color:var(--ac)}
.qwrap{flex:1;min-width:0}
.qt{display:block;line-height:1.35}
.tag{display:inline-block;margin-top:4px;font-size:11px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;
  color:var(--ac);background:var(--soft);padding:2px 9px;border-radius:999px}
mark{background:#fde68a;color:#0f172a;border-radius:4px;padding:0 2px}
details.q .ans{padding:2px 22px 20px 66px;color:var(--mute);line-height:1.7;animation:open .4s ease both}
details.q .ans p{margin:0 0 10px}
details.q .ans ol{margin:0 0 12px;padding-left:20px}
details.q .ans li{margin-bottom:6px}
.ahelp{display:flex;flex-wrap:wrap;align-items:center;gap:10px;margin-top:10px;padding-top:12px;border-top:1px dashed var(--line);font-size:13px}
.ahelp button{border:0;background:var(--ac);color:#fff;font:inherit;font-size:12.5px;font-weight:600;padding:7px 14px;border-radius:999px;
  cursor:pointer;display:inline-flex;align-items:center;gap:5px;transition:.2s}
.ahelp button:hover{background:var(--ac2);transform:translateY(-1px)}
.ahelp button .material-icons{font-size:16px}
.ahelp .thx{color:#16a34a;font-weight:600;display:none}
@keyframes open{from{opacity:0;transform:translateY(-8px)}to{opacity:1;transform:none}}

.nores{display:none;text-align:center;padding:30px 16px;color:var(--mute);border:2px dashed #93b4f5;border-radius:18px;background:var(--bg)}
.nores.on{display:block;animation:pop .4s ease both}
.nores .material-icons{font-size:44px;color:var(--ac);display:block;margin:0 auto 8px}
.nores b{color:var(--ink);display:block;font-size:17px;margin-bottom:4px}
.nores .btn{margin-top:14px}

/* contact */
.contact{background:var(--bg);border:1px solid var(--line);border-radius:22px;padding:clamp(18px,3vw,28px);
  box-shadow:0 18px 40px -22px rgba(37,99,235,.45);animation:up .7s ease both;scroll-margin-top:80px}
.contact .intro{display:flex;align-items:center;gap:10px;color:var(--mute);font-size:14px;margin:0 0 16px}
.contact .intro .material-icons{color:var(--ac)}
.fgrid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.fld{position:relative;margin-bottom:14px}
.fld .material-icons{position:absolute;left:14px;top:14px;color:#93a3bd;font-size:20px;transition:.25s}
.fld input,.fld textarea{width:100%;padding:13px 15px 13px 44px;border-radius:12px;border:1.5px solid var(--line);
  background:var(--page);font:inherit;color:var(--ink);transition:.25s}
.fld textarea{resize:vertical;min-height:120px}
.fld input:focus,.fld textarea:focus{outline:0;background:var(--bg);border-color:var(--ac);box-shadow:0 0 0 4px rgba(37,99,235,.16);transform:translateY(-1px)}
.fld:focus-within .material-icons{color:var(--ac)}
.cnt{text-align:right;font-size:12px;color:var(--mute);margin:-6px 4px 12px}
.btn{position:relative;overflow:hidden;display:inline-flex;align-items:center;justify-content:center;gap:8px;height:48px;
  padding:0 34px;border:0;border-radius:999px;background:var(--ac);color:#fff;font:inherit;font-weight:700;cursor:pointer;
  box-shadow:0 14px 28px -12px rgba(37,99,235,.8);transition:.3s}
.btn::after{content:"";position:absolute;top:0;left:-80%;width:50%;height:100%;
  background:linear-gradient(100deg,transparent,rgba(255,255,255,.45),transparent);transform:skewX(-20deg);animation:shine 3.2s ease-in-out infinite}
.btn:hover{background:var(--ac2);transform:translateY(-3px)}
.btn:hover .material-icons{transform:translateX(4px) rotate(-12deg)}
.btn .material-icons{transition:.3s}
.btn:active{transform:scale(.97)}
.ok{display:flex;align-items:center;gap:10px;margin:0 0 16px;padding:14px 18px;border-radius:12px;font-weight:600;
  color:#166534;background:#ecfdf3;border:1px solid #bbf7d0;animation:pop2 .6s cubic-bezier(.2,.7,.2,1) both}
.ok .material-icons{color:#16a34a;font-size:26px}
@keyframes pop2{0%{opacity:0;transform:scale(.9) translateY(-6px)}60%{transform:scale(1.03)}100%{opacity:1;transform:none}}

/* admin messages */
.mwrap{overflow-x:auto;border-radius:16px;border:1px solid var(--line);background:var(--bg);box-shadow:0 14px 34px -22px rgba(37,99,235,.45);animation:up .7s ease both}
.mwrap table{width:100%;border-collapse:collapse;min-width:560px}
.mwrap th{padding:14px 16px;text-align:left;font-size:.8rem;letter-spacing:.05em;text-transform:uppercase;color:#fff;background:linear-gradient(135deg,#1d4ed8,#3b82f6)}
.mwrap td{padding:14px 16px;vertical-align:top;color:var(--mute);border-top:1px solid var(--line);line-height:1.55;overflow-wrap:anywhere}
.mwrap td:first-child{color:var(--ink);font-weight:600}
.mwrap tr:nth-child(even) td{background:var(--page)}
.mwrap tr:hover td{background:var(--soft)}
.mwrap tr:not(:first-child){animation:up .5s ease both;animation-delay:calc(.2s + var(--r,0)*.04s)}
.cntb{margin-left:8px;font-size:13px;font-weight:700;color:var(--ac);background:var(--soft);padding:3px 10px;border-radius:999px}

@keyframes up{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:none}}
@media (max-width:620px){
  .tiles{grid-template-columns:1fr}
  .tile{flex-direction:row;text-align:left;padding:14px 16px}
  .fgrid{grid-template-columns:1fr}
  details.q .ans{padding-left:20px}
  details.q summary::before{display:none}
  .btn{width:100%}
  .brand{display:none}
  .search .go span.t{display:none}
  .search .go{padding:0 14px}
}
@media (prefers-reduced-motion:reduce){*{animation:none!important;transition:none!important;opacity:1!important}}
</style>
</head>
<body>
<div class="blob b1"></div><div class="blob b2"></div>

<a class="back" href="javascript:history.back()"><span class="material-icons">arrow_back</span> Back</a>
<a class="brand" href="index.php">Work<span>onnect</span></a>

<div class="wrap">

  <div class="hero">
    <div class="hero-bg"></div>
    <div class="ic"><span class="material-icons">support_agent</span></div>
    <h1>How can we help?</h1>
    <p>Describe your problem in your own words. For example: “I can’t login” or “payment failed”.</p>
    <div class="search" role="search">
      <span class="material-icons">search</span>
      <input type="search" id="q" placeholder="Search login, password, payment, jobs, verification..." autocomplete="off" aria-label="Search help">
      <button type="button" class="x" id="qx" aria-label="Clear search"><span class="material-icons">close</span></button>
      <button type="button" class="go" id="qgo"><span class="material-icons">search</span><span class="t">Search</span></button>
    </div>
    <div class="pop">
      <span class="lbl">Popular:</span>
      <?php foreach ($popular as $p): ?><button type="button" class="chip" data-q="<?= e($p) ?>"><?= e($p) ?></button><?php endforeach; ?>
    </div>
  </div>

  <div class="tiles">
    <a class="tile" href="#faq"><span class="ti"><span class="material-icons">quiz</span></span>FAQ<small>Quick answers</small></a>
    <a class="tile" href="#contact"><span class="ti"><span class="material-icons">mail</span></span>Contact us<small>Message the admin</small></a>
    <a class="tile" href="index.php"><span class="ti"><span class="material-icons">home</span></span>Home<small>Go to Workonnect</small></a>
  </div>

  <h2 class="sec" id="faq"><span class="si"><span class="material-icons">help</span></span> Help &amp; FAQ</h2>

  <div class="cats" id="cats">
    <button type="button" class="cat on" data-c="all"><span class="material-icons">apps</span> All</button>
    <?php foreach ($cats as $k => $c): ?>
    <button type="button" class="cat" data-c="<?= e($k) ?>"><span class="material-icons"><?= e($c[1]) ?></span> <?= e($c[0]) ?></button>
    <?php endforeach; ?>
  </div>

  <div class="rinfo" id="rinfo"><span class="material-icons">travel_explore</span><span id="rtext"></span><button type="button" id="rclear">Clear</button></div>

  <div class="faq" id="faqList">
    <?php foreach ($faqs as $i => $f): ?>
    <details class="q" data-i="<?= $i ?>" data-cat="<?= e($f[0]) ?>" data-kw="<?= e($f[4]) ?>">
      <summary>
        <span class="qwrap"><span class="qt"><?= e($f[1]) ?></span><span class="tag"><?= e($cats[$f[0]][0]) ?></span></span>
      </summary>
      <div class="ans">
        <p><?= e($f[2]) ?></p>
        <?php if ($f[3]): ?><ol><?php foreach ($f[3] as $st): ?><li><?= e($st) ?></li><?php endforeach; ?></ol><?php endif; ?>
        <div class="ahelp">
          <span>Did this solve it?</span>
          <button type="button" class="yes"><span class="material-icons">thumb_up</span> Yes</button>
          <button type="button" class="ask"><span class="material-icons">mail</span> No, message the admin</button>
          <span class="thx">Great, glad it helped!</span>
        </div>
      </div>
    </details>
    <?php endforeach; ?>
    <div class="nores" id="nores">
      <span class="material-icons">search_off</span>
      <b>We couldn’t find an exact answer</b>
      Try different words, or send your question to the admin and we’ll reply.
      <div><button type="button" class="btn" id="askNo"><span class="material-icons">send</span> Ask the admin</button></div>
    </div>
  </div>

  <h2 class="sec" id="contact"><span class="si"><span class="material-icons">mail</span></span> Contact Us</h2>
  <form class="contact" method="post">
    <p class="intro"><span class="material-icons">schedule</span> Tell us what went wrong or what you need. The admin will see it right away.</p>
    <?php if ($sent): ?>
      <p class="ok"><span class="material-icons">check_circle</span> Thanks! Your message was sent to the admin.</p>
    <?php endif; ?>
    <div class="fgrid">
      <div class="fld"><span class="material-icons">person</span>
        <input name="name" placeholder="Your name" maxlength="80" value="<?= e($u["name"] ?? "") ?>" required></div>
      <div class="fld"><span class="material-icons">alternate_email</span>
        <input type="email" name="email" placeholder="Your email" maxlength="100" value="<?= e($u["email"] ?? "") ?>" required></div>
    </div>
    <div class="fld"><span class="material-icons">chat</span>
      <textarea name="msg" id="msg" rows="3" maxlength="500" placeholder="Your message" required></textarea></div>
    <div class="cnt"><span id="cn">0</span> / 500</div>
    <button class="btn"><span class="material-icons">send</span> Send</button>
  </form>

  <?php if ($isAdmin): ?>
  <h2 class="sec"><span class="si"><span class="material-icons">inbox</span></span> Messages <span class="cntb"><?= count($msgs) ?></span></h2>
  <div class="mwrap"><table>
    <tr><th>From</th><th>Email</th><th>Message</th><th>Date</th></tr>
    <?php foreach ($msgs as $i => $x): ?>
    <tr style="--r:<?= min($i, 15) ?>">
      <td><?= e($x["name"]) ?></td>
      <td><?= e($x["email"]) ?></td>
      <td><?= e($x["msg"]) ?></td>
      <td><?= e($x["created"]) ?></td>
    </tr>
    <?php endforeach; ?>
  </table></div>
  <?php endif; ?>

</div>

<script>
(function () {
  var q = document.getElementById('q'), qx = document.getElementById('qx'),
      list = document.getElementById('faqList'), nores = document.getElementById('nores'),
      rinfo = document.getElementById('rinfo'), rtext = document.getElementById('rtext'),
      msg = document.getElementById('msg'), cn = document.getElementById('cn');
  var items = [].slice.call(list.querySelectorAll('details.q'));
  var cat = 'all';

  // ---- words that carry no meaning ----
  var STOP = {};
  ('a an the i my me is are am do does did how to can what why when where of in on for and or it its not no dont didnt doesnt im ive ' +
   'get getting have has was will should we you your with at be this that there please help need want').split(' ').forEach(function (w) { STOP[w] = 1; });

  // ---- related words: typing one also finds the others (lower weight) ----
  var GROUPS = [
    'login log signin sign access logging logon credentials',
    'password pass passcode pwd forgot forgotten reset recover',
    'payment pay paid money upi refund charged deducted transaction checkout',
    'worker workers professional electrician plumber technician labour',
    'customer client user',
    'job work task booking book request',
    'verify verified verification badge id proof aadhaar',
    'problem issue error bug wrong fail failed broken stuck crash',
    'cancel delete remove edit modify',
    'account profile register signup create join',
    'notification notifications notify alert',
    'slow loading load blank website page',
    'fake scam fraud cheat spam unsafe abuse rude',
    'photo image picture upload file'
  ].map(function (g) { return g.split(' '); });
  var REL = {};
  GROUPS.forEach(function (g) { g.forEach(function (w) { (REL[w] = REL[w] || []).push.apply(REL[w], g); }); });

  function words(s) {
    return s.toLowerCase().replace(/[’']/g, '').replace(/[^a-z0-9\u0c80-\u0cff\u0900-\u097f ]+/g, ' ')
            .split(/\s+/).filter(Boolean);
  }
  function terms(s) { return words(s).filter(function (w) { return !STOP[w]; }); }

  function dist(a, b, max) {            // small edit distance, for typos
    if (Math.abs(a.length - b.length) > max) return max + 1;
    var p = [], i, j;
    for (j = 0; j <= b.length; j++) p[j] = j;
    for (i = 1; i <= a.length; i++) {
      var prev = p[0], rowMin; p[0] = i; rowMin = p[0];
      for (j = 1; j <= b.length; j++) {
        var t = p[j];
        p[j] = Math.min(p[j] + 1, p[j - 1] + 1, prev + (a[i - 1] === b[j - 1] ? 0 : 1));
        prev = t; if (p[j] < rowMin) rowMin = p[j];
      }
      if (rowMin > max) return max + 1;
    }
    return p[b.length];
  }

  // ---- build the search index from the page itself ----
  items.forEach(function (d) {
    var qt = d.querySelector('.qt');
    d._qt = qt; d._qtext = qt.textContent;
    d._q = words(d._qtext);
    d._k = words(d.getAttribute('data-kw') || '');
    d._a = words(d.querySelector('.ans').textContent);
    d._all = d._q.concat(d._k, d._a);
    d._full = (d._qtext + ' ' + d.querySelector('.ans').textContent).toLowerCase();
  });

  function has(arr, t) { return arr.indexOf(t) > -1; }
  function near(arr, t, mode) {
    for (var i = 0; i < arr.length; i++) {
      var w = arr[i];
      if (t.length >= 3 && (w.indexOf(t) === 0 || (w.length >= 4 && t.indexOf(w) === 0))) return 1;
      if (mode && t.length >= 4 && dist(t, w, t.length >= 7 ? 2 : 1) <= (t.length >= 7 ? 2 : 1)) return 2;
    }
    return 0;
  }

  function score(d, ts, raw) {
    var s = 0;
    ts.forEach(function (t) {
      var best = 0;
      var cands = [[t, 1]];
      (REL[t] || []).forEach(function (r) { if (r !== t) cands.push([r, .45]); });
      cands.forEach(function (c) {
        var w = c[1], x = c[0], v = 0;
        if (has(d._q, x)) v = 5; else if (has(d._k, x)) v = 4; else if (has(d._a, x)) v = 2;
        else { var n = near(d._q.concat(d._k), x, w === 1); if (n) v = 1.5; else if (w === 1 && near(d._a, x, 0)) v = 1; }
        if (v * w > best) best = v * w;
      });
      s += best;
    });
    if (raw.length > 3 && d._full.indexOf(raw) > -1) s += 6;        // whole phrase found
    return s;
  }

  function esc(t) { return t.replace(/[&<>"]/g, function (c) { return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;'}[c]; }); }
  function highlight(d, ts) {
    var good = ts.filter(function (t) { return t.length >= 3; })
                 .map(function (t) { return t.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); });
    if (!good.length) { d._qt.textContent = d._qtext; return; }
    var re = new RegExp('(' + good.join('|') + ')', 'gi');
    d._qt.innerHTML = d._qtext.split(re).map(function (p, i) { return i % 2 ? '<mark>' + esc(p) + '</mark>' : esc(p); }).join('');
  }

  function run(openTop) {
    var raw = q.value.trim().toLowerCase(), ts = terms(raw);
    qx.classList.toggle('on', !!raw);
    var scored = [];

    items.forEach(function (d) {
      d.classList.remove('best');
      var inCat = cat === 'all' || d.getAttribute('data-cat') === cat;
      var sc = ts.length ? score(d, ts, raw) : 0;
      d._s = sc;
      d._show = inCat && (!ts.length || sc > 0);
      highlight(d, ts);
      if (d._show) scored.push(d);
    });

    // order: best match first while searching, original order otherwise
    items.slice().sort(function (a, b) {
      return ts.length ? (b._s - a._s) || (a.dataset.i - b.dataset.i) : (a.dataset.i - b.dataset.i);
    }).forEach(function (d) { list.insertBefore(d, nores); });

    items.forEach(function (d) { d.style.display = d._show ? '' : 'none'; if (!ts.length) d.open = false; });

    if (ts.length && scored.length) {
      var top = items.slice().sort(function (a, b) { return b._s - a._s; })[0];
      if (top._show) { top.classList.add('best'); top.open = true; }
      scored.forEach(function (d) { if (d !== top && d._s < top._s * .6) d.open = false; });
    }

    var none = ts.length && !scored.length;
    nores.classList.toggle('on', !!none);
    rinfo.classList.toggle('on', !!ts.length);
    if (ts.length) {
      rtext.innerHTML = scored.length
        ? '<b>' + scored.length + '</b> answer' + (scored.length > 1 ? 's' : '') + ' found for “' + esc(q.value.trim()) + '”. Best match is at the top.'
        : 'No answers for “' + esc(q.value.trim()) + '”.';
    }
    if (openTop && scored.length) {
      var f = list.querySelector('details.q:not([style*="none"])');
      if (f) f.scrollIntoView({behavior: 'smooth', block: 'center'});
    }
  }

  q.addEventListener('input', function () { run(false); });
  q.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); run(true); } });
  document.getElementById('qgo').addEventListener('click', function () { run(true); document.getElementById('faq').scrollIntoView({behavior: 'smooth'}); });
  function clear() { q.value = ''; run(false); q.focus(); }
  qx.addEventListener('click', clear);
  document.getElementById('rclear').addEventListener('click', clear);

  document.querySelectorAll('.chip').forEach(function (c) {
    c.addEventListener('click', function () {
      q.value = c.getAttribute('data-q'); cat = 'all'; syncCats();
      run(true); document.getElementById('faq').scrollIntoView({behavior: 'smooth'});
    });
  });

  // category filter
  var catBtns = [].slice.call(document.querySelectorAll('.cat'));
  function syncCats() { catBtns.forEach(function (b) { b.classList.toggle('on', b.getAttribute('data-c') === cat); }); }
  catBtns.forEach(function (b) { b.addEventListener('click', function () { cat = b.getAttribute('data-c'); syncCats(); run(false); }); });

  // send a question to the admin form
  function askAdmin(text) {
    var pre = text ? 'Regarding: ' + text + '\n' : '';
    if (!msg.value.trim()) msg.value = pre.slice(0, 500);
    cn.textContent = msg.value.length;
    document.getElementById('contact').scrollIntoView({behavior: 'smooth'});
    setTimeout(function () { msg.focus(); msg.setSelectionRange(msg.value.length, msg.value.length); }, 500);
  }
  document.getElementById('askNo').addEventListener('click', function () { askAdmin(q.value.trim()); });
  items.forEach(function (d) {
    d.querySelector('.ask').addEventListener('click', function () { askAdmin(d._qtext); });
    d.querySelector('.yes').addEventListener('click', function () {
      d.querySelector('.thx').style.display = 'inline'; this.style.display = 'none'; d.querySelector('.ask').style.display = 'none';
    });
  });

  msg.addEventListener('input', function () { cn.textContent = msg.value.length; });

  // open the answer if the link has a search, e.g. help.php?q=password
  var m = location.search.match(/[?&]q=([^&]+)/);
  if (m) { q.value = decodeURIComponent(m[1].replace(/\+/g, ' ')); run(false); }

  <?php if ($sent): ?>document.getElementById('contact').scrollIntoView();<?php endif; ?>
})();
</script>
</body>
</html>
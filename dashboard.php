<?php // Role based dashboard (customer / worker / admin)
require "config.php";
need();
$u = me();
expireJobs($pdo);
if ($u["role"] == "worker" && isset($_POST["alerts"])) {
    $pdo->prepare(
        "UPDATE users SET job_alerts=1-job_alerts WHERE id=?",
    )->execute([$u["id"]]);
    header("Location: dashboard.php");
    exit();
}
if ($u["role"] == "admin" && isset($_POST["ban"])) {
    $pdo->prepare(
        "UPDATE users SET banned=1-banned,ban_reason=? WHERE id=? AND role<>'admin'",
    )->execute([substr($_POST["why"] ?? "", 0, 200), (int) $_POST["ban"]]);
    logit("Ban/unban user #" . (int) $_POST["ban"]);
    header("Location: dashboard.php");
    exit();
}
if ($u["role"] == "admin" && isset($_POST["v"])) {
    $pdo->prepare("UPDATE users SET verified=1-verified WHERE id=?")->execute([
        (int) $_POST["v"],
    ]);
    notify(
        (int) $_POST["v"],
        "Your verified badge status was updated by admin",
    );
    header("Location: dashboard.php");
    exit();
}
head("Dashboard");
echo d_top("dashboard.php");
if ($u["role"] == "worker") {
    // the top tab bar repeats the left menu and the sections below, so it is hidden for workers (display only)
    echo '<script>(function(){var n=["overview","open jobs","my jobs","earnings","schedule"],g=[];document.querySelectorAll("a,button").forEach(function(a){if(n.indexOf(a.textContent.trim().toLowerCase())<0)return;for(var l=1;l<=2;l++){var p=a;for(var i=0;i<l&&p;i++)p=p.parentElement;if(!p||p.closest(".dash"))continue;var o=g.filter(function(x){return x.p===p})[0];if(o)o.n++;else g.push({p:p,n:1})}});var t=g.filter(function(x){return x.n>=4&&x.p.tagName!=="BODY"&&x.p.tagName!=="MAIN"})[0];if(t)t.p.style.display="none"})();</script>';
}
?>
<link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@700&display=swap" rel="stylesheet">
<style>
/* ===== Dashboard animations & styling (scoped to .dash) ===== */
.dash{--d-a:#0284c7;--d-b:#38bdf8;--d-c:#ff4f93;position:relative}
.dash *{box-sizing:border-box}

/* Welcome banner */
.dash>h2:first-of-type{position:relative;overflow:hidden;margin:0 0 20px;padding:22px 24px;border-radius:20px;color:#fff;
  background:linear-gradient(120deg,#0c4a6e,#0369a1,#0284c7,#38bdf8);background-size:300% 300%;
  box-shadow:0 16px 36px rgba(2,132,199,.33);animation:dDrop .7s cubic-bezier(.2,.8,.2,1) both,dFlow 10s ease infinite}
.dash>h2:first-of-type::after{content:"";position:absolute;width:200px;height:200px;border-radius:50%;right:-50px;top:-90px;
  background:rgba(255,255,255,.14);animation:dPulse 6s ease-in-out infinite}

/* Section headings */
.dash h3{display:flex;align-items:center;gap:8px;margin:28px 0 12px;position:relative;padding-bottom:8px;animation:dRise .6s both}
.dash h3::after{content:"";position:absolute;left:0;bottom:0;height:3px;width:56px;border-radius:3px;
  background:linear-gradient(90deg,var(--d-a),var(--d-b));animation:dGrow .9s .2s both}
.dash h3 .mi{color:var(--d-a)}

/* Cards */
.dash .card{position:relative;overflow:hidden;border-radius:18px;transition:transform .3s cubic-bezier(.2,.8,.2,1),box-shadow .3s}
.dash .card::before{content:"";position:absolute;left:0;top:0;height:100%;width:4px;
  background:linear-gradient(180deg,var(--d-a),var(--d-b));transform:scaleY(0);transform-origin:top;transition:transform .35s}
.dash .grid>.card:hover{transform:translateY(-6px);box-shadow:0 18px 36px rgba(2,132,199,.18)}
.dash .grid>.card:hover::before{transform:scaleY(1)}

/* Scroll reveal (class added by JS only, so no-JS stays visible) */
.dash .rv{opacity:0;transform:translateY(26px) scale(.98)}
.dash .rv.in{opacity:1;transform:none;transition:opacity .6s cubic-bezier(.2,.8,.2,1),transform .6s cubic-bezier(.2,.8,.2,1);transition-delay:var(--d,0s)}
.dash .grid>.card.rv.in:hover{transform:translateY(-6px)}

/* Buttons */
.dash .btn,.dash button{position:relative;overflow:hidden;transition:transform .2s,box-shadow .25s,filter .2s}
.dash .btn{display:inline-block;margin:3px 2px}
.dash .btn:hover,.dash button:hover{transform:translateY(-3px);box-shadow:0 10px 22px rgba(15,98,254,.28);filter:brightness(1.06)}
.dash .btn:active,.dash button:active{transform:scale(.95)}
.dash .btn .mi,.dash .btn .mi-xs{transition:transform .3s}
.dash .btn:hover .mi,.dash .btn:hover .mi-xs{transform:scale(1.25) rotate(-8deg)}
.d-ripple{position:absolute;border-radius:50%;background:rgba(255,255,255,.55);transform:scale(0);animation:dRipple .6s linear;pointer-events:none}

/* Emergency badge */
.dash .emg{display:inline-block;animation:dEmg 1.4s ease-in-out infinite}

/* Inputs */
.dash input,.dash select{transition:border-color .25s,box-shadow .25s,transform .25s}
.dash input:focus,.dash select:focus{outline:none;border-color:var(--d-a);box-shadow:0 0 0 4px rgba(2,132,199,.18);transform:translateY(-1px)}

/* Admin stats */
.dash .stat{font-size:clamp(26px,5vw,38px);margin:6px 0 0;background:linear-gradient(90deg,var(--d-a),var(--d-b));
  -webkit-background-clip:text;background-clip:text;color:transparent;display:inline-block}
.dash .bar{transform-origin:left center;border-radius:8px;background:linear-gradient(90deg,#0F62FE,#00a8ff)!important;
  animation:dBar 1s cubic-bezier(.2,.8,.2,1) both}

/* Tables */
.dash .tw{overflow-x:auto;border-radius:16px;box-shadow:0 8px 24px rgba(0,0,0,.08);animation:dRise .7s both}
.dash table{width:100%;border-collapse:separate;border-spacing:0;background:#fff;border-radius:16px;overflow:hidden}
.dash th{background:linear-gradient(90deg,#0284c7,#00a8ff);color:#fff;padding:13px 12px;text-align:left;font-weight:600;white-space:nowrap}
.dash td{padding:11px 12px;border-bottom:1px solid #eef0f6;transition:background .25s}
.dash tr.rv.in:hover td,.dash tr:hover td{background:#f0f9ff}
.dash td form{margin:0;display:flex;gap:6px;align-items:center;flex-wrap:wrap}

/* ===== Aligned action-button row (all roles) ===== */
.dash .d-actions{display:flex;flex-wrap:wrap;align-items:center;gap:10px;margin:0 0 8px}
.dash .d-actions form{margin:0;display:flex}
.dash .d-actions .btn,.dash .d-actions button{display:inline-flex;align-items:center;justify-content:center;gap:6px;
  margin:0;height:40px;padding:0 16px;font-size:14px;line-height:1;white-space:nowrap;
  border-radius:10px;box-sizing:border-box;text-decoration:none;cursor:pointer}
.dash .d-actions .mi,.dash .d-actions .mi-xs{font-size:18px;line-height:1;vertical-align:middle}
.dash .d-actions .btn:hover,.dash .d-actions button:hover{transform:translateY(-2px);box-shadow:0 6px 14px rgba(15,98,254,.22)}

/* ===== Compact search bar (Open Jobs, small category box) ===== */
.dash .d-search{display:flex;align-items:center;flex-wrap:wrap;gap:8px;margin:0 0 6px}
.dash .d-search input[name=q],.dash .d-search select{height:36px;margin:0;padding:0 10px;font-size:13px;border-radius:8px;box-sizing:border-box}
.dash .d-search input[name=q]{flex:1 1 200px;min-width:0;max-width:340px}
.dash .d-search select{flex:0 0 140px;width:140px}
.dash .d-search button{display:inline-flex;align-items:center;justify-content:center;gap:5px;height:36px;margin:0;padding:0 14px;
  font-size:13px;font-weight:600;line-height:1;border:0;border-radius:8px;background:#0284c7;color:#fff;
  cursor:pointer;box-shadow:none;transition:background .2s,transform .15s}
.dash .d-search button .mi{font-size:17px;margin:0}
.dash .d-search button:hover{background:#0369a1;transform:none;box-shadow:none;filter:none}
.dash .d-search button:active{transform:scale(.96)}

/* ===== My Jobs cards (only used when jobs($rows, true)) ===== */
.mj-card{display:flex;flex-direction:column;gap:10px;padding:18px 18px 16px!important}
.mj-top{display:flex;align-items:flex-start;justify-content:space-between;gap:10px}
.mj-title{margin:0;font-size:17px;font-weight:700;line-height:1.3;color:#0f172a}
.mj-cat{align-self:flex-start;background:#e0f2fe;color:#0284c7;font:600 12px system-ui,sans-serif;padding:3px 10px;border-radius:20px}
.mj-meta{display:flex;align-items:center;gap:8px;font-size:13.5px;color:#64748b}
.mj-meta .mi{font-size:18px;color:var(--d-a);flex:none}
.mj-meta b{color:#0f172a}

.mj-status{flex:none;display:inline-flex;align-items:center;gap:6px;padding:4px 11px;border-radius:20px;
  font:700 11px system-ui,sans-serif;letter-spacing:.4px;text-transform:uppercase;background:#f1f5f9;color:#475569}
.mj-status::before{content:"";width:7px;height:7px;border-radius:50%;background:currentColor;animation:mjDot 1.8s ease-in-out infinite}
.mj-status.s-open{background:#e0f2fe;color:#0369a1}
.mj-status.s-assigned,.mj-status.s-accepted,.mj-status.s-booked,.mj-status.s-requested{background:#ede9fe;color:#6d28d9}
.mj-status.s-inprogress,.mj-status.s-ongoing,.mj-status.s-started{background:#fef3c7;color:#b45309}
.mj-status.s-completed,.mj-status.s-paid,.mj-status.s-done{background:#dcfce7;color:#15803d}
.mj-status.s-completed::before,.mj-status.s-paid::before,.mj-status.s-done::before{animation:none}
.mj-status.s-cancelled,.mj-status.s-expired,.mj-status.s-rejected{background:#fee2e2;color:#b91c1c}
.mj-status.s-cancelled::before,.mj-status.s-expired::before,.mj-status.s-rejected::before{animation:none}

.mj-sos{align-self:flex-start;display:inline-flex;align-items:center;gap:4px;background:linear-gradient(135deg,#ef4444,#dc2626);
  color:#fff;font:700 11px system-ui,sans-serif;letter-spacing:.5px;padding:4px 10px;border-radius:20px;animation:dEmg 1.4s ease-in-out infinite}
.mj-sos .mi{font-size:15px}

.mj-foot{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-top:auto;padding-top:12px;border-top:1px dashed #e2e8f0}
.mj-id{color:#94a3b8;font:600 12px system-ui,sans-serif}
.mj-card .btn{display:inline-flex;align-items:center;justify-content:center;gap:4px;margin:0;height:36px;padding:0 14px;
  font-size:13px;line-height:1;border-radius:8px;text-decoration:none}
.mj-card .btn .mj-arrow{font-size:17px;transition:transform .25s}
.mj-card:hover .btn .mj-arrow{transform:translateX(4px)}

.mj-empty{grid-column:1/-1;text-align:center;padding:44px 16px;color:#64748b;background:#fff;border:2px dashed #e2e8f0;border-radius:18px}
.mj-empty .mi{font-size:46px;display:block;margin-bottom:6px;color:var(--d-a);animation:mjFloat 3s ease-in-out infinite}



/* ===== Clean UI override (CSS only) ===== */
.dash{--blue:#2563eb;--blue-dark:#1d4ed8;--d-a:#2563eb;--d-b:#3b82f6;--ink:#1e293b;--muted:#64748b;--line:#e5e9f2}
body:not(.dark),body:not(.dark) main{background:#f5f7fb!important}

/* welcome banner: solid blue, no animation */
.dash>h2:first-of-type{background:var(--blue);animation:none;border-radius:16px;padding:20px 24px;box-shadow:0 8px 20px -10px rgba(37,99,235,.6)}
.dash>h2:first-of-type::after{display:none}
.dash .wl-name{background:none;-webkit-text-fill-color:#fff;color:#fff;animation:none;filter:none}
.dash>h2 .ok{color:var(--blue)!important;-webkit-text-fill-color:var(--blue);box-shadow:none}
.dash>h2 .ok .mi-xs{color:var(--blue)}

/* headings */
.dash h3{color:var(--ink)}
.dash h3::after{background:var(--blue)}
.dash h3 .mi{color:var(--blue)}

/* cards */
.dash .card{background:#fff;border:1px solid var(--line);border-radius:14px;box-shadow:0 1px 3px rgba(15,23,42,.05)}
.dash .card::before{background:var(--blue)}
.dash .grid>.card:hover{border-color:#bfdbfe;box-shadow:0 10px 24px -12px rgba(37,99,235,.3)}
.dash .mj-title{color:var(--ink)}
.dash .mj-cat{background:#eff6ff;color:var(--blue)}
.dash .mj-meta .mi{color:var(--blue)}
.dash .mj-foot{border-top:1px solid var(--line)}
.dash .mj-empty{border-color:var(--line)}
.dash .mj-empty .mi{color:var(--blue)}

/* all buttons: solid blue (Ban keeps its red from the inline style) */
.dash .d-actions .btn,.dash .d-actions button,.dash .mj-card .btn,
.dash .grid>.card>.btn,.dash .tw button,.dash .d-search button{
  background:var(--blue);color:#fff;border:0;border-radius:8px;box-shadow:none;filter:none}
.dash .d-actions .btn:hover,.dash .d-actions button:hover,.dash .mj-card .btn:hover,
.dash .grid>.card>.btn:hover,.dash .tw button:hover,.dash .d-search button:hover{
  background:var(--blue-dark);color:#fff;filter:none;transform:translateY(-1px);
  box-shadow:0 6px 14px -6px rgba(37,99,235,.6)}
.dash .tw button{padding:6px 12px;font-size:13px;font-weight:600;cursor:pointer}

/* inputs */
.dash .d-search input[name=q],.dash .d-search select,.dash .tw input{border:1px solid #d5dbe8;background:#fff}
.dash input:focus,.dash select:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(37,99,235,.18)}

/* admin stats + bars */
.dash .stat{background:none;-webkit-text-fill-color:currentColor;color:var(--blue)}
.dash .bar{background:var(--blue)!important}

/* tables: light header, soft rows */
.dash .tw{border:1px solid var(--line);box-shadow:0 1px 3px rgba(15,23,42,.05)}
.dash th{background:#f1f5f9;color:#475569;font-size:12px;text-transform:uppercase;letter-spacing:.04em}
.dash td{border-bottom:1px solid #eef1f6}
.dash tr:hover td,.dash tr.rv.in:hover td{background:#f8fafc}

/* dark mode */
body.dark .dash .card,body.dark .dash table,body.dark .dash .mj-empty{background:#1f2233;border-color:#2e3350;color:#dfe2f5}
body.dark .dash .tw{border-color:#2e3350}
body.dark .dash th{background:#2a2e47;color:#cbd5e1}
body.dark .dash td{border-color:#2e3350}
body.dark .dash tr:hover td,body.dark .dash tr.rv.in:hover td{background:#2a2e47}
body.dark .dash h3,body.dark .dash .mj-title,body.dark .dash .mj-meta b{color:#e2e8f0}
body.dark .dash .mj-cat{background:#1e2a4a;color:#93b4ff}

@keyframes dRise{from{opacity:0;transform:translateY(24px)}to{opacity:1;transform:none}}
@keyframes dDrop{from{opacity:0;transform:translateY(-22px)}to{opacity:1;transform:none}}
@keyframes dGrow{from{width:0}to{width:56px}}
@keyframes dFlow{0%{background-position:0% 50%}50%{background-position:100% 50%}100%{background-position:0% 50%}}
@keyframes dPulse{0%,100%{transform:scale(1)}50%{transform:scale(1.2)}}
@keyframes dBar{from{transform:scaleX(0)}to{transform:scaleX(1)}}
@keyframes dEmg{0%,100%{box-shadow:0 0 0 0 rgba(204,0,0,.55)}50%{box-shadow:0 0 0 8px rgba(204,0,0,0)}}
@keyframes dRipple{to{transform:scale(4);opacity:0}}
@keyframes mjDot{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.45;transform:scale(1.5)}}
@keyframes mjFloat{0%,100%{transform:translateY(0)}50%{transform:translateY(-7px)}}

@media (prefers-reduced-motion:reduce){
  .dash *,.dash *::before,.dash *::after{animation-duration:.01ms!important;animation-iteration-count:1!important;transition-duration:.01ms!important}
  .dash .rv{opacity:1;transform:none}
}
@media (max-width:600px){
  .dash>h2:first-of-type{padding:18px}
  .dash .d-actions .btn,.dash .d-actions button{flex:1 1 calc(50% - 10px);padding:0 10px}
  .dash .d-search select{flex:1 1 120px;width:auto}
  .dash .d-search button{flex:1 1 100%}
}

/* ===== Welcome banner: font, name, verified badge ===== */
.dash>h2:first-of-type{display:flex;align-items:center;flex-wrap:wrap;gap:8px 14px;font-family:"Poppins","Inter",system-ui,sans-serif;
  font-weight:600;font-size:clamp(20px,3.2vw,30px);letter-spacing:-.01em;line-height:1.25}
.dash .wl-hi{font-weight:400;opacity:.92}
.dash .wl-name{font-weight:700;font-size:1.1em;background:linear-gradient(90deg,#fff,#bae6fd,#fff);background-size:200% 100%;
  -webkit-background-clip:text;background-clip:text;color:transparent;-webkit-text-fill-color:transparent;animation:dFlow 6s ease infinite}
.dash>h2 .ok{display:inline-flex;align-items:center;gap:4px;padding:4px 12px;border-radius:999px;background:#fff!important;color:#0369a1!important;
  font:600 12px/1 "Inter",system-ui,sans-serif;box-shadow:0 4px 12px rgba(3,105,161,.25);-webkit-text-fill-color:#0369a1}
.dash>h2 .ok .mi-xs{font-size:16px;color:#0ea5e9}
.dash .wl-role{font:600 11px/1 "Inter",system-ui,sans-serif;letter-spacing:.1em;text-transform:uppercase;padding:5px 11px;border-radius:999px;
  background:rgba(255,255,255,.2);border:1px solid rgba(255,255,255,.42)}

/* ===== Sky-blue buttons, smaller ===== */
.dash .d-actions .btn,.dash .d-actions button,.dash .mj-card .btn,.dash .grid>.card>.btn{
  background:linear-gradient(180deg,#38bdf8,#0284c7);color:#fff;border:1px solid #0369a1;
  box-shadow:inset 0 1px 0 rgba(255,255,255,.35),0 4px 10px -4px rgba(2,132,199,.55)}
.dash .d-actions .btn,.dash .d-actions button{height:36px;padding:0 13px;font-size:13px;font-weight:600;gap:5px;border-radius:9px}
.dash .d-actions .mi,.dash .d-actions .mi-xs{font-size:17px}
.dash .mj-card .btn{height:32px;padding:0 12px;font-size:12.5px}
.dash .d-actions .btn:hover,.dash .d-actions button:hover{box-shadow:0 8px 16px -6px rgba(2,132,199,.6)}

/* worker options row: always one line (scrolls sideways on small screens) */
.dash .d-one{flex-wrap:nowrap;overflow-x:auto;gap:8px;padding:6px 2px 12px;scrollbar-width:thin}
.dash .d-one .btn,.dash .d-one button{flex:0 0 auto}
.dash .d-one form{flex:0 0 auto}

/* ===== Welcome small, worker name big + stylish ===== */
.dash .wl-hi{font-size:clamp(13px,1.6vw,16px);font-weight:500;letter-spacing:.05em;text-transform:uppercase;opacity:.95}
.dash .wl-name{font-family:"Dancing Script","Poppins",cursive;font-size:clamp(34px,5.2vw,50px);font-weight:700;line-height:1.1;letter-spacing:0;
  filter:drop-shadow(0 2px 8px rgba(7,89,133,.35))}

/* ===== Blue page theme ===== */
body:not(.dark),body:not(.dark) main{background:linear-gradient(160deg,#e0f2fe 0%,#f0f9ff 45%,#dbeafe 100%) fixed!important}
.dash h3{color:#0c4a6e}
.dash h3 .mi{color:#0284c7}
.dash .card{border:1px solid #bae6fd;box-shadow:0 10px 24px -16px rgba(2,132,199,.5)}
.dash .grid>.card:hover{border-color:#7dd3fc;box-shadow:0 18px 36px -14px rgba(2,132,199,.35)}
.dash .d-search input[name=q],.dash .d-search select{border:1px solid #bae6fd;background:#fff}
.dash .mj-title{color:#0c4a6e}
.dash .mj-foot{border-top-color:#bae6fd}



/* ===== Clean stat boxes + click highlight ===== */
.dash .st-card{display:block;text-align:left;padding:18px 20px;cursor:pointer;user-select:none;
  background:#fff;border:1px solid var(--line,#e5e9f2);border-radius:14px;color:#64748b;font-size:14px;font-weight:500;
  transition:border-color .2s,box-shadow .2s,background .2s,transform .2s}
.dash .st-card .material-icons{font-size:18px;vertical-align:-4px;margin-right:4px;color:#2563eb}

/* remove the inner box that style.css gives every h2 */
.dash .st-card .stat{display:block;position:static;width:auto;min-height:0;margin:10px 0 0;padding:0;
  background:none;border:0;border-top:0;border-radius:0;box-shadow:none;transform:none;
  font-size:clamp(26px,3.4vw,34px);font-weight:700;line-height:1.1;color:#1e293b;
  -webkit-text-fill-color:currentColor}

.dash .st-card:hover{border-color:#bfdbfe;box-shadow:0 8px 20px -12px rgba(37,99,235,.35)}
.dash .st-card:focus-visible{outline:3px solid rgba(37,99,235,.35);outline-offset:2px}

/* selected (clicked) box */
.dash .st-card.sel{background:#eff6ff;border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.18),0 10px 22px -12px rgba(37,99,235,.45)}
.dash .st-card.sel::before{transform:scaleY(1);background:#2563eb}
.dash .st-card.sel .stat{color:#2563eb}

body.dark .dash .st-card{background:#1f2233;border-color:#2e3350;color:#cbd5e1}
body.dark .dash .st-card .stat{color:#e2e8f0}
body.dark .dash .st-card.sel{background:#1e2a4a;border-color:#3b82f6}
body.dark .dash .st-card.sel .stat{color:#93b4ff}

</style>
<?php
echo '<div class="dash">';
$wkV = 0;
if ($u["role"] == "worker") {
    $mv = $pdo->prepare("SELECT verified FROM users WHERE id=?");
    $mv->execute([$u["id"]]);
    $wkV = $mv->fetchColumn();
}
echo '<h2><span class="wl-hi">Welcome,</span> <span class="wl-name">' .
    e($u["name"]) .
    "</span>" .
    ($wkV ? " " . badge($wkV) : "") .
    ' <span class="wl-role">' .
    e($u["role"]) .
    "</span></h2>";
function jobs($rows, $mj = false)
{
    // New "My Jobs" card design (only when $mj is true)
    if ($mj) {
        if (!$rows) {
            echo '<div class="mj-empty"><span class="material-icons mi">work_off</span><p>No jobs yet.</p></div>';
            return;
        }
        foreach ($rows as $j) {
            $sc = preg_replace("/[^a-z0-9]/", "", strtolower((string) $j["status"]));
            $ts = strtotime($j["created"] ?? "");
            echo '<div class="card mj-card">' .
                ($j["urgent"]
                    ? '<span class="mj-sos"><span class="material-icons mi">emergency</span> EMERGENCY</span>'
                    : "") .
                '<div class="mj-top"><p class="mj-title">' .
                e($j["title"]) .
                '</p><span class="mj-status s-' .
                $sc .
                '">' .
                e($j["status"]) .
                "</span></div>" .
                '<span class="mj-cat">' .
                e($j["category"]) .
                "</span>" .
                '<div class="mj-meta"><span class="material-icons mi">place</span> ' .
                e($j["location"]) .
                " · <b>" .
                e($j["amount"]) .
                "</b></div>" .
                ($ts
                    ? '<div class="mj-meta"><span class="material-icons mi">schedule</span> ' .
                        date("d M, h:i A", $ts) .
                        "</div>"
                    : "") .
                '<div class="mj-foot"><span class="mj-id">Job #' .
                (int) $j["id"] .
                '</span><a class="btn" href="job.php?id=' .
                $j["id"] .
                '">Open <span class="material-icons mi mj-arrow">arrow_forward</span></a></div></div>';
        }
        return;
    }

    // Original cards (unchanged) for every other section
    foreach ($rows as $j) {
        echo '<div class="card">' .
            ($j["urgent"]
                ? '<span class="emg" style="background:#c00;color:#fff;padding:2px 8px;border-radius:10px;font-size:12px"><span class="material-icons mi">emergency</span> EMERGENCY</span><br>'
                : "") .
            "<b>" .
            e($j["title"]) .
            "</b> – " .
            e($j["category"]) .
            '<br><span class="material-icons mi">place</span> ' .
            e($j["location"]) .
            " · " .
            e($j["amount"]) .
            "<br>Status: <b>" .
            e($j["status"]) .
            '</b><br><br><a class="btn" href="job.php?id=' .
            $j["id"] .
            '">Open</a></div>';
    }
}
if ($u["role"] == "customer") {
    echo '<div class="d-actions"><a class="btn" href="post_job.php"><span class="material-icons mi">add_circle</span> Post New Job</a><a class="btn" href="match.php"><span class="material-icons mi">gps_fixed</span> Smart Match</a><a class="btn" href="addresses.php"><span class="material-icons mi">place</span> Addresses</a><a class="btn" href="leaderboard.php"><span class="material-icons mi">emoji_events</span> Top workers</a></div><h3><span class="material-icons mi">favorite</span> Favorite Workers</h3><div class="grid">';
    $fv = $pdo->prepare(
        "SELECT u.* FROM favorites f JOIN users u ON u.id=f.worker_id WHERE f.customer_id=?",
    );
    $fv->execute([$u["id"]]);
    foreach ($fv as $w) {
        echo '<div class="card"><b>' .
            e($w["name"]) .
            "</b> " .
            badge($w["verified"]) .
            "<br>" .
            e($w["skill"]) .
            '<br><br><a class="btn" href="worker.php?id=' .
            $w["id"] .
            '">View</a></div>';
    }
    echo '</div><h3>My Jobs</h3><div class="grid">';
    $s = $pdo->prepare(
        "SELECT * FROM jobs WHERE customer_id=? ORDER BY id DESC",
    );
    $s->execute([$u["id"]]);
    jobs($s->fetchAll(), true);
    echo "</div>";
} elseif ($u["role"] == "worker") {
    echo '<div class="d-actions d-one">' .
        '<a class="btn" href="schedule.php"><span class="material-icons mi">calendar_today</span> My Schedule</a>' .
        '<a class="btn" href="my_upi.php"><span class="material-icons mi">credit_card</span> ' .
        ($u["upi_id"]
            ? "My UPI ID"
            : 'Add UPI ID <span class="material-icons mi-xs">warning</span>') .
        "</a>" .
        '<a class="btn" href="upi_confirm.php"><span class="material-icons mi">smartphone</span> UPI confirmations</a>' .
        '<a class="btn" href="certificates.php"><span class="material-icons mi">school</span> Certificates</a>' .
        '<a class="btn" href="heatmap.php"><span class="material-icons mi">local_fire_department</span> Demand map</a>' .
        '<a class="btn" href="leaderboard.php"><span class="material-icons mi">emoji_events</span> Top workers</a>' .
        '<form method="post"><button name="alerts" value="1">' .
        ($u["job_alerts"]
            ? '<span class="material-icons mi">notifications</span> New-job alerts: ON'
            : '<span class="material-icons mi">notifications_off</span> New-job alerts: OFF') .
        "</button></form></div>";
    echo '<h3><span class="material-icons mi">lock_open</span> Open Jobs</h3><form method="get" class="d-search"><input name="q" placeholder="Search title or location" value="' .
        e($_GET["q"] ?? "") .
        '"><select name="cat"><option value="">All categories</option>';
    foreach ($CATS as $c) {
        echo "<option" .
            (($_GET["cat"] ?? "") == $c ? " selected" : "") .
            ">" .
            $c .
            "</option>";
    }
    echo '</select><button><span class="material-icons mi">search</span> Search</button></form><div class="grid">';
    $k = "%" . ($_GET["q"] ?? "") . "%";
    $c = $_GET["cat"] ?? "";
    $s = $pdo->prepare(
        "SELECT * FROM jobs WHERE status='open' AND (title LIKE ? OR location LIKE ?) AND (?='' OR category=?) ORDER BY urgent DESC,(category=?) DESC,id DESC LIMIT 50",
    );
    $s->execute([$k, $k, $c, $c, $u["skill"]]);
    jobs($s->fetchAll());
    echo "</div>";
    echo '<h3>My Assigned Jobs</h3><div class="grid">';
    $s = $pdo->prepare("SELECT * FROM jobs WHERE worker_id=? ORDER BY id DESC");
    $s->execute([$u["id"]]);
    jobs($s->fetchAll(), true);
    echo "</div>";
} else {
    $c = fn($q) => $pdo->query($q)->fetchColumn();
    echo '<h3><span class="material-icons mi">analytics</span> Analytics</h3><div class="grid"><div class="card"><span class="material-icons mi">people</span> Users<h2 class="stat">' .
        $c("SELECT COUNT(*) FROM users") .
        '</h2></div><div class="card"><span class="material-icons mi">engineering</span> Workers<h2 class="stat">' .
        $c("SELECT COUNT(*) FROM users WHERE role='worker'") .
        '</h2></div><div class="card"><span class="material-icons mi">assignment</span> Jobs<h2 class="stat">' .
        $c("SELECT COUNT(*) FROM jobs") .
        '</h2></div><div class="card"><span class="material-icons mi-xs">check_circle</span> Completed<h2 class="stat">' .
        $c("SELECT COUNT(*) FROM jobs WHERE status='completed'") .
        '</h2></div><div class="card"><span class="material-icons mi">payments</span> Income<h2 class="stat">₹' .
        (int) $c("SELECT SUM(price) FROM jobs WHERE paid=1") .
        '</h2></div><div class="card"><span class="material-icons mi">account_balance</span> Platform fee (10%)<h2 class="stat">₹' .
        (int) $c("SELECT SUM(commission) FROM jobs WHERE paid=1") .
        '</h2></div></div><div class="card"><b>Jobs by category</b>';
    foreach (
        $pdo->query("SELECT category,COUNT(*) n FROM jobs GROUP BY category")
        as $x
    ) {
        echo "<div>" .
            e($x["category"]) .
            ' <span class="bar" style="display:inline-block;background:#0F62FE;height:12px;width:' .
            $x["n"] * 30 .
            'px;max-width:60%"></span> ' .
            $x["n"] .
            "</div>";
    }
    echo '</div><div class="d-actions" style="margin-top:14px"><a class="btn" href="export.php?t=users"><span class="material-icons mi-xs">arrow_downward</span> Users CSV</a><a class="btn" href="export.php?t=jobs"><span class="material-icons mi-xs">arrow_downward</span> Jobs CSV</a><a class="btn" href="export.php?t=payments"><span class="material-icons mi-xs">arrow_downward</span> Payments CSV</a><a class="btn" href="charts.php"><span class="material-icons mi">trending_up</span> Charts</a><a class="btn" href="announcements.php"><span class="material-icons mi">campaign</span> Announcements</a><a class="btn" href="qr_payments.php"><span class="material-icons mi">smartphone</span> QR payments</a><a class="btn" href="settlements.php"><span class="material-icons mi">attach_money</span> Settlements</a><a class="btn" href="login_history.php"><span class="material-icons mi">lock</span> Login history</a><a class="btn" href="my_upi.php"><span class="material-icons mi">credit_card</span> My UPI ID</a><a class="btn" href="disputes.php"><span class="material-icons mi">balance</span> Disputes</a><a class="btn" href="certificates.php"><span class="material-icons mi">school</span> Certificates</a><a class="btn" href="heatmap.php"><span class="material-icons mi">local_fire_department</span> Demand map</a></div><h3>Users</h3><div class="tw"><table><tr><th>Name</th><th>Email</th><th>Role</th><th>Skill</th><th>Verified</th><th>Suspend</th></tr>';
    foreach ($pdo->query("SELECT * FROM users ORDER BY id DESC") as $x) {
        echo "<tr><td>" .
            e($x["name"]) .
            "</td><td>" .
            e($x["email"]) .
            "</td><td>" .
            $x["role"] .
            "</td><td>" .
            e($x["skill"]) .
            "</td><td>";
        if ($x["role"] == "worker") {
            echo '<form method="post"><input type="hidden" name="v" value="' .
                $x["id"] .
                '"><button>' .
                ($x["verified"] ? "Remove badge" : "Verify") .
                "</button></form>";
        }
        echo "</td><td>";
        if ($x["role"] != "admin") {
            echo '<form method="post"><input type="hidden" name="ban" value="' .
                $x["id"] .
                '">' .
                ($x["banned"]
                    ? '<b class="err">Suspended</b> <button>Unban</button>'
                    : '<input name="why" placeholder="Reason" required><button style="background:#c00">Ban</button>') .
                "</form>";
        }
        echo "</td></tr>";
    }
    echo '</table></div><h3>All Jobs</h3><div class="grid">';
    jobs($pdo->query("SELECT * FROM jobs ORDER BY id DESC")->fetchAll());
    echo "</div>";
}
echo '</div>';
?>
<script>
(function () {
  var root = document.querySelector(".dash");
  if (!root) return;
  var reduce = window.matchMedia && matchMedia("(prefers-reduced-motion: reduce)").matches;

  // Scroll reveal for cards and table rows, staggered inside each group
  if (!reduce && "IntersectionObserver" in window) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (en.isIntersecting) { en.target.classList.add("in"); io.unobserve(en.target); }
      });
    }, { threshold: 0.08 });
    root.querySelectorAll(".grid, .tw table").forEach(function (group) {
      var items = group.matches(".grid") ? group.children : group.querySelectorAll("tr:not(:first-child)");
      Array.prototype.forEach.call(items, function (el, i) {
        el.classList.add("rv");
        el.style.setProperty("--d", Math.min(i * 0.06, 0.6) + "s");
        io.observe(el);
      });
    });
    root.querySelectorAll(":scope > .card").forEach(function (el) { el.classList.add("rv"); io.observe(el); });
  }

  // Count-up for admin analytics numbers
  if (!reduce) {
    root.querySelectorAll(".stat").forEach(function (el) {
      var m = el.textContent.trim().match(/^(\D*)(\d+)$/);
      if (!m) return;
      var pre = m[1], target = parseInt(m[2], 10), t0 = null, dur = 1100;
      el.textContent = pre + "0";
      function tick(ts) {
        if (t0 === null) t0 = ts;
        var p = Math.min((ts - t0) / dur, 1);
        el.textContent = pre + Math.round(target * (1 - Math.pow(1 - p, 3)));
        if (p < 1) requestAnimationFrame(tick);
      }
      requestAnimationFrame(tick);
    });
  }

  // Ripple on buttons and links (does not block navigation or submit)
  root.addEventListener("click", function (e) {
    var b = e.target.closest(".btn, button");
    if (!b || reduce) return;
    var r = b.getBoundingClientRect(), d = Math.max(r.width, r.height);
    var s = document.createElement("span");
    s.className = "d-ripple";
    s.style.width = s.style.height = d + "px";
    s.style.left = (e.clientX - r.left - d / 2) + "px";
    s.style.top = (e.clientY - r.top - d / 2) + "px";
    b.appendChild(s);
    setTimeout(function () { s.remove(); }, 600);
  });
})();

(function(){
  var cards=[].slice.call(document.querySelectorAll(".dash .grid > .card")).filter(function(c){return c.querySelector(".stat")});
  function pick(c){
    var was=c.classList.contains("sel");
    cards.forEach(function(x){x.classList.remove("sel");x.setAttribute("aria-pressed","false")});
    if(!was){c.classList.add("sel");c.setAttribute("aria-pressed","true")}
  }
  cards.forEach(function(c){
    c.classList.add("st-card");
    c.tabIndex=0;
    c.setAttribute("role","button");
    c.setAttribute("aria-pressed","false");
    c.addEventListener("click",function(){pick(c)});
    c.addEventListener("keydown",function(e){if(e.key==="Enter"||e.key===" "){e.preventDefault();pick(c)}});
  });
})();

</script>
<?php foot();
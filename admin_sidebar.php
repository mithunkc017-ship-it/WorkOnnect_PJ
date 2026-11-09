<?php // Admin Tools: ONE button in the main sidebar opens a square popup with all admin options. Used by head()/foot() in config.php
function adm_show()
{
    $u = me();
    if (!$u || $u["role"] != "admin") {
        return false;
    }
    return !in_array(basename($_SERVER["SCRIPT_NAME"]), ["index.php", "login.php", "register.php"]);
}
function adm_side_open()
{
    if (!adm_show()) {
        return "";
    }
    $cur = basename($_SERVER["SCRIPT_NAME"]);
    $G = [
        "Main" => [
            ["dash.php", "dashboard", "Overview"],
            ["dashboard.php", "tune", "Manage"],
            ["analytics.php", "analytics", "Analytics"],
            ["charts.php", "trending_up", "Charts"],
            ["heatmap.php", "local_fire_department", "Demand map"],
        ],
        "Money" => [
            ["qr_payments.php", "payments", "Payments"],
            ["settlements.php", "attach_money", "Settlements"],
            ["coupons.php", "sell", "Coupons"],
            ["disputes.php", "balance", "Disputes"],
        ],
        "Manage" => [
            ["categories.php", "category", "Categories"],
            ["verifications.php", "fact_check", "Verifications"],
            ["certificates.php", "school", "Certificates"],
            ["complaints.php", "report", "Complaints"],
            ["announcements.php", "campaign", "Announcements"],
        ],
        "System" => [
            ["log.php", "history", "Activity"],
            ["login_history.php", "lock", "Login history"],
            ["account_settings.php", "settings", "Settings"],
        ],
    ];
    $o = '<style>
.adm-ov{position:fixed;inset:0;z-index:900;background:rgba(15,18,40,.5);opacity:0;visibility:hidden;transition:opacity .25s,visibility .25s;
  display:flex;align-items:center;justify-content:center;padding:16px}
body.adm-open .adm-ov{opacity:1;visibility:visible}
.adm-box{width:min(640px,100%);max-height:88vh;overflow-y:auto;padding:20px;border-radius:24px;background:#fff;box-shadow:0 30px 70px rgba(0,0,0,.35);
  transform:scale(.9) translateY(14px);opacity:0;transition:transform .3s cubic-bezier(.2,.8,.2,1),opacity .25s}
body.adm-open .adm-box{transform:none;opacity:1}
.adm-hd{display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;padding-bottom:10px;border-bottom:3px solid #4f46e5;font-weight:700;font-size:18px;color:#2b2f45}
.adm-hd button{border:0;background:#f0efff;color:#4f46e5;width:34px;height:34px;border-radius:50%;cursor:pointer;font-size:20px;line-height:1}
.adm-hd button:hover{background:#4f46e5;color:#fff}
.adm-box h4{margin:16px 2px 8px;font-size:12px;color:#8a8fa3;font-weight:600}
.adm-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(110px,1fr));gap:10px}
.adm-t{aspect-ratio:1/1;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;padding:8px;border-radius:16px;background:#f6f7fb;
  color:#2b2f45;text-decoration:none;font-size:13px;font-weight:600;text-align:center;border:2px solid transparent;
  transition:transform .2s,background .2s,box-shadow .2s,border-color .2s}
.adm-t .material-icons{font-size:28px;color:#4f46e5;width:46px;height:46px;display:grid;place-items:center;border-radius:14px;background:#fff;box-shadow:0 4px 10px rgba(79,70,229,.15);
  transition:transform .25s,background .2s,color .2s}
.adm-t:hover{transform:translateY(-4px);background:#eeedff;border-color:#c9c5ff;box-shadow:0 10px 20px rgba(79,70,229,.18)}
.adm-t:hover .material-icons{transform:scale(1.1) rotate(-6deg);background:#4f46e5;color:#fff}
.adm-t.on{background:#4f46e5;color:#fff}
.adm-t.on .material-icons{background:rgba(255,255,255,.2);color:#fff;box-shadow:none}
body.dark .adm-box{background:#1f2233}
body.dark .adm-hd,body.dark .adm-t{color:#dfe2f5}
body.dark .adm-t{background:#2a2e47}
body.dark .adm-t .material-icons{background:#1f2233}
@media (prefers-reduced-motion:reduce){.adm-ov,.adm-box,.adm-t,.adm-t .material-icons{transition:none!important}}
</style><div class="adm-ov" id="admOv"><div class="adm-box" role="dialog" aria-label="Admin tools"><div class="adm-hd"><span>Admin Tools</span><button type="button" id="admX" aria-label="Close">&times;</button></div>';
    foreach ($G as $title => $L) {
        $o .= "<h4>" . e($title) . '</h4><div class="adm-grid">';
        foreach ($L as $l) {
            $o .=
                '<a class="adm-t' . ($l[0] == $cur ? " on" : "") . '" href="' . $l[0] .
                '"><span class="material-icons">' . $l[1] . "</span>" . e($l[2]) . "</a>";
        }
        $o .= "</div>";
    }
    return $o . '</div></div><script>(function(){var b=document.body,btn=document.getElementById("admBtn");function o(v){b.classList.toggle("adm-open",v)}if(btn)btn.onclick=function(e){e.preventDefault();o(!b.classList.contains("adm-open"))};document.getElementById("admX").onclick=function(){o(false)};document.getElementById("admOv").onclick=function(e){if(e.target===this)o(false)};document.addEventListener("keydown",function(e){if(e.key=="Escape")o(false)})})()</script>';
}
function adm_side_close()
{
    return "";
}
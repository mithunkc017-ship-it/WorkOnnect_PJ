<?php
// Notification helpers + bell script. Loaded from config.php with require_once (see bottom of config.php).
// No business logic here: it only formats and displays notifications.

// Split a stored message into icon name + clean text.
function notif_parts($m)
{
    $m = (string) $m;
    $icon = "notifications";
    if (preg_match('/<span class="material-icons[^"]*">([a-z0-9_]+)<\/span>/i', $m, $x)) {
        $icon = $x[1];
    }
    $text = preg_replace('/<span class="material-icons[^"]*">[^<]*<\/span>/i', "", $m);
    $text = trim(html_entity_decode(strip_tags($text), ENT_QUOTES, "UTF-8"));
    return ["icon" => $icon, "text" => $text];
}

// "5 min ago" style time.
function time_ago($t)
{
    $ts = strtotime((string) $t);
    if (!$ts) {
        return "";
    }
    $d = time() - $ts;
    if ($d < 60) {
        return "Just now";
    }
    if ($d < 3600) {
        return floor($d / 60) . " min ago";
    }
    if ($d < 86400) {
        return floor($d / 3600) . " h ago";
    }
    if ($d < 604800) {
        return floor($d / 86400) . " d ago";
    }
    return date("d M Y", $ts);
}

// Bell dropdown, unread badge, toast, 15 s polling. Printed by foot().
function notif_script()
{
    $T = json_encode(
        [
            "title" => t("Notifications"),
            "mark" => t("Mark all read"),
            "none" => t("No notifications yet"),
            "all" => t("View all notifications"),
        ],
        JSON_UNESCAPED_UNICODE,
    );
    echo "<script>var NT=" . $T . ";var CS=" . json_encode($_SESSION["csrf"] ?? "") . ";</script>";
    echo <<<'JS'
<style>
#np{position:fixed;z-index:100000;right:auto;background:#fff;color:#1f2937;border-radius:14px;box-shadow:0 12px 40px rgba(0,0,0,.22);overflow:hidden;display:flex;flex-direction:column;border:1px solid #e5e7eb;text-align:left;font-family:inherit}
#np[hidden]{display:none!important}
#bc[hidden]{display:none!important}
#np .nh{display:flex;justify-content:space-between;align-items:center;padding:12px 16px;border-bottom:1px solid #eef0f3;font-size:15px}
#np .nh a{font-size:12px;color:#2f5bff;text-decoration:none}
#np .nl2{overflow-y:auto;flex:1}
#np .ni{display:flex;gap:12px;align-items:flex-start;padding:12px 16px;text-decoration:none;color:inherit;border-bottom:1px solid #f3f4f6;position:relative}
#np .ni:hover{background:#f6f8ff}
#np .ni.new{background:#eef3ff}
#np .ni.new:after{content:"";position:absolute;right:14px;top:18px;width:8px;height:8px;border-radius:50%;background:#2f5bff}
#np .nic{flex:none;width:36px;height:36px;border-radius:50%;background:#e8efff;color:#2f5bff;display:flex;align-items:center;justify-content:center}
#np .nic .material-icons{font-size:20px;color:#2f5bff}
#np .nbd{display:flex;flex-direction:column;gap:2px;min-width:0;padding-right:14px}
#np .ntx{font-size:13.5px;line-height:1.35;word-break:break-word}
#np .ntm{color:#6b7280;font-size:11.5px}
#np .nv{display:block;text-align:center;padding:11px;font-size:13px;color:#2f5bff;text-decoration:none;border-top:1px solid #eef0f3;font-weight:600}
#np .ne{padding:30px 16px;text-align:center;color:#6b7280;font-size:14px}
body.dark #np{background:#1e2330;color:#e5e7eb;border-color:#2c3342}
body.dark #np .ni.new{background:#26304a}
body.dark #np .ni:hover{background:#2a3350}
body.dark #np .ni,body.dark #np .nh,body.dark #np .nv{border-color:#2c3342}
.toast{position:fixed;right:18px;bottom:80px;z-index:100001;background:#1f2937;color:#fff;padding:12px 16px;border-radius:12px;box-shadow:0 8px 24px rgba(0,0,0,.3);text-decoration:none;max-width:320px;font-size:14px;animation:tin .3s ease}
.toast .material-icons{font-size:18px;vertical-align:middle;color:#fff}
@keyframes tin{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:none}}
#bell.ring .material-icons,#bell.ring svg{animation:rg 1s ease 1}
@keyframes rg{0%,100%{transform:rotate(0)}20%{transform:rotate(14deg)}40%{transform:rotate(-12deg)}60%{transform:rotate(8deg)}80%{transform:rotate(-6deg)}}
</style>
<script>
(function(){
try{
var bell=document.getElementById("bell");
if(!bell)return;
function mk(t,c,x){var e=document.createElement(t);if(c)e.className=c;if(x!=null)e.textContent=x;return e}
function ic(n){return mk("span","material-icons mi",n||"notifications")}

/* Make sure the badge and the panel exist (some pages rebuild the header) */
var badge=document.getElementById("bc");
if(!badge){badge=mk("b","","0");badge.id="bc";badge.hidden=true;var la=bell.querySelector("a");(la||bell).appendChild(badge)}
var panel=document.getElementById("np");
if(!panel){panel=mk("div");panel.id="np";panel.hidden=true}
/* Move the panel to <body>: sidebars / sticky headers / transforms can no longer clip it */
if(panel.parentNode!==document.body)document.body.appendChild(panel);
var last=parseInt(badge.textContent,10)||0;

function row(i){
  var a=mk("a","ni"+(i.seen==1?"":" new"));a.href=i.link||"notifications.php";
  var b=mk("span","nic");b.appendChild(ic(i.icon));a.appendChild(b);
  var w=mk("span","nbd");w.appendChild(mk("span","ntx",i.text||i.msg||""));w.appendChild(mk("small","ntm",i.ago||""));
  a.appendChild(w);return a}
function toast(i){
  try{if(window.Notification&&Notification.permission=="granted"&&document.hidden)new Notification("Workonnect",{body:i.text||i.msg,icon:"icons/icon-192.png"})}catch(e){}
  var t=mk("a","toast");t.href=i.link||"notifications.php";t.appendChild(ic(i.icon));
  t.appendChild(document.createTextNode(" "+(i.text||i.msg||"")));document.body.appendChild(t);
  setTimeout(function(){t.remove()},6000)}
function place(){
  var trg=bell.querySelector("a")||bell,r=trg.getBoundingClientRect();
  var w=Math.min(360,innerWidth-16),side=r.left<innerWidth/2&&innerWidth>800;
  var left=side?r.right+8:r.right-w;left=Math.max(8,Math.min(left,innerWidth-w-8));
  var top=side?Math.max(8,r.top-8):r.bottom+8;
  panel.style.width=w+"px";panel.style.left=left+"px";panel.style.right="auto";panel.style.top=top+"px";
  panel.style.maxHeight=Math.max(200,innerHeight-top-12)+"px"}

window.loadN=function(){
  fetch("notif.php",{credentials:"same-origin"}).then(function(r){return r.json()}).then(function(d){
    badge.textContent=d.unread>99?"99+":d.unread;badge.hidden=!d.unread;bell.classList.toggle("ring",d.unread>0);
    panel.innerHTML="";
    var h=mk("div","nh");h.appendChild(mk("b","",NT.title));
    var m=mk("a","",NT.mark);m.href="#";
    m.onclick=function(){fetch("notif.php",{method:"POST",credentials:"same-origin",body:new URLSearchParams({csrf:CS})}).then(window.loadN);return false};
    h.appendChild(m);panel.appendChild(h);
    var l=mk("div","nl2");
    if(!d.items.length)l.appendChild(mk("div","ne",NT.none));
    d.items.forEach(function(i){l.appendChild(row(i))});
    panel.appendChild(l);
    var v=mk("a","nv",NT.all);v.href="notifications.php";panel.appendChild(v);
    if(d.unread>last&&d.items[0])toast(d.items[0]);
    last=d.unread;
    if(!panel.hidden)place();
  }).catch(function(){})};

window.tb=function(){
  try{if(window.Notification&&Notification.permission=="default")Notification.requestPermission()}catch(e){}
  panel.hidden=!panel.hidden;
  if(!panel.hidden){place();window.loadN()}
  return false};

/* The bell link: works even if its inline onclick is missing */
var bl=bell.querySelector("a");
if(bl&&!bl.getAttribute("onclick"))bl.addEventListener("click",function(e){e.preventDefault();window.tb()});

document.addEventListener("click",function(e){if(!bell.contains(e.target)&&!panel.contains(e.target))panel.hidden=true});
document.addEventListener("keydown",function(e){if(e.key=="Escape")panel.hidden=true});
addEventListener("resize",function(){if(!panel.hidden)place()});
addEventListener("scroll",function(){if(!panel.hidden)place()},true);
window.loadN();setInterval(window.loadN,15000);
}catch(err){console.error("Notifications:",err)}
})();
</script>
JS;
}
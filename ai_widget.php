<?php // WorkOnnect AI widget (original design) - offline, no API. Included once from foot().
if (defined("WAI_LOADED")) { return; }
define("WAI_LOADED", 1);
if (!empty($GLOBALS["wk_auth"])) { return; }
$wu = function_exists("me") ? me() : null;
if (!$wu) { return; }
if (empty($_SESSION["ai_csrf"])) { $_SESSION["ai_csrf"] = bin2hex(random_bytes(16)); }
$wr = $wu["role"] == "worker" ? "worker" : ($wu["role"] == "customer" ? "customer" : "admin");
$wfirst = explode(" ", trim((string) $wu["name"]))[0];
?>
<div id="wai" data-csrf="<?= htmlspecialchars($_SESSION["ai_csrf"]) ?>" data-role="<?= $wr ?>" data-name="<?= htmlspecialchars($wfirst) ?>" data-uid="<?= (int) $wu["id"] ?>">
<style>
#wai{--b:#0a63d6;--b2:#2a9bff;--n:#0a3f9e;--ink:#0f1f3d;--mute:#5a6b8c;--line:#dbe6f6;--soft:#f3f8ff;--ok:#12a150;--red:#d62d28;font:500 14.5px/1.5 Inter,system-ui,-apple-system,"Segoe UI",sans-serif;color:var(--ink)}
#wai *{box-sizing:border-box;margin:0}
#wai button{font:inherit;cursor:pointer;border:0;background:none;color:inherit}
#wai svg{width:20px;height:20px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;flex:none}
.wai-fab{position:fixed;right:22px;bottom:22px;z-index:2147483000;width:62px;height:62px;border-radius:50%;color:#fff;display:grid;place-items:center;
  background:linear-gradient(135deg,#2a9bff,#0a3f9e);box-shadow:0 14px 30px -8px rgba(10,99,214,.7);transition:transform .25s cubic-bezier(.3,1.5,.5,1)}
.wai-fab::before{content:"";position:absolute;inset:0;border-radius:50%;border:2px solid var(--b2);animation:wping 2.6s infinite}
.wai-fab:hover{transform:scale(1.08) rotate(-6deg)}.wai-fab svg{width:28px;height:28px}
.wai-fab .tip{position:absolute;right:74px;white-space:nowrap;background:#fff;color:var(--ink);padding:8px 14px;border-radius:12px;font-weight:600;box-shadow:0 10px 24px -10px rgba(10,70,170,.5);animation:wtip 6s 1.5s both}
@keyframes wping{0%{transform:scale(1);opacity:.7}100%{transform:scale(1.55);opacity:0}}
@keyframes wtip{0%,100%{opacity:0;transform:translateX(10px)}10%,80%{opacity:1;transform:none}}
.wai-win{position:fixed;right:22px;bottom:100px;z-index:2147483001;width:410px;height:min(690px,calc(100vh - 120px));display:flex;flex-direction:column;background:#fff;border-radius:26px;overflow:hidden;
  box-shadow:0 30px 80px -20px rgba(10,50,140,.55),0 0 0 1px rgba(10,99,214,.08);transform-origin:bottom right;transform:scale(.6) translateY(40px);opacity:0;pointer-events:none;transition:transform .35s cubic-bezier(.2,.9,.3,1.1),opacity .25s}
#wai.open .wai-win{transform:none;opacity:1;pointer-events:auto}#wai.open .wai-fab .tip{display:none}
.wai-head{position:relative;display:flex;align-items:center;gap:12px;padding:16px 16px 18px;color:#fff;background:linear-gradient(120deg,#0a3f9e,#0a63d6 60%,#1e90ff);overflow:hidden}
.wai-head::after{content:"";position:absolute;right:-50px;top:-60px;width:180px;height:180px;border-radius:50%;background:rgba(255,255,255,.12)}
.wai-av{position:relative;width:44px;height:44px;border-radius:14px;display:grid;place-items:center;background:rgba(255,255,255,.2);border:1px solid rgba(255,255,255,.35)}
.wai-av i{position:absolute;right:-3px;bottom:-3px;width:12px;height:12px;border-radius:50%;background:#3ddc84;border:2px solid #0a63d6}
.wai-ht{flex:1;min-width:0;position:relative;z-index:1}.wai-ht b{display:block;font-size:1.05rem;letter-spacing:-.01em}.wai-ht small{opacity:.85;font-size:.76rem}
.wai-ib{position:relative;z-index:1;width:34px;height:34px;border-radius:10px;display:grid;place-items:center;transition:background .2s}.wai-ib:hover,.wai-ib.on{background:rgba(255,255,255,.22)}
.wai-bar{display:flex;gap:6px;padding:8px 14px;background:var(--soft);border-bottom:1px solid var(--line);align-items:center;font-size:.78rem;color:var(--mute)}
.wai-bar .lg{margin-left:auto;display:flex;gap:4px}
.wai-bar .lg button{padding:3px 10px;border-radius:99px;font-weight:700;font-size:.76rem;border:1px solid var(--line);background:#fff}
.wai-bar .lg button.on{background:var(--b);color:#fff;border-color:var(--b)}
.wai-msgs{flex:1;overflow-y:auto;padding:16px 14px;background:linear-gradient(#f7faff,#eef5ff);scroll-behavior:smooth;display:flex;flex-direction:column;gap:12px}
.wai-m{display:flex;flex-direction:column;gap:8px;max-width:92%;animation:wup .35s cubic-bezier(.2,.8,.2,1) both}
.wai-m.u{align-self:flex-end;align-items:flex-end}
.wai-b{position:relative;padding:11px 15px;border-radius:18px;word-wrap:break-word;white-space:pre-wrap}
.wai-b em{font-style:italic;opacity:.85}
.u .wai-b{background:linear-gradient(135deg,#2a9bff,#0a63d6);color:#fff;border-bottom-right-radius:5px;box-shadow:0 8px 18px -8px rgba(10,99,214,.6)}
.a .wai-b{background:#fff;border:1px solid var(--line);border-bottom-left-radius:5px;box-shadow:0 4px 14px -8px rgba(10,70,170,.25)}
.wai-b.err{background:#fdecea;border-color:#f5c2c0;color:var(--red)}
.wai-acts{display:flex;gap:4px;margin-top:-2px;opacity:.0;transition:opacity .2s}.wai-m:hover .wai-acts{opacity:1}
.wai-acts button{display:flex;align-items:center;gap:4px;font-size:.74rem;color:var(--mute);padding:3px 8px;border-radius:8px}.wai-acts button:hover{background:#fff}.wai-acts svg{width:14px;height:14px}
.wai-typing{display:flex;gap:5px;padding:14px 16px}.wai-typing i{width:8px;height:8px;border-radius:50%;background:var(--b2);animation:wdot 1.2s infinite}.wai-typing i:nth-child(2){animation-delay:.15s}.wai-typing i:nth-child(3){animation-delay:.3s}
@keyframes wdot{0%,60%,100%{transform:translateY(0);opacity:.4}30%{transform:translateY(-6px);opacity:1}}
@keyframes wup{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:none}}
.wai-chips{display:flex;flex-wrap:wrap;gap:8px}
.wai-chips button{padding:8px 14px;border-radius:99px;background:#fff;border:1px solid var(--line);color:var(--b);font-weight:600;font-size:.84rem;transition:all .2s;box-shadow:0 2px 8px -4px rgba(10,99,214,.3)}
.wai-chips button:hover{background:var(--b);color:#fff;transform:translateY(-2px)}
.wai-hello{text-align:center;padding:10px 6px 4px}.wai-hello .orb{width:64px;height:64px;margin:0 auto 10px;border-radius:20px;display:grid;place-items:center;color:#fff;background:linear-gradient(135deg,#2a9bff,#0a3f9e);box-shadow:0 14px 28px -10px rgba(10,99,214,.7);animation:wfloat 3.5s ease-in-out infinite}
.wai-hello .orb svg{width:30px;height:30px}.wai-hello h3{font-size:1.25rem;letter-spacing:-.02em}.wai-hello p{color:var(--mute);font-size:.88rem;margin-top:2px}
@keyframes wfloat{50%{transform:translateY(-6px)}}
/* cards */
.wai-card{background:#fff;border:1px solid var(--line);border-radius:16px;padding:12px;box-shadow:0 8px 22px -14px rgba(10,70,170,.4);animation:wup .45s both}
.wai-wk{display:flex;gap:12px}.wai-wk+.wai-wk{margin-top:12px;padding-top:12px;border-top:1px dashed var(--line)}
.wai-pic{width:52px;height:52px;border-radius:50%;object-fit:cover;display:grid;place-items:center;color:#fff;font-weight:800;font-size:1.2rem;background:linear-gradient(135deg,#2a9bff,#0a63d6);flex:none;overflow:hidden}
.wai-pic img{width:100%;height:100%;object-fit:cover}
.wai-wk b{display:flex;align-items:center;gap:6px}.wai-vf{color:var(--b);width:16px!important;height:16px!important}
.wai-st{color:#f5a623;font-size:.82rem;font-weight:700}.wai-st em{color:var(--mute);font-style:normal;font-weight:500}
.wai-tags{display:flex;flex-wrap:wrap;gap:5px;margin:6px 0}.wai-tags span{padding:2px 9px;border-radius:99px;background:var(--soft);border:1px solid var(--line);font-size:.74rem;font-weight:600}
.wai-tags .ok{background:#e3f7ec;border-color:#bfe9d1;color:var(--ok)}
.wai-btns{display:flex;gap:8px;flex-wrap:wrap}
.wai-btn{padding:8px 14px;border-radius:11px;font-weight:700;font-size:.82rem;text-decoration:none;display:inline-flex;align-items:center;gap:6px;transition:transform .15s,box-shadow .15s}
.wai-btn.p{background:linear-gradient(180deg,#2a9bff,#0a63d6);color:#fff;box-shadow:0 8px 16px -8px rgba(10,99,214,.7)}.wai-btn.g{background:var(--soft);color:var(--b);border:1px solid var(--line)}.wai-btn.r{background:#fdecea;color:var(--red)}
.wai-btn:hover{transform:translateY(-1px)}.wai-btn:disabled{opacity:.6;cursor:not-allowed;transform:none}
.wai-card h4{font-size:.95rem;margin-bottom:8px;display:flex;align-items:center;gap:8px}
.wai-row{display:flex;justify-content:space-between;gap:12px;padding:6px 0;border-bottom:1px solid var(--line);font-size:.88rem}.wai-row:last-of-type{border:0}.wai-row span{color:var(--mute)}
.wai-big{font-size:1.6rem;font-weight:800;color:var(--b);letter-spacing:-.03em}
.wai-note{font-size:.74rem;color:var(--mute);margin-top:6px}
.wai-bk{display:flex;justify-content:space-between;align-items:center;gap:10px;padding:9px 0}.wai-bk+.wai-bk{border-top:1px dashed var(--line)}
.wai-pill{padding:3px 10px;border-radius:99px;background:#dcecff;color:var(--b);font-size:.72rem;font-weight:700;text-transform:capitalize;white-space:nowrap;text-decoration:none}.wai-pill.completed{background:#d9f5e6;color:var(--ok)}.wai-pill.cancelled{background:#fdecea;color:var(--red)}
.wai-foot{padding:12px 12px 10px;background:#fff;border-top:1px solid var(--line)}
.wai-in{display:flex;align-items:flex-end;gap:8px;padding:6px 6px 6px 14px;border:1.5px solid var(--line);border-radius:18px;background:var(--soft);transition:border-color .2s,box-shadow .2s}
.wai-in:focus-within{border-color:var(--b);box-shadow:0 0 0 4px rgba(10,99,214,.12);background:#fff}
.wai-in textarea{flex:1;border:0;outline:0;background:none;resize:none;font:inherit;max-height:96px;padding:8px 0;color:var(--ink)}
.wai-in button{width:38px;height:38px;border-radius:12px;display:grid;place-items:center;color:var(--mute);transition:all .2s}.wai-in button:hover{background:#e6effb;color:var(--b)}
.wai-in .send{background:linear-gradient(180deg,#2a9bff,#0a63d6);color:#fff}.wai-in .send:hover{background:linear-gradient(180deg,#2a9bff,#0a63d6);color:#fff;transform:scale(1.06)}.wai-in .send:disabled{opacity:.5}
.wai-in .mic.rec{background:var(--red);color:#fff;animation:wping2 1.2s infinite}
@keyframes wping2{0%{box-shadow:0 0 0 0 rgba(214,45,40,.5)}100%{box-shadow:0 0 0 12px rgba(214,45,40,0)}}
.wai-sm{text-align:center;font-size:.7rem;color:var(--mute);margin-top:7px}
.wai-team{display:grid;grid-template-columns:1fr 1fr;gap:16px 10px}.wai-dev{text-align:center}
.wai-dpic{width:78px;height:78px;margin:0 auto 8px;border-radius:50%;overflow:hidden;display:grid;place-items:center;color:#fff;font-weight:800;font-size:1.7rem;background:linear-gradient(135deg,#2a9bff,#0a3f9e);box-shadow:0 10px 22px -10px rgba(10,99,214,.7),0 0 0 3px #fff,0 0 0 4px var(--line)}
.wai-dpic img{width:100%;height:100%;object-fit:cover}.wai-dev b{display:block;font-size:.88rem}.wai-dev small{color:var(--mute);font-size:.74rem}
.wai-card.wai-clock{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:16px;border:0;color:#fff;position:relative;overflow:hidden;background:linear-gradient(135deg,#0a3f9e,#0a63d6 65%,#1e90ff);box-shadow:0 16px 32px -14px rgba(10,99,214,.85)}
.wai-clock::after{content:"";position:absolute;right:-40px;top:-50px;width:150px;height:150px;border-radius:50%;background:rgba(255,255,255,.1)}
.wai-clock .wk-l{position:relative;z-index:1;min-width:0}
.wai-clock .wk-t{font-size:2.3rem;font-weight:800;letter-spacing:-.04em;line-height:1;font-variant-numeric:tabular-nums}
.wai-clock .wk-t small{font-size:.85rem;font-weight:700;opacity:.8;margin-left:5px;letter-spacing:0}
.wai-clock .wk-d{margin-top:7px;font-size:.84rem;font-weight:600;opacity:.95}
.wai-clock .wk-c{display:flex;gap:6px;flex-wrap:wrap;margin-top:10px}
.wai-clock .wk-c span{padding:2px 9px;border-radius:99px;background:rgba(255,255,255,.18);font-size:.72rem;font-weight:600}
#wai .wk-a{position:relative;z-index:1;flex:none;width:88px;height:88px}
#wai .wk-a svg{width:88px;height:88px;stroke-linecap:round}
#wai .wk-a .wk-f{fill:rgba(255,255,255,.12);stroke:rgba(255,255,255,.5);stroke-width:1.5}
#wai .wk-a .tk{stroke:rgba(255,255,255,.55);stroke-width:1.5}
#wai .wk-a .hh,#wai .wk-a .mh{stroke:#fff}#wai .wk-a .hh{stroke-width:3.2}#wai .wk-a .mh{stroke-width:2.2}
#wai .wk-a .sh{stroke:#3ddc84;stroke-width:1.3}#wai .wk-a .dot{fill:#fff;stroke:none}
@media(max-width:560px){.wai-win{inset:0;width:100%;height:100%;border-radius:0;transform-origin:bottom}.wai-fab{right:14px;bottom:14px}}
@media(prefers-reduced-motion:reduce){#wai *,#wai *::before{animation:none!important;transition:none!important}}
</style>

<button class="wai-fab" id="waiFab" aria-label="Open WorkOnnect AI"><span class="tip">Hi <?= htmlspecialchars($wfirst) ?>, need help? ✨</span>
  <svg viewBox="0 0 24 24"><path d="M12 3l1.8 4.6L18.5 9l-4.7 1.4L12 15l-1.8-4.6L5.5 9l4.7-1.4zM18 15l.8 2.2L21 18l-2.2.8L18 21l-.8-2.2L15 18l2.2-.8z"/></svg></button>

<section class="wai-win" role="dialog" aria-label="WorkOnnect AI assistant">
  <header class="wai-head">
    <span class="wai-av"><svg viewBox="0 0 24 24"><path d="M12 3l1.8 4.6L18.5 9l-4.7 1.4L12 15l-1.8-4.6L5.5 9l4.7-1.4z"/></svg><i></i></span>
    <div class="wai-ht"><b>WorkOnnect AI</b><small>Online · English, ಕನ್ನಡ, हिन्दी</small></div>
    <button class="wai-ib" id="waiSpk" title="Read replies aloud" aria-label="Read replies aloud"><svg viewBox="0 0 24 24"><path d="M11 5L6 9H3v6h3l5 4zM16 9a4 4 0 0 1 0 6M19 6a8 8 0 0 1 0 12"/></svg></button>
    <button class="wai-ib" id="waiNew" title="New conversation" aria-label="New conversation"><svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg></button>
    <button class="wai-ib" id="waiClose" title="Close" aria-label="Close"><svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18"/></svg></button>
  </header>
  <div class="wai-bar"><span>Voice &amp; reply language</span><div class="lg"><button data-l="en" class="on">EN</button><button data-l="kn">ಕನ್ನಡ</button><button data-l="hi">हिं</button></div></div>
  <div class="wai-msgs" id="waiMsgs" aria-live="polite"></div>
  <div class="wai-foot">
    <div class="wai-in">
      <textarea id="waiTx" rows="1" maxlength="600" placeholder="Ask anything about services…" aria-label="Message"></textarea>
      <button class="mic" id="waiMic" title="Speak" aria-label="Voice input"><svg viewBox="0 0 24 24"><rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3"/></svg></button>
      <button class="send" id="waiSend" aria-label="Send"><svg viewBox="0 0 24 24"><path d="M22 2L11 13M22 2l-7 20-4-9-9-4z"/></svg></button>
    </div>
    <div class="wai-sm">AI can make mistakes. Bookings are only made after you confirm. Never share OTPs or card details.</div>
  </div>
</section>

<script>
(function(){
var root=document.getElementById("wai"),CSRF=root.dataset.csrf,ROLE=root.dataset.role,NAME=root.dataset.name,KEY="wai_"+root.dataset.uid;
var $=function(i){return document.getElementById(i)},box=$("waiMsgs"),tx=$("waiTx"),send=$("waiSend"),lang="en",speak=false,busy=false,hist=[];
var LC={en:"en-IN",kn:"kn-IN",hi:"hi-IN"};
var CHIPS={customer:["Find an electrician near me","Estimate a plumbing job","Show my bookings","Find a verified painter"],worker:["Show my new job requests","How much did I earn this month?","How am I performing?","Improve my profile"],admin:["How does WorkOnnect work?","How do payments work?","Is it safe?"]};
var I={ok:'<svg class="wai-vf" viewBox="0 0 24 24"><path d="M12 2l2.4 2 3.1-.3 1 3 2.7 1.6-1 3 1 3-2.7 1.6-1 3-3.1-.3L12 22l-2.4-2-3.1.3-1-3L2.8 15.7l1-3-1-3 2.7-1.6 1-3 3.1.3z" fill="currentColor" stroke="none"/><path d="M8.5 12l2.5 2.5 4.5-5" stroke="#fff"/></svg>'};
function h(t,c,x){var e=document.createElement(t);if(c)e.className=c;if(x!=null)e.textContent=x;return e}
function fmt(el,t){String(t).split(/(\*\*[^*]+\*\*|\*[^*\n]+\*)/).forEach(function(p){
 if(/^\*\*[^*]+\*\*$/.test(p))el.appendChild(h("b",0,p.slice(2,-2)));
 else if(/^\*[^*\n]+\*$/.test(p))el.appendChild(h("em",0,p.slice(1,-1)));
 else if(p)el.appendChild(document.createTextNode(p))})}
function down(){box.scrollTop=box.scrollHeight}
function save(){try{sessionStorage.setItem(KEY,JSON.stringify(hist.slice(-30)))}catch(e){}}
function open(o){root.classList.toggle("open",o);if(o){setTimeout(function(){tx.focus()},300);down()}}
$("waiFab").onclick=function(){open(!root.classList.contains("open"))};$("waiClose").onclick=function(){open(false)};
document.addEventListener("keydown",function(e){if(e.key=="Escape"&&root.classList.contains("open"))open(false)});

function chips(list,keep){if(!keep)box.querySelectorAll(".wai-qr").forEach(function(x){x.remove()});if(!list||!list.length)return;
 var c=h("div","wai-chips");list.forEach(function(t){var b=h("button",0,t);b.onclick=function(){ask(t)};c.appendChild(b)});
 var m=h("div","wai-m a wai-qr");m.appendChild(c);box.appendChild(m);down()}
function welcome(){box.innerHTML="";var w=h("div","wai-hello");var o=h("div","orb");o.innerHTML=$("waiFab").querySelector("svg").outerHTML;
 w.append(o,h("h3",0,"Hi "+NAME+" 👋"),h("p",0,ROLE=="worker"?"Manage your jobs, earnings and availability — just ask.":"Find trusted workers, compare prices and book — just ask."));box.appendChild(w);chips(CHIPS[ROLE],true)}

function bubble(role,text,err){var m=h("div","wai-m "+(role=="user"?"u":"a")),b=h("div","wai-b"+(err?" err":""));fmt(b,text);m.appendChild(b);
 if(role!="user"&&!err){var a=h("div","wai-acts"),c=h("button");c.innerHTML='<svg viewBox="0 0 24 24"><rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h10"/></svg>Copy';
  c.onclick=function(){try{navigator.clipboard.writeText(String(text).replace(/\*/g,""));c.lastChild.textContent="Copied"}catch(e){}};a.appendChild(c);m.appendChild(a)}
 box.appendChild(m);down();return m}
function typing(){var m=h("div","wai-m a"),b=h("div","wai-b wai-typing");b.append(h("i"),h("i"),h("i"));m.appendChild(b);box.appendChild(m);down();return m}
function reveal(text,cb){var m=bubble("assistant",""),b=m.querySelector(".wai-b");b.textContent="";var w=String(text).replace(/\*/g,"").split(" "),i=0;
 if(text.length>500||matchMedia("(prefers-reduced-motion:reduce)").matches){fmt(b,text);cb&&cb();return}
 var t=setInterval(function(){b.textContent+=(i?" ":"")+w[i++];down();if(i>=w.length){clearInterval(t);b.textContent="";fmt(b,text);cb&&cb()}},22)}

function card(c){var d=h("div","wai-card");
 if(c.type=="workers"){c.items.forEach(function(w){var r=h("div","wai-wk"),p=h("div","wai-pic");if(w.photo){var im=h("img");im.src=w.photo;im.alt="";p.appendChild(im)}else p.textContent=(w.name||"?")[0].toUpperCase();
  var i=h("div");i.style.flex=1;var n=h("b",0,w.name);if(w.verified){var v=h("span");v.innerHTML=I.ok;v.style.color="var(--b)";n.appendChild(v)}
  var s=h("div","wai-st",w.rating==null?"New":"★ "+w.rating);s.appendChild(h("em",0,"  · "+w.jobs_done+" jobs done"));var t=h("div","wai-tags");
  [w.category,w.location,w.experience?w.experience+" exp":null,w.rate?"₹"+w.rate:null].forEach(function(x){if(x)t.appendChild(h("span",0,x))});if(w.available!=null)t.appendChild(h("span",w.available?"ok":"",w.available?"Available":"Busy"));
  var bt=h("div","wai-btns"),a=h("a","wai-btn g","View profile");a.href="worker.php?id="+w.id;var k=h("button","wai-btn p","Book");k.onclick=function(){ask("I want to book "+w.name+" (worker #"+w.index+")")};bt.append(a,k);i.append(n,s,t,bt);r.append(p,i);d.appendChild(r)})}
 else if(c.type=="estimate"){d.appendChild(h("h4",0,"Estimated price · "+c.category));d.appendChild(h("div","wai-big","₹"+c.low+" – ₹"+c.high));d.appendChild(h("div","wai-note","Typical ₹"+c.avg+" · based on "+c.samples+" past paid WorkOnnect jobs. This is an estimate, not a confirmed quotation."))}
 else if(c.type=="confirm"){d.appendChild(h("h4",0,"Review your booking"));[["Worker",c.worker],["Job",c.title],["When",String(c.slot).replace("T"," ")],["Where",c.location||"–"]].forEach(function(r){var x=h("div","wai-row");x.append(h("span",0,r[0]),h("b",0,r[1]));d.appendChild(x)});
  var bt=h("div","wai-btns");bt.style.marginTop="10px";var ok=h("button","wai-btn p","Confirm booking"),no=h("button","wai-btn g","Not now");
  ok.onclick=function(){ok.disabled=no.disabled=true;ok.textContent="Booking…";post({action:"confirm_booking",token:c.token}).then(function(r){bt.remove();
   if(r.error){bubble("assistant",r.error,true);return}
   (r.cards||[]).forEach(function(k){box.appendChild(wrap(card(k)))});reveal(r.reply,function(){chips(r.chips)});hist.push({r:"a",t:r.reply});save();say(r.reply)})};
  no.onclick=function(){bt.remove();ask("Not now")};bt.append(ok,no);d.appendChild(bt);d.appendChild(h("div","wai-note","Nothing is booked until you press Confirm."))}
 else if(c.type=="done"){d.appendChild(h("h4",0,"✅ Request sent · #"+c.job_id));d.appendChild(h("div","wai-note",c.title+" with "+c.worker+" · "+String(c.slot).replace("T"," ")));var a=h("a","wai-btn p","Track booking");a.href="job.php?id="+c.job_id;a.style.marginTop="8px";d.appendChild(a)}
 else if(c.type=="bookings"){d.appendChild(h("h4",0,c.title));if(!c.items.length)d.appendChild(h("div","wai-note","Nothing here yet."));c.items.forEach(function(j){var r=h("div","wai-bk"),l=h("div");l.append(h("b",0,"#"+j.id+" "+j.title),h("div","wai-note",[j.person,j.slot?String(j.slot).replace("T"," "):null,j.price?"₹"+j.price:null].filter(Boolean).join(" · ")));
  var s=h("a","wai-pill "+j.status,String(j.status).replace("_"," "));s.href="job.php?id="+j.id;r.append(l,s);d.appendChild(r)})}
 else if(c.type=="stats"){d.appendChild(h("h4",0,c.title));c.rows.forEach(function(r){var x=h("div","wai-row");x.append(h("span",0,r[0]),h("b",0,String(r[1])));d.appendChild(x)})}
 else if(c.type=="cancel"){d.appendChild(h("h4",0,"Cancel #"+c.job_id+" · "+c.title));d.appendChild(h("div","wai-note",c.fee?"A cancellation fee of ₹"+c.fee+" applies.":"No cancellation fee applies."));var a=h("a","wai-btn r","Continue on job page");a.href="job.php?id="+c.job_id;a.style.marginTop="8px";d.appendChild(a)}
 else if(c.type=="team"){d.appendChild(h("h4",0,"Meet the developers"));var g=h("div","wai-team");c.items.forEach(function(q){var t=h("div","wai-dev"),pc=h("div","wai-dpic");
  if(q.photo){var im=h("img");im.src=q.photo;im.alt=q.name;im.onerror=function(){im.remove();pc.textContent=q.name[0]};pc.appendChild(im)}else pc.textContent=q.name[0];
  t.append(pc,h("b",0,q.name),h("small",0,"Developer"));g.appendChild(t)});d.appendChild(g)}
 else if(c.type=="clock"){d.className="wai-card wai-clock";var cl=h("div","wk-l"),tm=h("div","wk-t"),dt=h("div","wk-d"),ch=h("div","wk-c");cl.append(tm,dt,ch);
  var ticks="";for(var q=0;q<12;q++)ticks+='<line class="tk" x1="50" y1="6" x2="50" y2="'+(q%3?9:12)+'" transform="rotate('+q*30+' 50 50)"/>';
  var an=h("div","wk-a");an.innerHTML='<svg viewBox="0 0 100 100"><circle class="wk-f" cx="50" cy="50" r="46"/>'+ticks+'<line class="hh" x1="50" y1="50" x2="50" y2="29"/><line class="mh" x1="50" y1="50" x2="50" y2="17"/><line class="sh" x1="50" y1="56" x2="50" y2="12"/><circle class="dot" cx="50" cy="50" r="3"/></svg>';
  d.append(cl,an);var iv;
  function tick(first){if(!first&&!document.body.contains(d)){clearInterval(iv);return}
   var n=new Date(),H=n.getHours(),M=n.getMinutes(),S=n.getSeconds(),p2=function(x){return(x<10?"0":"")+x};
   tm.textContent=(H%12||12)+":"+p2(M);var sm=h("small",0,p2(S)+" "+(H<12?"AM":"PM"));tm.appendChild(sm);
   try{dt.textContent=n.toLocaleDateString(LC[lang],{weekday:"long",day:"numeric",month:"long",year:"numeric"})}catch(e){dt.textContent=n.toDateString()}
   if(first){var y0=new Date(n.getFullYear(),0,0),doy=Math.floor((n-y0)/864e5),tz="";try{tz=Intl.DateTimeFormat().resolvedOptions().timeZone}catch(e){}
    if(tz)ch.appendChild(h("span",0,tz.replace("_"," ")));ch.appendChild(h("span",0,"Day "+doy+" of "+(new Date(n.getFullYear(),11,31).getTime()>0?((n.getFullYear()%4==0&&(n.getFullYear()%100||n.getFullYear()%400==0))?366:365):365)))}
   an.querySelector(".hh").setAttribute("transform","rotate("+((H%12+M/60)*30)+" 50 50)");an.querySelector(".mh").setAttribute("transform","rotate("+((M+S/60)*6)+" 50 50)");an.querySelector(".sh").setAttribute("transform","rotate("+(S*6)+" 50 50)")}
  iv=setInterval(tick,1000);tick(true)}
 return d}
function wrap(n){var m=h("div","wai-m a");m.style.maxWidth="100%";m.appendChild(n);return m}

function post(b){return fetch("ai_chat.php",{method:"POST",headers:{"Content-Type":"application/json","X-CSRF":CSRF},body:JSON.stringify(b),credentials:"same-origin"}).then(function(r){return r.json()}).catch(function(){return{error:"Network problem. Check your connection and try again."}})}
var VOICES=[];function loadV(){VOICES=window.speechSynthesis?speechSynthesis.getVoices():[]}
if(window.speechSynthesis){loadV();speechSynthesis.onvoiceschanged=loadV}
function pickVoice(){var l=LC[lang].toLowerCase(),pre=l.split("-")[0];
 var pool=VOICES.filter(function(v){return v.lang.toLowerCase().replace("_","-")==l});
 if(!pool.length)pool=VOICES.filter(function(v){return v.lang.toLowerCase().indexOf(pre)==0});
 var soft=/(neerja|swara|heera|sapna|kalpana|veena|lekha|priya|aria|jenny|zira|samantha|natural|google|female)/i;
 return pool.filter(function(v){return soft.test(v.name)})[0]||pool[0]||null}
function say(t){if(!speak||!window.speechSynthesis)return;speechSynthesis.cancel();
 var clean=String(t).replace(/\*/g,"").replace(/[\u{1F300}-\u{1FAFF}\u{2600}-\u{27BF}\u{FE0F}\u{200D}\u{20E3}]/gu,"").replace(/\s*\n+\s*/g,". ").replace(/\s+/g," ").trim();
 var u=new SpeechSynthesisUtterance(clean);u.lang=LC[lang];var v=pickVoice();if(v)u.voice=v;u.rate=.9;u.pitch=1.1;u.volume=.95;speechSynthesis.speak(u)}
function ask(t){t=(t||"").trim();if(!t||busy)return;if(!hist.length)box.innerHTML="";chips(null);bubble("user",t);hist.push({r:"u",t:t});tx.value="";grow();busy=true;send.disabled=true;var ty=typing();
 post({message:t,lang:lang}).then(function(r){ty.remove();busy=false;send.disabled=false;
  if(r.error){bubble("assistant",r.error,true);return}
  reveal(r.reply,function(){(r.cards||[]).forEach(function(c){box.appendChild(wrap(card(c)))});chips(r.chips);down()});hist.push({r:"a",t:r.reply});save();
  var extra="";if((r.cards||[]).some(function(c){return c.type=="clock"})){var n=new Date();try{extra=" "+n.toLocaleTimeString(LC[lang],{hour:"numeric",minute:"2-digit",hour12:true})+". "+n.toLocaleDateString(LC[lang],{weekday:"long",day:"numeric",month:"long",year:"numeric"})}catch(e){}}
  say(r.reply+extra)})}
send.onclick=function(){ask(tx.value)};tx.onkeydown=function(e){if(e.key=="Enter"&&!e.shiftKey){e.preventDefault();ask(tx.value)}};
function grow(){tx.style.height="auto";tx.style.height=Math.min(tx.scrollHeight,96)+"px"}tx.oninput=grow;

$("waiNew").onclick=function(){hist=[];save();post({action:"reset"});welcome()};
$("waiSpk").onclick=function(){speak=!speak;this.classList.toggle("on",speak);if(!speak&&window.speechSynthesis)speechSynthesis.cancel()};
var SR=window.SpeechRecognition||window.webkitSpeechRecognition,rec=null,mic=$("waiMic");
root.querySelectorAll(".lg button").forEach(function(b){b.onclick=function(){lang=b.dataset.l;root.querySelectorAll(".lg button").forEach(function(x){x.classList.toggle("on",x==b)});if(rec)rec.lang=LC[lang]}});
if(!SR){mic.style.display="none"}else{rec=new SR();rec.interimResults=true;rec.lang="en-IN";rec.onresult=function(e){var s="";for(var i=0;i<e.results.length;i++)s+=e.results[i][0].transcript;tx.value=s;grow();if(e.results[e.results.length-1].isFinal){mic.classList.remove("rec");ask(s)}};rec.onend=function(){mic.classList.remove("rec")};rec.onerror=function(){mic.classList.remove("rec")};
 mic.onclick=function(){if(mic.classList.contains("rec")){rec.stop();return}try{rec.lang=LC[lang];rec.start();mic.classList.add("rec")}catch(e){}}}

/* hide the old floating "Chat" button so this assistant takes its place */
var oldCb=document.getElementById("cb");if(oldCb)oldCb.style.display="none";
try{hist=JSON.parse(sessionStorage.getItem(KEY)||"[]")}catch(e){hist=[]}
if(hist.length){box.innerHTML="";hist.forEach(function(m){bubble(m.r=="u"?"user":"assistant",m.t)})}else welcome();
})();
</script>
</div>
<?php // WorkOnnect AI widget - offline, no API. Included once from foot().
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
#wai{--b:#0a63d6;--bh:#0852b3;--b2:#2a9bff;--n:#0b3a8c;--ink:#14213d;--mute:#5f6f8c;--line:#e2e9f4;--soft:#f4f7fc;--ok:#12844a;--red:#d12f2a;
  font:500 14.5px/1.55 Inter,system-ui,-apple-system,"Segoe UI","Noto Sans Kannada","Noto Sans Devanagari",sans-serif;color:var(--ink)}
:where(#wai) *{box-sizing:border-box;margin:0}
:where(#wai) button{font:inherit;cursor:pointer;border:0;background:none;color:inherit}
#wai button:focus-visible,#wai a:focus-visible,#wai textarea:focus-visible{outline:2px solid var(--b2);outline-offset:2px}
:where(#wai) svg{width:20px;height:20px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;flex:none}

/* launcher */
#wai .wai-fab{position:fixed;right:22px;bottom:22px;z-index:2147483000;width:58px;height:58px;border-radius:50%;color:#fff;display:grid;place-items:center;background:var(--b);box-shadow:0 6px 18px rgba(10,99,214,.35)}
#wai .wai-fab:hover{background:var(--bh)}
#wai .wai-fab svg{width:28px;height:28px}
/* #wai .wai-fab .tip{display:none} */
#wai .wai-fab .tip{position:absolute;right:70px;bottom:8px;width:max-content;max-width:min(230px,calc(100vw - 110px));display:flex;align-items:center;gap:8px;padding:10px 12px 10px 14px;border-radius:14px 14px 4px 14px;background:#fff;color:var(--ink);border:1px solid var(--line);box-shadow:0 8px 24px rgba(15,31,61,.18);font-size:.84rem;font-weight:600;line-height:1.35;text-align:left;opacity:0;transform:translateY(6px) scale(.96);transform-origin:100% 100%;pointer-events:none;transition:opacity .25s,transform .25s}
#wai .wai-fab .tip.show{opacity:1;transform:none;pointer-events:auto}
#wai .wai-fab .tip b{font-weight:600}
#wai .wai-fab .tip i{font-style:normal;flex:none;width:20px;height:20px;border-radius:50%;display:grid;place-items:center;color:var(--mute);font-size:1rem;line-height:1;cursor:pointer}
#wai .wai-fab .tip i:hover{background:var(--soft);color:var(--ink)}
#wai .wai-fab::after{content:"";position:absolute;inset:0;border-radius:50%;box-shadow:0 0 0 0 rgba(10,99,214,.45);animation:waiPulse 2.4s infinite;pointer-events:none}
#wai.open .wai-fab::after{animation:none}
@keyframes waiPulse{70%{box-shadow:0 0 0 14px rgba(10,99,214,0)}100%{box-shadow:0 0 0 0 rgba(10,99,214,0)}}
@media(prefers-reduced-motion:reduce){#wai .wai-fab::after{animation:none}#wai .wai-fab .tip{transition:none}}


/* window */
#wai .wai-win{position:fixed;right:22px;bottom:100px;z-index:2147483001;width:400px;height:min(700px,calc(100vh - 120px));display:none;flex-direction:column;background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 18px 50px rgba(15,31,61,.26),0 0 0 1px rgba(15,31,61,.06)}
#wai.open .wai-win{display:flex}

/* header */
#wai .wai-head{display:flex;align-items:center;gap:10px;padding:15px 14px 15px 16px;color:#fff;background:#0b4fbf}
#wai .wai-av{position:relative;width:42px;height:42px;border-radius:12px;display:grid;place-items:center;background:rgba(255,255,255,.16);flex:none}
#wai .wai-av i{position:absolute;right:-3px;bottom:-3px;width:12px;height:12px;border-radius:50%;background:#3ddc84;border:2px solid #0b4fbf}
#wai .wai-ht{flex:1;min-width:0}
#wai .wai-ht b{display:block;font-size:1.05rem;letter-spacing:-.01em}
#wai .wai-ht small{opacity:.88;font-size:.76rem}
#wai .wai-ib{width:34px;height:34px;border-radius:10px;display:grid;place-items:center;color:#fff}
#wai .wai-ib:hover,#wai .wai-ib.on{background:rgba(255,255,255,.2)}

/* language switch */
#wai .wai-bar{display:flex;gap:8px;padding:9px 14px;background:#fff;border-bottom:1px solid var(--line);align-items:center;font-size:.78rem;color:var(--mute)}
#wai .wai-bar .lg{margin-left:auto;display:flex;padding:2px;border-radius:99px;background:var(--soft);border:1px solid var(--line);gap:2px}
#wai .wai-bar .lg button{padding:4px 12px;border-radius:99px;font-weight:700;font-size:.76rem;color:var(--mute)}
#wai .wai-bar .lg button:hover{color:var(--b)}
#wai .wai-bar .lg button.on{background:var(--b);color:#fff}

/* conversation */
#wai .wai-msgs{flex:1;overflow-y:auto;padding:16px 14px;background:var(--soft);display:flex;flex-direction:column;gap:14px}
#wai .wai-msgs::-webkit-scrollbar{width:6px}
#wai .wai-msgs::-webkit-scrollbar-thumb{background:#c9d9f1;border-radius:9px}
#wai .wai-m{display:flex;flex-direction:column;gap:4px;max-width:92%}
#wai .wai-m.u{align-self:flex-end;align-items:flex-end}
#wai .wai-b{padding:12px 15px;border-radius:14px;line-height:1.6;word-wrap:break-word;white-space:pre-wrap}
#wai .wai-b em{font-style:italic;opacity:.85}
#wai .u .wai-b{background:var(--b);color:#fff;border-bottom-right-radius:4px}
#wai .a .wai-b{background:#fff;color:var(--ink);border:1px solid var(--line);border-left:3px solid var(--b);border-bottom-left-radius:4px;box-shadow:0 1px 2px rgba(15,31,61,.05)}
#wai .a .wai-b b{color:var(--n);font-weight:700}
#wai .wai-b.err{color:var(--red);border-color:#f1c5c3;border-left-color:var(--red)}
#wai .wai-t{font-size:.68rem;color:var(--mute);padding:0 6px}
#wai .wai-acts{display:flex;gap:2px}
#wai .wai-acts button{display:flex;align-items:center;gap:4px;font-size:.74rem;color:var(--mute);padding:3px 8px;border-radius:8px}
#wai .wai-acts button:hover{background:#e8eff9;color:var(--b)}
#wai .wai-acts svg{width:14px;height:14px}
#wai .wai-typing{display:flex;gap:5px;padding:14px 16px}
#wai .wai-typing i{width:8px;height:8px;border-radius:50%;background:var(--b)}
#wai .wai-typing i:nth-child(1){opacity:.35}#wai .wai-typing i:nth-child(2){opacity:.6}#wai .wai-typing i:nth-child(3){opacity:.9}

/* welcome + starter tiles */
#wai .wai-hello{text-align:center;padding:8px 6px 2px}
#wai .wai-hello .orb{width:64px;height:64px;margin:0 auto 10px;border-radius:18px;display:grid;place-items:center;color:#fff;background:var(--b)}
#wai .wai-hello .orb svg{width:30px;height:30px}
#wai .wai-hello h3{font-size:1.25rem;letter-spacing:-.02em}
#wai .wai-hello p{color:var(--mute);font-size:.88rem;margin-top:2px}
#wai .wai-sg{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:4px}
#wai .wai-sg button{display:flex;flex-direction:column;align-items:flex-start;gap:10px;text-align:left;padding:12px;border-radius:14px;background:#fff;border:1px solid var(--line);font-weight:600;font-size:.84rem;line-height:1.3;color:var(--ink)}
#wai .wai-sg button span{width:34px;height:34px;display:grid;place-items:center;border-radius:10px;background:#e8f0fd;font-size:1.1rem}
#wai .wai-sg button:hover{border-color:var(--b);background:#f7faff}

/* quick replies */
#wai .wai-strip{display:none;align-items:center;gap:8px;padding:10px 12px;overflow-x:auto;white-space:nowrap;background:#fff;border-top:1px solid var(--line);scrollbar-width:none}
#wai .wai-strip::-webkit-scrollbar{display:none}
#wai .wai-strip.has{display:flex}
#wai .wai-chip{flex:none;padding:7px 14px;border-radius:99px;background:#fff;border:1px solid #cddcf3;color:var(--b);font-weight:600;font-size:.82rem}
#wai .wai-chip:hover{background:var(--b);border-color:var(--b);color:#fff}

/* cards */
#wai .wai-card{background:#fff;border:1px solid var(--line);border-radius:14px;padding:14px}
#wai .wai-wk{display:flex;gap:12px}
#wai .wai-wk+.wai-wk{margin-top:12px;padding-top:12px;border-top:1px solid var(--line)}
#wai .wai-pic{width:52px;height:52px;border-radius:50%;display:grid;place-items:center;color:#fff;font-weight:800;font-size:1.2rem;background:var(--b);flex:none;overflow:hidden}
#wai .wai-pic img{width:100%;height:100%;object-fit:cover}
#wai .wai-wk b{display:flex;align-items:center;gap:6px}
#wai .wai-vf{color:var(--b);width:16px!important;height:16px!important}
#wai .wai-st{color:#f5a623;font-size:.82rem;font-weight:700}
#wai .wai-st em{color:var(--mute);font-style:normal;font-weight:500}
#wai .wai-tags{display:flex;flex-wrap:wrap;gap:5px;margin:6px 0}
#wai .wai-tags span{padding:2px 9px;border-radius:99px;background:var(--soft);border:1px solid var(--line);font-size:.74rem;font-weight:600}
#wai .wai-tags .ok{background:#e6f6ed;border-color:#c3e8d2;color:var(--ok)}
#wai .wai-btns{display:flex;gap:8px;flex-wrap:wrap}
#wai .wai-card h4{font-size:.95rem;margin-bottom:8px;display:flex;align-items:center;gap:8px;color:var(--n)}
#wai .wai-row{display:flex;justify-content:space-between;gap:12px;padding:6px 0;border-bottom:1px solid var(--line);font-size:.88rem}
#wai .wai-row:last-of-type{border:0}
#wai .wai-row span{color:var(--mute)}
#wai .wai-row b{text-align:right}
#wai .wai-big{font-size:1.6rem;font-weight:800;color:var(--b);letter-spacing:-.03em}
#wai .wai-note{font-size:.74rem;color:var(--mute);margin-top:6px}
#wai .wai-bk{display:flex;justify-content:space-between;align-items:center;gap:10px;padding:9px 0}
#wai .wai-bk+.wai-bk{border-top:1px solid var(--line)}
#wai .wai-pill{padding:3px 10px;border-radius:99px;background:#e3eefc;color:var(--b);font-size:.72rem;font-weight:700;text-transform:capitalize;white-space:nowrap;text-decoration:none}
#wai .wai-pill.completed{background:#e3f5ea;color:var(--ok)}
#wai .wai-pill.cancelled{background:#fdeceb;color:var(--red)}

/* buttons: always blue */
#wai .wai-btn{padding:9px 16px;border-radius:10px;font-weight:600;font-size:.84rem;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;gap:6px;border:1px solid transparent}
#wai .wai-btn.p{background:var(--b);color:#fff}
#wai .wai-btn.p:hover{background:var(--bh)}
#wai .wai-btn.g,#wai .wai-btn.r{background:#fff;color:var(--b);border-color:#cddcf3}
#wai .wai-btn.g:hover,#wai .wai-btn.r:hover{background:#eef4fd}
#wai .wai-btn:disabled{opacity:.55;cursor:not-allowed}

/* team + clock cards */
#wai .wai-team{display:grid;grid-template-columns:1fr 1fr;gap:16px 10px}
#wai .wai-dev{text-align:center}
#wai .wai-dpic{width:78px;height:78px;margin:0 auto 8px;border-radius:50%;overflow:hidden;display:grid;place-items:center;color:#fff;font-weight:800;font-size:1.7rem;background:var(--b);box-shadow:0 0 0 2px #fff,0 0 0 3px var(--line)}
#wai .wai-dpic img{width:100%;height:100%;object-fit:cover}
#wai .wai-dev b{display:block;font-size:.88rem}
#wai .wai-dev small{color:var(--mute);font-size:.74rem}
#wai .wai-card.wai-clock{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:16px;border:0;color:#fff;background:#0b4fbf}
#wai .wai-clock .wk-l{min-width:0}
#wai .wai-clock .wk-t{font-size:2.3rem;font-weight:800;letter-spacing:-.04em;line-height:1;font-variant-numeric:tabular-nums}
#wai .wai-clock .wk-t small{font-size:.85rem;font-weight:700;opacity:.8;margin-left:5px;letter-spacing:0}
#wai .wai-clock .wk-d{margin-top:7px;font-size:.84rem;font-weight:600;opacity:.95}
#wai .wai-clock .wk-c{display:flex;gap:6px;flex-wrap:wrap;margin-top:10px}
#wai .wai-clock .wk-c span{padding:2px 9px;border-radius:99px;background:rgba(255,255,255,.18);font-size:.72rem;font-weight:600}
#wai .wk-a{flex:none;width:88px;height:88px}
#wai .wk-a svg{width:88px;height:88px;stroke-linecap:round}
#wai .wk-a .wk-f{fill:rgba(255,255,255,.12);stroke:rgba(255,255,255,.5);stroke-width:1.5}
#wai .wk-a .tk{stroke:rgba(255,255,255,.55);stroke-width:1.5}
#wai .wk-a .hh,#wai .wk-a .mh{stroke:#fff}
#wai .wk-a .hh{stroke-width:3.2}#wai .wk-a .mh{stroke-width:2.2}
#wai .wk-a .sh{stroke:#3ddc84;stroke-width:1.3}
#wai .wk-a .dot{fill:#fff;stroke:none}

/* input */
#wai .wai-foot{padding:10px 12px 12px;background:#fff;border-top:1px solid var(--line)}
#wai .wai-strip.has+.wai-foot{border-top:0;padding-top:0}
#wai .wai-in{display:flex;align-items:flex-end;gap:6px;padding:5px;border-radius:14px;background:#fff;border:1px solid #cfdaed}
#wai .wai-in:focus-within{border-color:var(--b)}
#wai .wai-in textarea{flex:1;min-width:0;border:0;outline:0!important;background:none;resize:none;font:inherit;line-height:1.4;max-height:96px;padding:9px 6px 9px 10px;color:var(--ink)}
#wai .wai-in textarea::placeholder{color:#8a9ab8}
#wai .wai-in button{flex:none;width:38px;height:38px;border-radius:10px;display:grid;place-items:center}
#wai .wai-in .mic{background:var(--soft);color:var(--b)}
#wai .wai-in .mic:hover{background:#e3edfb}
#wai .wai-in .mic.rec{background:var(--b);color:#fff}
#wai .wai-in .send{background:var(--b);color:#fff}
#wai .wai-in .send:hover{background:var(--bh)}
#wai .wai-in .send:disabled{background:#c9d5e8;cursor:not-allowed}
#wai .wai-in .send.stop{background:var(--n)}
#wai .wai-sm{text-align:center;font-size:.7rem;color:var(--mute);margin-top:7px}
#wai .wai-toast{position:absolute;left:50%;top:112px;transform:translate(-50%,-10px);max-width:86%;padding:9px 16px;border-radius:10px;background:#14213d;color:#fff;font-size:.8rem;font-weight:600;text-align:center;opacity:0;pointer-events:none;z-index:5}
#wai .wai-toast.show{opacity:1;transform:translate(-50%,0)}

/* phones */
@media(max-width:560px){#wai .wai-win{inset:0;width:100%;height:100%;border-radius:0}#wai .wai-fab{right:14px;bottom:14px}}
</style>

<button class="wai-fab" id="waiFab" aria-label="Open WorkOnnect AI"><span class="tip" id="waiTip" role="status"><b id="waiTipT"></b><i id="waiTipX" role="button" aria-label="Close">×</i></span>
  <svg viewBox="0 0 24 24"><path d="M12 3l1.8 4.6L18.5 9l-4.7 1.4L12 15l-1.8-4.6L5.5 9l4.7-1.4zM18 15l.8 2.2L21 18l-2.2.8L18 21l-.8-2.2L15 18l2.2-.8z"/></svg></button>

<section class="wai-win" role="dialog" aria-label="WorkOnnect AI assistant">
  <header class="wai-head">
    <span class="wai-av"><svg viewBox="0 0 24 24"><path d="M12 3l1.8 4.6L18.5 9l-4.7 1.4L12 15l-1.8-4.6L5.5 9l4.7-1.4z"/></svg><i></i></span>
    <div class="wai-ht"><b>WorkOnnect AI</b><small id="waiSub"></small></div>
    <button class="wai-ib" id="waiSpk" aria-pressed="false"></button>
    <button class="wai-ib" id="waiNew"><svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg></button>
    <button class="wai-ib" id="waiClose"><svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18"/></svg></button>
  </header>
  <div class="wai-bar"><span id="waiBarL"></span><div class="lg" role="group"><button data-l="en" class="on">EN</button><button data-l="kn">ಕನ್ನಡ</button><button data-l="hi">हिं</button></div></div>
  <div class="wai-msgs" id="waiMsgs" aria-live="polite"></div>
  <div class="wai-strip" id="waiStrip"></div>
  <div class="wai-foot">
    <div class="wai-in">
      <textarea id="waiTx" rows="1" maxlength="600"></textarea>
      <button class="mic" id="waiMic"><svg viewBox="0 0 24 24"><rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3"/></svg></button>
      <button class="send" id="waiSend"></button>
    </div>
    <div class="wai-sm" id="waiFoot"></div>
  </div>
  <div class="wai-toast" id="waiToast" role="status"></div>
</section>

<script>
(function(){
var root=document.getElementById("wai"),CSRF=root.dataset.csrf,ROLE=root.dataset.role,NAME=root.dataset.name,KEY="wai_"+root.dataset.uid;
var $=function(i){return document.getElementById(i)},box=$("waiMsgs"),tx=$("waiTx"),send=$("waiSend"),strip=$("waiStrip"),spkB=$("waiSpk"),mic=$("waiMic");
var lang="en",speak=false,busy=false,hist=[],viaVoice=false,lastChips=[],speaking=false,revealing=false,revTimer=null,finishRev=null;
try{lang=localStorage.getItem("wai_lang")||"en";if(!/^(en|kn|hi)$/.test(lang))lang="en";speak=localStorage.getItem("wai_spk")=="1"}catch(e){}
var LC={en:"en-IN",kn:"kn-IN",hi:"hi-IN"};

/* ---------- interface text (English / ಕನ್ನಡ / हिन्दी) ---------- */
var UI={
en:{tip:"Hi %s, need help? ✨",sub:"Online · English, ಕನ್ನಡ, हिन्दी",bar:"Language (text & voice)",ph:"Type your message…",hi:"Hi %s 👋",subc:"Find trusted workers, compare prices and book — just ask.",subw:"Manage your jobs, earnings and availability — just ask.",
 foot:"Bookings are made only after you press Confirm. Never share OTPs or card details.",lis:"Listening… please speak",copy:"Copy",copied:"Copied",listen:"Listen",newc:"New conversation",close:"Close",spkon:"Voice replies: on",spkoff:"Voice replies: off",stop:"Stop",
 langset:"Language changed to English",send:"Send",voicefail:"Sorry, I couldn't play the voice. Please check your internet connection.",online:"No voice for this language on your device, so I'm using an online voice.",novoice:"A natural English voice isn't installed on this device, so I'll use the default voice.",micoff:"Please allow microphone access in your browser to use voice.",nospeech:"Sorry, I didn't catch that. Please try again.",micnet:"Voice typing needs an internet connection in this browser. You can still type your message.",neterr:"Sorry, there seems to be a network problem. Please check your connection and try again.",nomic:"Voice input isn't supported in this browser."},
kn:{tip:"ನಮಸ್ಕಾರ %s, ಸಹಾಯ ಬೇಕೆ? ✨",sub:"ಆನ್‌ಲೈನ್ · English, ಕನ್ನಡ, हिन्दी",bar:"ಭಾಷೆ (ಪಠ್ಯ ಮತ್ತು ಧ್ವನಿ)",ph:"ನಿಮ್ಮ ಸಂದೇಶ ಟೈಪ್ ಮಾಡಿ…",hi:"ನಮಸ್ಕಾರ %s 👋",subc:"ವಿಶ್ವಾಸಾರ್ಹ ಕೆಲಸಗಾರರನ್ನು ಹುಡುಕಿ, ಬೆಲೆ ಹೋಲಿಸಿ, ಬುಕ್ ಮಾಡಿ — ಕೇಳಿ ಸಾಕು.",subw:"ನಿಮ್ಮ ಕೆಲಸ, ಗಳಿಕೆ ಮತ್ತು ಲಭ್ಯತೆ ನಿರ್ವಹಿಸಿ — ಕೇಳಿ ಸಾಕು.",
 foot:"Confirm ಒತ್ತಿದ ನಂತರವೇ ಬುಕಿಂಗ್ ಆಗುತ್ತದೆ. OTP ಅಥವಾ ಕಾರ್ಡ್ ವಿವರ ಯಾರಿಗೂ ಹೇಳಬೇಡಿ.",lis:"ಆಲಿಸುತ್ತಿದ್ದೇನೆ… ದಯವಿಟ್ಟು ಮಾತನಾಡಿ",copy:"ನಕಲಿಸಿ",copied:"ನಕಲಿಸಲಾಗಿದೆ",listen:"ಕೇಳಿ",newc:"ಹೊಸ ಸಂಭಾಷಣೆ",close:"ಮುಚ್ಚಿ",spkon:"ಧ್ವನಿ ಉತ್ತರ: ಆನ್",spkoff:"ಧ್ವನಿ ಉತ್ತರ: ಆಫ್",stop:"ನಿಲ್ಲಿಸಿ",
 langset:"ಭಾಷೆಯನ್ನು ಕನ್ನಡಕ್ಕೆ ಬದಲಾಯಿಸಲಾಗಿದೆ",send:"ಕಳುಹಿಸಿ",voicefail:"ಕ್ಷಮಿಸಿ, ಧ್ವನಿ ಪ್ಲೇ ಮಾಡಲಾಗಲಿಲ್ಲ. ದಯವಿಟ್ಟು ಇಂಟರ್ನೆಟ್ ಪರಿಶೀಲಿಸಿ.",online:"ನಿಮ್ಮ ಸಾಧನದಲ್ಲಿ ಕನ್ನಡ ಧ್ವನಿ ಇಲ್ಲ, ಆದ್ದರಿಂದ ಆನ್‌ಲೈನ್ ಧ್ವನಿ ಬಳಸುತ್ತಿದ್ದೇನೆ.",novoice:"ಈ ಸಾಧನದಲ್ಲಿ ಕನ್ನಡ ಧ್ವನಿ ಇಲ್ಲ, ಆದ್ದರಿಂದ ಡೀಫಾಲ್ಟ್ ಧ್ವನಿ ಬಳಸುತ್ತೇನೆ.",micoff:"ಧ್ವನಿ ಬಳಸಲು ದಯವಿಟ್ಟು ಬ್ರೌಸರ್‌ನಲ್ಲಿ ಮೈಕ್ರೋಫೋನ್ ಅನುಮತಿ ನೀಡಿ.",nospeech:"ಕ್ಷಮಿಸಿ, ಕೇಳಿಸಲಿಲ್ಲ. ದಯವಿಟ್ಟು ಮತ್ತೊಮ್ಮೆ ಪ್ರಯತ್ನಿಸಿ.",micnet:"ಈ ಬ್ರೌಸರ್‌ನಲ್ಲಿ ಧ್ವನಿ ಟೈಪಿಂಗ್‌ಗೆ ಇಂಟರ್ನೆಟ್ ಬೇಕು. ನೀವು ಟೈಪ್ ಮಾಡಬಹುದು.",neterr:"ಕ್ಷಮಿಸಿ, ನೆಟ್‌ವರ್ಕ್ ಸಮಸ್ಯೆ ಇರುವಂತಿದೆ. ದಯವಿಟ್ಟು ಸಂಪರ್ಕ ಪರಿಶೀಲಿಸಿ ಮತ್ತೆ ಪ್ರಯತ್ನಿಸಿ.",nomic:"ಈ ಬ್ರೌಸರ್‌ನಲ್ಲಿ ಧ್ವನಿ ಇನ್‌ಪುಟ್ ಬೆಂಬಲಿತವಿಲ್ಲ."},
hi:{tip:"नमस्ते %s, मदद चाहिए? ✨",sub:"ऑनलाइन · English, ಕನ್ನಡ, हिन्दी",bar:"भाषा (टेक्स्ट और आवाज़)",ph:"अपना संदेश लिखें…",hi:"नमस्ते %s 👋",subc:"भरोसेमंद वर्कर खोजें, कीमतें तुलना करें और बुक करें — बस पूछिए।",subw:"अपने जॉब, कमाई और उपलब्धता संभालें — बस पूछिए।",
 foot:"बुकिंग Confirm दबाने के बाद ही होती है। OTP या कार्ड की जानकारी किसी को न बताएँ।",lis:"सुन रहा हूँ… कृपया बोलिए",copy:"कॉपी",copied:"कॉपी हो गया",listen:"सुनें",newc:"नई बातचीत",close:"बंद करें",spkon:"आवाज़ में जवाब: चालू",spkoff:"आवाज़ में जवाब: बंद",stop:"रोकें",
 langset:"भाषा हिन्दी में बदल दी गई है",send:"भेजें",voicefail:"क्षमा करें, आवाज़ नहीं चल सकी। कृपया इंटरनेट जाँचें।",online:"इस डिवाइस पर इस भाषा की आवाज़ नहीं है, इसलिए ऑनलाइन आवाज़ इस्तेमाल कर रहा हूँ।",novoice:"इस डिवाइस पर हिन्दी आवाज़ उपलब्ध नहीं है, इसलिए डिफ़ॉल्ट आवाज़ इस्तेमाल करूँगा।",micoff:"आवाज़ का उपयोग करने के लिए कृपया ब्राउज़र में माइक्रोफ़ोन की अनुमति दें।",nospeech:"क्षमा करें, मैं सुन नहीं पाया। कृपया फिर कोशिश करें।",micnet:"इस ब्राउज़र में वॉइस टाइपिंग के लिए इंटरनेट चाहिए। आप टाइप कर सकते हैं।",neterr:"क्षमा करें, नेटवर्क में समस्या लग रही है। कृपया कनेक्शन जाँचें और फिर कोशिश करें।",nomic:"इस ब्राउज़र में वॉइस इनपुट समर्थित नहीं है।"}};
function u(k,a){var s=(UI[lang]&&UI[lang][k])||UI.en[k]||k;return a!=null?s.replace("%s",a):s}

/* labels used inside cards */
var CL={kn:{"Worker":"ಕೆಲಸಗಾರ","Job":"ಕೆಲಸ","When":"ಯಾವಾಗ","Where":"ಎಲ್ಲಿ","Review your booking":"ನಿಮ್ಮ ಬುಕಿಂಗ್ ಪರಿಶೀಲಿಸಿ","Confirm booking":"ಬುಕಿಂಗ್ ದೃಢೀಕರಿಸಿ","Not now":"ಈಗ ಬೇಡ","Booking…":"ಬುಕ್ ಆಗುತ್ತಿದೆ…","Nothing is booked until you press Confirm.":"ನೀವು ದೃಢೀಕರಿಸುವವರೆಗೆ ಏನೂ ಬುಕ್ ಆಗುವುದಿಲ್ಲ.","Available":"ಲಭ್ಯ","Busy":"ಬ್ಯುಸಿ","View profile":"ಪ್ರೊಫೈಲ್ ನೋಡಿ","Book":"ಬುಕ್ ಮಾಡಿ","New":"ಹೊಸ","jobs done":"ಕೆಲಸ ಪೂರ್ಣ","exp":"ಅನುಭವ","Estimated price":"ಅಂದಾಜು ಬೆಲೆ","Typical":"ಸಾಮಾನ್ಯ","based on":"ಆಧಾರ:","past paid WorkOnnect jobs. This is an estimate, not a confirmed quotation.":"ಹಿಂದಿನ ಪಾವತಿಸಿದ WorkOnnect ಕೆಲಸಗಳು. ಇದು ಅಂದಾಜು ಮಾತ್ರ, ಖಚಿತ ಕೊಟೇಶನ್ ಅಲ್ಲ.","Request sent":"ವಿನಂತಿ ಕಳುಹಿಸಲಾಗಿದೆ","Track booking":"ಬುಕಿಂಗ್ ಟ್ರ್ಯಾಕ್ ಮಾಡಿ","Your bookings":"ನಿಮ್ಮ ಬುಕಿಂಗ್‌ಗಳು","Your jobs":"ನಿಮ್ಮ ಕೆಲಸಗಳು","Nothing here yet.":"ಇನ್ನೂ ಏನೂ ಇಲ್ಲ.","Booking":"ಬುಕಿಂಗ್","Cancel":"ರದ್ದು","A cancellation fee of ₹":"ರದ್ದು ಶುಲ್ಕ ₹","applies.":"ಅನ್ವಯಿಸುತ್ತದೆ.","No cancellation fee applies.":"ರದ್ದು ಶುಲ್ಕ ಇಲ್ಲ.","Continue on job page":"ಜಾಬ್ ಪುಟದಲ್ಲಿ ಮುಂದುವರಿಸಿ","Meet the developers":"ಡೆವಲಪರ್‌ಗಳನ್ನು ಭೇಟಿಯಾಗಿ","Developer":"ಡೆವಲಪರ್","Day":"ದಿನ","of":"/",
 "Paid jobs":"ಪಾವತಿಸಿದ ಕೆಲಸ","Net earnings":"ನಿವ್ವಳ ಗಳಿಕೆ","Tips":"ಟಿಪ್ಸ್","Already settled":"ಈಗಾಗಲೇ ಪಾವತಿ","Completed":"ಪೂರ್ಣಗೊಂಡಿದೆ","In progress / upcoming":"ಪ್ರಗತಿಯಲ್ಲಿ / ಮುಂದಿನ","Cancelled":"ರದ್ದುಗೊಂಡಿದೆ","Average rating":"ಸರಾಸರಿ ರೇಟಿಂಗ್","Rating":"ರೇಟಿಂಗ್","Jobs done":"ಪೂರ್ಣ ಕೆಲಸಗಳು","Verified":"ಪರಿಶೀಲಿತ","Missing":"ಬಾಕಿ","Yes":"ಹೌದು","No":"ಇಲ್ಲ","This month":"ಈ ತಿಂಗಳು","Profile strength":"ಪ್ರೊಫೈಲ್ ಬಲ","Earnings":"ಗಳಿಕೆ","Earnings · all time":"ಗಳಿಕೆ · ಒಟ್ಟು","Nothing 🎉":"ಏನೂ ಇಲ್ಲ 🎉",
 "category":"ವಿಭಾಗ","location":"ಸ್ಥಳ","experience":"ಅನುಭವ","photo":"ಫೋಟೋ","rate":"ದರ","hours":"ಸಮಯ","verification":"ಪರಿಶೀಲನೆ","requested":"ವಿನಂತಿ","assigned":"ನಿಯೋಜಿತ","in progress":"ಪ್ರಗತಿಯಲ್ಲಿ","completed":"ಪೂರ್ಣ","cancelled":"ರದ್ದು","open":"ಮುಕ್ತ"},
hi:{"Worker":"वर्कर","Job":"काम","When":"कब","Where":"कहाँ","Review your booking":"अपनी बुकिंग जाँचें","Confirm booking":"बुकिंग पक्की करें","Not now":"अभी नहीं","Booking…":"बुक हो रहा है…","Nothing is booked until you press Confirm.":"जब तक आप Confirm नहीं दबाते, कुछ बुक नहीं होता।","Available":"उपलब्ध","Busy":"व्यस्त","View profile":"प्रोफ़ाइल देखें","Book":"बुक करें","New":"नया","jobs done":"काम पूरे","exp":"अनुभव","Estimated price":"अनुमानित कीमत","Typical":"सामान्य","based on":"आधार:","past paid WorkOnnect jobs. This is an estimate, not a confirmed quotation.":"पहले के भुगतान किए गए WorkOnnect जॉब। यह सिर्फ़ अनुमान है, पक्का कोटेशन नहीं।","Request sent":"अनुरोध भेजा गया","Track booking":"बुकिंग ट्रैक करें","Your bookings":"आपकी बुकिंग","Your jobs":"आपके जॉब","Nothing here yet.":"अभी यहाँ कुछ नहीं है।","Booking":"बुकिंग","Cancel":"रद्द","A cancellation fee of ₹":"रद्द करने का शुल्क ₹","applies.":"लागू होगा।","No cancellation fee applies.":"कोई रद्दीकरण शुल्क नहीं।","Continue on job page":"जॉब पेज पर जारी रखें","Meet the developers":"डेवलपर्स से मिलिए","Developer":"डेवलपर","Day":"दिन","of":"/",
 "Paid jobs":"भुगतान किए गए जॉब","Net earnings":"शुद्ध कमाई","Tips":"टिप","Already settled":"पहले ही चुकता","Completed":"पूरे हुए","In progress / upcoming":"जारी / आने वाले","Cancelled":"रद्द","Average rating":"औसत रेटिंग","Rating":"रेटिंग","Jobs done":"पूरे किए गए काम","Verified":"वेरिफाइड","Missing":"बाकी","Yes":"हाँ","No":"नहीं","This month":"इस महीने","Profile strength":"प्रोफ़ाइल की मजबूती","Earnings":"कमाई","Earnings · all time":"कमाई · कुल","Nothing 🎉":"कुछ नहीं 🎉",
 "category":"श्रेणी","location":"स्थान","experience":"अनुभव","photo":"फ़ोटो","rate":"दर","hours":"समय","verification":"सत्यापन","requested":"अनुरोध","assigned":"सौंपा गया","in progress":"जारी","completed":"पूरा","cancelled":"रद्द","open":"खुला"}};
function tr(s){s=String(s);return lang=="en"?s:((CL[lang]||{})[s]||s)}

/* quick-reply buttons: the server sends English commands, we show them in the chosen language */
var CAT={Electrician:["ಎಲೆಕ್ಟ್ರಿಷಿಯನ್","इलेक्ट्रीशियन"],Plumber:["ಪ್ಲಂಬರ್","प्लंबर"],Painter:["ಪೇಂಟರ್","पेंटर"],Carpenter:["ಕಾರ್ಪೆಂಟರ್","कारपेंटर"],Cleaner:["ಕ್ಲೀನರ್","क्लीनर"],Cleaning:["ಕ್ಲೀನಿಂಗ್","क्लीनिंग"],"AC Technician":["ಎಸಿ ಟೆಕ್ನಿಷಿಯನ್","एसी टेक्नीशियन"],Mechanic:["ಮೆಕ್ಯಾನಿಕ್","मैकेनिक"],Gardener:["ತೋಟಗಾರ","माली"],Cook:["ಅಡುಗೆಯವರು","कुक"],Driver:["ಡ್ರೈವರ್","ड्राइवर"],General:["ಸಾಮಾನ್ಯ","सामान्य"]};
var CH={"Find an electrician near me":["ಹತ್ತಿರದ ಎಲೆಕ್ಟ್ರಿಷಿಯನ್ ಹುಡುಕಿ","पास का इलेक्ट्रीशियन खोजें"],"Estimate a plumbing job":["ಪ್ಲಂಬಿಂಗ್ ಕೆಲಸದ ಬೆಲೆ ಅಂದಾಜು","प्लंबिंग काम का अनुमान"],"Show my bookings":["ನನ್ನ ಬುಕಿಂಗ್‌ಗಳು","मेरी बुकिंग दिखाएँ"],"Find a verified painter":["ಪರಿಶೀಲಿತ ಪೇಂಟರ್ ಹುಡುಕಿ","वेरिफाइड पेंटर खोजें"],
"Show my new job requests":["ನನ್ನ ಹೊಸ ಕೆಲಸದ ವಿನಂತಿಗಳು","मेरे नए जॉब अनुरोध"],"How much did I earn this month?":["ಈ ತಿಂಗಳು ಎಷ್ಟು ಗಳಿಸಿದೆ?","इस महीने कितना कमाया?"],"How am I performing?":["ನನ್ನ ಕಾರ್ಯಕ್ಷಮತೆ ಹೇಗಿದೆ?","मेरा प्रदर्शन कैसा है?"],"Improve my profile":["ಪ್ರೊಫೈಲ್ ಸುಧಾರಿಸಿ","प्रोफ़ाइल सुधारें"],
"Set availability Mon-Sat 9am to 6pm":["ಲಭ್ಯತೆ ಸೋಮ–ಶನಿ 9–6","उपलब्धता सोम–शनि 9–6"],"Mon-Sat 9am to 6pm":["ಸೋಮ–ಶನಿ 9–6","सोम–शनि 9–6"],"Weekdays 10am to 7pm":["ವಾರದ ದಿನಗಳು 10–7","सप्ताह के दिन 10–7"],"Every day 8am to 8pm":["ಪ್ರತಿದಿನ 8–8","हर दिन 8–8"],"Earnings all time":["ಒಟ್ಟು ಗಳಿಕೆ","कुल कमाई"],"Completed jobs":["ಪೂರ್ಣಗೊಂಡ ಕೆಲಸಗಳು","पूरे हुए काम"],
"How does WorkOnnect work?":["WorkOnnect ಹೇಗೆ ಕೆಲಸ ಮಾಡುತ್ತದೆ?","WorkOnnect कैसे काम करता है?"],"How do payments work?":["ಪಾವತಿ ಹೇಗೆ ನಡೆಯುತ್ತದೆ?","भुगतान कैसे होता है?"],"Is it safe?":["ಇದು ಸುರಕ್ಷಿತವೇ?","क्या यह सुरक्षित है?"],
"Today 6 PM":["ಇಂದು ಸಂಜೆ 6","आज शाम 6 बजे"],"Tomorrow 10 AM":["ನಾಳೆ ಬೆಳಿಗ್ಗೆ 10","कल सुबह 10 बजे"],"Tomorrow 5 PM":["ನಾಳೆ ಸಂಜೆ 5","कल शाम 5 बजे"],"Not now":["ಈಗ ಬೇಡ","अभी नहीं"],"Repair work":["ದುರಸ್ತಿ ಕೆಲಸ","मरम्मत का काम"],"Installation":["ಅಳವಡಿಕೆ","इंस्टॉलेशन"]};
function cat(x){return lang=="en"||!CAT[x]?x:CAT[x][lang=="kn"?0:1]}
function chipLabel(v){if(lang=="en")return v;var i=lang=="kn"?0:1,m;if(CH[v])return CH[v][i];
 if(CAT[v])return cat(v);
 if(m=v.match(/^(.+) service$/))return cat(m[1])+(i?" सेवा":" ಸೇವೆ");
 if(m=v.match(/^Find a verified (.+)$/))return i?"वेरिफाइड "+cat(m[1])+" खोजें":"ಪರಿಶೀಲಿತ "+cat(m[1])+" ಹುಡುಕಿ";
 if(m=v.match(/^Find (.+) \(any area\)$/))return i?cat(m[1])+" खोजें (कोई भी क्षेत्र)":cat(m[1])+" ಹುಡುಕಿ (ಯಾವುದೇ ಪ್ರದೇಶ)";
 if(m=v.match(/^Find (.+)$/))return i?cat(m[1])+" खोजें":cat(m[1])+" ಹುಡುಕಿ";
 if(m=v.match(/^Only verified (.+)$/))return i?"सिर्फ़ वेरिफाइड "+cat(m[1]):"ಪರಿಶೀಲಿತ "+cat(m[1])+" ಮಾತ್ರ";
 if(m=v.match(/^Estimate price for (.+)$/))return i?cat(m[1])+" की कीमत का अनुमान":cat(m[1])+" ಬೆಲೆ ಅಂದಾಜು";
 if(m=v.match(/^Book worker #(\d) \((.+)\)$/))return i?"#"+m[1]+" "+m[2]+" को बुक करें":"#"+m[1]+" "+m[2]+" ಅವರನ್ನು ಬುಕ್ ಮಾಡಿ";
 if(m=v.match(/^Show profile #(\d)$/))return i?"#"+m[1]+" की प्रोफ़ाइल देखें":"#"+m[1]+" ಪ್ರೊಫೈಲ್ ನೋಡಿ";
 if(m=v.match(/^Cancel booking #(\d+)$/))return i?"बुकिंग #"+m[1]+" रद्द करें":"ಬುಕಿಂಗ್ #"+m[1]+" ರದ್ದು ಮಾಡಿ";
 return v}
var START={customer:[["⚡","Find an electrician near me"],["🔧","Estimate a plumbing job"],["📅","Show my bookings"],["🎨","Find a verified painter"]],
 worker:[["📋","Show my new job requests"],["💰","How much did I earn this month?"],["📈","How am I performing?"],["✨","Improve my profile"]],
 admin:[["💡","How does WorkOnnect work?"],["💳","How do payments work?"],["🛡️","Is it safe?"]]};

/* ---------- small helpers ---------- */
function h(t,c,x){var e=document.createElement(t);if(c)e.className=c;if(x!=null)e.textContent=x;return e}
function fmt(el,t){String(t).split(/(\*\*[^*]+\*\*|\*[^*\n]+\*)/).forEach(function(p){
 if(/^\*\*[^*]+\*\*$/.test(p))el.appendChild(h("b",0,p.slice(2,-2)));
 else if(/^\*[^*\n]+\*$/.test(p))el.appendChild(h("em",0,p.slice(1,-1)));
 else if(p)el.appendChild(document.createTextNode(p))})}
function down(){box.scrollTop=box.scrollHeight}
function save(){try{sessionStorage.setItem(KEY,JSON.stringify(hist.slice(-30)))}catch(e){}}
function clock(){var n=new Date(),p=function(x){return(x<10?"0":"")+x};return p(n.getHours())+":"+p(n.getMinutes())}
var tt;function toast(m){var t=$("waiToast");t.textContent=m;t.classList.add("show");clearTimeout(tt);tt=setTimeout(function(){t.classList.remove("show")},3600)}
function openChat(o){root.classList.toggle("open",o);if(o){$("waiTip").classList.remove("show");setTimeout(function(){tx.focus()},300);down()}}
$("waiFab").onclick=function(){openChat(!root.classList.contains("open"))};$("waiClose").onclick=function(){openChat(false)};
document.addEventListener("keydown",function(e){if(e.key=="Escape"&&root.classList.contains("open"))openChat(false)});

/* ---------- quick replies ---------- */
function chips(list){lastChips=list||[];strip.querySelectorAll(".wai-chip").forEach(function(x){x.remove()});
 lastChips.forEach(function(v){var l=chipLabel(v),b=h("button","wai-chip",l);b.onclick=function(){ask(v,l)};strip.appendChild(b)});strip.scrollLeft=0;strip.classList.toggle("has",lastChips.length>0)}
function welcome(){box.innerHTML="";var w=h("div","wai-hello"),o=h("div","orb");o.innerHTML=$("waiFab").querySelector("svg").outerHTML;
 w.append(o,h("h3",0,u("hi",NAME)),h("p",0,ROLE=="worker"?u("subw"):u("subc")));box.appendChild(w);
 var g=h("div","wai-sg");START[ROLE].forEach(function(s){var l=chipLabel(s[1]),b=h("button");b.append(h("span",0,s[0]),document.createTextNode(l));b.onclick=function(){ask(s[1],l)};g.appendChild(b)});
 var m=h("div","wai-m a");m.style.maxWidth="100%";m.appendChild(g);box.appendChild(m);chips([])}

/* ---------- messages ---------- */
var ICON={copy:'<svg viewBox="0 0 24 24"><rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h10"/></svg>',lis:'<svg viewBox="0 0 24 24"><path d="M11 5L6 9H3v6h3l5 4zM16 9a4 4 0 0 1 0 6"/></svg>'};
function bubble(role,text,err,ts){var m=h("div","wai-m "+(role=="user"?"u":"a")),b=h("div","wai-b"+(err?" err":""));fmt(b,text);m.appendChild(b);
 if(role!="user"&&!err){var a=h("div","wai-acts"),c=h("button"),l=h("button");c.innerHTML=ICON.copy+"<span>"+u("copy")+"</span>";l.innerHTML=ICON.lis+"<span>"+u("listen")+"</span>";
  c.onclick=function(){try{navigator.clipboard.writeText(String(text).replace(/\*/g,""));c.lastChild.textContent=u("copied")}catch(e){}};
  l.onclick=function(){say(text,true)};a.append(l,c);m.appendChild(a)}
 m.appendChild(h("div","wai-t",ts||clock()));box.appendChild(m);down();return m}
function typing(){var m=h("div","wai-m a"),b=h("div","wai-b wai-typing");b.append(h("i"),h("i"),h("i"));m.appendChild(b);box.appendChild(m);down();return m}
function reveal(text,cb){var m=bubble("assistant",""),b=m.querySelector(".wai-b");b.textContent="";var plain=String(text).replace(/\*/g,""),w=plain.split(" "),i=0;
 var bs=m.querySelectorAll(".wai-acts button");if(bs.length>1){bs[0].onclick=function(){say(text,true)};bs[1].onclick=function(){try{navigator.clipboard.writeText(plain);bs[1].lastChild.textContent=u("copied")}catch(e){}}}
 if(text.length>500||matchMedia("(prefers-reduced-motion:reduce)").matches){fmt(b,text);cb&&cb();return}
 function done(){clearInterval(revTimer);revTimer=null;revealing=false;finishRev=null;b.textContent="";fmt(b,text);sendUI();down();cb&&cb()}
 revealing=true;finishRev=done;sendUI();
 revTimer=setInterval(function(){b.textContent+=(i?" ":"")+w[i++];down();if(i>=w.length)done()},22)}

/* ---------- cards ---------- */
function card(c){var d=h("div","wai-card");
 if(c.type=="workers"){c.items.forEach(function(w){var r=h("div","wai-wk"),p=h("div","wai-pic");if(w.photo){var im=h("img");im.src=w.photo;im.alt="";p.appendChild(im)}else p.textContent=(w.name||"?")[0].toUpperCase();
  var i=h("div");i.style.flex=1;var n=h("b",0,w.name);if(w.verified){var v=h("span");v.innerHTML='<svg class="wai-vf" viewBox="0 0 24 24"><path d="M12 2l2.4 2 3.1-.3 1 3 2.7 1.6-1 3 1 3-2.7 1.6-1 3-3.1-.3L12 22l-2.4-2-3.1.3-1-3L2.8 15.7l1-3-1-3 2.7-1.6 1-3 3.1.3z" fill="currentColor" stroke="none"/><path d="M8.5 12l2.5 2.5 4.5-5" stroke="#fff"/></svg>';v.style.color="var(--b)";n.appendChild(v)}
  var s=h("div","wai-st",w.rating==null?tr("New"):"★ "+w.rating);s.appendChild(h("em",0,"  · "+w.jobs_done+" "+tr("jobs done")));var t=h("div","wai-tags");
  [cat(w.category),w.location,w.experience?w.experience+" "+tr("exp"):null,w.rate?"₹"+w.rate:null].forEach(function(x){if(x)t.appendChild(h("span",0,x))});if(w.available!=null)t.appendChild(h("span",w.available?"ok":"",tr(w.available?"Available":"Busy")));
  var bt=h("div","wai-btns"),a=h("a","wai-btn g",tr("View profile"));a.href="worker.php?id="+w.id;var k=h("button","wai-btn p",tr("Book"));
  k.onclick=function(){ask("Book worker #"+w.index,tr("Book")+" · "+w.name)};bt.append(a,k);i.append(n,s,t,bt);r.append(p,i);d.appendChild(r)})}
 else if(c.type=="estimate"){d.appendChild(h("h4",0,tr("Estimated price")+" · "+cat(c.category)));d.appendChild(h("div","wai-big","₹"+c.low+" – ₹"+c.high));d.appendChild(h("div","wai-note",tr("Typical")+" ₹"+c.avg+" · "+tr("based on")+" "+c.samples+" "+tr("past paid WorkOnnect jobs. This is an estimate, not a confirmed quotation.")))}
 else if(c.type=="confirm"){d.appendChild(h("h4",0,tr("Review your booking")));[[tr("Worker"),c.worker],[tr("Job"),c.title],[tr("When"),String(c.slot).replace("T"," ")],[tr("Where"),c.location||"–"]].forEach(function(r){var x=h("div","wai-row");x.append(h("span",0,r[0]),h("b",0,r[1]));d.appendChild(x)});
  var bt=h("div","wai-btns");bt.style.marginTop="10px";var ok=h("button","wai-btn p",tr("Confirm booking")),no=h("button","wai-btn g",tr("Not now"));
  ok.onclick=function(){ok.disabled=no.disabled=true;ok.textContent=tr("Booking…");post({action:"confirm_booking",token:c.token}).then(function(r){
   if(r.error){ok.disabled=no.disabled=false;ok.textContent=tr("Confirm booking");bubble("assistant",r.error,true);return}
   bt.remove();(r.cards||[]).forEach(function(k){box.appendChild(wrap(card(k)))});reveal(r.reply,function(){chips(r.chips)});hist.push({r:"a",t:r.reply,ts:clock()});save();say(r.reply)})};
  no.onclick=function(){bt.remove();ask("Not now",tr("Not now"))};bt.append(ok,no);d.appendChild(bt);d.appendChild(h("div","wai-note",tr("Nothing is booked until you press Confirm.")))}
 else if(c.type=="done"){d.appendChild(h("h4",0,"✅ "+tr("Request sent")+" · #"+c.job_id));d.appendChild(h("div","wai-note",c.title+" · "+c.worker+" · "+String(c.slot).replace("T"," ")));var a=h("a","wai-btn p",tr("Track booking"));a.href="job.php?id="+c.job_id;a.style.marginTop="8px";d.appendChild(a)}
 else if(c.type=="bookings"){d.appendChild(h("h4",0,tr(c.title)));if(!c.items.length)d.appendChild(h("div","wai-note",tr("Nothing here yet.")));c.items.forEach(function(j){var r=h("div","wai-bk"),l=h("div");l.append(h("b",0,"#"+j.id+" "+j.title),h("div","wai-note",[j.person,j.slot?String(j.slot).replace("T"," "):null,j.price?"₹"+j.price:null].filter(Boolean).join(" · ")));
  var s=h("a","wai-pill "+j.status,tr(String(j.status).replace("_"," ")));s.href="job.php?id="+j.id;r.append(l,s);d.appendChild(r)})}
 else if(c.type=="stats"){d.appendChild(h("h4",0,String(c.title).replace(/^Earnings/,tr("Earnings")).replace(" · all time"," · "+(lang=="en"?"all time":lang=="kn"?"ಒಟ್ಟು":"कुल")).replace(/^Profile strength$|^This month$/,function(x){return tr(x)})));
  c.rows.forEach(function(r){var x=h("div","wai-row"),v=String(r[1]).split(", ").map(tr).join(", ");x.append(h("span",0,tr(r[0])),h("b",0,v));d.appendChild(x)})}
 else if(c.type=="cancel"){d.appendChild(h("h4",0,tr("Cancel")+" #"+c.job_id+" · "+c.title));d.appendChild(h("div","wai-note",c.fee?tr("A cancellation fee of ₹")+c.fee+" "+tr("applies."):tr("No cancellation fee applies.")));var a=h("a","wai-btn p",tr("Continue on job page"));a.href="job.php?id="+c.job_id;a.style.marginTop="8px";d.appendChild(a)}
 else if(c.type=="team"){d.appendChild(h("h4",0,tr("Meet the developers")));var g=h("div","wai-team");c.items.forEach(function(q){var t=h("div","wai-dev"),pc=h("div","wai-dpic");
  if(q.photo){var im=h("img");im.src=q.photo;im.alt=q.name;im.onerror=function(){im.remove();pc.textContent=q.name[0]};pc.appendChild(im)}else pc.textContent=q.name[0];
  t.append(pc,h("b",0,q.name),h("small",0,tr("Developer")));g.appendChild(t)});d.appendChild(g)}
 else if(c.type=="clock"){d.className="wai-card wai-clock";var cl=h("div","wk-l"),tm=h("div","wk-t"),dt=h("div","wk-d"),ch=h("div","wk-c");cl.append(tm,dt,ch);
  var ticks="";for(var q=0;q<12;q++)ticks+='<line class="tk" x1="50" y1="6" x2="50" y2="'+(q%3?9:12)+'" transform="rotate('+q*30+' 50 50)"/>';
  var an=h("div","wk-a");an.innerHTML='<svg viewBox="0 0 100 100"><circle class="wk-f" cx="50" cy="50" r="46"/>'+ticks+'<line class="hh" x1="50" y1="50" x2="50" y2="29"/><line class="mh" x1="50" y1="50" x2="50" y2="17"/><line class="sh" x1="50" y1="56" x2="50" y2="12"/><circle class="dot" cx="50" cy="50" r="3"/></svg>';
  d.append(cl,an);var iv;
  function tick(first){if(!first&&!document.body.contains(d)){clearInterval(iv);return}
   var n=new Date(),H=n.getHours(),M=n.getMinutes(),S=n.getSeconds(),p2=function(x){return(x<10?"0":"")+x};
   tm.textContent=(H%12||12)+":"+p2(M);tm.appendChild(h("small",0,p2(S)+" "+(H<12?"AM":"PM")));
   try{dt.textContent=n.toLocaleDateString(LC[lang],{weekday:"long",day:"numeric",month:"long",year:"numeric"})}catch(e){dt.textContent=n.toDateString()}
   if(first){var y=n.getFullYear(),doy=Math.floor((n-new Date(y,0,0))/864e5),leap=(y%4==0&&(y%100!=0||y%400==0)),tz="";try{tz=Intl.DateTimeFormat().resolvedOptions().timeZone}catch(e){}
    if(tz)ch.appendChild(h("span",0,tz.replace("_"," ")));ch.appendChild(h("span",0,tr("Day")+" "+doy+" "+tr("of")+" "+(leap?366:365)))}
   an.querySelector(".hh").setAttribute("transform","rotate("+((H%12+M/60)*30)+" 50 50)");an.querySelector(".mh").setAttribute("transform","rotate("+((M+S/60)*6)+" 50 50)");an.querySelector(".sh").setAttribute("transform","rotate("+(S*6)+" 50 50)")}
  iv=setInterval(tick,1000);tick(true)}
 return d}
function wrap(n){var m=h("div","wai-m a");m.style.maxWidth="100%";m.appendChild(n);return m}

function post(b){return fetch("ai_chat.php",{method:"POST",headers:{"Content-Type":"application/json","X-CSRF":CSRF},body:JSON.stringify(b),credentials:"same-origin"}).then(function(r){return r.json()}).catch(function(){return{error:u("neterr")}})}

/* ---------- voice output: local voice first, online voice if the device has none (needed for Kannada on most PCs) ---------- */
var VOICES=[],warned={},aud=null,spkToken=0;function loadV(){VOICES=window.speechSynthesis?speechSynthesis.getVoices():[]}
if(window.speechSynthesis){loadV();speechSynthesis.onvoiceschanged=loadV}
var EMO=/[\u{1F300}-\u{1FAFF}\u{2600}-\u{27BF}\u{FE0F}\u{200D}\u{20E3}\u{2B50}]/gu;
function pickVoice(){var l=LC[lang].toLowerCase(),pre=l.split("-")[0];
 var pool=VOICES.filter(function(v){return v.lang.toLowerCase().replace("_","-")==l});
 if(!pool.length)pool=VOICES.filter(function(v){return v.lang.toLowerCase().indexOf(pre)==0});
 if(!pool.length&&lang=="en")pool=VOICES.filter(function(v){return /^en/i.test(v.lang)});
 var best=/(natural|online|neural)/i,soft=/(neerja|swara|heera|sapna|kalpana|veena|lekha|priya|aria|jenny|samantha|google|female|ravi)/i;
 return pool.filter(function(v){return best.test(v.name)&&soft.test(v.name)})[0]||pool.filter(function(v){return best.test(v.name)})[0]||pool.filter(function(v){return soft.test(v.name)})[0]||pool[0]||null}
function prep(t){var w={en:["rupees","number"],kn:["ರೂಪಾಯಿ","ಸಂಖ್ಯೆ"],hi:["रुपये","नंबर"]}[lang];
 return String(t).replace(/\*/g,"").replace(EMO,"").replace(/•/g,", ").replace(/₹\s?(\d[\d,]*)/g,"$1 "+w[0]).replace(/#(\d+)/g,w[1]+" $1").replace(/\s*\n+\s*/g,". ").replace(/\s+/g," ").trim()}
function setSpeaking(v){speaking=v;spkB.classList.toggle("on",v||speak);sendUI()}
function stopSpeak(){spkToken++;if(window.speechSynthesis)speechSynthesis.cancel();if(aud){try{aud.pause()}catch(e){}aud=null}setSpeaking(false)}
function stopAll(){stopSpeak();if(finishRev)finishRev()}
function chunkText(text,max){var out=[];(text.match(/[^.!?।]+[.!?।]*/g)||[text]).forEach(function(p){p=p.trim();while(p.length>max){var k=p.lastIndexOf(" ",max);if(k<40)k=max;out.push(p.slice(0,k).trim());p=p.slice(k).trim()}if(p)out.push(p)});return out}
function playOnline(text,tok){var q=chunkText(text,170),i=0;setSpeaking(true);
 function fail(){if(tok!=spkToken)return;setSpeaking(false);toast(u("voicefail"))}
 (function next(){if(tok!=spkToken)return;if(i>=q.length){setSpeaking(false);return}
  var a=new Audio("https://translate.google.com/translate_tts?ie=UTF-8&client=tw-ob&tl="+lang+"&q="+encodeURIComponent(q[i++]));aud=a;a.playbackRate=.95;a.onended=next;a.onerror=fail;
  var p=a.play();if(p&&p.catch)p.catch(fail)})()}
function say(t,force){if(!(speak||force||viaVoice))return;stopSpeak();var text=prep(t);if(!text)return;var tok=spkToken,v=window.speechSynthesis?pickVoice():null;
 if(!v&&lang!="en"){if(!warned[lang]){warned[lang]=1;toast(u("online"))}playOnline(text,tok);return}
 if(!window.speechSynthesis)return;
 var parts=(text.match(/[^.!?।]+[.!?।]*/g)||[text]).map(function(s){return s.trim()}).filter(Boolean);
 parts.forEach(function(p,idx){var x=new SpeechSynthesisUtterance(p);x.lang=v?v.lang:LC[lang];if(v)x.voice=v;x.rate=lang=="en"?.95:.9;x.pitch=1.05;x.volume=1;
  if(idx==0)x.onstart=function(){if(tok==spkToken)setSpeaking(true)};
  if(idx==parts.length-1)x.onend=x.onerror=function(){if(tok==spkToken)setSpeaking(false)};
  speechSynthesis.speak(x)})}
var ICO_SEND='<svg viewBox="0 0 24 24" style="stroke-width:2.4"><path d="M12 19V5M5 12l7-7 7 7"/></svg>',ICO_STOP='<svg viewBox="0 0 24 24"><rect x="6.5" y="6.5" width="11" height="11" rx="3" fill="currentColor" stroke="none"/></svg>';
function sendUI(){var st=speaking||revealing;send.classList.toggle("stop",st);send.innerHTML=st?ICO_STOP:ICO_SEND;send.disabled=busy&&!st;send.title=send.ariaLabel=st?u("stop"):u("send")}
var SPK={on:'<svg viewBox="0 0 24 24"><path d="M11 5L6 9H3v6h3l5 4zM16 9a4 4 0 0 1 0 6M19 6a8 8 0 0 1 0 12"/></svg>',off:'<svg viewBox="0 0 24 24"><path d="M11 5L6 9H3v6h3l5 4zM16 9l5 6M21 9l-5 6"/></svg>'};
function spkUI(){spkB.innerHTML=speak?SPK.on:SPK.off;spkB.classList.toggle("on",speak);spkB.setAttribute("aria-pressed",speak);spkB.title=spkB.ariaLabel=speak?u("spkon"):u("spkoff")}
spkB.onclick=function(){speak=!speak;try{localStorage.setItem("wai_spk",speak?"1":"0")}catch(e){}if(!speak)stopSpeak();spkUI();toast(speak?u("spkon"):u("spkoff"))};

/* ---------- sending ---------- */
function ask(t,label){t=(t||"").trim();if(!t||busy)return;if(!hist.length)box.innerHTML="";stopAll();chips([]);
 var shown=label||t,ts=clock();bubble("user",shown,false,ts);hist.push({r:"u",t:shown,ts:ts});tx.value="";grow();busy=true;sendUI();var ty=typing(),spoken=viaVoice;
 post({message:t,lang:lang}).then(function(r){ty.remove();busy=false;sendUI();viaVoice=false;
  if(r.error){bubble("assistant",r.error,true);return}
  reveal(r.reply,function(){(r.cards||[]).forEach(function(c){box.appendChild(wrap(card(c)))});chips(r.chips);down()});hist.push({r:"a",t:r.reply,ts:clock()});save();
  var extra="";if((r.cards||[]).some(function(c){return c.type=="clock"})){var n=new Date();try{extra=" "+n.toLocaleTimeString(LC[lang],{hour:"numeric",minute:"2-digit",hour12:true})+". "+n.toLocaleDateString(LC[lang],{weekday:"long",day:"numeric",month:"long",year:"numeric"})}catch(e){}}
  viaVoice=spoken;say(r.reply+extra);viaVoice=false})}
send.onclick=function(){if(speaking||revealing){stopAll();return}ask(tx.value)};tx.onkeydown=function(e){if(e.key=="Enter"&&!e.shiftKey){e.preventDefault();if(!speaking&&!revealing)ask(tx.value)}};
function grow(){tx.style.height="auto";tx.style.height=Math.min(tx.scrollHeight,96)+"px"}tx.oninput=grow;
$("waiNew").onclick=function(){stopAll();hist=[];save();post({action:"reset"});welcome()};

/* ---------- voice input ---------- */
var SR=window.SpeechRecognition||window.webkitSpeechRecognition,rec=null;
if(!SR){mic.onclick=function(){toast(u("nomic"))}}else{rec=new SR();rec.interimResults=true;rec.continuous=false;rec.lang=LC[lang];
 rec.onstart=function(){mic.classList.add("rec");tx.placeholder=u("lis")};
 rec.onresult=function(e){var s="";for(var i=0;i<e.results.length;i++)s+=e.results[i][0].transcript;tx.value=s;grow();if(e.results[e.results.length-1].isFinal){viaVoice=true;ask(s)}};
 rec.onend=function(){mic.classList.remove("rec");tx.placeholder=u("ph")};
 rec.onerror=function(e){mic.classList.remove("rec");tx.placeholder=u("ph");if(e.error=="not-allowed"||e.error=="service-not-allowed")toast(u("micoff"));else if(e.error=="no-speech"||e.error=="aborted")toast(u("nospeech"));else if(e.error=="network")toast(u("micnet"))};
 mic.onclick=function(){if(mic.classList.contains("rec")){rec.stop();return}stopSpeak();try{rec.lang=LC[lang];rec.start()}catch(e){}}}

/* ---------- language switch ---------- */
function applyLang(){$("waiTip").textContent=u("tip",NAME);$("waiSub").textContent=u("sub");$("waiBarL").textContent=u("bar");tx.placeholder=u("ph");$("waiFoot").textContent=u("foot");
 sendUI();$("waiNew").title=$("waiNew").ariaLabel=u("newc");$("waiClose").title=$("waiClose").ariaLabel=u("close");mic.title=mic.ariaLabel=u("lis").replace("…","");spkUI();
 root.querySelectorAll(".lg button").forEach(function(x){x.classList.toggle("on",x.dataset.l==lang)});if(rec)rec.lang=LC[lang]}
root.querySelectorAll(".lg button").forEach(function(b){b.onclick=function(){if(lang==b.dataset.l)return;lang=b.dataset.l;try{localStorage.setItem("wai_lang",lang)}catch(e){}stopSpeak();applyLang();
 if(!hist.length)welcome();else chips(lastChips);toast(u("langset"))}});

/* ---------- attention pop-up beside the logo ---------- */
var tipEl=$("waiTip"),tipT=$("waiTipT"),tipI=0,tipTimer=null;
var TIPS={
 en:["Hi %s, need any help? ✨",ROLE=="worker"?"Questions about jobs or earnings? 💰":"Looking for a trusted worker? 🔧"],
 kn:["ನಮಸ್ಕಾರ %s, ಸಹಾಯ ಬೇಕೆ? ✨",ROLE=="worker"?"ಕೆಲಸ ಅಥವಾ ಗಳಿಕೆ ಬಗ್ಗೆ ಪ್ರಶ್ನೆ ಇದೆಯೇ? 💰":"ವಿಶ್ವಾಸಾರ್ಹ ಕೆಲಸಗಾರ ಬೇಕೆ? 🔧"],
 hi:["नमस्ते %s, मदद चाहिए? ✨",ROLE=="worker"?"जॉब या कमाई के बारे में सवाल? 💰":"भरोसेमंद वर्कर चाहिए? 🔧"]};
function tipText(){var a=TIPS[lang]||TIPS.en;tipT.textContent=a[tipI%a.length].replace("%s",NAME)}
function tipGet(k){try{return sessionStorage.getItem(k)}catch(e){return null}}
function tipSet(k,v){try{sessionStorage.setItem(k,v)}catch(e){}}
function tipHide(){tipEl.classList.remove("show")}
function tipShow(){
 if(root.classList.contains("open")||tipGet("wai_tipoff")=="1")return;
 var n=+tipGet("wai_tipn")||0;if(n>=6)return;      /* max 6 pop-ups per browser session */
 tipSet("wai_tipn",n+1);tipI=n;tipText();tipEl.classList.add("show");
 clearTimeout(tipTimer);tipTimer=setTimeout(tipHide,9000)}
$("waiTipX").onclick=function(e){e.stopPropagation();e.preventDefault();tipSet("wai_tipoff","1");tipHide()};
setTimeout(tipShow,2500);setInterval(tipShow,60000);

/* hide the old floating "Chat" button so this assistant takes its place */
var oldCb=document.getElementById("cb");if(oldCb)oldCb.style.display="none";
// applyLang();
tipText();
try{hist=JSON.parse(sessionStorage.getItem(KEY)||"[]")}catch(e){hist=[]}
if(hist.length){box.innerHTML="";hist.forEach(function(m){bubble(m.r=="u"?"user":"assistant",m.t,false,m.ts||"")})}else welcome();
})();
</script>
</div>
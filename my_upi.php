<?php // My UPI ID. Worker: receives the worker's share directly from customers (split payment) and is the account the admin pays when settling. Admin: receives the platform fee (commission) and QR payments.
require "config.php";
need();
$u = me();
if ($u["role"] == "customer") {
    header("Location: dashboard.php");
    exit();
}
$msg = "";
$err = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $v = trim($_POST["upi"] ?? "");
    if ($v !== "" && !validUpi($v)) {
        $err = "Enter a valid UPI ID like name@okhdfcbank";
    } else {
        $pdo->prepare("UPDATE users SET upi_id=? WHERE id=?")->execute([
            $v !== "" ? $v : null,
            $u["id"],
        ]);
        $u["upi_id"] = $v;
        $msg = "UPI ID saved";
        logit("Updated UPI ID");
    }
}
$isAdmin = $u["role"] == "admin";
$display = trim($u["name"] ?? "") !== "" ? $u["name"] : ucfirst($u["role"]);
$initial = strtoupper(mb_substr($display, 0, 1));
$cur = $u["upi_id"] ?? "";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>My UPI ID</title>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<style>
*{box-sizing:border-box}
:root{--ac:#2563eb;--ac2:#1d4ed8;--ink:#101828;--mute:#667085;--line:#e3e8ef;--bg:#fff;--soft:#eff5ff;--page:#f1f5f9;--ok:#067647;--okbg:#ecfdf3;--okln:#abefc6;--er:#b42318;--erbg:#fef3f2;--erln:#fecdca}
@media (prefers-color-scheme:dark){:root{--ink:#e5e7eb;--mute:#9ca3af;--line:#334155;--bg:#1e293b;--soft:#172554;--page:#0f172a;--okbg:#052e1a;--okln:#14532d;--ok:#4ade80;--erbg:#3b0d0d;--erln:#7f1d1d;--er:#fca5a5}}
body{margin:0;padding:76px 16px 48px;font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;background:var(--page);color:var(--ink);overflow-x:hidden}
.material-icons{font-size:20px;vertical-align:middle}
.blob{position:fixed;border-radius:50%;filter:blur(60px);opacity:.28;z-index:0;pointer-events:none;animation:float 10s ease-in-out infinite}
.b1{width:260px;height:260px;background:#60a5fa;top:-70px;left:-70px}
.b2{width:300px;height:300px;background:#a78bfa;bottom:-90px;right:-90px;animation-delay:-5s}
@keyframes float{0%,100%{transform:translateY(0)}50%{transform:translateY(-24px)}}

.back{position:fixed;top:16px;left:16px;z-index:50;display:inline-flex;align-items:center;gap:4px;background:var(--ac);color:#fff;text-decoration:none;
  font-size:14px;font-weight:600;padding:9px 16px 9px 10px;border-radius:999px;box-shadow:0 6px 16px rgba(37,99,235,.35);transition:.25s}
.back:hover{background:var(--ac2);transform:translateX(-3px)}
.back .material-icons{font-size:18px}

.wrap{position:relative;z-index:1;max-width:1000px;margin:0 auto}
.hero{display:flex;align-items:center;gap:16px;margin-bottom:22px;animation:up .5s ease both}
.hero .ic{width:54px;height:54px;border-radius:16px;background:linear-gradient(135deg,#1d4ed8,#3b82f6);color:#fff;display:grid;place-items:center;
  box-shadow:0 10px 20px -8px rgba(37,99,235,.6);flex:none}
.hero .ic .material-icons{font-size:28px}
.hero h1{margin:0;font-size:clamp(1.4rem,3.5vw,1.9rem);letter-spacing:-.02em}
.hero p{margin:2px 0 0;color:var(--mute);font-size:14px}
.role{display:inline-block;margin-left:8px;padding:2px 10px;border-radius:999px;font-size:12px;font-weight:600;background:var(--soft);color:var(--ac);vertical-align:middle}

.grid{display:grid;grid-template-columns:minmax(0,1.05fr) minmax(0,.95fr);gap:22px;align-items:start}
.card{background:var(--bg);border:1px solid var(--line);border-radius:20px;padding:28px;
  box-shadow:0 1px 2px rgba(16,24,40,.04),0 14px 34px -16px rgba(37,99,235,.25);animation:up .6s .08s ease both}
.lead{margin:0 0 20px;font-size:14.5px;line-height:1.65;color:var(--mute)}

.alert{display:flex;align-items:flex-start;gap:10px;padding:12px 14px;border-radius:12px;font-size:14px;line-height:1.5;margin-bottom:18px}
.alert .material-icons{font-size:20px;flex:none}
.alert.ok{background:var(--okbg);color:var(--ok);border:1px solid var(--okln);animation:pop .5s ease both}
.alert.err{background:var(--erbg);color:var(--er);border:1px solid var(--erln);animation:shake .45s ease}
@keyframes pop{0%{opacity:0;transform:scale(.94)}60%{transform:scale(1.02)}100%{opacity:1;transform:none}}
@keyframes shake{0%,100%{transform:translateX(0)}25%,75%{transform:translateX(-6px)}50%{transform:translateX(6px)}}

label.lab{display:block;font-size:13.5px;font-weight:600;margin-bottom:8px}
.field{display:flex;align-items:center;gap:10px;background:var(--bg);border:1.5px solid var(--line);border-radius:14px;padding:0 8px 0 14px;transition:.2s}
.field:focus-within{border-color:var(--ac);box-shadow:0 0 0 4px rgba(37,99,235,.15)}
.field.bad{border-color:#f04438}
.field.good{border-color:#16a34a}
.field>.material-icons{color:#98a2b3}
.field input{flex:1;min-width:0;border:0!important;outline:0!important;box-shadow:none!important;background:transparent!important;
  padding:15px 0!important;margin:0!important;font:inherit;font-size:16px;color:var(--ink);width:auto}
.field input::placeholder{color:#98a2b3}
.ibtn{border:0;background:transparent;color:var(--mute);cursor:pointer;width:36px;height:36px;border-radius:10px;display:grid;place-items:center;transition:.2s}
.ibtn:hover{background:var(--soft);color:var(--ac)}
.status{display:flex;align-items:center;gap:8px;min-height:22px;margin:9px 2px 0;font-size:13px;color:var(--mute)}
.status .material-icons{font-size:17px}
.status.good{color:#16a34a}.status.bad{color:#d92d20}
.bank{display:none;align-items:center;gap:6px;margin-left:auto;font-weight:600;color:var(--ac);background:var(--soft);padding:3px 10px;border-radius:999px;font-size:12px}
.bank.on{display:inline-flex}

.chips{display:flex;flex-wrap:wrap;gap:8px;margin-top:12px}
.chips span.l{font-size:12px;color:var(--mute);align-self:center}
.chip{border:1px solid var(--line);background:var(--bg);color:var(--ink);font:inherit;font-size:12.5px;font-weight:600;padding:5px 12px;border-radius:999px;cursor:pointer;transition:.2s}
.chip:hover{border-color:var(--ac);color:var(--ac);transform:translateY(-1px)}

.actions{display:flex;align-items:center;gap:12px;margin-top:22px;flex-wrap:wrap}
.btn{position:relative;overflow:hidden;display:inline-flex;align-items:center;gap:8px;border:0;cursor:pointer;background:var(--ac);color:#fff;font:inherit;
  font-size:15px;font-weight:600;padding:13px 26px;border-radius:12px;box-shadow:0 8px 18px -8px rgba(37,99,235,.7);transition:.2s}
.btn::after{content:"";position:absolute;top:0;left:-80%;width:45%;height:100%;background:linear-gradient(100deg,transparent,rgba(255,255,255,.4),transparent);
  transform:skewX(-20deg);animation:shine 3.4s ease-in-out infinite}
@keyframes shine{0%{left:-80%}60%,100%{left:140%}}
.btn:hover{background:var(--ac2);transform:translateY(-2px)}
.btn:active{transform:scale(.97)}
.btn:focus-visible,.back:focus-visible{outline:3px solid #93c5fd;outline-offset:2px}
.btn .material-icons{font-size:18px}
.btn.alt{background:var(--soft);color:var(--ac);box-shadow:none;border:1px solid #bfd3fb;padding:11px 18px;font-size:14px}
.btn.alt::after{display:none}
.btn.alt:hover{background:#dbe8ff}
.dirty{display:none;align-items:center;gap:6px;font-size:12.5px;color:#b45309;background:#fffbeb;border:1px solid #fde68a;padding:5px 11px;border-radius:999px;font-weight:600}
.dirty.on{display:inline-flex}
.note{font-size:12.5px;color:var(--mute);display:flex;align-items:center;gap:6px;margin-top:16px}
.note .material-icons{font-size:16px;color:#12b76a}

/* realistic payment card */
.side{display:flex;flex-direction:column;gap:20px}
.pcard{position:relative;overflow:hidden;border-radius:22px;padding:24px;color:#fff;
  background:linear-gradient(145deg,#1e3a8a 0%,#2563eb 55%,#60a5fa 100%);box-shadow:0 22px 40px -18px rgba(37,99,235,.7);animation:up .6s .15s ease both}
.pcard::before{content:"";position:absolute;width:240px;height:240px;border-radius:50%;background:rgba(255,255,255,.1);top:-90px;right:-70px}
.pcard::after{content:"";position:absolute;width:160px;height:160px;border-radius:50%;background:rgba(255,255,255,.07);bottom:-60px;left:-40px}
.pcard>*{position:relative;z-index:1}
.ptop{display:flex;align-items:center;justify-content:space-between;margin-bottom:22px}
.ptop small{opacity:.85;font-size:12.5px;letter-spacing:.04em;text-transform:uppercase}
.upilogo{font-weight:800;font-style:italic;font-size:18px;letter-spacing:.5px}
.upilogo i{color:#fde68a;font-style:normal}
.who{display:flex;align-items:center;gap:12px;margin-bottom:18px}
.av{width:46px;height:46px;border-radius:50%;background:rgba(255,255,255,.2);display:grid;place-items:center;font-weight:700;font-size:19px}
.who b{display:block;font-size:16px}
.who span{font-size:12.5px;opacity:.85;display:inline-flex;align-items:center;gap:4px}
.who span .material-icons{font-size:14px;color:#86efac}
.pid{background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.25);border-radius:14px;padding:14px 16px;font-size:17px;font-weight:600;
  word-break:break-all;display:flex;align-items:center;gap:10px}
.pid.empty{font-weight:400;opacity:.8;font-size:15px}

.qrbox{text-align:center}
.qrbox h3{margin:0 0 4px;font-size:15px}
.qrbox p{margin:0 0 14px;font-size:12.5px;color:var(--mute)}
.qrframe{display:inline-grid;place-items:center;width:204px;height:204px;padding:12px;background:#fff;border-radius:18px;border:1px solid var(--line);
  box-shadow:0 10px 24px -14px rgba(15,23,42,.35)}
.qrframe img,.qrframe canvas{max-width:100%;height:auto;display:block}
.qrempty{color:#94a3b8;font-size:13px;line-height:1.4;padding:10px}
.qrempty .material-icons{display:block;font-size:44px;margin:0 auto 6px;color:#bfd3fb}
.qracts{display:flex;justify-content:center;gap:10px;margin-top:14px;flex-wrap:wrap}

.info h3{margin:0 0 14px;font-size:15px}
.info ul{list-style:none;margin:0;padding:0;display:grid;gap:14px}
.info li{display:flex;gap:12px;font-size:13.5px;line-height:1.55;color:var(--mute)}
.info li .material-icons{color:var(--ac);flex:none;margin-top:1px}

.toast{position:fixed;left:50%;bottom:26px;transform:translate(-50%,30px);background:#0f172a;color:#fff;padding:11px 18px;border-radius:999px;font-size:14px;
  font-weight:600;opacity:0;pointer-events:none;transition:.3s;z-index:90;display:flex;align-items:center;gap:8px}
.toast.on{opacity:1;transform:translate(-50%,0)}
.toast .material-icons{color:#4ade80;font-size:18px}

@keyframes up{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:none}}
@media (max-width:860px){.grid{grid-template-columns:1fr}.card{padding:22px 18px}}
@media (max-width:520px){.btn{width:100%;justify-content:center}.role{display:block;margin:6px 0 0}}
@media (prefers-reduced-motion:reduce){*{animation:none!important;transition:none!important}}
</style>
</head>
<body>
<div class="blob b1"></div><div class="blob b2"></div>
<a class="back" href="javascript:history.back()"><span class="material-icons">arrow_back</span> Back</a>

<div class="wrap">
  <div class="hero">
    <div class="ic"><span class="material-icons">account_balance_wallet</span></div>
    <div>
      <h1>My UPI ID <span class="role"><?= $isAdmin ? "Admin account" : "Worker account" ?></span></h1>
      <p>Set where your money is received. You can preview it and share a payment QR.</p>
    </div>
  </div>

  <div class="grid">
    <form class="card" method="post" autocomplete="off" id="form">
      <p class="lead">
        <?= $isAdmin
            ? "The platform fee (10% commission) from customer payments and the QR payments are received on this UPI ID. It is shown to customers when they scan the QR code."
            : "Customers can pay your share directly to this UPI ID (the platform fee is paid separately to the platform). The admin also uses it to pay your earnings." ?>
      </p>

      <?php if ($msg !== "") { ?>
        <div class="alert ok ok-msg" role="status"><span class="material-icons">verified</span><span><?= e($msg) ?></span></div>
      <?php } ?>
      <?php if ($err !== "") { ?>
        <div class="alert err" role="alert"><span class="material-icons">error_outline</span><span><?= e($err) ?></span></div>
      <?php } ?>

      <label class="lab" for="upi">UPI ID</label>
      <div class="field<?= $err !== "" ? " bad" : "" ?>" id="field">
        <span class="material-icons">alternate_email</span>
        <input id="upi" name="upi" value="<?= e($cur) ?>" placeholder="yourname@okhdfcbank" autocomplete="off" inputmode="email" autocapitalize="off" spellcheck="false">
        <button type="button" class="ibtn" id="paste" title="Paste" aria-label="Paste"><span class="material-icons">content_paste</span></button>
        <button type="button" class="ibtn" id="clr" title="Clear" aria-label="Clear"><span class="material-icons">close</span></button>
      </div>
      <div class="status" id="status"><span class="material-icons">info</span><span id="stxt">Format: name@bankhandle</span><span class="bank" id="bank"></span></div>

      <div class="chips">
        <span class="l">Add handle:</span>
        <button type="button" class="chip" data-h="@okhdfcbank">@okhdfcbank</button>
        <button type="button" class="chip" data-h="@oksbi">@oksbi</button>
        <button type="button" class="chip" data-h="@ybl">@ybl</button>
        <button type="button" class="chip" data-h="@paytm">@paytm</button>
        <button type="button" class="chip" data-h="@upi">@upi</button>
      </div>

      <div class="actions">
        <button class="btn"><span class="material-icons">check_circle</span>Save UPI ID</button>
        <span class="dirty" id="dirty"><span class="material-icons" style="font-size:16px">edit</span>Unsaved changes</span>
      </div>
      <div class="note"><span class="material-icons">lock</span>Only you can change this. Leave it empty to remove your UPI ID.</div>
    </form>

    <aside class="side">
      <div class="pcard" aria-live="polite">
        <div class="ptop"><small>How customers see it</small><span class="upilogo">UP<i>I</i></span></div>
        <div class="who">
          <div class="av"><?= e($initial) ?></div>
          <div><b><?= e($display) ?></b><span><span class="material-icons">verified</span><?= $isAdmin ? "Platform" : "Worker" ?></span></div>
        </div>
        <div class="pid<?= $cur === "" ? " empty" : "" ?>" id="pid">
          <span class="material-icons">account_balance</span>
          <span id="ptxt"><?= $cur !== "" ? e($cur) : "No UPI ID added yet" ?></span>
          <button type="button" class="ibtn" id="copy" title="Copy UPI ID" style="margin-left:auto;color:#fff"><span class="material-icons">content_copy</span></button>
        </div>
      </div>

      <div class="card qrbox">
        <h3>Your payment QR</h3>
        <p>Scan with any UPI app. It updates as you type.</p>
        <div class="qrframe" id="qrframe"><div class="qrempty"><span class="material-icons">qr_code_2</span>Enter a valid UPI ID to see your QR</div></div>
        <div class="qracts">
          <a class="btn alt" id="dl" download="upi-qr.png" style="display:none;text-decoration:none"><span class="material-icons">download</span>Download</a>
          <button type="button" class="btn alt" id="share" style="display:none"><span class="material-icons">share</span>Share</button>
        </div>
      </div>

      <div class="card info">
        <h3>Good to know</h3>
        <ul>
          <li><span class="material-icons">bolt</span><span><?= $isAdmin ? "Payments reach your bank account directly through UPI." : "Your share goes straight to you, with no waiting for settlement." ?></span></li>
          <li><span class="material-icons">fact_check</span><span>Double-check the ID. A wrong UPI ID sends money to the wrong person.</span></li>
          <li><span class="material-icons">history</span><span>Every change to your UPI ID is recorded in the activity log.</span></li>
          <li><span class="material-icons">shield</span><span>Never share your UPI PIN or OTP with anyone. Receiving money never needs a PIN.</span></li>
        </ul>
      </div>
    </aside>
  </div>
</div>

<div class="toast" id="toast"><span class="material-icons">check_circle</span><span id="ttxt">Copied</span></div>

<script>
(function(){
  var saved = <?= json_encode($cur, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  var NAME  = <?= json_encode($display, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  var $ = function(id){return document.getElementById(id)};
  var inp=$('upi'), field=$('field'), status=$('status'), stxt=$('stxt'), bank=$('bank'),
      pid=$('pid'), ptxt=$('ptxt'), dirty=$('dirty'), frame=$('qrframe'), dl=$('dl'), sh=$('share');
  var qr=null, lastQ='';

  // Hint only. The server's own check decides when you save.
  var RE=/^[a-zA-Z0-9.\-_]{2,256}@[a-zA-Z][a-zA-Z0-9]{1,63}$/;
  var BANKS={okhdfcbank:'HDFC Bank',okicici:'ICICI Bank',oksbi:'SBI',okaxis:'Axis Bank',ybl:'PhonePe',ibl:'PhonePe',axl:'PhonePe',
    paytm:'Paytm',apl:'Amazon Pay',upi:'BHIM',sbi:'SBI',hdfcbank:'HDFC Bank',icici:'ICICI Bank',axisbank:'Axis Bank',
    ybl2:'PhonePe',freecharge:'Freecharge',jio:'Jio',airtel:'Airtel',postbank:'India Post',kotak:'Kotak',pnb:'PNB',boi:'Bank of India',
    cnrb:'Canara Bank',unionbank:'Union Bank',idfcbank:'IDFC First',yesbank:'Yes Bank',indus:'IndusInd',fbl:'Federal Bank'};

  function toast(t){$('ttxt').textContent=t;var el=$('toast');el.classList.add('on');clearTimeout(toast._t);toast._t=setTimeout(function(){el.classList.remove('on')},1800)}

  function setStatus(cls,icon,text){
    status.className='status '+cls;
    status.firstChild.textContent=icon; stxt.textContent=text;
  }

  function drawQR(v){
    var q='upi://pay?pa='+encodeURIComponent(v)+'&pn='+encodeURIComponent(NAME)+'&cu=INR';
    if(q===lastQ)return; lastQ=q;
    frame.innerHTML='';
    if(typeof QRCode==='undefined'){frame.innerHTML='<div class="qrempty">QR could not load.<br>Check your internet.</div>';return}
    qr=new QRCode(frame,{text:q,width:180,height:180,colorDark:'#0f172a',colorLight:'#ffffff',correctLevel:QRCode.CorrectLevel.M});
    setTimeout(function(){
      var im=frame.querySelector('img'),cv=frame.querySelector('canvas');
      var src=im&&im.src&&im.src.indexOf('data:')===0?im.src:(cv?cv.toDataURL('image/png'):'');
      if(src){dl.href=src}
    },80);
  }
  function noQR(){
    lastQ='';
    frame.innerHTML='<div class="qrempty"><span class="material-icons">qr_code_2</span>Enter a valid UPI ID to see your QR</div>';
  }

  function refresh(){
    var v=inp.value.trim();
    ptxt.textContent=v||'No UPI ID added yet';
    pid.classList.toggle('empty',!v);
    dirty.classList.toggle('on',v!==saved);
    field.classList.remove('bad','good'); bank.classList.remove('on');

    var ok=RE.test(v);
    if(!v){setStatus('','info','Format: name@bankhandle. Empty removes your UPI ID.')}
    else if(v.indexOf('@')<0){setStatus('','info','Add the bank handle after @, for example @okhdfcbank')}
    else if(!ok){field.classList.add('bad');setStatus('bad','error_outline','This does not look like a valid UPI ID')}
    else{
      field.classList.add('good');setStatus('good','check_circle','Looks good');
      var h=v.split('@')[1].toLowerCase();
      if(BANKS[h]){bank.textContent=BANKS[h];bank.classList.add('on')}
    }
    if(ok){drawQR(v);dl.style.display='';sh.style.display=(navigator.share?'':'none')||''; if(!navigator.share)sh.style.display='none'}
    else{noQR();dl.style.display='none';sh.style.display='none'}
  }

  inp.addEventListener('input',refresh);
  $('clr').addEventListener('click',function(){inp.value='';refresh();inp.focus()});
  $('paste').addEventListener('click',function(){
    if(navigator.clipboard&&navigator.clipboard.readText){
      navigator.clipboard.readText().then(function(t){inp.value=t.trim();refresh();toast('Pasted')}).catch(function(){inp.focus();toast('Press Ctrl+V to paste')});
    }else{inp.focus();toast('Press Ctrl+V to paste')}
  });
  document.querySelectorAll('.chip').forEach(function(c){
    c.addEventListener('click',function(){
      var v=inp.value.trim().split('@')[0];
      if(!v){inp.focus();toast('Type your name first');return}
      inp.value=v+c.getAttribute('data-h');refresh();
    });
  });
  $('copy').addEventListener('click',function(){
    var v=inp.value.trim(); if(!v){toast('Nothing to copy');return}
    function done(){toast('UPI ID copied')}
    if(navigator.clipboard&&navigator.clipboard.writeText){navigator.clipboard.writeText(v).then(done,function(){fallback(v);done()})}
    else{fallback(v);done()}
  });
  function fallback(t){var a=document.createElement('textarea');a.value=t;document.body.appendChild(a);a.select();try{document.execCommand('copy')}catch(e){}document.body.removeChild(a)}
  sh.addEventListener('click',function(){
    var v=inp.value.trim();
    if(navigator.share)navigator.share({title:'Pay '+NAME,text:'Pay '+NAME+' on UPI: '+v}).catch(function(){});
  });
  window.addEventListener('beforeunload',function(e){
    if(inp.value.trim()!==saved && !submitting){e.preventDefault();e.returnValue=''}
  });
  var submitting=false;
  $('form').addEventListener('submit',function(){submitting=true});

  refresh();
})();
</script>
</body>
</html>
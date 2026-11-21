/* Workonnect: styled service dropdown. Progressive enhancement - the real <select name="cat"> stays in the form and keeps the value, so submit/URL/PHP are unchanged. */
(function(){
  'use strict';
  var d=document,s=d.querySelector('.wk-search select[name="cat"]');
  if(!s||!d.body.classList.contains('wk-page'))return;
  var lab=s.closest('.wk-f');if(!lab)return;
  if(s.getAttribute('data-wk-dd')==='1')return; /* never initialise twice, even if this file is accidentally pasted more than once */
  s.setAttribute('data-wk-dd','1');
  var ico=function(n){return '<svg class="wk-i" aria-hidden="true" focusable="false"><use href="#i-'+n+'"/></svg>'};
  var esc=function(t){return String(t).replace(/[&<>"]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]})};
  var wrap=d.createElement('div');wrap.className='wk-dd';
  var btn=d.createElement('button');btn.type='button';btn.className='wk-dd-btn';
  btn.setAttribute('role','combobox');btn.setAttribute('aria-haspopup','listbox');btn.setAttribute('aria-expanded','false');btn.setAttribute('aria-label','Service category');
  btn.innerHTML='<span class="wk-dd-v"></span>'+ico('chev');
  var list=d.createElement('ul');list.className='wk-dd-list';list.id='wkDdList';list.setAttribute('role','listbox');
  btn.setAttribute('aria-controls','wkDdList');
  var opts=[].slice.call(s.options),items=[],act=-1,ign=0,buf='',bt;
  opts.forEach(function(o,i){
    var li=d.createElement('li');li.setAttribute('role','option');li.id='wkDdO'+i;li.dataset.i=i;
    li.innerHTML='<span class="wk-dd-ic">'+ico(o.dataset.i||'tools')+'</span><span class="wk-dd-t">'+esc(o.dataset.label||o.text)+'</span><span class="wk-dd-ck">'+ico('check')+'</span>';
    list.appendChild(li);items.push(li);
  });
  /* the native select is moved into a hidden holder: it stays in the form (value still submitted) but can never be drawn, whatever other CSS says */
  var hold=d.createElement('span');hold.hidden=true;hold.setAttribute('aria-hidden','true');hold.style.setProperty('display','none','important');
  s.tabIndex=-1;lab.appendChild(hold);hold.appendChild(s);
  wrap.appendChild(btn);wrap.appendChild(list);lab.appendChild(wrap);lab.classList.add('wk-dd-on');
  var val=btn.firstChild;
  function paint(){
    var o=opts[s.selectedIndex]||opts[0],ph=!o.value;
    val.textContent=ph?o.text:(o.dataset.label||o.text);val.classList.toggle('is-ph',ph);
    items.forEach(function(li,i){var on=i===s.selectedIndex;li.classList.toggle('is-sel',on);li.setAttribute('aria-selected',on?'true':'false')});
  }
  function mark(i){
    act=i;items.forEach(function(li,k){li.classList.toggle('is-act',k===i)});
    if(i>-1){btn.setAttribute('aria-activedescendant',items[i].id);items[i].scrollIntoView({block:'nearest'})}else btn.removeAttribute('aria-activedescendant');
  }
  function open(){lab.classList.add('is-open');btn.setAttribute('aria-expanded','true');mark(s.selectedIndex<0?0:s.selectedIndex)}
  function close(){lab.classList.remove('is-open');btn.setAttribute('aria-expanded','false');mark(-1)}
  function isOpen(){return lab.classList.contains('is-open')}
  function pick(i){
    if(s.selectedIndex!==i){s.selectedIndex=i;s.dispatchEvent(new Event('change',{bubbles:true}))}
    paint();close();ign=Date.now()+300;
  }
  lab.addEventListener('click',function(e){
    if(list.contains(e.target))return;
    e.preventDefault();
    if(Date.now()<ign)return;
    isOpen()?close():open();
  });
  list.addEventListener('click',function(e){
    e.preventDefault();e.stopPropagation();
    var li=e.target.closest&&e.target.closest('li');if(li)pick(+li.dataset.i);
  });
  list.addEventListener('mousemove',function(e){var li=e.target.closest&&e.target.closest('li');if(li)mark(+li.dataset.i)});
  d.addEventListener('click',function(e){if(isOpen()&&!lab.contains(e.target))close()});
  btn.addEventListener('keydown',function(e){
    var k=e.key,n=items.length;
    if(k==='ArrowDown'||k==='ArrowUp'){
      e.preventDefault();if(!isOpen()){open();return}
      mark(k==='ArrowDown'?Math.min(n-1,act+1):Math.max(0,act-1));
    }else if(k==='Home'&&isOpen()){e.preventDefault();mark(0)}
    else if(k==='End'&&isOpen()){e.preventDefault();mark(n-1)}
    else if((k==='Enter'||k===' ')&&isOpen()){e.preventDefault();ign=Date.now()+300;if(act>-1)pick(act);else close()}
    else if(k==='Escape'&&isOpen()){e.preventDefault();e.stopPropagation();close()}
    else if(k==='Tab'&&isOpen()){close()}
    else if(k.length===1&&k!==' '){
      clearTimeout(bt);buf+=k.toLowerCase();bt=setTimeout(function(){buf=''},700);
      for(var i=0;i<n;i++){if(items[i].textContent.toLowerCase().replace(/\s+/g,' ').trim().indexOf(buf)===0||opts[i].text.toLowerCase().indexOf(buf)===0&&opts[i].value){if(!isOpen())open();mark(i);break}}
    }
  });
  s.addEventListener('change',function(){paint();syncChips()});
  paint();

  /* quick-service chips: clicking one puts that service in the search bar (no typing). Without JS they still link to workers.php?cat=... */
  var chips=[].slice.call(d.querySelectorAll('.wk-quick a'));
  function syncChips(){
    var cur=s.value;
    chips.forEach(function(a){var on=cur!==''&&a.textContent.trim()===cur;a.classList.toggle('is-on',on);a.setAttribute('aria-pressed',on?'true':'false')});
  }
  chips.forEach(function(a){
    a.addEventListener('click',function(e){
      e.preventDefault();
      var name=a.textContent.trim(),idx=-1;
      opts.forEach(function(o,i){if(o.value===name)idx=i});
      if(idx<0){location.href=a.href;return}
      /* tapping the chosen chip again clears the choice */
      var to=(s.selectedIndex===idx)?0:idx;
      s.selectedIndex=to;s.dispatchEvent(new Event('change',{bubbles:true}));
      lab.classList.remove('wk-flash');void lab.offsetWidth;lab.classList.add('wk-flash');
    });
  });
  syncChips();
})();
/* Workonnect landing page behaviour (v15). Vanilla JS, no dependencies. Safe to load on any page: it only acts on landing elements. */
(function(){
  'use strict';
  var d=document,w=window;
  if(!d.body||!d.body.classList.contains('wk-page'))return;
  var reduce=w.matchMedia&&w.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var hasIO='IntersectionObserver' in w;
  var $=function(s,r){return (r||d).querySelector(s)};
  var $$=function(s,r){return Array.prototype.slice.call((r||d).querySelectorAll(s))};

  /* sticky header shadow */
  var header=$('#wkHeader');
  function onScroll(){
    if(header)header.classList.toggle('is-stuck',w.scrollY>8);
    var up=$('#up');if(up)up.hidden=w.scrollY<400; /* back-to-top button */
    var sp=$('#sp');if(sp){var h=d.documentElement.scrollHeight-w.innerHeight;sp.style.width=(h>0?w.scrollY/h*100:0)+'%'} /* scroll progress bar */
  }
  w.addEventListener('scroll',onScroll,{passive:true});onScroll();

  /* fade-up reveals (section blocks only) */
  var rev=$$('.wk-reveal');
  if(hasIO&&!reduce){
    var ro=new IntersectionObserver(function(es){es.forEach(function(e){if(e.isIntersecting){e.target.classList.add('is-in');ro.unobserve(e.target)}})},{threshold:.12,rootMargin:'0px 0px -6% 0px'});
    rev.forEach(function(el){ro.observe(el)});
  }else{rev.forEach(function(el){el.classList.add('is-in')})}

  /* animated counters: real values come from the server; the text is restored exactly at the end */
  var nums=$$('.wk-num[data-n]');
  function fmt(v,dec){return dec?v.toFixed(dec):String(Math.round(v))}
  if(hasIO&&!reduce){
    var co=new IntersectionObserver(function(es){es.forEach(function(e){
      if(!e.isIntersecting)return;co.unobserve(e.target);
      var el=e.target,to=parseFloat(el.dataset.n),dec=parseInt(el.dataset.dec||'0',10),final=el.dataset.final,t0=null,dur=1100;
      if(!(to>0)){el.textContent=final;return}
      (function step(ts){
        if(t0===null)t0=ts;
        var p=Math.min(1,(ts-t0)/dur),k=1-Math.pow(1-p,3);
        el.textContent=p<1?fmt(to*k,dec):final;
        if(p<1)w.requestAnimationFrame(step);
      })(w.performance.now());
    })},{threshold:.4});
    nums.forEach(function(el){el.dataset.final=el.textContent;el.textContent=fmt(0,parseInt(el.dataset.dec||'0',10));co.observe(el)});
  }

  /* mobile menu */
  var burger=$('#wkBurger'),panel=$('#wkPanel');
  function menu(open){
    if(!burger||!panel)return;
    burger.setAttribute('aria-expanded',open?'true':'false');
    panel.classList.toggle('is-open',!!open);
  }
  if(burger&&panel){
    burger.addEventListener('click',function(){menu(burger.getAttribute('aria-expanded')!=='true')});
    panel.addEventListener('click',function(e){if(e.target.closest&&e.target.closest('a'))menu(false)});
    w.addEventListener('resize',function(){if(w.innerWidth>1060)menu(false)});
  }

  /* account dropdown (signed-in users) */
  var aw=$('.wk-acctw'),ab=$('#wkAcct');
  function acct(open){
    if(!aw||!ab)return;
    aw.classList.toggle('is-open',!!open);
    ab.setAttribute('aria-expanded',open?'true':'false');
  }
  if(aw&&ab){
    ab.addEventListener('click',function(e){e.stopPropagation();acct(!aw.classList.contains('is-open'))});
    d.addEventListener('click',function(e){if(!aw.contains(e.target))acct(false)});
  }
  d.addEventListener('keydown',function(e){
    if(e.key!=='Escape')return;
    if(aw&&aw.classList.contains('is-open')){acct(false);if(ab)ab.focus()}
    if(panel&&panel.classList.contains('is-open')){menu(false);if(burger)burger.focus()}
  });
})();

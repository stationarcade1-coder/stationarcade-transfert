(function(){
var $=function(s){return document.querySelector(s)},P={};
try{P=JSON.parse(document.getElementById('sa-prods').textContent)}catch(e){}
var KEY='sa-devis',devis={};try{devis=JSON.parse(localStorage.getItem(KEY))||{}}catch(e){}
function save(){try{localStorage.setItem(KEY,JSON.stringify(devis))}catch(e){}}
function count(){var n=0;for(var k in devis)n+=devis[k];return n}
function eur(v){return v.toLocaleString('fr-FR',{minimumFractionDigits:v%1?2:0,maximumFractionDigits:2})+' €'}
var page=document.body.dataset.prod||'';
function render(){var c=count(),ch=$('#count-hd');if(ch){ch.textContent=c;ch.dataset.z=c?'0':'1'}
 document.querySelectorAll('.pcard [data-add],.prod [data-add],.ctas [data-add]').forEach(function(b){var k=b.dataset.add,q=devis[k];b.classList.toggle('on',!!q);b.textContent=q?'✓ Dans le devis ('+q+')':(b.classList.contains('b-jaune')?'Ajouter au devis':'+ Ajouter au devis')});
 var mb=$('#mbar-btn'),mi=$('#mbar-info');if(!mb)return;var p=P[page];
 if(p){mi.innerHTML='<b>'+eur(p.price)+' HT</b>par '+(p.unit==='jour'?'jour':'3 h');mb.textContent=devis[page]?'Dans le devis ('+devis[page]+')':'Ajouter au devis';mb.dataset.add=page;mb.setAttribute('href','#')}
 else{mi.innerHTML='<b>'+c+' machine'+(c>1?'s':'')+'</b>dans votre devis';mb.textContent='Voir mon devis';mb.setAttribute('href','/devis/');delete mb.dataset.add}
 var am=$('#added-msg');if(am&&p){am.hidden=!devis[page];if(devis[page])am.textContent='Dans votre devis : '+devis[page]+' machine'+(devis[page]>1?'s':'')+'.'}
 document.dispatchEvent(new CustomEvent('sa-devis',{detail:devis}))}
function toast(t){var el=$('#toast');if(!el)return;el.textContent=t;el.hidden=false;clearTimeout(toast.t);toast.t=setTimeout(function(){el.hidden=true},2200)}
window.SA={P:P,devis:function(){return devis},set:function(k,q){if(q>0)devis[k]=q;else delete devis[k];save();render()},eur:eur};
var galI=0;function galGo(i){var bs=[].slice.call(document.querySelectorAll('#thumbs button'));if(!bs.length)return;galI=(i+bs.length)%bs.length;var b=bs[galI];bs.forEach(function(x){x.setAttribute('aria-current',x===b?'true':'false')});var g=$('#gal-img');g.src=b.dataset.src;g.alt=b.dataset.alt;var cap=$('#gal-cap');if(cap)cap.textContent=b.dataset.alt;var gc=$('#gcount');if(gc)gc.textContent=(galI+1)+' / '+bs.length}
document.addEventListener('click',function(e){
 var add=e.target.closest('[data-add]');if(add){e.preventDefault();var k=add.dataset.add;devis[k]=(devis[k]||0)+1;save();render();toast(((P[k]||{}).nom||'Machine')+' ajoutée au devis');return}
 var g=e.target.closest('[data-gal]');if(g){galGo(galI+(+g.dataset.gal));return}
 var t=e.target.closest('#thumbs button');if(t){galGo(+t.dataset.i);return}
 var tb=e.target.closest('[data-tab]');if(tb){var bar=tb.parentNode;bar.querySelectorAll('[data-tab]').forEach(function(b){var on=b===tb;b.setAttribute('aria-selected',on);var pn=document.getElementById(b.dataset.tab);if(pn)pn.hidden=!on});return}
 var sc=e.target.closest('[data-scroll]');if(sc){var el=document.getElementById(sc.dataset.scroll);if(el)window.scrollTo({top:el.getBoundingClientRect().top+window.scrollY-130,behavior:'smooth'});return}
 var ml=e.target.closest('#mnav a');if(ml){$('#mnav').hidden=true;$('#burger').setAttribute('aria-expanded','false')}
});
var bu=$('#burger');if(bu)bu.addEventListener('click',function(){var m=$('#mnav');m.hidden=!m.hidden;bu.setAttribute('aria-expanded',String(!m.hidden))});
var rmq=window.matchMedia('(prefers-reduced-motion: reduce)').matches;
[].slice.call(document.querySelectorAll('.prod .sl')).forEach(function(sl,idx){var im=[].slice.call(sl.querySelectorAll('img')),n=im.length,cur=0,cnt=sl.querySelector('.cnt'),hover=false;if(n<2){if(cnt)cnt.hidden=true;return}
 function go(i){var nx=im[i];function sw(){im[cur].classList.remove('on');nx.classList.add('on');cur=i;if(cnt)cnt.textContent=(i+1)+'/'+n}if(!nx.getAttribute('src')){nx.onload=sw;nx.setAttribute('src',nx.dataset.src)}else sw()}
 sl.addEventListener('mouseenter',function(){hover=true});sl.addEventListener('mouseleave',function(){hover=false});if(rmq)return;
 setTimeout(function(){setInterval(function(){if(!hover&&!document.hidden)go((cur+1)%n)},2800)},idx*550)});
var rail=$('#rail');if(rail){var step=function(){var c=rail.querySelector('.prod');return c?c.getBoundingClientRect().width+18:300};var fg=$('#fl-g'),fd=$('#fl-d');if(fg)fg.addEventListener('click',function(){rail.scrollBy({left:-step(),behavior:'smooth'})});if(fd)fd.addEventListener('click',function(){rail.scrollBy({left:step(),behavior:'smooth'})})}
var vids=[].slice.call(document.querySelectorAll('.hero-vid')),paused=rmq,vb=$('#vid-btn');
function vidSync(){vids.forEach(function(v){var vis=getComputedStyle(v).display!=='none';if(vis&&!v.getAttribute('src'))v.setAttribute('src',v.dataset.src);if(vis&&!paused){var pr=v.play();if(pr&&pr.catch)pr.catch(function(){})}else v.pause()})}
function vbIcon(){if(!vb)return;vb.setAttribute('aria-label',paused?'Lire la vidéo':'Mettre la vidéo en pause');vb.innerHTML=paused?'<svg width="14" height="14" viewBox="0 0 14 14" fill="currentColor"><path d="M3 1l10 6-10 6z"/></svg>':'<svg width="14" height="14" viewBox="0 0 14 14" fill="currentColor"><rect x="2" y="1" width="3.5" height="12"/><rect x="8.5" y="1" width="3.5" height="12"/></svg>'}
if(vids.length){vbIcon();if(vb)vb.addEventListener('click',function(){paused=!paused;vbIcon();vidSync()});window.addEventListener('resize',vidSync);vidSync()}
document.querySelectorAll('.nav a').forEach(function(a){var h=a.getAttribute('href');if(h&&h!=='/'&&location.pathname.indexOf(h)===0)a.classList.add('act');else if(h==='/'&&location.pathname!=='/')a.classList.remove('act')});
render();
})();

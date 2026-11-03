document.addEventListener('DOMContentLoaded',function(){(function(){
var $=function(s){return document.querySelector(s)},P=window.SA_PRODS||{},jeton='';
function esc(s){return String(s).replace(/[<>"&]/g,function(c){return {'<':'&lt;','>':'&gt;','"':'&quot;','&':'&amp;'}[c]})}
fetch('/wp-json/sa/v1/devis/jeton',{cache:'no-store'}).then(function(r){return r.json()}).then(function(j){jeton=j.jeton}).catch(function(){});
function liste(){var d=window.SA?SA.devis():{},list=$('#pan-list'),keys=Object.keys(d).filter(function(k){return d[k]>0&&P[k]}),sum=0,hasH=false;list.innerHTML='';
 if(!keys.length){list.innerHTML='<p class="panier-vide">Aucune machine pour l’instant. Ajoutez-en depuis le <a href="/location/" style="color:var(--cyan)">catalogue</a>, ou décrivez votre projet : nous vous conseillons.</p>';$('#pan-total').hidden=true;return}
 keys.forEach(function(k){var it=P[k];if(it.unit==='jour')sum+=it.price*d[k];else hasH=true;var row=document.createElement('div');row.className='ligne';
  row.innerHTML='<img src="'+esc(it.img)+'" alt=""><div><a class="t" href="'+esc(it.url)+'">'+esc(it.nom)+'</a><div class="p num">'+SA.eur(it.price)+' HT / '+esc(it.unit)+'</div><button type="button" class="rm" data-rm="'+k+'">Retirer</button></div><div class="qte"><button type="button" aria-label="Retirer une machine" data-dec="'+k+'">−</button><output aria-live="polite">'+d[k]+'</output><button type="button" aria-label="Ajouter une machine" data-inc="'+k+'">+</button></div>';list.appendChild(row)});
 $('#pan-total').hidden=false;$('#pan-sum').textContent=SA.eur(Math.round(sum*100)/100)+' HT / jour'+(hasH?' + photobooth':'')}
document.addEventListener('click',function(e){var d=SA.devis(),b;
 if(b=e.target.closest('[data-inc]')){SA.set(b.dataset.inc,(d[b.dataset.inc]||0)+1)}
 else if(b=e.target.closest('[data-dec]')){SA.set(b.dataset.dec,(d[b.dataset.dec]||0)-1)}
 else if(b=e.target.closest('[data-rm]')){SA.set(b.dataset.rm,0)}});
document.addEventListener('sa-devis',liste);liste();

var cal=$('#cal'),dDeb=$('#d-debut'),dFin=$('#d-fin'),calTxt=$('#cal-txt'),auj=new Date();auj.setHours(0,0,0,0);
var vue=new Date(auj.getFullYear(),auj.getMonth(),1),deb=null,fin=null;
function iso(d){return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0')}
function fr(d){return d.toLocaleDateString('fr-FR',{weekday:'long',day:'numeric',month:'long',year:'numeric'})}
function dessin(){var an=vue.getFullYear(),mo=vue.getMonth(),prem=(new Date(an,mo,1).getDay()+6)%7,nb=new Date(an,mo+1,0).getDate();
 var h='<div class="ch"><button type="button" data-cal="-1" aria-label="Mois précédent"'+(an===auj.getFullYear()&&mo===auj.getMonth()?' disabled':'')+'>‹</button><span>'+vue.toLocaleDateString('fr-FR',{month:'long',year:'numeric'})+'</span><button type="button" data-cal="1" aria-label="Mois suivant">›</button></div><div class="g">';
 ['L','M','M','J','V','S','D'].forEach(function(j){h+='<span aria-hidden="true">'+j+'</span>'});
 for(var i=0;i<prem;i++)h+='<span></span>';
 for(var j=1;j<=nb;j++){var d=new Date(an,mo,j),c='',t=d.getTime();if(deb&&t===deb.getTime())c='d';else if(fin&&t===fin.getTime())c='e';else if(deb&&fin&&t>deb&&t<fin)c='in';
  h+='<button type="button" data-jour="'+iso(d)+'" class="'+c+'"'+(d<auj?' disabled':'')+' aria-label="'+fr(d)+'"'+(c?' aria-pressed="true"':'')+'>'+j+'</button>'}
 cal.innerHTML=h+'</div>';
 dDeb.value=deb?iso(deb):'';dFin.value=fin?iso(fin):(deb?iso(deb):'');
 calTxt.textContent=!deb?'Cliquez sur le premier jour, puis sur le dernier jour.':(!fin?'Du '+fr(deb)+' — cliquez sur le dernier jour (ou laissez ainsi pour un seul jour).':'Du '+fr(deb)+' au '+fr(fin)+' ('+(Math.round((fin-deb)/864e5)+1)+' jours).')}
if(cal){cal.addEventListener('click',function(e){var b=e.target.closest('[data-cal]');if(b){vue=new Date(vue.getFullYear(),vue.getMonth()+(+b.dataset.cal),1);dessin();return}
 var j=e.target.closest('[data-jour]');if(!j)return;var p=j.dataset.jour.split('-'),d=new Date(+p[0],+p[1]-1,+p[2]);
 if(!deb||fin||d<deb){deb=d;fin=null}else if(d.getTime()===deb.getTime()){fin=null}else{fin=d}dessin()});dessin()}
var f=$('#fd'),btn=$('#fd-btn'),er=$('#fd-err'),ok=$('#fd-ok'),envoi=false;
f.addEventListener('submit',function(e){e.preventDefault();if(envoi)return;er.hidden=true;
 var v=function(n){var el=f.elements[n];return el?(el.value||'').trim():''};
 var manque=[];if(!v('nom'))manque.push('votre nom');if(!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(v('email')))manque.push('un e-mail valide');if(v('tel').replace(/\D/g,'').length<9)manque.push('votre téléphone');
 if(manque.length){er.textContent='Merci d’indiquer '+manque.join(' et ')+'.';er.hidden=false;(f.elements[!v('nom')?'nom':(manque.indexOf('un e-mail valide')>-1?'email':'tel')]).focus();return}
 var d=SA.devis(),machines=Object.keys(d).filter(function(k){return d[k]>0}).map(function(k){return {id:k,q:d[k],nom:(P[k]||{}).nom||k,img:(P[k]||{}).img||'',url:(P[k]||{}).url||''}});
 var data={jeton:jeton,site_web:v('site_web'),nom:v('nom'),societe:v('societe'),email:v('email'),tel:v('tel'),date:v('date_debut'),date_fin:v('date_fin'),lieu:v('lieu'),perso:f.elements.perso.checked?'Oui':'Non',message:v('message'),machines:machines,page:document.referrer||location.href};
 envoi=true;btn.disabled=true;btn.textContent='Envoi en cours…';
 fetch('/wp-json/sa/v1/devis',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)}).then(function(r){return r.json().then(function(j){return {s:r.status,j:j}})}).then(function(x){
  if(x.j&&x.j.ok){ok.innerHTML='<b>Demande envoyée.</b> Référence '+esc(x.j.ref)+'. Un e-mail de confirmation vous a été envoyé ; nous revenons vers vous rapidement.';ok.hidden=false;f.querySelectorAll('fieldset,button[type=submit]').forEach(function(el){el.hidden=true});ok.focus();
   Object.keys(SA.devis()).forEach(function(k){SA.set(k,0)});document.dispatchEvent(new CustomEvent('sa-devis-envoye',{detail:{ref:x.j.ref}}))}
  else throw new Error((x.j&&x.j.message)||'Erreur '+x.s)}).catch(function(err){er.textContent=(err&&err.message&&err.message.indexOf('Failed')<0?err.message:'La demande n’a pas pu être envoyée.')+' Vous pouvez aussi nous appeler au 06 29 72 90 92.';er.hidden=false;envoi=false;btn.disabled=false;btn.textContent='Envoyer ma demande'})});
})();
});
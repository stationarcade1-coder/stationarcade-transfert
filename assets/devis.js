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
var f=$('#fd'),btn=$('#fd-btn'),er=$('#fd-err'),ok=$('#fd-ok'),envoi=false;
f.addEventListener('submit',function(e){e.preventDefault();if(envoi)return;er.hidden=true;
 var v=function(n){var el=f.elements[n];return el?(el.value||'').trim():''};
 var manque=[];if(!v('nom'))manque.push('votre nom');if(!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(v('email')))manque.push('un e-mail valide');
 if(manque.length){er.textContent='Merci d’indiquer '+manque.join(' et ')+'.';er.hidden=false;(f.elements[v('nom')?'email':'nom']).focus();return}
 var d=SA.devis(),machines=Object.keys(d).filter(function(k){return d[k]>0}).map(function(k){return {id:k,q:d[k],nom:(P[k]||{}).nom||k}});
 var data={jeton:jeton,site_web:v('site_web'),nom:v('nom'),societe:v('societe'),email:v('email'),tel:v('tel'),date:v('date'),duree:v('duree'),lieu:v('lieu'),type:v('type'),perso:(f.querySelector('input[name=perso]:checked')||{}).value||'',message:v('message'),machines:machines,page:document.referrer||location.href};
 envoi=true;btn.disabled=true;btn.textContent='Envoi en cours…';
 fetch('/wp-json/sa/v1/devis',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)}).then(function(r){return r.json().then(function(j){return {s:r.status,j:j}})}).then(function(x){
  if(x.j&&x.j.ok){ok.innerHTML='<b>Demande envoyée.</b> Référence '+esc(x.j.ref)+'. Un e-mail de confirmation vous a été envoyé ; nous revenons vers vous rapidement.';ok.hidden=false;f.querySelectorAll('fieldset,button[type=submit]').forEach(function(el){el.hidden=true});ok.focus();
   Object.keys(SA.devis()).forEach(function(k){SA.set(k,0)});document.dispatchEvent(new CustomEvent('sa-devis-envoye',{detail:{ref:x.j.ref}}))}
  else throw new Error((x.j&&x.j.message)||'Erreur '+x.s)}).catch(function(err){er.textContent=(err&&err.message&&err.message.indexOf('Failed')<0?err.message:'La demande n’a pas pu être envoyée.')+' Vous pouvez aussi nous appeler au 06 29 72 90 92.';er.hidden=false;envoi=false;btn.disabled=false;btn.textContent='Envoyer ma demande'})});
})();
});
(function(){
var GA='G-SNCVFS8814',PROD=location.hostname==='stationarcade.fr'||location.hostname==='www.stationarcade.fr',KEY='sa-consent',MAX=180*864e5;
window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments)}window.gtag=gtag;
gtag('consent','default',{ad_storage:'denied',ad_user_data:'denied',ad_personalization:'denied',analytics_storage:'denied'});
var charge=false;function chargerGA(){if(charge)return;charge=true;gtag('consent','update',{analytics_storage:'granted'});
 if(!PROD){console.info('[Station Arcade] Préproduction : Google Analytics non chargé (mesures affichées ici uniquement).');return}
 var s=document.createElement('script');s.async=true;s.src='https://www.googletagmanager.com/gtag/js?id='+GA;document.head.appendChild(s);gtag('js',new Date());gtag('config',GA,{anonymize_ip:true})}
function lire(){try{var c=JSON.parse(localStorage.getItem(KEY));if(c&&Date.now()-c.t<MAX)return c.v}catch(e){}return null}
function ecrire(v){try{localStorage.setItem(KEY,JSON.stringify({v:v,t:Date.now()}))}catch(e){}}
function mesure(nom,params){if(!charge)return;if(PROD)gtag('event',nom,params||{});else console.info('[mesure]',nom,params||{})}
window.SA_mesure=mesure;
var b=document.createElement('div');b.className='sa-cookies';b.setAttribute('role','dialog');b.setAttribute('aria-label','Cookies');b.hidden=true;
b.innerHTML='<p>Nous utilisons des cookies de mesure d’audience (Google Analytics) pour améliorer le site. Vous pouvez les accepter ou les refuser ; votre choix est modifiable à tout moment. <a href="/confidentialite/">En savoir plus</a></p><div class="sa-cookies-b"><button type="button" data-ck="0">Refuser</button><button type="button" data-ck="1">Accepter</button></div>';
var st=document.createElement('style');st.textContent='.sa-cookies{position:fixed;left:16px;right:16px;bottom:16px;z-index:9999;max-width:560px;margin:0 auto;background:#000131;color:#fff;border:1px solid #00e1d4;border-radius:6px;padding:16px 18px;font:14px/1.5 Poppins,system-ui,sans-serif;box-shadow:0 10px 30px rgba(0,0,0,.45)}.sa-cookies a{color:#00e1d4;text-decoration:underline}.sa-cookies-b{display:flex;gap:10px;margin-top:12px}.sa-cookies-b button{flex:1;padding:10px 12px;font:600 14px Poppins,system-ui,sans-serif;border-radius:3px;cursor:pointer;border:2px solid #fff;background:transparent;color:#fff}.sa-cookies-b button[data-ck="1"]{border-color:#ffd900;color:#ffd900}';
document.head.appendChild(st);document.body.appendChild(b);
var v=lire();if(v==='1')chargerGA();else if(v===null)b.hidden=false;
b.addEventListener('click',function(e){var x=e.target.closest('[data-ck]');if(!x)return;ecrire(x.dataset.ck);b.hidden=true;if(x.dataset.ck==='1')chargerGA();else if(charge){gtag('consent','update',{analytics_storage:'denied'})}});
document.addEventListener('click',function(e){var g=e.target.closest('[data-cookies]');if(g){e.preventDefault();b.hidden=false;return}
 var a=e.target.closest('a[href^="tel:"]');if(a){mesure('clic_telephone',{page:location.pathname});return}
 var m=e.target.closest('a[href^="mailto:"]');if(m){mesure('clic_email',{page:location.pathname});return}
 var d=e.target.closest('[data-add]');if(d){mesure('ajout_devis',{machine:d.dataset.add,page:location.pathname})}},true);
document.addEventListener('sa-devis-envoye',function(){mesure('generate_lead',{page:location.pathname})});
})();

function renderHeader(title){const el=document.getElementById('header');if(el)el.innerHTML=`<div class="topbar"><h2 style="margin:0">${title}</h2></div>`}
function renderBottomNav(active){const el=document.getElementById('bottomNav');if(!el)return;const items=[['index.html','Home'],['cart.html','Cart'],['orders.html','Orders'],['account.html','Account']];el.className='bottom-nav';el.innerHTML=items.map(([href,label])=>`<a class="${active===label.toLowerCase()?'active':''}" href="${href}">${label}</a>`).join('')}
function setText(el,value){if(el)el.textContent=value==null?'':String(value)}
function showToast(msg,type='info'){const el=document.getElementById('toast');if(!el)return;el.className=`toast show ${type}`;el.textContent=msg;setTimeout(()=>el.className=`toast ${type}`,2200)}
function formatMoney(n){return new Intl.NumberFormat('th-TH',{style:'currency',currency:'THB'}).format(Number(n||0))}
function qs(name){return new URLSearchParams(location.search).get(name)}
function qsi(name){const v=qs(name);return v?parseInt(v,10):null}
function requireMerchantId(){if(!shopSettings.getMerchantId()){location.href='settings.html';return false}return true}
function requireAuth(){if(!shopSettings.getToken()){showToast('กรุณา login ก่อน','error');setTimeout(()=>location.href='account.html',500);return false}return true}
window.renderHeader=renderHeader;window.renderBottomNav=renderBottomNav;window.setText=setText;window.showToast=showToast;window.formatMoney=formatMoney;window.qs=qs;window.qsi=qsi;window.requireMerchantId=requireMerchantId;window.requireAuth=requireAuth;

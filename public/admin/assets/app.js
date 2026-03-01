function renderHeader(title){
  const el=document.getElementById('header');
  if(!el) return;
  const bar=document.createElement('div');
  bar.className='topbar';
  const h2=document.createElement('h2');
  h2.style.margin='0';
  h2.textContent=title;
  bar.appendChild(h2);
  el.replaceChildren(bar);
}
function setText(el,value){if(el)el.textContent=value==null?'':String(value)}
function showToast(msg,type='info'){const el=document.getElementById('toast');if(!el)return;el.className=`toast show ${type}`;el.textContent=msg;setTimeout(()=>el.className=`toast ${type}`,2200)}
function formatMoney(n){return new Intl.NumberFormat('th-TH',{style:'currency',currency:'THB'}).format(Number(n||0))}
function qs(name){return new URLSearchParams(location.search).get(name)}
function qsi(name){const v=qs(name);return v?parseInt(v,10):null}
function requireAuth(){if(!shopSettings.getToken()){showToast('กรุณา login ก่อน','error');setTimeout(()=>location.href='/shop/account.html',500);return false}return true}
window.renderHeader=renderHeader;window.setText=setText;window.showToast=showToast;window.formatMoney=formatMoney;window.qs=qs;window.qsi=qsi;window.requireAuth=requireAuth;

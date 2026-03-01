const SHOP_KEYS={base:'SHOP_API_BASE',token:'SHOP_TOKEN',merchantId:'SHOP_MERCHANT_ID'};
const shopSettings={
  getBaseUrl:()=>localStorage.getItem(SHOP_KEYS.base)||window.location.origin,
  setBaseUrl:(v)=>localStorage.setItem(SHOP_KEYS.base,v.replace(/\/$/,'')),
  getToken:()=>localStorage.getItem(SHOP_KEYS.token)||'',
  setToken:(v)=>v?localStorage.setItem(SHOP_KEYS.token,v):localStorage.removeItem(SHOP_KEYS.token),
  getMerchantId:()=>localStorage.getItem(SHOP_KEYS.merchantId)||'',
  setMerchantId:(v)=>localStorage.setItem(SHOP_KEYS.merchantId,String(v||'')),
};
async function request(method,path,{query,json,body}={}){
  const url=new URL(shopSettings.getBaseUrl()+path);
  if(query) Object.keys(query).forEach(k=>query[k]!=null&&url.searchParams.set(k,query[k]));
  const headers={Accept:'application/json'};
  const token=shopSettings.getToken(); if(token) headers.Authorization=`Bearer ${token}`;
  let payload=body;
  if(json){headers['Content-Type']='application/json';payload=JSON.stringify(json)}
  const res=await fetch(url,{method,headers,body:payload,credentials:'include'});
  const data=await res.json().catch(()=>({ok:false,message:'invalid json'}));
  if(res.status===401&&window.showToast){showToast('Unauthorized กรุณา login ที่ Account/Settings','error');}
  if(!res.ok){throw new Error(data.message||data.error||`HTTP ${res.status}`)}
  return data;
}
const api={get:(p,q)=>request('GET',p,{query:q}),post:(p,j)=>request('POST',p,{json:j}),patch:(p,j)=>request('PATCH',p,{json:j}),delete:(p)=>request('DELETE',p,{}),upload:(p,formData)=>request('POST',p,{body:formData})};
window.shopSettings=shopSettings;window.api=api;

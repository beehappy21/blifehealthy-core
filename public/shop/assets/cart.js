const CART_KEY='SHOP_CART';
function loadCart(){return JSON.parse(localStorage.getItem(CART_KEY)||'[]')}
function saveCart(items){localStorage.setItem(CART_KEY,JSON.stringify(items));return items}
function addItem(item){const cart=loadCart();const i=cart.findIndex(x=>x.product_id===item.product_id&&x.variant_id===item.variant_id);if(i>=0)cart[i].qty+=item.qty||1;else cart.push({...item,qty:item.qty||1});return saveCart(cart)}
function updateQty(product_id,variant_id,qty){const cart=loadCart().map(i=>i.product_id===product_id&&i.variant_id===variant_id?{...i,qty:Math.max(1,qty)}:i);return saveCart(cart)}
function removeItem(product_id,variant_id){return saveCart(loadCart().filter(i=>!(i.product_id===product_id&&i.variant_id===variant_id)))}
function clearCart(){return saveCart([])}
function calcTotals(){const items=loadCart();const subtotal=items.reduce((s,i)=>s+(Number(i.price)||0)*(Number(i.qty)||0),0);return{items,subtotal,total:subtotal}}
window.cartStore={loadCart,saveCart,addItem,updateQty,removeItem,clearCart,calcTotals};

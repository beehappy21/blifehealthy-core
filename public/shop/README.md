# WAP Shop (Mobile-first)

## Run
1. `php artisan serve`
2. Open:
   - `/shop/settings.html`
   - `/shop/index.html`

## localStorage keys
- `SHOP_API_BASE`
- `SHOP_MERCHANT_ID`
- `SHOP_TOKEN`
- `SHOP_CART`

## Endpoint Map

| Feature | Method | URL | Auth | Request | Response (example) |
|---|---|---|---|---|---|
| Ping | GET | `/api/ping` | Public | - | `{ "ok": true }` |
| Product list by merchant | GET | `/api/shop/{merchantId}/products` | Public | path: merchantId | `{ ok, items[] }` |
| Product detail | GET | `/api/products/{id}` | Public | path: id | `{ ok, product, variants, images... }` |
| Product reviews | GET | `/api/products/{id}/reviews` | Public | path: id | `{ ok, items[] }` |
| Create/Update review | POST | `/api/products/{id}/reviews` | Bearer | `rating,title,body` | `{ ok, review }` |
| Register | POST | `/api/auth/register` | Public | `name,phone,password...` | `{ token, member_code... }` |
| Login | POST | `/api/auth/login` | Public | `phone,password` | `{ token, member_code }` |
| Me | GET | `/api/me` | Bearer | - | `user json` |
| Logout | POST | `/api/auth/logout` | Bearer | - | `{ ok: true }` |
| My notifications | GET | `/api/me/notifications` | Bearer | - | `{ ok, items[] }` |
| My addresses | GET | `/api/me/addresses` | Bearer | - | `{ ok, items[] }` |
| Create address | POST | `/api/me/addresses` | Bearer | `receiver_name,receiver_phone,address_line1...` | `{ ok, item }` |
| Update address | PATCH | `/api/me/addresses/{id}` | Bearer | partial address fields | `{ ok, item }` |
| Delete address | DELETE | `/api/me/addresses/{id}` | Bearer | - | `{ ok: true }` |
| Create order | POST | `/api/orders` | Bearer | `address_id,items[{product_id,variant_id,qty}]` | `{ ok, item }` |
| List my orders | GET | `/api/orders` | Bearer | - | `{ ok, items[] }` |
| Order detail | GET | `/api/orders/{id}` | Bearer | - | `{ ok, order, items, payment_slip, shipment }` |
| Upload payment slip | POST | `/api/orders/{id}/payment-slip` | Bearer | multipart `slip`, optional `amount`,`transfer_at` | `{ ok, item }` |

### Repo references used for API/schema
- `routes/api.php`
- `app/Http/Controllers/Api/ProductController.php`
- `app/Http/Controllers/Api/ReviewController.php`
- `app/Http/Controllers/Api/AuthController.php`
- `database/migrations/2026_02_25_070909_create_user_addresses_table.php`
- `database/migrations/2026_02_25_070910_create_orders_table.php`
- `database/migrations/2026_02_25_070910_create_order_items_table.php`
- `database/migrations/2026_02_25_070910_create_payment_slips_table.php`
- `database/migrations/2026_02_25_070910_create_shipments_table.php`

## Test checklist
1. ตั้งค่า `SHOP_MERCHANT_ID` ที่ `/shop/settings.html` แล้วเข้า `/shop/index.html` ต้องเห็นสินค้า
2. เข้า `product.html?id=...` แล้วกดเพิ่มตะกร้าได้
3. เข้า `/shop/cart.html` แล้วเพิ่ม/ลดจำนวนสินค้าได้
4. login แล้วเข้า `/shop/checkout.html` สร้าง address และ create order ได้
5. เข้า `/shop/orders.html` เห็นรายการ และ `/shop/order.html?id=...` อัปโหลดสลิปได้

## POS/Coupon safety
- งานนี้ไม่แก้ controller/logic ของ `pos/*`, `coupon*`, `device*`.

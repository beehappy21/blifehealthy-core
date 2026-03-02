# Release Checklist — WAP Merchant/Admin v1

## 1) Preconditions
- ตรวจว่า branch ที่จะปล่อยมี remote tracking ครบ (`git remote -v`, `git branch -vv`)
- ถ้ายังไม่มี remote ให้ตั้งก่อน deploy/merge เช่น:
```bash
git remote add origin <repo-url>
git fetch origin
git branch --set-upstream-to=origin/main main
```
- ตรวจ `.env` ให้ถูกต้อง (`APP_ENV`, DB connection, APP_URL)
- ตั้ง `SESSION_DRIVER=file`
- สร้าง symlink สำหรับไฟล์อัปโหลด:
```bash
php artisan storage:link
```
> Payment slip upload ใช้ `public` disk (`Storage::url(...)`) ต้องมี storage link เพื่อแสดงภาพหน้าเว็บได้

## 2) Deploy/Setup Commands
```bash
composer install --no-interaction --prefer-dist --optimize-autoloader
php artisan migrate --force
php artisan db:seed --force
php artisan test
```

## 3) Local sanity (fresh env)
```bash
APP_ENV=local php artisan migrate:fresh --seed
APP_ENV=local php artisan demo:checkout
APP_ENV=local php artisan tinker --execute="echo DB::table('users')->whereNull('referrer_member_code')->count();"
```
Expected:
- `referrer_member_code` ต้องไม่เป็น `NULL`
- role `admin` มีอยู่
- optional attach role ผ่าน `ADMIN_MEMBER_CODE` ต้องไม่กระทบถ้าไม่ตั้งค่า

## 4) Smoke URLs
- `/shop/index.html`
- `/merchant/slips.html`
- `/merchant/orders.html`
- `/admin/orders.html`

## 5) Smoke API (copy/paste)
```bash
# 1) create order
curl -X POST "$BASE/api/orders" \
  -H "Authorization: Bearer $BUYER_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"address_id":1,"items":[{"product_id":1,"variant_id":1,"qty":1}]}'

# 2) upload slip
curl -X POST "$BASE/api/orders/$ORDER_ID/payment-slip" \
  -H "Authorization: Bearer $BUYER_TOKEN" \
  -F "slip=@/tmp/slip.jpg" \
  -F "amount=100"

# 3) approve slip
curl -X POST "$BASE/api/merchant/payment-slips/$SLIP_ID/approve" \
  -H "Authorization: Bearer $MERCHANT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{}'

# 4) set tracking
curl -X PATCH "$BASE/api/merchant/orders/$ORDER_ID/shipment" \
  -H "Authorization: Bearer $MERCHANT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"provider":"THPOST","tracking_no":"TRK001"}'

# 5) mark shipped
curl -X PATCH "$BASE/api/merchant/orders/$ORDER_ID/mark-shipped" \
  -H "Authorization: Bearer $MERCHANT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{}'

# 6) notifications paging
curl "$BASE/api/me/notifications?limit=50" \
  -H "Authorization: Bearer $BUYER_TOKEN"
curl "$BASE/api/me/notifications?limit=50&before=2026-01-01 00:00:00" \
  -H "Authorization: Bearer $BUYER_TOKEN"
```

## 6) Final regression checks
```bash
php artisan test
php artisan route:list | rg "api/(pos|merchant/pos-devices)"
```

## 7) Known limitations / open questions
- ยังไม่มี `DELIVERED` ใน `orders.status`
- shipping provider ยังไม่มี allow-list validation
- outbox retention/archival policy สำหรับ production growth ยังไม่ได้กำหนด

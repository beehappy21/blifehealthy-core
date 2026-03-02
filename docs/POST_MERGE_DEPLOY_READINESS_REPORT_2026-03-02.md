# Post-merge Deploy Readiness Report (2026-03-02)

## Scope
Validation after merge for WAP Shop + Merchant/Admin v1 flow (no new feature scope).

## Git / Sync
- Current local branch in this environment: `work`.
- Remote `origin` is not configured in this container, so `git checkout main` / `git pull origin main` could not be executed here.
- Code validation was run against current merged code snapshot available in this environment.

## Environment preparation
Commands executed:
- `composer install --no-interaction --prefer-dist`
- `[ -f .env ] || cp .env.example .env`
- `php artisan key:generate --force`
- `php artisan config:clear`
- `php artisan storage:link`
- Verified session driver:
  - `.env: SESSION_DRIVER=file`
  - `.env.example: SESSION_DRIVER=file`

## Database prep
Commands executed:
- `mkdir -p database && touch database/database.sqlite`
- `php artisan migrate:fresh --seed`
- `php artisan demo:checkout`

Result:
- migrations + seed succeeded
- demo checkout succeeded and generated customer/merchant demo data

## Regression checks
Commands executed:
- `php artisan test`
- `php artisan route:list | rg "api/(pos|merchant/pos-devices)"`

Result:
- test suite passed (15 passed)
- POS routes still present

## Manual smoke (API flow, one complete round)
Server:
- `php artisan serve --host=127.0.0.1 --port=8002`

Executed flow via API (using demo users):
1. Customer login
2. Create order (`POST /api/orders`)
3. Upload slip (`POST /api/orders/{id}/payment-slip`) => slip status `submitted`, order moves to `PAYMENT_REVIEW`
4. Merchant approve slip (`POST /api/merchant/payment-slips/{id}/approve`) => order `PAID`
5. Merchant set tracking (`PATCH /api/merchant/orders/{id}/shipment`) => order `SHIPPING_CREATED`
6. Merchant mark shipped (`PATCH /api/merchant/orders/{id}/mark-shipped`) => order `SHIPPED`
7. Admin enforcement check:
   - merchant token to `/api/admin/orders` => `403`
   - admin token to `/api/admin/orders` => `200`
8. Notifications paging:
   - `/api/me/notifications?limit=2`
   - `/api/me/notifications?limit=2&before=<next_before>`
   - observed event sequence across pages: `order.shipped`, `slip.approved`, `slip.submitted`, `order.created`

## Result summary
- Customer/merchant/admin core flow: **PASS**
- Notification contract + paging behavior: **PASS**
- POS route regression check: **PASS**
- Storage link requirement: **PASS**

## Notes / limitations
- Browser-based UI clicks for `/shop/*.html`, `/merchant/*.html`, `/admin/*.html` were not executed in this headless run; equivalent API smoke flow was executed end-to-end and passed.

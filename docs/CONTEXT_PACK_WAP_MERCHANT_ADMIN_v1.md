# CONTEXT PACK — WAP Merchant/Admin v1

## Modules
- Merchant Orders v1
- Admin Orders v1
- Shipments flow
- Notifications v1 (order events + coupon events)

## API Map (current)

### Merchant
- `GET /api/merchant/orders?status=...&q=...`
- `GET /api/merchant/orders/{id}`
- `POST|PATCH /api/merchant/orders/{id}/shipment`
- `PATCH /api/merchant/orders/{id}/mark-shipped`
- `PATCH /api/merchant/orders/{id}/cancel`

### Admin
- `GET /api/admin/orders?status=...&q=...`
- `GET /api/admin/orders/{id}`
- `POST|PATCH /api/admin/orders/{id}/status`
- `PATCH /api/admin/orders/{id}/shipment`
- `PATCH /api/admin/orders/{id}/mark-shipped`

### Notifications
- `GET /api/me/notifications?limit=50&before=YYYY-MM-DD HH:MM:SS`

## Status conventions (DB-canonical)
- `orders.status` (UPPERCASE):
  - `WAITING_PAYMENT`, `PAYMENT_REVIEW`, `PAYMENT_REJECTED`, `PAID`, `SHIPPING_CREATED`, `SHIPPED`, `CANCELLED`
- `payment_slips.status` (lowercase):
  - `submitted`, `approved`, `rejected`

## Error conventions
- `403`: `{"message":"...","code":"FORBIDDEN_RESOURCE"}`
- `409`: `{"message":"...","code":"ORDER_STATE_CONFLICT"}`

## Main flow
1. checkout creates order => `order.created` event
2. customer uploads slip => `PAYMENT_REVIEW` + `slip.submitted`
3. merchant/admin approves slip => `PAID` + `slip.approved`
4. set tracking => `SHIPPING_CREATED`
5. mark shipped => `SHIPPED` + `order.shipped`

## Notifications contract
Each item from `/api/me/notifications`:
```json
{
  "id": "<string-or-int unique within source>",
  "source": "order|coupon",
  "type": "<event_type>",
  "title": "<human readable>",
  "message": "<human readable>",
  "payload": {},
  "created_at": "YYYY-MM-DD HH:MM:SS"
}
```

## Smoke curl steps
1) Create order
```bash
curl -X POST "$BASE/api/orders" -H "Authorization: Bearer $BUYER_TOKEN" -H "Content-Type: application/json" -d '{"address_id":1,"items":[{"product_id":1,"variant_id":1,"qty":1}]}'
```
2) Upload slip
```bash
curl -X POST "$BASE/api/orders/$ORDER_ID/payment-slip" -H "Authorization: Bearer $BUYER_TOKEN" -F "slip=@/tmp/slip.jpg" -F "amount=100"
```
3) Approve
```bash
curl -X POST "$BASE/api/merchant/payment-slips/$SLIP_ID/approve" -H "Authorization: Bearer $MERCHANT_TOKEN" -H "Content-Type: application/json" -d '{}'
```
4) Set tracking
```bash
curl -X PATCH "$BASE/api/merchant/orders/$ORDER_ID/shipment" -H "Authorization: Bearer $MERCHANT_TOKEN" -H "Content-Type: application/json" -d '{"provider":"THPOST","tracking_no":"TRK001"}'
```
5) Mark shipped
```bash
curl -X PATCH "$BASE/api/merchant/orders/$ORDER_ID/mark-shipped" -H "Authorization: Bearer $MERCHANT_TOKEN" -H "Content-Type: application/json" -d '{}'
```
6) Get notifications
```bash
curl "$BASE/api/me/notifications?limit=50" -H "Authorization: Bearer $BUYER_TOKEN"
```

## Open Questions
- Should we introduce `DELIVERED` status in orders?
- Should shipping providers be validated against allow-list?
- Outbox retention policy / archival window for production growth?

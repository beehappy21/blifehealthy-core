# STATUS and Slip Flow (Source-of-Truth Alignment)

## Allowed status values and casing

### `orders.status` (UPPERCASE)
- `WAITING_PAYMENT`
- `PAYMENT_REVIEW`
- `PAYMENT_REJECTED`
- `PAID`
- `SHIPPING_CREATED`
- `SHIPPED`
- `CANCELLED`

### `payment_slips.status` (lowercase)
- `submitted`
- `approved`
- `rejected`

## Flow rules
- Upload slip (`POST /api/orders/{id}/payment-slip`)
  - slip stored as `submitted`
  - order moves to `PAYMENT_REVIEW`
- Approve slip (`POST /api/merchant/payment-slips/{id}/approve` or admin endpoint)
  - slip => `approved`
  - order => `PAID`
  - `orders.paid_at` is set
- Reject slip (`POST /api/merchant/payment-slips/{id}/reject` or admin endpoint)
  - slip => `rejected`
  - order => `PAYMENT_REJECTED`

## Review restrictions
- Reviewer must have permission to the resource (`403`, code `FORBIDDEN_RESOURCE`).
- Order must be in reviewable state (`PAYMENT_REVIEW`) (`409`, code `ORDER_STATE_CONFLICT`).
- Only the latest slip can be reviewed (`409`, code `ORDER_STATE_CONFLICT`).

## 403 vs 409
- `403 FORBIDDEN_RESOURCE`: user authenticated but lacks permission on target order/slip.
- `409 ORDER_STATE_CONFLICT`: request conflicts with current order/slip state.

## Curl smoke flow (example)
> Replace placeholders: `<BASE_URL>`, `<TOKEN>`, `<ORDER_ID>`, `<SLIP_ID>`

1) Create order
```bash
curl -X POST "<BASE_URL>/api/orders" \
  -H "Authorization: Bearer <TOKEN>" \
  -H "Content-Type: application/json" \
  -d '{"address_id":1,"items":[{"product_id":1,"variant_id":1,"qty":1}]}'
```

2) Upload slip
```bash
curl -X POST "<BASE_URL>/api/orders/<ORDER_ID>/payment-slip" \
  -H "Authorization: Bearer <TOKEN>" \
  -F "slip=@/path/to/slip.jpg" \
  -F "amount=199.00"
```

3) List merchant slips (submitted)
```bash
curl "<BASE_URL>/api/merchant/payment-slips?status=submitted" \
  -H "Authorization: Bearer <MERCHANT_TOKEN>"
```

4) Approve slip
```bash
curl -X POST "<BASE_URL>/api/merchant/payment-slips/<SLIP_ID>/approve" \
  -H "Authorization: Bearer <MERCHANT_TOKEN>" \
  -H "Content-Type: application/json" \
  -d '{"note":"ok"}'
```

5) Reject slip (alternative path)
```bash
curl -X POST "<BASE_URL>/api/merchant/payment-slips/<SLIP_ID>/reject" \
  -H "Authorization: Bearer <MERCHANT_TOKEN>" \
  -H "Content-Type: application/json" \
  -d '{"note":"invalid slip"}'
```

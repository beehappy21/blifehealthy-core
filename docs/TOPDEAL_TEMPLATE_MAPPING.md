# Topdeal Template Mapping (Phase 1)

## Mapping
- `home3.html` -> `public/shop/index.html`
- `product-v3.html` or `product-v2.html` -> `public/shop/product.html`
- `cart.html` -> `public/shop/cart.html`
- `checkout.html` -> `public/shop/checkout.html` (+ `public/shop/addresses.html`)
- `my-account.html` -> `public/shop/my-account.html`
- `order-history.html` -> `public/shop/orders.html`

## Phase 1 scope
- Integrate Topdeal Home3 layout for index only.
- Keep current runtime scripts:
  - `public/shop/assets/api.js`
  - `public/shop/assets/app.js`
  - `public/shop/assets/cart.js`

## Required hooks kept on index
- `#header`
- `#search`
- `#list`
- `#toast`
- `#bottomNav`

## Notes
- No `_vendor/*` files committed.
- No build pipeline.

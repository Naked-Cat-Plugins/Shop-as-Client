# Shop as Client (free) — block checkout (v8.0, released)

**Current release: v8.0 — stateless, request-driven block checkout.**
The v7.5 stateful WC-session design is obsolete; all code on `main` is the
stateless one.

The free plugin owns the block-checkout core and is deliberately **unaware of the
Pro add-on** (no "selected customer" concept, no third-party field knowledge).

---

## Stateless design (current code)

No transient server state. State rides each request:

- `block.js` renders the "Shop as client" / "Create user" toggles, holds state on
  `window.ShopAsClient`, registers an apiFetch middleware that injects
  `X-SAC-Active: 1` on Store API requests while the toggle is on, and pushes
  `{ shopAsClient, createUser }` onto the checkout `extensions` payload via
  `setExtensionData`. No server round-trip on toggle.
- `class-shop-as-client-extend-store-endpoint.php`:
  - `capture_checkout_state()` reads the checkout `extensions` payload.
  - `process_order()` (on `woocommerce_store_api_checkout_order_processed`)
    resolves the client **by submitted billing email** (+ create-user), assigns
    the order, stamps SAC meta (even for a guest when no user matches), then fires
    `shop_as_client_checkout_order_processed` for Pro.
  - `suppress_address_meta()` (on `update_user_metadata`/`add_user_metadata`)
    suppresses the manager's own write for the **21 standard fields**
    (`$default_checkout_keys`) while SAC is active, keeping the in-memory value so
    the order still gets the client's address. Third-party fields are Pro's job.

Full Free↔Pro contract + the third-party snapshot/restore subtlety:
`shop-as-client-pro/.claude/CLAUDE.md`.

## SAC order metabox

`shop_as_client_order_metabox()` registered via `add_meta_boxes` on both
`shop_order` (posts mode) and `woocommerce_page_wc-orders` (HPOS). Fires
`shop_as_client_after_order_details` for Pro to add extra fields (PO number,
User Switching link, etc.).

## Settings

Plugin settings live under WooCommerce → Settings → Accounts & Privacy →
Shop as Client (section `shop_as_client`). Redirect to settings on activation.

## WooCommerce minimum: 9.0 | WordPress minimum: 6.4

Tests: sibling `shop-as-client-tests` plugin.

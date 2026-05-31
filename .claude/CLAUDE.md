# Shop as Client (free) — block checkout

**Released (live): v7.5 — stateful (WC session).**
**This working tree: stateless redesign** (branch `feature/improve-block-checkout-flow`),
which supersedes the released design on the next release. Both are documented
below; the code in this tree is the **stateless** one.

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
`shop-as-client-pro-add-on/.claude/CLAUDE.md`.

## Released design (v7.5, for reference)

The shipped version is **stateful**, using `wc()->session`:

- `store_api_update_callback` persists the toggles to session keys
  (`ptwoo-shop-as-client_shop_as_client` / `_create_user`) and snapshots the
  manager's own billing/shipping into `_current_customer_data`.
- `process_order` @100 assigns the order, then `restore_customer_data()` re-applies
  the manager's snapshot onto `wc()->customer`.
- The Store-API schema/data callbacks are empty stubs (population is the Pro
  add-on's job, via its own session + `customCheckoutData`).
- Known issues this redesign fixes: WC-session whole-blob write race, swallowed
  user-create errors, no clean guest-order flag.

Tests: sibling `shop-as-client-tests` plugin.

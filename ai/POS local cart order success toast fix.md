### POS local cart order success toast fix

**Problem**
- After switching to the local cart, clicking Place Order showed the toast “Cart is empty.” even though the order was created successfully. The wrong notification appeared right before the page refreshed.

**Goal**
- Display “Order created!” on successful order placement with the local cart, avoiding the misleading empty-cart toast.

**Files updated**
- `public/assets/back-end/js/admin/pos-script.js`

**Key edits**
- Updated `clearClientCart` to accept an optional parameter to control toast display:
  - Signature: `clearClientCart(toastOptions)`
  - Defaults to previous behavior (shows “Cart is empty.”) when no options are provided.
  - Allows overriding toast visibility, type, and message.
- On successful order placement, invoke `clearClientCart` with a success toast before reloading the page:
  - `clearClientCart({ show: true, type: 'success', message: $("#message-order-created").data("text") || "Order created!" })`

**Why this works**
- Previously, clearing the client cart unconditionally showed the “Cart is empty.” info toast, which fired immediately before page reload in the success handler. By parameterizing `clearClientCart`, we can emit a success toast instead, accurately reflecting that the order was created.

**Behavior before**
- Success path cleared cart ➜ info toast “Cart is empty.” ➜ reload.

**Behavior after**
- Success path clears cart with a success toast “Order created!” ➜ reload.

**Backward compatibility**
- Calls to `clearClientCart()` without arguments behave exactly as before and still show the “Cart is empty.” info toast.

**Testing steps**
1. Switch to local cart mode in POS.
2. Add any product to the cart.
3. Fill required customer fields (name, phone, city, seller, etc.).
4. Click Place Order and confirm.
5. Observe a success toast “Order created!” appears, then the page reloads.
6. Optional: Click Cancel Order (or clear cart) to confirm the empty-cart toast still shows when the cart is intentionally emptied.

**Localization**
- If available, the message is taken from `#message-order-created`’s `data-text` attribute; otherwise falls back to “Order created!”.



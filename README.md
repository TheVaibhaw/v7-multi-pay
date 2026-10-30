# V7 Multi Pay

A WooCommerce payment plugin that sends payments for **selected products** to a **second Stripe account**. Every other product keeps paying through your existing/default gateway (for example the official *WooCommerce Stripe Gateway*), exactly as before.

---

## 1. Requirements & supported versions

| Component | Minimum | Tested up to |
|---|---|---|
| WordPress | 5.8 | 6.8 |
| WooCommerce | 6.0 | 10.3 |
| PHP | 7.4 | 8.3 |
| Stripe API version (pinned) | 2024-06-20 | — |
| WordPress Multisite | Supported (network or per-site activation) | — |
| WooCommerce HPOS (custom order tables) | Supported | — |
| Checkout type | Classic `[woocommerce_checkout]` shortcode **and** Cart/Checkout blocks | — |
| Themes | Any theme: payment happens on Stripe's hosted page, so the plugin adds no front-end CSS | — |

**Required plugin:** WooCommerce.
**Optional:** WooCommerce Stripe Gateway (or any other gateway) as your *default* payment method. V7 Multi Pay doesn't replace it, it only steps in for the selected products.

You also need **two Stripe accounts**:
- **Account A (default):** connected through your normal Stripe plugin.
- **Account B (secondary):** connected through V7 Multi Pay.

---

## 2. How it works

```
Customer cart
     │
     ├── no selected products ──► default gateways (Account A) – untouched
     │
     └── contains selected products ──► "Secondary Stripe" gateway only
                                         │
                                         ▼
                              Stripe hosted Checkout (Account B)
                                         │
                       return URL + signed webhook mark order "Processing"
```

1. You mark products (or whole categories) as **Secondary Stripe** in the admin.
2. At checkout, the plugin looks at the cart:
   - **No selected products:** the V7 gateway is hidden and everything works like a normal store.
   - **Selected products present:** the V7 gateway is shown. Either the default Stripe gateways or all other gateways are hidden, depending on your setting.
3. The customer clicks **Place order** and is redirected to Stripe Checkout on Account B. They pay there and come back to the thank-you page.
4. The order is marked paid. This happens both on return and through the webhook, so it still works if the customer closes the browser.
5. Refunds from the WooCommerce order screen go back to Account B automatically. They use the same mode (test/live) the order was paid in.

**Mixed carts** (selected products and normal products together): one payment can only go to one Stripe account, so you choose:
- **Prevent mixing (recommended):** the customer gets a notice and must buy them in separate orders.
- **Combine:** the whole cart is charged on Account B.

---

## 3. Installation

1. Copy the `v7-multi-pay` folder to `wp-content/plugins/`, or zip it and upload it via **Plugins → Add New → Upload Plugin**.
2. **Single site:** go to **Plugins** and click **Activate**.
   **Multisite:** go to **Network Admin → Plugins** and click **Network Activate**, or activate it per site. Every site has its **own** settings and Stripe keys.
3. If WooCommerce is not active, the plugin shows an admin notice and does nothing. It will not break the site.

---

## 4. Configuration: step by step

### Step 1: Get the keys from Stripe Account B
1. Log in to the **secondary** Stripe dashboard.
2. Go to **Developers → API keys** and copy the **Secret key** (`sk_test_…` for test, `sk_live_…` for live).
   A **restricted key** (`rk_…`) also works if it has write access to *Checkout Sessions* and *Refunds*.

### Step 2: Plugin settings
Go to **WooCommerce → Settings → Payments → V7 Multi Pay – Secondary Stripe**. You can also get there from the **Settings** link on the Plugins page.

| Field | What to enter |
|---|---|
| Enable/Disable | Tick to switch routing on |
| Title / Description | Text the customer sees at checkout |
| Product categories | Optional. Every product in these categories (including sub-categories) uses Account B |
| Mixed carts | *Prevent mixing* or *Charge whole cart on secondary* |
| Other gateways | *Hide all others* or *Hide only default Stripe* (keeps PayPal, COD and so on) |
| Test mode | Tick while testing |
| Test / Live secret key | Keys from Step 1 |
| Test / Live webhook signing secret | From Step 3 |

Click **Save changes**. Invalid keys (wrong prefix) are rejected with a red notice. In that case the previous value is kept, so a typo can never wipe a working key.

### Step 3: Webhook in Stripe Account B
1. Go to **Developers → Webhooks → Add endpoint** in the secondary Stripe dashboard.
2. Use the endpoint URL shown on the settings page: `https://your-site.com/wp-json/v7mp/v1/webhook`.
   On multisite each site has its own URL, for example `https://site2.example.com/wp-json/v7mp/v1/webhook`.
3. Select these events:
   - `checkout.session.completed`
   - `checkout.session.async_payment_succeeded`
   - `checkout.session.async_payment_failed`
4. Copy the **Signing secret** (`whsec_…`) into the matching field in the plugin and save.

### Step 4: Assign products
You can use any of these methods, and they combine:
- **Single product:** open **Products → Edit product → Product data → General tab** and tick **Secondary Stripe**, then **Update**. Variable products: tick it on the parent, and all variations follow.
- **Bulk:** go to **Products → All Products**, tick the products, choose **Bulk actions → Edit**, set **Secondary Stripe = Yes/No**, then **Update**.
- **By category:** use the *Product categories* setting from Step 2.

The **⇄ column** in the products list shows a ✓ for every product routed to Account B.

### Step 5: Test, then go live
1. With **Test mode** on, buy a selected product with card `4242 4242 4242 4242`, any future expiry date and any CVC.
2. Check that the order is **Processing**, the order notes say *"Paid via secondary Stripe account"*, and the payment appears in **Account B (test)**.
3. Buy a normal product and check that it goes through your default gateway and lands in Account A.
4. Try a refund from the order screen.
5. Untick **Test mode**, fill in the **live** key and live webhook secret, and save.

---

## 5. What the screens look like

**Payments list** (WooCommerce → Settings → Payments)
```
┌───────────────────────────────────────────────┬─────────┐
│ Stripe (default)                              │  ● On   │
│ V7 Multi Pay – Secondary Stripe               │  ● On   │  ← Manage
└───────────────────────────────────────────────┴─────────┘
```

**Product edit → General tab**
```
Regular price (₹)  [ 999   ]
Sale price (₹)     [       ]
─────────────────────────────────────────────
Secondary Stripe   [✓] Collect payment for this product on the
                       secondary Stripe account (V7 Multi Pay).
```

**Products list**
```
☐ | Image | Name           | SKU | Stock | Price | ⇄ |
☐ | ▢     | Course A       | C1  | In    | ₹999  | ✓ |
☐ | ▢     | T-shirt        | T1  | In    | ₹499  |   |
```

**Checkout: cart with a selected product**
```
Payment method
 (●) Credit / Debit Card
     You will be redirected to Stripe to complete your payment securely.
[ Place order ]
```
The default Stripe card form is not shown. With a normal cart, the reverse happens: only your default gateways are shown.

**Mixed cart notice (Prevent mixing mode)**
```
⚠ This product must be purchased in a separate order.
  Please complete or empty your current cart first.
```

**Order notes after payment**
```
Paid via secondary Stripe account (Payment Intent: pi_3Q…).
Order status changed from Pending payment to Processing.
```

---

## 6. Security

- Secret keys are stored in the site's WooCommerce gateway option, the same way the official Stripe plugin stores them. They are never sent to the browser.
- Card data never touches your server, because payment happens on Stripe's hosted Checkout page (PCI SAQ-A).
- Webhooks are verified with Stripe's HMAC-SHA256 signature using a timing-safe comparison and a 5-minute replay window. Unsigned or forged calls get a `400` response.
- The return URL is verified using the order key (`hash_equals`) **and** a match on the stored Checkout Session ID. The payment status is then re-fetched from Stripe server-side, so the URL itself is never trusted.
- Webhook events are only applied if the order uses this gateway and the session ID matches. Events from other stores that share the same Stripe account are ignored.
- All input is sanitized and all output escaped. Admin saves go through WooCommerce/WordPress nonce and capability checks, and bulk edit also checks `edit_product` for each product.
- Every file blocks direct access (`ABSPATH` check).

## 7. Performance & SEO

- **No CSS or JS on any front-end page.** The single small script is only registered for the Checkout block, through WooCommerce's own loader.
- Product decisions are cached per request. The category list is loaded only on the settings screen.
- No custom database tables and no cron jobs. Stripe is called only when an order is placed, refunded, or returned from Stripe.
- No impact on page markup, meta tags, or Core Web Vitals.

## 8. Uninstall

Deleting the plugin from **Plugins** removes its settings and the product flags on **every site** of a multisite network. Order meta (payment intent IDs) is kept on purpose, for accounting records.

## 9. Developer hooks

```php
// Force a product to Account B (or not).
add_filter( 'v7mp_is_product_secondary', function ( $is, $product ) { return $is; }, 10, 2 );

// Change the Stripe Checkout Session parameters (e.g. enable promotion codes).
add_filter( 'v7mp_checkout_session_params', function ( $params, $order ) {
	$params['allow_promotion_codes'] = 'true';
	return $params;
}, 10, 2 );

// Change the mixed-cart message.
add_filter( 'v7mp_mixed_cart_message', fn() => 'Please order this item separately.' );
```

## 10. Troubleshooting

| Problem | Fix |
|---|---|
| Secondary gateway does not show | Check that it is enabled, a key is set for the current mode (test/live), and the product or category is assigned |
| Order stays *Pending* after payment | The webhook is missing or wrong. Check the URL, the events, and the `whsec_` secret for the correct mode |
| "Key looks invalid and was not saved" | Use `sk_test_`/`sk_live_` (or `rk_…`) keys and a `whsec_` webhook secret |
| Customer can't add a product | *Prevent mixing* is on and the cart holds the other type of product. This is expected |
| Refund fails | The order must have been paid via V7 Multi Pay. Refunds use the mode the order was paid in |

## 11. File structure

```
v7-multi-pay/
├── v7-multi-pay.php                 Bootstrap, HPOS/blocks compatibility
├── uninstall.php                    Cleanup (multisite aware)
├── readme.txt                       WordPress.org-style readme
├── assets/js/blocks.js              Checkout block registration
└── includes/
    ├── class-v7mp-plugin.php        Gateway routing, mixed-cart rules
    ├── class-v7mp-rules.php         Which products/carts/orders use Account B
    ├── class-v7mp-gateway.php       Payment gateway, settings, refunds
    ├── class-v7mp-stripe-api.php    Stripe HTTP client, webhook signature check
    ├── class-v7mp-webhook.php       REST endpoint /wp-json/v7mp/v1/webhook
    ├── class-v7mp-blocks.php        Cart/Checkout blocks integration
    └── admin/class-v7mp-admin-product.php   Product checkbox, bulk edit, list column
```

## Changelog

**1.0.0:** Initial release.

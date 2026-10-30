=== V7 Multi Pay ===
Contributors: vaibhawkumar
Tags: woocommerce, stripe, payment gateway, multiple stripe accounts, split payments
Requires at least: 5.8
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Route selected WooCommerce products to a second Stripe account. Everything else keeps paying through your default gateway.

== Description ==

* Assign products individually (Product data → General → "Secondary Stripe"), in bulk (Products → Bulk Edit) or by category (with sub-categories).
* When the cart contains assigned products, checkout shows the "V7 Multi Pay – Secondary Stripe" gateway and hides the default Stripe gateways (or all other gateways – configurable).
* Carts without assigned products are untouched – your existing Stripe/WooCommerce setup runs exactly as before.
* Mixed carts: prevent mixing (default) or charge the whole cart on the secondary account.
* Uses Stripe hosted Checkout (PCI SAQ-A, no extra scripts on your pages), test & live modes, signed webhooks, and refunds from the WooCommerce order screen.
* Works with classic checkout, Cart/Checkout blocks, the "Pay for order" page, HPOS, and both single sites and Multisite (settings are per site).

== Installation ==

1. Upload the `v7-multi-pay` folder to `/wp-content/plugins/` and activate it (or Network Activate on Multisite).
2. Go to WooCommerce → Settings → Payments → V7 Multi Pay – Secondary Stripe.
3. Enter the secondary account's secret key(s) and enable the gateway.
4. In the secondary Stripe dashboard add a webhook to the URL shown on the settings page with the events
   `checkout.session.completed`, `checkout.session.async_payment_succeeded`, `checkout.session.async_payment_failed`, and paste its signing secret.
5. Assign products or categories.

== Developer hooks ==

* `v7mp_is_product_secondary` (filter) – override the routing decision for a product.
* `v7mp_checkout_session_params` (filter) – modify Stripe Checkout Session parameters.
* `v7mp_mixed_cart_message` (filter) – change the mixed-cart notice.

== Changelog ==

= 1.0.0 =
* Initial release.

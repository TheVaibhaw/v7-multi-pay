<?php
/**
 * Plugin Name:          V7 Multi Pay
 * Description:          Route selected WooCommerce products to a secondary Stripe account at checkout. Every other product keeps using your default payment gateways.
 * Version:              1.0.0
 * Requires at least:    5.8
 * Requires PHP:         7.4
 * Requires Plugins:     woocommerce
 * Author:               Vaibhaw Kumar
 * License:              GPL-2.0-or-later
 * License URI:          https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:          v7-multi-pay
 * Domain Path:          /languages
 * WC requires at least: 6.0
 * WC tested up to:      10.3
 *
 * @package V7_Multi_Pay
 */

defined( 'ABSPATH' ) || exit;

define( 'V7MP_VERSION', '1.0.0' );
define( 'V7MP_FILE', __FILE__ );
define( 'V7MP_PATH', plugin_dir_path( __FILE__ ) );
define( 'V7MP_URL', plugin_dir_url( __FILE__ ) );
define( 'V7MP_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Declare compatibility with HPOS (custom order tables) and the Cart/Checkout blocks.
 */
add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', V7MP_FILE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', V7MP_FILE, true );
		}
	}
);

/**
 * Boot the plugin once all plugins are loaded so WooCommerce is available.
 */
function v7mp_init() {
	if ( ! class_exists( 'WooCommerce' ) || ! class_exists( 'WC_Payment_Gateway' ) ) {
		add_action(
			'admin_notices',
			static function () {
				if ( current_user_can( 'activate_plugins' ) ) {
					echo '<div class="notice notice-error"><p>' . esc_html__( 'V7 Multi Pay requires WooCommerce to be installed and active.', 'v7-multi-pay' ) . '</p></div>';
				}
			}
		);
		return;
	}

	require_once V7MP_PATH . 'includes/class-v7mp-stripe-api.php';
	require_once V7MP_PATH . 'includes/class-v7mp-rules.php';
	require_once V7MP_PATH . 'includes/class-v7mp-gateway.php';
	require_once V7MP_PATH . 'includes/class-v7mp-webhook.php';
	require_once V7MP_PATH . 'includes/class-v7mp-plugin.php';

	if ( is_admin() ) {
		require_once V7MP_PATH . 'includes/admin/class-v7mp-admin-product.php';
	}

	V7MP_Plugin::instance();
}
add_action( 'plugins_loaded', 'v7mp_init', 11 );

/**
 * Load translations.
 */
function v7mp_load_textdomain() {
	load_plugin_textdomain( 'v7-multi-pay', false, dirname( V7MP_BASENAME ) . '/languages' );
}
add_action( 'init', 'v7mp_load_textdomain' );

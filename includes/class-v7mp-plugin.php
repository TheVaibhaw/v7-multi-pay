<?php
/**
 * Main plugin controller: registers the gateway and routes checkout to the right Stripe account.
 *
 * @package V7_Multi_Pay
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class V7MP_Plugin
 */
final class V7MP_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var V7MP_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get the instance.
	 *
	 * @return V7MP_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Hook everything up.
	 */
	private function __construct() {
		add_filter( 'woocommerce_payment_gateways', array( $this, 'register_gateway' ) );
		add_filter( 'woocommerce_available_payment_gateways', array( $this, 'filter_gateways' ), 100 );
		add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'validate_add_to_cart' ), 10, 2 );
		add_action( 'woocommerce_check_cart_items', array( $this, 'check_cart_items' ) );
		add_action( 'woocommerce_blocks_payment_method_type_registration', array( $this, 'register_block_support' ) );
		add_action( 'rest_api_init', array( 'V7MP_Webhook', 'register_routes' ) );
		add_filter( 'plugin_action_links_' . V7MP_BASENAME, array( $this, 'action_links' ) );
	}

	/**
	 * Register the secondary gateway.
	 *
	 * @param string[] $gateways Gateway class names.
	 * @return string[]
	 */
	public function register_gateway( $gateways ) {
		$gateways[] = 'V7MP_Gateway';
		return $gateways;
	}

	/**
	 * Show the secondary gateway only for selected products, and hide the default
	 * Stripe gateways (or all others) in that case.
	 *
	 * @param WC_Payment_Gateway[] $gateways Available gateways.
	 * @return WC_Payment_Gateway[]
	 */
	public function filter_gateways( $gateways ) {
		if ( ! is_array( $gateways ) || ( is_admin() && ! wp_doing_ajax() ) ) {
			return $gateways;
		}

		$state = $this->current_state();

		if ( ! $state || ! $state['secondary'] || ! V7MP_Rules::is_enabled() || ! isset( $gateways[ V7MP_Gateway::ID ] ) ) {
			unset( $gateways[ V7MP_Gateway::ID ] );
			return $gateways;
		}

		$hide_all = 'stripe' !== V7MP_Rules::setting( 'hide_gateways', 'all' );

		foreach ( array_keys( $gateways ) as $id ) {
			if ( V7MP_Gateway::ID === $id ) {
				continue;
			}
			// Default Stripe plugin gateway IDs are "stripe" and "stripe_*" (stripe_sepa, stripe_ideal, ...).
			if ( $hide_all || 'stripe' === $id || 0 === strpos( $id, 'stripe_' ) ) {
				unset( $gateways[ $id ] );
			}
		}

		return $gateways;
	}

	/**
	 * Cart/order state for the current checkout context.
	 *
	 * @return array|null
	 */
	private function current_state() {
		if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'order-pay' ) ) {
			$order = wc_get_order( absint( get_query_var( 'order-pay' ) ) );
			return $order ? V7MP_Rules::order_state( $order ) : null;
		}

		return ( function_exists( 'WC' ) && WC()->cart ) ? V7MP_Rules::cart_state() : null;
	}

	/**
	 * Whether carts must not mix selected and normal products.
	 *
	 * @return bool
	 */
	private function prevent_mixing() {
		return V7MP_Rules::is_enabled() && 'combine' !== V7MP_Rules::setting( 'mixed_cart', 'separate' );
	}

	/**
	 * Block adding a product that would create a mixed cart.
	 *
	 * @param bool $passed     Validation result.
	 * @param int  $product_id Product ID.
	 * @return bool
	 */
	public function validate_add_to_cart( $passed, $product_id ) {
		if ( ! $passed || ! $this->prevent_mixing() || ! WC()->cart || WC()->cart->is_empty() ) {
			return $passed;
		}

		$state    = V7MP_Rules::cart_state();
		$incoming = V7MP_Rules::is_product_secondary( $product_id );

		if ( ( $incoming && $state['default'] ) || ( ! $incoming && $state['secondary'] ) ) {
			wc_add_notice( $this->mixed_message(), 'error' );
			return false;
		}

		return $passed;
	}

	/**
	 * Stop checkout if a mixed cart slipped through (e.g. settings changed mid-session).
	 */
	public function check_cart_items() {
		if ( ! $this->prevent_mixing() ) {
			return;
		}

		$state = V7MP_Rules::cart_state();

		if ( $state['secondary'] && $state['default'] ) {
			wc_add_notice( $this->mixed_message(), 'error' );
		}
	}

	/**
	 * Mixed cart notice.
	 *
	 * @return string
	 */
	private function mixed_message() {
		return (string) apply_filters(
			'v7mp_mixed_cart_message',
			__( 'This product must be purchased in a separate order. Please complete or empty your current cart first.', 'v7-multi-pay' )
		);
	}

	/**
	 * Register Cart/Checkout block support.
	 *
	 * @param \Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $registry Registry.
	 */
	public function register_block_support( $registry ) {
		if ( ! class_exists( '\Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
			return;
		}
		require_once V7MP_PATH . 'includes/class-v7mp-blocks.php';
		$registry->register( new V7MP_Blocks() );
	}

	/**
	 * Settings link on the Plugins screen.
	 *
	 * @param string[] $links Links.
	 * @return string[]
	 */
	public function action_links( $links ) {
		$url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . V7MP_Gateway::ID );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'v7-multi-pay' ) . '</a>' );
		return $links;
	}
}

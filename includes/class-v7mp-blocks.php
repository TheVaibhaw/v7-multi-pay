<?php
/**
 * Cart & Checkout blocks integration.
 *
 * @package V7_Multi_Pay
 */

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * Class V7MP_Blocks
 */
final class V7MP_Blocks extends AbstractPaymentMethodType {

	/**
	 * Payment method name (matches the gateway ID).
	 *
	 * @var string
	 */
	protected $name = V7MP_Gateway::ID;

	/**
	 * Load settings.
	 */
	public function initialize() {
		$this->settings = get_option( V7MP_Rules::OPTION_KEY, array() );
	}

	/**
	 * Whether the method is active. Per-cart visibility is handled server side
	 * via woocommerce_available_payment_gateways.
	 *
	 * @return bool
	 */
	public function is_active() {
		return V7MP_Rules::is_enabled();
	}

	/**
	 * Script handles.
	 *
	 * @return string[]
	 */
	public function get_payment_method_script_handles() {
		wp_register_script(
			'v7mp-blocks',
			V7MP_URL . 'assets/js/blocks.js',
			array( 'wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities' ),
			V7MP_VERSION,
			true
		);
		return array( 'v7mp-blocks' );
	}

	/**
	 * Data passed to the script.
	 *
	 * @return array
	 */
	public function get_payment_method_data() {
		return array(
			'title'       => $this->get_setting( 'title' ),
			'description' => $this->get_setting( 'description' ),
			'supports'    => array( 'products' ),
		);
	}
}

<?php
/**
 * Secondary Stripe account payment gateway (Stripe hosted Checkout).
 *
 * @package V7_Multi_Pay
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class V7MP_Gateway
 */
class V7MP_Gateway extends WC_Payment_Gateway {

	const ID = 'v7mp_stripe';

	/**
	 * Test mode flag.
	 *
	 * @var bool
	 */
	public $testmode;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id                 = self::ID;
		$this->has_fields         = false;
		$this->method_title       = __( 'V7 Multi Pay – Secondary Stripe', 'v7-multi-pay' );
		$this->method_description = __( 'Payments for selected products are collected on a second Stripe account via Stripe Checkout. All other products keep using your default gateways.', 'v7-multi-pay' );
		$this->supports           = array( 'products', 'refunds' );

		$this->init_form_fields();
		$this->init_settings();

		$this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );
		$this->testmode    = 'yes' === $this->get_option( 'testmode' );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_api_v7mp_return', array( $this, 'handle_return' ) );
	}

	/**
	 * Settings fields.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'             => array(
				'title'   => __( 'Enable/Disable', 'v7-multi-pay' ),
				'type'    => 'checkbox',
				'label'   => __( 'Route selected products to the secondary Stripe account', 'v7-multi-pay' ),
				'default' => 'no',
			),
			'title'               => array(
				'title'       => __( 'Title', 'v7-multi-pay' ),
				'type'        => 'text',
				'description' => __( 'Payment method title shown at checkout.', 'v7-multi-pay' ),
				'default'     => __( 'Credit / Debit Card', 'v7-multi-pay' ),
				'desc_tip'    => true,
			),
			'description'         => array(
				'title'       => __( 'Description', 'v7-multi-pay' ),
				'type'        => 'textarea',
				'description' => __( 'Shown below the payment method at checkout.', 'v7-multi-pay' ),
				'default'     => __( 'You will be redirected to Stripe to complete your payment securely.', 'v7-multi-pay' ),
				'desc_tip'    => true,
			),
			'rules_title'         => array(
				'title'       => __( 'Product routing', 'v7-multi-pay' ),
				'type'        => 'title',
				'description' => __( 'Products can also be assigned individually from Products → Edit → General tab, or in bulk via Products → Bulk Edit.', 'v7-multi-pay' ),
			),
			'categories'          => array(
				'title'       => __( 'Product categories', 'v7-multi-pay' ),
				'type'        => 'multiselect',
				'class'       => 'wc-enhanced-select',
				'description' => __( 'All products in these categories (including sub-categories) use the secondary account.', 'v7-multi-pay' ),
				'desc_tip'    => true,
				'options'     => array(), // Populated lazily in admin_options() to keep front-end requests light.
				'default'     => array(),
			),
			'mixed_cart'          => array(
				'title'       => __( 'Mixed carts', 'v7-multi-pay' ),
				'type'        => 'select',
				'class'       => 'wc-enhanced-select',
				'description' => __( 'What happens when the cart contains both selected and normal products. A single payment can only go to one Stripe account.', 'v7-multi-pay' ),
				'default'     => 'separate',
				'options'     => array(
					'separate' => __( 'Prevent mixing – customer must check out separately (recommended)', 'v7-multi-pay' ),
					'combine'  => __( 'Charge the whole cart on the secondary account', 'v7-multi-pay' ),
				),
			),
			'hide_gateways'       => array(
				'title'       => __( 'Other gateways', 'v7-multi-pay' ),
				'type'        => 'select',
				'class'       => 'wc-enhanced-select',
				'description' => __( 'Which gateways to hide when the cart contains selected products.', 'v7-multi-pay' ),
				'default'     => 'all',
				'options'     => array(
					'all'    => __( 'Hide all other gateways', 'v7-multi-pay' ),
					'stripe' => __( 'Hide only the default Stripe gateways (PayPal, COD etc. stay available)', 'v7-multi-pay' ),
				),
			),
			'api_title'           => array(
				'title'       => __( 'Secondary Stripe account', 'v7-multi-pay' ),
				'type'        => 'title',
				/* translators: %s: webhook URL */
				'description' => sprintf( __( 'Add this webhook endpoint in the secondary Stripe dashboard: %s — events: checkout.session.completed, checkout.session.async_payment_succeeded, checkout.session.async_payment_failed.', 'v7-multi-pay' ), '<code>' . esc_url( rest_url( 'v7mp/v1/webhook' ) ) . '</code>' ),
			),
			'testmode'            => array(
				'title'   => __( 'Test mode', 'v7-multi-pay' ),
				'type'    => 'checkbox',
				'label'   => __( 'Use test keys', 'v7-multi-pay' ),
				'default' => 'yes',
			),
			'test_secret_key'     => array(
				'title' => __( 'Test secret key', 'v7-multi-pay' ),
				'type'  => 'password',
			),
			'test_webhook_secret' => array(
				'title' => __( 'Test webhook signing secret', 'v7-multi-pay' ),
				'type'  => 'password',
			),
			'live_secret_key'     => array(
				'title' => __( 'Live secret key', 'v7-multi-pay' ),
				'type'  => 'password',
			),
			'live_webhook_secret' => array(
				'title' => __( 'Live webhook signing secret', 'v7-multi-pay' ),
				'type'  => 'password',
			),
		);
	}

	/**
	 * Render settings, populating category options only here.
	 */
	public function admin_options() {
		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'fields'     => 'id=>name',
			)
		);

		if ( ! is_wp_error( $terms ) ) {
			$this->form_fields['categories']['options'] = $terms;
		}

		parent::admin_options();
	}

	/**
	 * Validate key format on save.
	 *
	 * @param string $key   Field key.
	 * @param string $value Posted value.
	 * @return string
	 */
	public function validate_password_field( $key, $value ) {
		$value = trim( (string) parent::validate_password_field( $key, $value ) );

		if ( '' === $value ) {
			return $value;
		}

		$prefixes = array(
			'test_secret_key'     => array( 'sk_test_', 'rk_test_' ),
			'live_secret_key'     => array( 'sk_live_', 'rk_live_' ),
			'test_webhook_secret' => array( 'whsec_' ),
			'live_webhook_secret' => array( 'whsec_' ),
		);

		if ( isset( $prefixes[ $key ] ) ) {
			foreach ( $prefixes[ $key ] as $prefix ) {
				if ( 0 === strpos( $value, $prefix ) ) {
					return $value;
				}
			}
			/* translators: %s: field title */
			WC_Admin_Settings::add_error( sprintf( __( '%s looks invalid and was not saved.', 'v7-multi-pay' ), $this->form_fields[ $key ]['title'] ) );
			return $this->get_option( $key );
		}

		return $value;
	}

	/**
	 * Save settings and reset rule caches.
	 *
	 * @return bool
	 */
	public function process_admin_options() {
		$saved = parent::process_admin_options();
		V7MP_Rules::flush();
		return $saved;
	}

	/**
	 * Available only when configured.
	 *
	 * @return bool
	 */
	public function is_available() {
		return parent::is_available() && '' !== $this->get_secret_key();
	}

	/**
	 * Secret key for the given (or current) mode.
	 *
	 * @param string|null $mode "test" or "live".
	 * @return string
	 */
	public function get_secret_key( $mode = null ) {
		$mode = $mode ? $mode : ( $this->testmode ? 'test' : 'live' );
		return (string) $this->get_option( $mode . '_secret_key' );
	}

	/**
	 * API client for the given mode.
	 *
	 * @param string|null $mode "test" or "live".
	 * @return V7MP_Stripe_API
	 */
	public function api( $mode = null ) {
		return new V7MP_Stripe_API( $this->get_secret_key( $mode ) );
	}

	/**
	 * Create a Stripe Checkout Session and redirect the customer to it.
	 *
	 * @param int $order_id Order ID.
	 * @return array
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return array( 'result' => 'failure' );
		}

		$currency = $order->get_currency();
		$names    = array();

		foreach ( $order->get_items() as $item ) {
			$names[] = $item->get_name() . ' × ' . $item->get_quantity();
		}

		/* translators: %s: order number */
		$label       = sprintf( __( 'Order #%s', 'v7-multi-pay' ), $order->get_order_number() );
		$description = wp_html_excerpt( implode( ', ', $names ), 490, '…' );

		$return_url = add_query_arg(
			array(
				'order_id'   => $order->get_id(),
				'key'        => $order->get_order_key(),
				'session_id' => '{CHECKOUT_SESSION_ID}',
			),
			WC()->api_request_url( 'v7mp_return' )
		);

		$params = array(
			'mode'                => 'payment',
			'client_reference_id' => (string) $order->get_id(),
			// Stripe must see the literal placeholder, so it is not URL-encoded.
			'success_url'         => str_replace( '%7BCHECKOUT_SESSION_ID%7D', '{CHECKOUT_SESSION_ID}', $return_url ),
			'cancel_url'          => $order->get_checkout_payment_url(),
			'locale'              => 'auto',
			'line_items'          => array(
				array(
					'quantity'   => 1,
					'price_data' => array(
						'currency'     => strtolower( $currency ),
						'unit_amount'  => V7MP_Stripe_API::to_minor_units( $order->get_total(), $currency ),
						'product_data' => array_filter(
							array(
								'name'        => $label,
								'description' => $description,
							)
						),
					),
				),
			),
			'metadata'            => array(
				'order_id' => $order->get_id(),
				'site_url' => home_url(),
			),
			'payment_intent_data' => array(
				'description' => $label . ' – ' . get_bloginfo( 'name' ),
				'metadata'    => array(
					'order_id' => $order->get_id(),
					'site_url' => home_url(),
				),
			),
		);

		if ( $order->get_billing_email() ) {
			$params['customer_email'] = $order->get_billing_email();
		}

		/**
		 * Filter the Stripe Checkout Session parameters.
		 *
		 * @param array    $params Parameters.
		 * @param WC_Order $order  Order.
		 */
		$params  = apply_filters( 'v7mp_checkout_session_params', $params, $order );
		$session = $this->api()->request( 'checkout/sessions', $params );

		if ( is_wp_error( $session ) || empty( $session['url'] ) ) {
			$message = is_wp_error( $session ) ? $session->get_error_message() : __( 'Could not start the payment.', 'v7-multi-pay' );
			$order->add_order_note( sprintf( /* translators: %s: error */ __( 'Secondary Stripe error: %s', 'v7-multi-pay' ), $message ) );
			wc_add_notice( __( 'We could not start your payment. Please try again.', 'v7-multi-pay' ), 'error' );
			return array( 'result' => 'failure' );
		}

		$order->update_meta_data( '_v7mp_session_id', $session['id'] );
		$order->update_meta_data( '_v7mp_mode', $this->testmode ? 'test' : 'live' );
		$order->save();

		return array(
			'result'   => 'success',
			'redirect' => $session['url'],
		);
	}

	/**
	 * Customer returns from Stripe Checkout.
	 */
	public function handle_return() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Verified with the order key and Stripe session.
		$order_id   = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
		$order_key  = isset( $_GET['key'] ) ? wc_clean( wp_unslash( $_GET['key'] ) ) : '';
		$session_id = isset( $_GET['session_id'] ) ? wc_clean( wp_unslash( $_GET['session_id'] ) ) : '';
		// phpcs:enable

		$order = wc_get_order( $order_id );

		if ( ! $order || ! hash_equals( $order->get_order_key(), (string) $order_key ) || $session_id !== $order->get_meta( '_v7mp_session_id' ) ) {
			wp_safe_redirect( wc_get_cart_url() );
			exit;
		}

		if ( ! $order->is_paid() ) {
			$session = $this->api( $order->get_meta( '_v7mp_mode' ) )->request( 'checkout/sessions/' . rawurlencode( $session_id ), array(), 'GET' );

			if ( ! is_wp_error( $session ) ) {
				$this->sync_session( $order, $session );
			}
		}

		if ( $order->is_paid() || $order->has_status( 'on-hold' ) ) {
			if ( WC()->cart ) {
				WC()->cart->empty_cart();
			}
			wp_safe_redirect( $this->get_return_url( $order ) );
			exit;
		}

		wc_add_notice( __( 'Payment was not completed. Please try again.', 'v7-multi-pay' ), 'error' );
		wp_safe_redirect( $order->get_checkout_payment_url() );
		exit;
	}

	/**
	 * Apply a Checkout Session's state to the order. Shared by the return handler and webhooks.
	 *
	 * @param WC_Order $order   Order.
	 * @param array    $session Checkout Session object.
	 */
	public function sync_session( WC_Order $order, array $session ) {
		if ( $order->is_paid() || empty( $session['id'] ) || $session['id'] !== $order->get_meta( '_v7mp_session_id' ) ) {
			return;
		}

		$intent = isset( $session['payment_intent'] ) ? $session['payment_intent'] : '';
		$intent = is_array( $intent ) ? $intent['id'] : (string) $intent;

		if ( isset( $session['payment_status'] ) && 'paid' === $session['payment_status'] ) {
			$order->update_meta_data( '_v7mp_payment_intent', $intent );
			$order->payment_complete( $intent );
			/* translators: %s: payment intent ID */
			$order->add_order_note( sprintf( __( 'Paid via secondary Stripe account (Payment Intent: %s).', 'v7-multi-pay' ), $intent ) );
		} elseif ( isset( $session['status'] ) && 'complete' === $session['status'] && ! $order->has_status( 'on-hold' ) ) {
			// Async methods (bank debits etc.) complete later via webhook.
			$order->update_meta_data( '_v7mp_payment_intent', $intent );
			$order->update_status( 'on-hold', __( 'Awaiting asynchronous payment confirmation from secondary Stripe account.', 'v7-multi-pay' ) );
		}
	}

	/**
	 * Refund through the account the order was paid on.
	 *
	 * @param int    $order_id Order ID.
	 * @param float  $amount   Amount.
	 * @param string $reason   Reason.
	 * @return bool|WP_Error
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$order  = wc_get_order( $order_id );
		$intent = $order ? $order->get_meta( '_v7mp_payment_intent' ) : '';

		if ( ! $intent ) {
			return new WP_Error( 'v7mp_refund', __( 'No secondary Stripe payment found for this order.', 'v7-multi-pay' ) );
		}

		$params = array(
			'payment_intent' => $intent,
			'metadata'       => array(
				'order_id' => $order->get_id(),
				'reason'   => wp_html_excerpt( (string) $reason, 450 ),
			),
		);

		if ( null !== $amount ) {
			$params['amount'] = V7MP_Stripe_API::to_minor_units( $amount, $order->get_currency() );
		}

		$refund = $this->api( $order->get_meta( '_v7mp_mode' ) )->request( 'refunds', $params );

		if ( is_wp_error( $refund ) ) {
			return $refund;
		}

		/* translators: 1: amount 2: refund ID */
		$order->add_order_note( sprintf( __( 'Refunded %1$s on secondary Stripe account (Refund: %2$s).', 'v7-multi-pay' ), wc_price( $amount, array( 'currency' => $order->get_currency() ) ), $refund['id'] ) );

		return true;
	}
}

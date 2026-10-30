<?php
/**
 * Stripe webhook endpoint for the secondary account.
 *
 * @package V7_Multi_Pay
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class V7MP_Webhook
 */
class V7MP_Webhook {

	/**
	 * Register the REST route: /wp-json/v7mp/v1/webhook (per site on multisite).
	 */
	public static function register_routes() {
		register_rest_route(
			'v7mp/v1',
			'/webhook',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle' ),
				'permission_callback' => '__return_true', // Authenticity is checked with the Stripe signature.
			)
		);
	}

	/**
	 * Handle an incoming event.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function handle( WP_REST_Request $request ) {
		$payload = $request->get_body();
		$event   = json_decode( $payload, true );

		if ( ! is_array( $event ) || empty( $event['type'] ) ) {
			return new WP_REST_Response( array( 'error' => 'invalid payload' ), 400 );
		}

		$mode   = empty( $event['livemode'] ) ? 'test' : 'live';
		$secret = (string) V7MP_Rules::setting( $mode . '_webhook_secret' );

		if ( ! V7MP_Stripe_API::verify_signature( $payload, (string) $request->get_header( 'stripe_signature' ), $secret ) ) {
			return new WP_REST_Response( array( 'error' => 'invalid signature' ), 400 );
		}

		$session = isset( $event['data']['object'] ) ? $event['data']['object'] : array();
		$order   = ! empty( $session['metadata']['order_id'] ) ? wc_get_order( absint( $session['metadata']['order_id'] ) ) : false;

		// Ignore events for other sites/stores sharing the Stripe account.
		if ( ! $order || V7MP_Gateway::ID !== $order->get_payment_method() || empty( $session['id'] ) || $session['id'] !== $order->get_meta( '_v7mp_session_id' ) ) {
			return new WP_REST_Response( array( 'received' => true ), 200 );
		}

		$gateways = WC()->payment_gateways()->payment_gateways();
		$gateway  = isset( $gateways[ V7MP_Gateway::ID ] ) ? $gateways[ V7MP_Gateway::ID ] : null;

		switch ( $event['type'] ) {
			case 'checkout.session.completed':
			case 'checkout.session.async_payment_succeeded':
				if ( $gateway ) {
					$gateway->sync_session( $order, $session );
				}
				break;

			case 'checkout.session.async_payment_failed':
				if ( ! $order->is_paid() ) {
					$order->update_status( 'failed', __( 'Asynchronous payment failed on secondary Stripe account.', 'v7-multi-pay' ) );
				}
				break;
		}

		return new WP_REST_Response( array( 'received' => true ), 200 );
	}
}

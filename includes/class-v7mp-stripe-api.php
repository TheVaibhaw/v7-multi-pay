<?php
/**
 * Minimal Stripe REST client built on the WordPress HTTP API (no SDK needed).
 *
 * @package V7_Multi_Pay
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class V7MP_Stripe_API
 */
class V7MP_Stripe_API {

	const ENDPOINT    = 'https://api.stripe.com/v1/';
	const API_VERSION = '2024-06-20';

	/**
	 * Currencies Stripe treats as having no minor unit.
	 *
	 * @var string[]
	 */
	const ZERO_DECIMAL = array( 'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF' );

	/**
	 * Currencies with three minor-unit digits.
	 *
	 * @var string[]
	 */
	const THREE_DECIMAL = array( 'BHD', 'JOD', 'KWD', 'OMR', 'TND' );

	/**
	 * Secret key.
	 *
	 * @var string
	 */
	private $secret_key;

	/**
	 * Constructor.
	 *
	 * @param string $secret_key Stripe secret or restricted key.
	 */
	public function __construct( $secret_key ) {
		$this->secret_key = (string) $secret_key;
	}

	/**
	 * Send a request to Stripe.
	 *
	 * @param string $path   API path, e.g. "checkout/sessions".
	 * @param array  $params Request parameters.
	 * @param string $method HTTP method.
	 * @return array|WP_Error Decoded response or error.
	 */
	public function request( $path, array $params = array(), $method = 'POST' ) {
		if ( '' === $this->secret_key ) {
			return new WP_Error( 'v7mp_no_key', __( 'Secondary Stripe account is not configured.', 'v7-multi-pay' ) );
		}

		$url  = self::ENDPOINT . ltrim( $path, '/' );
		$args = array(
			'method'  => $method,
			'timeout' => 45,
			'headers' => array(
				'Authorization'  => 'Bearer ' . $this->secret_key,
				'Stripe-Version' => self::API_VERSION,
				'User-Agent'     => 'V7MultiPay/' . V7MP_VERSION . '; ' . home_url(),
			),
		);

		if ( 'GET' === $method ) {
			$url = $params ? $url . '?' . http_build_query( $params, '', '&' ) : $url;
		} else {
			$args['body'] = http_build_query( $params, '', '&' );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $body ) ) {
			return new WP_Error( 'v7mp_bad_response', __( 'Unexpected response from Stripe.', 'v7-multi-pay' ) );
		}

		if ( isset( $body['error'] ) ) {
			$message = isset( $body['error']['message'] ) ? $body['error']['message'] : __( 'Stripe request failed.', 'v7-multi-pay' );
			return new WP_Error( 'v7mp_stripe_error', $message, $body['error'] );
		}

		return $body;
	}

	/**
	 * Convert a decimal amount to Stripe's smallest currency unit.
	 *
	 * @param float  $amount   Amount.
	 * @param string $currency ISO currency code.
	 * @return int
	 */
	public static function to_minor_units( $amount, $currency ) {
		$currency = strtoupper( $currency );

		if ( in_array( $currency, self::ZERO_DECIMAL, true ) ) {
			return (int) round( (float) $amount );
		}

		if ( in_array( $currency, self::THREE_DECIMAL, true ) ) {
			// Stripe requires the last digit to be 0 for three-decimal currencies.
			return (int) ( round( (float) $amount * 100 ) * 10 );
		}

		return (int) round( (float) $amount * 100 );
	}

	/**
	 * Verify a webhook payload signature.
	 *
	 * @param string $payload   Raw request body.
	 * @param string $header    Stripe-Signature header.
	 * @param string $secret    Webhook signing secret.
	 * @param int    $tolerance Allowed clock skew in seconds.
	 * @return bool
	 */
	public static function verify_signature( $payload, $header, $secret, $tolerance = 300 ) {
		if ( '' === (string) $secret || '' === (string) $header ) {
			return false;
		}

		$timestamp  = 0;
		$signatures = array();

		foreach ( explode( ',', $header ) as $part ) {
			$pair = explode( '=', trim( $part ), 2 );
			if ( 2 !== count( $pair ) ) {
				continue;
			}
			if ( 't' === $pair[0] ) {
				$timestamp = (int) $pair[1];
			} elseif ( 'v1' === $pair[0] ) {
				$signatures[] = $pair[1];
			}
		}

		if ( ! $timestamp || ! $signatures || abs( time() - $timestamp ) > $tolerance ) {
			return false;
		}

		$expected = hash_hmac( 'sha256', $timestamp . '.' . $payload, $secret );

		foreach ( $signatures as $signature ) {
			if ( hash_equals( $expected, $signature ) ) {
				return true;
			}
		}

		return false;
	}
}

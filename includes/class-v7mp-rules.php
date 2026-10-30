<?php
/**
 * Decides which products, carts and orders belong to the secondary Stripe account.
 *
 * @package V7_Multi_Pay
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class V7MP_Rules
 */
class V7MP_Rules {

	const META_KEY   = '_v7mp_secondary';
	const OPTION_KEY = 'woocommerce_v7mp_stripe_settings';

	/**
	 * Per-request cache of product decisions.
	 *
	 * @var array<int,bool>
	 */
	private static $product_cache = array();

	/**
	 * Per-request cache of expanded category IDs.
	 *
	 * @var int[]|null
	 */
	private static $category_ids = null;

	/**
	 * Read a gateway setting without instantiating every gateway.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public static function setting( $key, $default = '' ) {
		$settings = get_option( self::OPTION_KEY, array() );
		return ( is_array( $settings ) && isset( $settings[ $key ] ) ) ? $settings[ $key ] : $default;
	}

	/**
	 * Whether routing is switched on and a key is configured for the current mode.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		if ( 'yes' !== self::setting( 'enabled', 'no' ) ) {
			return false;
		}
		$key = 'yes' === self::setting( 'testmode', 'no' ) ? 'test_secret_key' : 'live_secret_key';
		return '' !== (string) self::setting( $key );
	}

	/**
	 * Flush caches (called after settings are saved).
	 */
	public static function flush() {
		self::$product_cache = array();
		self::$category_ids  = null;
	}

	/**
	 * Selected categories plus all of their child categories.
	 *
	 * @return int[]
	 */
	private static function category_ids() {
		if ( null !== self::$category_ids ) {
			return self::$category_ids;
		}

		$ids = array_filter( array_map( 'absint', (array) self::setting( 'categories', array() ) ) );

		foreach ( $ids as $id ) {
			$children = get_term_children( $id, 'product_cat' );
			if ( ! is_wp_error( $children ) ) {
				$ids = array_merge( $ids, array_map( 'absint', $children ) );
			}
		}

		self::$category_ids = array_values( array_unique( $ids ) );
		return self::$category_ids;
	}

	/**
	 * Is this product assigned to the secondary account?
	 *
	 * @param WC_Product|int $product Product or product ID.
	 * @return bool
	 */
	public static function is_product_secondary( $product ) {
		$product = $product instanceof WC_Product ? $product : wc_get_product( $product );
		if ( ! $product ) {
			return false;
		}

		// Variations inherit the assignment of their parent product.
		$id = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();

		if ( isset( self::$product_cache[ $id ] ) ) {
			return self::$product_cache[ $id ];
		}

		$parent = $product->get_parent_id() ? wc_get_product( $id ) : $product;
		$result = $parent && 'yes' === $parent->get_meta( self::META_KEY );

		if ( ! $result ) {
			$categories = self::category_ids();
			$result     = $categories && has_term( $categories, 'product_cat', $id );
		}

		/**
		 * Filter whether a product is paid through the secondary Stripe account.
		 *
		 * @param bool       $result  Decision.
		 * @param WC_Product $product Product.
		 */
		self::$product_cache[ $id ] = (bool) apply_filters( 'v7mp_is_product_secondary', $result, $product );

		return self::$product_cache[ $id ];
	}

	/**
	 * Inspect a list of products.
	 *
	 * @param WC_Product[] $products Products.
	 * @return array{secondary:bool,default:bool}
	 */
	private static function inspect( array $products ) {
		$state = array(
			'secondary' => false,
			'default'   => false,
		);

		foreach ( $products as $product ) {
			$state[ self::is_product_secondary( $product ) ? 'secondary' : 'default' ] = true;
		}

		return $state;
	}

	/**
	 * Cart state.
	 *
	 * @return array{secondary:bool,default:bool}
	 */
	public static function cart_state() {
		$products = array();

		if ( function_exists( 'WC' ) && WC()->cart ) {
			foreach ( WC()->cart->get_cart() as $item ) {
				if ( isset( $item['data'] ) && $item['data'] instanceof WC_Product ) {
					$products[] = $item['data'];
				}
			}
		}

		return self::inspect( $products );
	}

	/**
	 * Order state.
	 *
	 * @param WC_Order $order Order.
	 * @return array{secondary:bool,default:bool}
	 */
	public static function order_state( $order ) {
		$products = array();

		foreach ( $order->get_items() as $item ) {
			$product = $item instanceof WC_Order_Item_Product ? $item->get_product() : null;
			if ( $product ) {
				$products[] = $product;
			}
		}

		return self::inspect( $products );
	}
}

<?php
/**
 * Product edit screen, bulk edit and list column for assigning products to the secondary account.
 *
 * @package V7_Multi_Pay
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class V7MP_Admin_Product
 */
class V7MP_Admin_Product {

	/**
	 * Hook up.
	 */
	public static function init() {
		add_action( 'woocommerce_product_options_general_product_data', array( __CLASS__, 'render_field' ) );
		add_action( 'woocommerce_admin_process_product_object', array( __CLASS__, 'save_field' ) );
		add_action( 'woocommerce_product_bulk_edit_end', array( __CLASS__, 'render_bulk_field' ) );
		add_action( 'woocommerce_product_bulk_edit_save', array( __CLASS__, 'save_bulk_field' ) );
		add_filter( 'manage_edit-product_columns', array( __CLASS__, 'add_column' ), 20 );
		add_action( 'manage_product_posts_custom_column', array( __CLASS__, 'render_column' ), 10, 2 );
		add_action( 'admin_head-edit.php', array( __CLASS__, 'column_style' ) );
	}

	/**
	 * Keep the icon column narrow on the products list.
	 */
	public static function column_style() {
		if ( 'product' === get_current_screen()->post_type ) {
			echo '<style>.wp-list-table .column-v7mp{width:2.5em;text-align:center}</style>';
		}
	}

	/**
	 * Checkbox in Product data → General.
	 */
	public static function render_field() {
		echo '<div class="options_group">';
		woocommerce_wp_checkbox(
			array(
				'id'          => V7MP_Rules::META_KEY,
				'label'       => __( 'Secondary Stripe', 'v7-multi-pay' ),
				'description' => __( 'Collect payment for this product on the secondary Stripe account (V7 Multi Pay).', 'v7-multi-pay' ),
			)
		);
		echo '</div>';
	}

	/**
	 * Save the checkbox. WooCommerce has already verified the nonce and capability.
	 *
	 * @param WC_Product $product Product.
	 */
	public static function save_field( $product ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$product->update_meta_data( V7MP_Rules::META_KEY, isset( $_POST[ V7MP_Rules::META_KEY ] ) ? 'yes' : 'no' );
	}

	/**
	 * Bulk edit dropdown.
	 */
	public static function render_bulk_field() {
		?>
		<div class="inline-edit-group">
			<label class="alignleft">
				<span class="title"><?php esc_html_e( 'Secondary Stripe', 'v7-multi-pay' ); ?></span>
				<span class="input-text-wrap">
					<select class="v7mp_secondary" name="v7mp_secondary_bulk">
						<option value=""><?php esc_html_e( '— No change —', 'v7-multi-pay' ); ?></option>
						<option value="yes"><?php esc_html_e( 'Yes', 'v7-multi-pay' ); ?></option>
						<option value="no"><?php esc_html_e( 'No', 'v7-multi-pay' ); ?></option>
					</select>
				</span>
			</label>
		</div>
		<?php
	}

	/**
	 * Save bulk edit. Runs inside WordPress' nonce-checked bulk edit request.
	 *
	 * @param WC_Product $product Product.
	 */
	public static function save_bulk_field( $product ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$value = isset( $_REQUEST['v7mp_secondary_bulk'] ) ? sanitize_key( wp_unslash( $_REQUEST['v7mp_secondary_bulk'] ) ) : '';

		if ( in_array( $value, array( 'yes', 'no' ), true ) && current_user_can( 'edit_product', $product->get_id() ) ) {
			$product->update_meta_data( V7MP_Rules::META_KEY, $value );
			$product->save();
		}
	}

	/**
	 * Products list column.
	 *
	 * @param string[] $columns Columns.
	 * @return string[]
	 */
	public static function add_column( $columns ) {
		$columns['v7mp'] = '<span class="dashicons dashicons-randomize" title="' . esc_attr__( 'Secondary Stripe', 'v7-multi-pay' ) . '"></span><span class="screen-reader-text">' . esc_html__( 'Secondary Stripe', 'v7-multi-pay' ) . '</span>';
		return $columns;
	}

	/**
	 * Column value.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Product ID.
	 */
	public static function render_column( $column, $post_id ) {
		if ( 'v7mp' === $column && V7MP_Rules::is_product_secondary( $post_id ) ) {
			echo '<span class="dashicons dashicons-yes" title="' . esc_attr__( 'Paid via secondary Stripe', 'v7-multi-pay' ) . '"></span>';
		}
	}
}

V7MP_Admin_Product::init();

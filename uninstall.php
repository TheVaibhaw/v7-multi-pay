<?php
/**
 * Remove plugin data on uninstall (every site on multisite).
 *
 * @package V7_Multi_Pay
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Delete data for the current site.
 */
function v7mp_uninstall_site() {
	delete_option( 'woocommerce_v7mp_stripe_settings' );
	delete_post_meta_by_key( '_v7mp_secondary' );
}

if ( is_multisite() ) {
	$v7mp_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $v7mp_site_ids as $v7mp_site_id ) {
		switch_to_blog( $v7mp_site_id );
		v7mp_uninstall_site();
		restore_current_blog();
	}
} else {
	v7mp_uninstall_site();
}

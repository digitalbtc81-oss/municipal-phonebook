<?php
/**
 * Uninstall cleanup.
 *
 * Always removes plugin options and cached data. Contacts and departments are removed
 * only if "Delete all phonebook data" was enabled in the plugin settings.
 *
 * @package MunicipalPhonebook
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Remove data for the current site.
 *
 * @return void
 */
function munipb_uninstall_site() {
	global $wpdb;

	$settings = get_option( 'munipb_settings', array() );

	if ( is_array( $settings ) && ! empty( $settings['delete_on_remove'] ) ) {
		$ids = get_posts(
			array(
				'post_type'      => 'munipb_entry',
				'post_status'    => array_keys( get_post_stati() ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);
		foreach ( $ids as $id ) {
			wp_delete_post( $id, true );
		}

		// The taxonomy is not registered during uninstall, so read term ids directly.
		$term_ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( "SELECT term_id FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s", 'munipb_department' )
		);
		foreach ( $term_ids as $term_id ) {
			$wpdb->delete( $wpdb->termmeta, array( 'term_id' => (int) $term_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete( $wpdb->term_taxonomy, array( 'term_id' => (int) $term_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete( $wpdb->terms, array( 'term_id' => (int) $term_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
	}

	delete_option( 'munipb_settings' );
	delete_option( 'munipb_cache_version' );
	delete_option( 'munipb_version' );
	delete_option( 'munipb_department_children' ); // Core hierarchy cache for the taxonomy.

	// Remove cached directory and rate-limit transients.
	$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			$wpdb->esc_like( '_transient_munipb_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_munipb_' ) . '%'
		)
	);
}

if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids' ) ) as $munipb_site_id ) {
		switch_to_blog( $munipb_site_id );
		munipb_uninstall_site();
		restore_current_blog();
	}
} else {
	munipb_uninstall_site();
}

delete_site_transient( 'munipb_github_release' );

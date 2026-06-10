<?php
/**
 * NexiSettings uninstall cleanup.
 *
 * @package NexiSettings
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'nexisettings_options' );
delete_option( 'nexisettings_redirects' );

if ( is_multisite() ) {
	$nexisettings_site_ids = get_sites(
		array(
			'fields' => 'ids',
		)
	);

	foreach ( $nexisettings_site_ids as $nexisettings_site_id ) {
		switch_to_blog( $nexisettings_site_id );
		delete_option( 'nexisettings_options' );
		delete_option( 'nexisettings_redirects' );
		restore_current_blog();
	}
}

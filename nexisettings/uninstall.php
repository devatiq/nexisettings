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
	$site_ids = get_sites(
		array(
			'fields' => 'ids',
		)
	);

	foreach ( $site_ids as $site_id ) {
		switch_to_blog( $site_id );
		delete_option( 'nexisettings_options' );
		delete_option( 'nexisettings_redirects' );
		restore_current_blog();
	}
}

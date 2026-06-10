<?php
/**
 * Security toggles.
 *
 * @package NexiSettings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Applies basic hardening options.
 */
class NexiSettings_Security {
	/**
	 * Plugin options.
	 *
	 * @var array
	 */
	private $options;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->options = NexiSettings::get_options();

		if ( ! empty( $this->options['disable_xmlrpc'] ) ) {
			add_filter( 'xmlrpc_enabled', '__return_false' );
		}

		if ( ! empty( $this->options['disable_user_enumeration'] ) ) {
			add_action( 'template_redirect', array( $this, 'block_user_enumeration' ), 0 );
		}

		if ( ! empty( $this->options['hide_wp_version'] ) ) {
			remove_action( 'wp_head', 'wp_generator' );
			add_filter( 'the_generator', '__return_empty_string' );
		}
	}

	/**
	 * Block common author archive enumeration requests.
	 *
	 * @return void
	 */
	public function block_user_enumeration() {
		if ( NexiSettings::is_protected_request_context() || is_user_logged_in() ) {
			return;
		}

		if ( ! isset( $_GET['author'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$author = sanitize_text_field( wp_unslash( $_GET['author'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! is_numeric( $author ) || absint( $author ) < 1 ) {
			return;
		}

		status_header( 404 );
		nocache_headers();
		wp_die(
			esc_html__( 'Not Found', 'nexisettings' ),
			esc_html__( 'Not Found', 'nexisettings' ),
			array( 'response' => 404 )
		);
	}
}

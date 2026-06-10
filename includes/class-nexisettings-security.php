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
			add_action( 'init', array( $this, 'block_xmlrpc_request' ), 0 );
			add_filter( 'xmlrpc_enabled', '__return_false' );
			add_filter( 'wp_headers', array( $this, 'remove_x_pingback_header' ) );
		}

		if ( ! empty( $this->options['disable_user_enumeration'] ) ) {
			$this->block_author_query_enumeration();
			add_action( 'parse_request', array( $this, 'block_author_query_enumeration' ), 0 );
			add_action( 'template_redirect', array( $this, 'block_user_enumeration' ), 0 );
			add_filter( 'rest_authentication_errors', array( $this, 'block_rest_user_enumeration' ) );
			add_filter( 'rest_endpoints', array( $this, 'remove_rest_user_endpoints' ) );
		}

		if ( ! empty( $this->options['hide_wp_version'] ) ) {
			remove_action( 'wp_head', 'wp_generator' );
			add_filter( 'the_generator', '__return_empty_string' );
			add_filter( 'get_the_generator_html', '__return_empty_string' );
			add_filter( 'get_the_generator_xhtml', '__return_empty_string' );
			add_filter( 'get_the_generator_atom', '__return_empty_string' );
			add_filter( 'get_the_generator_rss2', '__return_empty_string' );
			add_filter( 'get_the_generator_rdf', '__return_empty_string' );
			add_filter( 'get_the_generator_comment', '__return_empty_string' );
			add_filter( 'script_loader_src', array( $this, 'remove_wp_version_from_asset_url' ), 999 );
			add_filter( 'style_loader_src', array( $this, 'remove_wp_version_from_asset_url' ), 999 );
		}
	}

	/**
	 * Block XML-RPC requests outright when disabled.
	 *
	 * @return void
	 */
	public function block_xmlrpc_request() {
		if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
			status_header( 403 );
			nocache_headers();
			wp_die(
				esc_html__( 'XML-RPC is disabled.', 'nexisettings' ),
				esc_html__( 'Forbidden', 'nexisettings' ),
				array( 'response' => 403 )
			);
		}
	}

	/**
	 * Remove the X-Pingback response header.
	 *
	 * @param array $headers Response headers.
	 * @return array
	 */
	public function remove_x_pingback_header( $headers ) {
		if ( isset( $headers['X-Pingback'] ) ) {
			unset( $headers['X-Pingback'] );
		}

		return $headers;
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

		if ( is_author() ) {
			$this->send_not_found();
		}

		$this->block_author_query_enumeration();
	}

	/**
	 * Block numeric ?author=ID requests before WordPress redirects them.
	 *
	 * @return void
	 */
	public function block_author_query_enumeration() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || is_user_logged_in() ) {
			return;
		}

		if ( ! isset( $_GET['author'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$author = sanitize_text_field( wp_unslash( $_GET['author'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! is_numeric( $author ) || absint( $author ) < 1 ) {
			return;
		}

		$this->send_not_found();
	}

	/**
	 * Block public REST API user enumeration endpoints for logged-out visitors.
	 *
	 * @param WP_Error|null|bool $result Existing authentication result.
	 * @return WP_Error|null|bool
	 */
	public function block_rest_user_enumeration( $result ) {
		if ( ! empty( $result ) || is_user_logged_in() ) {
			return $result;
		}

		$route = isset( $GLOBALS['wp']->query_vars['rest_route'] ) ? $GLOBALS['wp']->query_vars['rest_route'] : '';

		if ( empty( $route ) && isset( $_SERVER['REQUEST_URI'] ) ) {
			$request_uri = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );
			$path        = wp_parse_url( $request_uri, PHP_URL_PATH );
			$rest_prefix = trailingslashit( rest_get_url_prefix() );

			if ( is_string( $path ) && false !== strpos( $path, '/' . $rest_prefix ) ) {
				$route = substr( $path, strpos( $path, '/' . $rest_prefix ) + strlen( '/' . rest_get_url_prefix() ) );
			}
		}

		$route = '/' . ltrim( (string) $route, '/' );

		if ( preg_match( '#^/wp/v2/users(?:/|$)#', $route ) ) {
			return new WP_Error(
				'nexisettings_user_enumeration_disabled',
				esc_html__( 'User enumeration is disabled.', 'nexisettings' ),
				array( 'status' => 404 )
			);
		}

		return $result;
	}

	/**
	 * Remove public REST user endpoints for logged-out visitors.
	 *
	 * @param array $endpoints REST endpoints.
	 * @return array
	 */
	public function remove_rest_user_endpoints( $endpoints ) {
		if ( is_user_logged_in() ) {
			return $endpoints;
		}

		foreach ( array_keys( $endpoints ) as $route ) {
			if ( preg_match( '#^/wp/v2/users(?:/|$)#', $route ) ) {
				unset( $endpoints[ $route ] );
			}
		}

		return $endpoints;
	}

	/**
	 * Remove WordPress version query strings from core asset URLs.
	 *
	 * @param string $src Asset URL.
	 * @return string
	 */
	public function remove_wp_version_from_asset_url( $src ) {
		if ( empty( $src ) ) {
			return $src;
		}

		$wp_version = get_bloginfo( 'version' );
		$query_ver  = wp_parse_url( $src, PHP_URL_QUERY );

		if ( empty( $query_ver ) || false === strpos( $query_ver, 'ver=' ) ) {
			return $src;
		}

		parse_str( $query_ver, $query_args );

		if ( isset( $query_args['ver'] ) && $query_args['ver'] === $wp_version ) {
			return remove_query_arg( 'ver', $src );
		}

		return $src;
	}

	/**
	 * Send a 404 response.
	 *
	 * @return void
	 */
	private function send_not_found() {
		status_header( 404 );
		nocache_headers();
		wp_die(
			esc_html__( 'Not Found', 'nexisettings' ),
			esc_html__( 'Not Found', 'nexisettings' ),
			array( 'response' => 404 )
		);
	}
}

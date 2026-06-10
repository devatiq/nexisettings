<?php
/**
 * Frontend redirect manager.
 *
 * @package NexiSettings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles stored redirects on frontend requests.
 */
class NexiSettings_Redirects {
	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'template_redirect', array( $this, 'maybe_redirect' ), 1 );
	}

	/**
	 * Run matching redirects on frontend requests only.
	 *
	 * @return void
	 */
	public function maybe_redirect() {
		if ( NexiSettings::is_protected_request_context() ) {
			return;
		}

		$redirects = self::get_redirects();
		if ( empty( $redirects ) ) {
			return;
		}

		$current = $this->get_current_request_parts();

		foreach ( $redirects as $redirect ) {
			if ( empty( $redirect['enabled'] ) || empty( $redirect['source'] ) || empty( $redirect['destination'] ) ) {
				continue;
			}

			if ( ! $this->source_matches_current_request( $redirect['source'], $current ) ) {
				continue;
			}

			if ( $this->would_loop( $redirect['destination'], $current ) ) {
				continue;
			}

			$status = isset( $redirect['type'] ) && 302 === absint( $redirect['type'] ) ? 302 : 301;
			wp_redirect( esc_url_raw( $redirect['destination'] ), $status ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
			exit;
		}
	}

	/**
	 * Get sanitized redirects from the database.
	 *
	 * @return array
	 */
	public static function get_redirects() {
		$redirects = get_option( NEXISETTINGS_REDIRECTS_OPTION, array() );

		if ( ! is_array( $redirects ) ) {
			return array();
		}

		return self::sanitize_redirect_rows( $redirects );
	}

	/**
	 * Sanitize redirect rows from submitted or stored data.
	 *
	 * @param array $rows Redirect rows.
	 * @return array
	 */
	public static function sanitize_redirect_rows( $rows ) {
		$clean = array();

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			if ( ! empty( $row['delete'] ) ) {
				continue;
			}

			$source      = isset( $row['source'] ) ? self::sanitize_redirect_url( $row['source'] ) : '';
			$destination = isset( $row['destination'] ) ? self::sanitize_redirect_url( $row['destination'] ) : '';

			if ( '' === $source || '' === $destination ) {
				continue;
			}

			if ( self::normalize_url_for_comparison( $source ) === self::normalize_url_for_comparison( $destination ) ) {
				continue;
			}

			$type = isset( $row['type'] ) && 302 === absint( $row['type'] ) ? 302 : 301;

			$clean[] = array(
				'id'          => isset( $row['id'] ) ? sanitize_key( $row['id'] ) : wp_generate_uuid4(),
				'source'      => $source,
				'destination' => $destination,
				'type'        => $type,
				'enabled'     => empty( $row['enabled'] ) ? 0 : 1,
			);
		}

		return $clean;
	}

	/**
	 * Sanitize a redirect URL.
	 *
	 * Accepts root-relative URLs and absolute HTTP(S) URLs.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	public static function sanitize_redirect_url( $url ) {
		if ( ! is_scalar( $url ) ) {
			return '';
		}

		$url = trim( sanitize_text_field( wp_unslash( $url ) ) );

		if ( '' === $url ) {
			return '';
		}

		if ( 0 === strpos( $url, '//' ) ) {
			return '';
		}

		if ( 0 === strpos( $url, '/' ) ) {
			return esc_url_raw( $url );
		}

		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return '';
		}

		if ( ! in_array( strtolower( $parts['scheme'] ), array( 'http', 'https' ), true ) ) {
			return '';
		}

		return esc_url_raw( $url );
	}

	/**
	 * Get current request URL parts.
	 *
	 * @return array
	 */
	private function get_current_request_parts() {
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
		$host        = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : wp_parse_url( home_url(), PHP_URL_HOST );
		$scheme      = is_ssl() ? 'https' : 'http';
		$full        = $scheme . '://' . $host . $request_uri;

		return array(
			'full'     => self::normalize_url_for_comparison( $full ),
			'relative' => self::normalize_url_for_comparison( $request_uri ),
		);
	}

	/**
	 * Determine if a redirect source matches the current request.
	 *
	 * @param string $source  Redirect source.
	 * @param array  $current Current request parts.
	 * @return bool
	 */
	private function source_matches_current_request( $source, $current ) {
		$normalized_source = self::normalize_url_for_comparison( $source );

		if ( 0 === strpos( $source, '/' ) ) {
			return $normalized_source === $current['relative'];
		}

		return $normalized_source === $current['full'];
	}

	/**
	 * Check whether destination points back to the current request.
	 *
	 * @param string $destination Redirect destination.
	 * @param array  $current     Current request parts.
	 * @return bool
	 */
	private function would_loop( $destination, $current ) {
		$normalized_destination = self::normalize_url_for_comparison( $destination );

		return $normalized_destination === $current['relative'] || $normalized_destination === $current['full'];
	}

	/**
	 * Normalize URLs for comparison.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	public static function normalize_url_for_comparison( $url ) {
		$url = trim( (string) $url );

		if ( '' === $url ) {
			return '';
		}

		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) ) {
			return strtolower( untrailingslashit( $url ) );
		}

		$path  = isset( $parts['path'] ) ? '/' . ltrim( $parts['path'], '/' ) : '/';
		$query = isset( $parts['query'] ) && '' !== $parts['query'] ? '?' . $parts['query'] : '';
		$path  = '/' === $path ? '/' : untrailingslashit( $path );

		if ( isset( $parts['scheme'], $parts['host'] ) ) {
			$port = isset( $parts['port'] ) ? ':' . absint( $parts['port'] ) : '';
			return strtolower( $parts['scheme'] . '://' . $parts['host'] . $port . $path . $query );
		}

		return strtolower( $path . $query );
	}
}

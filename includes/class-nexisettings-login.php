<?php
/**
 * Custom login URL and login branding.
 *
 * @package NexiSettings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles custom login routing, wp-login.php protection, and login screen branding.
 */
class NexiSettings_Login {
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

		add_action( 'init', array( $this, 'add_rewrite_rule' ) );
		add_action( 'parse_request', array( $this, 'maybe_load_custom_login' ), 0 );
		add_action( 'login_init', array( $this, 'maybe_block_wp_login' ), 0 );
		add_filter( 'login_url', array( $this, 'filter_login_url' ), 10, 3 );
		add_filter( 'site_url', array( $this, 'filter_site_url' ), 10, 4 );

		add_action( 'login_enqueue_scripts', array( $this, 'output_login_branding_css' ) );
		add_filter( 'login_headerurl', array( $this, 'filter_login_logo_url' ) );
		add_filter( 'login_headertext', array( $this, 'filter_login_logo_title' ) );
		add_filter( 'login_message', array( $this, 'add_login_message' ) );
	}

	/**
	 * Add a rewrite rule for the custom login slug.
	 *
	 * @return void
	 */
	public function add_rewrite_rule() {
		if ( ! $this->is_custom_login_enabled() ) {
			return;
		}

		add_rewrite_rule(
			'^' . preg_quote( $this->get_custom_login_slug(), '/' ) . '/?$',
			'index.php?nexisettings_login=1',
			'top'
		);
	}

	/**
	 * Load wp-login.php when the custom slug is requested.
	 *
	 * This direct path check keeps newly saved login slugs reachable even before
	 * rewrite rules have been flushed by the current request lifecycle.
	 *
	 * @return void
	 */
	public function maybe_load_custom_login() {
		if ( ! $this->is_custom_login_enabled() || NexiSettings::is_protected_request_context() ) {
			return;
		}

		$request_path = $this->get_request_path();
		if ( $request_path !== $this->get_custom_login_slug() ) {
			return;
		}

		if ( ! defined( 'NEXISETTINGS_DOING_CUSTOM_LOGIN' ) ) {
			define( 'NEXISETTINGS_DOING_CUSTOM_LOGIN', true );
		}

		require ABSPATH . 'wp-login.php';
		exit;
	}

	/**
	 * Block direct wp-login.php access for non-logged-in users.
	 *
	 * @return void
	 */
	public function maybe_block_wp_login() {
		if ( ! $this->is_custom_login_enabled() || defined( 'NEXISETTINGS_DOING_CUSTOM_LOGIN' ) ) {
			return;
		}

		if ( is_user_logged_in() ) {
			return;
		}

		$script_name = isset( $_SERVER['SCRIPT_NAME'] ) ? wp_unslash( $_SERVER['SCRIPT_NAME'] ) : '';
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';

		if ( false === strpos( $script_name, 'wp-login.php' ) && false === strpos( $request_uri, 'wp-login.php' ) ) {
			return;
		}

		$this->handle_blocked_login_request();
	}

	/**
	 * Replace generated login URLs with the custom login URL.
	 *
	 * @param string $login_url    Login URL.
	 * @param string $redirect     Redirect target.
	 * @param bool   $force_reauth Whether to force re-authentication.
	 * @return string
	 */
	public function filter_login_url( $login_url, $redirect, $force_reauth ) {
		if ( ! $this->is_custom_login_enabled() ) {
			return $login_url;
		}

		$url = $this->get_custom_login_url();

		if ( ! empty( $redirect ) ) {
			$url = add_query_arg( 'redirect_to', $redirect, $url );
		}

		if ( $force_reauth ) {
			$url = add_query_arg( 'reauth', '1', $url );
		}

		return $url;
	}

	/**
	 * Replace wp-login.php URLs generated through site_url().
	 *
	 * @param string      $url     Complete URL.
	 * @param string      $path    Requested path.
	 * @param string|null $scheme  URL scheme.
	 * @param int|null    $blog_id Blog ID.
	 * @return string
	 */
	public function filter_site_url( $url, $path, $scheme, $blog_id ) {
		unset( $scheme, $blog_id );

		if ( ! $this->is_custom_login_enabled() || empty( $path ) ) {
			return $url;
		}

		$path = ltrim( (string) $path, '/' );
		if ( 0 !== strpos( $path, 'wp-login.php' ) ) {
			return $url;
		}

		$query = wp_parse_url( $path, PHP_URL_QUERY );
		$url   = $this->get_custom_login_url();

		if ( ! empty( $query ) ) {
			parse_str( $query, $query_args );
			if ( is_array( $query_args ) && ! empty( $query_args ) ) {
				$url = add_query_arg( $query_args, $url );
			}
		}

		return $url;
	}

	/**
	 * Output custom login logo styles.
	 *
	 * @return void
	 */
	public function output_login_branding_css() {
		$logo_url = $this->get_login_logo_image_url();

		if ( empty( $logo_url ) ) {
			return;
		}

		?>
		<style type="text/css">
			.login h1 a {
				background-image: url('<?php echo esc_url( $logo_url ); ?>');
				background-size: contain;
				background-position: center center;
				width: 100%;
				max-width: 320px;
				height: 100px;
			}
		</style>
		<?php
	}

	/**
	 * Filter the login logo URL.
	 *
	 * @param string $url Login header URL.
	 * @return string
	 */
	public function filter_login_logo_url( $url ) {
		if ( empty( $this->options['login_logo_url'] ) ) {
			return $url;
		}

		return esc_url_raw( $this->options['login_logo_url'] );
	}

	/**
	 * Filter login logo title text.
	 *
	 * @param string $title Login header title.
	 * @return string
	 */
	public function filter_login_logo_title( $title ) {
		if ( empty( $this->options['login_logo_url'] ) ) {
			return $title;
		}

		return get_bloginfo( 'name' );
	}

	/**
	 * Add custom text below the login logo.
	 *
	 * @param string $message Existing login message.
	 * @return string
	 */
	public function add_login_message( $message ) {
		if ( empty( $this->options['login_logo_text'] ) ) {
			return $message;
		}

		$custom_message = '<div class="nexisettings-login-message">' . wp_kses_post( wpautop( $this->options['login_logo_text'] ) ) . '</div>';

		return $custom_message . $message;
	}

	/**
	 * Is custom login currently active.
	 *
	 * @return bool
	 */
	private function is_custom_login_enabled() {
		if ( NexiSettings::is_custom_login_disabled() ) {
			return false;
		}

		return ! empty( $this->options['enable_custom_login'] ) && '' !== $this->get_custom_login_slug();
	}

	/**
	 * Get sanitized custom login slug.
	 *
	 * @return string
	 */
	private function get_custom_login_slug() {
		return trim( sanitize_title( $this->options['custom_login_slug'] ), '/' );
	}

	/**
	 * Get the public custom login URL.
	 *
	 * @return string
	 */
	private function get_custom_login_url() {
		return home_url( '/' . $this->get_custom_login_slug() . '/' );
	}

	/**
	 * Handle a blocked wp-login.php request.
	 *
	 * @return void
	 */
	private function handle_blocked_login_request() {
		$action = isset( $this->options['login_block_action'] ) ? $this->options['login_block_action'] : '404';

		if ( 'home' === $action ) {
			wp_safe_redirect( home_url( '/' ), 302 );
			exit;
		}

		if ( 'custom' === $action ) {
			wp_safe_redirect( $this->get_custom_login_url(), 302 );
			exit;
		}

		status_header( 404 );
		nocache_headers();
		wp_die(
			esc_html__( 'Not Found', 'nexisettings' ),
			esc_html__( 'Not Found', 'nexisettings' ),
			array( 'response' => 404 )
		);
	}

	/**
	 * Get the normalized request path.
	 *
	 * @return string
	 */
	private function get_request_path() {
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$path        = wp_parse_url( $request_uri, PHP_URL_PATH );

		if ( empty( $path ) ) {
			return '';
		}

		$home_path = wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		$home_path = is_string( $home_path ) ? trim( $home_path, '/' ) : '';
		$path      = trim( $path, '/' );

		if ( '' !== $home_path && 0 === strpos( $path, $home_path . '/' ) ) {
			$path = substr( $path, strlen( $home_path ) + 1 );
		}

		return trim( $path, '/' );
	}

	/**
	 * Get login logo image URL from attachment ID.
	 *
	 * @return string
	 */
	private function get_login_logo_image_url() {
		$logo_id = absint( $this->options['login_logo_id'] );

		if ( ! $logo_id ) {
			return '';
		}

		$image = wp_get_attachment_image_src( $logo_id, 'full' );

		if ( ! is_array( $image ) || empty( $image[0] ) ) {
			return '';
		}

		return esc_url_raw( $image[0] );
	}
}

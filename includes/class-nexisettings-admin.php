<?php
/**
 * Admin settings UI.
 *
 * @package NexiSettings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds and saves NexiSettings admin screens.
 */
class NexiSettings_Admin {
	/**
	 * Admin page slug.
	 *
	 * @var string
	 */
	const MENU_SLUG = 'nexisettings';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_nexisettings_save_redirects', array( $this, 'save_redirects' ) );
		add_action( 'wp_ajax_nexisettings_save_options', array( $this, 'ajax_save_options' ) );
		add_action( 'wp_ajax_nexisettings_save_redirects', array( $this, 'ajax_save_redirects' ) );
		add_filter( 'plugin_action_links_' . NEXISETTINGS_BASENAME, array( $this, 'add_settings_link' ) );
	}

	/**
	 * Add top-level menu page.
	 *
	 * @return void
	 */
	public function add_menu_page() {
		add_menu_page(
			esc_html__( 'NexiSettings', 'nexisettings' ),
			esc_html__( 'NexiSettings', 'nexisettings' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_page' ),
			'dashicons-shield-alt',
			58
		);
	}

	/**
	 * Register primary plugin setting.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'nexisettings_options_group',
			NEXISETTINGS_OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_options' ),
				'default'           => NexiSettings::get_default_options(),
			)
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Page hook.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		if ( 'toplevel_page_' . self::MENU_SLUG !== $hook ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_style(
			'nexisettings-admin',
			NEXISETTINGS_URL . 'assets/css/admin.css',
			array(),
			NEXISETTINGS_VERSION
		);
		wp_enqueue_script(
			'nexisettings-admin',
			NEXISETTINGS_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			NEXISETTINGS_VERSION,
			true
		);
		wp_localize_script(
			'nexisettings-admin',
			'nexiSettingsAdmin',
			array(
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( 'nexisettings_ajax_save' ),
				'chooseLogo'  => esc_html__( 'Choose login logo', 'nexisettings' ),
				'useLogo'     => esc_html__( 'Use this logo', 'nexisettings' ),
				'noLogo'      => esc_html__( 'No logo selected', 'nexisettings' ),
				'saving'      => esc_html__( 'Saving...', 'nexisettings' ),
				'saveFailed'  => esc_html__( 'Settings could not be saved. Please refresh and try again.', 'nexisettings' ),
				'ajaxError'   => esc_html__( 'A network error prevented saving. Please try again.', 'nexisettings' ),
			)
		);
	}

	/**
	 * Add settings link on Plugins screen.
	 *
	 * @param array $links Plugin action links.
	 * @return array
	 */
	public function add_settings_link( $links ) {
		$settings_link = sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url( admin_url( 'admin.php?page=' . self::MENU_SLUG ) ),
			esc_html__( 'Settings', 'nexisettings' )
		);

		array_unshift( $links, $settings_link );

		return $links;
	}

	/**
	 * Sanitize primary options.
	 *
	 * @param array $input Submitted settings.
	 * @return array
	 */
	public function sanitize_options( $input ) {
		$input    = is_array( $input ) ? wp_unslash( $input ) : array();
		$existing = NexiSettings::get_options();
		$output   = $existing;
		$tab      = isset( $input['active_tab'] ) ? sanitize_key( $input['active_tab'] ) : '';

		switch ( $tab ) {
			case 'login-security':
				$output = $this->sanitize_login_security_options( $input, $existing, $output );
				break;
			case 'login-branding':
				$output = $this->sanitize_login_branding_options( $input, $output );
				break;
			case 'security':
				$output['disable_xmlrpc']           = empty( $input['disable_xmlrpc'] ) ? 0 : 1;
				$output['disable_user_enumeration'] = empty( $input['disable_user_enumeration'] ) ? 0 : 1;
				$output['hide_wp_version']          = empty( $input['hide_wp_version'] ) ? 0 : 1;
				break;
			case 'performance':
				$output['disable_emojis'] = empty( $input['disable_emojis'] ) ? 0 : 1;
				$output['disable_embeds'] = empty( $input['disable_embeds'] ) ? 0 : 1;
				break;
			case 'admin-branding':
				$output['enable_admin_footer_text'] = empty( $input['enable_admin_footer_text'] ) ? 0 : 1;
				$output['custom_admin_footer_text'] = isset( $input['custom_admin_footer_text'] ) ? wp_kses_post( $input['custom_admin_footer_text'] ) : '';
				break;
		}

		return wp_parse_args( $output, NexiSettings::get_default_options() );
	}

	/**
	 * Save primary plugin options through AJAX.
	 *
	 * @return void
	 */
	public function ajax_save_options() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array(
					'notices' => $this->render_notice_html( esc_html__( 'You do not have permission to manage NexiSettings.', 'nexisettings' ), 'error' ),
				),
				403
			);
		}

		check_ajax_referer( 'nexisettings_ajax_save', 'nonce' );

		$input = isset( $_POST[ NEXISETTINGS_OPTION ] ) && is_array( $_POST[ NEXISETTINGS_OPTION ] ) ? $_POST[ NEXISETTINGS_OPTION ] : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$tab   = isset( $input['active_tab'] ) ? sanitize_key( wp_unslash( $input['active_tab'] ) ) : '';

		if ( '' === $tab ) {
			wp_send_json_error(
				array(
					'notices' => $this->render_notice_html( esc_html__( 'Settings could not be saved because the active tab was missing.', 'nexisettings' ), 'error' ),
				),
				400
			);
		}

		$this->clear_settings_errors();
		$options = $this->sanitize_options( $input );
		update_option( NEXISETTINGS_OPTION, $options );

		$errors     = get_settings_errors( NEXISETTINGS_OPTION );
		$has_errors = $this->settings_errors_have_errors( $errors );
		$notices    = $this->render_settings_errors_html( $errors );

		if ( ! $has_errors ) {
			$notices .= $this->render_notice_html( $this->get_success_message_for_tab( $tab, $options ), 'success' );
		}

		wp_send_json_success(
			array(
				'notices'          => $notices,
				'options'          => $options,
				'currentLoginHtml' => $this->get_current_login_notice_html( $options ),
				'logoUrl'          => $this->get_logo_preview_url( $options ),
			)
		);
	}

	/**
	 * Save redirects from the custom redirects form.
	 *
	 * @return void
	 */
	public function save_redirects() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage NexiSettings.', 'nexisettings' ) );
		}

		check_admin_referer( 'nexisettings_save_redirects' );

		$result = $this->process_redirect_save();

		$this->set_admin_notice( $result['message'], $result['type'] );

		wp_safe_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'redirects' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Save redirects through AJAX.
	 *
	 * @return void
	 */
	public function ajax_save_redirects() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array(
					'notices' => $this->render_notice_html( esc_html__( 'You do not have permission to manage NexiSettings.', 'nexisettings' ), 'error' ),
				),
				403
			);
		}

		check_ajax_referer( 'nexisettings_ajax_save', 'nonce' );

		$result = $this->process_redirect_save();

		wp_send_json_success(
			array(
				'notices' => $this->render_notice_html( $result['message'], $result['type'] ),
			)
		);
	}

	/**
	 * Process submitted redirect rows.
	 *
	 * @return array
	 */
	private function process_redirect_save() {
		$rows      = isset( $_POST['nexisettings_redirects'] ) && is_array( $_POST['nexisettings_redirects'] ) ? wp_unslash( $_POST['nexisettings_redirects'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$redirects = NexiSettings_Redirects::sanitize_redirect_rows( $rows );
		$submitted = $this->count_submitted_redirect_rows( $rows );
		$skipped   = max( 0, $submitted - count( $redirects ) );

		update_option( NEXISETTINGS_REDIRECTS_OPTION, $redirects );

		if ( $skipped > 0 ) {
			return array(
				'message' => sprintf(
					/* translators: 1: Number of redirects saved. 2: Number of redirects skipped. */
					__( '%1$d redirect(s) saved. %2$d invalid row(s) skipped.', 'nexisettings' ),
					count( $redirects ),
					$skipped
				),
				'type'    => 'warning',
			);
		}

		return array(
			'message' => sprintf(
				/* translators: %d: Number of redirects saved. */
				_n( '%d redirect saved.', '%d redirects saved.', count( $redirects ), 'nexisettings' ),
				count( $redirects )
			),
			'type'    => 'success',
		);
	}

	/**
	 * Render settings page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$options    = NexiSettings::get_options();
		$redirects  = NexiSettings_Redirects::get_redirects();
		$active_tab = $this->get_active_tab();

		?>
		<div class="wrap nexisettings-wrap">
			<div class="nexisettings-hero">
				<div>
					<p class="nexisettings-eyebrow"><?php esc_html_e( 'Nexiby toolkit', 'nexisettings' ); ?></p>
					<h1><?php esc_html_e( 'NexiSettings', 'nexisettings' ); ?></h1>
					<p><?php esc_html_e( 'Secure and customize WordPress login, redirects, performance, and admin branding from one clean dashboard.', 'nexisettings' ); ?></p>
				</div>
				<div class="nexisettings-version">
					<?php
					printf(
						/* translators: %s: Plugin version. */
						esc_html__( 'Version %s', 'nexisettings' ),
						esc_html( NEXISETTINGS_VERSION )
					);
					?>
				</div>
			</div>

			<div class="nexisettings-notices" aria-live="polite">
				<?php settings_errors( NEXISETTINGS_OPTION ); ?>
				<?php $this->display_admin_notice(); ?>
			</div>

			<nav class="nexisettings-tabs" aria-label="<?php esc_attr_e( 'NexiSettings sections', 'nexisettings' ); ?>">
				<?php foreach ( $this->get_tabs() as $tab_id => $label ) : ?>
					<a class="<?php echo esc_attr( $active_tab === $tab_id ? 'is-active' : '' ); ?>" href="<?php echo esc_url( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => $tab_id ), admin_url( 'admin.php' ) ) ); ?>">
						<?php echo esc_html( $label ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<div class="nexisettings-panel">
				<?php
				switch ( $active_tab ) {
					case 'login-branding':
						$this->render_login_branding_tab( $options );
						break;
					case 'redirects':
						$this->render_redirects_tab( $redirects );
						break;
					case 'security':
						$this->render_security_tab( $options );
						break;
					case 'performance':
						$this->render_performance_tab( $options );
						break;
					case 'admin-branding':
						$this->render_admin_branding_tab( $options );
						break;
					case 'login-security':
					default:
						$this->render_login_security_tab( $options );
						break;
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Sanitize login security fields.
	 *
	 * @param array $input    Submitted settings.
	 * @param array $existing Existing settings.
	 * @param array $output   Output settings.
	 * @return array
	 */
	private function sanitize_login_security_options( $input, $existing, $output ) {
		$was_enabled = ! empty( $existing['enable_custom_login'] );
		$old_slug    = isset( $existing['custom_login_slug'] ) ? $existing['custom_login_slug'] : '';

		$output['enable_custom_login']     = empty( $input['enable_custom_login'] ) ? 0 : 1;
		$output['login_block_action']      = isset( $input['login_block_action'] ) && in_array( $input['login_block_action'], array( '404', 'home', 'custom_url' ), true ) ? $input['login_block_action'] : '404';
		$output['login_block_custom_url']  = isset( $input['login_block_custom_url'] ) ? $this->sanitize_local_page_url( $input['login_block_custom_url'] ) : '';

		if ( 'custom_url' === $output['login_block_action'] && '' === $output['login_block_custom_url'] ) {
			$output['login_block_action'] = '404';
			add_settings_error( NEXISETTINGS_OPTION, 'nexisettings-invalid-block-url', esc_html__( 'Enter a valid same-site custom page URL before selecting the custom page redirect option.', 'nexisettings' ), 'error' );
		}

		$raw_slug = isset( $input['custom_login_slug'] ) ? trim( sanitize_text_field( $input['custom_login_slug'] ) ) : '';
		$raw_slug = trim( $raw_slug, '/' );
		$slug     = sanitize_title( $raw_slug );

		if ( '' !== $raw_slug && preg_match( '#[\\/\\\\]#', $raw_slug ) ) {
			$slug = '';
			add_settings_error( NEXISETTINGS_OPTION, 'nexisettings-invalid-login-slug', esc_html__( 'The custom login slug cannot contain slashes.', 'nexisettings' ), 'error' );
		}

		if ( '' !== $slug && in_array( $slug, NexiSettings::get_reserved_login_slugs(), true ) ) {
			$slug = '';
			add_settings_error( NEXISETTINGS_OPTION, 'nexisettings-reserved-login-slug', esc_html__( 'That custom login slug is reserved by WordPress. Choose a different slug.', 'nexisettings' ), 'error' );
		}

		if ( ! empty( $output['enable_custom_login'] ) && '' === $slug ) {
			$output['enable_custom_login'] = 0;
			$output['custom_login_slug']  = $old_slug;
			add_settings_error( NEXISETTINGS_OPTION, 'nexisettings-login-disabled', esc_html__( 'Custom login protection was not enabled because the login slug is invalid or empty.', 'nexisettings' ), 'error' );
		} elseif ( '' !== $slug ) {
			$output['custom_login_slug'] = $slug;
		} else {
			$output['custom_login_slug'] = '';
		}

		if ( $was_enabled !== (bool) $output['enable_custom_login'] || $old_slug !== $output['custom_login_slug'] ) {
			add_action( 'shutdown', array( $this, 'flush_rewrite_rules' ) );
		}

		return $output;
	}

	/**
	 * Sanitize login branding fields.
	 *
	 * @param array $input  Submitted settings.
	 * @param array $output Output settings.
	 * @return array
	 */
	private function sanitize_login_branding_options( $input, $output ) {
		if ( ! empty( $input['reset_login_branding'] ) ) {
			$output['login_logo_id']   = 0;
			$output['login_logo_url']  = '';
			$output['login_logo_text'] = '';
			return $output;
		}

		$output['login_logo_id']   = isset( $input['login_logo_id'] ) ? absint( $input['login_logo_id'] ) : 0;
		$output['login_logo_url']  = isset( $input['login_logo_url'] ) ? esc_url_raw( $input['login_logo_url'] ) : '';
		$output['login_logo_text'] = isset( $input['login_logo_text'] ) ? wp_kses_post( $input['login_logo_text'] ) : '';

		return $output;
	}

	/**
	 * Flush rewrite rules after custom login changes.
	 *
	 * @return void
	 */
	public function flush_rewrite_rules() {
		flush_rewrite_rules( false );
	}

	/**
	 * Count non-empty submitted redirect rows.
	 *
	 * @param array $rows Submitted redirect rows.
	 * @return int
	 */
	private function count_submitted_redirect_rows( $rows ) {
		$count = 0;

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) || ! empty( $row['delete'] ) ) {
				continue;
			}

			$source      = isset( $row['source'] ) ? trim( (string) $row['source'] ) : '';
			$destination = isset( $row['destination'] ) ? trim( (string) $row['destination'] ) : '';

			if ( '' === $source && '' === $destination ) {
				continue;
			}

			$count++;
		}

		return $count;
	}

	/**
	 * Sanitize a same-site page URL for wp-login.php block redirects.
	 *
	 * @param mixed $url Submitted URL.
	 * @return string
	 */
	private function sanitize_local_page_url( $url ) {
		if ( ! is_scalar( $url ) ) {
			return '';
		}

		$url = trim( sanitize_text_field( wp_unslash( $url ) ) );

		if ( '' === $url || 0 === strpos( $url, '//' ) ) {
			return '';
		}

		if ( 0 === strpos( $url, '/' ) ) {
			return esc_url_raw( $url );
		}

		$parts     = wp_parse_url( $url );
		$home_host = wp_parse_url( home_url( '/' ), PHP_URL_HOST );

		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) || empty( $home_host ) ) {
			return '';
		}

		if ( ! in_array( strtolower( $parts['scheme'] ), array( 'http', 'https' ), true ) ) {
			return '';
		}

		if ( strtolower( $parts['host'] ) !== strtolower( $home_host ) ) {
			return '';
		}

		return esc_url_raw( $url );
	}

	/**
	 * Clear collected Settings API errors before an AJAX save.
	 *
	 * @return void
	 */
	private function clear_settings_errors() {
		global $wp_settings_errors;

		$wp_settings_errors = array();
	}

	/**
	 * Determine whether Settings API messages contain errors.
	 *
	 * @param array $errors Settings API messages.
	 * @return bool
	 */
	private function settings_errors_have_errors( $errors ) {
		foreach ( $errors as $error ) {
			if ( isset( $error['type'] ) && 'error' === $error['type'] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Render Settings API messages as visible notices.
	 *
	 * @param array $errors Settings API messages.
	 * @return string
	 */
	private function render_settings_errors_html( $errors ) {
		$html = '';

		foreach ( $errors as $error ) {
			$type    = isset( $error['type'] ) ? sanitize_key( $error['type'] ) : 'info';
			$message = isset( $error['message'] ) ? $error['message'] : '';

			if ( '' === $message ) {
				continue;
			}

			$html .= $this->render_notice_html( $message, $type );
		}

		return $html;
	}

	/**
	 * Render a high-contrast admin notice.
	 *
	 * @param string $message Notice message.
	 * @param string $type    Notice type.
	 * @return string
	 */
	private function render_notice_html( $message, $type = 'success' ) {
		if ( 'updated' === $type ) {
			$type = 'success';
		}

		$type = in_array( $type, array( 'success', 'error', 'warning', 'info' ), true ) ? $type : 'info';

		ob_start();
		?>
		<div class="notice notice-<?php echo esc_attr( $type ); ?> nexisettings-notice is-dismissible">
			<p><?php echo wp_kses_post( $message ); ?></p>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Get success message for a saved settings tab.
	 *
	 * @param string $tab     Active tab.
	 * @param array  $options Saved options.
	 * @return string
	 */
	private function get_success_message_for_tab( $tab, $options ) {
		if ( 'login-security' === $tab && ! empty( $options['enable_custom_login'] ) && ! empty( $options['custom_login_slug'] ) ) {
			return sprintf(
				/* translators: %s: Current custom login URL. */
				__( 'Login security saved. Current login URL: %s', 'nexisettings' ),
				esc_url( $this->get_current_login_url( $options ) )
			);
		}

		$messages = array(
			'login-security' => __( 'Login security settings saved.', 'nexisettings' ),
			'login-branding' => __( 'Login branding settings saved.', 'nexisettings' ),
			'security'       => __( 'Security settings saved.', 'nexisettings' ),
			'performance'    => __( 'Performance settings saved.', 'nexisettings' ),
			'admin-branding' => __( 'Admin branding settings saved.', 'nexisettings' ),
		);

		return isset( $messages[ $tab ] ) ? $messages[ $tab ] : __( 'Settings saved.', 'nexisettings' );
	}

	/**
	 * Get current login notice HTML.
	 *
	 * @param array $options Plugin options.
	 * @return string
	 */
	private function get_current_login_notice_html( $options ) {
		ob_start();

		if ( NexiSettings::is_custom_login_disabled() ) :
			?>
			<div class="nexisettings-alert nexisettings-alert-warning">
				<?php esc_html_e( 'Custom login protection is disabled because NEXISETTINGS_DISABLE_CUSTOM_LOGIN is defined as true.', 'nexisettings' ); ?>
			</div>
			<?php
		elseif ( ! empty( $options['enable_custom_login'] ) && '' !== $options['custom_login_slug'] ) :
			$current_login_url = $this->get_current_login_url( $options );
			?>
			<div class="nexisettings-alert nexisettings-alert-success">
				<strong><?php esc_html_e( 'Current login URL:', 'nexisettings' ); ?></strong>
				<a href="<?php echo esc_url( $current_login_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $current_login_url ); ?></a>
				<span><?php esc_html_e( 'Bookmark this URL before logging out.', 'nexisettings' ); ?></span>
			</div>
			<?php
		endif;

		return ob_get_clean();
	}

	/**
	 * Get login logo preview URL for AJAX responses.
	 *
	 * @param array $options Plugin options.
	 * @return string
	 */
	private function get_logo_preview_url( $options ) {
		if ( empty( $options['login_logo_id'] ) ) {
			return '';
		}

		$image = wp_get_attachment_image_src( absint( $options['login_logo_id'] ), 'medium' );

		if ( ! is_array( $image ) || empty( $image[0] ) ) {
			return '';
		}

		return esc_url_raw( $image[0] );
	}

	/**
	 * Render Login Security tab.
	 *
	 * @param array $options Plugin options.
	 * @return void
	 */
	private function render_login_security_tab( $options ) {
		?>
		<form method="post" action="options.php" class="nexisettings-form nexisettings-options-form">
			<?php settings_fields( 'nexisettings_options_group' ); ?>
			<input type="hidden" name="<?php echo esc_attr( NEXISETTINGS_OPTION ); ?>[active_tab]" value="login-security" />

			<div class="nexisettings-card">
				<div class="nexisettings-card-header">
					<div>
						<h2><?php esc_html_e( 'Custom Login URL', 'nexisettings' ); ?></h2>
						<p><?php esc_html_e( 'Move public login access away from wp-login.php while preserving a safe admin fallback.', 'nexisettings' ); ?></p>
					</div>
				</div>

				<div class="nexisettings-current-login-wrap">
					<?php echo wp_kses_post( $this->get_current_login_notice_html( $options ) ); ?>
				</div>

				<?php
				$this->render_toggle(
					NEXISETTINGS_OPTION . '[enable_custom_login]',
					'enable_custom_login',
					! empty( $options['enable_custom_login'] ),
					esc_html__( 'Enable custom login URL', 'nexisettings' ),
					esc_html__( 'When enabled, non-logged-in direct visits to wp-login.php are blocked according to the action below.', 'nexisettings' )
				);
				?>

				<label class="nexisettings-field">
					<span><?php esc_html_e( 'Custom login slug', 'nexisettings' ); ?></span>
					<div class="nexisettings-prefix-input">
						<span><?php echo esc_html( trailingslashit( home_url() ) ); ?></span>
						<input type="text" name="<?php echo esc_attr( NEXISETTINGS_OPTION ); ?>[custom_login_slug]" value="<?php echo esc_attr( $options['custom_login_slug'] ); ?>" placeholder="<?php esc_attr_e( 'my-login', 'nexisettings' ); ?>" />
					</div>
					<small><?php esc_html_e( 'Use letters, numbers, and hyphens only. Reserved slugs like wp-admin, wp-content, login, and admin are blocked.', 'nexisettings' ); ?></small>
				</label>

				<label class="nexisettings-field">
					<span><?php esc_html_e( 'When wp-login.php is visited', 'nexisettings' ); ?></span>
					<select name="<?php echo esc_attr( NEXISETTINGS_OPTION ); ?>[login_block_action]">
						<option value="404" <?php selected( $options['login_block_action'], '404' ); ?>><?php esc_html_e( 'Show 404', 'nexisettings' ); ?></option>
						<option value="home" <?php selected( $options['login_block_action'], 'home' ); ?>><?php esc_html_e( 'Redirect to homepage', 'nexisettings' ); ?></option>
						<option value="custom_url" <?php selected( $options['login_block_action'], 'custom_url' ); ?>><?php esc_html_e( 'Redirect to custom page URL', 'nexisettings' ); ?></option>
					</select>
					<small><?php esc_html_e( 'Logged-in users are never blocked from wp-login.php.', 'nexisettings' ); ?></small>
				</label>

				<label class="nexisettings-field nexisettings-custom-block-url-field <?php echo esc_attr( 'custom_url' === $options['login_block_action'] ? '' : 'is-hidden' ); ?>">
					<span><?php esc_html_e( 'Custom page URL', 'nexisettings' ); ?></span>
					<input type="text" name="<?php echo esc_attr( NEXISETTINGS_OPTION ); ?>[login_block_custom_url]" value="<?php echo esc_attr( $options['login_block_custom_url'] ); ?>" placeholder="<?php echo esc_attr( home_url( '/login-help/' ) ); ?>" />
					<small><?php esc_html_e( 'Use a same-site page URL such as /login-help/ or a full URL on this domain.', 'nexisettings' ); ?></small>
				</label>
			</div>

			<?php submit_button( esc_html__( 'Save Login Security', 'nexisettings' ) ); ?>
		</form>
		<?php
	}

	/**
	 * Render Login Branding tab.
	 *
	 * @param array $options Plugin options.
	 * @return void
	 */
	private function render_login_branding_tab( $options ) {
		$logo_url = '';
		if ( ! empty( $options['login_logo_id'] ) ) {
			$image = wp_get_attachment_image_src( absint( $options['login_logo_id'] ), 'medium' );
			if ( is_array( $image ) && ! empty( $image[0] ) ) {
				$logo_url = $image[0];
			}
		}
		?>
		<form method="post" action="options.php" class="nexisettings-form nexisettings-options-form">
			<?php settings_fields( 'nexisettings_options_group' ); ?>
			<input type="hidden" name="<?php echo esc_attr( NEXISETTINGS_OPTION ); ?>[active_tab]" value="login-branding" />

			<div class="nexisettings-card">
				<div class="nexisettings-card-header">
					<div>
						<h2><?php esc_html_e( 'Login Page Branding', 'nexisettings' ); ?></h2>
						<p><?php esc_html_e( 'Customize the WordPress login screen with your brand assets and helper text.', 'nexisettings' ); ?></p>
					</div>
				</div>

				<div class="nexisettings-logo-control">
					<input type="hidden" class="nexisettings-logo-id" name="<?php echo esc_attr( NEXISETTINGS_OPTION ); ?>[login_logo_id]" value="<?php echo esc_attr( absint( $options['login_logo_id'] ) ); ?>" />
					<div class="nexisettings-logo-preview <?php echo esc_attr( empty( $logo_url ) ? 'is-empty' : '' ); ?>">
						<?php if ( ! empty( $logo_url ) ) : ?>
							<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php esc_attr_e( 'Selected login logo', 'nexisettings' ); ?>" />
						<?php else : ?>
							<span><?php esc_html_e( 'No logo selected', 'nexisettings' ); ?></span>
						<?php endif; ?>
					</div>
					<div class="nexisettings-logo-actions">
						<button type="button" class="button nexisettings-upload-logo"><?php esc_html_e( 'Choose Logo', 'nexisettings' ); ?></button>
						<button type="button" class="button nexisettings-remove-logo"><?php esc_html_e( 'Remove Logo', 'nexisettings' ); ?></button>
					</div>
				</div>

				<label class="nexisettings-field">
					<span><?php esc_html_e( 'Logo URL', 'nexisettings' ); ?></span>
					<input type="url" name="<?php echo esc_attr( NEXISETTINGS_OPTION ); ?>[login_logo_url]" value="<?php echo esc_attr( $options['login_logo_url'] ); ?>" placeholder="<?php echo esc_attr( home_url( '/' ) ); ?>" />
					<small><?php esc_html_e( 'Where users go when they click the login logo. Leave blank for WordPress default.', 'nexisettings' ); ?></small>
				</label>

				<label class="nexisettings-field">
					<span><?php esc_html_e( 'Text below logo', 'nexisettings' ); ?></span>
					<textarea name="<?php echo esc_attr( NEXISETTINGS_OPTION ); ?>[login_logo_text]" rows="4"><?php echo esc_textarea( $options['login_logo_text'] ); ?></textarea>
					<small><?php esc_html_e( 'Basic formatting is allowed. Unsafe HTML is removed when saved.', 'nexisettings' ); ?></small>
				</label>
			</div>

			<?php submit_button( esc_html__( 'Save Login Branding', 'nexisettings' ), 'primary', 'submit', false ); ?>
			<button type="submit" class="button button-secondary" name="<?php echo esc_attr( NEXISETTINGS_OPTION ); ?>[reset_login_branding]" value="1"><?php esc_html_e( 'Reset to Default', 'nexisettings' ); ?></button>
		</form>
		<?php
	}

	/**
	 * Render Redirects tab.
	 *
	 * @param array $redirects Redirect rows.
	 * @return void
	 */
	private function render_redirects_tab( $redirects ) {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="nexisettings-form nexisettings-redirects-form">
			<input type="hidden" name="action" value="nexisettings_save_redirects" />
			<?php wp_nonce_field( 'nexisettings_save_redirects' ); ?>

			<div class="nexisettings-card">
				<div class="nexisettings-card-header">
					<div>
						<h2><?php esc_html_e( 'Redirect Manager', 'nexisettings' ); ?></h2>
						<p><?php esc_html_e( 'Create simple frontend 301 and 302 redirects without touching wp-admin, AJAX, cron, or REST requests.', 'nexisettings' ); ?></p>
					</div>
					<button type="button" class="button button-secondary nexisettings-add-redirect"><?php esc_html_e( 'Add Redirect', 'nexisettings' ); ?></button>
				</div>

				<div class="nexisettings-table-wrap">
					<table class="widefat fixed striped nexisettings-redirects-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Source URL', 'nexisettings' ); ?></th>
								<th><?php esc_html_e( 'Destination URL', 'nexisettings' ); ?></th>
								<th><?php esc_html_e( 'Type', 'nexisettings' ); ?></th>
								<th><?php esc_html_e( 'Enabled', 'nexisettings' ); ?></th>
								<th><?php esc_html_e( 'Delete', 'nexisettings' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php
							if ( empty( $redirects ) ) {
								$redirects = array(
									array(
										'id'          => '',
										'source'      => '',
										'destination' => '',
										'type'        => 301,
										'enabled'     => 1,
									),
								);
							}

							foreach ( array_values( $redirects ) as $index => $redirect ) :
								$this->render_redirect_row( $index, $redirect );
							endforeach;
							?>
						</tbody>
					</table>
				</div>
				<p class="description"><?php esc_html_e( 'Use root-relative URLs like /old-page or absolute http(s) URLs. Protocol-relative URLs are rejected.', 'nexisettings' ); ?></p>
			</div>

			<?php submit_button( esc_html__( 'Save Redirects', 'nexisettings' ) ); ?>
		</form>

		<script type="text/template" id="nexisettings-redirect-row-template">
			<?php
			$this->render_redirect_row(
				'__index__',
				array(
					'id'          => '',
					'source'      => '',
					'destination' => '',
					'type'        => 301,
					'enabled'     => 1,
				)
			);
			?>
		</script>
		<?php
	}

	/**
	 * Render Security tab.
	 *
	 * @param array $options Plugin options.
	 * @return void
	 */
	private function render_security_tab( $options ) {
		?>
		<form method="post" action="options.php" class="nexisettings-form nexisettings-options-form">
			<?php settings_fields( 'nexisettings_options_group' ); ?>
			<input type="hidden" name="<?php echo esc_attr( NEXISETTINGS_OPTION ); ?>[active_tab]" value="security" />
			<div class="nexisettings-card">
				<div class="nexisettings-card-header">
					<div>
						<h2><?php esc_html_e( 'Basic Security', 'nexisettings' ); ?></h2>
						<p><?php esc_html_e( 'Enable small hardening improvements that reduce common public signals and attack surfaces.', 'nexisettings' ); ?></p>
					</div>
				</div>
				<?php
				$this->render_toggle( NEXISETTINGS_OPTION . '[disable_xmlrpc]', 'disable_xmlrpc', ! empty( $options['disable_xmlrpc'] ), esc_html__( 'Disable XML-RPC', 'nexisettings' ), esc_html__( 'Turns off XML-RPC authentication and remote publishing endpoints through WordPress filters.', 'nexisettings' ) );
				$this->render_toggle( NEXISETTINGS_OPTION . '[disable_user_enumeration]', 'disable_user_enumeration', ! empty( $options['disable_user_enumeration'] ), esc_html__( 'Disable user enumeration', 'nexisettings' ), esc_html__( 'Blocks common ?author=1 discovery requests for logged-out visitors.', 'nexisettings' ) );
				$this->render_toggle( NEXISETTINGS_OPTION . '[hide_wp_version]', 'hide_wp_version', ! empty( $options['hide_wp_version'] ), esc_html__( 'Hide WordPress version', 'nexisettings' ), esc_html__( 'Removes the generator meta tag and generator output.', 'nexisettings' ) );
				?>
			</div>
			<?php submit_button( esc_html__( 'Save Security Settings', 'nexisettings' ) ); ?>
		</form>
		<?php
	}

	/**
	 * Render Performance tab.
	 *
	 * @param array $options Plugin options.
	 * @return void
	 */
	private function render_performance_tab( $options ) {
		?>
		<form method="post" action="options.php" class="nexisettings-form nexisettings-options-form">
			<?php settings_fields( 'nexisettings_options_group' ); ?>
			<input type="hidden" name="<?php echo esc_attr( NEXISETTINGS_OPTION ); ?>[active_tab]" value="performance" />
			<div class="nexisettings-card">
				<div class="nexisettings-card-header">
					<div>
						<h2><?php esc_html_e( 'Performance Tweaks', 'nexisettings' ); ?></h2>
						<p><?php esc_html_e( 'Remove optional WordPress frontend assets that many sites do not need.', 'nexisettings' ); ?></p>
					</div>
				</div>
				<?php
				$this->render_toggle( NEXISETTINGS_OPTION . '[disable_emojis]', 'disable_emojis', ! empty( $options['disable_emojis'] ), esc_html__( 'Disable emojis', 'nexisettings' ), esc_html__( 'Removes WordPress emoji detection scripts, styles, filters, and DNS prefetch hints.', 'nexisettings' ) );
				$this->render_toggle( NEXISETTINGS_OPTION . '[disable_embeds]', 'disable_embeds', ! empty( $options['disable_embeds'] ), esc_html__( 'Disable embeds', 'nexisettings' ), esc_html__( 'Disables oEmbed discovery links and dequeues the wp-embed script on the frontend.', 'nexisettings' ) );
				?>
			</div>
			<?php submit_button( esc_html__( 'Save Performance Settings', 'nexisettings' ) ); ?>
		</form>
		<?php
	}

	/**
	 * Render Admin Branding tab.
	 *
	 * @param array $options Plugin options.
	 * @return void
	 */
	private function render_admin_branding_tab( $options ) {
		?>
		<form method="post" action="options.php" class="nexisettings-form nexisettings-options-form">
			<?php settings_fields( 'nexisettings_options_group' ); ?>
			<input type="hidden" name="<?php echo esc_attr( NEXISETTINGS_OPTION ); ?>[active_tab]" value="admin-branding" />
			<div class="nexisettings-card">
				<div class="nexisettings-card-header">
					<div>
						<h2><?php esc_html_e( 'Admin Branding', 'nexisettings' ); ?></h2>
						<p><?php esc_html_e( 'Replace the default WordPress admin footer with your own message.', 'nexisettings' ); ?></p>
					</div>
				</div>
				<?php
				$this->render_toggle( NEXISETTINGS_OPTION . '[enable_admin_footer_text]', 'enable_admin_footer_text', ! empty( $options['enable_admin_footer_text'] ), esc_html__( 'Enable custom admin footer', 'nexisettings' ), esc_html__( 'When enabled, the footer text below replaces the default WordPress footer text.', 'nexisettings' ) );
				?>
				<label class="nexisettings-field">
					<span><?php esc_html_e( 'Custom admin footer text', 'nexisettings' ); ?></span>
					<textarea name="<?php echo esc_attr( NEXISETTINGS_OPTION ); ?>[custom_admin_footer_text]" rows="4"><?php echo esc_textarea( $options['custom_admin_footer_text'] ); ?></textarea>
					<small><?php esc_html_e( 'Basic formatting is allowed. Unsafe HTML is removed when saved.', 'nexisettings' ); ?></small>
				</label>
			</div>
			<?php submit_button( esc_html__( 'Save Admin Branding', 'nexisettings' ) ); ?>
		</form>
		<?php
	}

	/**
	 * Render a toggle control.
	 *
	 * @param string $name        Field name.
	 * @param string $id          Field ID suffix.
	 * @param bool   $checked     Checked state.
	 * @param string $label       Field label.
	 * @param string $description Field description.
	 * @return void
	 */
	private function render_toggle( $name, $id, $checked, $label, $description ) {
		$field_id = 'nexisettings-' . sanitize_key( $id );
		?>
		<div class="nexisettings-toggle-row">
			<div>
				<label for="<?php echo esc_attr( $field_id ); ?>"><?php echo esc_html( $label ); ?></label>
				<small><?php echo esc_html( $description ); ?></small>
			</div>
			<label class="nexisettings-switch" aria-label="<?php echo esc_attr( $label ); ?>">
				<input id="<?php echo esc_attr( $field_id ); ?>" type="checkbox" name="<?php echo esc_attr( $name ); ?>" value="1" <?php checked( $checked ); ?> />
				<span></span>
			</label>
		</div>
		<?php
	}

	/**
	 * Render a redirect row.
	 *
	 * @param int|string $index    Row index.
	 * @param array      $redirect Redirect row.
	 * @return void
	 */
	private function render_redirect_row( $index, $redirect ) {
		$name = 'nexisettings_redirects[' . $index . ']';
		?>
		<tr>
			<td>
				<input type="hidden" name="<?php echo esc_attr( $name ); ?>[id]" value="<?php echo esc_attr( isset( $redirect['id'] ) ? $redirect['id'] : '' ); ?>" />
				<input type="text" name="<?php echo esc_attr( $name ); ?>[source]" value="<?php echo esc_attr( isset( $redirect['source'] ) ? $redirect['source'] : '' ); ?>" placeholder="/old-page" />
			</td>
			<td>
				<input type="text" name="<?php echo esc_attr( $name ); ?>[destination]" value="<?php echo esc_attr( isset( $redirect['destination'] ) ? $redirect['destination'] : '' ); ?>" placeholder="/new-page" />
			</td>
			<td>
				<select name="<?php echo esc_attr( $name ); ?>[type]">
					<option value="301" <?php selected( isset( $redirect['type'] ) ? absint( $redirect['type'] ) : 301, 301 ); ?>>301</option>
					<option value="302" <?php selected( isset( $redirect['type'] ) ? absint( $redirect['type'] ) : 301, 302 ); ?>>302</option>
				</select>
			</td>
			<td>
				<label class="nexisettings-switch nexisettings-switch-small">
					<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[enabled]" value="1" <?php checked( ! isset( $redirect['enabled'] ) || ! empty( $redirect['enabled'] ) ); ?> />
					<span></span>
				</label>
			</td>
			<td>
				<input type="hidden" class="nexisettings-delete-value" name="<?php echo esc_attr( $name ); ?>[delete]" value="0" />
				<button type="button" class="button-link-delete nexisettings-delete-row"><?php esc_html_e( 'Delete', 'nexisettings' ); ?></button>
			</td>
		</tr>
		<?php
	}

	/**
	 * Get available tabs.
	 *
	 * @return array
	 */
	private function get_tabs() {
		return array(
			'login-security' => __( 'Login Security', 'nexisettings' ),
			'login-branding' => __( 'Login Branding', 'nexisettings' ),
			'redirects'      => __( 'Redirects', 'nexisettings' ),
			'security'       => __( 'Security', 'nexisettings' ),
			'performance'    => __( 'Performance', 'nexisettings' ),
			'admin-branding' => __( 'Admin Branding', 'nexisettings' ),
		);
	}

	/**
	 * Get active tab.
	 *
	 * @return string
	 */
	private function get_active_tab() {
		$tabs = $this->get_tabs();
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'login-security'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		return array_key_exists( $tab, $tabs ) ? $tab : 'login-security';
	}

	/**
	 * Get current login URL for display.
	 *
	 * @param array $options Plugin options.
	 * @return string
	 */
	private function get_current_login_url( $options ) {
		if ( empty( $options['custom_login_slug'] ) ) {
			return wp_login_url();
		}

		return home_url( '/' . trim( sanitize_title( $options['custom_login_slug'] ), '/' ) . '/' );
	}

	/**
	 * Set a short-lived admin notice.
	 *
	 * @param string $message Notice text.
	 * @param string $type    Notice type.
	 * @return void
	 */
	private function set_admin_notice( $message, $type = 'success' ) {
		set_transient(
			'nexisettings_admin_notice_' . get_current_user_id(),
			array(
				'message' => sanitize_text_field( $message ),
				'type'    => sanitize_key( $type ),
			),
			30
		);
	}

	/**
	 * Display and clear a stored admin notice.
	 *
	 * @return void
	 */
	private function display_admin_notice() {
		$key    = 'nexisettings_admin_notice_' . get_current_user_id();
		$notice = get_transient( $key );

		if ( ! is_array( $notice ) || empty( $notice['message'] ) ) {
			return;
		}

		delete_transient( $key );
		$type = isset( $notice['type'] ) && in_array( $notice['type'], array( 'success', 'error', 'warning', 'info' ), true ) ? $notice['type'] : 'success';
		?>
		<div class="notice notice-<?php echo esc_attr( $type ); ?> is-dismissible">
			<p><?php echo esc_html( $notice['message'] ); ?></p>
		</div>
		<?php
	}
}

<?php
/**
 * Plugin Name: NexiSettings – Login, Redirect & Admin Toolkit
 * Plugin URI: https://nexiby.com/
 * Description: Secure and customize WordPress with custom login URLs, redirect management, security enhancements, performance tweaks, and admin branding tools.
 * Version: 1.0.0
 * Author: Nexiby LLC
 * Author URI: https://nexiby.com/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: nexisettings
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 *
 * @package NexiSettings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'NEXISETTINGS_VERSION', '1.0.0' );
define( 'NEXISETTINGS_FILE', __FILE__ );
define( 'NEXISETTINGS_PATH', plugin_dir_path( __FILE__ ) );
define( 'NEXISETTINGS_URL', plugin_dir_url( __FILE__ ) );
define( 'NEXISETTINGS_BASENAME', plugin_basename( __FILE__ ) );
define( 'NEXISETTINGS_OPTION', 'nexisettings_options' );
define( 'NEXISETTINGS_REDIRECTS_OPTION', 'nexisettings_redirects' );

require_once NEXISETTINGS_PATH . 'includes/class-nexisettings.php';

register_activation_hook( __FILE__, array( 'NexiSettings', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'NexiSettings', 'deactivate' ) );

/**
 * Start the plugin.
 *
 * @return void
 */
function nexisettings_run() {
	NexiSettings::instance()->run();
}

nexisettings_run();

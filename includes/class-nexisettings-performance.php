<?php
/**
 * Performance toggles.
 *
 * @package NexiSettings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Applies lightweight performance cleanup options.
 */
class NexiSettings_Performance {
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

		if ( ! empty( $this->options['disable_emojis'] ) ) {
			$this->disable_emojis();
		}

		if ( ! empty( $this->options['disable_embeds'] ) ) {
			$this->disable_embeds();
		}
	}

	/**
	 * Remove WordPress emoji scripts and styles.
	 *
	 * @return void
	 */
	private function disable_emojis() {
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		remove_action( 'admin_print_styles', 'print_emoji_styles' );
		remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
		remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
		remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );

		add_filter( 'tiny_mce_plugins', array( $this, 'disable_emojis_tinymce' ) );
		add_filter( 'wp_resource_hints', array( $this, 'nexisettings_remove_emoji_dns_prefetch' ), 10, 2 );
	}

	/**
	 * Remove the TinyMCE emoji plugin.
	 *
	 * @param array $plugins TinyMCE plugins.
	 * @return array
	 */
	public function disable_emojis_tinymce( $plugins ) {
		if ( is_array( $plugins ) ) {
			return array_diff( $plugins, array( 'wpemoji' ) );
		}

		return array();
	}

	/**
	 * Remove emoji DNS prefetch hints.
	 *
	 * @param array  $urls          Resource hints.
	 * @param string $relation_type Relation type.
	 * @return array
	 */
	public function nexisettings_remove_emoji_dns_prefetch( $urls, $relation_type ) {
		if ( 'dns-prefetch' !== $relation_type ) {
			return $urls;
		}

		$emoji_svg_url = 'https://s.w.org/images/core/emoji/15.0.3/svg/';

		return array_diff( $urls, array( $emoji_svg_url ) );
	}

	/**
	 * Disable frontend oEmbed scripts where appropriate.
	 *
	 * @return void
	 */
	private function disable_embeds() {
		remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
		remove_action( 'wp_head', 'wp_oembed_add_host_js' );
		remove_filter( 'oembed_dataparse', 'wp_filter_oembed_result', 10 );

		add_filter( 'embed_oembed_discover', '__return_false' );
		add_action( 'wp_enqueue_scripts', array( $this, 'dequeue_embed_script' ), 100 );
	}

	/**
	 * Dequeue wp-embed on the frontend.
	 *
	 * @return void
	 */
	public function dequeue_embed_script() {
		if ( is_admin() ) {
			return;
		}

		wp_deregister_script( 'wp-embed' );
	}
}

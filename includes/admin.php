<?php
/**
 * Admin UI for WP101.
 *
 * @package WP101
 */

namespace WP101\Admin;

use WP101\API;
use WP101\Migrate as Migrate;
use WP101\TemplateTags as TemplateTags;

/**
 * Register the plugin textdomain.
 */
function register_textdomain() {
	load_plugin_textdomain( 'wp101', false, basename( dirname( WP101_BASENAME ) ) . '/languages' );
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\register_textdomain' );

/**
 * Register scripts and styles to be used in WP admin.
 *
 * @param string $hook The page being loaded.
 */
function enqueue_scripts( $hook ) {
	wp_register_style(
		'wp101-admin',
		WP101_URL . '/assets/css/wp101-admin.css',
		null,
		WP101_VERSION,
		'all'
	);

	wp_register_script(
		'wp101-admin',
		WP101_URL . '/assets/js/wp101-admin.min.js',
		array( 'jquery-ui-accordion' ),
		WP101_VERSION,
		true
	);

	// Only enqueue on WP101 pages.
	if ( 'toplevel_page_wp101' === $hook || preg_match( '/^video-tutorials_page_wp101/', $hook ) ) {
		wp_enqueue_style( 'wp101-admin' );
		wp_enqueue_script( 'wp101-admin' );
	}
}
add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\enqueue_scripts' );

/**
 * Retrieve the capability necessary for users to view/purchase add-ons.
 *
 * @return string A WordPress capability name.
 */
function get_addon_capability() {

	/**
	 * Determine the capability a user must possess in order to purchase WP101 add-ons.
	 *
	 * @param string $capability The capability name.
	 */
	return apply_filters( 'wp101_addon_capability', 'publish_posts' );
}

/**
 * Register the WP101 settings page.
 */
function register_menu_pages() {

	// If the API key hasn't been configured, *only* show the settings page.
	if ( ! TemplateTags\api()->has_api_key() ) {
		return add_menu_page(
			_x( 'WP101', 'page title', 'wp101' ),
			_x( 'Video Tutorials', 'menu title', 'wp101' ),
			'manage_options',
			'wp101-settings',
			__NAMESPACE__ . '\render_settings_page',
			'dashicons-video-alt3'
		);
	}

	add_menu_page(
		_x( 'WP101', 'page title', 'wp101' ),
		_x( 'Video Tutorials', 'menu title', 'wp101' ),
		'read',
		'wp101',
		__NAMESPACE__ . '\render_listings_page',
		'dashicons-video-alt3'
	);

	add_submenu_page(
		'wp101',
		_x( 'WP101 Settings', 'page title', 'wp101' ),
		_x( 'Settings', 'menu title', 'wp101' ),
		'manage_options',
		'wp101-settings',
		__NAMESPACE__ . '\render_settings_page'
	);

	$addons = TemplateTags\api()->get_addons();

	if ( ! empty( $addons['addons'] ) ) {
		add_submenu_page(
			'wp101',
			_x( 'WP101 Add-ons', 'page title', 'wp101' ),
			_x( 'Add-ons', 'menu title', 'wp101' ),
			get_addon_capability(),
			'wp101-addons',
			__NAMESPACE__ . '\render_addons_page'
		);
	}
}
add_action( 'admin_menu', __NAMESPACE__ . '\register_menu_pages' );

/**
 * Add a link to the WP101 plugin settings page on the plugin page.
 *
 * @param array $links Links currently being displayed for this plugin.
 *
 * @return array The filtered $links array.
 */
function plugin_settings_link( $links ) {
	$links['settings'] = sprintf(
		'<a href="%1$s">%2$s</a>',
		get_admin_url( null, 'admin.php?page=wp101-settings' ),
		_x( 'Settings', 'plugin links', 'wp101' )
	);

	return $links;
}
add_action( 'plugin_action_links_' . WP101_BASENAME, __NAMESPACE__ . '\plugin_settings_link' );

/**
 * Register the settings within WordPress.
 */
function register_settings() {
	register_setting( 'wp101', 'wp101_api_key', [
		'description'       => _x( 'The key used to authenticate with WP101plugin.com.', 'wp101' ),
		'sanitize_callback' => 'sanitize_text_field',
		'show_in_rest'      => false,
	] );
}
add_action( 'admin_init', __NAMESPACE__ . '\register_settings' );

/**
 * Render the WP101 add-ons page.
 */
function render_addons_page() {
	Migrate\maybe_migrate();

	$api       = TemplateTags\api();
	$addons    = $api->get_addons();
	$purchased = wp_list_pluck( $api->get_playlist()['series'], 'slug' );

	include WP101_VIEWS . '/add-ons.php';
}

/**
 * Render the WP101 listings page.
 */
function render_listings_page() {
	Migrate\maybe_migrate();

	$api        = TemplateTags\api();
	$playlist   = $api->get_playlist();
	$public_key = $api->get_public_api_key();

	// Filter out irrelevant series.
	$playlist['series'] = array_filter( $playlist['series'], __NAMESPACE__ . '\is_relevant_series' );

	include WP101_VIEWS . '/listings.php';
}

/**
 * Render the WP101 settings page.
 */
function render_settings_page() {
	Migrate\maybe_migrate();

	/** This action is documented in wp-admin/admin-header.php. */
	do_action( 'admin_notices' );

	include WP101_VIEWS . '/settings.php';
}

/**
 * Flush the public key after saving the private key.
 */
function clear_public_api_key() {
	delete_option( API::PUBLIC_API_KEY_OPTION );

	// Prime the cache with the new public + private keys.
	$api = TemplateTags\api();
	$api->clear_api_key();
	$api->get_public_api_key();
}
add_action( 'update_option_wp101_api_key', __NAMESPACE__ . '\clear_public_api_key' );

/**
 * Determine whether or not a series is relevant to the current site.
 *
 * Relevancy is determined based on two factors:
 *
 * 1. Does the series define any specific requirements?
 * 2. If so, does this site meet those requirements?
 *
 * For example, a series about Jetpack might specify that it should only be displayed on sites
 * running Jetpack — if a site doesn't have Jetpack installed and activated, don't bother
 * displaying the series.
 *
 * @param array $series The series object, as returned from the API.
 *
 * @return bool Whether or not the series should be displayed.
 */
function is_relevant_series( $series ) {
	if ( ! isset( $series['restrictions']['plugins'] ) || empty( $series['restrictions']['plugins'] ) ) {
		return true;
	}

	$restrictions = array_filter( $series['restrictions']['plugins'], 'is_plugin_active' );

	return ! empty( $restrictions );
}

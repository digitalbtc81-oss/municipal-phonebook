<?php
/**
 * Plugin Name:       Municipal Phonebook
 * Plugin URI:        https://github.com/digitalbtc81-oss/municipal-phonebook
 * Description:       Searchable telephone directory grouped by departments and sub-departments, with accent-insensitive Greek search, category filter, responsive cards, shortcode, block and Elementor widget.
 * Version:           1.4.0
 * Update URI:        https://github.com/digitalbtc81-oss/municipal-phonebook
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Elementor tested up to: 4.3.5
 * Author:            dev
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       municipal-phonebook
 * Domain Path:       /languages
 *
 * @package MunicipalPhonebook
 */

defined( 'ABSPATH' ) || exit;

define( 'MUNIPB_VERSION', '1.4.0' );
define( 'MUNIPB_FILE', __FILE__ );
define( 'MUNIPB_DIR', plugin_dir_path( __FILE__ ) );
define( 'MUNIPB_URL', plugin_dir_url( __FILE__ ) );

require_once MUNIPB_DIR . 'includes/functions.php';
require_once MUNIPB_DIR . 'includes/class-munipb-post-types.php';
require_once MUNIPB_DIR . 'includes/class-munipb-frontend.php';
require_once MUNIPB_DIR . 'includes/class-munipb-rest.php';
require_once MUNIPB_DIR . 'includes/class-munipb-elementor.php';
require_once MUNIPB_DIR . 'includes/class-munipb-updater.php';

if ( is_admin() ) {
	require_once MUNIPB_DIR . 'includes/class-munipb-admin.php';
	require_once MUNIPB_DIR . 'includes/class-munipb-csv.php';
}

/**
 * Boot the plugin.
 *
 * @return void
 */
function munipb_bootstrap() {
	Munipb_Post_Types::init();
	Munipb_Frontend::init();
	Munipb_REST::init();
	Munipb_Elementor::init();
	Munipb_Updater::init();

	if ( is_admin() ) {
		Munipb_Admin::init();
		Munipb_CSV::init();
	}
}
add_action( 'plugins_loaded', 'munipb_bootstrap' );

/**
 * Upgrade routine: runs once per version change and removes data older versions left behind.
 *
 * @return void
 */
function munipb_maybe_upgrade() {
	$installed = (string) get_option( 'munipb_version', '' );
	if ( MUNIPB_VERSION === $installed ) {
		return;
	}

	// 1.2.0 removed pagination: drop the orphan "per_page" setting.
	$settings = get_option( 'munipb_settings' );
	if ( is_array( $settings ) && array_key_exists( 'per_page', $settings ) ) {
		unset( $settings['per_page'] );
		update_option( 'munipb_settings', $settings );
	}

	// The cached GitHub release describes the previous install.
	Munipb_Updater::forget();

	munipb_flush_cache();
	update_option( 'munipb_version', MUNIPB_VERSION );
}
add_action( 'plugins_loaded', 'munipb_maybe_upgrade', 5 );

/**
 * Activation: register types so rewrite/capabilities exist, set default options.
 *
 * @return void
 */
function munipb_activate() {
	Munipb_Post_Types::register();
	add_option( 'munipb_settings', munipb_default_settings() );
	munipb_maybe_upgrade();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'munipb_activate' );

/**
 * Deactivation.
 *
 * @return void
 */
function munipb_deactivate() {
	munipb_flush_cache();
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'munipb_deactivate' );

<?php
/**
 * Plugin Name:       On-board Diagnostics Scanner for WooCommerce
 * Description:       A free health dashboard for your WooCommerce store, by tvarkom: server, cart and checkout speed, page weight, database, PHP and email security records, scored from 0 to 100 and checked again every week.
 * Version:           0.1.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Author:            tvarkom
 * Author URI:        https://tvarkom.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       obd-scanner
 *
 * @package OBDScanner
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TVARKOM_OBD_VERSION', '0.1.0' );
define( 'TVARKOM_OBD_FILE', __FILE__ );
define( 'TVARKOM_OBD_OPTION', 'tvarkom_obd' );
define( 'TVARKOM_OBD_TOKEN', 'tvarkom_obd_verify_token' );
define( 'TVARKOM_OBD_LOCK', 'tvarkom_obd_scan_lock' );
define( 'TVARKOM_OBD_CRON', 'tvarkom_obd_scan' );

// Overridable in wp-config.php, e.g. for testing against a local gateway.
if ( ! defined( 'TVARKOM_OBD_API_URL' ) ) {
	define( 'TVARKOM_OBD_API_URL', 'https://api.tvarkom.com' );
}

require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/rest.php';
require_once __DIR__ . '/includes/metrics.php';
require_once __DIR__ . '/includes/scan.php';
require_once __DIR__ . '/includes/dashboard.php';
require_once __DIR__ . '/includes/admin.php';

// Every hook the plugin registers. Nothing contacts tvarkom until an administrator clicks
// "Connect and scan" on the plugin's page (includes/admin.php).
add_action( 'admin_menu', 'tvarkom_obd_admin_menu', 99 );
add_action( 'admin_post_tvarkom_obd_connect', 'tvarkom_obd_handle_connect' );
add_action( 'admin_enqueue_scripts', 'tvarkom_obd_admin_assets' );
add_action( 'admin_init', 'tvarkom_obd_privacy_policy_content' );
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'tvarkom_obd_plugin_action_links' );

// Domain check for registration (includes/rest.php).
add_action( 'rest_api_init', 'tvarkom_obd_register_routes' );

// The weekly scan, once connected (includes/scan.php).
add_action( TVARKOM_OBD_CRON, 'tvarkom_obd_scheduled_scan' );
register_deactivation_hook( __FILE__, 'tvarkom_obd_deactivate' );

<?php
/**
 * Plugin Name:       On-board Diagnostics Scanner for WooCommerce
 * Description:       A free health dashboard for your WooCommerce store, by tvarkom: server, cart and checkout speed, page weight, database, PHP and email security records, scored from 0 to 100, with your score over time.
 * Version:           0.2.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Author:            tvarkom
 * Author URI:        https://tvarkom.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       on-board-diagnostics-scanner-for-woocommerce
 *
 * @package OBDScanner
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TVARKOM_OBD_VERSION', '0.2.0' );
define( 'TVARKOM_OBD_FILE', __FILE__ );
define( 'TVARKOM_OBD_OPTION', 'tvarkom_obd' );
define( 'TVARKOM_OBD_TOKEN', 'tvarkom_obd_verify_token' );
define( 'TVARKOM_OBD_LOCK', 'tvarkom_obd_scan_lock' );
define( 'TVARKOM_OBD_PAGE', 'obd-scanner' ); // admin.php?page=obd-scanner (0.1.0: tvarkom-obd, redirected).

// Overridable in wp-config.php, e.g. for testing against a local gateway.
if ( ! defined( 'TVARKOM_OBD_API_URL' ) ) {
	define( 'TVARKOM_OBD_API_URL', 'https://api.tvarkom.com' );
}

require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/rest.php';
require_once __DIR__ . '/includes/business.php';
require_once __DIR__ . '/includes/funnel.php';
require_once __DIR__ . '/includes/metrics.php';
require_once __DIR__ . '/includes/detailed.php';
require_once __DIR__ . '/includes/site-health.php';
require_once __DIR__ . '/includes/server-checks.php';
require_once __DIR__ . '/includes/wordpress-checks.php';
require_once __DIR__ . '/includes/class-tvarkom-obd-profiler.php';
require_once __DIR__ . '/includes/profile.php';
require_once __DIR__ . '/includes/profile-report.php';
require_once __DIR__ . '/includes/profile-request.php';
require_once __DIR__ . '/includes/scan.php';
require_once __DIR__ . '/includes/dashboard.php';
require_once __DIR__ . '/includes/analysis.php';
require_once __DIR__ . '/includes/charts.php';
require_once __DIR__ . '/includes/pipeline.php';
require_once __DIR__ . '/includes/overview.php';
require_once __DIR__ . '/includes/admin.php';

// Every hook the plugin registers. Nothing contacts tvarkom until an administrator clicks
// "Connect and scan" on the plugin's page (includes/admin.php); scans run only on a click.

// The checkout profiler registers its own hooks, and only on a scan's own checkout request
// (includes/profile.php); every other request returns after one header check.
tvarkom_obd_profile_maybe_start();
add_action( 'admin_menu', 'tvarkom_obd_admin_menu', 99 );
add_action( 'admin_post_tvarkom_obd_scan', 'tvarkom_obd_handle_scan' );
add_action( 'admin_page_access_denied', 'tvarkom_obd_redirect_old_page' ); // WordPress refuses an unknown page before admin_init.
add_action( 'admin_enqueue_scripts', 'tvarkom_obd_admin_assets' );
add_action( 'admin_init', 'tvarkom_obd_privacy_policy_content' );
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'tvarkom_obd_plugin_action_links' );

// Shop activity counters (includes/funnel.php): the table on activation, or on the first admin page
// after an update; daily totals of add-to-cart, checkout loads and orders, kept 35 days.
register_activation_hook( __FILE__, 'tvarkom_obd_funnel_install' );
add_action( 'admin_init', 'tvarkom_obd_funnel_install' );
add_action( 'woocommerce_add_to_cart', 'tvarkom_obd_funnel_add_to_cart' );
add_action( 'template_redirect', 'tvarkom_obd_funnel_checkout_view' );
add_action( 'woocommerce_checkout_order_processed', 'tvarkom_obd_funnel_order' );
add_action( 'woocommerce_store_api_checkout_order_processed', 'tvarkom_obd_funnel_order' );

// Domain check for registration (includes/rest.php).
add_action( 'rest_api_init', 'tvarkom_obd_register_routes' );

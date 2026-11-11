<?php
/**
 * Plugin Name:       Pirkoo
 * Description:       On-board Diagnostics Scanner for WooCommerce
 * Version:           0.2.3
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Author:            tvarkom
 * Plugin URI:        https://pirkoo.com
 * Author URI:        https://tvarkom.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       pirkoo
 *
 * @package Pirkoo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PIRKOO_VERSION', '0.2.3' );
define( 'PIRKOO_FILE', __FILE__ );
define( 'PIRKOO_OPTION', 'pirkoo' );
define( 'PIRKOO_TOKEN', 'pirkoo_verify_token' );
define( 'PIRKOO_LOCK', 'pirkoo_scan_lock' );
define( 'PIRKOO_PAGE', 'pirkoo' ); // The page is admin.php?page=pirkoo.

// Overridable in wp-config.php, e.g. for testing against a local gateway.
if ( ! defined( 'PIRKOO_API_URL' ) ) {
	define( 'PIRKOO_API_URL', 'https://api.pirkoo.com' );
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
require_once __DIR__ . '/includes/class-pirkoo-profiler.php';
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

// Every hook the plugin registers. Nothing contacts Pirkoo until an administrator clicks
// "Connect and scan" on the plugin's page (includes/admin.php); scans run only on a click.

// The checkout profiler registers its own hooks, and only on a scan's own checkout request
// (includes/profile.php); every other request returns after one header check.
pirkoo_profile_maybe_start();
add_action( 'admin_menu', 'pirkoo_admin_menu', 99 );
add_action( 'admin_post_pirkoo_scan', 'pirkoo_handle_scan' );
add_action( 'admin_enqueue_scripts', 'pirkoo_admin_assets' );
add_action( 'admin_init', 'pirkoo_privacy_policy_content' );
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'pirkoo_plugin_action_links' );

// Shop activity counters (includes/funnel.php): the table on activation, or on the first admin page
// after an update; daily totals of add-to-cart, checkout loads and orders, kept 35 days.
register_activation_hook( __FILE__, 'pirkoo_funnel_install' );
add_action( 'admin_init', 'pirkoo_funnel_install' );
add_action( 'woocommerce_add_to_cart', 'pirkoo_funnel_add_to_cart' );
add_action( 'template_redirect', 'pirkoo_funnel_checkout_view' );
add_action( 'woocommerce_checkout_order_processed', 'pirkoo_funnel_order' );
add_action( 'woocommerce_store_api_checkout_order_processed', 'pirkoo_funnel_order' );

// Domain check for registration (includes/rest.php).
add_action( 'rest_api_init', 'pirkoo_register_routes' );

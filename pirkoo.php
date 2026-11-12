<?php
/**
 * Plugin Name:       Pirkoo
 * Description:       On-board Diagnostics Scanner for WooCommerce
 * Version:           0.3.1
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

define( 'PIRKOO_VERSION', '0.3.1' );
define( 'PIRKOO_FILE', __FILE__ );
define( 'PIRKOO_OPTION', 'pirkoo' );
define( 'PIRKOO_LOCK', 'pirkoo_scan_lock' );

// Overridable in wp-config.php, e.g. for testing against a local app.
if ( ! defined( 'PIRKOO_APP_URL' ) ) {
	define( 'PIRKOO_APP_URL', 'https://app.pirkoo.com' );
}

require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/connect.php';
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

// Every hook the plugin registers. The plugin has no screen (0.3.0): it connects the store to
// app.pirkoo.com from a link on the Plugins screen, and answers the app's signed requests
// (includes/connect.php). Nothing is collected or sent before the store is connected.

// The checkout profiler registers its own hooks, and only on a scan's own checkout request
// (includes/profile.php); every other request returns after one header check.
pirkoo_profile_maybe_start();
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'pirkoo_plugin_action_links' );
add_filter( 'allowed_redirect_hosts', 'pirkoo_allowed_redirect_hosts' );
add_action( 'admin_post_pirkoo_connect', 'pirkoo_handle_connect' );
add_action( 'admin_post_pirkoo_disconnect', 'pirkoo_handle_disconnect' );
add_action( 'admin_notices', 'pirkoo_admin_notices' );
add_action( 'admin_init', 'pirkoo_privacy_policy_content' );
add_action( 'admin_init', 'pirkoo_upgrade' );
add_action( 'rest_api_init', 'pirkoo_register_routes' );

// Shop activity counters (includes/funnel.php): the table on activation, or on the first admin page
// after an update; daily totals of add-to-cart, checkout loads and orders, kept 35 days.
register_activation_hook( __FILE__, 'pirkoo_funnel_install' );
add_action( 'admin_init', 'pirkoo_funnel_install' );
add_action( 'woocommerce_add_to_cart', 'pirkoo_funnel_add_to_cart' );
add_action( 'template_redirect', 'pirkoo_funnel_checkout_view' );
add_action( 'woocommerce_checkout_order_processed', 'pirkoo_funnel_order' );
add_action( 'woocommerce_store_api_checkout_order_processed', 'pirkoo_funnel_order' );

<?php
/**
 * The connection with app.pirkoo.com (0.3.0; docs/PROPOSAL-0.3.0.md 3.5 in the app's repository).
 *
 * The plugin has no screen. Under its name on the Plugins screen, an administrator who can manage
 * WooCommerce finds **Connect to Pirkoo** or **Disconnect from Pirkoo**:
 *
 * - Connect makes a one-time code (10 minutes) and sends the browser to app.pirkoo.com, where a
 *   logged-in Owner or Admin confirms. The app then calls POST /wp-json/pirkoo/v1/connect with the
 *   code, server to server, and receives the connection secret this plugin makes.
 * - From then on the app's requests are signed with that secret: POST /pirkoo/v1/collect answers
 *   with the store's data for a scan, POST /pirkoo/v1/disconnect forgets the secret.
 * - Disconnect tells the app (signed, best effort) and forgets the secret.
 *
 * Nothing is collected or sent before the store is connected, and only when the app asks.
 *
 * @package Pirkoo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PIRKOO_CODE_LIFETIME', 10 * MINUTE_IN_SECONDS );
define( 'PIRKOO_CLOCK_SKEW', 5 * MINUTE_IN_SECONDS );

/**
 * The store's origin as app.pirkoo.com keeps it: scheme and host of the home URL, lowercase, no
 * path.
 *
 * @return string
 */
function pirkoo_store_origin() {
	$home = wp_parse_url( home_url() );
	$host = isset( $home['host'] ) ? strtolower( $home['host'] ) : '';
	return 'https://' . $host;
}

/**
 * A random token: 32 bytes, base64url.
 *
 * @return string
 */
function pirkoo_random_token() {
	return rtrim( strtr( base64_encode( random_bytes( 32 ) ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- base64url of random bytes, not obfuscation.
}

/**
 * The plugin's links on the Plugins screen: Connect to Pirkoo, or Open Pirkoo and Disconnect from
 * Pirkoo, for users who can manage WooCommerce.
 *
 * @param array<int|string, string> $links The plugin's action links.
 * @return array<int|string, string>
 */
function pirkoo_plugin_action_links( $links ) {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return $links;
	}
	if ( pirkoo_is_connected() ) {
		array_unshift(
			$links,
			'<a href="' . esc_url( PIRKOO_APP_URL ) . '">' . esc_html__( 'Open Pirkoo', 'pirkoo' ) . '</a>',
			'<a href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=pirkoo_disconnect' ), 'pirkoo_disconnect' ) ) . '">' . esc_html__( 'Disconnect from Pirkoo', 'pirkoo' ) . '</a>'
		);
	} else {
		array_unshift( $links, '<a href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=pirkoo_connect' ), 'pirkoo_connect' ) ) . '">' . esc_html__( 'Connect to Pirkoo', 'pirkoo' ) . '</a>' );
	}
	return $links;
}

/**
 * Lets wp_safe_redirect() send the browser to app.pirkoo.com.
 *
 * @param string[] $hosts Allowed hosts.
 * @return string[]
 */
function pirkoo_allowed_redirect_hosts( $hosts ) {
	$app = wp_parse_url( PIRKOO_APP_URL, PHP_URL_HOST );
	if ( is_string( $app ) ) {
		$hosts[] = $app;
	}
	return $hosts;
}

/**
 * Connect to Pirkoo: a new one-time code, then app.pirkoo.com to confirm.
 *
 * @return void
 */
function pirkoo_handle_connect() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to connect this store.', 'pirkoo' ), 403 );
	}
	check_admin_referer( 'pirkoo_connect' );
	$code = pirkoo_random_token();
	pirkoo_update_settings(
		array(
			'code_hash'    => hash( 'sha256', $code ),
			'code_expires' => time() + PIRKOO_CODE_LIFETIME,
		)
	);
	$url = PIRKOO_APP_URL . '/connect?' . http_build_query(
		array(
			'store' => pirkoo_store_origin(),
			// This plugin's REST address: not <origin>/wp-json/ with plain permalinks or WordPress in a
			// subfolder (0.3.1). The app accepts it only on the store's own host.
			'api'   => untrailingslashit( rest_url( 'pirkoo/v1' ) ),
			'code'  => $code,
		),
		'',
		'&',
		PHP_QUERY_RFC3986
	);
	wp_safe_redirect( $url );
	exit;
}

/**
 * Disconnect from Pirkoo: tells the app (best effort) and forgets the secret. The store's history
 * stays in the app.
 *
 * @return void
 */
function pirkoo_handle_disconnect() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to disconnect this store.', 'pirkoo' ), 403 );
	}
	check_admin_referer( 'pirkoo_disconnect' );
	$secret = pirkoo_settings()['secret'];
	if ( '' !== $secret ) {
		$body = (string) wp_json_encode( array( 'store' => pirkoo_store_origin() ) );
		$path = '/api/stores/disconnect';
		wp_remote_post(
			PIRKOO_APP_URL . $path,
			array(
				'timeout' => 10,
				'headers' => array_merge( array( 'Content-Type' => 'application/json' ), pirkoo_signature_headers( $secret, 'POST', $path, $body ) ),
				'body'    => $body,
			)
		);
	}
	pirkoo_forget_connection();
	wp_safe_redirect( add_query_arg( 'pirkoo', 'disconnected', admin_url( 'plugins.php' ) ) );
	exit;
}

/**
 * Forgets the connection: the secret and any code in progress.
 *
 * @return void
 */
function pirkoo_forget_connection() {
	pirkoo_update_settings(
		array(
			'secret'       => '',
			'connected_at' => 0,
			'code_hash'    => '',
			'code_expires' => 0,
		)
	);
}

/**
 * The notice on the Plugins screen after disconnecting.
 *
 * @return void
 */
function pirkoo_admin_notices() {
	$screen = get_current_screen();
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only chooses a fixed notice to show.
	if ( ! $screen || 'plugins' !== $screen->id || ! isset( $_GET['pirkoo'] ) || 'disconnected' !== $_GET['pirkoo'] ) {
		return;
	}
	echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'This store is disconnected from Pirkoo. Its history stays in the app; connect it again from here at any time.', 'pirkoo' ) . '</p></div>';
}

/**
 * The signature headers of a request signed with the connection secret: the hex HMAC-SHA256 of
 * the timestamp, the method, the URL path and the sha256 of the body, one per line.
 *
 * @param string   $secret The connection secret.
 * @param string   $method HTTP method.
 * @param string   $path   URL path, e.g. /wp-json/pirkoo/v1/collect.
 * @param string   $body   The request body.
 * @param int|null $time   Unix seconds (now when null).
 * @return array{X-Pirkoo-Timestamp: string, X-Pirkoo-Signature: string}
 */
function pirkoo_signature_headers( $secret, $method, $path, $body, $time = null ) {
	$time = null === $time ? time() : (int) $time;
	return array(
		'X-Pirkoo-Timestamp' => (string) $time,
		'X-Pirkoo-Signature' => pirkoo_signature( $secret, $time, $method, $path, $body ),
	);
}

/**
 * The signature itself (see pirkoo_signature_headers()).
 *
 * @param string $secret The connection secret.
 * @param int    $time   Unix seconds.
 * @param string $method HTTP method.
 * @param string $path   URL path.
 * @param string $body   The request body.
 * @return string
 */
function pirkoo_signature( $secret, $time, $method, $path, $body ) {
	return hash_hmac( 'sha256', $time . "\n" . $method . "\n" . $path . "\n" . hash( 'sha256', $body ), $secret );
}

/**
 * The plugin's REST routes.
 *
 * @return void
 */
function pirkoo_register_routes() {
	register_rest_route(
		'pirkoo/v1',
		'/connect',
		array(
			'methods'             => 'POST',
			'permission_callback' => '__return_true', // The one-time code is checked in the callback.
			'callback'            => 'pirkoo_rest_connect',
		)
	);
	foreach ( array(
		'/collect'    => 'pirkoo_rest_collect',
		'/disconnect' => 'pirkoo_rest_disconnect',
	) as $route => $callback ) {
		register_rest_route(
			'pirkoo/v1',
			$route,
			array(
				'methods'             => 'POST',
				'permission_callback' => 'pirkoo_rest_signed',
				'callback'            => $callback,
			)
		);
	}
}

/**
 * POST /pirkoo/v1/connect {"code"}: exchanges a valid one-time code for a new connection secret.
 *
 * @param WP_REST_Request $request The request.
 * @return array{secret: string}|WP_Error
 */
function pirkoo_rest_connect( WP_REST_Request $request ) {
	$code     = $request->get_param( 'code' );
	$settings = pirkoo_settings();
	if ( ! is_string( $code ) || '' === $settings['code_hash'] || $settings['code_expires'] < time() || ! hash_equals( $settings['code_hash'], hash( 'sha256', $code ) ) ) {
		return new WP_Error( 'pirkoo_invalid_code', 'Unknown, used or expired connection code.', array( 'status' => 403 ) );
	}
	$secret = pirkoo_random_token();
	pirkoo_update_settings(
		array(
			'secret'       => $secret,
			'connected_at' => time(),
			'code_hash'    => '',
			'code_expires' => 0,
		)
	);
	return array( 'secret' => $secret );
}

/**
 * Permission for the signed routes: a request signed with the connection secret, within 5 minutes
 * of this server's clock, not seen before.
 *
 * @param WP_REST_Request $request The request.
 * @return true|WP_Error
 */
function pirkoo_rest_signed( WP_REST_Request $request ) {
	$secret = pirkoo_settings()['secret'];
	if ( '' === $secret ) {
		return new WP_Error( 'pirkoo_not_connected', 'This store is not connected to Pirkoo.', array( 'status' => 401 ) );
	}
	$time      = $request->get_header( 'x_pirkoo_timestamp' );
	$signature = $request->get_header( 'x_pirkoo_signature' );
	if ( ! is_string( $time ) || ! ctype_digit( $time ) || ! is_string( $signature ) || '' === $signature ) {
		return new WP_Error( 'pirkoo_unsigned', 'The request is not signed.', array( 'status' => 401 ) );
	}
	if ( abs( time() - (int) $time ) > PIRKOO_CLOCK_SKEW ) {
		return new WP_Error( 'pirkoo_clock_skew', 'The signature\'s time is more than 5 minutes off: check this server\'s clock.', array( 'status' => 401 ) );
	}
	// The signed route is always /wp-json/pirkoo/v1/<route>, whatever this site's REST address
	// (plain permalinks, a subfolder, another prefix): both sides sign the same string.
	$path     = '/wp-json' . $request->get_route();
	$expected = pirkoo_signature( $secret, (int) $time, $request->get_method(), $path, $request->get_body() );
	if ( ! hash_equals( $expected, strtolower( $signature ) ) ) {
		return new WP_Error( 'pirkoo_bad_signature', 'Wrong signature.', array( 'status' => 401 ) );
	}
	$seen = 'pirkoo_sig_' . substr( $expected, 0, 40 );
	if ( false !== get_transient( $seen ) ) {
		return new WP_Error( 'pirkoo_replayed', 'Signature already used.', array( 'status' => 401 ) );
	}
	set_transient( $seen, 1, 2 * PIRKOO_CLOCK_SKEW );
	return true;
}

/**
 * POST /pirkoo/v1/collect: the store's data for a scan, collected now (the checkout profile makes
 * it take several seconds). One collection at a time: 409 while another runs.
 *
 * @return array<string, mixed>|WP_Error
 */
function pirkoo_rest_collect() {
	if ( ! pirkoo_lock() ) {
		return new WP_Error( 'pirkoo_busy', 'A collection is already running.', array( 'status' => 409 ) );
	}
	try {
		return pirkoo_collect();
	} finally {
		pirkoo_unlock();
	}
}

/**
 * What a scan needs, as 0.2.x sent it to the gateway, plus what only WordPress knows about the
 * store for the app's pages.
 *
 * @return array<string, mixed>
 */
function pirkoo_collect() {
	return array(
		'website'  => home_url( '/' ),
		'email'    => (string) get_option( 'admin_email' ),
		'pages'    => array(
			'cart'     => wc_get_cart_url(),
			'checkout' => wc_get_checkout_url(),
		),
		'metrics'  => pirkoo_collect_metrics(),
		'detailed' => pirkoo_collect_detailed(),
		'store'    => array(
			'name'             => wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ),
			'timezone'         => wp_timezone_string(),
			'date_format'      => (string) get_option( 'date_format' ),
			'time_format'      => (string) get_option( 'time_format' ),
			'locale'           => get_locale(),
			'payment_methods'  => pirkoo_payment_method_titles(),
			'shipping_methods' => pirkoo_shipping_method_titles(),
		),
	);
}

/**
 * A method title as the store shows it to shoppers: tags stripped, at most 80 characters.
 *
 * @param mixed $title The title.
 * @return string
 */
function pirkoo_method_title( $title ) {
	$title = trim( wp_strip_all_tags( html_entity_decode( (string) $title, ENT_QUOTES, 'UTF-8' ) ) );
	return function_exists( 'mb_substr' ) ? mb_substr( $title, 0, 80 ) : substr( $title, 0, 80 );
}

/**
 * The enabled payment gateways with the store's own titles (0.3.1), for the app's store profile:
 * id and title only, never settings. At most 30.
 *
 * @return array<int, array{id: string, title: string}>
 */
function pirkoo_payment_method_titles() {
	$out = array();
	foreach ( WC()->payment_gateways()->payment_gateways() as $gateway ) {
		if ( is_object( $gateway ) && isset( $gateway->enabled, $gateway->id ) && 'yes' === $gateway->enabled && method_exists( $gateway, 'get_title' ) ) {
			$title = pirkoo_method_title( $gateway->get_title() );
			if ( '' !== $title ) {
				$out[] = array(
					'id'    => (string) $gateway->id,
					'title' => $title,
				);
			}
		}
	}
	return array_slice( $out, 0, 30 );
}

/**
 * The shipping methods enabled in any zone with the titles the store gave each (0.3.1), e.g.
 * flat_rate "PAC" and flat_rate "SEDEX": id and title only. Repeated titles once; at most 30.
 *
 * @return array<int, array{id: string, title: string}>
 */
function pirkoo_shipping_method_titles() {
	if ( ! class_exists( 'WC_Shipping_Zones' ) ) {
		return array();
	}
	$zones = array( new WC_Shipping_Zone( 0 ) ); // "Locations not covered by your other zones".
	foreach ( WC_Shipping_Zones::get_zones( 'json' ) as $zone ) {
		$zones[] = new WC_Shipping_Zone( (int) $zone['id'] );
	}
	$out  = array();
	$seen = array();
	foreach ( $zones as $zone ) {
		foreach ( $zone->get_shipping_methods( true ) as $method ) {
			$title = pirkoo_method_title( $method->get_title() );
			if ( '' === $title || isset( $seen[ $method->id . '|' . $title ] ) ) {
				continue;
			}
			$seen[ $method->id . '|' . $title ] = true;
			$out[]                              = array(
				'id'    => (string) $method->id,
				'title' => $title,
			);
		}
	}
	return array_slice( $out, 0, 30 );
}

/**
 * POST /pirkoo/v1/disconnect: the app ended the connection; the secret is forgotten.
 *
 * @return array{status: string}
 */
function pirkoo_rest_disconnect() {
	pirkoo_forget_connection();
	return array( 'status' => 'ok' );
}

/**
 * Takes the lock that lets one collection run at a time. The lock is an option row inserted
 * atomically, as in WordPress core's WP_Upgrader::create_lock(); one older than five minutes was
 * left by a request that died and is taken over.
 *
 * @return bool Whether this request now holds the lock.
 */
function pirkoo_lock() {
	global $wpdb;
	$now = (string) time();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- The atomic insert is the lock; the options API can't refuse a row that exists.
	if ( $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO `$wpdb->options` ( `option_name`, `option_value`, `autoload` ) VALUES ( %s, %s, 'no' )", PIRKOO_LOCK, $now ) ) ) {
		return true;
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Read the row itself: the options cache doesn't know about the insert above.
	$since = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM `$wpdb->options` WHERE option_name = %s", PIRKOO_LOCK ) );
	if ( null === $since || (int) $since > time() - 5 * MINUTE_IN_SECONDS ) {
		return false;
	}
	// Take over a stale lock only if no other request took it over first.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Compare-and-set on the lock row.
	return 1 === $wpdb->query( $wpdb->prepare( "UPDATE `$wpdb->options` SET option_value = %s WHERE option_name = %s AND option_value = %s", $now, PIRKOO_LOCK, $since ) );
}

/**
 * Releases the lock taken by pirkoo_lock().
 *
 * @return void
 */
function pirkoo_unlock() {
	delete_option( PIRKOO_LOCK );
}

/**
 * Suggests text for the site's privacy policy (Settings → Privacy → Policy Guide).
 *
 * @return void
 */
function pirkoo_privacy_policy_content() {
	if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
		return;
	}
	$content  = '<p>' . esc_html__( 'This site uses the Pirkoo plugin (On-board Diagnostics Scanner for WooCommerce), by tvarkom (MB TVARKOM SOFTWARE, Lithuania). After an administrator connects the store to Pirkoo, each time Pirkoo scans the store its address, its cart and checkout page addresses and the site\'s admin email are sent to tvarkom, with aggregate store data, such as software versions, database sizes, background task counts, order counts, the 30-day gross sales and average order value, new and returning customer counts, daily shop activity totals and order source counts, a profile of one checkout page load, and the store\'s name, timezone and date format. Customers\' names, emails and addresses are not sent. tvarkom keeps each scan\'s results and analysis linked to the store and shows them to the store\'s team at app.pirkoo.com.', 'pirkoo' ) . '</p>';
	$content .= '<p><a href="' . esc_url( 'https://pirkoo.com/privacy/' ) . '">' . esc_html__( 'Pirkoo Privacy Policy', 'pirkoo' ) . '</a></p>';
	wp_add_privacy_policy_content( 'Pirkoo', wp_kses_post( $content ) );
}

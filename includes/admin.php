<?php
/**
 * The admin page (WooCommerce → OBD Scanner): before connecting, what the plugin does and sends,
 * and the "Connect and scan" button; after, the dashboard or the scan in progress.
 *
 * @package OBDScanner
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds the page under the WooCommerce menu.
 *
 * @return void
 */
function tvarkom_obd_admin_menu() {
	add_submenu_page(
		'woocommerce',
		__( 'On-board Diagnostics', 'on-board-diagnostics-scanner-for-woocommerce' ),
		__( 'OBD Scanner', 'on-board-diagnostics-scanner-for-woocommerce' ),
		'manage_woocommerce',
		'tvarkom-obd',
		'tvarkom_obd_render_page'
	);
}

/**
 * The page's URL.
 *
 * @return string
 */
function tvarkom_obd_page_url() {
	return admin_url( 'admin.php?page=tvarkom-obd' );
}

/**
 * The URL of one of the legal texts that ship with the plugin (legal/), so the version the
 * administrator agrees to is the one installed. The privacy policy is also at tvarkom.com/privacy/.
 *
 * @param string $file File name in legal/: terms-of-use.txt or privacy-policy.txt.
 * @return string
 */
function tvarkom_obd_legal_url( $file ) {
	return plugins_url( 'legal/' . $file, TVARKOM_OBD_FILE );
}

/**
 * A "Dashboard" link on the Plugins screen.
 *
 * @param array<int|string, string> $links The plugin's action links.
 * @return array<int|string, string>
 */
function tvarkom_obd_plugin_action_links( $links ) {
	if ( current_user_can( 'manage_woocommerce' ) ) {
		array_unshift( $links, '<a href="' . esc_url( tvarkom_obd_page_url() ) . '">' . esc_html__( 'Dashboard', 'on-board-diagnostics-scanner-for-woocommerce' ) . '</a>' );
	}
	return $links;
}

/**
 * Notice for each result code from the gateway or this plugin.
 *
 * @return array<string, array{0: string, 1: string}> Code => [notice type, message].
 */
function tvarkom_obd_messages() {
	$try_later = __( 'Try again later. If it keeps happening, email support@tvarkom.com.', 'on-board-diagnostics-scanner-for-woocommerce' );
	return array(
		'queued'                 => array( 'success', __( 'Scan requested. Your store is being scanned now; the results appear here in a few minutes.', 'on-board-diagnostics-scanner-for-woocommerce' ) ),
		'too_soon'               => array( 'info', __( 'Your store was scanned in the last 7 days. You can scan it once a week: the date of your next scan is shown below.', 'on-board-diagnostics-scanner-for-woocommerce' ) ),
		'scan_running'           => array( 'info', __( 'A scan request for this store is already being sent. Reload this page in a minute.', 'on-board-diagnostics-scanner-for-woocommerce' ) ),
		'invalid_website'        => array( 'error', __( 'This store can\'t be scanned: its address must be a public https domain, not localhost or an IP address.', 'on-board-diagnostics-scanner-for-woocommerce' ) ),
		'invalid_verify_url'     => array( 'error', __( 'This store can\'t be connected: its REST API is on a different domain than the store.', 'on-board-diagnostics-scanner-for-woocommerce' ) ),
		'invalid_email'          => array( 'error', __( 'tvarkom couldn\'t accept this site\'s admin email address (Settings → General). Use an address you read, then connect again.', 'on-board-diagnostics-scanner-for-woocommerce' ) ),
		'verification_failed'    => array( 'error', __( 'We couldn\'t confirm this domain. tvarkom needs to reach /wp-json/tvarkom-obd/v1/verify on your store: check that a security plugin or firewall isn\'t blocking the WordPress REST API, then connect again.', 'on-board-diagnostics-scanner-for-woocommerce' ) ),
		'registration_not_found' => array( 'error', __( 'The connection timed out. Click Connect and scan again.', 'on-board-diagnostics-scanner-for-woocommerce' ) ),
		'invalid_key'            => array( 'error', __( 'This store\'s connection to tvarkom was replaced or revoked.', 'on-board-diagnostics-scanner-for-woocommerce' ) ),
		'website_not_allowed'    => array( 'error', __( 'This store\'s address changed since it was connected.', 'on-board-diagnostics-scanner-for-woocommerce' ) ),
		'client_disabled'        => array( 'error', __( 'Scans are paused for this store. Email support@tvarkom.com.', 'on-board-diagnostics-scanner-for-woocommerce' ) ),
		'rate_limited'           => array( 'error', __( 'Too many scans or connections for this store today.', 'on-board-diagnostics-scanner-for-woocommerce' ) . ' ' . $try_later ),
		'not_woocommerce'        => array( 'error', __( 'Our scanner couldn\'t find WooCommerce on your public homepage. Check that your store isn\'t in maintenance mode or behind a password.', 'on-board-diagnostics-scanner-for-woocommerce' ) ),
		'busy'                   => array( 'error', __( 'The scanner is busy right now.', 'on-board-diagnostics-scanner-for-woocommerce' ) . ' ' . $try_later ),
		'network'                => array( 'error', __( 'Couldn\'t reach tvarkom. Check that your server can make outgoing HTTPS requests.', 'on-board-diagnostics-scanner-for-woocommerce' ) . ' ' . $try_later ),
		'server'                 => array( 'error', __( 'Something went wrong on our side.', 'on-board-diagnostics-scanner-for-woocommerce' ) . ' ' . $try_later ),
	);
}

/**
 * Renders the page.
 *
 * @return void
 */
function tvarkom_obd_render_page() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}
	$messages = tvarkom_obd_messages();
	// Read-only display: the result code set by tvarkom_obd_handle_scan(), validated against the known codes.
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$notice = isset( $_GET['obd_result'] ) ? sanitize_key( wp_unslash( $_GET['obd_result'] ) ) : '';
	if ( '' !== $notice && ! isset( $messages[ $notice ] ) ) {
		$notice = 'server'; // A code this version has no text for still says the request didn't go through.
	}
	?>
	<div class="wrap tobd">
		<hr class="wp-header-end">
		<header class="tobd__header">
			<div>
				<h1 class="tobd__title"><?php esc_html_e( 'On-board Diagnostics', 'on-board-diagnostics-scanner-for-woocommerce' ); ?></h1>
				<p class="tobd__store"><?php echo esc_html( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ); ?></p>
			</div>
			<p class="tobd__brand"><?php esc_html_e( 'by tvarkom', 'on-board-diagnostics-scanner-for-woocommerce' ); ?></p>
		</header>
		<?php
		if ( isset( $messages[ $notice ] ) ) {
			tvarkom_obd_render_notice( $messages[ $notice ][0], $messages[ $notice ][1] );
		}
		if ( tvarkom_obd_is_connected() ) {
			tvarkom_obd_render_connected();
		} else {
			tvarkom_obd_render_connect( false );
		}
		?>
		<p class="tobd__footer"><?php esc_html_e( 'Help: support@tvarkom.com · Privacy requests: privacy@tvarkom.com', 'on-board-diagnostics-scanner-for-woocommerce' ); ?></p>
	</div>
	<?php
}

/**
 * One notice, kept inside the page.
 *
 * @param string $type success, info, warning or error.
 * @param string $text The message.
 * @return void
 */
function tvarkom_obd_render_notice( $type, $text ) {
	echo '<div class="notice notice-' . esc_attr( $type ) . ' inline tobd__notice"><p>' . esc_html( $text ) . '</p></div>';
}

/**
 * The page once connected: the results from tvarkom, or what's happening, and the scan button.
 *
 * @return void
 */
function tvarkom_obd_render_connected() {
	$results = tvarkom_obd_fetch_results();

	if ( is_string( $results ) ) {
		if ( 'invalid_key' === $results || 'website_not_allowed' === $results ) {
			// The next scan registers again; the admin can do it now.
			$messages = tvarkom_obd_messages();
			tvarkom_obd_render_notice( 'warning', $messages[ $results ][1] . ' ' . __( 'Connect again to keep your results up to date.', 'on-board-diagnostics-scanner-for-woocommerce' ) );
			tvarkom_obd_render_connect( true );
			return;
		}
		tvarkom_obd_render_notice( 'error', __( 'Your results couldn\'t be loaded from tvarkom right now. Reload this page in a few minutes.', 'on-board-diagnostics-scanner-for-woocommerce' ) );
		return;
	}

	if ( null === $results['latest'] ) {
		tvarkom_obd_render_waiting( $results['status'], $results['next_scan_at'] );
		return;
	}
	if ( 'scanning' === $results['status'] ) {
		echo '<div class="tobd__live" data-tobd-refresh="1">';
		tvarkom_obd_render_notice( 'info', __( 'A new scan is running. This page updates itself when it\'s done.', 'on-board-diagnostics-scanner-for-woocommerce' ) );
		echo '</div>';
	} else {
		if ( 'failed' === $results['status'] ) {
			tvarkom_obd_render_notice( 'warning', __( 'The latest scan couldn\'t be completed, so these are the results of the one before. A failed scan doesn\'t count: you can scan again now.', 'on-board-diagnostics-scanner-for-woocommerce' ) );
		}
		tvarkom_obd_render_scan_button( $results['next_scan_at'] );
	}
	tvarkom_obd_render_dashboard( $results );
}

/**
 * Before the first results: the scan in progress (the page reloads itself), or a failure or the
 * last result code, with the scan button.
 *
 * @param string $status  The gateway's status: none, scanning or failed.
 * @param int    $allowed When tvarkom allows the next scan (Unix seconds, 0: now).
 * @return void
 */
function tvarkom_obd_render_waiting( $status, $allowed ) {
	$last     = tvarkom_obd_settings()['last'];
	$code     = isset( $last['code'] ) ? (string) $last['code'] : '';
	$messages = tvarkom_obd_messages();

	if ( 'scanning' === $status || ( 'none' === $status && 'queued' === $code ) ) {
		?>
		<section class="tobd__panel tobd__waiting" data-tobd-refresh="1" aria-live="polite">
			<span class="tobd__spinner" aria-hidden="true"></span>
			<h2 class="tobd__h2"><?php esc_html_e( 'Scanning your store', 'on-board-diagnostics-scanner-for-woocommerce' ); ?></h2>
			<p><?php esc_html_e( 'Our scanner is loading your homepage, cart and checkout like a shopper would. This usually takes 2 to 5 minutes. This page updates itself; you can also leave and come back later.', 'on-board-diagnostics-scanner-for-woocommerce' ); ?></p>
		</section>
		<?php
		return;
	}

	if ( 'failed' === $status ) {
		$text = __( 'The scan couldn\'t be completed. This happens when a firewall or security plugin blocks our scanner, or the store doesn\'t answer. A failed scan doesn\'t count: you can scan again now.', 'on-board-diagnostics-scanner-for-woocommerce' );
	} elseif ( isset( $messages[ $code ] ) && 'error' === $messages[ $code ][0] ) {
		$text = $messages[ $code ][1];
	} else {
		$text = __( 'Scan your store to see its results here.', 'on-board-diagnostics-scanner-for-woocommerce' );
	}
	?>
	<section class="tobd__panel tobd__waiting">
		<h2 class="tobd__h2"><?php esc_html_e( 'No results yet', 'on-board-diagnostics-scanner-for-woocommerce' ); ?></h2>
		<p><?php echo esc_html( $text ); ?></p>
	</section>
	<?php
	tvarkom_obd_render_scan_button( $allowed );
}

/**
 * The scan button: enabled when tvarkom allows a scan, otherwise disabled with the date of the
 * next one (one scan a week).
 *
 * @param int $allowed When tvarkom allows the next scan (Unix seconds, 0: now).
 * @return void
 */
function tvarkom_obd_render_scan_button( $allowed ) {
	$wait = $allowed > time();
	?>
	<form class="tobd__scanbar tobd-scan-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="tvarkom_obd_scan">
		<?php wp_nonce_field( 'tvarkom_obd_scan' ); ?>
		<button type="submit" class="tobd__btn" <?php disabled( $wait ); ?>><?php esc_html_e( 'Scan now', 'on-board-diagnostics-scanner-for-woocommerce' ); ?></button>
		<p class="tobd__meta">
			<?php
			echo esc_html(
				$wait
					/* translators: %s: date and time. */
					? sprintf( __( 'You can scan your store once a week. Your next scan is available on %s.', 'on-board-diagnostics-scanner-for-woocommerce' ), tvarkom_obd_datetime( $allowed ) )
					: __( 'You can scan your store once a week.', 'on-board-diagnostics-scanner-for-woocommerce' )
			);
			?>
		</p>
	</form>
	<?php
}

/**
 * Before connecting (or to reconnect): what the plugin does, what it sends, the consent line and
 * the button. Nothing is sent before the click.
 *
 * @param bool $reconnect Whether the store was connected before.
 * @return void
 */
function tvarkom_obd_render_connect( $reconnect ) {
	$checks = array(
		__( 'Server response of your homepage, cart and checkout', 'on-board-diagnostics-scanner-for-woocommerce' ),
		__( 'Homepage weight, files and browser caching', 'on-board-diagnostics-scanner-for-woocommerce' ),
		__( 'Autoloaded options, PHP and object cache', 'on-board-diagnostics-scanner-for-woocommerce' ),
		__( 'WooCommerce background tasks', 'on-board-diagnostics-scanner-for-woocommerce' ),
		__( 'HTTPS certificate, SPF and DMARC records', 'on-board-diagnostics-scanner-for-woocommerce' ),
		__( 'A score from 0 to 100, and how it changes from scan to scan', 'on-board-diagnostics-scanner-for-woocommerce' ),
	);
	$link   = '<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>';
	?>
	<section class="tobd__panel tobd__connect" aria-labelledby="tobd-connect-title">
		<?php if ( ! $reconnect ) : ?>
			<h2 class="tobd__h2 tobd__lead-title" id="tobd-connect-title"><?php esc_html_e( 'See how healthy your store is', 'on-board-diagnostics-scanner-for-woocommerce' ); ?></h2>
			<p class="tobd__lead"><?php esc_html_e( 'On-board Diagnostics connects your store to tvarkom\'s free scanner. It measures your store from outside, like a shopper\'s browser, adds a few figures from inside WordPress, and shows the results here: a score, where points were lost, and how it changes over time.', 'on-board-diagnostics-scanner-for-woocommerce' ); ?></p>
			<ul class="tobd__checks">
				<?php foreach ( $checks as $check ) : ?>
					<li><?php echo esc_html( $check ); ?></li>
				<?php endforeach; ?>
			</ul>
		<?php else : ?>
			<h2 class="tobd__h2" id="tobd-connect-title"><?php esc_html_e( 'Connect again', 'on-board-diagnostics-scanner-for-woocommerce' ); ?></h2>
		<?php endif; ?>

		<h3 class="tobd__h3"><?php esc_html_e( 'What is sent to tvarkom', 'on-board-diagnostics-scanner-for-woocommerce' ); ?></h3>
		<dl class="tobd__sends">
			<div>
				<dt><?php esc_html_e( 'To connect', 'on-board-diagnostics-scanner-for-woocommerce' ); ?></dt>
				<dd><?php esc_html_e( 'Your store\'s address and this site\'s admin email, the account\'s contact address (no reports or marketing are sent to it). tvarkom then reads a one-time code from your store to confirm the request came from it.', 'on-board-diagnostics-scanner-for-woocommerce' ); ?></dd>
			</div>
			<div>
				<dt><?php esc_html_e( 'With each scan you request', 'on-board-diagnostics-scanner-for-woocommerce' ); ?></dt>
				<dd><?php esc_html_e( 'Your store, cart and checkout addresses, the admin email, and these store figures: PHP version, autoloaded options size, number of active plugins, object cache type, and counts of pending, overdue and failed background tasks and how late WP-Cron is. tvarkom\'s scanner then loads your public pages like a visitor.', 'on-board-diagnostics-scanner-for-woocommerce' ); ?></dd>
			</div>
			<div class="tobd__never">
				<dt><?php esc_html_e( 'Never sent', 'on-board-diagnostics-scanner-for-woocommerce' ); ?></dt>
				<dd><?php esc_html_e( 'Customers, orders, sales, products, option contents and task names.', 'on-board-diagnostics-scanner-for-woocommerce' ); ?></dd>
			</div>
		</dl>
		<p class="tobd__meta"><?php esc_html_e( 'tvarkom keeps each scan\'s results linked to your store for 12 months, to show them here and compare scans. Nothing on your store changes, and nothing is sent unless you click a scan button.', 'on-board-diagnostics-scanner-for-woocommerce' ); ?></p>

		<form id="tobd-connect-form" class="tobd-scan-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="tvarkom_obd_scan">
			<?php wp_nonce_field( 'tvarkom_obd_scan' ); ?>
			<p class="tobd__consent">
				<?php
				printf(
					/* translators: 1: link to the Terms of Use, 2: link to the Privacy Policy. */
					esc_html__( 'By clicking “Connect and scan”, you agree to send this data to tvarkom now and each time you click Scan now, and accept tvarkom\'s %1$s and %2$s.', 'on-board-diagnostics-scanner-for-woocommerce' ),
					sprintf( $link, esc_url( tvarkom_obd_legal_url( 'terms-of-use.txt' ) ), esc_html__( 'Terms of Use', 'on-board-diagnostics-scanner-for-woocommerce' ) ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
					sprintf( $link, esc_url( tvarkom_obd_legal_url( 'privacy-policy.txt' ) ), esc_html__( 'Privacy Policy', 'on-board-diagnostics-scanner-for-woocommerce' ) ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
				);
				?>
			</p>
			<button type="submit" class="tobd__btn"><?php esc_html_e( 'Connect and scan', 'on-board-diagnostics-scanner-for-woocommerce' ); ?></button>
		</form>
	</section>
	<?php
}

/**
 * The "Connect and scan" and "Scan now" buttons: the first click records the administrator's
 * consent and registers the store; each click requests one scan. If the first registration
 * fails, the store stays unconnected and the page says why.
 *
 * @return void
 */
function tvarkom_obd_handle_scan() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( esc_html__( 'You are not allowed to do this.', 'on-board-diagnostics-scanner-for-woocommerce' ), 403 );
	}
	check_admin_referer( 'tvarkom_obd_scan' );

	// Registration waits for tvarkom to fetch this site; the scan request for a quick platform check.
	wc_set_time_limit( 120 );
	$first = ! tvarkom_obd_is_connected();
	if ( $first ) {
		tvarkom_obd_update_settings( array( 'connected_at' => time() ) );
	}
	$code = tvarkom_obd_scan();
	if ( $first && '' === tvarkom_obd_settings()['api_key'] ) {
		// Not registered: nothing will be sent until the next click.
		tvarkom_obd_update_settings( array( 'connected_at' => 0 ) );
	}
	wp_safe_redirect( add_query_arg( 'obd_result', $code, tvarkom_obd_page_url() ) );
	exit;
}

/**
 * On the plugin page: its stylesheet, and a small script that reloads the page every 20 seconds
 * (for up to 10 minutes) while a scan runs, and marks the button busy after the click.
 *
 * @param string $hook_suffix Current admin page.
 * @return void
 */
function tvarkom_obd_admin_assets( $hook_suffix ) {
	if ( 'woocommerce_page_tvarkom-obd' !== $hook_suffix ) {
		return;
	}
	wp_enqueue_style( 'tvarkom-obd-admin', plugins_url( 'assets/admin.css', TVARKOM_OBD_FILE ), array(), TVARKOM_OBD_VERSION );
	wp_enqueue_script( 'tvarkom-obd-admin', plugins_url( 'assets/admin.js', TVARKOM_OBD_FILE ), array(), TVARKOM_OBD_VERSION, true );
	wp_localize_script(
		'tvarkom-obd-admin',
		'tvarkomObd',
		array( 'busy' => __( 'Requesting the scan… this can take up to a minute', 'on-board-diagnostics-scanner-for-woocommerce' ) )
	);
}

/**
 * Suggests text for the site's privacy policy (Settings → Privacy → Policy Guide).
 *
 * @return void
 */
function tvarkom_obd_privacy_policy_content() {
	if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
		return;
	}
	$content  = '<p>' . esc_html__( 'This site uses the On-board Diagnostics Scanner plugin, by tvarkom (MB TVARKOM SOFTWARE, Lithuania). When an administrator requests a scan, the store\'s address, its cart and checkout page addresses, the site\'s admin email and aggregate technical figures (PHP version, autoloaded options size, number of active plugins, object cache type, background task counts and WP-Cron delay) are sent to tvarkom each time an administrator requests a scan (at most once a week), and tvarkom\'s scanner loads the store\'s public pages. No customer or order data is sent. tvarkom keeps each scan\'s results linked to the store for 12 months.', 'on-board-diagnostics-scanner-for-woocommerce' ) . '</p>';
	$content .= '<p><a href="' . esc_url( 'https://tvarkom.com/privacy/' ) . '">' . esc_html__( 'tvarkom Privacy Policy', 'on-board-diagnostics-scanner-for-woocommerce' ) . '</a></p>';
	wp_add_privacy_policy_content( 'On-board Diagnostics Scanner', wp_kses_post( $content ) );
}

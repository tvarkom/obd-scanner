<?php
/**
 * The admin page (WooCommerce → Store Scanner): before connecting, what the plugin does and sends,
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
		__( 'On-board Diagnostics', 'tvarkom-helper' ),
		__( 'Store Scanner', 'tvarkom-helper' ),
		'manage_woocommerce',
		TVARKOM_OBD_PAGE,
		'tvarkom_obd_render_page'
	);
}

/**
 * The page's URL.
 *
 * @return string
 */
function tvarkom_obd_page_url() {
	return admin_url( 'admin.php?page=' . TVARKOM_OBD_PAGE );
}

/**
 * Sends the page's 0.1.0 address (admin.php?page=tvarkom-obd) to the current one, so bookmarks
 * and old links keep working.
 *
 * @return void
 */
function tvarkom_obd_redirect_old_page() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only compares the page name; nothing is changed.
	if ( isset( $_GET['page'] ) && 'tvarkom-obd' === $_GET['page'] ) {
		wp_safe_redirect( tvarkom_obd_page_url() );
		exit;
	}
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
		array_unshift( $links, '<a href="' . esc_url( tvarkom_obd_page_url() ) . '">' . esc_html__( 'Dashboard', 'tvarkom-helper' ) . '</a>' );
	}
	return $links;
}

/**
 * Notice for each result code from the gateway or this plugin.
 *
 * @return array<string, array{0: string, 1: string}> Code => [notice type, message].
 */
function tvarkom_obd_messages() {
	$try_later = __( 'Try again later. If it keeps happening, email support@tvarkom.com.', 'tvarkom-helper' );
	return array(
		'queued'                 => array( 'success', __( 'Scan requested. Your store is being scanned now; the results appear here in a few minutes.', 'tvarkom-helper' ) ),
		'too_soon'               => array( 'info', __( 'Your store was scanned in the last 7 days. You can scan it once a week: the date of your next scan is shown below.', 'tvarkom-helper' ) ),
		'scan_running'           => array( 'info', __( 'A scan request for this store is already being sent. Reload this page in a minute.', 'tvarkom-helper' ) ),
		'invalid_website'        => array( 'error', __( 'This store can\'t be scanned: its address must be a public https domain, not localhost or an IP address.', 'tvarkom-helper' ) ),
		'invalid_verify_url'     => array( 'error', __( 'This store can\'t be connected: its REST API is on a different domain than the store.', 'tvarkom-helper' ) ),
		'invalid_email'          => array( 'error', __( 'tvarkom couldn\'t accept this site\'s admin email address (Settings → General). Use an address you read, then connect again.', 'tvarkom-helper' ) ),
		'verification_failed'    => array( 'error', __( 'We couldn\'t confirm this domain. tvarkom needs to reach /wp-json/tvarkom-obd/v1/verify on your store: check that a security plugin or firewall isn\'t blocking the WordPress REST API, then connect again.', 'tvarkom-helper' ) ),
		'registration_not_found' => array( 'error', __( 'The connection timed out. Click Connect and scan again.', 'tvarkom-helper' ) ),
		'invalid_key'            => array( 'error', __( 'This store\'s connection to tvarkom was replaced or revoked.', 'tvarkom-helper' ) ),
		'website_not_allowed'    => array( 'error', __( 'This store\'s address changed since it was connected.', 'tvarkom-helper' ) ),
		'client_disabled'        => array( 'error', __( 'Scans are paused for this store. Email support@tvarkom.com.', 'tvarkom-helper' ) ),
		'rate_limited'           => array( 'error', __( 'Too many scans or connections for this store today.', 'tvarkom-helper' ) . ' ' . $try_later ),
		'not_woocommerce'        => array( 'error', __( 'Our scanner couldn\'t find WooCommerce on your public homepage. Check that your store isn\'t in maintenance mode or behind a password.', 'tvarkom-helper' ) ),
		'busy'                   => array( 'error', __( 'The scanner is busy right now.', 'tvarkom-helper' ) . ' ' . $try_later ),
		'network'                => array( 'error', __( 'Couldn\'t reach tvarkom. Check that your server can make outgoing HTTPS requests.', 'tvarkom-helper' ) . ' ' . $try_later ),
		'server'                 => array( 'error', __( 'Something went wrong on our side.', 'tvarkom-helper' ) . ' ' . $try_later ),
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
			<h1 class="tobd__title"><?php esc_html_e( 'On-board Diagnostics Scanner', 'tvarkom-helper' ); ?></h1>
			<p class="tobd__store"><?php echo esc_html( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ); ?></p>
		</header>
		<?php
		$connected = tvarkom_obd_is_connected();
		// Once connected, a requested scan shows as the scan's own status, not as a second notice.
		if ( isset( $messages[ $notice ] ) && ! ( $connected && 'queued' === $notice ) ) {
			tvarkom_obd_render_notice( $messages[ $notice ][0], $messages[ $notice ][1] );
		}
		if ( $connected ) {
			tvarkom_obd_render_connected( 'queued' === $notice );
		} else {
			tvarkom_obd_render_connect( false );
		}
		?>
		<p class="tobd__footer"><?php esc_html_e( 'Help: support@tvarkom.com · Privacy requests: privacy@tvarkom.com', 'tvarkom-helper' ); ?></p>
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
 * @param bool $requested Whether a scan was requested just now (tvarkom accepted it).
 * @return void
 */
function tvarkom_obd_render_connected( $requested = false ) {
	$results = tvarkom_obd_fetch_results();

	if ( is_string( $results ) ) {
		if ( 'invalid_key' === $results || 'website_not_allowed' === $results ) {
			// The next scan registers again; the admin can do it now.
			$messages = tvarkom_obd_messages();
			tvarkom_obd_render_notice( 'warning', $messages[ $results ][1] . ' ' . __( 'Connect again to keep your results up to date.', 'tvarkom-helper' ) );
			tvarkom_obd_render_connect( true );
			return;
		}
		tvarkom_obd_render_notice( 'error', __( 'Your results couldn\'t be loaded from tvarkom right now. Reload this page in a few minutes.', 'tvarkom-helper' ) );
		return;
	}

	if ( null === $results['latest'] ) {
		tvarkom_obd_render_waiting( $results['status'], $results['next_scan_at'] );
		return;
	}
	if ( 'scanning' === $results['status'] || ( $requested && 'ready' === $results['status'] ) ) {
		tvarkom_obd_render_scan_progress( (int) $results['latest']['completed_at'] );
	} else {
		if ( 'failed' === $results['status'] ) {
			tvarkom_obd_render_notice( 'warning', __( 'The latest scan couldn\'t be completed, so these are the results of the one before. A failed scan doesn\'t count: you can scan again now.', 'tvarkom-helper' ) );
		}
		tvarkom_obd_render_scan_button( $results['next_scan_at'] );
	}
	tvarkom_obd_render_dashboard( $results );
}

/**
 * While a new scan runs over earlier results: one status bar with a spinner, saying which scan the
 * page shows meanwhile. The page reloads itself (assets/admin.js).
 *
 * @param int $shown When the scan on the page completed (Unix seconds).
 * @return void
 */
function tvarkom_obd_render_scan_progress( $shown ) {
	?>
	<div class="tobd__progress" data-tobd-refresh="1" role="status">
		<span class="tobd__spinner" aria-hidden="true"></span>
		<div>
			<p class="tobd__progress-title"><?php esc_html_e( 'Scanning your store…', 'tvarkom-helper' ); ?></p>
			<p>
				<?php
				echo esc_html(
					sprintf(
						/* translators: %s: date and time of the scan shown on the page. */
						__( 'This usually takes 2 to 5 minutes, and this page updates itself when it\'s done. Until then, you\'re seeing your scan from %s.', 'tvarkom-helper' ),
						tvarkom_obd_datetime( $shown )
					)
				);
				?>
			</p>
		</div>
	</div>
	<?php
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
			<h2 class="tobd__h2"><?php esc_html_e( 'Scanning your store', 'tvarkom-helper' ); ?></h2>
			<p><?php esc_html_e( 'Our scanner is loading your homepage, cart and checkout like a shopper would. This usually takes 2 to 5 minutes. This page updates itself; you can also leave and come back later.', 'tvarkom-helper' ); ?></p>
		</section>
		<?php
		return;
	}

	if ( 'failed' === $status ) {
		$text = __( 'The scan couldn\'t be completed. This happens when a firewall or security plugin blocks our scanner, or the store doesn\'t answer. A failed scan doesn\'t count: you can scan again now.', 'tvarkom-helper' );
	} elseif ( isset( $messages[ $code ] ) && 'error' === $messages[ $code ][0] ) {
		$text = $messages[ $code ][1];
	} else {
		$text = __( 'Scan your store to see its results here.', 'tvarkom-helper' );
	}
	?>
	<section class="tobd__panel tobd__waiting">
		<h2 class="tobd__h2"><?php esc_html_e( 'No results yet', 'tvarkom-helper' ); ?></h2>
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
		<button type="submit" class="tobd__btn" <?php disabled( $wait ); ?>><?php esc_html_e( 'Scan now', 'tvarkom-helper' ); ?></button>
		<p class="tobd__meta">
			<?php
			echo esc_html(
				$wait
					/* translators: %s: date and time. */
					? sprintf( __( 'You can scan your store once a week. Your next scan is available on %s.', 'tvarkom-helper' ), tvarkom_obd_datetime( $allowed ) )
					: __( 'You can scan your store once a week.', 'tvarkom-helper' )
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
		__( 'Server response of your homepage, cart and checkout', 'tvarkom-helper' ),
		__( 'Homepage weight, files and browser caching', 'tvarkom-helper' ),
		__( 'Autoloaded options, PHP and object cache', 'tvarkom-helper' ),
		__( 'WooCommerce background tasks', 'tvarkom-helper' ),
		__( 'HTTPS certificate, SPF and DMARC records', 'tvarkom-helper' ),
		__( 'Your checkout profiled: the plugins, hooks and queries that take the time', 'tvarkom-helper' ),
		__( 'Your store\'s profile: sales and checkout as counted, catalog and operations', 'tvarkom-helper' ),
		__( 'A health score out of 100, and how it changes from scan to scan', 'tvarkom-helper' ),
	);
	$link   = '<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>';
	?>
	<section class="tobd__panel tobd__connect" aria-labelledby="tobd-connect-title">
		<?php if ( ! $reconnect ) : ?>
			<h2 class="tobd__h2 tobd__lead-title" id="tobd-connect-title"><?php esc_html_e( 'See how healthy your store is', 'tvarkom-helper' ); ?></h2>
			<p class="tobd__lead"><?php esc_html_e( 'On-board Diagnostics connects your store to tvarkom\'s free scanner. It measures your store from outside, like a shopper\'s browser, adds a few figures from inside WordPress, and shows the results here: a score, where points were lost, and how it changes over time.', 'tvarkom-helper' ); ?></p>
			<ul class="tobd__checks">
				<?php foreach ( $checks as $check ) : ?>
					<li><?php echo esc_html( $check ); ?></li>
				<?php endforeach; ?>
			</ul>
		<?php else : ?>
			<h2 class="tobd__h2" id="tobd-connect-title"><?php esc_html_e( 'Connect again', 'tvarkom-helper' ); ?></h2>
		<?php endif; ?>

		<h3 class="tobd__h3"><?php esc_html_e( 'What is sent to tvarkom', 'tvarkom-helper' ); ?></h3>
		<dl class="tobd__sends">
			<div>
				<dt><?php esc_html_e( 'To connect', 'tvarkom-helper' ); ?></dt>
				<dd><?php esc_html_e( 'Your store\'s address and this site\'s admin email, the account\'s contact address (no reports or marketing are sent to it). tvarkom then reads a one-time code from your store to confirm the request came from it.', 'tvarkom-helper' ); ?></dd>
			</div>
			<div>
				<dt><?php esc_html_e( 'With each scan you request', 'tvarkom-helper' ); ?></dt>
				<dd><?php esc_html_e( 'Your store, cart and checkout addresses and the admin email. tvarkom\'s scanner then loads your public pages like a visitor.', 'tvarkom-helper' ); ?></dd>
			</div>
			<div>
				<dt><?php esc_html_e( 'Store data', 'tvarkom-helper' ); ?></dt>
				<dd><?php esc_html_e( 'PHP, WordPress and WooCommerce versions; database, options table and autoloaded options sizes, with the names and sizes of the largest autoloaded options and of tables left by removed plugins; the number of active plugins and the object cache type; background task and WP-Cron counts, with the names of failed and late tasks (error messages stripped of emails, links, IP addresses and long numbers); PHP settings such as the memory limit; and your monthly order volume as a range, for example 50–200.', 'tvarkom-helper' ); ?></dd>
			</div>
			<div>
				<dt><?php esc_html_e( 'Business context', 'tvarkom-helper' ); ?></dt>
				<dd><?php esc_html_e( 'So the analysis can speak in business terms: country and currency; paid, failed, pending and cancelled orders in the last 30 days; gross sales and average order value in your store\'s currency; how many customers were new or returning, as two counts; product and variation counts and whether stock management is on; the IDs of your payment gateways and shipping methods (never their settings); checkout type, order storage, database and web server, outdated template overrides, pending plugin updates and any backup plugin; the domain your store\'s emails come from (not the address); how many products were added to the cart, checkouts opened and orders placed over the last 30 days, as totals the plugin counts on your store without cookies or anything about the shopper; and how many orders came from each source (search, ads, referrals, direct), from WooCommerce Order Attribution.', 'tvarkom-helper' ); ?></dd>
			</div>
			<div>
				<dt><?php esc_html_e( 'Site checks', 'tvarkom-helper' ); ?></dt>
				<dd><?php esc_html_e( 'Whether a WordPress or theme update is waiting, whether debug output is shown on your pages, whether search engines are asked not to index the store, the site\'s WordPress environment type (production, staging, development or local), how many database tables use the MyISAM engine or a character set other than utf8mb4, how many expired transients are stored, how many administrator accounts exist (a count, no user details), and whether the dashboard file editor is on; the upload limit, missing PHP extensions, free disk space, the server\'s load and available memory; how many plugins or themes WordPress paused, how many fatal errors your error log shows for the last 7 days (a count, never the log), automatic update settings and whether permalinks are plain; the database\'s response time and how many spam comments, auto-drafts and old revisions it holds; how many core files differ from WordPress.org\'s official checksums (the plugin fetches them from api.wordpress.org), wp-config.php\'s file permissions, whether an "admin" username exists, and whether registration is open and with which role. tvarkom\'s scanner also checks whether well-known files such as configuration backups or the debug log can be downloaded from your store.', 'tvarkom-helper' ); ?></dd>
			</div>
			<div>
				<dt><?php esc_html_e( 'A checkout profile', 'tvarkom-helper' ); ?></dt>
				<dd><?php esc_html_e( 'One checkout page load on your server, with one product in a temporary cart that is never saved (nothing is ordered): plugin, theme and hook names with their timings, database query shapes with every value replaced by "?", the host names of outside services called, and object cache hit and miss counts.', 'tvarkom-helper' ); ?></dd>
			</div>
			<div class="tobd__never">
				<dt><?php esc_html_e( 'Never sent', 'tvarkom-helper' ); ?></dt>
				<dd><?php esc_html_e( 'Customer records, order details, option contents and task arguments.', 'tvarkom-helper' ); ?></dd>
			</div>
		</dl>
		<p class="tobd__meta"><?php esc_html_e( 'tvarkom keeps each scan\'s results and analysis, including the business context and sales figures above, linked to your store for 12 months, to show them here and compare scans. Nothing on your store changes, and nothing is sent unless you click a scan button.', 'tvarkom-helper' ); ?></p>

		<form id="tobd-connect-form" class="tobd-scan-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="tvarkom_obd_scan">
			<?php wp_nonce_field( 'tvarkom_obd_scan' ); ?>
			<p class="tobd__consent">
				<?php
				printf(
					/* translators: 1: link to the Terms of Use, 2: link to the Privacy Policy. */
					esc_html__( 'By clicking “Connect and scan”, you agree to send this data to tvarkom now and each time you click Scan now, and accept tvarkom\'s %1$s and %2$s.', 'tvarkom-helper' ),
					sprintf( $link, esc_url( tvarkom_obd_legal_url( 'terms-of-use.txt' ) ), esc_html__( 'Terms of Use', 'tvarkom-helper' ) ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
					sprintf( $link, esc_url( tvarkom_obd_legal_url( 'privacy-policy.txt' ) ), esc_html__( 'Privacy Policy', 'tvarkom-helper' ) ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
				);
				?>
			</p>
			<button type="submit" class="tobd__btn"><?php esc_html_e( 'Connect and scan', 'tvarkom-helper' ); ?></button>
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
		wp_die( esc_html__( 'You are not allowed to do this.', 'tvarkom-helper' ), 403 );
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
	if ( 'woocommerce_page_' . TVARKOM_OBD_PAGE !== $hook_suffix ) {
		return;
	}
	wp_enqueue_style( 'tvarkom-obd-admin', plugins_url( 'assets/admin.css', TVARKOM_OBD_FILE ), array(), tvarkom_obd_asset_version( 'assets/admin.css' ) );
	wp_enqueue_script( 'tvarkom-obd-admin', plugins_url( 'assets/admin.js', TVARKOM_OBD_FILE ), array(), tvarkom_obd_asset_version( 'assets/admin.js' ), true );
	wp_localize_script(
		'tvarkom-obd-admin',
		'tvarkomObd',
		array( 'busy' => __( 'Requesting the scan… this can take up to a minute', 'tvarkom-helper' ) )
	);
}

/**
 * An asset's version for its URL: the plugin's version and the file's modification time, so a
 * browser never keeps an old copy after an update, even one that keeps the version number.
 *
 * @param string $file Path inside the plugin, e.g. assets/admin.css.
 * @return string
 */
function tvarkom_obd_asset_version( $file ) {
	$modified = @filemtime( plugin_dir_path( TVARKOM_OBD_FILE ) . $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- a missing file keeps the plugin's version.
	return false === $modified ? TVARKOM_OBD_VERSION : TVARKOM_OBD_VERSION . '.' . $modified;
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
	$content  = '<p>' . esc_html__( 'This site uses the tvarkom Helper plugin (On-board Diagnostics Scanner), by tvarkom (MB TVARKOM SOFTWARE, Lithuania). When an administrator requests a scan (at most once a week), the store\'s address, its cart and checkout page addresses and the site\'s admin email are sent to tvarkom, with aggregate store data, such as software versions, database sizes, background task counts, order counts, the 30-day gross sales and average order value, new and returning customer counts, daily shop activity totals and order source counts, and a profile of one checkout page load. Customers\' names, emails and addresses are not sent. tvarkom keeps each scan\'s results and analysis, including these business figures, linked to the store for 12 months, and shows them on the plugin\'s page; nothing is emailed.', 'tvarkom-helper' ) . '</p>';
	$content .= '<p><a href="' . esc_url( 'https://tvarkom.com/privacy/' ) . '">' . esc_html__( 'tvarkom Privacy Policy', 'tvarkom-helper' ) . '</a></p>';
	wp_add_privacy_policy_content( 'tvarkom Helper', wp_kses_post( $content ) );
}

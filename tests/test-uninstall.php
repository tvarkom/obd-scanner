<?php
/**
 * Uninstalling removes everything the plugin stored (uninstall.php).
 *
 * @package OBDScanner
 */

/**
 * Uninstall.
 */
class Tvarkom_OBD_Uninstall_Test extends Tvarkom_OBD_Test_Case {

	public function test_uninstall_removes_the_plugin_data() {
		$this->connect_store();
		tvarkom_obd_lock();
		tvarkom_obd_schedule( time() + HOUR_IN_SECONDS );
		set_transient( TVARKOM_OBD_TOKEN, 'tok_1', MINUTE_IN_SECONDS );

		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'obd-scanner/obd-scanner.php' );
		}
		require dirname( __DIR__ ) . '/uninstall.php';

		$this->assertFalse( get_option( TVARKOM_OBD_OPTION ) );
		$this->assertFalse( get_option( TVARKOM_OBD_LOCK ) );
		$this->assertFalse( get_transient( TVARKOM_OBD_TOKEN ) );
		$this->assertFalse( wp_next_scheduled( TVARKOM_OBD_CRON ) );
	}
}

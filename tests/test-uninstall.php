<?php
/**
 * Uninstalling removes everything the plugin stored (uninstall.php).
 *
 * @package Pirkoo
 */

/**
 * Uninstall.
 */
class Pirkoo_Uninstall_Test extends Pirkoo_Test_Case {

	public function test_uninstall_removes_the_plugin_data() {
		$this->connect_store();
		pirkoo_lock();
		set_transient( 'pirkoo_verify_token', 'tok_1', MINUTE_IN_SECONDS ); // 0.2.x's registration token.

		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'pirkoo/pirkoo.php' );
		}
		require dirname( __DIR__ ) . '/uninstall.php';

		$this->assertFalse( get_option( PIRKOO_OPTION ) );
		$this->assertFalse( get_option( PIRKOO_LOCK ) );
		$this->assertFalse( get_transient( 'pirkoo_verify_token' ) );
	}
}

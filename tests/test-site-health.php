<?php
/**
 * Site checks: core files against WordPress.org's checksums.
 *
 * @package OBDScanner
 */

/**
 * Core file checksums.
 */
class Tvarkom_OBD_Site_Health_Test extends Tvarkom_OBD_Test_Case {

	/**
	 * WordPress.org's answer to the checksums request, or null for "unreachable".
	 *
	 * @var array<string, string>|null
	 */
	private $checksums = null;

	public function set_up() {
		parent::set_up();
		add_filter( 'pre_http_request', array( $this, 'fake_wordpress_org' ), 0, 3 );
	}

	/**
	 * Answers api.wordpress.org/core/checksums; leaves every other request to the fake gateway.
	 *
	 * @param false|array|WP_Error $pre  Short-circuit value.
	 * @param array                $args Request arguments.
	 * @param string               $url  Request URL.
	 * @return false|array|WP_Error
	 */
	public function fake_wordpress_org( $pre, $args, $url ) {
		if ( 0 !== strpos( $url, 'https://api.wordpress.org/core/checksums/1.0/' ) ) {
			return $pre;
		}
		if ( null === $this->checksums ) {
			return new WP_Error( 'http_request_failed', 'offline' );
		}
		return array(
			'headers'  => array(),
			'body'     => wp_json_encode( array( 'checksums' => $this->checksums ) ),
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'cookies'  => array(),
			'filename' => null,
		);
	}

	public function test_core_files_are_counted_against_the_checksums() {
		$this->checksums = array(
			'wp-includes/version.php'      => md5_file( ABSPATH . 'wp-includes/version.php' ),
			'wp-includes/functions.php'    => str_repeat( '0', 32 ),
			'wp-includes/not-a-file.php'   => str_repeat( '0', 32 ),
			'wp-content/plugins/hello.php' => str_repeat( '0', 32 ),
		);

		$this->assertSame(
			array(
				'modified' => 1,
				'missing'  => 1,
			),
			tvarkom_obd_core_file_changes(),
			'one modified, one missing; wp-content is not checked'
		);
	}

	public function test_unreachable_checksums_are_not_measured_and_raise_no_warning() {
		$this->assertSame(
			array(
				'modified' => null,
				'missing'  => null,
			),
			tvarkom_obd_core_file_changes()
		);
	}
}

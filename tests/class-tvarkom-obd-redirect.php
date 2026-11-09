<?php
/**
 * Thrown by the tests' wp_redirect filter, so code that redirects and exits can be tested.
 *
 * @package OBDScanner
 */

/**
 * A redirect the code under test made.
 */
class Tvarkom_OBD_Redirect extends Exception {

	/**
	 * Redirect target.
	 *
	 * @var string
	 */
	public $location;

	/**
	 * Constructor.
	 *
	 * @param string $location Redirect target.
	 */
	public function __construct( $location ) {
		parent::__construct( 'Redirect to ' . $location );
		$this->location = $location;
	}
}

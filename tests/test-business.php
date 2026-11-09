<?php
/**
 * The business context sent with an audit (includes/business.php): counts, codes and the 30-day
 * sales totals in the store's currency (schema 3, 7 Nov 2026), never names or addresses.
 *
 * @package OBDScanner
 */

/**
 * Metrics schema 3's business fields.
 */
class Tvarkom_OBD_Business_Test extends Tvarkom_OBD_Test_Case {

	/**
	 * A paid order of $total in the store's currency, created today.
	 *
	 * @param float  $total  Order total.
	 * @param string $status Order status.
	 */
	private function order( $total, $status = 'completed' ) {
		$order = wc_create_order( array( 'status' => $status ) );
		$order->set_currency( get_woocommerce_currency() );
		$order->set_total( $total );
		$order->save();
	}

	public function test_sales_and_orders_in_the_store_currency() {
		update_option( 'woocommerce_currency', 'USD' );
		$this->order( 100 );
		$this->order( 150, 'processing' );
		$this->order( 50, 'on-hold' );
		$this->order( 999, 'pending' ); // Not paid: not sales.
		$this->order( 999, 'failed' );

		$data = tvarkom_obd_collect_business();

		$this->assertSame( 'USD', $data['currency'] );
		$this->assertSame( 3, $data['orders_30d'] );
		$this->assertSame( 1, $data['orders_pending_30d'] );
		$this->assertSame( 1, $data['orders_failed_30d'] );
		$this->assertSame( 0, $data['orders_cancelled_30d'] );
		$this->assertSame( 300.0, $data['sales_30d'] ); // Dollars, as they are: no conversion.
		$this->assertSame( 100.0, $data['aov_30d'] );
		$this->assertArrayNotHasKey( 'sales_30d_range', $data );
	}

	public function test_no_orders_has_no_average() {
		$data = tvarkom_obd_collect_business();
		$this->assertSame( 0.0, $data['sales_30d'] );
		$this->assertNull( $data['aov_30d'] );
	}

	public function test_store_settings() {
		update_option( 'woocommerce_default_country', 'LT' );
		update_option( 'woocommerce_manage_stock', 'yes' );
		$product = new WC_Product_Simple();
		$product->set_status( 'publish' );
		$product->save();

		$data = tvarkom_obd_collect_business();

		$this->assertSame( 'LT', $data['country'] );
		$this->assertTrue( $data['stock_management'] );
		$this->assertSame( 1, $data['products'] );
		$this->assertIsArray( $data['payment_gateways'] );
		$this->assertIsArray( $data['shipping_methods'] );
		$this->assertIsBool( $data['hpos'] );
		$this->assertMatchesRegularExpression( '/^(MySQL|MariaDB) \d+\.\d+$/', (string) $data['database_server'] );
	}

	public function test_checkout_type() {
		$page = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_content' => '<!-- wp:woocommerce/checkout /-->',
			)
		);
		update_option( 'woocommerce_checkout_page_id', $page );
		$this->assertSame( 'block', tvarkom_obd_checkout_type() );
		wp_update_post(
			array(
				'ID'           => $page,
				'post_content' => '[woocommerce_checkout]',
			)
		);
		$this->assertSame( 'classic', tvarkom_obd_checkout_type() );
	}

	public function test_enabled_payment_gateways_only() {
		update_option( 'woocommerce_cod_settings', array( 'enabled' => 'yes' ) );
		update_option( 'woocommerce_bacs_settings', array( 'enabled' => 'no' ) );
		WC()->payment_gateways()->init();
		$ids = tvarkom_obd_payment_gateway_ids();
		$this->assertContains( 'cod', $ids );
		$this->assertNotContains( 'bacs', $ids );
	}

	public function test_backup_plugin() {
		$this->assertNull( tvarkom_obd_backup_plugin() );
		update_option( 'active_plugins', array( 'akismet/akismet.php', 'updraftplus/updraftplus.php' ) );
		$this->assertSame( 'UpdraftPlus', tvarkom_obd_backup_plugin() );
	}

	public function test_web_server_is_the_product_name_only() {
		$_SERVER['SERVER_SOFTWARE'] = 'nginx/1.25.3 (Ubuntu)';
		$this->assertSame( 'nginx', tvarkom_obd_web_server() );
		$_SERVER['SERVER_SOFTWARE'] = '';
		$this->assertNull( tvarkom_obd_web_server() );
	}

	public function test_the_audit_sends_sales_in_the_store_currency() {
		$this->connect_store();
		update_option( 'woocommerce_currency', 'USD' );
		$this->order( 2000 );
		$this->respond( 'POST', '/v1/audits', 202, array( 'status' => 'queued' ) );

		tvarkom_obd_scan();

		$metrics = $this->requests[0]['body']['metrics'];
		$this->assertSame( 3, $metrics['schema'] );
		$this->assertSame( 'USD', $metrics['currency'] );
		// The request body went through JSON: 2000.0 comes back as the number 2000.
		$this->assertEquals( 2000, $metrics['sales_30d'] );
		$this->assertEquals( 2000, $metrics['aov_30d'] );
	}
}

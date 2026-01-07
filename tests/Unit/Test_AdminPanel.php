<?php
/**
 * AdminPanel Test Class
 *
 * @package CLOSE\JumpToCheckout\Tests
 */

namespace CLOSE\JumpToCheckout\Tests;

use CLOSE\JumpToCheckout\Admin\AdminPanel;
use CLOSE\JumpToCheckout\Core\JumpToCheckout;
use CLOSE\JumpToCheckout\Database\Database;
use WP_UnitTestCase;

/**
 * AdminPanel Test Case
 */
class Test_AdminPanel extends WP_UnitTestCase {

	/**
	 * AdminPanel instance
	 *
	 * @var AdminPanel
	 */
	private $admin_panel;

	/**
	 * Setup test
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		// Create database table.
		$db = new Database();
		$db->create_table();

		// Create instance.
		$this->admin_panel = new AdminPanel();
	}

	/**
	 * Tear down test
	 *
	 * @return void
	 */
	public function tearDown(): void {
		parent::tearDown();
	}

	/**
	 * Test sanitize products data with valid JSON
	 *
	 * @return void
	 */
	public function test_sanitize_products_data_valid() {
		$products_json = wp_json_encode(
			array(
				array(
					'product_id' => 1,
					'quantity'   => 2,
				),
				array(
					'product_id' => 2,
					'quantity'   => 1,
				),
			)
		);

		// Use reflection to access private method.
		$reflection = new \ReflectionClass( $this->admin_panel );
		$method     = $reflection->getMethod( 'sanitize_products_data' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->admin_panel, $products_json );

		$this->assertIsArray( $result );
		$this->assertCount( 2, $result );
		$this->assertEquals( 1, $result[0]['product_id'] );
		$this->assertEquals( 2, $result[0]['quantity'] );
		$this->assertEquals( 2, $result[1]['product_id'] );
		$this->assertEquals( 1, $result[1]['quantity'] );
	}

	/**
	 * Test sanitize products data with invalid JSON
	 *
	 * @return void
	 */
	public function test_sanitize_products_data_invalid() {
		$invalid_json = 'not valid json {';

		// Use reflection to access private method.
		$reflection = new \ReflectionClass( $this->admin_panel );
		$method     = $reflection->getMethod( 'sanitize_products_data' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->admin_panel, $invalid_json );

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * Test sanitize products data with empty string
	 *
	 * @return void
	 */
	public function test_sanitize_products_data_empty() {
		// Use reflection to access private method.
		$reflection = new \ReflectionClass( $this->admin_panel );
		$method     = $reflection->getMethod( 'sanitize_products_data' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->admin_panel, '' );

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * Test sanitize products data filters invalid products
	 *
	 * @return void
	 */
	public function test_sanitize_products_data_filters_invalid() {
		$products_json = wp_json_encode(
			array(
				array(
					'product_id' => 1,
					'quantity'   => 2,
				),
				array(
					// Missing product_id.
					'quantity' => 1,
				),
				array(
					'product_id' => 3,
					// Missing quantity.
				),
				array(
					'product_id' => 4,
					'quantity'   => 5,
				),
			)
		);

		// Use reflection to access private method.
		$reflection = new \ReflectionClass( $this->admin_panel );
		$method     = $reflection->getMethod( 'sanitize_products_data' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->admin_panel, $products_json );

		// Should only include valid products (1 and 4).
		$this->assertIsArray( $result );
		$this->assertCount( 2, $result );
		$this->assertEquals( 1, $result[0]['product_id'] );
		$this->assertEquals( 4, $result[1]['product_id'] );
	}

	/**
	 * Test sanitize products data with variation
	 *
	 * @return void
	 */
	public function test_sanitize_products_data_with_variation() {
		$products_json = wp_json_encode(
			array(
				array(
					'product_id'   => 1,
					'variation_id' => 10,
					'quantity'     => 2,
					'variation'    => array(
						'color' => 'red',
						'size'  => 'large',
					),
				),
			)
		);

		// Use reflection to access private method.
		$reflection = new \ReflectionClass( $this->admin_panel );
		$method     = $reflection->getMethod( 'sanitize_products_data' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->admin_panel, $products_json );

		$this->assertIsArray( $result );
		$this->assertCount( 1, $result );
		$this->assertEquals( 1, $result[0]['product_id'] );
		$this->assertEquals( 10, $result[0]['variation_id'] );
		$this->assertEquals( 2, $result[0]['quantity'] );
		$this->assertIsArray( $result[0]['variation'] );
		$this->assertEquals( 'red', $result[0]['variation']['color'] );
		$this->assertEquals( 'large', $result[0]['variation']['size'] );
	}

	/**
	 * Test sanitize products data sanitizes values
	 *
	 * @return void
	 */
	public function test_sanitize_products_data_sanitizes_values() {
		$products_json = wp_json_encode(
			array(
				array(
					'product_id' => '1', // String should be converted to int.
					'quantity'   => '5', // String should be converted to int.
				),
			)
		);

		// Use reflection to access private method.
		$reflection = new \ReflectionClass( $this->admin_panel );
		$method     = $reflection->getMethod( 'sanitize_products_data' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->admin_panel, $products_json );

		$this->assertIsArray( $result );
		$this->assertIsInt( $result[0]['product_id'] );
		$this->assertIsInt( $result[0]['quantity'] );
		$this->assertEquals( 1, $result[0]['product_id'] );
		$this->assertEquals( 5, $result[0]['quantity'] );
	}
}


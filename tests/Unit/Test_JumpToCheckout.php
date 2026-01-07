<?php
/**
 * JumpToCheckout Test Class
 *
 * @package CLOSE\JumpToCheckout\Tests
 */

namespace CLOSE\JumpToCheckout\Tests;

use CLOSE\JumpToCheckout\Core\JumpToCheckout;
use CLOSE\JumpToCheckout\Database\Database;
use WP_UnitTestCase;

/**
 * JumpToCheckout Test Case
 */
class Test_JumpToCheckout extends WP_UnitTestCase {

	/**
	 * JumpToCheckout instance
	 *
	 * @var JumpToCheckout
	 */
	private $jump_to_checkout;

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

		// Create instance (this will register hooks, which is fine for tests).
		$this->jump_to_checkout = new JumpToCheckout();
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
	 * Test generate link with valid data
	 *
	 * @return void
	 */
	public function test_generate_link_valid() {
		$name     = 'Test Campaign Link';
		$products = array(
			array(
				'product_id' => 1,
				'quantity'   => 2,
			),
		);

		$result = $this->jump_to_checkout->generate_link( $name, $products );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'id', $result );
		$this->assertArrayHasKey( 'url', $result );
		$this->assertArrayHasKey( 'token', $result );
		$this->assertIsInt( $result['id'] );
		$this->assertIsString( $result['url'] );
		$this->assertIsString( $result['token'] );
		$this->assertStringContainsString( '/jump-to-checkout/', $result['url'] );
	}

	/**
	 * Test generate link with multiple products
	 *
	 * @return void
	 */
	public function test_generate_link_multiple_products() {
		$name     = 'Test Multiple Products';
		$products = array(
			array(
				'product_id' => 1,
				'quantity'   => 1,
			),
			array(
				'product_id' => 2,
				'quantity'   => 3,
			),
			array(
				'product_id' => 3,
				'quantity'   => 2,
			),
		);

		$result = $this->jump_to_checkout->generate_link( $name, $products );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'id', $result );
		$this->assertGreaterThan( 0, $result['id'] );

		// Verify link was saved to database.
		$db   = new Database();
		$link = $db->get_link_by_token( $result['token'] );

		$this->assertIsObject( $link );
		$this->assertEquals( $name, $link->name );
		$saved_products = json_decode( $link->products, true );
		$this->assertCount( 3, $saved_products );
	}

	/**
	 * Test generate link with empty products array
	 *
	 * @return void
	 */
	public function test_generate_link_empty_products() {
		$name     = 'Test Empty Products';
		$products = array();

		$result = $this->jump_to_checkout->generate_link( $name, $products );

		// Should still create a link even with empty products.
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'id', $result );
	}

	/**
	 * Test generate link expiry is always 0 in FREE version
	 *
	 * @return void
	 */
	public function test_generate_link_no_expiry_free() {
		$name     = 'Test No Expiry';
		$products = array(
			array(
				'product_id' => 1,
				'quantity'   => 1,
			),
		);

		$result = $this->jump_to_checkout->generate_link( $name, $products, 24 );

		// Verify link was created.
		$this->assertIsArray( $result );

		// Verify expiry is 0 in database (FREE version).
		$db   = new Database();
		$link = $db->get_link_by_token( $result['token'] );

		$this->assertIsObject( $link );
		$this->assertEquals( 0, $link->expiry_hours );
		$this->assertNull( $link->expires_at );
	}

	/**
	 * Test token encoding and decoding
	 *
	 * @return void
	 */
	public function test_token_encoding() {
		$name     = 'Test Token Encoding';
		$products = array(
			array(
				'product_id' => 1,
				'quantity'   => 1,
			),
		);

		$result = $this->jump_to_checkout->generate_link( $name, $products );

		// Token should be a valid base64 string.
		$this->assertIsString( $result['token'] );
		$this->assertNotEmpty( $result['token'] );

		// Verify token is in URL.
		$this->assertStringContainsString( $result['token'], $result['url'] );
	}

	/**
	 * Test generate link stores correct data
	 *
	 * @return void
	 */
	public function test_generate_link_stores_data() {
		$name     = 'Test Data Storage';
		$products = array(
			array(
				'product_id' => 5,
				'quantity'   => 10,
			),
		);

		$result = $this->jump_to_checkout->generate_link( $name, $products );

		// Verify data in database.
		$db   = new Database();
		$link = $db->get_link_by_token( $result['token'] );

		$this->assertIsObject( $link );
		$this->assertEquals( $name, $link->name );
		$this->assertEquals( 'active', $link->status );
		$this->assertEquals( 0, $link->visits );
		$this->assertEquals( 0, $link->conversions );

		$saved_products = json_decode( $link->products, true );
		$this->assertIsArray( $saved_products );
		$this->assertCount( 1, $saved_products );
		$this->assertEquals( 5, $saved_products[0]['product_id'] );
		$this->assertEquals( 10, $saved_products[0]['quantity'] );
	}

	/**
	 * Test generate link with special characters in name
	 *
	 * @return void
	 */
	public function test_generate_link_special_characters() {
		$name     = 'Test Link with "Special" Characters & Symbols < >';
		$products = array(
			array(
				'product_id' => 1,
				'quantity'   => 1,
			),
		);

		$result = $this->jump_to_checkout->generate_link( $name, $products );

		$this->assertIsArray( $result );

		// Verify name is stored correctly.
		$db   = new Database();
		$link = $db->get_link_by_token( $result['token'] );

		$this->assertIsObject( $link );
		$this->assertEquals( $name, $link->name );
	}
}


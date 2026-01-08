<?php
/**
 * Database Test Class
 *
 * @package CLOSE\JumpToCheckout\Tests
 */

namespace CLOSE\JumpToCheckout\Tests;

use CLOSE\JumpToCheckout\Database\Database;
use WP_UnitTestCase;

/**
 * Database Test Case
 */
class Test_Database extends WP_UnitTestCase {

	/**
	 * Database instance
	 *
	 * @var Database
	 */
	private $database;

	/**
	 * Setup test
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		$this->database = new Database();
		$this->database->create_table();
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
	 * Test table creation and basic functionality
	 *
	 * @return void
	 */
	public function test_table_exists() {
		// Test table functionality by inserting and retrieving data.
		$data = array(
			'name'         => 'Test Table Exists',
			'token'        => 'test_token_table',
			'url'          => 'https://example.com/test',
			'products'     => array(),
			'expiry_hours' => 0,
			'expires_at'   => null,
		);

		$link_id = $this->database->insert_link( $data );

		// If we can insert and get an ID, the table exists and works.
		$this->assertIsInt( $link_id );
		$this->assertGreaterThan( 0, $link_id );
	}

	/**
	 * Test insert link
	 *
	 * @return void
	 */
	public function test_insert_link() {
		$data = array(
			'name'         => 'Test Link',
			'token'        => 'test_token_123',
			'url'          => 'https://example.com/jump-to-checkout/test_token_123',
			'products'     => array(
				array(
					'product_id' => 1,
					'quantity'   => 2,
				),
			),
			'expiry_hours' => 0,
			'expires_at'   => null,
		);

		$link_id = $this->database->insert_link( $data );

		$this->assertIsInt( $link_id );
		$this->assertGreaterThan( 0, $link_id );
	}

	/**
	 * Test get link by token
	 *
	 * @return void
	 */
	public function test_get_link_by_token() {
		$data = array(
			'name'         => 'Test Link 2',
			'token'        => 'test_token_456',
			'url'          => 'https://example.com/jump-to-checkout/test_token_456',
			'products'     => array(
				array(
					'product_id' => 2,
					'quantity'   => 1,
				),
			),
			'expiry_hours' => 0,
			'expires_at'   => null,
		);

		$link_id = $this->database->insert_link( $data );
		$link    = $this->database->get_link_by_token( 'test_token_456' );

		$this->assertIsObject( $link );
		$this->assertEquals( 'Test Link 2', $link->name );
		$this->assertEquals( 'test_token_456', $link->token );
		$this->assertEquals( 'active', $link->status );
	}

	/**
	 * Test get links with filters
	 *
	 * @return void
	 */
	public function test_get_links() {
		// Insert multiple links.
		for ( $i = 0; $i < 5; $i++ ) {
			$data = array(
				'name'         => 'Test Link ' . $i,
				'token'        => 'test_token_get_' . $i,
				'url'          => 'https://example.com/jump-to-checkout/test_token_get_' . $i,
				'products'     => array(),
				'expiry_hours' => 0,
				'expires_at'   => null,
			);
			$this->database->insert_link( $data );
		}

		// Test default get_links.
		$links = $this->database->get_links();
		$this->assertIsArray( $links );
		$this->assertGreaterThanOrEqual( 5, count( $links ) );

		// Test with limit.
		$limited_links = $this->database->get_links( array( 'limit' => 2 ) );
		$this->assertLessThanOrEqual( 2, count( $limited_links ) );

		// Test with status filter.
		$active_links = $this->database->get_links( array( 'status' => 'active' ) );
		$this->assertIsArray( $active_links );
	}

	/**
	 * Test increment visits
	 *
	 * @return void
	 */
	public function test_increment_visits() {
		$data = array(
			'name'         => 'Test Link 3',
			'token'        => 'test_token_789',
			'url'          => 'https://example.com/jump-to-checkout/test_token_789',
			'products'     => array(),
			'expiry_hours' => 0,
			'expires_at'   => null,
		);

		$link_id = $this->database->insert_link( $data );

		$this->database->increment_visits( $link_id );
		$this->database->increment_visits( $link_id );

		$link = $this->database->get_link_by_token( 'test_token_789' );

		$this->assertEquals( 2, $link->visits );
	}

	/**
	 * Test increment conversions
	 *
	 * @return void
	 */
	public function test_increment_conversions() {
		$data = array(
			'name'         => 'Test Link 4',
			'token'        => 'test_token_abc',
			'url'          => 'https://example.com/jump-to-checkout/test_token_abc',
			'products'     => array(),
			'expiry_hours' => 0,
			'expires_at'   => null,
		);

		$link_id = $this->database->insert_link( $data );

		$this->database->increment_conversions( $link_id );

		$link = $this->database->get_link_by_token( 'test_token_abc' );

		$this->assertEquals( 1, $link->conversions );
	}

	/**
	 * Test update status
	 *
	 * @return void
	 */
	public function test_update_status() {
		$data = array(
			'name'         => 'Test Link 5',
			'token'        => 'test_token_def',
			'url'          => 'https://example.com/jump-to-checkout/test_token_def',
			'products'     => array(),
			'expiry_hours' => 0,
			'expires_at'   => null,
		);

		$link_id = $this->database->insert_link( $data );

		$this->database->update_status( $link_id, 'inactive' );

		$link = $this->database->get_link_by_token( 'test_token_def' );

		$this->assertEquals( 'inactive', $link->status );
	}

	/**
	 * Test delete link
	 *
	 * @return void
	 */
	public function test_delete_link() {
		$data = array(
			'name'         => 'Test Link 6',
			'token'        => 'test_token_ghi',
			'url'          => 'https://example.com/jump-to-checkout/test_token_ghi',
			'products'     => array(),
			'expiry_hours' => 0,
			'expires_at'   => null,
		);

		$link_id = $this->database->insert_link( $data );

		$result = $this->database->delete_link( $link_id );

		// wpdb->delete returns the number of rows affected, not boolean.
		$this->assertEquals( 1, $result );

		$link = $this->database->get_link_by_token( 'test_token_ghi' );

		$this->assertNull( $link );
	}

	/**
	 * Test get statistics
	 *
	 * @return void
	 */
	public function test_get_statistics() {
		// Insert some test links.
		for ( $i = 0; $i < 3; $i++ ) {
			$data = array(
				'name'         => 'Test Link Stats ' . $i,
				'token'        => 'test_token_stat_' . $i,
				'url'          => 'https://example.com/jump-to-checkout/test_token_stat_' . $i,
				'products'     => array(),
				'expiry_hours' => 0,
				'expires_at'   => null,
			);

			$this->database->insert_link( $data );
		}

		$stats = $this->database->get_statistics();

		$this->assertIsObject( $stats );
		$this->assertGreaterThanOrEqual( 3, $stats->total_links );
		$this->assertGreaterThanOrEqual( 3, $stats->active_links );
	}
}


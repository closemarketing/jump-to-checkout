<?php
/**
 * LinkExpiration Test Class
 *
 * @package CLOSE\JumpToCheckout\Tests
 */

namespace CLOSE\JumpToCheckout\Tests;

use CLOSE\JumpToCheckout\Core\LinkExpiration;
use WP_UnitTestCase;

/**
 * LinkExpiration Test Case
 */
class Test_LinkExpiration extends WP_UnitTestCase {

	/**
	 * LinkExpiration instance
	 *
	 * @var LinkExpiration
	 */
	private $link_expiration;

	/**
	 * Setup test
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		$this->link_expiration = new LinkExpiration();
	}

	/**
	 * Test get_ajax_expiry returns zero when no expiry posted
	 *
	 * @return void
	 */
	public function test_get_ajax_expiry_no_expiry() {
		$result = $this->link_expiration->get_ajax_expiry( 0, array() );
		$this->assertEquals( 0, $result );
	}

	/**
	 * Test get_ajax_expiry reads from POST data
	 *
	 * @return void
	 */
	public function test_get_ajax_expiry_with_value() {
		$result = $this->link_expiration->get_ajax_expiry( 0, array( 'expiry' => '48' ) );
		$this->assertEquals( 48, $result );
	}

	/**
	 * Test get_ajax_expiry sanitizes to absint
	 *
	 * @return void
	 */
	public function test_get_ajax_expiry_sanitizes_negative() {
		$result = $this->link_expiration->get_ajax_expiry( 0, array( 'expiry' => '-10' ) );
		$this->assertEquals( 0, $result );
	}

	/**
	 * Test calculate_token_expiry returns zero when no expiry
	 *
	 * @return void
	 */
	public function test_calculate_token_expiry_no_expiry() {
		$result = $this->link_expiration->calculate_token_expiry( 0, 0 );
		$this->assertEquals( 0, $result );
	}

	/**
	 * Test calculate_token_expiry returns future timestamp
	 *
	 * @return void
	 */
	public function test_calculate_token_expiry_with_expiry() {
		$result = $this->link_expiration->calculate_token_expiry( 0, 24 );
		$this->assertGreaterThan( time(), $result );
		$this->assertEqualsWithDelta( time() + ( 24 * HOUR_IN_SECONDS ), $result, 5 );
	}

	/**
	 * Test modify_link_data sets expiry fields when expiry is set
	 *
	 * @return void
	 */
	public function test_modify_link_data_with_expiry() {
		$link_data = array();
		$result    = $this->link_expiration->modify_link_data( $link_data, 'Test', array(), 24 );

		$this->assertEquals( 24, $result['expiry_hours'] );
		$this->assertNotNull( $result['expires_at'] );
		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $result['expires_at'] );
	}

	/**
	 * Test modify_link_data sets null expires_at when no expiry
	 *
	 * @return void
	 */
	public function test_modify_link_data_no_expiry() {
		$link_data = array();
		$result    = $this->link_expiration->modify_link_data( $link_data, 'Test', array(), 0 );

		$this->assertEquals( 0, $result['expiry_hours'] );
		$this->assertNull( $result['expires_at'] );
	}

	/**
	 * Test check_link_expired returns false for non-expired link
	 *
	 * @return void
	 */
	public function test_check_link_expired_not_expired() {
		$link             = new \stdClass();
		$link->expires_at = gmdate( 'Y-m-d H:i:s', time() + ( 24 * HOUR_IN_SECONDS ) );

		$result = $this->link_expiration->check_link_expired( false, $link );
		$this->assertFalse( $result );
	}

	/**
	 * Test check_link_expired returns true for expired link
	 *
	 * @return void
	 */
	public function test_check_link_expired_is_expired() {
		$link             = new \stdClass();
		$link->expires_at = gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS );

		$result = $this->link_expiration->check_link_expired( false, $link );
		$this->assertTrue( $result );
	}

	/**
	 * Test check_link_expired returns false when expires_at is null (no expiry)
	 *
	 * @return void
	 */
	public function test_check_link_expired_no_expiry_date() {
		$link             = new \stdClass();
		$link->expires_at = null;

		$result = $this->link_expiration->check_link_expired( false, $link );
		$this->assertFalse( $result );
	}

	/**
	 * Test check_link_expired does not override already-expired status
	 *
	 * @return void
	 */
	public function test_check_link_expired_already_expired() {
		$link             = new \stdClass();
		$link->expires_at = gmdate( 'Y-m-d H:i:s', time() + HOUR_IN_SECONDS );

		$result = $this->link_expiration->check_link_expired( true, $link );
		$this->assertTrue( $result );
	}

	/**
	 * Test modify_token_data adds exp key with expiry
	 *
	 * @return void
	 */
	public function test_modify_token_data_with_expiry() {
		$data   = array();
		$result = $this->link_expiration->modify_token_data( $data, 'Test', array(), 12 );

		$this->assertArrayHasKey( 'exp', $result );
		$this->assertGreaterThan( time(), $result['exp'] );
	}

	/**
	 * Test modify_token_data sets exp to zero without expiry
	 *
	 * @return void
	 */
	public function test_modify_token_data_no_expiry() {
		$data   = array();
		$result = $this->link_expiration->modify_token_data( $data, 'Test', array(), 0 );

		$this->assertArrayHasKey( 'exp', $result );
		$this->assertEquals( 0, $result['exp'] );
	}
}

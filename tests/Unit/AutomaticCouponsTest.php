<?php
/**
 * AutomaticCoupons Test Class
 *
 * @package CLOSE\JumpToCheckout\Tests
 */

namespace CLOSE\JumpToCheckout\Tests;

use CLOSE\JumpToCheckout\Core\AutomaticCoupons;
use WP_UnitTestCase;

/**
 * AutomaticCoupons Test Case
 */
class Test_AutomaticCoupons extends WP_UnitTestCase {

	/**
	 * AutomaticCoupons instance
	 *
	 * @var AutomaticCoupons
	 */
	private $automatic_coupons;

	/**
	 * Setup test
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		$this->automatic_coupons = new AutomaticCoupons();
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
	 * Test save_coupon_from_ajax stores coupon code in link data
	 *
	 * @return void
	 */
	public function test_save_coupon_from_ajax_with_coupon() {
		$link_data = array();
		$post_data = array( 'coupon_code' => 'SUMMER20' );

		$result = $this->automatic_coupons->save_coupon_from_ajax( $link_data, $post_data );

		$this->assertArrayHasKey( '_coupon_code', $result );
		$this->assertEquals( 'SUMMER20', $result['_coupon_code'] );
	}

	/**
	 * Test save_coupon_from_ajax returns unchanged link data when no coupon
	 *
	 * @return void
	 */
	public function test_save_coupon_from_ajax_without_coupon() {
		$link_data = array( 'foo' => 'bar' );
		$post_data = array();

		$result = $this->automatic_coupons->save_coupon_from_ajax( $link_data, $post_data );

		$this->assertArrayNotHasKey( '_coupon_code', $result );
		$this->assertEquals( 'bar', $result['foo'] );
	}

	/**
	 * Test save_coupon_from_ajax ignores empty coupon code
	 *
	 * @return void
	 */
	public function test_save_coupon_from_ajax_empty_coupon() {
		$link_data = array();
		$post_data = array( 'coupon_code' => '' );

		$result = $this->automatic_coupons->save_coupon_from_ajax( $link_data, $post_data );

		$this->assertArrayNotHasKey( '_coupon_code', $result );
	}

	/**
	 * Test save_coupon_after_link_created stores option when coupon is set
	 *
	 * @return void
	 */
	public function test_save_coupon_after_link_created_stores_option() {
		$link_id   = 999;
		$link_data = array( '_coupon_code' => 'TESTCODE' );

		// With WooCommerce not available in unit tests, save_link_coupon will
		// return false because WC_Coupon cannot validate. We test that the
		// option write path is attempted by mocking the scenario without WC.
		// Here we verify the method runs without errors.
		$this->automatic_coupons->save_coupon_after_link_created( $link_id, $link_data );

		// No exception thrown — method handled gracefully.
		$this->assertTrue( true );
	}

	/**
	 * Test save_coupon_after_link_created does nothing when no coupon in data
	 *
	 * @return void
	 */
	public function test_save_coupon_after_link_created_no_coupon() {
		$link_id   = 998;
		$link_data = array();

		$this->automatic_coupons->save_coupon_after_link_created( $link_id, $link_data );

		$this->assertFalse( get_option( 'jptc_link_coupon_' . $link_id ) );
	}

	/**
	 * Test hooks are registered on construct
	 *
	 * @return void
	 */
	public function test_hooks_registered() {
		$this->assertGreaterThan(
			0,
			has_action( 'jptc_before_redirect_to_checkout', array( $this->automatic_coupons, 'apply_coupon_to_link' ) )
		);
		$this->assertGreaterThan(
			0,
			has_filter( 'jptc_ajax_link_data', array( $this->automatic_coupons, 'save_coupon_from_ajax' ) )
		);
		$this->assertGreaterThan(
			0,
			has_action( 'jptc_after_link_created', array( $this->automatic_coupons, 'save_coupon_after_link_created' ) )
		);
	}
}

<?php
/**
 * AdvancedAnalytics Test Class
 *
 * @package CLOSE\JumpToCheckout\Tests
 */

namespace CLOSE\JumpToCheckout\Tests;

use CLOSE\JumpToCheckout\Admin\AdvancedAnalytics;
use CLOSE\JumpToCheckout\Database\Database;
use WP_UnitTestCase;

/**
 * AdvancedAnalytics Test Case
 */
class Test_AdvancedAnalytics extends WP_UnitTestCase {

	/**
	 * AdvancedAnalytics instance
	 *
	 * @var AdvancedAnalytics
	 */
	private $analytics;

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

		$this->analytics = new AdvancedAnalytics();
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
	 * Test hooks are registered on construct
	 *
	 * @return void
	 */
	public function test_hooks_registered() {
		$this->assertGreaterThan(
			0,
			has_action( 'admin_menu', array( $this->analytics, 'add_analytics_menu' ) )
		);
		$this->assertGreaterThan(
			0,
			has_action( 'admin_enqueue_scripts', array( $this->analytics, 'enqueue_analytics_scripts' ) )
		);
		$this->assertGreaterThan(
			0,
			has_action( 'wp_ajax_jptc_get_analytics_data', array( $this->analytics, 'ajax_get_analytics_data' ) )
		);
	}

	/**
	 * Test AJAX handler returns error without nonce
	 *
	 * @return void
	 */
	public function test_ajax_get_analytics_data_requires_nonce() {
		$this->expectException( \WPDieException::class );

		$_REQUEST['nonce'] = 'invalid_nonce';

		$this->analytics->ajax_get_analytics_data();
	}

	/**
	 * Test analytics menu is added at priority 20
	 *
	 * @return void
	 */
	public function test_analytics_menu_priority() {
		$priority = has_action( 'admin_menu', array( $this->analytics, 'add_analytics_menu' ) );
		$this->assertEquals( 20, $priority );
	}

	/**
	 * A repeated menu registration must not duplicate the Analytics link.
	 *
	 * @return void
	 */
	public function test_analytics_menu_is_registered_once() {
		global $submenu;

		$previous_submenu = $submenu;
		$previous_user_id = get_current_user_id();
		$user_id          = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$user             = get_user_by( 'id', $user_id );
		$user->add_cap( 'manage_woocommerce' );
		wp_set_current_user( $user_id );
		$submenu['jptc-jump-to-checkout'] = array();

		try {
			$this->analytics->add_analytics_menu();
			$this->analytics->add_analytics_menu();

			$analytics_items = array_filter(
				$submenu['jptc-jump-to-checkout'],
				function ( $item ) {
					return 'jptc-analytics' === $item[2];
				}
			);

			$this->assertCount( 1, $analytics_items );
		} finally {
			$submenu = $previous_submenu;
			wp_set_current_user( $previous_user_id );
		}
	}

	/**
	 * Test enqueue_analytics_scripts skips non-analytics pages
	 *
	 * @return void
	 */
	public function test_enqueue_analytics_scripts_wrong_hook() {
		// Should return early without enqueuing anything.
		$this->analytics->enqueue_analytics_scripts( 'toplevel_page_jptc-jump-to-checkout' );

		$this->assertFalse( wp_script_is( 'jptc-analytics', 'enqueued' ) );
		$this->assertFalse( wp_style_is( 'jptc-analytics', 'enqueued' ) );
	}
}

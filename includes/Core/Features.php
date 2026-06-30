<?php
/**
 * Features Class
 *
 * Placeholder kept for backward compatibility with any third-party code
 * that references Features methods. All features are now available to all users.
 *
 * @package    CLOSE\JumpToCheckout\Core
 * @author     Close Marketing
 * @copyright  2025 Closemarketing
 * @version    2.0.0
 */

namespace CLOSE\JumpToCheckout\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Features Class
 */
class Features {

	/**
	 * Get current active links count
	 *
	 * @return int
	 */
	public static function get_active_links_count() {
		global $wpdb;
		$table = $wpdb->prefix . 'jptc_links';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE status = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				'active'
			)
		);
	}
}

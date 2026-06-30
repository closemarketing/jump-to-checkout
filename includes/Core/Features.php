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
	 * Backward-compat stub — all features are now available.
	 *
	 * @return bool
	 */
	public static function is_pro() {
		return true;
	}

	/**
	 * Backward-compat stub — returns empty string.
	 *
	 * @return string
	 */
	public static function get_upgrade_url() {
		return '';
	}

	/**
	 * Backward-compat stub — export is always available.
	 *
	 * @return bool
	 */
	public static function can_export() {
		return true;
	}

	/**
	 * Backward-compat stub — coupons are always available.
	 *
	 * @return bool
	 */
	public static function can_use_coupons() {
		return true;
	}

	/**
	 * Backward-compat stub — templates are always available.
	 *
	 * @return bool
	 */
	public static function can_use_templates() {
		return true;
	}

	/**
	 * Backward-compat stub — webhooks are always available.
	 *
	 * @return bool
	 */
	public static function can_use_webhooks() {
		return true;
	}

	/**
	 * Backward-compat stub — API is always available.
	 *
	 * @return bool
	 */
	public static function can_use_api() {
		return true;
	}

	/**
	 * Backward-compat stub — analytics are always available.
	 *
	 * @return bool
	 */
	public static function has_analytics() {
		return true;
	}

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

<?php
/**
 * Features Manager Class
 *
 * Handles PRO features verification and limits
 *
 * @package    CLOSE\JumpToCheckout\Core
 * @author     Close Marketing
 * @copyright  2025 Closemarketing
 * @version    1.0.0
 */

namespace CLOSE\JumpToCheckout\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Features Manager Class
 */
class Features {

	/**
	 * Check if PRO version is active
	 *
	 * @return bool
	 */
	public static function is_pro() {
		// Check if PRO plugin is active.
		return apply_filters( 'jptc_is_pro_active', defined( 'JTPC_IS_PRO' ) && JTPC_IS_PRO );
	}

	/**
	 * Check if can create new link
	 *
	 * @return bool
	 */
	public static function can_create_link() {
		// Allow PRO to override.
		if ( apply_filters( 'jptc_can_create_link_override', false ) ) {
			return true;
		}

		if ( self::is_pro() ) {
			return true;
		}

		// FREE: Limit of 5 active links.
		global $wpdb;
		$table = $wpdb->prefix . 'jptc_links';
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE status = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				'active'
			)
		);

		$max_links = self::max_links();
		return $count < $max_links;
	}

	/**
	 * Get max links allowed
	 *
	 * @return int
	 */
	public static function max_links() {
		$max = self::is_pro() ? 999999 : 5;
		return apply_filters( 'jptc_max_links', $max );
	}

	/**
	 * Get current active links count
	 *
	 * @return int
	 */
	public static function get_active_links_count() {
		global $wpdb;
		$table = $wpdb->prefix . 'jptc_links';
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE status = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				'active'
			)
		);
	}

	/**
	 * Get max products per link
	 *
	 * @return int
	 */
	public static function max_products_per_link() {
		$max = self::is_pro() ? 999 : 1;
		return apply_filters( 'jptc_max_products_per_link', $max );
	}

	/**
	 * Check if has advanced analytics
	 *
	 * @return bool
	 */
	public static function has_analytics() {
		return self::is_pro();
	}

	/**
	 * Check if can export data
	 *
	 * @return bool
	 */
	public static function can_export() {
		return self::is_pro();
	}

	/**
	 * Check if can use automatic coupons
	 *
	 * @return bool
	 */
	public static function can_use_coupons() {
		return self::is_pro();
	}

	/**
	 * Check if can use templates
	 *
	 * @return bool
	 */
	public static function can_use_templates() {
		return self::is_pro();
	}

	/**
	 * Check if can use webhooks
	 *
	 * @return bool
	 */
	public static function can_use_webhooks() {
		return self::is_pro();
	}

	/**
	 * Check if can use API
	 *
	 * @return bool
	 */
	public static function can_use_api() {
		return self::is_pro();
	}

	/**
	 * Get upgrade URL
	 *
	 * @return string
	 */
	public static function get_upgrade_url() {
		return JTPC_UPGRADE_URL;
	}

	/**
	 * Show upgrade notice
	 *
	 * @param string $feature Feature name.
	 * @return void
	 */
	public static function show_upgrade_notice( $feature = '' ) {
		$message = $feature ? sprintf(
			/* translators: %s is the feature name */
			__( 'The "%s" feature is only available in the PRO version.', 'jump-to-checkout' ),
			esc_html( $feature )
		) : __( 'This feature is only available in the PRO version.', 'jump-to-checkout' );

		echo '<div class="notice notice-warning jump-to-checkout-upgrade-notice">';
		echo '<p>' . esc_html( $message ) . ' ';
		echo '<a href="' . esc_url( self::get_upgrade_url() ) . '" class="button button-primary" target="_blank">';
		esc_html_e( 'Upgrade to PRO', 'jump-to-checkout' );
		echo '</a></p>';
		echo '</div>';
	}

	/**
	 * Get features comparison array
	 *
	 * @return array
	 */
	public static function get_features_comparison() {
		return array(
			'free' => array(
				'name'     => __( 'FREE', 'jump-to-checkout' ),
				'price'    => __( 'Free', 'jump-to-checkout' ),
				'features' => array(
					__( '5 active links maximum', 'jump-to-checkout' ),
					__( '1 product per link', 'jump-to-checkout' ),
					__( 'Basic statistics', 'jump-to-checkout' ),
					__( 'No link expiration', 'jump-to-checkout' ),
				),
			),
			'pro'  => array(
				'name'     => __( 'PRO', 'jump-to-checkout' ),
				'price'    => __( 'From €49/year', 'jump-to-checkout' ),
				'features' => array(
					__( 'Unlimited links', 'jump-to-checkout' ),
					__( 'Multiple products per link', 'jump-to-checkout' ),
					__( 'Advanced analytics with charts', 'jump-to-checkout' ),
					__( 'Export to CSV/Excel', 'jump-to-checkout' ),
					__( 'Automatic coupons', 'jump-to-checkout' ),
					__( 'Templates & UTM tracking', 'jump-to-checkout' ),
					__( 'API & Webhooks', 'jump-to-checkout' ),
					__( 'Priority support', 'jump-to-checkout' ),
				),
			),
		);
	}
}


<?php
/**
 * Features Manager Class
 *
 * Handles PRO features verification and limits
 *
 * @package    CLOSE\DirectLinkCheckout\Core
 * @author     Close Marketing
 * @copyright  2025 Closemarketing
 * @version    1.0.0
 */

namespace CLOSE\DirectLinkCheckout\Core;

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
		return apply_filters( 'cldc_is_pro_active', defined( 'CLDC_IS_PRO' ) && CLDC_IS_PRO );
	}

	/**
	 * Check if can create new link
	 *
	 * @return bool
	 */
	public static function can_create_link() {
		// Allow PRO to override.
		if ( apply_filters( 'cldc_can_create_link_override', false ) ) {
			return true;
		}

		if ( self::is_pro() ) {
			return true;
		}

		// FREE: Limit of 5 active links.
		global $wpdb;
		$table = $wpdb->prefix . 'cldc_links';
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
		return apply_filters( 'cldc_max_links', $max );
	}

	/**
	 * Get current active links count
	 *
	 * @return int
	 */
	public static function get_active_links_count() {
		global $wpdb;
		$table = $wpdb->prefix . 'cldc_links';
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
		return apply_filters( 'cldc_max_products_per_link', $max );
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
		return 'https://close.technology/plugins/direct-link-checkout-pro/';
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
			__( 'The "%s" feature is only available in the PRO version.', 'direct-link-checkout' ),
			esc_html( $feature )
		) : __( 'This feature is only available in the PRO version.', 'direct-link-checkout' );

		echo '<div class="notice notice-warning cldc-upgrade-notice">';
		echo '<p>' . esc_html( $message ) . ' ';
		echo '<a href="' . esc_url( self::get_upgrade_url() ) . '" class="button button-primary" target="_blank">';
		esc_html_e( 'Upgrade to PRO', 'direct-link-checkout' );
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
				'name'     => __( 'FREE', 'direct-link-checkout' ),
				'price'    => __( 'Free', 'direct-link-checkout' ),
				'features' => array(
					__( '5 active links maximum', 'direct-link-checkout' ),
					__( '1 product per link', 'direct-link-checkout' ),
					__( 'Basic statistics', 'direct-link-checkout' ),
					__( 'No link expiration', 'direct-link-checkout' ),
				),
			),
			'pro'  => array(
				'name'     => __( 'PRO', 'direct-link-checkout' ),
				'price'    => __( 'From €49/year', 'direct-link-checkout' ),
				'features' => array(
					__( 'Unlimited links', 'direct-link-checkout' ),
					__( 'Multiple products per link', 'direct-link-checkout' ),
					__( 'Advanced analytics with charts', 'direct-link-checkout' ),
					__( 'Export to CSV/Excel', 'direct-link-checkout' ),
					__( 'Automatic coupons', 'direct-link-checkout' ),
					__( 'Templates & UTM tracking', 'direct-link-checkout' ),
					__( 'API & Webhooks', 'direct-link-checkout' ),
					__( 'Priority support', 'direct-link-checkout' ),
				),
			),
		);
	}
}


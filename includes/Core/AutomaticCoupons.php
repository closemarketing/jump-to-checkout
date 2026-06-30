<?php
/**
 * Automatic Coupons Handler
 *
 * Applies discount codes automatically when links are clicked
 *
 * @package    CLOSE\JumpToCheckout\Core
 * @author     Close Marketing
 * @copyright  2025 Closemarketing
 * @version    1.0.0
 */

namespace CLOSE\JumpToCheckout\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Automatic Coupons Class
 */
class AutomaticCoupons {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'jptc_before_redirect_to_checkout', array( $this, 'apply_coupon_to_link' ), 10, 1 );
		add_filter( 'jptc_ajax_link_data', array( $this, 'save_coupon_from_ajax' ), 10, 2 );
		add_action( 'jptc_after_link_created', array( $this, 'save_coupon_after_link_created' ), 10, 2 );
	}

	/**
	 * Apply coupon to link
	 *
	 * @param object $link Link object from database.
	 * @return void
	 */
	public function apply_coupon_to_link( $link ) {
		if ( ! $link || ! function_exists( 'WC' ) ) {
			return;
		}

		$coupon_code = $this->get_link_coupon( $link->id );

		if ( ! $coupon_code ) {
			return;
		}

		$coupon = new \WC_Coupon( $coupon_code );
		if ( ! $coupon->get_id() ) {
			return;
		}

		if ( ! $coupon->is_valid() ) {
			return;
		}

		if ( WC()->cart && ! WC()->cart->has_discount( $coupon_code ) ) {
			WC()->cart->apply_coupon( $coupon_code );
		}
	}

	/**
	 * Get coupon code for a link
	 *
	 * @param int $link_id Link ID.
	 * @return string|false Coupon code or false if not set.
	 */
	private function get_link_coupon( $link_id ) {
		$coupon = get_option( 'jptc_link_coupon_' . $link_id, false );
		return $coupon ? $coupon : false;
	}

	/**
	 * Save coupon code for a link
	 *
	 * @param int    $link_id Link ID.
	 * @param string $coupon_code Coupon code.
	 * @return bool
	 */
	public function save_link_coupon( $link_id, $coupon_code ) {
		if ( ! $link_id || ! $coupon_code ) {
			return false;
		}

		$coupon = new \WC_Coupon( $coupon_code );
		if ( ! $coupon->get_id() ) {
			return false;
		}

		return update_option( 'jptc_link_coupon_' . $link_id, sanitize_text_field( $coupon_code ) );
	}

	/**
	 * Save coupon from AJAX request
	 *
	 * @param array $link_data Link data.
	 * @param array $post_data POST data from AJAX.
	 * @return array
	 */
	public function save_coupon_from_ajax( $link_data, $post_data ) {
		if ( isset( $post_data['coupon_code'] ) && ! empty( $post_data['coupon_code'] ) ) {
			$coupon_code               = sanitize_text_field( $post_data['coupon_code'] );
			$link_data['_coupon_code'] = $coupon_code;
		}
		return $link_data;
	}

	/**
	 * Save coupon after link is created
	 *
	 * @param int   $link_id Link ID.
	 * @param array $link_data Link data from AJAX.
	 * @return void
	 */
	public function save_coupon_after_link_created( $link_id, $link_data ) {
		if ( isset( $link_data['_coupon_code'] ) && ! empty( $link_data['_coupon_code'] ) ) {
			$this->save_link_coupon( $link_id, $link_data['_coupon_code'] );
		}
	}
}

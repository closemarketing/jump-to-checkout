<?php
/**
 * Link Expiration Handler
 *
 * Handles link expiration functionality
 *
 * @package    CLOSE\JumpToCheckout\Core
 * @author     Close Marketing
 * @copyright  2025 Closemarketing
 * @version    1.0.0
 */

namespace CLOSE\JumpToCheckout\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Link Expiration Class
 */
class LinkExpiration {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_filter( 'jptc_link_expiry', array( $this, 'get_link_expiry' ), 10, 3 );
		add_filter( 'jptc_token_expiry_timestamp', array( $this, 'calculate_token_expiry' ), 10, 2 );
		add_filter( 'jptc_token_data_before_encode', array( $this, 'modify_token_data' ), 10, 4 );
		add_filter( 'jptc_link_data_before_insert', array( $this, 'modify_link_data' ), 10, 4 );
		add_filter( 'jptc_link_is_expired', array( $this, 'check_link_expired' ), 10, 2 );
		add_filter( 'jptc_token_expiry_check', array( $this, 'check_token_expiry' ), 10, 2 );
		add_filter( 'jptc_ajax_link_expiry', array( $this, 'get_ajax_expiry' ), 10, 2 );
	}

	/**
	 * Get link expiry from AJAX request
	 *
	 * @param int   $expiry Current expiry.
	 * @param array $post_data POST data from AJAX request.
	 * @return int
	 */
	public function get_ajax_expiry( $expiry, $post_data ) {
		if ( isset( $post_data['expiry'] ) ) {
			$expiry = max( 0, (int) $post_data['expiry'] );
		}
		return $expiry;
	}

	/**
	 * Get link expiry value
	 *
	 * @param int    $expiry Current expiry.
	 * @param string $name Link name.
	 * @param array  $products Products array.
	 * @return int
	 */
	public function get_link_expiry( $expiry, $name, $products ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		return $expiry;
	}

	/**
	 * Calculate token expiry timestamp
	 *
	 * @param int $timestamp Current timestamp.
	 * @param int $expiry Expiry in hours.
	 * @return int
	 */
	public function calculate_token_expiry( $timestamp, $expiry ) {
		if ( 0 !== $expiry ) {
			return time() + ( $expiry * HOUR_IN_SECONDS );
		}
		return 0;
	}

	/**
	 * Modify token data before encoding
	 *
	 * @param array  $data Token data array.
	 * @param string $name Link name.
	 * @param array  $products Products array.
	 * @param int    $expiry Expiry in hours.
	 * @return array
	 */
	public function modify_token_data( $data, $name, $products, $expiry ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( 0 !== $expiry ) {
			$data['exp'] = time() + ( $expiry * HOUR_IN_SECONDS );
		} else {
			$data['exp'] = 0;
		}
		return $data;
	}

	/**
	 * Modify link data before insert
	 *
	 * @param array  $link_data Link data array.
	 * @param string $name Link name.
	 * @param array  $products Products array.
	 * @param int    $expiry Expiry in hours.
	 * @return array
	 */
	public function modify_link_data( $link_data, $name, $products, $expiry ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( 0 !== $expiry ) {
			$link_data['expiry_hours'] = $expiry;
			$link_data['expires_at']   = gmdate( 'Y-m-d H:i:s', time() + ( $expiry * HOUR_IN_SECONDS ) );
		} else {
			$link_data['expiry_hours'] = 0;
			$link_data['expires_at']   = null;
		}
		return $link_data;
	}

	/**
	 * Check if link has expired
	 *
	 * @param bool   $expired Current expired status.
	 * @param object $link Link object from database.
	 * @return bool
	 */
	public function check_link_expired( $expired, $link ) {
		if ( ! $expired && isset( $link->expires_at ) && $link->expires_at ) {
			$expired = strtotime( $link->expires_at ) < time();
		}
		return $expired;
	}

	/**
	 * Check token expiry
	 *
	 * @param bool  $is_valid Current validity status.
	 * @param array $data Decoded token data.
	 * @return bool
	 */
	public function check_token_expiry( $is_valid, $data ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		return false;
	}
}

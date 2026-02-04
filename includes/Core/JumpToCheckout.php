<?php
/**
 * Jump to Checkout Class
 *
 * Handles the direct checkout link functionality
 *
 * @package    CLOSE\JumpToCheckout\Core
 * @author     Close Marketing
 * @copyright  2025 Closemarketing
 * @version    1.0.0
 */

namespace CLOSE\JumpToCheckout\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Jump to Checkout Class
 */
class JumpToCheckout {

	/**
	 * Secret key for token generation
	 *
	 * @var string
	 */
	private $secret_key;

	/**
	 * Database instance
	 *
	 * @var Database
	 */
	private $db;

	/**
	 * Constructor
	 */
	public function __construct() {
		// Initialize secret key.
		$this->secret_key = $this->get_secret_key();

		// Initialize database.
		$this->db = new \CLOSE\JumpToCheckout\Database\Database();

		// Hook to handle checkout URLs.
		add_action( 'template_redirect', array( $this, 'handle_checkout_link' ) );

		// Add query vars.
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );

		// Add rewrite rules.
		add_action( 'init', array( $this, 'add_rewrite_rules' ) );

		// Track conversions.
		add_action( 'woocommerce_thankyou', array( $this, 'track_conversion' ), 10, 1 );
		add_action( 'woocommerce_payment_complete', array( $this, 'track_conversion' ), 10, 1 );
		add_action( 'woocommerce_order_status_completed', array( $this, 'track_conversion' ), 10, 1 );
		add_action( 'woocommerce_order_status_processing', array( $this, 'track_conversion' ), 10, 1 );
		add_action( 'woocommerce_order_status_on-hold', array( $this, 'track_conversion' ), 10, 1 );
		add_action( 'woocommerce_order_status_pending', array( $this, 'track_conversion' ), 10, 1 );

		// Save link ID to order before payment.
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'save_link_to_order' ), 10, 1 );
	}

	/**
	 * Get or generate secret key
	 *
	 * @return string
	 */
	private function get_secret_key() {
		$key = get_option( 'jptc_secret_key' );

		if ( ! $key ) {
			$key = wp_generate_password( 64, true, true );
			update_option( 'jptc_secret_key', $key );
		}

		return $key;
	}

	/**
	 * Generate unique short token (8-10 characters)
	 *
	 * @return string
	 */
	private function generate_short_token() {
		global $wpdb;

		// Characters allowed in short token (alphanumeric, URL-safe).
		$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$length     = 10;
		$max_tries  = 10;

		for ( $i = 0; $i < $max_tries; $i++ ) {
			$token = '';
			for ( $j = 0; $j < $length; $j++ ) {
				$token .= $characters[ wp_rand( 0, strlen( $characters ) - 1 ) ];
			}

			// Check if token already exists.
			$table_name = $wpdb->prefix . 'jptc_links';
			$exists     = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table_name} WHERE token = %s",
					$token
				)
			);

			if ( ! $exists ) {
				return $token;
			}
		}

		// Fallback: use timestamp + random.
		return substr( md5( time() . wp_rand() ), 0, 10 );
	}

	/**
	 * Check if token is new short format (10 chars) or old long format
	 *
	 * @param string $token Token to check.
	 * @return bool True if new short format, false if old long format.
	 */
	private function is_short_token( $token ) {
		return strlen( $token ) <= 20;
	}

	/**
	 * Add custom query vars
	 *
	 * @param array $vars Query vars.
	 * @return array
	 */
	public function add_query_vars( $vars ) {
		$vars[] = 'jptc_token';
		return $vars;
	}

	/**
	 * Add rewrite rules
	 *
	 * @return void
	 */
	public function add_rewrite_rules() {
		add_rewrite_rule(
			'^jump-to-checkout/([^/]+)/?$',
			'index.php?jptc_token=$matches[1]',
			'top'
		);

		// Check if we need to flush rules.
		$version = get_option( 'jptc_rewrite_version' );
		if ( JTPC_VERSION !== $version ) {
			flush_rewrite_rules();
			update_option( 'jptc_rewrite_version', JTPC_VERSION );
		}
	}

	/**
	 * Generate checkout link
	 *
	 * @param string $name Link name.
	 * @param array  $products Array of products with product_id and quantity.
	 * @param int    $expiry Expiry time in hours (0 for no expiry). Only used if PRO is active via filter.
	 * @return array|false Array with link data or false on failure.
	 */
	public function generate_link( $name, $products, $expiry = 0 ) {
		// Ensure database is initialized.
		if ( ! $this->db ) {
			$this->db = new \CLOSE\JumpToCheckout\Database\Database();
		}

		// FREE version: never calculate expiry. PRO can override via filter.
		$expiry = apply_filters( 'jptc_link_expiry', 0, $name, $products );

		// Generate short token (new format: just a random ID, products stored in DB).
		$token = $this->generate_short_token();
		$url   = home_url( '/jump-to-checkout/' . $token );

		// FREE version: never calculate expiry data. PRO will add it via filter.
		$link_data = array(
			'name'         => $name,
			'token'        => $token,
			'url'          => $url,
			'products'     => $products,
			'expiry_hours' => 0,
			'expires_at'   => null,
		);

		// Allow PRO to modify link data before insert (PRO will add expiry data here).
		$link_data = apply_filters( 'jptc_link_data_before_insert', $link_data, $name, $products, $expiry );

		$link_id = $this->db->insert_link( $link_data );

		if ( ! $link_id ) {
			return false;
		}

		return array(
			'id'    => $link_id,
			'url'   => $url,
			'token' => $token,
		);
	}

	/**
	 * Encode token
	 *
	 * @param array $data Data to encode.
	 * @return string
	 */
	private function encode_token( $data ) {
		$json    = wp_json_encode( $data );
		$encoded = base64_encode( $json ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		$hash    = hash_hmac( 'sha256', $encoded, $this->secret_key );

		return base64_encode( $encoded . '.' . $hash ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Decode token
	 *
	 * @param string $token Token to decode.
	 * @return array|false
	 */
	private function decode_token( $token ) {
		$decoded = base64_decode( $token ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

		if ( ! $decoded ) {
			return false;
		}

		$parts = explode( '.', $decoded );

		if ( 2 !== count( $parts ) ) {
			return false;
		}

		list( $encoded, $hash ) = $parts;

		// Verify hash.
		$expected_hash = hash_hmac( 'sha256', $encoded, $this->secret_key );

		if ( ! hash_equals( $expected_hash, $hash ) ) {
			return false;
		}

		$json = base64_decode( $encoded ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		$data = json_decode( $json, true );

		if ( ! $data ) {
			return false;
		}

		// Check expiry. PRO handles this via filter (FREE tokens never expire).
		if ( isset( $data['exp'] ) && 0 !== $data['exp'] && $data['exp'] < time() ) {
			// Allow PRO to override expiry check.
			$is_valid = apply_filters( 'jptc_token_expiry_check', false, $data );
			if ( ! $is_valid ) {
				return false;
			}
		}

		return $data;
	}

	/**
	 * Handle checkout link
	 *
	 * @return void
	 */
	public function handle_checkout_link() {
		// Check if WooCommerce is active.
		if ( ! function_exists( 'WC' ) ) {
			return;
		}

		$token = get_query_var( 'jptc_token' );

		if ( empty( $token ) ) {
			return;
		}

		// Ensure database is initialized.
		if ( ! $this->db ) {
			$this->db = new \CLOSE\JumpToCheckout\Database\Database();
		}

		// Get link from database.
		$link = $this->db->get_link_by_token( $token );

		if ( ! $link ) {
			wp_die(
				esc_html__( 'Invalid checkout link.', 'jump-to-checkout' ),
				esc_html__( 'Error', 'jump-to-checkout' ),
				array( 'response' => 403 )
			);
		}

		// Check if link is active.
		if ( 'active' !== $link->status ) {
			wp_die(
				esc_html__( 'This checkout link has been disabled.', 'jump-to-checkout' ),
				esc_html__( 'Error', 'jump-to-checkout' ),
				array( 'response' => 403 )
			);
		}

		// Check if link has expired. PRO handles this via filter (FREE never expires).
		$expired = apply_filters( 'jptc_link_is_expired', false, $link );

		if ( $expired ) {
			wp_die(
				esc_html__( 'This checkout link has expired.', 'jump-to-checkout' ),
				esc_html__( 'Error', 'jump-to-checkout' ),
				array( 'response' => 403 )
			);
		}

		// Increment visit count.
		$this->db->increment_visits( $link->id );

		// Store link ID in session for conversion tracking.
		if ( WC()->session ) {
			WC()->session->set( 'jptc_link_id', $link->id );
		}

		// Also store in cookie as backup.
		$cookie_path   = defined( 'COOKIEPATH' ) ? COOKIEPATH : '/';
		$cookie_domain = defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '';
		setcookie( 'jptc_link_id', $link->id, time() + DAY_IN_SECONDS, $cookie_path, $cookie_domain, is_ssl(), true );

		// Check if token is new short format or old long format.
		if ( $this->is_short_token( $token ) ) {
			// New format: products are stored in database.
			$data = array(
				'products' => json_decode( $link->products, true ),
			);
		} else {
			// Old format: decode token to get product data (backward compatibility).
			$data = $this->decode_token( $token );

			if ( false === $data ) {
				wp_die(
					esc_html__( 'Invalid or expired checkout link.', 'jump-to-checkout' ),
					esc_html__( 'Error', 'jump-to-checkout' ),
					array( 'response' => 403 )
				);
			}
		}

		// Clear cart.
		WC()->cart->empty_cart();

		// Track products that couldn't be added.
		$out_of_stock   = array();
		$added_products = 0;

		// Add products to cart.
		if ( isset( $data['products'] ) && is_array( $data['products'] ) ) {
			foreach ( $data['products'] as $product ) {
				if ( ! isset( $product['product_id'] ) ) {
					continue;
				}

				$product_id   = absint( $product['product_id'] );
				$quantity     = isset( $product['quantity'] ) ? absint( $product['quantity'] ) : 1;
				$variation_id = isset( $product['variation_id'] ) ? absint( $product['variation_id'] ) : 0;
				$variation    = isset( $product['variation'] ) ? $product['variation'] : array();

				// Get product to check stock.
				$product_obj = wc_get_product( $variation_id ? $variation_id : $product_id );

				if ( ! $product_obj ) {
					continue;
				}

				// Check if product is in stock.
				if ( ! $product_obj->is_in_stock() ) {
					$out_of_stock[] = $product_obj->get_name();
					continue;
				}

				// Check if we have enough stock.
				if ( $product_obj->managing_stock() && $product_obj->get_stock_quantity() < $quantity ) {
					$out_of_stock[] = $product_obj->get_name() . ' (' . sprintf(
						/* translators: %d: available stock quantity */
						__( 'only %d available', 'jump-to-checkout' ),
						$product_obj->get_stock_quantity()
					) . ')';
					continue;
				}

				// Add to cart.
				$cart_item_key = WC()->cart->add_to_cart( $product_id, $quantity, $variation_id, $variation );

				if ( $cart_item_key ) {
					++$added_products;
				}
			}
		}

		// If no products were added, show error.
		if ( 0 === $added_products ) {
			$message  = esc_html__( 'Sorry, the products in this link are not available:', 'jump-to-checkout' ) . '<br><br>';
			$message .= implode( '<br>', array_map( 'esc_html', $out_of_stock ) );

			wp_die(
				wp_kses_post( $message ),
				esc_html__( 'Products Not Available', 'jump-to-checkout' ),
				array( 'response' => 200 )
			);
		}

		// If some products were added but others weren't, show warning and continue.
		if ( ! empty( $out_of_stock ) ) {
			wc_add_notice(
				sprintf(
					/* translators: %s: list of out of stock products */
					__( 'Some products could not be added to your cart because they are out of stock: %s', 'jump-to-checkout' ),
					implode( ', ', array_map( 'esc_html', $out_of_stock ) )
				),
				'notice'
			);
		}

		// Redirect to checkout.
		wp_safe_redirect( wc_get_checkout_url() );
		exit;
	}

	/**
	 * Save link ID to order meta
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function save_link_to_order( $order_id ) {
		if ( ! $order_id ) {
			return;
		}

		$link_id = null;

		// Try session first.
		if ( WC()->session ) {
			$link_id = WC()->session->get( 'jptc_link_id' );
		}

		// Try cookie if session fails.
		if ( ! $link_id && isset( $_COOKIE['jptc_link_id'] ) ) {
			$link_id = absint( $_COOKIE['jptc_link_id'] );
		}

		// Save to order meta if found.
		if ( $link_id ) {
			update_post_meta( $order_id, '_jptc_link_id', $link_id );
		}
	}

	/**
	 * Track conversion
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function track_conversion( $order_id ) {
		if ( ! $order_id ) {
			return;
		}

		// Ensure database is initialized.
		if ( ! $this->db ) {
			$this->db = new \CLOSE\JumpToCheckout\Database\Database();
		}

		$link_id = null;

		// Try to get link ID from order meta first.
		$link_id = get_post_meta( $order_id, '_jptc_link_id', true );

		// If not in meta, try session.
		if ( ! $link_id && WC()->session ) {
			$link_id = WC()->session->get( 'jptc_link_id' );
		}

		// If not in session, try cookie.
		if ( ! $link_id && isset( $_COOKIE['jptc_link_id'] ) ) {
			$link_id = absint( $_COOKIE['jptc_link_id'] );
		}

		if ( ! $link_id ) {
			return;
		}

		// Save to order meta for future reference.
		update_post_meta( $order_id, '_jptc_link_id', $link_id );

		// Check if already counted (prevent duplicates).
		$already_counted = get_post_meta( $order_id, '_jptc_conversion_counted', true );

		if ( $already_counted ) {
			return;
		}

		// Increment conversion count.
		$result = $this->db->increment_conversions( $link_id );

		// Mark as counted.
		update_post_meta( $order_id, '_jptc_conversion_counted', '1' );

		// Clear session and cookie.
		if ( WC()->session ) {
			WC()->session->set( 'jptc_link_id', null );
		}

		if ( isset( $_COOKIE['jptc_link_id'] ) ) {
			$cookie_path   = defined( 'COOKIEPATH' ) ? COOKIEPATH : '/';
			$cookie_domain = defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '';
			setcookie( 'jptc_link_id', '', time() - 3600, $cookie_path, $cookie_domain, is_ssl(), true );
		}
	}
}

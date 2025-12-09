<?php
/**
 * Direct Checkout Class
 *
 * Handles the direct checkout link functionality
 *
 * @package    CLOSE\DirectLinkCheckout\Core
 * @author     Close Marketing
 * @copyright  2025 Closemarketing
 * @version    1.0.0
 */

namespace CLOSE\DirectLinkCheckout\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Direct Checkout Class
 */
class DirectCheckout {

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
		$this->db = new \CLOSE\DirectLinkCheckout\Database\Database();

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
		$key = get_option( 'cldc_secret_key' );

		if ( ! $key ) {
			$key = wp_generate_password( 64, true, true );
			update_option( 'cldc_secret_key', $key );
		}

		return $key;
	}

	/**
	 * Add custom query vars
	 *
	 * @param array $vars Query vars.
	 * @return array
	 */
	public function add_query_vars( $vars ) {
		$vars[] = 'cldc_token';
		return $vars;
	}

	/**
	 * Add rewrite rules
	 *
	 * @return void
	 */
	public function add_rewrite_rules() {
		add_rewrite_rule(
			'^direct-checkout/([^/]+)/?$',
			'index.php?cldc_token=$matches[1]',
			'top'
		);

		// Check if we need to flush rules.
		$version = get_option( 'cldc_rewrite_version' );
		if ( CLDC_VERSION !== $version ) {
			flush_rewrite_rules();
			update_option( 'cldc_rewrite_version', CLDC_VERSION );
		}
	}

	/**
	 * Generate checkout link
	 *
	 * @param string $name Link name.
	 * @param array  $products Array of products with product_id and quantity.
	 * @param int    $expiry Expiry time in hours (0 for no expiry).
	 * @return array|false Array with link data or false on failure.
	 */
	public function generate_link( $name, $products, $expiry = 0 ) {
		// Ensure database is initialized.
		if ( ! $this->db ) {
			$this->db = new \CLOSE\DirectLinkCheckout\Database\Database();
		}

		$data = array(
			'products' => $products,
			'exp'      => 0 !== $expiry ? time() + ( $expiry * HOUR_IN_SECONDS ) : 0,
			'iss'      => 'cldc',
			'iat'      => time(),
		);

		$token = $this->encode_token( $data );
		$url   = home_url( '/direct-checkout/' . $token );

		// Save to database.
		$expires_at = 0 !== $expiry ? gmdate( 'Y-m-d H:i:s', time() + ( $expiry * HOUR_IN_SECONDS ) ) : null;

		$link_id = $this->db->insert_link(
			array(
				'name'         => $name,
				'token'        => $token,
				'url'          => $url,
				'products'     => $products,
				'expiry_hours' => $expiry,
				'expires_at'   => $expires_at,
			)
		);

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

		// Check expiry.
		if ( isset( $data['exp'] ) && 0 !== $data['exp'] && $data['exp'] < time() ) {
			return false;
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

		$token = get_query_var( 'cldc_token' );

		if ( empty( $token ) ) {
			return;
		}

		// Ensure database is initialized.
		if ( ! $this->db ) {
			$this->db = new \CLOSE\DirectLinkCheckout\Database\Database();
		}

		// Get link from database.
		$link = $this->db->get_link_by_token( $token );

		if ( ! $link ) {
			wp_die(
				esc_html__( 'Invalid checkout link.', 'direct-link-checkout' ),
				esc_html__( 'Error', 'direct-link-checkout' ),
				array( 'response' => 403 )
			);
		}

		// Check if link is active.
		if ( 'active' !== $link->status ) {
			wp_die(
				esc_html__( 'This checkout link has been disabled.', 'direct-link-checkout' ),
				esc_html__( 'Error', 'direct-link-checkout' ),
				array( 'response' => 403 )
			);
		}

		// Check if link has expired.
		if ( $link->expires_at && strtotime( $link->expires_at ) < time() ) {
			wp_die(
				esc_html__( 'This checkout link has expired.', 'direct-link-checkout' ),
				esc_html__( 'Error', 'direct-link-checkout' ),
				array( 'response' => 403 )
			);
		}

		// Increment visit count.
		$this->db->increment_visits( $link->id );

		// Store link ID in session for conversion tracking.
		if ( WC()->session ) {
			WC()->session->set( 'cldc_link_id', $link->id );
		}

		// Also store in cookie as backup.
		$cookie_set = setcookie( 'cldc_link_id', $link->id, time() + DAY_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );

		// Decode token.
		$data = $this->decode_token( $token );

		if ( false === $data ) {
			wp_die(
				esc_html__( 'Invalid or expired checkout link.', 'direct-link-checkout' ),
				esc_html__( 'Error', 'direct-link-checkout' ),
				array( 'response' => 403 )
			);
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
						__( 'only %d available', 'direct-link-checkout' ),
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
			$message  = esc_html__( 'Sorry, the products in this link are not available:', 'direct-link-checkout' ) . '<br><br>';
			$message .= implode( '<br>', array_map( 'esc_html', $out_of_stock ) );

			wp_die(
				wp_kses_post( $message ),
				esc_html__( 'Products Not Available', 'direct-link-checkout' ),
				array( 'response' => 200 )
			);
		}

		// If some products were added but others weren't, show warning and continue.
		if ( ! empty( $out_of_stock ) ) {
			wc_add_notice(
				sprintf(
					/* translators: %s: list of out of stock products */
					__( 'Some products could not be added to your cart because they are out of stock: %s', 'direct-link-checkout' ),
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
			$link_id = WC()->session->get( 'cldc_link_id' );
		}

		// Try cookie if session fails.
		if ( ! $link_id && isset( $_COOKIE['cldc_link_id'] ) ) {
			$link_id = absint( $_COOKIE['cldc_link_id'] );
		}

		// Save to order meta if found.
		if ( $link_id ) {
			update_post_meta( $order_id, '_cldc_link_id', $link_id );
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
			$this->db = new \CLOSE\DirectLinkCheckout\Database\Database();
		}

		$link_id = null;

		// Try to get link ID from order meta first.
		$link_id = get_post_meta( $order_id, '_cldc_link_id', true );

		// If not in meta, try session.
		if ( ! $link_id && WC()->session ) {
			$link_id = WC()->session->get( 'cldc_link_id' );
		}

		// If not in session, try cookie.
		if ( ! $link_id && isset( $_COOKIE['cldc_link_id'] ) ) {
			$link_id = absint( $_COOKIE['cldc_link_id'] );
		}

		if ( ! $link_id ) {
			return;
		}

		// Save to order meta for future reference.
		update_post_meta( $order_id, '_cldc_link_id', $link_id );

		// Check if already counted (prevent duplicates).
		$already_counted = get_post_meta( $order_id, '_cldc_conversion_counted', true );

		if ( $already_counted ) {
			return;
		}

		// Increment conversion count.
		$result = $this->db->increment_conversions( $link_id );

		// Mark as counted.
		update_post_meta( $order_id, '_cldc_conversion_counted', '1' );

		// Clear session and cookie.
		if ( WC()->session ) {
			WC()->session->set( 'cldc_link_id', null );
		}

		if ( isset( $_COOKIE['cldc_link_id'] ) ) {
			setcookie( 'cldc_link_id', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
		}
	}
}

<?php
/**
 * Variable Products Handler
 *
 * Enhances variable product support
 *
 * @package    CLOSE\JumpToCheckout\Core
 * @author     Close Marketing
 * @copyright  2025 Closemarketing
 * @version    1.0.0
 */

namespace CLOSE\JumpToCheckout\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Variable Products Class
 */
class VariableProducts {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'wp_ajax_jptc_get_product_variations', array( $this, 'ajax_get_product_variations' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_variable_products_script' ) );
	}

	/**
	 * AJAX: Get product variations
	 *
	 * @return void
	 */
	public function ajax_get_product_variations() {
		check_ajax_referer( 'jptc_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'jump-to-checkout' ) ) );
		}

		$product_id = isset( $_GET['product_id'] ) ? absint( $_GET['product_id'] ) : 0;

		if ( ! $product_id ) {
			wp_send_json_error( array( 'message' => __( 'Product ID is required.', 'jump-to-checkout' ) ) );
		}

		$product = wc_get_product( $product_id );

		if ( ! $product || ! $product->is_type( 'variable' ) ) {
			wp_send_json_error( array( 'message' => __( 'Product is not a variable product.', 'jump-to-checkout' ) ) );
		}

		$raw_attributes = $product->get_variation_attributes();
		$attributes     = array();
		$variations     = array();

		foreach ( $raw_attributes as $attribute_name => $options ) {
			$attributes[ $attribute_name ] = $options;
		}

		$variation_ids = $product->get_children();

		foreach ( $variation_ids as $variation_id ) {
			$variation = wc_get_product( $variation_id );
			if ( ! $variation || ! $variation->is_purchasable() ) {
				continue;
			}

			$variations[] = array(
				'variation_id' => $variation_id,
				'attributes'   => $variation->get_variation_attributes(),
				'price'        => $variation->get_price_html(),
				'stock_status' => $variation->get_stock_status(),
				'in_stock'     => $variation->is_in_stock(),
			);
		}

		wp_send_json_success(
			array(
				'attributes' => $attributes,
				'variations' => $variations,
			)
		);
	}

	/**
	 * Enqueue variable products script
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_variable_products_script( $hook ) {
		if ( 'toplevel_page_jptc-jump-to-checkout' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'jptc-variable-products',
			JTPC_PLUGIN_URL . 'assets/css/variable-products.css',
			array(),
			JTPC_VERSION
		);

		wp_enqueue_script(
			'jptc-variable-products',
			JTPC_PLUGIN_URL . 'assets/js/variable-products.js',
			array( 'jquery' ),
			JTPC_VERSION,
			true
		);
	}

	/**
	 * Normalize variation attributes for WooCommerce
	 *
	 * WooCommerce expects variation attributes in format: attribute_pa_color, attribute_size, etc.
	 *
	 * @param array $variation Variation attributes.
	 * @param int   $product_id Product ID.
	 * @param int   $variation_id Variation ID.
	 * @return array
	 */
	public function normalize_variation_attributes( $variation, $product_id, $variation_id ) {
		if ( empty( $variation ) || ! is_array( $variation ) ) {
			return $variation;
		}

		if ( $variation_id > 0 ) {
			$variation_obj = wc_get_product( $variation_id );
			if ( $variation_obj && $variation_obj->is_type( 'variation' ) ) {
				return $variation_obj->get_variation_attributes();
			}
		}

		$normalized = array();
		$product    = wc_get_product( $product_id );

		if ( ! $product ) {
			return $variation;
		}

		$product_attributes = $product->get_attributes();

		foreach ( $variation as $attr_name => $attr_value ) {
			if ( 0 === strpos( $attr_name, 'attribute_' ) ) {
				$normalized[ $attr_name ] = $attr_value;
				continue;
			}

			$found = false;
			foreach ( $product_attributes as $product_attr_name => $product_attr ) {
				$attribute_name = wc_attribute_taxonomy_name( $product_attr_name );
				if ( $attribute_name === $attr_name
					|| $product_attr_name === $attr_name
					|| str_replace( 'pa_', '', $attribute_name ) === str_replace( 'pa_', '', $attr_name )
				) {
					if ( $product_attr->is_taxonomy() ) {
						$normalized[ 'attribute_' . $attribute_name ] = $attr_value;
					} else {
						$normalized[ 'attribute_' . sanitize_title( $product_attr_name ) ] = $attr_value;
					}
					$found = true;
					break;
				}
			}

			if ( ! $found ) {
				if ( 0 === strpos( $attr_name, 'pa_' ) ) {
					$normalized[ 'attribute_' . $attr_name ] = $attr_value;
				} else {
					$normalized[ 'attribute_' . sanitize_title( $attr_name ) ] = $attr_value;
				}
			}
		}

		return $normalized;
	}
}

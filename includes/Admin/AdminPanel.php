<?php
/**
 * Admin Panel Class
 *
 * Handles the admin panel for generating checkout links
 *
 * @package    CLOSE\JumpToCheckout\Admin
 * @author     Close Marketing
 * @copyright  2025 Closemarketing
 * @version    1.0.0
 */

namespace CLOSE\JumpToCheckout\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Admin Panel Class
 */
class AdminPanel {

	/**
	 * Jump to Checkout instance
	 *
	 * @var \CLOSE\JumpToCheckout\Core\JumpToCheckout
	 */
	private $direct_checkout;

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		add_action( 'wp_ajax_jptc_generate_link', array( $this, 'ajax_generate_link' ) );
		add_action( 'wp_ajax_jptc_search_products', array( $this, 'ajax_search_products' ) );

		$this->direct_checkout = new \CLOSE\JumpToCheckout\Core\JumpToCheckout();
	}

	/**
	 * Add admin menu
	 *
	 * @return void
	 */
	public function add_admin_menu() {
		add_menu_page(
			__( 'Jump to Checkout', 'jump-to-checkout' ),
			__( 'Jump to Checkout', 'jump-to-checkout' ),
			'manage_woocommerce',
			'jptc-jump-to-checkout',
			array( $this, 'render_admin_page' ),
			'dashicons-cart',
			56
		);

		add_submenu_page(
			'jptc-jump-to-checkout',
			__( 'Generate Link', 'jump-to-checkout' ),
			__( 'Generate Link', 'jump-to-checkout' ),
			'manage_woocommerce',
			'jptc-jump-to-checkout',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Enqueue admin scripts
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_admin_scripts( $hook ) {
		if ( 'toplevel_page_jptc-jump-to-checkout' !== $hook && 'jump-to-checkout_page_jptc-manage-links' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'jptc-admin',
			JTPC_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			JTPC_VERSION
		);

		wp_enqueue_script(
			'jptc-admin',
			JTPC_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			JTPC_VERSION,
			true
		);

		wp_localize_script(
			'jptc-admin',
			'jptcAdmin',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'jptc_admin_nonce' ),
				'i18n'     => array(
					'copy_success'           => __( 'Link copied to clipboard!', 'jump-to-checkout' ),
					'copy_error'             => __( 'Failed to copy link.', 'jump-to-checkout' ),
					'generate_error'         => __( 'Error generating link.', 'jump-to-checkout' ),
					'search_placeholder'     => __( 'Search products...', 'jump-to-checkout' ),
					'no_products'            => __( 'No products found.', 'jump-to-checkout' ),
					'no_link_name'           => __( 'Please enter a link name.', 'jump-to-checkout' ),
					'no_products_selected'   => __( 'Please select at least one product.', 'jump-to-checkout' ),
					'no_link_in_response'    => __( 'No link in response', 'jump-to-checkout' ),
					'no_products_label'      => __( 'No products selected.', 'jump-to-checkout' ),
					'remove_button'          => __( 'Remove', 'jump-to-checkout' ),
					'variable_product_error' => __( 'Variable products cannot be added directly. Please select a specific variation.', 'jump-to-checkout' ),
					'select_variation'       => __( 'Select Variation', 'jump-to-checkout' ),
					'choose_option'          => __( 'Choose an option', 'jump-to-checkout' ),
					'selected_variation'     => __( 'Selected variation:', 'jump-to-checkout' ),
				),
			)
		);

		// Allow other classes to enqueue their scripts for this hook.
		do_action( 'jptc_enqueue_admin_scripts', $hook );

		wp_enqueue_style(
			'jptc-select2',
			JTPC_PLUGIN_URL . 'vendor/select2/select2/dist/css/select2.min.css',
			array(),
			'4.1.0'
		);
		wp_enqueue_script(
			'jptc-select2',
			JTPC_PLUGIN_URL . 'vendor/select2/select2/dist/js/select2.min.js',
			array( 'jquery' ),
			'4.1.0',
			true
		);
	}

	/**
	 * Render admin page
	 *
	 * @return void
	 */
	public function render_admin_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'jump-to-checkout' ) );
		}

		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Jump to Checkout Link Generator', 'jump-to-checkout' ); ?></h1>
			<p><?php echo esc_html__( 'Generate secure links that automatically add products to cart and redirect to checkout.', 'jump-to-checkout' ); ?></p>

			<div class="jump-to-checkout-admin-container">
				<div class="jump-to-checkout-form-section">
					<h2><?php echo esc_html__( 'Generate New Link', 'jump-to-checkout' ); ?></h2>

					<div class="jump-to-checkout-link-name-section">
						<label><?php echo esc_html__( 'Link Name', 'jump-to-checkout' ); ?></label>
						<input type="text" class="jump-to-checkout-link-name" placeholder="<?php echo esc_attr__( 'e.g. Summer Campaign 2025', 'jump-to-checkout' ); ?>" />
						<p class="description"><?php echo esc_html__( 'Give this link a name to identify it later in the statistics.', 'jump-to-checkout' ); ?></p>
					</div>

					<h3><?php echo esc_html__( 'Select Products', 'jump-to-checkout' ); ?></h3>

					<div class="jump-to-checkout-products-container">
						<div class="jump-to-checkout-product-row">
							<div class="jump-to-checkout-product-field">
								<label><?php echo esc_html__( 'Product', 'jump-to-checkout' ); ?></label>
								<select class="jump-to-checkout-product-search" style="width: 100%;"></select>
							</div>
							<div class="jump-to-checkout-quantity-field">
								<label><?php echo esc_html__( 'Quantity', 'jump-to-checkout' ); ?></label>
								<input type="number" class="jump-to-checkout-quantity" value="1" min="1" />
							</div>
							<div class="jump-to-checkout-actions-field">
								<button type="button" class="button jump-to-checkout-add-product">
									<?php echo esc_html__( 'Add Product', 'jump-to-checkout' ); ?>
								</button>
							</div>
						</div>
					</div>

					<div class="jump-to-checkout-selected-products">
						<h3><?php echo esc_html__( 'Selected Products', 'jump-to-checkout' ); ?></h3>
						<table class="wp-list-table widefat fixed striped">
							<thead>
								<tr>
									<th><?php echo esc_html__( 'Product', 'jump-to-checkout' ); ?></th>
									<th><?php echo esc_html__( 'Quantity', 'jump-to-checkout' ); ?></th>
									<th><?php echo esc_html__( 'Actions', 'jump-to-checkout' ); ?></th>
								</tr>
							</thead>
							<tbody class="jump-to-checkout-selected-products-body">
								<tr class="no-items">
									<td colspan="3"><?php echo esc_html__( 'No products selected.', 'jump-to-checkout' ); ?></td>
								</tr>
							</tbody>
						</table>
					</div>

					<?php do_action( 'jptc_render_expiry_section', true ); ?>
					<?php do_action( 'jptc_render_coupon_section', true ); ?>

					<div class="jump-to-checkout-generate-section">
						<button type="button" class="button button-primary button-large jump-to-checkout-generate-link">
							<?php echo esc_html__( 'Generate Link', 'jump-to-checkout' ); ?>
						</button>
					</div>

					<div class="jump-to-checkout-result-section" style="display: none;">
						<h3><?php echo esc_html__( 'Generated Link', 'jump-to-checkout' ); ?></h3>
						<div class="jump-to-checkout-result-container">
							<input type="text" class="jump-to-checkout-generated-link" readonly />
							<button type="button" class="button jump-to-checkout-copy-link">
								<?php echo esc_html__( 'Copy Link', 'jump-to-checkout' ); ?>
							</button>
						</div>
						<div class="jump-to-checkout-result-info">
							<p class="description">
								<?php echo esc_html__( 'Share this link with your customers. When they click it, the products will be added to their cart and they will be redirected to checkout.', 'jump-to-checkout' ); ?>
							</p>
						</div>
					</div>
				</div>

				<div class="jump-to-checkout-info-section">
					<div class="jump-to-checkout-promo-box">
						<p class="jump-to-checkout-promo-by"><?php esc_html_e( 'Built by', 'jump-to-checkout' ); ?> <strong>Close Technology</strong></p>
						<p class="jump-to-checkout-promo-tagline">
							<?php esc_html_e( 'Need a custom WooCommerce feature? We build tailored solutions for your store.', 'jump-to-checkout' ); ?>
						</p>
						<ul class="jump-to-checkout-promo-list">
							<li><?php esc_html_e( 'Custom plugins &amp; integrations', 'jump-to-checkout' ); ?></li>
							<li><?php esc_html_e( 'WooCommerce development', 'jump-to-checkout' ); ?></li>
							<li><?php esc_html_e( 'Performance &amp; optimization', 'jump-to-checkout' ); ?></li>
						</ul>
						<a href="https://close.technology/en/services/custom-development/?utm_source=wp-admin&utm_medium=plugin&utm_campaign=jump-to-checkout&utm_content=sidebar-widget"
							class="button button-primary jump-to-checkout-promo-cta"
							target="_blank"
							rel="noopener noreferrer">
							<?php esc_html_e( 'Custom Development', 'jump-to-checkout' ); ?> &rarr;
						</a>
						<p class="jump-to-checkout-promo-footer">
							<a href="https://close.technology/?utm_source=wp-admin&utm_medium=plugin&utm_campaign=jump-to-checkout&utm_content=sidebar-footer"
								target="_blank"
								rel="noopener noreferrer">close.technology</a>
						</p>
					</div>

					<div class="jump-to-checkout-rating-box">
						<p class="jump-to-checkout-rating-stars">⭐⭐⭐⭐⭐</p>
						<h3><?php esc_html_e( 'Enjoying Jump to Checkout?', 'jump-to-checkout' ); ?></h3>
						<p><?php esc_html_e( 'If the plugin is useful, a quick review on WordPress.org helps others discover it — and motivates us to keep improving it.', 'jump-to-checkout' ); ?></p>
						<a href="<?php echo esc_url( \CLOSE\JumpToCheckout\Admin\RatingNotice::REVIEW_URL ); ?>"
							class="button button-primary jump-to-checkout-rating-cta"
							target="_blank"
							rel="noopener noreferrer">
							<?php esc_html_e( 'Leave a Review', 'jump-to-checkout' ); ?> &#9733;
						</a>
					</div>

					<div class="jump-to-checkout-info-box">
						<h3><?php echo esc_html__( 'How it works', 'jump-to-checkout' ); ?></h3>
						<ol>
							<li><?php echo esc_html__( 'Select the products you want to include in the link', 'jump-to-checkout' ); ?></li>
							<li><?php echo esc_html__( 'Set the quantity for each product', 'jump-to-checkout' ); ?></li>
							<li><?php echo esc_html__( 'Choose if the link should expire', 'jump-to-checkout' ); ?></li>
							<li><?php echo esc_html__( 'Click "Generate Link"', 'jump-to-checkout' ); ?></li>
							<li><?php echo esc_html__( 'Share the link with your customers', 'jump-to-checkout' ); ?></li>
						</ol>
					</div>

					<div class="jump-to-checkout-info-box">
						<h3><?php echo esc_html__( 'Security', 'jump-to-checkout' ); ?></h3>
						<p>
							<?php echo esc_html__( 'All links are secured with cryptographic signatures to prevent tampering. Each link contains encoded product information that cannot be modified without invalidating the link.', 'jump-to-checkout' ); ?>
						</p>
					</div>

					<div class="jump-to-checkout-info-box">
						<h3><?php echo esc_html__( 'Link Format', 'jump-to-checkout' ); ?></h3>
						<p>
							<code><?php echo esc_html( home_url( '/jump-to-checkout/{token}' ) ); ?></code>
						</p>
					</div>

				
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Sanitize products data from JSON
	 *
	 * @param string $json_data JSON string with products data.
	 * @return array Sanitized products array.
	 */
	private function sanitize_products_data( $json_data ) {
		if ( empty( $json_data ) || ! is_string( $json_data ) ) {
			return array();
		}

		$decoded = json_decode( $json_data, true );

		if ( null === $decoded || JSON_ERROR_NONE !== json_last_error() ) {
			return array();
		}

		if ( ! is_array( $decoded ) ) {
			return array();
		}

		$sanitized = array();
		foreach ( $decoded as $product ) {
			if ( ! is_array( $product ) ) {
				continue;
			}

			if ( ! isset( $product['product_id'] ) || ! isset( $product['quantity'] ) ) {
				continue;
			}

			$sanitized_product = array(
				'product_id' => absint( $product['product_id'] ),
				'quantity'   => absint( $product['quantity'] ),
			);

			if ( isset( $product['variation_id'] ) ) {
				$sanitized_product['variation_id'] = absint( $product['variation_id'] );
			}

			if ( isset( $product['variation'] ) && is_array( $product['variation'] ) ) {
				$sanitized_product['variation'] = array_map( 'sanitize_text_field', $product['variation'] );
			}

			if ( isset( $product['name'] ) ) {
				$sanitized_product['name'] = sanitize_text_field( $product['name'] );
			}

			if ( $sanitized_product['product_id'] > 0 && $sanitized_product['quantity'] > 0 ) {
				$sanitized[] = $sanitized_product;
			}
		}

		return $sanitized;
	}

	/**
	 * AJAX: Generate link
	 *
	 * @return void
	 */
	public function ajax_generate_link() {
		check_ajax_referer( 'jptc_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'jump-to-checkout' ) ) );
		}

		$name   = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$expiry = apply_filters( 'jptc_ajax_link_expiry', 0, $_POST );

		if ( empty( $name ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a link name.', 'jump-to-checkout' ) ) );
		}

		$products = $this->sanitize_products_data(
			isset( $_POST['products'] ) ? wp_unslash( $_POST['products'] ) : '' // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized in sanitize_products_data method.
		);

		if ( empty( $products ) || ! is_array( $products ) ) {
			wp_send_json_error( array( 'message' => __( 'No products selected.', 'jump-to-checkout' ) ) );
		}

		// Allow AutomaticCoupons to read POST data before link is created.
		$link_data = apply_filters( 'jptc_ajax_link_data', array(), $_POST );

		$result = $this->direct_checkout->generate_link( $name, $products, $expiry );

		if ( ! $result || ! isset( $result['url'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Error generating link.', 'jump-to-checkout' ) ) );
		}

		// Allow AutomaticCoupons to save coupon after link creation.
		do_action( 'jptc_after_link_created', $result['id'], $link_data );

		wp_send_json_success( array( 'link' => $result['url'] ) );
	}

	/**
	 * AJAX: Search products
	 *
	 * @return void
	 */
	public function ajax_search_products() {
		check_ajax_referer( 'jptc_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'jump-to-checkout' ) ) );
		}

		$search = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';

		$args = array(
			'post_type'      => array( 'product', 'product_variation' ),
			'posts_per_page' => 20,
			'post_status'    => 'publish',
			's'              => $search,
		);

		$products = new \WP_Query( $args );
		$results  = array();

		if ( $products->have_posts() ) {
			while ( $products->have_posts() ) {
				$products->the_post();
				$product = wc_get_product( get_the_ID() );

				if ( ! $product ) {
					continue;
				}

				$product_name = $product->get_name();

				if ( $product->get_sku() ) {
					$product_name .= ' (' . $product->get_sku() . ')';
				}

				// Variable products are never directly selectable; user must choose a variation.
				if ( $product->is_type( 'variable' ) ) {
					$product_name .= ' ' . __( '[Variable Product - Select variation below]', 'jump-to-checkout' );

					$results[] = array(
						'id'       => $product->get_id(),
						'text'     => wp_strip_all_tags( $product_name ),
						'disabled' => true,
					);
					continue;
				}

				if ( $product->is_type( 'variation' ) ) {
					$parent_id   = $product->get_parent_id();
					$parent      = wc_get_product( $parent_id );
					$parent_name = $parent ? $parent->get_name() : '';
					$attributes  = wc_get_formatted_variation( $product, true, false );

					$display_name = $parent_name . ( $attributes ? ' - ' . $attributes : '' );

					$result = array(
						'id'           => $product->get_id(),
						'text'         => wp_strip_all_tags( $display_name ),
						'variation_id' => $product->get_id(),
						'product_id'   => $parent_id,
						'variation'    => $product->get_variation_attributes(),
					);

					$results[] = apply_filters( 'jptc_product_search_result', $result, $product );
					continue;
				}

				$result    = array(
					'id'   => $product->get_id(),
					'text' => wp_strip_all_tags( $product_name ),
				);
				$results[] = apply_filters( 'jptc_product_search_result', $result, $product );
			}
			wp_reset_postdata();
		}

		$results = apply_filters( 'jptc_ajax_search_products', $results, $search );

		wp_send_json( array( 'results' => $results ) );
	}
}

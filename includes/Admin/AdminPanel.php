<?php
/**
 * Admin Panel Class
 *
 * Handles the admin panel for generating checkout links - FREE VERSION
 *
 * @package    CLOSE\JumpToCheckout\Admin
 * @author     Close Marketing
 * @copyright  2025 Closemarketing
 * @version    1.0.0
 */

namespace CLOSE\JumpToCheckout\Admin;

use CLOSE\JumpToCheckout\Core\Features;

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
		// Add admin menu.
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );

		// Enqueue admin scripts.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );

		// Handle AJAX requests.
		add_action( 'wp_ajax_jptc_generate_link', array( $this, 'ajax_generate_link' ) );
		add_action( 'wp_ajax_jptc_search_products', array( $this, 'ajax_search_products' ) );
		add_action( 'wp_ajax_jptc_dismiss_upgrade_widget', array( $this, 'ajax_dismiss_upgrade_widget' ) );

		// Initialize Jump to Checkout.
		$this->direct_checkout = new \CLOSE\JumpToCheckout\Core\JumpToCheckout();
	}

	/**
	 * Add admin menu
	 *
	 * @return void
	 */
	public function add_admin_menu() {
		// Main menu.
		add_menu_page(
			__( 'Jump to Checkout', 'jump-to-checkout' ),
			__( 'Jump to Checkout', 'jump-to-checkout' ),
			'manage_woocommerce',
			'jptc-jump-to-checkout',
			array( $this, 'render_admin_page' ),
			'dashicons-cart',
			56
		);

		// Submenu: Generate Link.
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
				'ajax_url'     => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( 'jptc_admin_nonce' ),
				'dismissNonce' => wp_create_nonce( 'jptc_dismiss_upgrade' ),
				'is_pro'       => Features::is_pro(),
				'upgrade_url'  => Features::get_upgrade_url(),
				'i18n'         => array(
					'copy_success'         => __( 'Link copied to clipboard!', 'jump-to-checkout' ),
					'copy_error'           => __( 'Failed to copy link.', 'jump-to-checkout' ),
					'generate_error'       => __( 'Error generating link.', 'jump-to-checkout' ),
					'search_placeholder'   => __( 'Search products...', 'jump-to-checkout' ),
					'no_products'          => __( 'No products found.', 'jump-to-checkout' ),
					'no_link_name'         => __( 'Please enter a link name.', 'jump-to-checkout' ),
					'no_products_selected' => __( 'Please select at least one product.', 'jump-to-checkout' ),
					'no_link_in_response'  => __( 'No link in response', 'jump-to-checkout' ),
					'no_products_label'    => __( 'No products selected.', 'jump-to-checkout' ),
					'remove_button'        => __( 'Remove', 'jump-to-checkout' ),
				),
			)
		);

		// Select2 for product search (local copy).
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

			<?php if ( ! Features::is_pro() ) : ?>
				<?php $this->render_upgrade_widget(); ?>
			<?php endif; ?>

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

					<?php
					// Only show expiry section if PRO is active. PRO will render this via filter.
					if ( Features::is_pro() ) {
						// PRO will render expiry UI via filter.
						do_action( 'jptc_render_expiry_section', true );
					}
					?>

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

					<?php if ( ! Features::is_pro() ) : ?>
						<div class="jump-to-checkout-free-footer">
							<p><strong><?php esc_html_e( 'You are using Jump to Checkout FREE', 'jump-to-checkout' ); ?></strong></p>
							<p><?php esc_html_e( 'Unlock advanced features like analytics, export, automatic coupons, templates, API access, and more with PRO.', 'jump-to-checkout' ); ?></p>
							<a href="<?php echo esc_url( Features::get_upgrade_url() ); ?>" class="button button-primary" target="_blank">
								<?php esc_html_e( 'Upgrade to PRO', 'jump-to-checkout' ); ?>
							</a>
						</div>
					<?php endif; ?>
				</div>

				<div class="jump-to-checkout-info-section">
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
	 * Render upgrade widget
	 *
	 * @return void
	 */
	private function render_upgrade_widget() {
		// Check if user dismissed the widget.
		if ( get_user_meta( get_current_user_id(), 'jptc_upgrade_widget_dismissed', true ) ) {
			return;
		}
		?>
		<div class="notice notice-info is-dismissible jump-to-checkout-upgrade-widget" data-dismissible="jump-to-checkout-upgrade-widget">
			<button type="button" class="notice-dismiss jump-to-checkout-dismiss-upgrade">
				<span class="screen-reader-text"><?php esc_html_e( 'Dismiss this notice.', 'jump-to-checkout' ); ?></span>
			</button>
			<div class="jump-to-checkout-upgrade-content">
				<div class="jump-to-checkout-upgrade-header">
					<h3>🚀 <?php esc_html_e( 'Unlock Full Potential with PRO', 'jump-to-checkout' ); ?></h3>
				</div>
				<div class="jump-to-checkout-upgrade-columns">
					<div class="jump-to-checkout-features-column">
						<ul class="jump-to-checkout-features-list">
							<li>⭐ <?php esc_html_e( 'Advanced analytics with charts', 'jump-to-checkout' ); ?></li>
							<li>⭐ <?php esc_html_e( 'Export to CSV/Excel', 'jump-to-checkout' ); ?></li>
							<li>⭐ <?php esc_html_e( 'Automatic coupons', 'jump-to-checkout' ); ?></li>
							<li>⭐ <?php esc_html_e( 'Link expiration', 'jump-to-checkout' ); ?></li>
						</ul>
					</div>
					<div class="jump-to-checkout-features-column">
						<ul class="jump-to-checkout-features-list">
							<li>⭐ <?php esc_html_e( 'Templates & UTM tracking', 'jump-to-checkout' ); ?></li>
							<li>⭐ <?php esc_html_e( 'API & Webhooks', 'jump-to-checkout' ); ?></li>
							<li>⭐ <?php esc_html_e( 'Scheduled links', 'jump-to-checkout' ); ?></li>
							<li>⭐ <?php esc_html_e( 'Priority support', 'jump-to-checkout' ); ?></li>
						</ul>
					</div>
					<div class="jump-to-checkout-cta-column">
						<a href="<?php echo esc_url( Features::get_upgrade_url() ); ?>" class="button button-primary button-large" target="_blank">
							<?php esc_html_e( 'Upgrade to PRO', 'jump-to-checkout' ); ?>
						</a>
						<p class="jump-to-checkout-guarantee">
							<small>✓ <?php esc_html_e( '30-day money back guarantee', 'jump-to-checkout' ); ?></small>
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

		// Decode JSON.
		$decoded = json_decode( $json_data, true );

		// Validate JSON decode was successful.
		if ( null === $decoded || JSON_ERROR_NONE !== json_last_error() ) {
			return array();
		}

		// Ensure it's an array.
		if ( ! is_array( $decoded ) ) {
			return array();
		}

		// Sanitize each product.
		$sanitized = array();
		foreach ( $decoded as $product ) {
			if ( ! is_array( $product ) ) {
				continue;
			}

			// Validate required fields exist.
			if ( ! isset( $product['product_id'] ) || ! isset( $product['quantity'] ) ) {
				continue;
			}

			// Sanitize fields.
			$sanitized_product = array(
				'product_id' => absint( $product['product_id'] ),
				'quantity'   => absint( $product['quantity'] ),
			);

			// Add name if present.
			if ( isset( $product['name'] ) ) {
				$sanitized_product['name'] = sanitize_text_field( $product['name'] );
			}

			// Only add if product_id and quantity are valid.
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

		$name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		// FREE version: always use 0 expiry. PRO can override via filter.
		$expiry = 0;
		if ( Features::is_pro() ) {
			$expiry = isset( $_POST['expiry'] ) ? absint( $_POST['expiry'] ) : 0;
		}
		// Allow PRO to modify expiry via filter.
		$expiry = apply_filters( 'jptc_ajax_link_expiry', $expiry, $_POST );

		if ( empty( $name ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a link name.', 'jump-to-checkout' ) ) );
		}

		// Sanitize and validate products data.
		$products = $this->sanitize_products_data(
			isset( $_POST['products'] ) ? wp_unslash( $_POST['products'] ) : '' // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized in sanitize_products_data method.
		);

		if ( empty( $products ) || ! is_array( $products ) ) {
			wp_send_json_error( array( 'message' => __( 'No products selected.', 'jump-to-checkout' ) ) );
		}

		$result = $this->direct_checkout->generate_link( $name, $products, $expiry );

		if ( ! $result || ! isset( $result['url'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Error generating link.', 'jump-to-checkout' ) ) );
		}

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

				// Get product name and strip HTML.
				$product_name = $product->get_name();

				// Add SKU if available.
				if ( $product->get_sku() ) {
					$product_name .= ' (' . $product->get_sku() . ')';
				}

				$results[] = array(
					'id'   => $product->get_id(),
					'text' => wp_strip_all_tags( $product_name ),
				);
			}
			wp_reset_postdata();
		}

		wp_send_json( array( 'results' => $results ) );
	}

	/**
	 * AJAX: Dismiss upgrade widget
	 *
	 * @return void
	 */
	public function ajax_dismiss_upgrade_widget() {
		check_ajax_referer( 'jptc_dismiss_upgrade', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error();
		}

		update_user_meta( get_current_user_id(), 'jptc_upgrade_widget_dismissed', true );
		wp_send_json_success();
	}
}


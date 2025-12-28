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
	 * @var \CLOSE\JumpToCheckout\Core\DirectCheckout
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

		// Show admin notices.
		add_action( 'admin_notices', array( $this, 'show_limit_notices' ) );

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

		// Submenu: Upgrade to PRO.
		if ( ! Features::is_pro() ) {
			add_submenu_page(
				'jptc-jump-to-checkout',
				__( '⭐ Upgrade to PRO', 'jump-to-checkout' ),
				__( '⭐ Upgrade to PRO', 'jump-to-checkout' ),
				'manage_woocommerce',
				'jptc-upgrade',
				array( $this, 'render_upgrade_page' )
			);
		}
	}

	/**
	 * Show limit notices
	 *
	 * @return void
	 */
	public function show_limit_notices() {
		$screen = get_current_screen();
		if ( ! $screen || 'toplevel_page_jptc-jump-to-checkout' !== $screen->id ) {
			return;
		}

		if ( Features::is_pro() ) {
			return;
		}

		// Check if close to limit.
		$active_links = Features::get_active_links_count();
		$max_links    = Features::max_links();

		if ( $active_links >= $max_links ) {
			?>
			<div class="notice notice-error is-dismissible">
				<p>
					<strong><?php esc_html_e( 'Limit Reached!', 'jump-to-checkout' ); ?></strong>
					<?php
					printf(
						/* translators: %d: max links */
						esc_html__( 'You have reached the limit of %d active links in the FREE version.', 'jump-to-checkout' ),
						(int) $max_links
					);
					?>
					<a href="<?php echo esc_url( Features::get_upgrade_url() ); ?>" class="button button-primary" style="margin-left: 10px;" target="_blank">
						<?php esc_html_e( 'Upgrade to PRO for unlimited links', 'jump-to-checkout' ); ?>
					</a>
				</p>
			</div>
			<?php
		} elseif ( $active_links >= ( $max_links - 1 ) ) {
			?>
			<div class="notice notice-warning is-dismissible">
				<p>
					<strong><?php esc_html_e( 'Almost at the Limit!', 'jump-to-checkout' ); ?></strong>
					<?php
					printf(
						/* translators: %1$d: active links, %2$d: max links */
						esc_html__( 'You have %1$d of %2$d active links. Consider upgrading to PRO for unlimited links.', 'jump-to-checkout' ),
						(int) $active_links,
						(int) $max_links
					);
					?>
					<a href="<?php echo esc_url( Features::get_upgrade_url() ); ?>" target="_blank">
						<?php esc_html_e( 'View PRO plans', 'jump-to-checkout' ); ?>
					</a>
				</p>
			</div>
			<?php
		}
	}

	/**
	 * Enqueue admin scripts
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_admin_scripts( $hook ) {
		if ( 'toplevel_page_jptc-jump-to-checkout' !== $hook && 'jump-to-checkout_page_jptc-upgrade' !== $hook && 'jump-to-checkout_page_jptc-manage-links' !== $hook ) {
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
				'is_pro'       => Features::is_pro(),
				'max_links'    => Features::max_links(),
				'max_products' => Features::max_products_per_link(),
				'upgrade_url'  => Features::get_upgrade_url(),
				'i18n'         => array(
					'copy_success'         => __( 'Link copied to clipboard!', 'jump-to-checkout' ),
					'copy_error'           => __( 'Failed to copy link.', 'jump-to-checkout' ),
					'generate_error'       => __( 'Error generating link.', 'jump-to-checkout' ),
					'search_placeholder'   => __( 'Search products...', 'jump-to-checkout' ),
					'no_products'          => __( 'No products found.', 'jump-to-checkout' ),
					'no_link_name'         => __( 'Please enter a link name.', 'jump-to-checkout' ),
					'no_products_selected' => __( 'Please select at least one product.', 'jump-to-checkout' ),
					'limit_reached'        => __( 'You have reached the active links limit in the FREE version.', 'jump-to-checkout' ),
					'max_products_reached' => __( 'The FREE version allows only 1 product per link. Upgrade to PRO for multiple products.', 'jump-to-checkout' ),
					'upgrade_confirm'      => __( 'Do you want to upgrade to PRO now?', 'jump-to-checkout' ),
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

		$can_create   = Features::can_create_link();
		$max_products = Features::max_products_per_link();
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

					<?php if ( ! $can_create ) : ?>
					<div class="notice notice-error">
						<p>
							<strong><?php esc_html_e( 'Limit Reached!', 'jump-to-checkout' ); ?></strong>
							<?php esc_html_e( 'You cannot create more links in the FREE version. Please deactivate or delete an existing link, or upgrade to PRO.', 'jump-to-checkout' ); ?>
							<a href="<?php echo esc_url( Features::get_upgrade_url() ); ?>" class="button button-primary" style="margin-left: 10px;" target="_blank">
								<?php esc_html_e( 'Upgrade to PRO', 'jump-to-checkout' ); ?>
							</a>
						</p>
					</div>
					<?php endif; ?>

					<?php if ( 1 === $max_products ) : ?>
					<div class="jump-to-checkout-limit-message">
						<p>
							<strong><?php esc_html_e( 'FREE Version:', 'jump-to-checkout' ); ?></strong>
							<?php esc_html_e( 'You can only add 1 product per link.', 'jump-to-checkout' ); ?>
							<a href="<?php echo esc_url( Features::get_upgrade_url() ); ?>" target="_blank">
								<?php esc_html_e( 'Upgrade to PRO for unlimited products', 'jump-to-checkout' ); ?>
							</a>
						</p>
					</div>
					<?php endif; ?>

					<div class="jump-to-checkout-link-name-section">
						<label><?php echo esc_html__( 'Link Name', 'jump-to-checkout' ); ?></label>
						<input type="text" class="jump-to-checkout-link-name" placeholder="<?php echo esc_attr__( 'e.g. Summer Campaign 2025', 'jump-to-checkout' ); ?>" <?php echo ! $can_create ? 'disabled' : ''; ?> />
						<p class="description"><?php echo esc_html__( 'Give this link a name to identify it later in the statistics.', 'jump-to-checkout' ); ?></p>
					</div>

					<h3><?php echo esc_html__( 'Select Products', 'jump-to-checkout' ); ?></h3>

					<div class="jump-to-checkout-products-container">
						<div class="jump-to-checkout-product-row">
							<div class="jump-to-checkout-product-field">
								<label><?php echo esc_html__( 'Product', 'jump-to-checkout' ); ?></label>
								<select class="jump-to-checkout-product-search" style="width: 100%;" <?php echo ! $can_create ? 'disabled' : ''; ?>></select>
							</div>
							<div class="jump-to-checkout-quantity-field">
								<label><?php echo esc_html__( 'Quantity', 'jump-to-checkout' ); ?></label>
								<input type="number" class="jump-to-checkout-quantity" value="1" min="1" <?php echo ! $can_create ? 'disabled' : ''; ?> />
							</div>
							<div class="jump-to-checkout-actions-field">
								<button type="button" class="button jump-to-checkout-add-product" <?php echo ! $can_create ? 'disabled' : ''; ?>>
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

					<div class="jump-to-checkout-expiry-section">
						<h3><?php echo esc_html__( 'Link Expiry', 'jump-to-checkout' ); ?></h3>
						<label>
							<input type="radio" name="jptc_expiry_type" value="never" checked <?php echo ! $can_create ? 'disabled' : ''; ?> />
							<?php echo esc_html__( 'Never expires', 'jump-to-checkout' ); ?>
						</label>
						<label>
							<input type="radio" name="jptc_expiry_type" value="custom" disabled />
							<?php echo esc_html__( 'Expires in', 'jump-to-checkout' ); ?>
							<input type="number" name="jptc_expiry_hours" value="24" min="1" disabled />
							<?php echo esc_html__( 'hours', 'jump-to-checkout' ); ?>
							<span class="jump-to-checkout-pro-badge"><?php echo esc_html__( 'PRO', 'jump-to-checkout' ); ?></span>
						</label>
						<p class="description">
							<?php esc_html_e( 'Link expiration is only available in the PRO version.', 'jump-to-checkout' ); ?>
							<a href="<?php echo esc_url( Features::get_upgrade_url() ); ?>" target="_blank">
								<?php esc_html_e( 'Learn more', 'jump-to-checkout' ); ?>
							</a>
						</p>
					</div>

					<div class="jump-to-checkout-generate-section">
						<button type="button" class="button button-primary button-large jump-to-checkout-generate-link" <?php echo ! $can_create ? 'disabled' : ''; ?>>
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
							<p><?php esc_html_e( 'Limit: 5 active links | 1 product per link | Basic statistics', 'jump-to-checkout' ); ?></p>
							<a href="<?php echo esc_url( Features::get_upgrade_url() ); ?>" class="button button-primary" target="_blank">
								<?php esc_html_e( 'Unlock all features with PRO', 'jump-to-checkout' ); ?>
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
							<li>✅ <?php esc_html_e( 'Unlimited links', 'jump-to-checkout' ); ?></li>
							<li>✅ <?php esc_html_e( 'Multiple products per link', 'jump-to-checkout' ); ?></li>
							<li>✅ <?php esc_html_e( 'Advanced analytics with charts', 'jump-to-checkout' ); ?></li>
							<li>✅ <?php esc_html_e( 'Export to CSV/Excel', 'jump-to-checkout' ); ?></li>
						</ul>
					</div>
					<div class="jump-to-checkout-features-column">
						<ul class="jump-to-checkout-features-list">
							<li>✅ <?php esc_html_e( 'Automatic coupons', 'jump-to-checkout' ); ?></li>
							<li>✅ <?php esc_html_e( 'Templates & UTM tracking', 'jump-to-checkout' ); ?></li>
							<li>✅ <?php esc_html_e( 'API & Webhooks', 'jump-to-checkout' ); ?></li>
							<li>✅ <?php esc_html_e( 'Priority support', 'jump-to-checkout' ); ?></li>
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
		<script>
		jQuery(document).ready(function($) {
			$('.jump-to-checkout-dismiss-upgrade').on('click', function() {
				var $widget = $(this).closest('.jump-to-checkout-upgrade-widget');
				$.post(ajaxurl, {
					action: 'jptc_dismiss_upgrade_widget',
					nonce: '<?php echo esc_js( wp_create_nonce( 'jptc_dismiss_upgrade' ) ); ?>'
				});
				$widget.fadeOut();
			});
		});
		</script>
		<?php
	}

	/**
	 * Render upgrade page
	 *
	 * @return void
	 */
	public function render_upgrade_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'jump-to-checkout' ) );
		}

		$comparison = Features::get_features_comparison();
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Actualizar a Jump to Checkout PRO', 'jump-to-checkout' ); ?></h1>
			<p class="description"><?php echo esc_html__( 'Desbloquea todas las funciones premium y lleva tu tienda al siguiente nivel.', 'jump-to-checkout' ); ?></p>

			<div class="jump-to-checkout-pricing-table">
				<div class="jump-to-checkout-pricing-column jump-to-checkout-pricing-free">
					<h2><?php echo esc_html( $comparison['free']['name'] ); ?></h2>
					<div class="jump-to-checkout-pricing-price"><?php echo esc_html( $comparison['free']['price'] ); ?></div>
					<ul class="jump-to-checkout-pricing-features">
						<?php foreach ( $comparison['free']['features'] as $feature ) : ?>
							<li><?php echo esc_html( $feature ); ?></li>
						<?php endforeach; ?>
					</ul>
					<p class="jump-to-checkout-current-plan"><?php esc_html_e( 'Current plan', 'jump-to-checkout' ); ?></p>
				</div>

				<div class="jump-to-checkout-pricing-column jump-to-checkout-pricing-pro">
					<div class="jump-to-checkout-recommended-badge"><?php esc_html_e( 'Recommended', 'jump-to-checkout' ); ?></div>
					<h2><?php echo esc_html( $comparison['pro']['name'] ); ?></h2>
					<div class="jump-to-checkout-pricing-price"><?php echo esc_html( $comparison['pro']['price'] ); ?></div>
					<ul class="jump-to-checkout-pricing-features">
						<?php foreach ( $comparison['pro']['features'] as $feature ) : ?>
							<li><?php echo esc_html( $feature ); ?></li>
						<?php endforeach; ?>
					</ul>
					<a href="<?php echo esc_url( Features::get_upgrade_url() ); ?>" class="button button-primary button-hero" target="_blank">
						<?php esc_html_e( 'Upgrade now', 'jump-to-checkout' ); ?>
					</a>
				</div>
			</div>

			<div class="jump-to-checkout-testimonials">
				<h2><?php esc_html_e( 'Why upgrade to PRO?', 'jump-to-checkout' ); ?></h2>
				<div class="jump-to-checkout-benefits-grid">
					<div class="jump-to-checkout-benefit">
						<h3>📈 <?php esc_html_e( 'Increase conversions', 'jump-to-checkout' ); ?></h3>
						<p><?php esc_html_e( 'Create optimized links with multiple products and automatic coupons to maximize sales.', 'jump-to-checkout' ); ?></p>
					</div>
					<div class="jump-to-checkout-benefit">
						<h3>📊 <?php esc_html_e( 'Detailed data', 'jump-to-checkout' ); ?></h3>
						<p><?php esc_html_e( 'Advanced analytics with charts, export and complete tracking of each link.', 'jump-to-checkout' ); ?></p>
					</div>
					<div class="jump-to-checkout-benefit">
						<h3>🚀 <?php esc_html_e( 'Automation', 'jump-to-checkout' ); ?></h3>
						<p><?php esc_html_e( 'Webhooks, REST API and integrations with your favorite tools.', 'jump-to-checkout' ); ?></p>
					</div>
					<div class="jump-to-checkout-benefit">
						<h3>💬 <?php esc_html_e( 'Priority support', 'jump-to-checkout' ); ?></h3>
						<p><?php esc_html_e( 'Fast response and personalized help from our expert team.', 'jump-to-checkout' ); ?></p>
					</div>
				</div>
			</div>
		</div>
		<?php
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

		// Check if can create link (FREE limit).
		if ( ! Features::can_create_link() ) {
			wp_send_json_error(
				array(
					'message'     => __( 'You have reached the active links limit in the FREE version.', 'jump-to-checkout' ),
					'upgrade_url' => Features::get_upgrade_url(),
				)
			);
		}

		$name          = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$products_json = isset( $_POST['products'] ) ? wp_unslash( $_POST['products'] ) : '[]'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON decoded and validated below.
		$products      = json_decode( $products_json, true );
		$expiry        = isset( $_POST['expiry'] ) ? absint( $_POST['expiry'] ) : 0;

		if ( empty( $name ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a link name.', 'jump-to-checkout' ) ) );
		}

		if ( empty( $products ) ) {
			wp_send_json_error( array( 'message' => __( 'No products selected.', 'jump-to-checkout' ) ) );
		}

		// FREE limit: Max 1 product per link.
		if ( ! Features::is_pro() && count( $products ) > Features::max_products_per_link() ) {
			wp_send_json_error(
				array(
					'message'     => __( 'The FREE version allows only 1 product per link. Upgrade to PRO for multiple products.', 'jump-to-checkout' ),
					'upgrade_url' => Features::get_upgrade_url(),
				)
			);
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


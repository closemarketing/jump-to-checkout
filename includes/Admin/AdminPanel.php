<?php
/**
 * Admin Panel Class
 *
 * Handles the admin panel for generating checkout links - FREE VERSION
 *
 * @package    CLOSE\DirectLinkCheckout\Admin
 * @author     Close Marketing
 * @copyright  2025 Closemarketing
 * @version    1.0.0
 */

namespace CLOSE\DirectLinkCheckout\Admin;

use CLOSE\DirectLinkCheckout\Core\Features;

defined( 'ABSPATH' ) || exit;

/**
 * Admin Panel Class
 */
class AdminPanel {

	/**
	 * Direct Checkout instance
	 *
	 * @var \CLOSE\DirectLinkCheckout\Core\DirectCheckout
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
		add_action( 'wp_ajax_cldc_generate_link', array( $this, 'ajax_generate_link' ) );
		add_action( 'wp_ajax_cldc_search_products', array( $this, 'ajax_search_products' ) );
		add_action( 'wp_ajax_cldc_dismiss_upgrade_widget', array( $this, 'ajax_dismiss_upgrade_widget' ) );

		// Show admin notices.
		add_action( 'admin_notices', array( $this, 'show_limit_notices' ) );

		// Initialize Direct Checkout.
		$this->direct_checkout = new \CLOSE\DirectLinkCheckout\Core\DirectCheckout();
	}

	/**
	 * Add admin menu
	 *
	 * @return void
	 */
	public function add_admin_menu() {
		// Main menu.
		add_menu_page(
			__( 'Direct Checkout', 'direct-link-checkout' ),
			__( 'Direct Checkout', 'direct-link-checkout' ),
			'manage_woocommerce',
			'cldc-direct-checkout',
			array( $this, 'render_admin_page' ),
			'dashicons-cart',
			56
		);

		// Submenu: Generate Link.
		add_submenu_page(
			'cldc-direct-checkout',
			__( 'Generate Link', 'direct-link-checkout' ),
			__( 'Generate Link', 'direct-link-checkout' ),
			'manage_woocommerce',
			'cldc-direct-checkout',
			array( $this, 'render_admin_page' )
		);

		// Submenu: Upgrade to PRO.
		if ( ! Features::is_pro() ) {
			add_submenu_page(
				'cldc-direct-checkout',
				__( '⭐ Upgrade to PRO', 'direct-link-checkout' ),
				__( '⭐ Upgrade to PRO', 'direct-link-checkout' ),
				'manage_woocommerce',
				'cldc-upgrade',
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
		if ( ! $screen || 'toplevel_page_cldc-direct-checkout' !== $screen->id ) {
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
					<strong><?php esc_html_e( 'Limit Reached!', 'direct-link-checkout' ); ?></strong>
					<?php
					echo sprintf(
						/* translators: %d: max links */
						esc_html__( 'You have reached the limit of %d active links in the FREE version.', 'direct-link-checkout' ),
						$max_links
					);
					?>
					<a href="<?php echo esc_url( Features::get_upgrade_url() ); ?>" class="button button-primary" style="margin-left: 10px;" target="_blank">
						<?php esc_html_e( 'Upgrade to PRO for unlimited links', 'direct-link-checkout' ); ?>
					</a>
				</p>
			</div>
			<?php
		} elseif ( $active_links >= ( $max_links - 1 ) ) {
			?>
			<div class="notice notice-warning is-dismissible">
				<p>
					<strong><?php esc_html_e( 'Almost at the Limit!', 'direct-link-checkout' ); ?></strong>
					<?php
					echo sprintf(
						/* translators: %1$d: active links, %2$d: max links */
						esc_html__( 'You have %1$d of %2$d active links. Consider upgrading to PRO for unlimited links.', 'direct-link-checkout' ),
						$active_links,
						$max_links
					);
					?>
					<a href="<?php echo esc_url( Features::get_upgrade_url() ); ?>" target="_blank">
						<?php esc_html_e( 'View PRO plans', 'direct-link-checkout' ); ?>
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
		if ( 'toplevel_page_cldc-direct-checkout' !== $hook && 'direct-checkout_page_cldc-upgrade' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'cldc-admin',
			CLDC_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			CLDC_VERSION
		);

		// Add FREE version styles.
		wp_add_inline_style(
			'cldc-admin',
			$this->get_free_version_styles()
		);

		wp_enqueue_script(
			'cldc-admin',
			CLDC_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			CLDC_VERSION,
			true
		);

		wp_localize_script(
			'cldc-admin',
			'cldcAdmin',
			array(
				'ajax_url'   => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( 'cldc_admin_nonce' ),
				'is_pro'     => Features::is_pro(),
				'max_links'  => Features::max_links(),
				'max_products' => Features::max_products_per_link(),
				'i18n'       => array(
					'copy_success'         => __( 'Link copied to clipboard!', 'direct-link-checkout' ),
					'copy_error'           => __( 'Failed to copy link.', 'direct-link-checkout' ),
					'generate_error'       => __( 'Error generating link.', 'direct-link-checkout' ),
					'search_placeholder'   => __( 'Search products...', 'direct-link-checkout' ),
					'no_products'          => __( 'No products found.', 'direct-link-checkout' ),
					'no_link_name'         => __( 'Please enter a link name.', 'direct-link-checkout' ),
					'no_products_selected' => __( 'Please select at least one product.', 'direct-link-checkout' ),
					'limit_reached'        => __( 'You have reached the active links limit in the FREE version.', 'direct-link-checkout' ),
					'max_products_reached' => __( 'The FREE version allows only 1 product per link. Upgrade to PRO for multiple products.', 'direct-link-checkout' ),
				),
			)
		);

		// Select2 for product search.
		wp_enqueue_style( 'select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', array(), '4.1.0' );
		wp_enqueue_script( 'select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', array( 'jquery' ), '4.1.0', true );
	}

	/**
	 * Get FREE version styles
	 *
	 * @return string
	 */
	private function get_free_version_styles() {
		return "
		/* Upgrade Widget */
		.cldc-upgrade-widget {
			border-left: 4px solid #2271b1;
			background: #f0f6fc;
			padding: 15px 20px;
			margin-bottom: 20px;
			position: relative;
		}

		.cldc-upgrade-content {
			max-width: 100%;
		}

		.cldc-upgrade-header {
			margin-bottom: 15px;
		}

		.cldc-upgrade-header h3 {
			margin: 0;
			font-size: 18px;
		}

		.cldc-upgrade-columns {
			display: grid;
			grid-template-columns: 1fr 1fr auto;
			gap: 20px;
			align-items: start;
		}

		.cldc-features-column ul.cldc-features-list {
			list-style: none;
			padding: 0;
			margin: 0;
		}

		.cldc-features-list li {
			padding: 6px 0;
			font-size: 13px;
			line-height: 1.4;
		}

		.cldc-cta-column {
			text-align: center;
			padding: 0 10px;
			min-width: 180px;
		}

		.cldc-cta-column .button {
			margin-bottom: 10px;
			white-space: nowrap;
		}

		.cldc-guarantee {
			margin: 5px 0 0 0;
			color: #666;
			font-size: 12px;
		}

		@media (max-width: 1200px) {
			.cldc-upgrade-columns {
				grid-template-columns: 1fr;
				gap: 15px;
			}
			
			.cldc-cta-column {
				border-top: 1px solid #ddd;
				padding-top: 15px;
			}
		}

		/* PRO Feature Badge */
		.cldc-pro-badge {
			background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
			color: white;
			padding: 3px 8px;
			border-radius: 3px;
			font-size: 11px;
			font-weight: bold;
			margin-left: 8px;
			vertical-align: middle;
		}

		/* Product limit message */
		.cldc-limit-message {
			background: #fff3cd;
			border-left: 4px solid #ffc107;
			padding: 12px 20px;
			margin: 15px 0;
			border-radius: 4px;
		}

		.cldc-limit-message p {
			margin: 0;
			color: #856404;
		}

		/* Footer branding */
		.cldc-free-footer {
			margin-top: 30px;
			padding: 20px;
			background: #f9f9f9;
			border: 1px solid #ddd;
			border-radius: 4px;
			text-align: center;
		}

		.cldc-free-footer p {
			margin: 0 0 10px 0;
			color: #666;
			font-size: 13px;
		}
		";
	}

	/**
	 * Render admin page
	 *
	 * @return void
	 */
	public function render_admin_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'direct-link-checkout' ) );
		}

		$can_create = Features::can_create_link();
		$max_products = Features::max_products_per_link();
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Direct Checkout Link Generator', 'direct-link-checkout' ); ?></h1>
			<p><?php echo esc_html__( 'Generate secure links that automatically add products to cart and redirect to checkout.', 'direct-link-checkout' ); ?></p>

			<?php if ( ! Features::is_pro() ) : ?>
				<?php $this->render_upgrade_widget(); ?>
			<?php endif; ?>

			<div class="cldc-admin-container">
				<div class="cldc-form-section">
					<h2><?php echo esc_html__( 'Generate New Link', 'direct-link-checkout' ); ?></h2>

					<?php if ( ! $can_create ) : ?>
					<div class="notice notice-error">
						<p>
							<strong><?php esc_html_e( 'Limit Reached!', 'direct-link-checkout' ); ?></strong>
							<?php esc_html_e( 'You cannot create more links in the FREE version. Please deactivate or delete an existing link, or upgrade to PRO.', 'direct-link-checkout' ); ?>
							<a href="<?php echo esc_url( Features::get_upgrade_url() ); ?>" class="button button-primary" style="margin-left: 10px;" target="_blank">
								<?php esc_html_e( 'Upgrade to PRO', 'direct-link-checkout' ); ?>
							</a>
						</p>
					</div>
					<?php endif; ?>

					<?php if ( 1 === $max_products ) : ?>
					<div class="cldc-limit-message">
						<p>
							<strong><?php esc_html_e( 'FREE Version:', 'direct-link-checkout' ); ?></strong>
							<?php esc_html_e( 'You can only add 1 product per link.', 'direct-link-checkout' ); ?>
							<a href="<?php echo esc_url( Features::get_upgrade_url() ); ?>" target="_blank">
								<?php esc_html_e( 'Upgrade to PRO for unlimited products', 'direct-link-checkout' ); ?>
							</a>
						</p>
					</div>
					<?php endif; ?>

					<div class="cldc-link-name-section">
						<label><?php echo esc_html__( 'Link Name', 'direct-link-checkout' ); ?></label>
						<input type="text" class="cldc-link-name" placeholder="<?php echo esc_attr__( 'e.g. Summer Campaign 2025', 'direct-link-checkout' ); ?>" <?php echo ! $can_create ? 'disabled' : ''; ?> />
						<p class="description"><?php echo esc_html__( 'Give this link a name to identify it later in the statistics.', 'direct-link-checkout' ); ?></p>
					</div>

					<h3><?php echo esc_html__( 'Select Products', 'direct-link-checkout' ); ?></h3>

					<div class="cldc-products-container">
						<div class="cldc-product-row">
							<div class="cldc-product-field">
								<label><?php echo esc_html__( 'Product', 'direct-link-checkout' ); ?></label>
								<select class="cldc-product-search" style="width: 100%;" <?php echo ! $can_create ? 'disabled' : ''; ?>></select>
							</div>
							<div class="cldc-quantity-field">
								<label><?php echo esc_html__( 'Quantity', 'direct-link-checkout' ); ?></label>
								<input type="number" class="cldc-quantity" value="1" min="1" <?php echo ! $can_create ? 'disabled' : ''; ?> />
							</div>
							<div class="cldc-actions-field">
								<button type="button" class="button cldc-add-product" <?php echo ! $can_create ? 'disabled' : ''; ?>>
									<?php echo esc_html__( 'Add Product', 'direct-link-checkout' ); ?>
								</button>
							</div>
						</div>
					</div>

					<div class="cldc-selected-products">
						<h3><?php echo esc_html__( 'Selected Products', 'direct-link-checkout' ); ?></h3>
						<table class="wp-list-table widefat fixed striped">
							<thead>
								<tr>
									<th><?php echo esc_html__( 'Product', 'direct-link-checkout' ); ?></th>
									<th><?php echo esc_html__( 'Quantity', 'direct-link-checkout' ); ?></th>
									<th><?php echo esc_html__( 'Actions', 'direct-link-checkout' ); ?></th>
								</tr>
							</thead>
							<tbody class="cldc-selected-products-body">
								<tr class="no-items">
									<td colspan="3"><?php echo esc_html__( 'No products selected.', 'direct-link-checkout' ); ?></td>
								</tr>
							</tbody>
						</table>
					</div>

					<div class="cldc-expiry-section">
						<h3><?php echo esc_html__( 'Link Expiry', 'direct-link-checkout' ); ?></h3>
						<label>
							<input type="radio" name="cldc_expiry_type" value="never" checked <?php echo ! $can_create ? 'disabled' : ''; ?> />
							<?php echo esc_html__( 'Never expires', 'direct-link-checkout' ); ?>
						</label>
						<label>
							<input type="radio" name="cldc_expiry_type" value="custom" disabled />
							<?php echo esc_html__( 'Expires in', 'direct-link-checkout' ); ?>
							<input type="number" name="cldc_expiry_hours" value="24" min="1" disabled />
							<?php echo esc_html__( 'hours', 'direct-link-checkout' ); ?>
							<span class="cldc-pro-badge"><?php echo esc_html__( 'PRO', 'direct-link-checkout' ); ?></span>
						</label>
						<p class="description">
							<?php esc_html_e( 'Link expiration is only available in the PRO version.', 'direct-link-checkout' ); ?>
							<a href="<?php echo esc_url( Features::get_upgrade_url() ); ?>" target="_blank">
								<?php esc_html_e( 'Learn more', 'direct-link-checkout' ); ?>
							</a>
						</p>
					</div>

					<div class="cldc-generate-section">
						<button type="button" class="button button-primary button-large cldc-generate-link" <?php echo ! $can_create ? 'disabled' : ''; ?>>
							<?php echo esc_html__( 'Generate Link', 'direct-link-checkout' ); ?>
						</button>
					</div>

					<div class="cldc-result-section" style="display: none;">
						<h3><?php echo esc_html__( 'Generated Link', 'direct-link-checkout' ); ?></h3>
						<div class="cldc-result-container">
							<input type="text" class="cldc-generated-link" readonly />
							<button type="button" class="button cldc-copy-link">
								<?php echo esc_html__( 'Copy Link', 'direct-link-checkout' ); ?>
							</button>
						</div>
						<div class="cldc-result-info">
							<p class="description">
								<?php echo esc_html__( 'Share this link with your customers. When they click it, the products will be added to their cart and they will be redirected to checkout.', 'direct-link-checkout' ); ?>
							</p>
						</div>
					</div>

					<?php if ( ! Features::is_pro() ) : ?>
						<div class="cldc-free-footer">
							<p><strong><?php esc_html_e( 'You are using Direct Link Checkout FREE', 'direct-link-checkout' ); ?></strong></p>
							<p><?php esc_html_e( 'Limit: 5 active links | 1 product per link | Basic statistics', 'direct-link-checkout' ); ?></p>
							<a href="<?php echo esc_url( Features::get_upgrade_url() ); ?>" class="button button-primary" target="_blank">
								<?php esc_html_e( 'Unlock all features with PRO', 'direct-link-checkout' ); ?>
							</a>
						</div>
					<?php endif; ?>
				</div>

				<div class="cldc-info-section">
					<div class="cldc-info-box">
						<h3><?php echo esc_html__( 'How it works', 'direct-link-checkout' ); ?></h3>
						<ol>
							<li><?php echo esc_html__( 'Select the products you want to include in the link', 'direct-link-checkout' ); ?></li>
							<li><?php echo esc_html__( 'Set the quantity for each product', 'direct-link-checkout' ); ?></li>
							<li><?php echo esc_html__( 'Choose if the link should expire', 'direct-link-checkout' ); ?></li>
							<li><?php echo esc_html__( 'Click "Generate Link"', 'direct-link-checkout' ); ?></li>
							<li><?php echo esc_html__( 'Share the link with your customers', 'direct-link-checkout' ); ?></li>
						</ol>
					</div>

					<div class="cldc-info-box">
						<h3><?php echo esc_html__( 'Security', 'direct-link-checkout' ); ?></h3>
						<p>
							<?php echo esc_html__( 'All links are secured with cryptographic signatures to prevent tampering. Each link contains encoded product information that cannot be modified without invalidating the link.', 'direct-link-checkout' ); ?>
						</p>
					</div>

					<div class="cldc-info-box">
						<h3><?php echo esc_html__( 'Link Format', 'direct-link-checkout' ); ?></h3>
						<p>
							<code><?php echo esc_html( home_url( '/direct-checkout/{token}' ) ); ?></code>
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
		if ( get_user_meta( get_current_user_id(), 'cldc_upgrade_widget_dismissed', true ) ) {
			return;
		}
		?>
		<div class="notice notice-info is-dismissible cldc-upgrade-widget" data-dismissible="cldc-upgrade-widget">
			<button type="button" class="notice-dismiss cldc-dismiss-upgrade">
				<span class="screen-reader-text"><?php esc_html_e( 'Dismiss this notice.', 'direct-link-checkout' ); ?></span>
			</button>
			<div class="cldc-upgrade-content">
				<div class="cldc-upgrade-header">
					<h3>🚀 <?php esc_html_e( 'Unlock Full Potential with PRO', 'direct-link-checkout' ); ?></h3>
				</div>
				<div class="cldc-upgrade-columns">
					<div class="cldc-features-column">
						<ul class="cldc-features-list">
							<li>✅ <?php esc_html_e( 'Unlimited links', 'direct-link-checkout' ); ?></li>
							<li>✅ <?php esc_html_e( 'Multiple products per link', 'direct-link-checkout' ); ?></li>
							<li>✅ <?php esc_html_e( 'Advanced analytics with charts', 'direct-link-checkout' ); ?></li>
							<li>✅ <?php esc_html_e( 'Export to CSV/Excel', 'direct-link-checkout' ); ?></li>
						</ul>
					</div>
					<div class="cldc-features-column">
						<ul class="cldc-features-list">
							<li>✅ <?php esc_html_e( 'Automatic coupons', 'direct-link-checkout' ); ?></li>
							<li>✅ <?php esc_html_e( 'Templates & UTM tracking', 'direct-link-checkout' ); ?></li>
							<li>✅ <?php esc_html_e( 'API & Webhooks', 'direct-link-checkout' ); ?></li>
							<li>✅ <?php esc_html_e( 'Priority support', 'direct-link-checkout' ); ?></li>
						</ul>
					</div>
					<div class="cldc-cta-column">
						<a href="<?php echo esc_url( Features::get_upgrade_url() ); ?>" class="button button-primary button-large" target="_blank">
							<?php esc_html_e( 'Upgrade to PRO', 'direct-link-checkout' ); ?>
						</a>
						<p class="cldc-guarantee">
							<small>✓ <?php esc_html_e( '30-day money back guarantee', 'direct-link-checkout' ); ?></small>
						</p>
					</div>
				</div>
			</div>
		</div>
		<script>
		jQuery(document).ready(function($) {
			$('.cldc-dismiss-upgrade').on('click', function() {
				var $widget = $(this).closest('.cldc-upgrade-widget');
				$.post(ajaxurl, {
					action: 'cldc_dismiss_upgrade_widget',
					nonce: '<?php echo esc_js( wp_create_nonce( 'cldc_dismiss_upgrade' ) ); ?>'
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
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'direct-link-checkout' ) );
		}

		$comparison = Features::get_features_comparison();
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Actualizar a Direct Link Checkout PRO', 'direct-link-checkout' ); ?></h1>
			<p class="description"><?php echo esc_html__( 'Desbloquea todas las funciones premium y lleva tu tienda al siguiente nivel.', 'direct-link-checkout' ); ?></p>

			<div class="cldc-pricing-table">
				<div class="cldc-pricing-column cldc-pricing-free">
					<h2><?php echo esc_html( $comparison['free']['name'] ); ?></h2>
					<div class="cldc-pricing-price"><?php echo esc_html( $comparison['free']['price'] ); ?></div>
					<ul class="cldc-pricing-features">
						<?php foreach ( $comparison['free']['features'] as $feature ) : ?>
							<li><?php echo esc_html( $feature ); ?></li>
						<?php endforeach; ?>
					</ul>
					<p class="cldc-current-plan"><?php esc_html_e( 'Current plan', 'direct-link-checkout' ); ?></p>
				</div>

				<div class="cldc-pricing-column cldc-pricing-pro">
					<div class="cldc-recommended-badge"><?php esc_html_e( 'Recommended', 'direct-link-checkout' ); ?></div>
					<h2><?php echo esc_html( $comparison['pro']['name'] ); ?></h2>
					<div class="cldc-pricing-price"><?php echo esc_html( $comparison['pro']['price'] ); ?></div>
					<ul class="cldc-pricing-features">
						<?php foreach ( $comparison['pro']['features'] as $feature ) : ?>
							<li><?php echo esc_html( $feature ); ?></li>
						<?php endforeach; ?>
					</ul>
					<a href="<?php echo esc_url( Features::get_upgrade_url() ); ?>" class="button button-primary button-hero" target="_blank">
						<?php esc_html_e( 'Upgrade now', 'direct-link-checkout' ); ?>
					</a>
				</div>
			</div>

			<div class="cldc-testimonials">
				<h2><?php esc_html_e( 'Why upgrade to PRO?', 'direct-link-checkout' ); ?></h2>
				<div class="cldc-benefits-grid">
					<div class="cldc-benefit">
						<h3>📈 <?php esc_html_e( 'Increase conversions', 'direct-link-checkout' ); ?></h3>
						<p><?php esc_html_e( 'Create optimized links with multiple products and automatic coupons to maximize sales.', 'direct-link-checkout' ); ?></p>
					</div>
					<div class="cldc-benefit">
						<h3>📊 <?php esc_html_e( 'Detailed data', 'direct-link-checkout' ); ?></h3>
						<p><?php esc_html_e( 'Advanced analytics with charts, export and complete tracking of each link.', 'direct-link-checkout' ); ?></p>
					</div>
					<div class="cldc-benefit">
						<h3>🚀 <?php esc_html_e( 'Automation', 'direct-link-checkout' ); ?></h3>
						<p><?php esc_html_e( 'Webhooks, REST API and integrations with your favorite tools.', 'direct-link-checkout' ); ?></p>
					</div>
					<div class="cldc-benefit">
						<h3>💬 <?php esc_html_e( 'Priority support', 'direct-link-checkout' ); ?></h3>
						<p><?php esc_html_e( 'Fast response and personalized help from our expert team.', 'direct-link-checkout' ); ?></p>
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
		check_ajax_referer( 'cldc_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'direct-link-checkout' ) ) );
		}

		// Check if can create link (FREE limit).
		if ( ! Features::can_create_link() ) {
		wp_send_json_error(
			array(
				'message'     => __( 'You have reached the active links limit in the FREE version.', 'direct-link-checkout' ),
				'upgrade_url' => Features::get_upgrade_url(),
			)
		);
		}

		$name          = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$products_json = isset( $_POST['products'] ) ? wp_unslash( $_POST['products'] ) : '[]'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON decoded and validated below.
		$products      = json_decode( $products_json, true );
		$expiry        = isset( $_POST['expiry'] ) ? absint( $_POST['expiry'] ) : 0;

		if ( empty( $name ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a link name.', 'direct-link-checkout' ) ) );
		}

		if ( empty( $products ) ) {
			wp_send_json_error( array( 'message' => __( 'No products selected.', 'direct-link-checkout' ) ) );
		}

		// FREE limit: Max 1 product per link.
		if ( ! Features::is_pro() && count( $products ) > Features::max_products_per_link() ) {
			wp_send_json_error(
				array(
					'message'     => __( 'The FREE version allows only 1 product per link. Upgrade to PRO for multiple products.', 'direct-link-checkout' ),
					'upgrade_url' => Features::get_upgrade_url(),
				)
			);
		}

		$result = $this->direct_checkout->generate_link( $name, $products, $expiry );

		if ( ! $result || ! isset( $result['url'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Error generating link.', 'direct-link-checkout' ) ) );
		}

		wp_send_json_success( array( 'link' => $result['url'] ) );
	}

	/**
	 * AJAX: Search products
	 *
	 * @return void
	 */
	public function ajax_search_products() {
		check_ajax_referer( 'cldc_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'direct-link-checkout' ) ) );
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
		check_ajax_referer( 'cldc_dismiss_upgrade', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error();
		}

		update_user_meta( get_current_user_id(), 'cldc_upgrade_widget_dismissed', true );
		wp_send_json_success();
	}
}


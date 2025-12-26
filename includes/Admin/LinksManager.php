<?php
/**
 * Links Manager Class
 *
 * Handles the links management page - FREE VERSION
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
 * Links Manager Class
 */
class LinksManager {

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
		$this->db = new \CLOSE\DirectLinkCheckout\Database\Database();

		// Add admin menu.
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );

		// Enqueue admin scripts.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );

		// Handle AJAX requests.
		add_action( 'wp_ajax_cldc_delete_link', array( $this, 'ajax_delete_link' ) );
		add_action( 'wp_ajax_cldc_toggle_status', array( $this, 'ajax_toggle_status' ) );
	}

	/**
	 * Add admin menu
	 *
	 * @return void
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'cldc-direct-checkout',
			__( 'Manage Links', 'direct-link-checkout' ),
			__( 'Manage Links', 'direct-link-checkout' ),
			'manage_woocommerce',
			'cldc-manage-links',
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
		if ( 'direct-checkout_page_cldc-manage-links' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'cldc-manager',
			CLDC_PLUGIN_URL . 'assets/css/manager.css',
			array(),
			CLDC_VERSION
		);

		wp_enqueue_script(
			'cldc-manager',
			CLDC_PLUGIN_URL . 'assets/js/manager.js',
			array( 'jquery' ),
			CLDC_VERSION,
			true
		);

		wp_localize_script(
			'cldc-manager',
			'cldcManager',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'cldc_manager_nonce' ),
				'i18n'     => array(
					'confirm_delete' => __( 'Are you sure you want to delete this link?', 'direct-link-checkout' ),
					'delete_success' => __( 'Link deleted successfully.', 'direct-link-checkout' ),
					'delete_error'   => __( 'Error deleting link.', 'direct-link-checkout' ),
					'status_success' => __( 'Status updated successfully.', 'direct-link-checkout' ),
					'status_error'   => __( 'Error updating status.', 'direct-link-checkout' ),
					'copied'         => __( 'Copied!', 'direct-link-checkout' ),
					'copy_error'     => __( 'Failed to copy URL.', 'direct-link-checkout' ),
					'enable'         => __( 'Enable', 'direct-link-checkout' ),
					'disable'        => __( 'Disable', 'direct-link-checkout' ),
					'no_links'       => __( 'No links found.', 'direct-link-checkout' ),
				),
			)
		);
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

		$links      = $this->db->get_links();
		$statistics = $this->db->get_statistics();
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Manage Direct Checkout Links', 'direct-link-checkout' ); ?></h1>

			<?php if ( ! Features::is_pro() ) : ?>
				<div class="notice notice-info">
					<p>
						<strong><?php esc_html_e( 'FREE Version:', 'direct-link-checkout' ); ?></strong>
						<?php
						printf(
							/* translators: %1$d: active links, %2$d: max links */
							esc_html__( 'You have %1$d of %2$d active links.', 'direct-link-checkout' ),
							(int) Features::get_active_links_count(),
							(int) Features::max_links()
						);
						?>
						<a href="<?php echo esc_url( Features::get_upgrade_url() ); ?>" target="_blank">
							<?php esc_html_e( 'Upgrade to PRO for unlimited links', 'direct-link-checkout' ); ?>
						</a>
					</p>
				</div>
			<?php endif; ?>

			<div class="cldc-stats-container">
				<div class="cldc-stat-box">
					<div class="cldc-stat-number"><?php echo esc_html( $statistics->total_links ); ?></div>
					<div class="cldc-stat-label"><?php echo esc_html__( 'Total Links', 'direct-link-checkout' ); ?></div>
				</div>
				<div class="cldc-stat-box">
					<div class="cldc-stat-number"><?php echo esc_html( $statistics->active_links ); ?></div>
					<div class="cldc-stat-label"><?php echo esc_html__( 'Active Links', 'direct-link-checkout' ); ?></div>
				</div>
				<div class="cldc-stat-box">
					<div class="cldc-stat-number"><?php echo esc_html( $statistics->total_visits ); ?></div>
					<div class="cldc-stat-label"><?php echo esc_html__( 'Total Visits', 'direct-link-checkout' ); ?></div>
				</div>
				<div class="cldc-stat-box">
					<div class="cldc-stat-number"><?php echo esc_html( $statistics->total_conversions ); ?></div>
					<div class="cldc-stat-label"><?php echo esc_html__( 'Total Conversions', 'direct-link-checkout' ); ?></div>
				</div>
				<div class="cldc-stat-box">
					<div class="cldc-stat-number">
						<?php
						if ( $statistics->total_visits > 0 ) {
							echo esc_html( number_format( ( $statistics->total_conversions / $statistics->total_visits ) * 100, 2 ) ) . '%';
						} else {
							echo '0%';
						}
						?>
					</div>
					<div class="cldc-stat-label"><?php echo esc_html__( 'Conversion Rate', 'direct-link-checkout' ); ?></div>
				</div>
			</div>

			<?php if ( ! Features::is_pro() && ! Features::can_export() ) : ?>
				<div class="cldc-upgrade-banner" style="background: #f0f6fc; border-left: 4px solid #2271b1; padding: 15px; margin: 20px 0; border-radius: 4px;">
					<p style="margin: 0;">
						<strong>📊 <?php esc_html_e( 'Need to export this data?', 'direct-link-checkout' ); ?></strong>
						<?php esc_html_e( 'With the PRO version you can export all statistics to CSV/Excel and access advanced analytics with charts.', 'direct-link-checkout' ); ?>
						<a href="<?php echo esc_url( Features::get_upgrade_url() ); ?>" class="button button-primary" style="margin-left: 10px;" target="_blank">
							<?php esc_html_e( 'Upgrade to PRO', 'direct-link-checkout' ); ?>
						</a>
					</p>
				</div>
			<?php endif; ?>

			<table class="wp-list-table widefat fixed striped cldc-links-table">
				<thead>
					<tr>
						<th><?php echo esc_html__( 'Name', 'direct-link-checkout' ); ?></th>
						<th><?php echo esc_html__( 'Products', 'direct-link-checkout' ); ?></th>
						<th><?php echo esc_html__( 'Created', 'direct-link-checkout' ); ?></th>
						<th><?php echo esc_html__( 'Expires', 'direct-link-checkout' ); ?></th>
						<th><?php echo esc_html__( 'Visits', 'direct-link-checkout' ); ?></th>
						<th><?php echo esc_html__( 'Conversions', 'direct-link-checkout' ); ?></th>
						<th><?php echo esc_html__( 'Rate', 'direct-link-checkout' ); ?></th>
						<th><?php echo esc_html__( 'Status', 'direct-link-checkout' ); ?></th>
						<th><?php echo esc_html__( 'Actions', 'direct-link-checkout' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $links ) ) : ?>
						<tr>
							<td colspan="9" class="no-items"><?php echo esc_html__( 'No links found.', 'direct-link-checkout' ); ?></td>
						</tr>
					<?php else : ?>
						<?php foreach ( $links as $link ) : ?>
							<?php
							$products      = json_decode( $link->products, true );
							$product_count = count( $products );
							$rate          = $link->visits > 0 ? ( $link->conversions / $link->visits ) * 100 : 0;
							$is_expired    = $link->expires_at && strtotime( $link->expires_at ) < time();
							?>
							<tr data-link-id="<?php echo esc_attr( $link->id ); ?>">
								<td>
									<strong><?php echo esc_html( $link->name ); ?></strong>
									<div class="cldc-link-url">
										<input type="text" value="<?php echo esc_attr( $link->url ); ?>" readonly class="cldc-link-input" />
										<button type="button" class="button button-small cldc-copy-url" data-url="<?php echo esc_attr( $link->url ); ?>">
											<?php echo esc_html__( 'Copy', 'direct-link-checkout' ); ?>
										</button>
									</div>
								</td>
								<td><?php echo esc_html( $product_count . ' ' . _n( 'product', 'products', $product_count, 'direct-link-checkout' ) ); ?></td>
								<td><?php echo esc_html( gmdate( 'Y-m-d H:i', strtotime( $link->created_at ) ) ); ?></td>
								<td>
									<?php
									if ( $link->expires_at ) {
										echo esc_html( gmdate( 'Y-m-d H:i', strtotime( $link->expires_at ) ) );
										if ( $is_expired ) {
											echo ' <span class="cldc-expired">' . esc_html__( '(Expired)', 'direct-link-checkout' ) . '</span>';
										}
									} else {
										echo '<span class="cldc-never">' . esc_html__( 'Never', 'direct-link-checkout' ) . '</span>';
									}
									?>
								</td>
								<td><strong><?php echo esc_html( $link->visits ); ?></strong></td>
								<td><strong><?php echo esc_html( $link->conversions ); ?></strong></td>
								<td><?php echo esc_html( number_format( $rate, 2 ) ); ?>%</td>
								<td>
									<span class="cldc-status cldc-status-<?php echo esc_attr( $link->status ); ?>">
										<?php echo esc_html( ucfirst( $link->status ) ); ?>
									</span>
								</td>
								<td class="cldc-actions">
									<button type="button" class="button button-small cldc-toggle-status" data-link-id="<?php echo esc_attr( $link->id ); ?>" data-status="<?php echo esc_attr( $link->status ); ?>">
										<?php echo 'active' === $link->status ? esc_html__( 'Disable', 'direct-link-checkout' ) : esc_html__( 'Enable', 'direct-link-checkout' ); ?>
									</button>
									<button type="button" class="button button-small button-link-delete cldc-delete-link" data-link-id="<?php echo esc_attr( $link->id ); ?>">
										<?php echo esc_html__( 'Delete', 'direct-link-checkout' ); ?>
									</button>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<?php if ( ! Features::is_pro() ) : ?>
				<div class="cldc-free-footer" style="margin-top: 30px; padding: 20px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px; text-align: center;">
					<p style="margin: 0 0 10px 0;"><strong><?php esc_html_e( 'You are using Direct Link Checkout FREE', 'direct-link-checkout' ); ?></strong></p>
					<p style="margin: 0 0 15px 0; color: #666; font-size: 13px;">
						<?php esc_html_e( 'Developed by Close Technology', 'direct-link-checkout' ); ?> | 
						<a href="https://close.technology" target="_blank">close.technology</a>
					</p>
					<a href="<?php echo esc_url( Features::get_upgrade_url() ); ?>" class="button button-primary" target="_blank">
						<?php esc_html_e( 'Upgrade to PRO for all features', 'direct-link-checkout' ); ?>
					</a>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * AJAX: Delete link
	 *
	 * @return void
	 */
	public function ajax_delete_link() {
		check_ajax_referer( 'cldc_manager_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'direct-link-checkout' ) ) );
		}

		$link_id = isset( $_POST['link_id'] ) ? absint( $_POST['link_id'] ) : 0;

		if ( ! $link_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid link ID.', 'direct-link-checkout' ) ) );
		}

		$result = $this->db->delete_link( $link_id );

		if ( $result ) {
			wp_send_json_success();
		} else {
			wp_send_json_error( array( 'message' => __( 'Error deleting link.', 'direct-link-checkout' ) ) );
		}
	}

	/**
	 * AJAX: Toggle status
	 *
	 * @return void
	 */
	public function ajax_toggle_status() {
		check_ajax_referer( 'cldc_manager_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'direct-link-checkout' ) ) );
		}

		$link_id = isset( $_POST['link_id'] ) ? absint( $_POST['link_id'] ) : 0;
		$status  = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '';

		if ( ! $link_id || ! $status ) {
			wp_send_json_error( array( 'message' => __( 'Invalid parameters.', 'direct-link-checkout' ) ) );
		}

		$new_status = 'active' === $status ? 'inactive' : 'active';

		// Check FREE limits when activating.
		if ( 'active' === $new_status && ! Features::is_pro() ) {
			if ( ! Features::can_create_link() ) {
				wp_send_json_error(
					array(
						'message'     => __( 'You cannot activate more links. You have reached the limit of 5 active links in the FREE version.', 'direct-link-checkout' ),
						'upgrade_url' => Features::get_upgrade_url(),
					)
				);
			}
		}

		$result = $this->db->update_status( $link_id, $new_status );

		if ( $result ) {
			wp_send_json_success( array( 'new_status' => $new_status ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Error updating status.', 'direct-link-checkout' ) ) );
		}
	}
}


<?php
/**
 * Links Manager Class
 *
 * Handles the links management page - FREE VERSION
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
		$this->db = new \CLOSE\JumpToCheckout\Database\Database();

		// Add admin menu.
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );

		// Enqueue admin scripts.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );

		// Handle AJAX requests.
		add_action( 'wp_ajax_jptc_delete_link', array( $this, 'ajax_delete_link' ) );
		add_action( 'wp_ajax_jptc_toggle_status', array( $this, 'ajax_toggle_status' ) );
	}

	/**
	 * Add admin menu
	 *
	 * @return void
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'jptc-jump-to-checkout',
			__( 'Manage Links', 'jump-to-checkout' ),
			__( 'Manage Links', 'jump-to-checkout' ),
			'manage_woocommerce',
			'jptc-manage-links',
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
		if ( 'jump-to-checkout_page_jptc-manage-links' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'jptc-manager',
			JTPC_PLUGIN_URL . 'assets/css/manager.css',
			array(),
			JTPC_VERSION
		);

		wp_enqueue_script(
			'jptc-manager',
			JTPC_PLUGIN_URL . 'assets/js/manager.js',
			array( 'jquery' ),
			JTPC_VERSION,
			true
		);

		wp_localize_script(
			'jptc-manager',
			'jptcManager',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'jptc_manager_nonce' ),
				'i18n'     => array(
					'confirm_delete' => __( 'Are you sure you want to delete this link?', 'jump-to-checkout' ),
					'delete_success' => __( 'Link deleted successfully.', 'jump-to-checkout' ),
					'delete_error'   => __( 'Error deleting link.', 'jump-to-checkout' ),
					'status_success' => __( 'Status updated successfully.', 'jump-to-checkout' ),
					'status_error'   => __( 'Error updating status.', 'jump-to-checkout' ),
					'copied'         => __( 'Copied!', 'jump-to-checkout' ),
					'copy_error'     => __( 'Failed to copy URL.', 'jump-to-checkout' ),
					'enable'         => __( 'Enable', 'jump-to-checkout' ),
					'disable'        => __( 'Disable', 'jump-to-checkout' ),
					'no_links'       => __( 'No links found.', 'jump-to-checkout' ),
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
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'jump-to-checkout' ) );
		}

		$links      = $this->db->get_links();
		$statistics = $this->db->get_statistics();
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Manage Jump to Checkout Links', 'jump-to-checkout' ); ?></h1>

			<?php if ( ! Features::is_pro() ) : ?>
				<div class="notice notice-info">
					<p>
						<strong><?php esc_html_e( 'FREE Version:', 'jump-to-checkout' ); ?></strong>
						<?php
						printf(
							/* translators: %1$d: active links, %2$d: max links */
							esc_html__( 'You have %1$d of %2$d active links.', 'jump-to-checkout' ),
							(int) Features::get_active_links_count(),
							(int) Features::max_links()
						);
						?>
						<a href="<?php echo esc_url( Features::get_upgrade_url() ); ?>" target="_blank">
							<?php esc_html_e( 'Upgrade to PRO for unlimited links', 'jump-to-checkout' ); ?>
						</a>
					</p>
				</div>
			<?php endif; ?>

			<div class="jump-to-checkout-stats-container">
				<div class="jump-to-checkout-stat-box">
					<div class="jump-to-checkout-stat-number"><?php echo esc_html( $statistics->total_links ); ?></div>
					<div class="jump-to-checkout-stat-label"><?php echo esc_html__( 'Total Links', 'jump-to-checkout' ); ?></div>
				</div>
				<div class="jump-to-checkout-stat-box">
					<div class="jump-to-checkout-stat-number"><?php echo esc_html( $statistics->active_links ); ?></div>
					<div class="jump-to-checkout-stat-label"><?php echo esc_html__( 'Active Links', 'jump-to-checkout' ); ?></div>
				</div>
				<div class="jump-to-checkout-stat-box">
					<div class="jump-to-checkout-stat-number"><?php echo esc_html( $statistics->total_visits ); ?></div>
					<div class="jump-to-checkout-stat-label"><?php echo esc_html__( 'Total Visits', 'jump-to-checkout' ); ?></div>
				</div>
				<div class="jump-to-checkout-stat-box">
					<div class="jump-to-checkout-stat-number"><?php echo esc_html( $statistics->total_conversions ); ?></div>
					<div class="jump-to-checkout-stat-label"><?php echo esc_html__( 'Total Conversions', 'jump-to-checkout' ); ?></div>
				</div>
				<div class="jump-to-checkout-stat-box">
					<div class="jump-to-checkout-stat-number">
						<?php
						if ( $statistics->total_visits > 0 ) {
							echo esc_html( number_format( ( $statistics->total_conversions / $statistics->total_visits ) * 100, 2 ) ) . '%';
						} else {
							echo '0%';
						}
						?>
					</div>
					<div class="jump-to-checkout-stat-label"><?php echo esc_html__( 'Conversion Rate', 'jump-to-checkout' ); ?></div>
				</div>
			</div>

			<?php if ( ! Features::is_pro() && ! Features::can_export() ) : ?>
				<div class="jump-to-checkout-upgrade-banner" style="background: #f0f6fc; border-left: 4px solid #2271b1; padding: 15px; margin: 20px 0; border-radius: 4px;">
					<p style="margin: 0;">
						<strong>📊 <?php esc_html_e( 'Need to export this data?', 'jump-to-checkout' ); ?></strong>
						<?php esc_html_e( 'With the PRO version you can export all statistics to CSV/Excel and access advanced analytics with charts.', 'jump-to-checkout' ); ?>
						<a href="<?php echo esc_url( Features::get_upgrade_url() ); ?>" class="button button-primary" style="margin-left: 10px;" target="_blank">
							<?php esc_html_e( 'Upgrade to PRO', 'jump-to-checkout' ); ?>
						</a>
					</p>
				</div>
			<?php endif; ?>

			<table class="wp-list-table widefat fixed striped jump-to-checkout-links-table">
				<thead>
					<tr>
						<th><?php echo esc_html__( 'Name', 'jump-to-checkout' ); ?></th>
						<th><?php echo esc_html__( 'Products', 'jump-to-checkout' ); ?></th>
						<th><?php echo esc_html__( 'Created', 'jump-to-checkout' ); ?></th>
						<th><?php echo esc_html__( 'Expires', 'jump-to-checkout' ); ?></th>
						<th><?php echo esc_html__( 'Visits', 'jump-to-checkout' ); ?></th>
						<th><?php echo esc_html__( 'Conversions', 'jump-to-checkout' ); ?></th>
						<th><?php echo esc_html__( 'Rate', 'jump-to-checkout' ); ?></th>
						<th><?php echo esc_html__( 'Status', 'jump-to-checkout' ); ?></th>
						<th><?php echo esc_html__( 'Actions', 'jump-to-checkout' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $links ) ) : ?>
						<tr>
							<td colspan="9" class="no-items"><?php echo esc_html__( 'No links found.', 'jump-to-checkout' ); ?></td>
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
									<div class="jump-to-checkout-link-url">
										<input type="text" value="<?php echo esc_attr( $link->url ); ?>" readonly class="jump-to-checkout-link-input" />
										<button type="button" class="button button-small jump-to-checkout-copy-url" data-url="<?php echo esc_attr( $link->url ); ?>">
											<?php echo esc_html__( 'Copy', 'jump-to-checkout' ); ?>
										</button>
									</div>
								</td>
								<td><?php echo esc_html( $product_count . ' ' . _n( 'product', 'products', $product_count, 'jump-to-checkout' ) ); ?></td>
								<td><?php echo esc_html( gmdate( 'Y-m-d H:i', strtotime( $link->created_at ) ) ); ?></td>
								<td>
									<?php
									if ( $link->expires_at ) {
										echo esc_html( gmdate( 'Y-m-d H:i', strtotime( $link->expires_at ) ) );
										if ( $is_expired ) {
											echo ' <span class="jump-to-checkout-expired">' . esc_html__( '(Expired)', 'jump-to-checkout' ) . '</span>';
										}
									} else {
										echo '<span class="jump-to-checkout-never">' . esc_html__( 'Never', 'jump-to-checkout' ) . '</span>';
									}
									?>
								</td>
								<td><strong><?php echo esc_html( $link->visits ); ?></strong></td>
								<td><strong><?php echo esc_html( $link->conversions ); ?></strong></td>
								<td><?php echo esc_html( number_format( $rate, 2 ) ); ?>%</td>
								<td>
									<span class="jump-to-checkout-status jump-to-checkout-status-<?php echo esc_attr( $link->status ); ?>">
										<?php echo esc_html( ucfirst( $link->status ) ); ?>
									</span>
								</td>
								<td class="jump-to-checkout-actions">
									<button type="button" class="button button-small jump-to-checkout-toggle-status" data-link-id="<?php echo esc_attr( $link->id ); ?>" data-status="<?php echo esc_attr( $link->status ); ?>">
										<?php echo 'active' === $link->status ? esc_html__( 'Disable', 'jump-to-checkout' ) : esc_html__( 'Enable', 'jump-to-checkout' ); ?>
									</button>
									<button type="button" class="button button-small button-link-delete jump-to-checkout-delete-link" data-link-id="<?php echo esc_attr( $link->id ); ?>">
										<?php echo esc_html__( 'Delete', 'jump-to-checkout' ); ?>
									</button>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<?php if ( ! Features::is_pro() ) : ?>
				<div class="jump-to-checkout-free-footer" style="margin-top: 30px; padding: 20px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px; text-align: center;">
					<p style="margin: 0 0 10px 0;"><strong><?php esc_html_e( 'You are using Jump to Checkout FREE', 'jump-to-checkout' ); ?></strong></p>
					<p style="margin: 0 0 15px 0; color: #666; font-size: 13px;">
						<?php esc_html_e( 'Developed by Close Technology', 'jump-to-checkout' ); ?> | 
						<a href="https://close.technology" target="_blank">close.technology</a>
					</p>
					<a href="<?php echo esc_url( Features::get_upgrade_url() ); ?>" class="button button-primary" target="_blank">
						<?php esc_html_e( 'Upgrade to PRO for all features', 'jump-to-checkout' ); ?>
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
		check_ajax_referer( 'jptc_manager_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'jump-to-checkout' ) ) );
		}

		$link_id = isset( $_POST['link_id'] ) ? absint( $_POST['link_id'] ) : 0;

		if ( ! $link_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid link ID.', 'jump-to-checkout' ) ) );
		}

		$result = $this->db->delete_link( $link_id );

		if ( $result ) {
			wp_send_json_success();
		} else {
			wp_send_json_error( array( 'message' => __( 'Error deleting link.', 'jump-to-checkout' ) ) );
		}
	}

	/**
	 * AJAX: Toggle status
	 *
	 * @return void
	 */
	public function ajax_toggle_status() {
		check_ajax_referer( 'jptc_manager_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'jump-to-checkout' ) ) );
		}

		$link_id = isset( $_POST['link_id'] ) ? absint( $_POST['link_id'] ) : 0;
		$status  = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '';

		if ( ! $link_id || ! $status ) {
			wp_send_json_error( array( 'message' => __( 'Invalid parameters.', 'jump-to-checkout' ) ) );
		}

		$new_status = 'active' === $status ? 'inactive' : 'active';

		// Check FREE limits when activating.
		if ( 'active' === $new_status && ! Features::is_pro() ) {
			if ( ! Features::can_create_link() ) {
				wp_send_json_error(
					array(
						'message'     => __( 'You cannot activate more links. You have reached the limit of 5 active links in the FREE version.', 'jump-to-checkout' ),
						'upgrade_url' => Features::get_upgrade_url(),
					)
				);
			}
		}

		$result = $this->db->update_status( $link_id, $new_status );

		if ( $result ) {
			wp_send_json_success( array( 'new_status' => $new_status ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Error updating status.', 'jump-to-checkout' ) ) );
		}
	}
}


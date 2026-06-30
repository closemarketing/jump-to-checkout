<?php
/**
 * Rating Notice
 *
 * Shows an admin notice asking users to rate the plugin after 14 days of use.
 * Supports permanent dismissal or a 90-day snooze.
 *
 * @package    CLOSE\JumpToCheckout\Admin
 * @author     Close Marketing
 * @copyright  2025 Closemarketing
 */

namespace CLOSE\JumpToCheckout\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Rating Notice Class
 */
class RatingNotice {

	const OPTION_INSTALLED = 'jptc_installed_date';
	const OPTION_DISMISSED = 'jptc_rating_dismissed';
	const DAYS_BEFORE_SHOW = 14;
	const DAYS_SNOOZE      = 90;
	const REVIEW_URL       = 'https://wordpress.org/support/plugin/jump-to-checkout/reviews/#new-post';

	/**
	 * Cached result of should_show() to avoid duplicate DB reads within a single request.
	 *
	 * @var bool|null
	 */
	private $show_cache = null;

	/**
	 * Constructor
	 */
	public function __construct() {
		// Ensure install date is recorded even on plugin updates (activation hook doesn't run on updates).
		// add_option() is a no-op when the option already exists, so this is cheap after first install.
		self::record_installation();

		add_action( 'admin_notices', array( $this, 'render_notice' ) );
		add_action( 'wp_ajax_jptc_dismiss_rating', array( $this, 'handle_dismiss' ) );
	}

	/**
	 * Record installation date once. Uses add_option() which is atomic and a no-op if already set.
	 *
	 * @return void
	 */
	public static function record_installation() {
		add_option( self::OPTION_INSTALLED, time(), '', false );
	}

	/**
	 * Whether the notice should be shown. Result is cached for the lifetime of the request.
	 *
	 * @return bool
	 */
	private function should_show() {
		if ( null !== $this->show_cache ) {
			return $this->show_cache;
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			$this->show_cache = false;
			return false;
		}

		$dismissed = get_option( self::OPTION_DISMISSED );

		// Permanently dismissed.
		if ( 'yes' === $dismissed ) {
			$this->show_cache = false;
			return false;
		}

		// Snoozed: check if snooze period has passed.
		if ( is_numeric( $dismissed ) && time() < (int) $dismissed ) {
			$this->show_cache = false;
			return false;
		}

		$installed = (int) get_option( self::OPTION_INSTALLED );

		if ( ! $installed ) {
			$this->show_cache = false;
			return false;
		}

		$days_active      = ( time() - $installed ) / DAY_IN_SECONDS;
		$this->show_cache = $days_active >= self::DAYS_BEFORE_SHOW;
		return $this->show_cache;
	}

	/**
	 * Render the admin notice and its inline JS handler.
	 *
	 * @return void
	 */
	public function render_notice() {
		if ( ! $this->should_show() ) {
			return;
		}

		?>
		<div class="notice notice-info jptc-rating-notice" style="display:flex;align-items:center;gap:16px;padding:12px 16px;">
			<span style="font-size:32px;line-height:1;">⭐</span>
			<div style="flex:1;">
				<p style="margin:0 0 8px;font-size:14px;">
					<strong><?php esc_html_e( 'Enjoying Jump to Checkout?', 'jump-to-checkout' ); ?></strong><br>
					<?php esc_html_e( 'If the plugin is working well for you, we\'d really appreciate a quick review on WordPress.org. It only takes a minute!', 'jump-to-checkout' ); ?>
				</p>
				<p style="margin:0;display:flex;gap:12px;flex-wrap:wrap;">
					<a href="<?php echo esc_url( self::REVIEW_URL ); ?>" target="_blank" rel="noopener noreferrer" class="button button-primary jptc-rating-action" data-action="yes">
						⭐ <?php esc_html_e( 'Rate it now!', 'jump-to-checkout' ); ?>
					</a>
					<button type="button" class="button jptc-rating-action" data-action="snooze">
						🕐 <?php esc_html_e( 'Remind me in 3 months', 'jump-to-checkout' ); ?>
					</button>
					<button type="button" class="button-link jptc-rating-action" data-action="yes" style="align-self:center;color:#999;">
						<?php esc_html_e( 'I already did', 'jump-to-checkout' ); ?>
					</button>
				</p>
			</div>
		</div>
		<script>
		(function() {
			var notice = document.querySelector('.jptc-rating-notice');
			if (!notice) return;

			notice.querySelectorAll('.jptc-rating-action').forEach(function(btn) {
				btn.addEventListener('click', function() {
					var action = this.getAttribute('data-action');
					var data = new FormData();
					data.append('action', 'jptc_dismiss_rating');
					data.append('nonce', '<?php echo esc_js( wp_create_nonce( 'jptc_rating_nonce' ) ); ?>');
					data.append('rating_action', action);

					fetch(ajaxurl, {method: 'POST', body: data, keepalive: true});
					notice.style.display = 'none';
				});
			});
		})();
		</script>
		<?php
	}

	/**
	 * Handle AJAX dismiss request.
	 *
	 * @return void
	 */
	public function handle_dismiss() {
		check_ajax_referer( 'jptc_rating_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( null, 403 );
		}

		if ( 'snooze' === sanitize_key( $_POST['rating_action'] ?? '' ) ) {
			update_option( self::OPTION_DISMISSED, time() + ( self::DAYS_SNOOZE * DAY_IN_SECONDS ), false );
		} else {
			update_option( self::OPTION_DISMISSED, 'yes', false );
		}

		wp_send_json_success();
	}
}

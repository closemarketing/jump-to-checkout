<?php
/**
 * Advanced Analytics
 *
 * Detailed dashboard with interactive charts, trends, and performance insights
 *
 * @package    CLOSE\JumpToCheckout\Admin
 * @author     Close Marketing
 * @copyright  2025 Closemarketing
 * @version    1.0.0
 */

namespace CLOSE\JumpToCheckout\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Advanced Analytics Class
 */
class AdvancedAnalytics {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_analytics_menu' ), 20 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_analytics_scripts' ) );
		add_action( 'wp_ajax_jptc_get_analytics_data', array( $this, 'ajax_get_analytics_data' ) );
	}

	/**
	 * Add analytics menu
	 *
	 * @return void
	 */
	public function add_analytics_menu() {
		add_submenu_page(
			'jptc-jump-to-checkout',
			__( 'Analytics', 'jump-to-checkout' ),
			__( 'Analytics', 'jump-to-checkout' ),
			'manage_woocommerce',
			'jptc-analytics',
			array( $this, 'render_analytics_page' )
		);
	}

	/**
	 * Enqueue analytics scripts
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_analytics_scripts( $hook ) {
		if ( 'jump-to-checkout_page_jptc-analytics' !== $hook ) {
			return;
		}

		wp_enqueue_script(
			'chartjs',
			'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js',
			array(),
			'4.4.0',
			true
		);

		wp_enqueue_style(
			'jptc-analytics',
			JTPC_PLUGIN_URL . 'assets/css/analytics.css',
			array(),
			JTPC_VERSION
		);

		wp_enqueue_script(
			'jptc-analytics',
			JTPC_PLUGIN_URL . 'assets/js/analytics.js',
			array( 'chartjs' ),
			JTPC_VERSION,
			true
		);

		wp_localize_script(
			'jptc-analytics',
			'jptcAnalytics',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'jptc_analytics_nonce' ),
				'i18n'     => array(
					'loading'             => __( 'Loading...', 'jump-to-checkout' ),
					'error'               => __( 'Error loading data', 'jump-to-checkout' ),
					'visits'              => __( 'Visits', 'jump-to-checkout' ),
					'conversions'         => __( 'Conversions', 'jump-to-checkout' ),
					'conversion_rate'     => __( 'Conversion Rate', 'jump-to-checkout' ),
					'total_links'         => __( 'Total Links', 'jump-to-checkout' ),
					'active_links'        => __( 'Active Links', 'jump-to-checkout' ),
					'total_visits'        => __( 'Total Visits', 'jump-to-checkout' ),
					'total_conversions'   => __( 'Total Conversions', 'jump-to-checkout' ),
					'avg_conversion_rate' => __( 'Avg. Conversion Rate', 'jump-to-checkout' ),
				),
			)
		);
	}

	/**
	 * Render analytics page
	 *
	 * @return void
	 */
	public function render_analytics_page() {
		?>
		<div class="wrap jptc-analytics-wrap">
			<h1><?php echo esc_html__( 'Analytics', 'jump-to-checkout' ); ?></h1>

			<div class="jptc-analytics-dashboard">
				<div class="jptc-analytics-stats">
					<div class="jptc-stat-card">
						<h3><?php echo esc_html__( 'Total Links', 'jump-to-checkout' ); ?></h3>
						<p class="jptc-stat-value" id="jptc-stat-total-links">-</p>
					</div>
					<div class="jptc-stat-card">
						<h3><?php echo esc_html__( 'Active Links', 'jump-to-checkout' ); ?></h3>
						<p class="jptc-stat-value" id="jptc-stat-active-links">-</p>
					</div>
					<div class="jptc-stat-card">
						<h3><?php echo esc_html__( 'Total Visits', 'jump-to-checkout' ); ?></h3>
						<p class="jptc-stat-value" id="jptc-stat-total-visits">-</p>
					</div>
					<div class="jptc-stat-card">
						<h3><?php echo esc_html__( 'Total Conversions', 'jump-to-checkout' ); ?></h3>
						<p class="jptc-stat-value" id="jptc-stat-total-conversions">-</p>
					</div>
					<div class="jptc-stat-card">
						<h3><?php echo esc_html__( 'Avg. Conversion Rate', 'jump-to-checkout' ); ?></h3>
						<p class="jptc-stat-value" id="jptc-stat-avg-conversion">-</p>
					</div>
				</div>

				<div class="jptc-analytics-charts">
					<div class="jptc-chart-container">
						<h2><?php echo esc_html__( 'Visits & Conversions Over Time', 'jump-to-checkout' ); ?></h2>
						<canvas id="jptc-chart-timeline"></canvas>
					</div>

					<div class="jptc-chart-container">
						<h2><?php echo esc_html__( 'Top Performing Links', 'jump-to-checkout' ); ?></h2>
						<canvas id="jptc-chart-top-links"></canvas>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * AJAX: Get analytics data
	 *
	 * @return void
	 */
	public function ajax_get_analytics_data() {
		check_ajax_referer( 'jptc_analytics_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'jump-to-checkout' ) ) );
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'jptc_links';

		$date_from = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : gmdate( 'Y-m-d', strtotime( '-30 days' ) );
		$date_to   = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : gmdate( 'Y-m-d' );

		$date_to_end = $date_to . ' 23:59:59';

		// Links created in the selected date range (for timeline and top-links table).
		$links = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table_name} WHERE created_at >= %s AND created_at <= %s ORDER BY created_at DESC", $date_from, $date_to_end ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// Global totals across all links regardless of creation date (visits/conversions are lifetime counters).
		$all_links = $wpdb->get_results( "SELECT status, visits, conversions FROM {$table_name}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$total_links       = count( $all_links );
		$active_links      = 0;
		$total_visits      = 0;
		$total_conversions = 0;

		foreach ( $all_links as $link ) {
			if ( 'active' === $link->status ) {
				++$active_links;
			}
			$total_visits      += $link->visits;
			$total_conversions += $link->conversions;
		}

		$avg_conversion_rate = 0;
		if ( $total_visits > 0 ) {
			$avg_conversion_rate = round( ( $total_conversions / $total_visits ) * 100, 2 );
		}

		$timeline_data = array();
		foreach ( $links as $link ) {
			$date = gmdate( 'Y-m-d', strtotime( $link->created_at ) );
			if ( ! isset( $timeline_data[ $date ] ) ) {
				$timeline_data[ $date ] = array(
					'visits'      => 0,
					'conversions' => 0,
				);
			}
			$timeline_data[ $date ]['visits']      += $link->visits;
			$timeline_data[ $date ]['conversions'] += $link->conversions;
		}

		ksort( $timeline_data );

		$top_links = array();
		foreach ( $links as $link ) {
			$conversion_rate = 0;
			if ( $link->visits > 0 ) {
				$conversion_rate = round( ( $link->conversions / $link->visits ) * 100, 2 );
			}
			$top_links[] = array(
				'name'            => $link->name,
				'visits'          => $link->visits,
				'conversions'     => $link->conversions,
				'conversion_rate' => $conversion_rate,
			);
		}

		usort(
			$top_links,
			function ( $a, $b ) {
				return $b['conversions'] - $a['conversions'];
			}
		);

		$top_links = array_slice( $top_links, 0, 10 );

		wp_send_json_success(
			array(
				'stats'     => array(
					'total_links'         => $total_links,
					'active_links'        => $active_links,
					'total_visits'        => $total_visits,
					'total_conversions'   => $total_conversions,
					'avg_conversion_rate' => $avg_conversion_rate,
				),
				'timeline'  => $timeline_data,
				'top_links' => $top_links,
			)
		);
	}
}

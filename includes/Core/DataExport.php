<?php
/**
 * Data Export Handler
 *
 * Exports statistics to CSV for external analysis
 *
 * @package    CLOSE\JumpToCheckout\Core
 * @author     Close Marketing
 * @copyright  2025 Closemarketing
 * @version    1.0.0
 */

namespace CLOSE\JumpToCheckout\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Data Export Class
 */
class DataExport {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'handle_export_request' ) );
	}

	/**
	 * Handle export request
	 *
	 * @return void
	 */
	public function handle_export_request() {
		if ( ! isset( $_GET['jptc_export'] ) || 'csv' !== $_GET['jptc_export'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to export data.', 'jump-to-checkout' ) );
		}

		check_admin_referer( 'jptc_export_csv' );

		$this->export_to_csv();
	}

	/**
	 * Export links data to CSV
	 *
	 * @return void
	 */
	private function export_to_csv() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'jptc_links';

		$links = $wpdb->get_results( "SELECT * FROM {$table_name} ORDER BY created_at DESC" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=jump-to-checkout-links-' . gmdate( 'Y-m-d' ) . '.csv' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$output = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen

		fprintf( $output, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );

		$headers = array(
			__( 'ID', 'jump-to-checkout' ),
			__( 'Name', 'jump-to-checkout' ),
			__( 'URL', 'jump-to-checkout' ),
			__( 'Status', 'jump-to-checkout' ),
			__( 'Created At', 'jump-to-checkout' ),
			__( 'Expires At', 'jump-to-checkout' ),
			__( 'Visits', 'jump-to-checkout' ),
			__( 'Conversions', 'jump-to-checkout' ),
			__( 'Conversion Rate', 'jump-to-checkout' ),
			__( 'Products', 'jump-to-checkout' ),
		);
		fputcsv( $output, $headers );

		foreach ( $links as $link ) {
			$products     = json_decode( $link->products, true );
			$product_list = array();
			if ( is_array( $products ) ) {
				foreach ( $products as $product ) {
					$product_obj = wc_get_product( isset( $product['variation_id'] ) && $product['variation_id'] ? $product['variation_id'] : $product['product_id'] );
					if ( $product_obj ) {
						$product_list[] = $product_obj->get_name() . ' (x' . ( isset( $product['quantity'] ) ? $product['quantity'] : 1 ) . ')';
					}
				}
			}

			$conversion_rate = 0;
			if ( $link->visits > 0 ) {
				$conversion_rate = round( ( $link->conversions / $link->visits ) * 100, 2 );
			}

			$row = array(
				$link->id,
				$link->name,
				$link->url,
				$link->status,
				$link->created_at,
				$link->expires_at ? $link->expires_at : __( 'Never', 'jump-to-checkout' ),
				$link->visits,
				$link->conversions,
				$conversion_rate . '%',
				implode( '; ', $product_list ),
			);
			fputcsv( $output, $row );
		}

		fclose( $output ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

}

<?php
/**
 * Database Handler Class
 *
 * Handles database operations for direct checkout links
 *
 * @package    CLOSE\JumpToCheckout\Database
 * @author     Close Marketing
 * @copyright  2025 Closemarketing
 * @version    1.0.0
 */

namespace CLOSE\JumpToCheckout\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Database Handler Class
 */
class Database {

	/**
	 * Table name
	 *
	 * @var string
	 */
	private $table_name;

	/**
	 * Constructor
	 */
	public function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'jptc_links';
	}

	/**
	 * Create database table
	 *
	 * @return void
	 */
	public function create_table() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL,
			token text NOT NULL,
			url text NOT NULL,
			products longtext NOT NULL,
			expiry_hours int(11) DEFAULT 0,
			expires_at datetime DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			visits int(11) DEFAULT 0,
			conversions int(11) DEFAULT 0,
			status varchar(20) DEFAULT 'active',
			PRIMARY KEY  (id),
			KEY name (name(191)),
			KEY status (status),
			KEY created_at (created_at)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Update version.
		update_option( 'jptc_db_version', '1.0.0' );
	}

	/**
	 * Check if table exists and create if needed
	 *
	 * @return void
	 */
	public function maybe_create_table() {
		$installed_version = get_option( 'jptc_db_version' );

		if ( '1.0.0' === $installed_version ) {
			return;
		}

		$this->create_table();
	}

	/**
	 * Insert new link
	 *
	 * @param array $data Link data.
	 * @return int|false Insert ID or false on failure.
	 */
	public function insert_link( $data ) {
		global $wpdb;

		$result = $wpdb->insert(
			$this->table_name,
			array(
				'name'         => $data['name'],
				'token'        => $data['token'],
				'url'          => $data['url'],
				'products'     => wp_json_encode( $data['products'] ),
				'expiry_hours' => $data['expiry_hours'],
				'expires_at'   => $data['expires_at'],
				'status'       => 'active',
			),
			array(
				'%s',
				'%s',
				'%s',
				'%s',
				'%d',
				'%s',
				'%s',
			)
		);

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Get link by token
	 *
	 * @param string $token Token.
	 * @return object|null
	 */
	public function get_link_by_token( $token ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table_name} WHERE token = %s",
				$token
			)
		);
	}

	/**
	 * Get all links
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	public function get_links( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'orderby' => 'created_at',
			'order'   => 'DESC',
			'limit'   => 50,
			'offset'  => 0,
			'status'  => '',
		);

		$args = wp_parse_args( $args, $defaults );

		// Sanitize orderby (whitelist).
		$allowed_orderby = array( 'id', 'name', 'created_at', 'visits', 'conversions', 'status', 'expires_at' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'created_at';

		// Sanitize order.
		$order = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';

		// Build WHERE clause.
		$where_clause = '1=1';
		$where_values = array();

		if ( ! empty( $args['status'] ) ) {
			$where_clause  .= ' AND status = %s';
			$where_values[] = $args['status'];
		}

		// Build base query with WHERE clause.
		$query = "SELECT * FROM {$this->table_name} WHERE {$where_clause}"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// Add ORDER BY (sanitized via whitelist).
		$query .= " ORDER BY {$orderby} {$order}"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// Add LIMIT and OFFSET.
		$query         .= ' LIMIT %d OFFSET %d';
		$where_values[] = $args['limit'];
		$where_values[] = $args['offset'];

		// Prepare the complete query.
		$prepared_query = $wpdb->prepare( $query, $where_values ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return $wpdb->get_results( $prepared_query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Get total count
	 *
	 * @return int
	 */
	public function get_total_count() {
		global $wpdb;
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table_name}" );
	}

	/**
	 * Update visit count
	 *
	 * @param int $link_id Link ID.
	 * @return bool
	 */
	public function increment_visits( $link_id ) {
		global $wpdb;

		return $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$this->table_name} SET visits = visits + 1 WHERE id = %d",
				$link_id
			)
		);
	}

	/**
	 * Update conversion count
	 *
	 * @param int $link_id Link ID.
	 * @return bool
	 */
	public function increment_conversions( $link_id ) {
		global $wpdb;

		return $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$this->table_name} SET conversions = conversions + 1 WHERE id = %d",
				$link_id
			)
		);
	}

	/**
	 * Update link status
	 *
	 * @param int    $link_id Link ID.
	 * @param string $status New status.
	 * @return bool
	 */
	public function update_status( $link_id, $status ) {
		global $wpdb;

		return $wpdb->update(
			$this->table_name,
			array( 'status' => $status ),
			array( 'id' => $link_id ),
			array( '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Delete link
	 *
	 * @param int $link_id Link ID.
	 * @return bool
	 */
	public function delete_link( $link_id ) {
		global $wpdb;

		return $wpdb->delete(
			$this->table_name,
			array( 'id' => $link_id ),
			array( '%d' )
		);
	}

	/**
	 * Get statistics
	 *
	 * @return object
	 */
	public function get_statistics() {
		global $wpdb;

		return $wpdb->get_row(
			"SELECT 
				COUNT(*) as total_links,
				SUM(visits) as total_visits,
				SUM(conversions) as total_conversions,
				COUNT(CASE WHEN status = 'active' THEN 1 END) as active_links
			FROM {$this->table_name}"
		);
	}
}

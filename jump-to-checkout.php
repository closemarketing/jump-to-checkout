<?php
/**
 * Plugin Name: Jump to Checkout
 * Plugin URI:  https://close.technology/wordpress-plugins/jump-to-checkout-pro/
 * Description: Generate direct checkout links with pre-selected products for WooCommerce.
 * Version:     1.0.2-rc.1
 * Author:      Close Marketing
 * Author URI:  https://close.marketing
 * Text Domain: jump-to-checkout
 * Domain Path: /languages
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Requires Plugins: woocommerce
 *
 * @package     WordPress
 * @author      Close Marketing
 * @copyright   2026 Closemarketing
 * @license     GPL-2.0+
 *
 * @wordpress-plugin
 *
 * Prefix:      jptc_
 */

defined( 'ABSPATH' ) || die( 'No script kiddies please!' );

define( 'JTPC_VERSION', '1.0.2-rc.1' );
define( 'JTPC_PLUGIN', __FILE__ );
define( 'JTPC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'JTPC_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'JTPC_IS_PRO', false ); // FREE version.
define( 'JTPC_UPGRADE_URL', 'https://close.technology/en/wordpress-plugins/jump-to-checkout-pro/' );

// Load Composer autoloader (includes PSR-4 autoload for plugin classes).
if ( file_exists( JTPC_PLUGIN_PATH . 'vendor/autoload.php' ) ) {
	require_once JTPC_PLUGIN_PATH . 'vendor/autoload.php';
}

add_action( 'plugins_loaded', 'jptc_plugin_init' );
/**
 * Load localization files
 *
 * @return void
 */
function jptc_plugin_init() {
	// Initialize plugin classes.
	if ( class_exists( 'CLOSE\JumpToCheckout\Core\JumpToCheckout' ) ) {
		// Check and update database if needed.
		$db = new CLOSE\JumpToCheckout\Database\Database();
		$db->maybe_create_table();

		new CLOSE\JumpToCheckout\Core\JumpToCheckout();
		new CLOSE\JumpToCheckout\Admin\AdminPanel();
		new CLOSE\JumpToCheckout\Admin\LinksManager();
	}
}

register_activation_hook( __FILE__, 'jptc_plugin_activate' );
/**
 * Plugin activation
 *
 * @return void
 */
function jptc_plugin_activate() {
	// Load Composer autoloader.
	if ( file_exists( JTPC_PLUGIN_PATH . 'vendor/autoload.php' ) ) {
		require_once JTPC_PLUGIN_PATH . 'vendor/autoload.php';
	}

	// Create database table.
	$db = new CLOSE\JumpToCheckout\Database\Database();
	$db->create_table();

	// Create instance to register rewrite rules.
	$checkout = new CLOSE\JumpToCheckout\Core\JumpToCheckout();
	$checkout->add_rewrite_rules();

	// Flush rewrite rules.
	flush_rewrite_rules();
}

// Deactivation hook.
register_deactivation_hook( __FILE__, 'jptc_plugin_deactivate' );
/**
 * Plugin deactivation
 *
 * @return void
 */
function jptc_plugin_deactivate() {
	// Flush rewrite rules.
	flush_rewrite_rules();
}

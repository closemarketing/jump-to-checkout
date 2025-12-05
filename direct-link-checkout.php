<?php
/**
 * Plugin Name: Direct Link Checkout
 * Plugin URI:  https://close.technology/plugins/direct-link-checkout/
 * Description: Generate direct checkout links with pre-selected products for WooCommerce. FREE version limited to 5 links and 1 product per link.
 * Version:     1.0.0
 * Author:      Close Marketing
 * Author URI:  https://close.marketing
 * Text Domain: direct-link-checkout
 * Domain Path: /languages
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Requires Plugins: woocommerce
 *
 * @package     WordPress
 * @author      Close Marketing
 * @copyright   2025 Closemarketing
 * @license     GPL-2.0+
 *
 * @wordpress-plugin
 *
 * Prefix:      cldc_
 */

defined( 'ABSPATH' ) || die( 'No script kiddies please!' );

define( 'CLDC_VERSION', '1.0.0' );
define( 'CLDC_PLUGIN', __FILE__ );
define( 'CLDC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CLDC_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'CLDC_IS_PRO', false ); // FREE version.

// Load Composer autoloader (includes PSR-4 autoload for plugin classes).
if ( file_exists( CLDC_PLUGIN_PATH . 'vendor/autoload.php' ) ) {
	require_once CLDC_PLUGIN_PATH . 'vendor/autoload.php';
}

add_action( 'plugins_loaded', 'cldc_plugin_init' );
/**
 * Load localization files
 *
 * @return void
 */
function cldc_plugin_init() {
	load_plugin_textdomain( 'direct-link-checkout', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	// Initialize plugin classes.
	if ( class_exists( 'CLOSE\DirectLinkCheckout\Core\DirectCheckout' ) ) {
		new CLOSE\DirectLinkCheckout\Core\DirectCheckout();
		new CLOSE\DirectLinkCheckout\Admin\AdminPanel();
		new CLOSE\DirectLinkCheckout\Admin\LinksManager();
	}
}

register_activation_hook( __FILE__, 'cldc_plugin_activate' );
/**
 * Plugin activation
 *
 * @return void
 */
function cldc_plugin_activate() {
	// Load Composer autoloader.
	if ( file_exists( CLDC_PLUGIN_PATH . 'vendor/autoload.php' ) ) {
		require_once CLDC_PLUGIN_PATH . 'vendor/autoload.php';
	}

	// Create database table.
	$db = new CLOSE\DirectLinkCheckout\Database\Database();
	$db->create_table();

	// Create instance to register rewrite rules.
	$checkout = new CLOSE\DirectLinkCheckout\Core\DirectCheckout();
	$checkout->add_rewrite_rules();

	// Flush rewrite rules.
	flush_rewrite_rules();
}

// Deactivation hook.
register_deactivation_hook( __FILE__, 'cldc_plugin_deactivate' );
/**
 * Plugin deactivation
 *
 * @return void
 */
function cldc_plugin_deactivate() {
	// Flush rewrite rules.
	flush_rewrite_rules();
}


<?php
/**
 * PHPStan Bootstrap File
 * 
 * This file defines constants and functions that PHPStan needs to understand
 * but are not available during static analysis.
 */

// Define plugin constants that are used throughout the codebase
if (!defined('JTPC_PLUGIN_URL')) {
    define('JTPC_PLUGIN_URL', 'http://localhost/wp-content/plugins/jump-to-checkout/');
}

if (!defined('JTPC_VERSION')) {
    define('JTPC_VERSION', '1.0.0');
}

if (!defined('JTPC_FILE')) {
    define('JTPC_FILE', __FILE__);
}

if (!defined('JTPC_PLUGIN_PATH')) {
    define('JTPC_PLUGIN_PATH', dirname(__FILE__) . '/');
}

if (!defined('JTPC_UPGRADE_URL')) {
    define('JTPC_UPGRADE_URL', 'https://close.technology/en/wordpress-plugins/jump-to-checkout-pro/');
}

// Define WordPress constants that might be missing
if (!defined('DOING_AJAX')) {
    define('DOING_AJAX', false);
}

if (!defined('WP_DEBUG')) {
    define('WP_DEBUG', false);
}

if (!defined('ABSPATH')) {
    define('ABSPATH', '/path/to/wordpress/');
}

// Mock WordPress functions that PHPStan can't find
if (!function_exists('wp_doing_ajax')) {
    function wp_doing_ajax() {
        return defined('DOING_AJAX') && DOING_AJAX;
    }
}

// Mock Action Scheduler function
if (!function_exists('as_schedule_recurring_action')) {
    function as_schedule_recurring_action($timestamp, $interval_in_seconds, $hook, $args = [], $group = '') {
        return true;
    }
}

// Mock WP_CLI class
if (!class_exists('WP_CLI')) {
	class WP_CLI {
			public static function line($message) {
					echo $message . "\n";
			}
			public static function add_command($command, $class) {
					return true;
			}
	}
}


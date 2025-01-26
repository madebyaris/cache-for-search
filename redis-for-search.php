<?php
/**
 * Plugin Name: Redis for Search
 * Plugin URI: maddebyaris.com
 * Description: A WordPress plugin that caches search results using Redis or Disk, improving search performance and reducing database load.
 * Version: 1.0.0
 * Author: M Aris Setiawan
 * Author URI: https://madebyaris.com/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: redis-for-search
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define plugin constants
define('RFS_VERSION', '1.0.0');
define('RFS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('RFS_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include required files
require_once RFS_PLUGIN_DIR . 'includes/class-redis-for-search.php';
require_once RFS_PLUGIN_DIR . 'includes/class-redis-for-search-admin.php';
require_once RFS_PLUGIN_DIR . 'includes/class-redis-for-search-smart-cache.php';

// Initialize the plugin
function redis_for_search_init() {
    $plugin = new Redis_For_Search();
    $plugin->init();
    
    if (is_admin()) {
        $admin = new Redis_For_Search_Admin();
        $admin->init();
    }
    
    // Initialize smart cache
    $smart_cache = new Redis_For_Search_Smart_Cache();
    $smart_cache->init();
}
add_action('plugins_loaded', 'redis_for_search_init');

// Activation hook
register_activation_hook(__FILE__, 'redis_for_search_activate');
function redis_for_search_activate() {
    // Set default options
    $default_options = array(
        'cache_type' => 'disk',
        'redis_host' => 'localhost',
        'redis_port' => '6379',
        'redis_username' => '',
        'redis_password' => '',
        'cache_ttl' => 3600, // 1 hour in seconds
        'enable_stats' => true, // Enable statistics tracking by default
        'enable_smart_cache' => false, // Smart cache disabled by default
    );
    
    add_option('redis_for_search_options', $default_options);
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'redis_for_search_deactivate');
function redis_for_search_deactivate() {
    // Cleanup tasks if needed
}
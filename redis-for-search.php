<?php
/**
 * Plugin Name: Redis for Search
 * Description: A WordPress plugin that caches search results using Redis or Disk to improve performance and reduce database load.
 * Version: 1.0.1
 * Author: M Aris Setiawan
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

// Include Composer's autoloader
if (file_exists(RFS_PLUGIN_DIR . 'vendor/autoload.php')) {
    require_once RFS_PLUGIN_DIR . 'vendor/autoload.php';
} else {
    wp_die('Please run composer install in the redis-for-search plugin directory.');
}

// Load core plugin class
require_once RFS_PLUGIN_DIR . 'includes/class-redis-for-search.php';
require_once RFS_PLUGIN_DIR . 'includes/class-redis-for-search-admin.php';

// Initialize the plugin
function redis_for_search_init() {
    $instance = Redis_For_Search::get_instance();
    
    // Initialize admin
    if (is_admin()) {
        $admin = new Redis_For_Search_Admin();
        $admin->init();
    }
    
    return $instance;
}
add_action('plugins_loaded', 'redis_for_search_init');

// Register WP-CLI commands if WP-CLI is available
if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('redis-for-search rebuild', function() {
        $smart_cache = Redis_For_Search::get_instance()->get_smart_cache();
        $smart_cache->init();
        
        WP_CLI::log('Starting cache rebuild...');
        $result = $smart_cache->rebuild_cache();
        
        if ($result) {
            WP_CLI::success('Cache rebuild completed successfully.');
        } else {
            WP_CLI::error('Cache rebuild failed. Check the error logs for details.');
        }
    }, array(
        'shortdesc' => 'Rebuild the Redis for Search cache',
        'longdesc'  => 'This command rebuilds the Redis for Search cache by processing all published posts and pages.',
    ));
}

// Activation hook
register_activation_hook(__FILE__, 'redis_for_search_activate');
function redis_for_search_activate() {
    // Create cache directories
    $cache_dir = WP_CONTENT_DIR . '/cache/redis-for-search/smart/';
    if (!file_exists($cache_dir)) {
        wp_mkdir_p($cache_dir);
        @chmod($cache_dir, 0755);
    }
    
    // Create subdirectories
    $subdirs = array('posts', 'words');
    foreach ($subdirs as $subdir) {
        $dir = $cache_dir . $subdir . '/';
        if (!file_exists($dir)) {
            wp_mkdir_p($dir);
            @chmod($dir, 0755);
        }
    }

    // Initialize empty data.json
    $data_file = $cache_dir . 'posts/data.json';
    if (!file_exists($data_file)) {
        $safeWriter = new \Webimpress\SafeWriter\FileWriter();
        $safeWriter->writeFile($data_file, json_encode(array()));
        @chmod($data_file, 0644);
    }

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
    // Clean up any plugin data if needed
}
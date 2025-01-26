<?php

if (!defined('ABSPATH')) {
    exit;
}

class Redis_For_Search_Admin {
    private $options;

    public function init() {
        $this->options = get_option('redis_for_search_options');
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_post_reset_search_cache', array($this, 'reset_search_cache'));
        add_action('admin_post_build_search_index', array($this, 'build_search_index'));
        add_action('admin_post_test_data_json', array($this, 'test_data_json'));
    }

    public function add_admin_menu() {
        add_options_page(
            __('Redis for Search Settings', 'redis-for-search'),
            __('Redis for Search', 'redis-for-search'),
            'manage_options',
            'redis-for-search',
            array($this, 'render_settings_page')
        );
    }

    public function register_settings() {
        register_setting('redis_for_search_options', 'redis_for_search_options', array($this, 'validate_options'));

        add_settings_section(
            'redis_for_search_general',
            __('General Settings', 'redis-for-search'),
            array($this, 'render_section_info'),
            'redis-for-search'
        );

        add_settings_field(
            'enable_cache',
            __('Enable Caching', 'redis-for-search'),
            array($this, 'render_enable_cache_field'),
            'redis-for-search',
            'redis_for_search_general'
        );

        add_settings_field(
            'cache_type',
            __('Cache Storage Type', 'redis-for-search'),
            array($this, 'render_cache_type_field'),
            'redis-for-search',
            'redis_for_search_general'
        );

        add_settings_field(
            'cache_ttl',
            __('Cache Time to Live (seconds)', 'redis-for-search'),
            array($this, 'render_cache_ttl_field'),
            'redis-for-search',
            'redis_for_search_general'
        );

        add_settings_field(
            'redis_host',
            __('Redis Host', 'redis-for-search'),
            array($this, 'render_redis_host_field'),
            'redis-for-search',
            'redis_for_search_general'
        );

        add_settings_field(
            'redis_port',
            __('Redis Port', 'redis-for-search'),
            array($this, 'render_redis_port_field'),
            'redis-for-search',
            'redis_for_search_general'
        );

        add_settings_field(
            'redis_username',
            __('Redis Username', 'redis-for-search'),
            array($this, 'render_redis_username_field'),
            'redis-for-search',
            'redis_for_search_general'
        );

        add_settings_field(
            'redis_password',
            __('Redis Password', 'redis-for-search'),
            array($this, 'render_redis_password_field'),
            'redis-for-search',
            'redis_for_search_general'
        );

        add_settings_field(
            'auto_revalidate',
            __('Auto Revalidate Cache', 'redis-for-search'),
            array($this, 'render_auto_revalidate_field'),
            'redis-for-search',
            'redis_for_search_general'
        );

        add_settings_field(
            'enable_smart_cache',
            __('Enable Smart Cache', 'redis-for-search'),
            array($this, 'render_enable_smart_cache_field'),
            'redis-for-search',
            'redis_for_search_general'
        );
    }

    public function render_section_info() {
        echo '<p>' . __('Configure your Redis connection and cache settings below.', 'redis-for-search') . '</p>';
    }

    public function render_cache_type_field() {
        $cache_type = isset($this->options['cache_type']) ? $this->options['cache_type'] : 'disk';
        ?>
        <select name="redis_for_search_options[cache_type]" id="cache_type">
            <option value="disk" <?php selected($cache_type, 'disk'); ?>><?php _e('Disk', 'redis-for-search'); ?></option>
            <option value="redis" <?php selected($cache_type, 'redis'); ?>><?php _e('Redis', 'redis-for-search'); ?></option>
        </select>
        <?php
    }

    public function render_enable_stats_field() {
        $enable_stats = isset($this->options['enable_stats']) ? $this->options['enable_stats'] : true;
        ?>
        <input type="checkbox" id="enable_stats" name="redis_for_search_options[enable_stats]" value="1" <?php checked($enable_stats); ?> />
        <label for="enable_stats"><?php _e('Enable Cache Statistics', 'redis-for-search'); ?></label>
        <?php
    }

    public function render_cache_ttl_field() {
        $cache_ttl = isset($this->options['cache_ttl']) ? $this->options['cache_ttl'] : 3600;
        ?>
        <input type="number" name="redis_for_search_options[cache_ttl]" value="<?php echo esc_attr($cache_ttl); ?>" min="1" max="31536000" />
        <p class="description">
            <?php _e('Time in seconds before cache invalidation.', 'redis-for-search'); ?>
            <?php _e('For large blogs, consider using longer cache periods:', 'redis-for-search'); ?>
            <br>
            <?php _e('- 2,592,000 seconds = 1 month', 'redis-for-search'); ?>
            <br>
            <?php _e('- 31,536,000 seconds = 1 year', 'redis-for-search'); ?>
            <br>
            <?php _e('Longer cache periods improve performance but may delay content updates in search results.', 'redis-for-search'); ?>
        </p>
        <?php
    }

    public function render_redis_host_field() {
        $redis_host = isset($this->options['redis_host']) ? $this->options['redis_host'] : 'localhost';
        ?>
        <input type="text" name="redis_for_search_options[redis_host]" value="<?php echo esc_attr($redis_host); ?>" />
        <?php
    }

    public function render_redis_port_field() {
        $redis_port = isset($this->options['redis_port']) ? $this->options['redis_port'] : '6379';
        ?>
        <input type="text" name="redis_for_search_options[redis_port]" value="<?php echo esc_attr($redis_port); ?>" />
        <?php
    }

    public function render_redis_username_field() {
        $redis_username = isset($this->options['redis_username']) ? $this->options['redis_username'] : '';
        ?>
        <input type="text" name="redis_for_search_options[redis_username]" value="<?php echo esc_attr($redis_username); ?>" />
        <?php
    }

    public function render_redis_password_field() {
        $redis_password = isset($this->options['redis_password']) ? $this->options['redis_password'] : '';
        ?>
        <input type="password" name="redis_for_search_options[redis_password]" value="<?php echo esc_attr($redis_password); ?>" />
        <?php
    }

    public function render_auto_revalidate_field() {
        $auto_revalidate = isset($this->options['auto_revalidate']) ? $this->options['auto_revalidate'] : false;
        ?>
        <input type="checkbox" id="auto_revalidate" name="redis_for_search_options[auto_revalidate]" value="1" <?php checked($auto_revalidate); ?> />
        <label for="auto_revalidate"><?php _e('Automatically revalidate cache when content changes', 'redis-for-search'); ?></label>
        <?php
    }

    public function render_enable_smart_cache_field() {
        $enable_smart_cache = isset($this->options['enable_smart_cache']) ? $this->options['enable_smart_cache'] : false;
        ?>
        <input type="checkbox" id="enable_smart_cache" name="redis_for_search_options[enable_smart_cache]" value="1" <?php checked($enable_smart_cache); ?> />
        <label for="enable_smart_cache"><?php _e('Enable advanced smart cache for better search performance', 'redis-for-search'); ?></label>
        <p class="description">
            <?php _e('Smart cache significantly reduces MySQL database connections by:', 'redis-for-search'); ?>
            <ul style="list-style-type: disc; margin-left: 20px;">
                <li><?php _e('Storing post data in Redis memory for faster access', 'redis-for-search'); ?></li>
                <li><?php _e('Reducing the need for repeated database queries', 'redis-for-search'); ?></li>
                <li><?php _e('Ideal for sites with MySQL connection limits or high traffic', 'redis-for-search'); ?></li>
            </ul>
        </p>
        <?php if ($enable_smart_cache): ?>
            <p>
                <a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=build_search_index'), 'build_search_index_nonce', 'build_index_nonce'); ?>" class="button button-secondary">
                    <?php _e('Build Index Cache', 'redis-for-search'); ?>
                </a>
                <a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=test_data_json'), 'test_data_json_nonce', 'test_nonce'); ?>" class="button button-secondary">
                    <?php _e('Test data.json', 'redis-for-search'); ?>
                </a>
            </p>
        <?php endif; ?>
        <?php
    }

    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'settings';
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <h2 class="nav-tab-wrapper">
                <a href="?page=redis-for-search&tab=settings" class="nav-tab <?php echo $active_tab == 'settings' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Settings', 'redis-for-search'); ?>
                </a>
                <a href="?page=redis-for-search&tab=cache" class="nav-tab <?php echo $active_tab == 'cache' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Cache Management', 'redis-for-search'); ?>
                </a>
            </h2>

            <?php if ($active_tab == 'settings'): ?>
                <form action="options.php" method="post">
                    <?php
                    settings_fields('redis_for_search_options');
                    do_settings_sections('redis-for-search');
                    submit_button();
                    ?>
                </form>
            <?php elseif ($active_tab == 'cache'): ?>
                <?php
                $redis = new Redis_For_Search();
                $stats = get_option('redis_for_search_stats');
                $total_requests = $stats['hits'] + $stats['misses'];
                $hit_rate = $total_requests > 0 ? round(($stats['hits'] / $total_requests) * 100, 2) : 0;
                ?>
                <div class="cache-stats">
                    <h3><?php _e('Cache Statistics', 'redis-for-search'); ?></h3>
                    <p>
                        <strong><?php _e('Cache Hits:', 'redis-for-search'); ?></strong> <?php echo number_format($stats['hits']); ?><br>
                        <strong><?php _e('Cache Misses:', 'redis-for-search'); ?></strong> <?php echo number_format($stats['misses']); ?><br>
                        <strong><?php _e('Hit Rate:', 'redis-for-search'); ?></strong> <?php echo $hit_rate; ?>%<br>
                        <strong><?php _e('Last Reset:', 'redis-for-search'); ?></strong> <?php echo date('Y-m-d H:i:s', $stats['last_reset']); ?>
                    </p>
                </div>
                <form action="<?php echo admin_url('admin-post.php'); ?>" method="post" style="margin-top: 20px;">
                    <input type="hidden" name="action" value="reset_search_cache" />
                    <?php wp_nonce_field('reset_search_cache_nonce', 'reset_cache_nonce'); ?>
                    <?php submit_button(__('Reset Cache', 'redis-for-search'), 'secondary', 'reset-cache', false); ?>
                </form>
            <?php endif; ?>
        </div>
        <?php
    }

    public function render_enable_cache_field() {
        $enable_cache = isset($this->options['enable_cache']) ? $this->options['enable_cache'] : true;
        ?>
        <input type="checkbox" id="enable_cache" name="redis_for_search_options[enable_cache]" value="1" <?php checked($enable_cache); ?> />
        <label for="enable_cache"><?php _e('Enable Search Results Caching', 'redis-for-search'); ?></label>
        <p class="description"><?php _e('When disabled, the plugin will not cache any search results.', 'redis-for-search'); ?></p>
        <?php
    }

    public function validate_options($input) {
        $new_input = array();

        $new_input['cache_type'] = in_array($input['cache_type'], array('disk', 'redis')) ? $input['cache_type'] : 'disk';
        $new_input['cache_ttl'] = absint($input['cache_ttl']);
        $new_input['redis_host'] = sanitize_text_field($input['redis_host']);
        $new_input['redis_port'] = sanitize_text_field($input['redis_port']);
        $new_input['redis_username'] = sanitize_text_field($input['redis_username']);
        $new_input['redis_password'] = sanitize_text_field($input['redis_password']);
        $new_input['auto_revalidate'] = isset($input['auto_revalidate']) ? true : false;
        $new_input['enable_cache'] = isset($input['enable_cache']) ? true : false;
        $new_input['enable_stats'] = isset($input['enable_stats']) ? true : false;
        $new_input['enable_smart_cache'] = isset($input['enable_smart_cache']) ? true : false;

        return $new_input;
    }

    public function reset_search_cache() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        check_admin_referer('reset_search_cache_nonce', 'reset_cache_nonce');

        $cache_type = isset($this->options['cache_type']) ? $this->options['cache_type'] : 'disk';
        
        if ($cache_type === 'redis') {
            // Reset Redis cache
            $redis = new Redis_For_Search();
            $redis->flush_cache();
        } else {
            // Reset disk cache
            $cache_dir = WP_CONTENT_DIR . '/cache/redis-for-search/';
            if (is_dir($cache_dir)) {
                array_map('unlink', glob("$cache_dir/*.*"));
            }
        }

        // Reset cache statistics
        $reset_stats = array(
            'hits' => 0,
            'misses' => 0,
            'last_reset' => current_time('timestamp')
        );
        update_option('redis_for_search_stats', $reset_stats);

        wp_redirect(add_query_arg(
            array('page' => 'redis-for-search', 'cache-cleared' => '1'),
            admin_url('options-general.php')
        ));
        exit;
    }

    public function build_search_index() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        check_admin_referer('build_search_index_nonce', 'build_index_nonce');

        $smart_cache = new Redis_For_Search_Smart_Cache();
        $smart_cache->rebuild_cache();

        wp_redirect(add_query_arg(
            array('page' => 'redis-for-search', 'index-built' => '1'),
            admin_url('options-general.php')
        ));
        exit;
    }

    public function test_data_json() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        // Handle both AJAX and form submissions
        $is_ajax = defined('DOING_AJAX') && DOING_AJAX;
        if ($is_ajax) {
            check_ajax_referer('test_data_json_nonce');
        } else {
            check_admin_referer('test_data_json_nonce', 'test_nonce');
        }

        $test_data = array(
            '1' => array(
                'post_title' => 'Test Post',
                'post_content' => 'This is a test post content.',
                'post_type' => 'post',
                'post_status' => 'publish',
                'taxonomies' => array(
                    'category' => array('Test Category'),
                    'post_tag' => array('test', 'sample')
                ),
                'meta' => array(
                    '_thumbnail_id' => '123'
                )
            )
        );

        $cache_dir = WP_CONTENT_DIR . '/cache/redis-for-search/smart/posts';
        if (!file_exists($cache_dir)) {
            wp_mkdir_p($cache_dir);
            chmod($cache_dir, 0755);
        }

        $data_file = $cache_dir . '/data.json';
        $result = file_put_contents($data_file, json_encode($test_data, JSON_PRETTY_PRINT));
        if ($result === false) {
            $message = __('Failed to write test data to data.json', 'redis-for-search');
            if ($is_ajax) {
                wp_send_json_error($message);
            } else {
                add_settings_error('redis_for_search', 'test_data_json_error', $message, 'error');
            }
        } else {
            chmod($data_file, 0644);
            $message = __('Successfully wrote test data to data.json', 'redis-for-search');
            if ($is_ajax) {
                wp_send_json_success($message);
            } else {
                add_settings_error('redis_for_search', 'test_data_json_success', $message, 'success');
            }
        }

        if (!$is_ajax) {
            wp_redirect(add_query_arg(
                array('page' => 'redis-for-search', 'settings-updated' => 'true'),
                admin_url('options-general.php')
            ));
            exit;
        }
    }
}
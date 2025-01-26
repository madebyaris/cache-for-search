<?php

if (!defined('ABSPATH')) {
    exit;
}

class Redis_For_Search {
    private $redis;
    private $options;
    private $cache_prefix = 'rfs_';
    private $cache_stats = array(
        'hits' => 0,
        'misses' => 0,
        'last_reset' => 0
    );

    public function init() {
        if (is_admin()) {
            return;
        }
        
        $this->options = get_option('redis_for_search_options');
        $this->cache_stats = get_option('redis_for_search_stats');
        if (empty($this->cache_stats) || !isset($this->cache_stats['last_reset'])) {
            $this->cache_stats = array(
                'hits' => 0,
                'misses' => 0,
                'last_reset' => current_time('timestamp')
            );
            update_option('redis_for_search_stats', $this->cache_stats);
        }
        add_filter('posts_pre_query', array($this, 'get_cached_search_results'), 10, 2);
        add_action('pre_get_posts', array($this, 'cache_search_results'));
        
        // Add hooks for cache revalidation if enabled
        if (isset($this->options['auto_revalidate']) && $this->options['auto_revalidate']) {
            add_action('save_post', array($this, 'invalidate_cache_on_post_update'), 10, 3);
            add_action('delete_post', array($this, 'invalidate_cache_on_post_update'), 10);
        }
    }

    private function connect_redis() {
        if ($this->redis !== null) {
            return true;
        }

        if (!class_exists('Redis')) {
            return false;
        }

        try {
            $this->redis = new Redis();
            $host = isset($this->options['redis_host']) ? $this->options['redis_host'] : 'localhost';
            $port = isset($this->options['redis_port']) ? $this->options['redis_port'] : 6379;
            
            if (!$this->redis->connect($host, $port)) {
                return false;
            }

            $username = isset($this->options['redis_username']) ? $this->options['redis_username'] : '';
            $password = isset($this->options['redis_password']) ? $this->options['redis_password'] : '';

            if (!empty($username) && !empty($password)) {
                if (!$this->redis->auth([$username, $password])) {
                    return false;
                }
            } elseif (!empty($password)) {
                if (!$this->redis->auth($password)) {
                    return false;
                }
            }

            return true;
        } catch (Exception $e) {
            error_log('Redis connection error: ' . $e->getMessage());
            return false;
        }
    }

    public function get_cached_search_results($posts, $query) {
        if (!$query->is_search() || !$query->is_main_query() || 
            !isset($this->options['enable_cache']) || !$this->options['enable_cache']) {
            return $posts;
        }

        $cache_key = $this->get_cache_key($query);
        $cache_type = isset($this->options['cache_type']) ? $this->options['cache_type'] : 'disk';

        if ($cache_type === 'redis') {
            if (!$this->connect_redis()) {
                return $posts;
            }
            $cached_results = $this->redis->get($cache_key);
        } else {
            $cache_file = $this->get_disk_cache_path($cache_key);
            if (!file_exists($cache_file)) {
                return $posts;
            }
            $cached_results = file_get_contents($cache_file);
        }

        if ($cached_results) {
            $this->increment_cache_stat('hits');
            return unserialize($cached_results);
        }

        $this->increment_cache_stat('misses');
        $this->increment_cache_stat('misses');
        return $posts;
    }

    private function increment_cache_stat($type) {
        if (!isset($this->cache_stats[$type]) || !isset($this->options['enable_stats']) || !$this->options['enable_stats']) {
            return;
        }
        $this->cache_stats[$type]++;
        update_option('redis_for_search_stats', $this->cache_stats);
    }

    public function get_cache_stats() {
        if (!isset($this->options['enable_stats']) || !$this->options['enable_stats']) {
            return array(
                'hits' => 0,
                'misses' => 0,
                'last_reset' => current_time('timestamp')
            );
        }
        $this->cache_stats = get_option('redis_for_search_stats');
        return $this->cache_stats;
    }

    public function reset_cache_stats() {
        $this->cache_stats = array(
            'hits' => 0,
            'misses' => 0,
            'last_reset' => current_time('timestamp')
        );
        update_option('redis_for_search_stats', $this->cache_stats);
    }

    public function cache_search_results($query) {
        if (!$query->is_search() || !$query->is_main_query() || 
            !isset($this->options['enable_cache']) || !$this->options['enable_cache']) {
            return;
        }

        add_filter('posts_results', function($posts, $q) use ($query) {
            if ($q === $query) {
                $cache_key = $this->get_cache_key($query);
                $cache_type = isset($this->options['cache_type']) ? $this->options['cache_type'] : 'disk';
                $serialized_posts = serialize($posts);

                if ($cache_type === 'redis') {
                    if ($this->connect_redis()) {
                        $ttl = isset($this->options['cache_ttl']) ? $this->options['cache_ttl'] : 3600;
                        $this->redis->setex($cache_key, $ttl, $serialized_posts);
                    }
                } else {
                    $cache_file = $this->get_disk_cache_path($cache_key);
                    $cache_dir = dirname($cache_file);
                    
                    if (!is_dir($cache_dir)) {
                        wp_mkdir_p($cache_dir);
                    }
                    
                    file_put_contents($cache_file, $serialized_posts);
                }
            }
            return $posts;
        }, 10, 2);
    }

    private function get_cache_key($query) {
        $key_parts = array(
            isset($query->query_vars['s']) ? $query->query_vars['s'] : '',
            isset($query->query_vars['posts_per_page']) ? $query->query_vars['posts_per_page'] : get_option('posts_per_page'),
            isset($query->query_vars['paged']) ? $query->query_vars['paged'] : 1,
            isset($query->query_vars['orderby']) ? $query->query_vars['orderby'] : 'date',
            isset($query->query_vars['order']) ? $query->query_vars['order'] : 'DESC'
        );
        return $this->cache_prefix . md5(serialize($key_parts));
    }

    private function get_disk_cache_path($cache_key) {
        return WP_CONTENT_DIR . '/cache/redis-for-search/' . $cache_key . '.cache';
    }

    public function flush_cache() {
        $cache_type = isset($this->options['cache_type']) ? $this->options['cache_type'] : 'disk';

        if ($cache_type === 'redis') {
            if ($this->connect_redis()) {
                $keys = $this->redis->keys($this->cache_prefix . '*');
                if (!empty($keys)) {
                    foreach ($keys as $key) {
                        $this->redis->del($key);
                    }
                }
            }
        } else {
            $cache_dir = WP_CONTENT_DIR . '/cache/redis-for-search/';
            if (is_dir($cache_dir)) {
                array_map('unlink', glob("$cache_dir/*.*"));
            }
        }
    }

    public function invalidate_cache_on_post_update($post_id, $post = null, $update = null) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        $this->flush_cache();
    }
}
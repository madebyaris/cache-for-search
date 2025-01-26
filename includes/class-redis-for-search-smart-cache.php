<?php

if (!defined('ABSPATH')) {
    exit;
}

class Redis_For_Search_Smart_Cache {
    private $redis;
    private $options;
    private $cache_prefix = 'rfs_smart_';
    private $default_fields = array(
        'post_title',
        'post_excerpt',
        'post_content',
        'post_type',
        'post_status',
        'post_date'
    );
    private $disk_cache_dir;

    public function init() {
        $this->options = get_option('redis_for_search_options');
        $this->disk_cache_dir = WP_CONTENT_DIR . '/cache/redis-for-search/smart/';
        
        // Only proceed if smart cache is enabled
        if (!isset($this->options['enable_smart_cache']) || !$this->options['enable_smart_cache']) {
            return;
        }

        // Create base cache directory with proper permissions
        if (!file_exists(WP_CONTENT_DIR . '/cache')) {
            wp_mkdir_p(WP_CONTENT_DIR . '/cache');
            @chmod(WP_CONTENT_DIR . '/cache', 0755);
        }

        // Initialize storage based on cache type
        $cache_type = isset($this->options['cache_type']) ? $this->options['cache_type'] : 'disk';
        if ($cache_type === 'redis' && !$this->connect_redis()) {
            // Fallback to disk if Redis connection fails
            $this->options['cache_type'] = 'disk';
            update_option('redis_for_search_options', $this->options);
        }

        // Ensure disk cache directory exists
        if ($this->options['cache_type'] === 'disk') {
            // Create cache directories with proper permissions
            if (!file_exists($this->disk_cache_dir)) {
                wp_mkdir_p($this->disk_cache_dir);
                @chmod($this->disk_cache_dir, 0755);
            }
            
            if (!file_exists($this->disk_cache_dir . 'posts/')) {
                wp_mkdir_p($this->disk_cache_dir . 'posts/');
                @chmod($this->disk_cache_dir . 'posts/', 0755);
            }
            
            if (!file_exists($this->disk_cache_dir . 'words/')) {
                wp_mkdir_p($this->disk_cache_dir . 'words/');
                @chmod($this->disk_cache_dir . 'words/', 0755);
            }

            // Initialize data.json with empty array if it doesn't exist
            $data_file = $this->disk_cache_dir . 'posts/data.json';
            if (!file_exists($data_file)) {
                file_put_contents($data_file, json_encode(array()));
                @chmod($data_file, 0644);
            }
        }

        // Hook into post operations
        add_action('save_post', array($this, 'update_post_cache'), 10, 3);
        add_action('delete_post', array($this, 'delete_post_cache'));
        add_action('trash_post', array($this, 'delete_post_cache'));

        // Hook into search query
        add_filter('posts_pre_query', array($this, 'search_in_cache'), 10, 2);
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
            error_log('Redis Smart Cache connection error: ' . $e->getMessage());
            return false;
        }
    }

    private function store_post_data($post_id, $data) {
        if (!isset($this->options['cache_type'])) {
            $this->options['cache_type'] = 'disk';
        }

        if ($this->options['cache_type'] === 'redis') {
            return $this->redis->hSet(
                $this->cache_prefix . 'posts',
                $post_id,
                serialize($data)
            );
        } else {
            if (!file_exists($this->disk_cache_dir . 'posts/')) {
                wp_mkdir_p($this->disk_cache_dir . 'posts/');
                @chmod($this->disk_cache_dir . 'posts/', 0755);
            }
            $data_file = $this->disk_cache_dir . 'posts/data.json';
            
            // Initialize with empty array if file doesn't exist or is empty
            if (!file_exists($data_file) || filesize($data_file) === 0) {
                $safeWriter = new \Webimpress\SafeWriter\FileWriter();
                $safeWriter->writeFile($data_file, json_encode(array()));
                @chmod($data_file, 0644);
            }

            // Test file writing with detailed error logging
            if (WP_DEBUG && WP_DEBUG_LOG) {
                error_log('Redis For Search: Attempting to write test files');
            }
            // Test write to data.json
            $safeWriter = new \Webimpress\SafeWriter\FileWriter();
            $write_result = $safeWriter->writeFile($data_file, 'makan bang');
            if ($write_result === false) {
                if (WP_DEBUG && WP_DEBUG_LOG) {
                    error_log('Redis For Search: Failed to write to data.json: ' . $data_file);
                    error_log('Redis For Search: PHP error: ' . error_get_last()['message']);
                }
            } else {
                if (WP_DEBUG && WP_DEBUG_LOG) {
                    error_log('Redis For Search: Successfully wrote ' . $write_result . ' bytes to data.json');
                }
                @chmod($data_file, 0644);
            }

            // Test write to data file
            $data_file_ex = $this->disk_cache_dir . 'posts/data';
            $write_result_ex = $safeWriter->writeFile($data_file_ex, 'makan bang');
            if ($write_result_ex === false) {
                error_log('Redis For Search: Failed to write to data file: ' . $data_file_ex);
                error_log('Redis For Search: PHP error: ' . error_get_last()['message']);
            } else {
                error_log('Redis For Search: Successfully wrote ' . $write_result_ex . ' bytes to data file');
                @chmod($data_file_ex, 0644);
            }
           
            return true;
        }
    }



    private function add_word_index($word, $post_id) {
        if (!isset($this->options['cache_type'])) {
            $this->options['cache_type'] = 'disk';
        }

        if ($this->options['cache_type'] === 'redis') {
            return $this->redis->sAdd($this->cache_prefix . 'word:' . $word, $post_id);
        } else {
            if (!file_exists($this->disk_cache_dir . 'words/')) {
                wp_mkdir_p($this->disk_cache_dir . 'words/');
                @chmod($this->disk_cache_dir . 'words/', 0755);
            }
            $word_file = $this->disk_cache_dir . 'words/' . $word . '.txt';
            $post_ids = file_exists($word_file) ? 
                array_filter(explode('\n', file_get_contents($word_file))) : 
                array();
            
            if (!in_array($post_id, $post_ids)) {
                $post_ids[] = $post_id;
                $safeWriter = new \Webimpress\SafeWriter\FileWriter();
                $success = $safeWriter->writeFile($word_file, implode("\n", $post_ids)) !== false;
                if ($success) {
                    @chmod($word_file, 0644);
                }
                return $success;
            }
            return true;
        }
    }

    private function remove_word_index($word, $post_id) {
        if ($this->options['cache_type'] === 'redis') {
            return $this->redis->sRem($this->cache_prefix . 'word:' . $word, $post_id);
        } else {
            $word_file = $this->disk_cache_dir . 'words/' . $word . '.txt';
            if (file_exists($word_file)) {
                $post_ids = array_filter(explode("\n", file_get_contents($word_file)));
                $post_ids = array_diff($post_ids, array($post_id));
                if (empty($post_ids)) {
                    return @unlink($word_file);
                } else {
                    $safeWriter = new \Webimpress\SafeWriter\FileWriter();
                $success = $safeWriter->writeFile($word_file, implode("\n", $post_ids)) !== false;
                    if ($success) {
                        @chmod($word_file, 0644);
                    }
                    return $success;
                }
            }
            return true;
        }
    }

    private function get_post_data($post_id) {
        if ($this->options['cache_type'] === 'redis') {
            $data = $this->redis->hGet($this->cache_prefix . 'posts', $post_id);
            return $data ? unserialize($data) : null;
        } else {
            $data_file = $this->disk_cache_dir . 'posts/data.json';
            if (file_exists($data_file)) {
                $file_content = file_get_contents($data_file);
                if ($file_content !== false) {
                    $all_data = json_decode($file_content, true) ?: array();
                    return isset($all_data[$post_id]) ? $all_data[$post_id] : null;
                }
            }
            return null;
        }
    }



    private function get_word_post_ids($word) {
        if ($this->options['cache_type'] === 'redis') {
            return $this->redis->sMembers($this->cache_prefix . 'word:' . $word);
        } else {
            $word_file = $this->disk_cache_dir . 'words/' . $word . '.txt';
            if (file_exists($word_file)) {
                return array_filter(explode('\n', file_get_contents($word_file)));
            }
            return array();
        }
    }

    public function update_post_cache($post_id, $post = null, $update = true) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        $post = get_post($post_id);
        if (!$post || $post->post_status !== 'publish') {
            $this->delete_post_cache($post_id);
            error_log(sprintf('Redis For Search: Skipping post ID %d - not published or invalid', $post_id));
            return false;
        }

        $cache_data = array();
        foreach ($this->default_fields as $field) {
            if (isset($post->$field)) {
                $cache_data[$field] = $post->$field;
            }
        }

        // Add taxonomies
        $taxonomies = get_object_taxonomies($post->post_type);
        $cache_data['taxonomies'] = array();
        foreach ($taxonomies as $taxonomy) {
            $terms = wp_get_object_terms($post_id, $taxonomy, array('fields' => 'names'));
            if (!is_wp_error($terms)) {
                $cache_data['taxonomies'][$taxonomy] = $terms;
            }
        }

        // Add custom fields (meta)
        $cache_data['meta'] = array();
        $meta_keys = array('_thumbnail_id'); // Add more meta keys as needed
        foreach ($meta_keys as $key) {
            $meta_value = get_post_meta($post_id, $key, true);
            if ($meta_value) {
                $cache_data['meta'][$key] = $meta_value;
            }
        }

        error_log(sprintf('Redis For Search: Storing data for post ID %d', $post_id));
        $store_result = $this->store_post_data($post_id, $cache_data);
        if (!$store_result) {
            error_log(sprintf('Redis For Search: Failed to store data for post ID %d', $post_id));
            return false;
        }

        // Update search index
        $search_text = $post->post_title . ' ' . 
                      $post->post_excerpt . ' ' . 
                      strip_tags($post->post_content);
        $words = array_unique(array_filter(
            explode(' ', strtolower(preg_replace('/[^a-zA-Z0-9\s]/', '', $search_text)))
        ));

        foreach ($words as $word) {
            if (strlen($word) > 2) { // Only index words longer than 2 characters
                $this->add_word_index($word, $post_id);
            }
        }

        error_log(sprintf('Redis For Search: Successfully updated cache for post ID %d', $post_id));
        return true;
    }

    public function delete_post_cache($post_id) {
        // Remove from posts storage
        if ($this->options['cache_type'] === 'redis') {
            $this->redis->hDel($this->cache_prefix . 'posts', $post_id);
            $keys = $this->redis->keys($this->cache_prefix . 'word:*');
            foreach ($keys as $key) {
                $this->redis->sRem($key, $post_id);
            }
        } else {
            $post_file = $this->disk_cache_dir . 'posts/' . $post_id . '.json';
            if (file_exists($post_file)) {
                @unlink($post_file);
            }
            
            // Remove from word indices
            $word_files = glob($this->disk_cache_dir . 'words/*.txt');
            foreach ($word_files as $word_file) {
                $word = basename($word_file, '.txt');
                $this->remove_word_index($word, $post_id);
            }
        }
    }

    public function search_in_cache($posts, $query) {
        if (!$query->is_search() || !$query->is_main_query()) {
            return $posts;
        }

        $search_terms = array_filter(
            explode(' ', strtolower(preg_replace('/[^a-zA-Z0-9\s]/', '', $query->query_vars['s'])))
        );

        if (empty($search_terms)) {
            return $posts;
        }

        $post_ids = array();
        $first_term = true;

        foreach ($search_terms as $term) {
            if (strlen($term) <= 2) continue;

            $term_posts = $this->get_word_post_ids($term);
            
            if ($first_term) {
                $post_ids = $term_posts;
                $first_term = false;
            } else {
                $post_ids = array_intersect($post_ids, $term_posts);
            }

            if (empty($post_ids)) {
                break;
            }
        }

        if (empty($post_ids)) {
            return $posts;
        }

        // Retrieve cached post data
        $cached_posts = array();
        foreach ($post_ids as $post_id) {
            $cached_data = $this->get_post_data($post_id);
            if ($cached_data) {
                $cached_posts[] = $cached_data;
            }
        }

        // Sort results
        usort($cached_posts, function($a, $b) {
            return strtotime($b['post_date']) - strtotime($a['post_date']);
        });

        return $cached_posts;
    }

    public function rebuild_cache() {
        $this->flush_cache();

        // Get all published posts and pages
        $args = array(
            'post_type' => array('post', 'page'),
            'post_status' => 'publish',
            'posts_per_page' => -1
        );

        $query = new WP_Query($args);
        $processed_count = 0;
        $batch_size = 100; // Process posts in batches of 100
        $accumulated_data = array();
        
        if ($query->have_posts()) {

            foreach ($query->posts as $post) {
                if ($post && isset($post->ID)) {
                    if ($this->options['cache_type'] === 'disk') {
                        // For disk cache, accumulate data first
                        $cache_data = $this->prepare_post_cache_data($post->ID, $post);
                        if ($cache_data) {
                            $accumulated_data[$post->ID] = $cache_data;
                            $processed_count++;
                            //error_log(sprintf('Redis For Search: Added post ID %d to batch. Current batch size: %d', $post->ID, count($accumulated_data)));
                        }
                        
                        // Write to disk when batch size is reached
                        if (count($accumulated_data) >= $batch_size) {
                            //error_log(sprintf('Redis For Search: Processing batch of %d posts. Memory usage: %s', count($accumulated_data), size_format(memory_get_usage())));
                            //error_log('Redis For Search: Batch Data Content: ' . print_r($accumulated_data, true));
                            $write_result = $this->write_batch_to_disk($accumulated_data);
                            //error_log(sprintf('Redis For Search: Batch write result: %s', $write_result ? 'Success' : 'Failed'));
                            $accumulated_data = array(); // Clear the accumulated data
                            //error_log('Redis For Search: Cleared accumulated data after batch write');
                            sleep(2); // Pause for 2 seconds between batches
                        }
                    } else {
                        // For Redis, process normally
                        $result = $this->update_post_cache($post->ID, $post, false);
                        if ($result) {
                            $processed_count++;
                        }
                    }
                }
            }
            
            // Write any remaining posts for disk cache
            if ($this->options['cache_type'] === 'disk' && !empty($accumulated_data)) {
                $this->write_batch_to_disk($accumulated_data);
            }
        }
        return true;
    }

    private function prepare_post_cache_data($post_id, $post) {
        if (!$post || $post->post_status !== 'publish') {
            return false;
        }

        $cache_data = array();
        foreach ($this->default_fields as $field) {
            if (isset($post->$field)) {
                $cache_data[$field] = $post->$field;
            }
        }

        // Add taxonomies
        $taxonomies = get_object_taxonomies($post->post_type);
        $cache_data['taxonomies'] = array();
        foreach ($taxonomies as $taxonomy) {
            $terms = wp_get_object_terms($post_id, $taxonomy, array('fields' => 'names'));
            if (!is_wp_error($terms)) {
                $cache_data['taxonomies'][$taxonomy] = $terms;
            }
        }

        // Add custom fields (meta)
        $cache_data['meta'] = array();
        $meta_keys = array('_thumbnail_id'); // Add more meta keys as needed
        foreach ($meta_keys as $key) {
            $meta_value = get_post_meta($post_id, $key, true);
            if ($meta_value) {
                $cache_data['meta'][$key] = $meta_value;
            }
        }

        return $cache_data;
    }

    private function write_batch_to_disk($batch_data) {
       
        $data_file = $this->disk_cache_dir . 'posts/data.json';
        
        // Read existing data
        $existing_data = array();
        if (file_exists($data_file) && filesize($data_file) > 0) {
            $file_content = file_get_contents($data_file);
            if ($file_content !== false) {
                $decoded_data = json_decode($file_content, true);
                if (is_array($decoded_data)) {
                    $existing_data = $decoded_data;
                }
            }
        }
        
        // Merge new data with existing data
        $all_data = array_merge($existing_data, $batch_data);
        
        // Write back to file with proper JSON encoding and Unicode support
        $json_data = json_encode($all_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($json_data === false) {
            error_log('Redis For Search: JSON encoding failed for batch write');
            return false;
        }
        
        // Use file locking for atomic writes
        $fp = fopen($data_file, 'c+');

        if (!$fp) {
            error_log("Failed to open file: $data_file");
        }

        try {

            if (flock($fp, LOCK_EX)) {
                ftruncate($fp, 0);
                fwrite($fp, $json_data);
                fflush($fp);
                flock($fp, LOCK_UN);

            } else {
                error_log("Failed to acquire lock for file: $data_file");
            }


        } finally {
            fclose($fp);
        }

        
        
        return true;
    }

    public function flush_cache() {
        if (!isset($this->options['cache_type'])) {
            $this->options['cache_type'] = 'disk';
        }

        if ($this->options['cache_type'] === 'redis' && $this->redis) {
            $keys = $this->redis->keys($this->cache_prefix . '*');
            foreach ($keys as $key) {
                $this->redis->del($key);
            }
        } else {
            wp_mkdir_p($this->disk_cache_dir . 'posts/');
            wp_mkdir_p($this->disk_cache_dir . 'words/');
            array_map('unlink', glob($this->disk_cache_dir . 'posts/*.json'));
            array_map('unlink', glob($this->disk_cache_dir . 'words/*.txt'));
        }

        return true;
    }
}
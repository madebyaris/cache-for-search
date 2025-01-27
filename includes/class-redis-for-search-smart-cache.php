<?php

if (!defined('ABSPATH')) {
    exit;
}

use Webimpress\SafeWriter\FileWriter;

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
    private $file_locks = array();
    private $logger;

    private function connect_redis() {
        try {
            if (!class_exists('Redis')) {
                throw new Exception('Redis extension is not installed');
            }

            $this->redis = new Redis();
            $host = isset($this->options['redis_host']) ? $this->options['redis_host'] : 'localhost';
            $port = isset($this->options['redis_port']) ? (int)$this->options['redis_port'] : 6379;
            
            if (!$this->redis->connect($host, $port)) {
                throw new Exception('Failed to connect to Redis server');
            }

            // Optional authentication if configured
            if (!empty($this->options['redis_password'])) {
                if (!$this->redis->auth($this->options['redis_password'])) {
                    throw new Exception('Redis authentication failed');
                }
            }

            //$this->logger->info('Successfully connected to Redis server');
            return true;

        } catch (Exception $e) {
            $this->logger->error('Redis connection failed: ' . $e->getMessage());
            return false;
        }
    }

    private function setup_hooks() {
        try {
            // Hook into post updates to maintain cache consistency
            add_action('save_post', array($this, 'update_post_cache'), 10, 3);
            add_action('delete_post', array($this, 'remove_post_from_cache'), 10);
            add_action('trash_post', array($this, 'remove_post_from_cache'), 10);
            
            // Hook into search query to integrate smart cache
            add_filter('posts_pre_query', array($this, 'filter_search_results'), 10, 2);
            
            //$this->logger->info('Smart cache hooks setup successfully');
        } catch (Exception $e) {
            $this->log_error('Failed to setup hooks: ' . $e->getMessage());
        }
    }

    public function init() {
        $this->setup_logging();
        $this->options = get_option('redis_for_search_options');
        $this->disk_cache_dir = WP_CONTENT_DIR . '/cache/redis-for-search/smart/';
        
        try {
            // Only proceed if smart cache is enabled
            if (!isset($this->options['enable_smart_cache']) || !$this->options['enable_smart_cache']) {
                return;
            }

            $this->create_cache_directories();
            $this->initialize_data_file();
            $this->setup_hooks();

            // Initialize storage based on cache type
            $cache_type = isset($this->options['cache_type']) ? $this->options['cache_type'] : 'disk';
            if ($cache_type === 'redis' && !$this->connect_redis()) {
                throw new Exception('Redis connection failed, falling back to disk cache');
            }

        } catch (Exception $e) {
            $this->log_error('Initialization failed: ' . $e->getMessage());
            return false;
        }
    }

    private function setup_logging() {
        $this->logger = new class {
            private function write_log($level, $message, $context = array()) {
                if (WP_DEBUG === true) {
                    $log_entry = sprintf('[%s] [%s] %s %s',
                        current_time('mysql'),
                        strtoupper($level),
                        $message,
                        !empty($context) ? json_encode($context) : ''
                    );
                    error_log($log_entry);
                }
            }

            public function error($message, $context = array()) {
                $this->write_log('error', $message, $context);
            }

            public function info($message, $context = array()) {
                $this->write_log('info', $message, $context);
            }

            public function debug($message, $context = array()) {
                $this->write_log('debug', $message, $context);
            }
        };
    }

    private function create_cache_directories() {
        $directories = array(
            WP_CONTENT_DIR . '/cache',
            $this->disk_cache_dir,
            $this->disk_cache_dir . 'posts',
            $this->disk_cache_dir . 'words'
        );

        foreach ($directories as $dir) {
            if (!$this->create_directory($dir)) {
                throw new Exception("Failed to create directory: $dir");
            }
        }
    }

    private function create_directory($dir) {
        try {
            if (!file_exists($dir)) {
                if (!wp_mkdir_p($dir)) {
                    throw new Exception("Failed to create directory: $dir");
                }
                if (!chmod($dir, 0755)) {
                    throw new Exception("Failed to set permissions on directory: $dir");
                }
            }
            return true;
        } catch (Exception $e) {
            $this->log_error('Directory creation failed: ' . $e->getMessage());
            return false;
        }
    }

    private function initialize_data_file() {
        $data_file = $this->disk_cache_dir . 'posts/data.json';
        if (!file_exists($data_file)) {
            try {
                $safeWriter = new FileWriter();
                $safeWriter->writeFile($data_file, json_encode(array()));
                chmod($data_file, 0644);
               // $this->logger->info('Created empty data.json file at: ' . $data_file);
            } catch (Exception $e) {
                throw new Exception('Failed to initialize data file: ' . $e->getMessage());
            }
        }
    }

    private function acquire_lock($file_path, $timeout = 10) {
        $lock_file = $file_path . '.lock';
        $start_time = time();

        while (file_exists($lock_file)) {
            if (time() - $start_time >= $timeout) {
                throw new Exception('Failed to acquire lock: timeout');
            }
            usleep(100000); // Wait 100ms before trying again
        }

        if (!touch($lock_file)) {
            throw new Exception('Failed to create lock file');
        }

        $this->file_locks[] = $lock_file;
        return true;
    }

    private function release_lock($file_path) {
        $lock_file = $file_path . '.lock';
        if (file_exists($lock_file)) {
            unlink($lock_file);
        }
        $this->file_locks = array_diff($this->file_locks, array($lock_file));
    }

    private function log_error($message, $context = array()) {
        $error = array(
            'message' => $message,
            'context' => $context,
            'timestamp' => current_time('mysql')
        );

        $this->logger->error($message, $context);
    }

    public function process_batch($posts, $batch_size = null) {
        if ($batch_size === null) {
            $batch_size = isset($this->options['max_batch_size']) ? $this->options['max_batch_size'] : 100;
        }

        $processed = 0;
        $total = count($posts);
        $batches = array_chunk($posts, $batch_size);

        foreach ($batches as $batch) {
            try {
                $this->process_posts_batch($batch);
                $processed += count($batch);
              //  $this->logger->info("Processed $processed/$total posts");

                if (function_exists('wp_cache_flush')) {
                    wp_cache_flush(); // Clear WordPress object cache after each batch
                }

            } catch (Exception $e) {
                $this->log_error('Batch processing failed: ' . $e->getMessage());
                continue;
            }
        }

        return $processed;
    }

    private function process_posts_batch($posts) {
        foreach ($posts as $post) {
            try {
                $this->update_post_cache($post->ID, $post, true);
            } catch (Exception $e) {
                $this->log_error('Failed to process post ' . $post->ID . ': ' . $e->getMessage());
            }
        }
    }

    public function filter_search_results($posts, $query) {
        if (!$query->is_search() || !isset($this->options['enable_smart_cache']) || !$this->options['enable_smart_cache']) {
            return $posts;
        }
    
        try {
            $search_terms = $query->get('s');
            if (empty($search_terms)) {
                return $posts;
            }
    
            $cache_type = isset($this->options['cache_type']) ? $this->options['cache_type'] : 'disk';
            $results = array();
    
            if ($cache_type === 'redis' && $this->redis) {
                $results = $this->search_redis($search_terms);
            } else {
                $results = $this->search_disk($search_terms);
            }
    
            if (!empty($results)) {
             //   $this->logger->info('Smart cache search results found', array('count' => count($results)));
                return $results;
            }
    
        } catch (Exception $e) {
            $this->log_error('Search filtering failed: ' . $e->getMessage());
        }
    
        return $posts;
    }
    
    private function search_redis($search_terms) {
        $results = array();
        $terms = explode(' ', strtolower(trim($search_terms)));
    
        try {
            foreach ($terms as $term) {
                $post_ids = $this->redis->sMembers($this->cache_prefix . 'term:' . $term);
                if (!empty($post_ids)) {
                    foreach ($post_ids as $post_id) {
                        $post_data = $this->redis->get($this->cache_prefix . 'post:' . $post_id);
                        if ($post_data) {
                            $post = get_post($post_id);
                            if ($post && $post->post_status === 'publish') {
                                $results[] = $post;
                            }
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $this->log_error('Redis search failed: ' . $e->getMessage());
        }
    
        return array_unique($results, SORT_REGULAR);
    }
    
    private function search_disk($search_terms) {
        $results = array();
        $terms = explode(' ', strtolower(trim($search_terms)));
        $data_file = $this->disk_cache_dir . 'posts/data.json';
    
        try {
            if (!file_exists($data_file)) {
                return array();
            }
    
            $this->acquire_lock($data_file);
            $json_data = file_get_contents($data_file);
            $this->release_lock($data_file);
    
            $cache_data = json_decode($json_data, true);
            if (!$cache_data) {
                return array();
            }
    
            foreach ($terms as $term) {
                foreach ($cache_data as $post_id => $post_data) {
                    if (isset($post_data['content']) && 
                        (strpos(strtolower($post_data['content']), $term) !== false || 
                         strpos(strtolower($post_data['title']), $term) !== false)) {
                        $post = get_post($post_id);
                        if ($post && $post->post_status === 'publish') {
                            $results[] = $post;
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $this->log_error('Disk search failed: ' . $e->getMessage());
        }
    
        return array_unique($results, SORT_REGULAR);
    }

    public function rebuild_cache($batch_size = 200) {
        try {
            // Get all published posts
            $args = array(
                'post_type' => 'any',
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'fields' => 'ids'
            );

            $query = new WP_Query($args);
            $post_ids = $query->posts;

            if (empty($post_ids)) {
              //  $this->logger->info('No posts found to cache');
                return true;
            }

            // Get posts in batches
            $total_posts = count($post_ids);
            $processed = 0;
            $batches = array_chunk($post_ids, $batch_size);

            foreach ($batches as $batch) {
                $posts = get_posts(array(
                    'post__in' => $batch,
                    'post_type' => 'any',
                    'post_status' => 'publish',
                    'posts_per_page' => count($batch)
                ));

                $processed += $this->process_batch($posts);
             //   $this->logger->info("Processed $processed/$total_posts posts");
            }

           // $this->logger->info('Cache rebuild completed successfully');
            return true;

        } catch (Exception $e) {
            $this->logger->error('Cache rebuild failed: ' . $e->getMessage());
            return false;
        }
    }

    public function update_post_cache($post_id, $post = null, $update = true) {
        try {
            if (!$post) {
                $post = get_post($post_id);
            }

            if (!$post || $post->post_status !== 'publish') {
                $this->remove_post_from_cache($post_id);
                return;
            }

            $post_data = array(
                'id' => $post->ID,
                'title' => $post->post_title,
                'content' => strip_tags($post->post_content),
                'excerpt' => $post->post_excerpt,
                'type' => $post->post_type,
                'status' => $post->post_status,
                'date' => $post->post_date
            );

            $cache_type = isset($this->options['cache_type']) ? $this->options['cache_type'] : 'disk';

            if ($cache_type === 'redis' && $this->redis) {
                $this->update_redis_cache($post_id, $post_data);
            } else {
                $this->update_disk_cache($post_id, $post_data);
            }

           // $this->logger->info('Updated cache for post ' . $post_id);
            return true;

        } catch (Exception $e) {
            $this->log_error('Failed to update post cache: ' . $e->getMessage());
            return false;
        }
    }

    private function update_redis_cache($post_id, $post_data) {
        // Remove old terms first
        $old_terms = $this->redis->sMembers($this->cache_prefix . 'post_terms:' . $post_id);
        if ($old_terms) {
            foreach ($old_terms as $term) {
                $this->redis->sRem($this->cache_prefix . 'term:' . $term, $post_id);
            }
        }

        // Store post data
        $this->redis->set($this->cache_prefix . 'post:' . $post_id, json_encode($post_data));

        // Index terms
        $terms = $this->extract_terms($post_data['title'] . ' ' . $post_data['content']);
        $this->redis->del($this->cache_prefix . 'post_terms:' . $post_id);
        foreach ($terms as $term) {
            $this->redis->sAdd($this->cache_prefix . 'term:' . $term, $post_id);
            $this->redis->sAdd($this->cache_prefix . 'post_terms:' . $post_id, $term);
        }
    }

    private function update_disk_cache($post_id, $post_data) {
        $data_file = $this->disk_cache_dir . 'posts/data.json';
        
        try {
            $this->acquire_lock($data_file);
            $json_data = file_exists($data_file) ? file_get_contents($data_file) : '{}';
            $cache_data = json_decode($json_data, true) ?: array();
            
            $cache_data[$post_id] = $post_data;
            
            $safeWriter = new FileWriter();
            $safeWriter->writeFile($data_file, json_encode($cache_data));
            chmod($data_file, 0644);
            
        } finally {
            $this->release_lock($data_file);
        }
    }

    private function extract_terms($text) {
        $text = strtolower(strip_tags($text));
        $text = preg_replace('/[^a-z0-9\s]/', '', $text);
        $words = array_unique(array_filter(explode(' ', $text)));
        return array_values(array_filter($words, function($word) {
            return strlen($word) >= 3;
        }));
    }

    public function __destruct() {
        // Clean up any remaining locks
        foreach ($this->file_locks as $lock_file) {
            if (file_exists($lock_file)) {
                unlink($lock_file);
            }
        }
    }
}
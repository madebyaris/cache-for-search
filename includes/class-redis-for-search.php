<?php

if (!defined('ABSPATH')) {
    exit;
}

class Redis_For_Search {
    private static $instance = null;
    private $redis = null;
    private $options;
    private $cache_prefix = 'rfs_';
    private $smart_cache;
    private $logger;

    private function __construct() {
        $this->setup_logging();
        $this->init();
    }

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
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

    private function init() {
        try {
            $this->options = get_option('redis_for_search_options', array());
            $this->setup_hooks();
            $this->initialize_storage();
            $this->initialize_smart_cache();
        } catch (Exception $e) {
            $this->logger->error('Initialization failed: ' . $e->getMessage());
        }
    }

    private function setup_hooks() {
        add_filter('posts_pre_query', array($this, 'filter_search_results'), 10, 2);
        add_action('save_post', array($this, 'invalidate_cache'), 10, 3);
        add_action('delete_post', array($this, 'invalidate_cache'), 10);
        add_action('trash_post', array($this, 'invalidate_cache'), 10);
    }

    private function initialize_storage() {
        if (isset($this->options['cache_type']) && $this->options['cache_type'] === 'redis') {
            $this->connect_redis();
        }
    }

    private function initialize_smart_cache() {
        if (isset($this->options['enable_smart_cache']) && $this->options['enable_smart_cache']) {
            require_once RFS_PLUGIN_DIR . 'includes/class-redis-for-search-smart-cache.php';
            $this->smart_cache = new Redis_For_Search_Smart_Cache();
            $this->smart_cache->init();
        }
    }

    private function connect_redis() {
        try {
            if (!class_exists('Redis')) {
                throw new Exception('Redis PHP extension not installed');
            }

            $this->redis = new Redis();
            $host = isset($this->options['redis_host']) ? $this->options['redis_host'] : 'localhost';
            $port = isset($this->options['redis_port']) ? (int)$this->options['redis_port'] : 6379;
            $timeout = 2;

            if (!$this->redis->connect($host, $port, $timeout)) {
                throw new Exception('Could not connect to Redis server');
            }

            if (!empty($this->options['redis_password'])) {
                if (!$this->redis->auth($this->options['redis_password'])) {
                    throw new Exception('Redis authentication failed');
                }
            }

            $this->logger->info('Connected to Redis server successfully');
            return true;

        } catch (Exception $e) {
            $this->redis = null;
            $this->logger->error('Redis connection failed: ' . $e->getMessage());
            return false;
        }
    }

    public function filter_search_results($posts, $query) {
        if (!$query->is_search()) {
            return $posts;
        }


        try {
            $search_terms = $query->get('s');
            if (empty($search_terms)) {
                return $posts;
            }

            $cache_key = $this->generate_cache_key($search_terms, $query);
            $cached_results = $this->get_cache($cache_key);

            if ($cached_results !== false) {
                $this->logger->info('Cache hit for search: ' . $search_terms);
                return $cached_results;
            }

            // Create the .cache file
            $this->set_cache($cache_key, $posts);
            

            $this->logger->info('Cache miss for search: ' . $search_terms);
            return $posts;

        } catch (Exception $e) {
            $this->logger->error('Error filtering search results: ' . $e->getMessage());
            return $posts;
        }
    }

    private function generate_cache_key($search_terms, $query) {
        $key_parts = array(
            'search',
            md5($search_terms),
            $query->get('posts_per_page'),
            $query->get('paged'),
            $query->get('post_type'),
            $query->get('orderby'),
            $query->get('order'),
            md5(serialize($query->get('tax_query'))),
            md5(serialize($query->get('meta_query')))
        );

        $key = $this->cache_prefix . implode('_', array_filter($key_parts));
        $this->logger->debug('Generated cache key: ' . $key);
        return $key;
    }

    public function get_cache($key) {
        try {
            if ($this->redis) {
                return $this->get_redis_cache($key);
            }
            return $this->get_disk_cache($key);
        } catch (Exception $e) {
            $this->logger->error('Cache retrieval failed: ' . $e->getMessage());
            return false;
        }
    }

    private function get_redis_cache($key) {
        if (!$this->redis) {
            return false;
        }

        $data = $this->redis->get($key);
        if ($data === false) {
            return false;
        }

        return unserialize($data);
    }

    private function get_disk_cache($key) {
        $cache_file = WP_CONTENT_DIR . '/cache/redis-for-search/disk/' . md5($key) . '.cache';
        if (!file_exists($cache_file)) {
            return false;
        }

        $data = file_get_contents($cache_file);
        if ($data === false) {
            return false;
        }

        return unserialize($data);
    }

    public function set_cache($key, $data, $expiry = null) {
        if ($expiry === null) {
            $expiry = isset($this->options['cache_ttl']) ? (int)$this->options['cache_ttl'] : 3600;
        }

        try {
            if ($this->redis) {
                return $this->set_redis_cache($key, $data, $expiry);
            }
            return $this->set_disk_cache($key, $data);
        } catch (Exception $e) {
            $this->logger->error('Cache storage failed: ' . $e->getMessage());
            return false;
        }
    }

    private function set_redis_cache($key, $data, $expiry) {
        if (!$this->redis) {
            return false;
        }

        return $this->redis->setex($key, $expiry, serialize($data));
    }

    private function set_disk_cache($key, $data) {
        try {
            $cache_dir = WP_CONTENT_DIR . '/cache/redis-for-search/disk/';
            
            // Ensure cache directory exists and is writable
            if (!is_dir($cache_dir)) {
                if (!wp_mkdir_p($cache_dir)) {
                    $this->logger->error('Failed to create cache directory: ' . $cache_dir);
                    throw new Exception('Failed to create cache directory');
                }
                if (!chmod($cache_dir, 0755)) {
                    $this->logger->error('Failed to set cache directory permissions');
                    throw new Exception('Failed to set cache directory permissions');
                }
            } elseif (!is_writable($cache_dir)) {
                $this->logger->error('Cache directory is not writable: ' . $cache_dir);
                throw new Exception('Cache directory is not writable');
            }

            $cache_file = $cache_dir . md5($key) . '.cache';
            $serialized_data = serialize($data);
            
            // Use atomic file writing
            $safeWriter = new \Webimpress\SafeWriter\FileWriter();
            try {
                $safeWriter->writeFile($cache_file, $serialized_data);
                if (!chmod($cache_file, 0644)) {
                    $this->logger->error('Failed to set cache file permissions');
                    throw new Exception('Failed to set cache file permissions');
                }
            } catch (\Webimpress\SafeWriter\Exception\ExceptionInterface $e) {
                $this->logger->error('Failed to write cache file: ' . $e->getMessage());
                throw new Exception('Failed to write cache file');
            }
            
            $this->logger->debug('Cache file created successfully: ' . $cache_file);
            return true;

        } catch (Exception $e) {
            $this->logger->error('Failed to set disk cache: ' . $e->getMessage());
            return false;
        }
    }

    public function invalidate_cache($post_id) {
        try {
            if ($this->redis) {
                $this->redis->del($this->cache_prefix . '*');
            } else {
                $cache_dir = WP_CONTENT_DIR . '/cache/redis-for-search/';
                if (file_exists($cache_dir)) {
                    array_map('unlink', glob($cache_dir . '*.cache'));
                }
            }
            $this->logger->info('Cache invalidated for post: ' . $post_id);
            return true;
        } catch (Exception $e) {
            $this->logger->error('Cache invalidation failed: ' . $e->getMessage());
            return false;
        }
    }

    public function get_redis_connection() {
        return $this->redis;
    }

    public function is_redis_connected() {
        return $this->redis !== null && $this->redis->ping() === true;
    }
}
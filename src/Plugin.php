<?php
namespace XAutoPoster;

class Plugin {
    private static $instance = null;
    private $settings;
    private $twitter;
    private $queue;

    public static function getInstance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->initHooks();
    }

    private function initHooks() {
        add_action('init', [$this, 'init']);
        add_action('admin_init', [$this, 'checkApiCredentials']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminScripts']);
        add_action('wp_ajax_xautoposter_reset_api_verification', [$this, 'resetApiVerification']);
        add_action('wp_ajax_xautoposter_share_posts', [$this, 'handleManualShare']);
        add_action('publish_post', [$this, 'handlePostPublish'], 10, 2);
        add_action('xautoposter_cron_hook', [$this, 'processQueue']);
        register_activation_hook(XAUTOPOSTER_FILE, [$this, 'activate']);
        register_deactivation_hook(XAUTOPOSTER_FILE, [$this, 'deactivate']);
    }

    public function init() {
        if (!class_exists('Abraham\\TwitterOAuth\\TwitterOAuth')) {
            add_action('admin_notices', function() {
                echo '<div class="error"><p>' . 
                     __('XAutoPoster requires TwitterOAuth library. Please run composer install.', 'xautoposter') . 
                     '</p></div>';
            });
            return;
        }

        try {
            $this->loadTextdomain();
            $this->initComponents();
            $this->registerHooks();
        } catch (\Exception $e) {
            add_action('admin_notices', function() use ($e) {
                echo '<div class="error"><p>' . 
                     esc_html__('XAutoPoster Error: ', 'xautoposter') . 
                     esc_html($e->getMessage()) . '</p></div>';
            });
        }
    }

    private function loadTextdomain() {
        load_plugin_textdomain(
            'xautoposter',
            false,
            dirname(XAUTOPOSTER_BASENAME) . '/languages'
        );
    }

    private function initComponents() {
        $this->settings = new Admin\Settings();
        
        $options = get_option('xautoposter_options', []);
        
        if (!empty($options['api_key']) && !empty($options['api_secret']) && 
            !empty($options['access_token']) && !empty($options['access_token_secret'])) {
            try {
                $this->twitter = new Services\TwitterService(
                    $options['api_key'],
                    $options['api_secret'],
                    $options['access_token'],
                    $options['access_token_secret']
                );
            } catch (\Exception $e) {
                error_log('XAutoPoster Twitter Service Init Error: ' . $e->getMessage());
            }
        }
        
        $this->queue = new Models\Queue();
    }

    private function registerHooks() {
        add_action('admin_menu', [$this, 'addAdminMenu']);
        add_action('admin_init', [$this->settings, 'registerSettings']);
        add_filter('cron_schedules', [$this, 'addCronIntervals']);
    }

    public function addAdminMenu() {
        add_menu_page(
            __('XAutoPoster', 'xautoposter'),
            __('XAutoPoster', 'xautoposter'),
            'manage_options',
            'xautoposter',
            [$this, 'renderAdminPage'],
            'dashicons-twitter',
            30
        );
    }

    public function renderAdminPage() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'xautoposter'));
        }
        
        require_once XAUTOPOSTER_PATH . 'templates/admin-page.php';
    }

    public function checkApiCredentials() {
        $options = get_option('xautoposter_options', []);
        
        if (!empty($options['api_key']) && !empty($options['api_secret']) && 
            !empty($options['access_token']) && !empty($options['access_token_secret'])) {
            try {
                $twitter = new Services\TwitterService(
                    $options['api_key'],
                    $options['api_secret'],
                    $options['access_token'],
                    $options['access_token_secret']
                );
                
                if ($twitter->verifyCredentials()) {
                    update_option('xautoposter_api_verified', true);
                    delete_option('xautoposter_api_error');
                } else {
                    throw new \Exception(__('API verification failed', 'xautoposter'));
                }
            } catch (\Exception $e) {
                update_option('xautoposter_api_verified', false);
                update_option('xautoposter_api_error', $e->getMessage());
            }
        }
    }

    public function resetApiVerification() {
        check_ajax_referer('xautoposter_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'xautoposter')]);
            return;
        }
        
        delete_option('xautoposter_api_verified');
        delete_option('xautoposter_api_error');
        
        wp_send_json_success([
            'message' => __('API verification reset. You can now modify the settings.', 'xautoposter')
        ]);
    }

    public function handleManualShare() {
        check_ajax_referer('xautoposter_share_posts', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'xautoposter')]);
            return;
        }
        
        $postIds = isset($_POST['posts']) ? array_map('intval', $_POST['posts']) : [];
        
        if (empty($postIds)) {
            wp_send_json_error(['message' => __('No posts selected.', 'xautoposter')]);
            return;
        }
        
        $results = [];
        $success = 0;
        $failed = 0;
        
        foreach ($postIds as $postId) {
            try {
                if ($this->sharePost($postId)) {
                    $success++;
                    $results[] = [
                        'id' => $postId,
                        'status' => 'success',
                        'message' => sprintf(__('Post #%d shared successfully', 'xautoposter'), $postId)
                    ];
                } else {
                    $failed++;
                    $results[] = [
                        'id' => $postId,
                        'status' => 'error',
                        'message' => sprintf(__('Failed to share post #%d', 'xautoposter'), $postId)
                    ];
                }
            } catch (\Exception $e) {
                $failed++;
                $results[] = [
                    'id' => $postId,
                    'status' => 'error',
                    'message' => $e->getMessage()
                ];
            }
        }
        
        if ($success > 0) {
            wp_send_json_success([
                'message' => sprintf(
                    __('%d posts shared successfully, %d failed', 'xautoposter'),
                    $success,
                    $failed
                ),
                'results' => $results
            ]);
        } else {
            wp_send_json_error([
                'message' => __('Failed to share posts', 'xautoposter'),
                'results' => $results
            ]);
        }
    }

    public function handlePostPublish($postId, $post) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (wp_is_post_revision($postId)) return;
        if (get_post_meta($postId, '_xautoposter_shared', true)) return;

        $options = get_option('xautoposter_options', []);
        $selectedCategories = isset($options['categories']) ? (array)$options['categories'] : [];
        
        // Kategoriler seçilmişse kontrol et
        if (!empty($selectedCategories)) {
            $postCategories = wp_get_post_categories($postId);
            $hasSelectedCategory = array_intersect($selectedCategories, $postCategories);
            
            if (empty($hasSelectedCategory)) {
                return; // Post seçili kategorilerde değilse paylaşma
            }
        }

        $this->queue->addToQueue($postId);
        
        if (!empty($options['auto_share']) && $options['auto_share'] === '1') {
            try {
                $this->sharePost($postId);
            } catch (\Exception $e) {
                error_log('XAutoPoster Auto Share Error: ' . $e->getMessage());
            }
        }
    }

    private function sharePost($postId) {
        if (!$this->twitter) {
            throw new \Exception(__('Twitter API credentials not configured.', 'xautoposter'));
        }
        
        $post = get_post($postId);
        if (!$post) {
            throw new \Exception(__('Post not found.', 'xautoposter'));
        }
        
        try {
            $result = $this->twitter->sharePost($post);
            
            if ($result) {
                update_post_meta($postId, '_xautoposter_shared', '1');
                update_post_meta($postId, '_xautoposter_share_time', current_time('mysql'));
                update_post_meta($postId, '_xautoposter_tweet_id', $result->data->id);
                $this->queue->markAsShared($postId);
                
                // Tweet metriklerini kaydet
                $metrics = $this->twitter->getTweetMetrics($result->data->id);
                if ($metrics) {
                    update_post_meta($postId, '_xautoposter_tweet_metrics', $metrics);
                }
                
                return true;
            }
            
            return false;
        } catch (\Exception $e) {
            error_log('XAutoPoster Share Error: ' . $e->getMessage());
            throw $e;
        }
    }

    public function processQueue() {
        if (!$this->twitter) {
            return;
        }
        
        $pendingPosts = $this->queue->getPendingPosts();
        
        foreach ($pendingPosts as $post) {
            try {
                $this->sharePost($post->post_id);
            } catch (\Exception $e) {
                error_log('XAutoPoster Queue Error: ' . $e->getMessage());
                $this->queue->incrementAttempts($post->post_id);
            }
        }
    }

    public function activate() {
        if (!class_exists('XAutoPoster\\Models\\Queue')) {
            require_once XAUTOPOSTER_PATH . 'src/Models/Queue.php';
        }
        
        $queue = new Models\Queue();
        $queue->createTable();
        
        $options = get_option('xautoposter_options', []);
        $interval = isset($options['interval']) ? $options['interval'] : '30min';
        
        if (!wp_next_scheduled('xautoposter_cron_hook')) {
            wp_schedule_event(time(), $interval, 'xautoposter_cron_hook');
        }
    }

    public function deactivate() {
        wp_clear_scheduled_hook('xautoposter_cron_hook');
    }

    public function addCronIntervals($schedules) {
        $schedules['5min'] = [
            'interval' => 300,
            'display' => __('Every 5 minutes', 'xautoposter')
        ];
        
        $schedules['15min'] = [
            'interval' => 900,
            'display' => __('Every 15 minutes', 'xautoposter')
        ];
        
        $schedules['30min'] = [
            'interval' => 1800,
            'display' => __('Every 30 minutes', 'xautoposter')
        ];
        
        $schedules['60min'] = [
            'interval' => 3600,
            'display' => __('Every hour', 'xautoposter')
        ];
        
        return $schedules;
    }

    public function enqueueAdminScripts($hook) {
        if (strpos($hook, 'xautoposter') === false) {
            return;
        }
        
        wp_enqueue_style(
            'xautoposter-admin',
            XAUTOPOSTER_URL . 'assets/css/admin.css',
            [],
            XAUTOPOSTER_VERSION
        );
        
        wp_enqueue_script(
            'xautoposter-admin',
            XAUTOPOSTER_URL . 'assets/js/admin.js',
            ['jquery'],
            XAUTOPOSTER_VERSION,
            true
        );
        
        wp_localize_script('xautoposter-admin', 'xautoposter', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('xautoposter_admin'),
            'strings' => [
                'error' => __('An error occurred.', 'xautoposter'),
                'confirm_unlock' => __('Are you sure you want to modify the API settings? This will require re-verification.', 'xautoposter'),
                'interval_warning' => __('Changing the interval will affect when posts are shared.', 'xautoposter')
            ]
        ]);
    }
}
<?php
/**
 * Административная часть плагина
 */

if (!defined('ABSPATH')) {
    exit;
}

class WTB_Admin {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_menu_page'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('wp_ajax_wtb_test_connection', array($this, 'ajax_test_connection'));
        add_action('wp_ajax_wtb_webhook_info', array($this, 'ajax_webhook_info'));
        add_action('wp_ajax_wtb_set_webhook', array($this, 'ajax_set_webhook'));
        add_action('wp_ajax_wtb_delete_webhook', array($this, 'ajax_delete_webhook'));
        add_action('wp_ajax_wtb_get_updates', array($this, 'ajax_get_updates'));
        add_action('wp_ajax_wtb_manual_poll', array($this, 'ajax_manual_poll'));
    }
    
    /**
     * Добавление страницы в меню
     */
    public function add_menu_page() {
        add_menu_page(
            __('Telegram Bridge', 'wp-telegram-bridge'),
            __('TG Bridge', 'wp-telegram-bridge'),
            'manage_options',
            'wtb-settings',
            array($this, 'render_settings_page'),
            'dashicons-format-chat',
            30
        );
        
        add_submenu_page(
            'wtb-settings',
            __('Настройки', 'wp-telegram-bridge'),
            __('Настройки', 'wp-telegram-bridge'),
            'manage_options',
            'wtb-settings'
        );
        
        add_submenu_page(
            'wtb-settings',
            __('Активные чаты', 'wp-telegram-bridge'),
            __('Активные чаты', 'wp-telegram-bridge'),
            'manage_options',
            'wtb-chats',
            array($this, 'render_chats_page')
        );
    }
    
    /**
     * Регистрация настроек
     */
    public function register_settings() {
        register_setting('wtb_settings', 'wtb_enabled', array(
            'type' => 'boolean',
            'default' => true
        ));
        
        register_setting('wtb_settings', 'wtb_telegram_token', array(
            'type' => 'string',
            'sanitize_callback' => array($this, 'sanitize_token')
        ));
        
        register_setting('wtb_settings', 'wtb_telegram_chat_id', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field'
        ));
        
        register_setting('wtb_settings', 'wtb_telegram_api_url', array(
            'type' => 'string',
            'default' => 'https://api.telegram.org/bot',
            'sanitize_callback' => 'esc_url_raw'
        ));
        
        register_setting('wtb_settings', 'wtb_rate_limit', array(
            'type' => 'integer',
            'default' => 5,
            'sanitize_callback' => 'intval'
        ));
        
        register_setting('wtb_settings', 'wtb_widget_position', array(
            'type' => 'string',
            'default' => 'right'
        ));
        
        register_setting('wtb_settings', 'wtb_widget_title', array(
            'type' => 'string',
            'default' => __('Чат с оператором', 'wp-telegram-bridge'),
            'sanitize_callback' => 'sanitize_text_field'
        ));
        
        register_setting('wtb_settings', 'wtb_operator_name', array(
            'type' => 'string',
            'default' => __('Оператор', 'wp-telegram-bridge'),
            'sanitize_callback' => 'sanitize_text_field'
        ));
        
        register_setting('wtb_settings', 'wtb_operator_photo', array(
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => 'esc_url_raw'
        ));
        
        register_setting('wtb_settings', 'wtb_operator_list', array(
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => 'sanitize_textarea_field'
        ));
        
        register_setting('wtb_settings', 'wtb_proxy_host', array(
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => 'sanitize_text_field'
        ));
        
        register_setting('wtb_settings', 'wtb_proxy_port', array(
            'type' => 'integer',
            'default' => '',
            'sanitize_callback' => 'intval'
        ));
        
        register_setting('wtb_settings', 'wtb_proxy_username', array(
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => 'sanitize_text_field'
        ));
        
        register_setting('wtb_settings', 'wtb_proxy_password', array(
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => array($this, 'sanitize_proxy_password')
        ));
        
        register_setting('wtb_settings', 'wtb_proxy_type', array(
            'type' => 'string',
            'default' => 'http',
            'sanitize_callback' => 'sanitize_text_field'
        ));
        
        register_setting('wtb_settings', 'wtb_api_ip', array(
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => 'sanitize_text_field'
        ));
    }
    
    /**
     * Подключение ресурсов админки
     */
    public function enqueue_assets($hook) {
        if (strpos($hook, 'wtb-') === false) {
            return;
        }
        
        wp_enqueue_style('wtb-admin', WTB_PLUGIN_URL . 'admin/css/admin.css', array(), WTB_VERSION);
    }
    
    /**
     * Рендер страницы настроек
     */
    public function render_settings_page() {
        include WTB_PLUGIN_DIR . 'admin/views/settings-page.php';
    }
    
    /**
     * Рендер страницы чатов
     */
    public function render_chats_page() {
        global $wpdb;
        
        $db = new WTB_Database();
        $sessions = $db->get_active_sessions(168); // 7 дней
        
        include WTB_PLUGIN_DIR . 'admin/views/chats-page.php';
    }
    
    /**
     * AJAX: тест подключения к Telegram
     */
    public function ajax_test_connection() {
        check_ajax_referer('wtb_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Недостаточно прав', 'wp-telegram-bridge')));
        }
        
        $tg = new WTB_Telegram_API();
        $result = $tg->test_connection();
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        wp_send_json_success(array(
            'bot_name' => $result['first_name'] ?? '',
            'bot_username' => $result['username'] ?? ''
        ));
    }
    
    /**
     * Sanitize token: не перешифровываем при пустом значении
     */
    public function sanitize_token($token) {
        if (empty($token)) {
            return get_option('wtb_telegram_token');
        }
        return WTB_Telegram_API::encrypt_token($token);
    }
    
    /**
     * Sanitize proxy password: не перезаписываем при пустом значении
     */
    public function sanitize_proxy_password($password) {
        if (empty($password)) {
            return get_option('wtb_proxy_password');
        }
        return sanitize_text_field($password);
    }
    
    /**
     * AJAX: установка webhook
     */
    public function ajax_set_webhook() {
        check_ajax_referer('wtb_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Недостаточно прав', 'wp-telegram-bridge')));
        }
        
        $use_fallback = !empty($_POST['fallback']);
        $webhook_url = $use_fallback ? self::get_webhook_ajax_url() : self::get_webhook_url();
        
        $tg = new WTB_Telegram_API();
        $result = $tg->set_webhook($webhook_url);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        wp_send_json_success(array(
            'description' => $result['description'] ?? 'OK',
            'url' => $webhook_url
        ));
    }
    
    /**
     * AJAX: удаление webhook
     */
    public function ajax_delete_webhook() {
        check_ajax_referer('wtb_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Недостаточно прав', 'wp-telegram-bridge')));
        }
        
        $tg = new WTB_Telegram_API();
        $result = $tg->delete_webhook();
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        wp_send_json_success(array('description' => $result['description'] ?? 'OK'));
    }
    
    /**
     * AJAX: получение updates (для проверки Group Privacy)
     */
    public function ajax_get_updates() {
        check_ajax_referer('wtb_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Недостаточно прав', 'wp-telegram-bridge')));
        }
        
        $tg = new WTB_Telegram_API();
        
        if (empty($tg->is_configured())) {
            wp_send_json_error(array('message' => __('API не настроен', 'wp-telegram-bridge')));
        }
        
        $api_url = apply_filters('wtb_telegram_api_url', get_option('wtb_telegram_api_url', 'https://api.telegram.org/bot'));
        $url = $api_url . $tg->get_token() . '/getUpdates';
        $request_args = array('timeout' => 30, 'sslverify' => apply_filters('wtb_sslverify', true));
        if (strpos($api_url, 'api.telegram.org') === false) {
            $request_args['headers'] = array('Host' => 'api.telegram.org');
        }
        $request_args = array_merge($request_args, WTB_Telegram_API::get_proxy_args());
        
        $response = wp_remote_get($url, $request_args);
        
        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => $response->get_error_message()));
        }
        
        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($data['ok'])) {
            wp_send_json_error(array('message' => $data['description'] ?? 'Unknown error'));
        }
        
        wp_send_json_success(array(
            'count' => count($data['result']),
            'updates' => array_slice($data['result'], -5) // последние 5
        ));
    }
    
    /**
     * AJAX: ручная проверка сообщений из Telegram (getUpdates)
     */
    public function ajax_manual_poll() {
        check_ajax_referer('wtb_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Недостаточно прав', 'wp-telegram-bridge')));
        }
        
        $result = wtb_poll_updates();
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        if (!empty($result['first_run'])) {
            wp_send_json_success(array(
                'message' => __('Первый запуск. Offset сохранён, старые сообщения пропущены.', 'wp-telegram-bridge'),
                'processed' => 0
            ));
        }
        
        if ($result['processed'] > 0) {
            wp_send_json_success(array(
                'message' => sprintf(
                    __('Получено сообщений: %d', 'wp-telegram-bridge'),
                    $result['processed']
                ),
                'processed' => $result['processed']
            ));
        } else {
            wp_send_json_success(array(
                'message' => __('Новых сообщений нет.', 'wp-telegram-bridge'),
                'processed' => 0
            ));
        }
    }
    
    /**
     * Получение webhook URL (rewrite endpoint)
     */
    public static function get_webhook_url() {
        $secret = get_option('wtb_webhook_secret');
        return home_url('/wtb-webhook/?secret=' . $secret);
    }
    
    /**
     * Получение fallback webhook URL (admin-ajax, не требует rewrite)
     */
    public static function get_webhook_ajax_url() {
        $secret = get_option('wtb_webhook_secret');
        return admin_url('admin-ajax.php?action=wtb_webhook&secret=' . $secret);
    }
    
    /**
     * AJAX: получение информации о webhook
     */
    public function ajax_webhook_info() {
        check_ajax_referer('wtb_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Недостаточно прав', 'wp-telegram-bridge')));
        }
        
        $tg = new WTB_Telegram_API();
        $result = $tg->get_webhook_info();
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        wp_send_json_success(array(
            'url' => $result['url'] ?? '',
            'has_custom_certificate' => $result['has_custom_certificate'] ?? false,
            'pending_update_count' => $result['pending_update_count'] ?? 0,
            'last_error_date' => $result['last_error_date'] ?? '',
            'last_error_message' => $result['last_error_message'] ?? ''
        ));
    }
}

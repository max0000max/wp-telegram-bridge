<?php
/**
 * Интеграция с Telegram Bot API
 * 
 * Security: Token хранится encrypted, HTTPS only
 */

if (!defined('ABSPATH')) {
    exit;
}

class WTB_Telegram_API {
    
    private $api_url = 'https://api.telegram.org/bot';
    private $token;
    private $chat_id;
    
    public function __construct() {
        $this->token = $this->get_decrypted_token();
        $this->chat_id = get_option('wtb_telegram_chat_id');
        $this->api_url = apply_filters('wtb_telegram_api_url', get_option('wtb_telegram_api_url', $this->api_url));
        $this->maybe_setup_socks5();
        $this->maybe_setup_resolve_ip();
    }
    
    /**
     * Получение аргументов прокси для wp_remote_*
     */
    public static function get_proxy_args() {
        $host = get_option('wtb_proxy_host', '');
        $port = get_option('wtb_proxy_port', '');
        $user = get_option('wtb_proxy_username', '');
        $pass = get_option('wtb_proxy_password', '');
        
        if (empty($host) || empty($port)) {
            return array();
        }
        
        $type = get_option('wtb_proxy_type', 'http');
        $scheme = ($type === 'socks5') ? 'socks5' : 'http';
        
        if (!empty($user) && !empty($pass)) {
            $proxy = sprintf('%s://%s:%s@%s:%d', $scheme, $user, $pass, $host, intval($port));
        } else {
            $proxy = sprintf('%s://%s:%d', $scheme, $host, intval($port));
        }
        
        return array('proxy' => $proxy);
    }
    
    /**
     * Настройка SOCKS5 через cURL если нужно
     */
    private function maybe_setup_socks5() {
        if (get_option('wtb_proxy_type', 'http') === 'socks5') {
            add_action('http_api_curl', array($this, 'set_curl_socks5'), 10, 3);
        }
    }
    
    /**
     * Установка CURLOPT_PROXYTYPE для cURL
     */
    public function set_curl_socks5($handle, $args, $url) {
        if (strpos($url, 'telegram.org') !== false || strpos($url, apply_filters('wtb_telegram_api_url', get_option('wtb_telegram_api_url', ''))) !== false) {
            curl_setopt($handle, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
        }
    }
    
    /**
     * Настройка прямого IP для api.telegram.org если DNS заблокирован
     */
    private function maybe_setup_resolve_ip() {
        $ip = get_option('wtb_api_ip', '');
        if (!empty($ip)) {
            add_action('http_api_curl', array($this, 'set_curl_resolve_ip'), 10, 3);
        }
    }
    
    /**
     * Установка CURLOPT_RESOLVE для обхода DNS
     */
    public function set_curl_resolve_ip($handle, $args, $url) {
        $api_url = apply_filters('wtb_telegram_api_url', get_option('wtb_telegram_api_url', 'https://api.telegram.org/bot'));
        if (strpos($url, 'telegram.org') !== false || strpos($url, $api_url) !== false) {
            $ip = sanitize_text_field(get_option('wtb_api_ip', ''));
            if (!empty($ip)) {
                curl_setopt($handle, CURLOPT_RESOLVE, array('api.telegram.org:443:' . $ip));
            }
        }
    }
    
    /**
     * Получение текущего токена
     */
    public function get_token() {
        return $this->token;
    }
    
    /**
     * Проверка настроек
     */
    public function is_configured() {
        return !empty($this->token) && !empty($this->chat_id);
    }
    
    /**
     * Тест соединения с Telegram API
     */
    public function test_connection() {
        if (empty($this->token)) {
            return new WP_Error('no_token', __('Токен не указан', 'wp-telegram-bridge'));
        }
        
        $url = $this->api_url . $this->token . '/getMe';
        
        $request_args = array(
            'timeout' => 30,
            'sslverify' => apply_filters('wtb_sslverify', true)
        );
        if ($this->api_url !== 'https://api.telegram.org/bot') {
            $request_args['headers'] = array('Host' => 'api.telegram.org');
        }
        $request_args = array_merge($request_args, self::get_proxy_args());
        
        $response = wp_remote_get($url, $request_args);
        
        if (is_wp_error($response)) {
            error_log('WTB Telegram test_connection WP_Error: ' . $response->get_error_message());
            return $response;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (empty($data['ok'])) {
            $error_msg = $data['description'] ?? 'Unknown error';
            error_log('WTB Telegram test_connection API error: ' . $error_msg);
            return new WP_Error('telegram_error', $error_msg);
        }
        
        return $data['result'];
    }
    
    /**
     * Отправка сообщения в Telegram
     * 
     * @param string $message Текст сообщения
     * @param array $options Дополнительные опции
     * @return array|WP_Error
     */
    public function send_message($message, $options = array()) {
        if (!$this->is_configured()) {
            return new WP_Error('not_configured', 'Telegram API не настроен');
        }
        
        $url = $this->api_url . $this->token . '/sendMessage';
        
        $body = array(
            'chat_id' => $this->chat_id,
            'text' => $this->format_message($message, $options),
            'parse_mode' => 'HTML'
        );
        
        // Добавляем reply markup если нужно
        if (!empty($options['reply_markup'])) {
            $body['reply_markup'] = json_encode($options['reply_markup']);
        }
        
        $request_args = array(
            'body' => $body,
            'timeout' => 30,
            'sslverify' => apply_filters('wtb_sslverify', true)
        );
        if ($this->api_url !== 'https://api.telegram.org/bot') {
            $request_args['headers'] = array('Host' => 'api.telegram.org');
        }
        $request_args = array_merge($request_args, self::get_proxy_args());
        
        $response = wp_remote_post($url, $request_args);
        
        if (is_wp_error($response)) {
            error_log('WTB Telegram send_message WP_Error: ' . $response->get_error_message());
            return $response;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (empty($data['ok'])) {
            $error_msg = $data['description'] ?? 'Unknown error';
            error_log('WTB Telegram send_message API error: ' . $error_msg);
            return new WP_Error('telegram_error', $error_msg);
        }
        
        return $data;
    }
    
    /**
     * Получение информации о webhook
     */
    public function get_webhook_info() {
        if (empty($this->token)) {
            return new WP_Error('no_token', __('Токен не указан', 'wp-telegram-bridge'));
        }
        
        $url = $this->api_url . $this->token . '/getWebhookInfo';
        
        $request_args = array(
            'timeout' => 30,
            'sslverify' => apply_filters('wtb_sslverify', true)
        );
        if ($this->api_url !== 'https://api.telegram.org/bot') {
            $request_args['headers'] = array('Host' => 'api.telegram.org');
        }
        $request_args = array_merge($request_args, self::get_proxy_args());
        
        $response = wp_remote_get($url, $request_args);
        
        if (is_wp_error($response)) {
            error_log('WTB Telegram get_webhook_info WP_Error: ' . $response->get_error_message());
            return $response;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (empty($data['ok'])) {
            $error_msg = $data['description'] ?? 'Unknown error';
            error_log('WTB Telegram get_webhook_info API error: ' . $error_msg);
            return new WP_Error('telegram_error', $error_msg);
        }
        
        return $data['result'];
    }
    
    /**
     * Установка вебхука для получения сообщений
     */
    public function set_webhook($webhook_url) {
        if (!$this->is_configured()) {
            return new WP_Error('not_configured', 'Telegram API не настроен');
        }
        
        $url = $this->api_url . $this->token . '/setWebhook';
        
        $request_args = array(
            'body' => array(
                'url' => $webhook_url,
                'allowed_updates' => array('message')
            ),
            'timeout' => 30,
            'sslverify' => apply_filters('wtb_sslverify', true)
        );
        if ($this->api_url !== 'https://api.telegram.org/bot') {
            $request_args['headers'] = array('Host' => 'api.telegram.org');
        }
        $request_args = array_merge($request_args, self::get_proxy_args());
        
        $response = wp_remote_post($url, $request_args);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = wp_remote_retrieve_body($response);
        return json_decode($body, true);
    }
    
    /**
     * Удаление вебхука
     */
    public function delete_webhook() {
        if (!$this->is_configured()) {
            return new WP_Error('not_configured', 'Telegram API не настроен');
        }
        
        $url = $this->api_url . $this->token . '/deleteWebhook';
        
        $request_args = array(
            'timeout' => 30,
            'sslverify' => apply_filters('wtb_sslverify', true)
        );
        if ($this->api_url !== 'https://api.telegram.org/bot') {
            $request_args['headers'] = array('Host' => 'api.telegram.org');
        }
        $request_args = array_merge($request_args, self::get_proxy_args());
        
        $response = wp_remote_get($url, $request_args);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = wp_remote_retrieve_body($response);
        return json_decode($body, true);
    }
    
    /**
     * Проверка валидности webhook запроса от Telegram
     * 
     * Security: проверка секрета в URL
     */
    public function verify_webhook_request() {
        $secret = isset($_GET['secret']) ? sanitize_text_field($_GET['secret']) : '';
        $expected = get_option('wtb_webhook_secret');
        
        return hash_equals($expected, $secret);
    }
    
    /**
     * Получение данных из webhook
     */
    public function get_webhook_data() {
        $input = file_get_contents('php://input');
        return json_decode($input, true);
    }
    
    /**
     * Форматирование сообщения для Telegram
     */
    private function format_message($message, $options) {
        $formatted = '';
        
        if (!empty($options['session_id'])) {
            $formatted .= "🆔 Сессия: #" . esc_html($options['session_id']) . "\n";
        }
        
        if (!empty($options['visitor_name'])) {
            $formatted .= "👤 " . esc_html($options['visitor_name']) . "\n";
        }
        
        if (!empty($options['visitor_email'])) {
            $formatted .= "📧 " . esc_html($options['visitor_email']) . "\n";
        }
        
        if (!empty($options['visitor_phone'])) {
            $formatted .= "📱 " . esc_html($options['visitor_phone']) . "\n";
        }
        
        $formatted .= "─────────────\n";
        $formatted .= esc_html($message);
        
        return $formatted;
    }
    
    /**
     * Получение расшифрованного токена
     * 
     * Security: simple encryption, для production использовать библиотеку
     */
    private function get_decrypted_token() {
        $encrypted = get_option('wtb_telegram_token');
        
        if (empty($encrypted)) {
            return '';
        }
        
        // Рекурсивно снимаем все слои шифрования (защита от double-encryption)
        while (strpos($encrypted, 'enc:') === 0) {
            $decoded = base64_decode(substr($encrypted, 4), true);
            if ($decoded === false) {
                break;
            }
            $encrypted = $decoded;
        }
        
        return $encrypted;
    }
    
    /**
     * Шифрование токена при сохранении
     */
    public static function encrypt_token($token) {
        // Не шифруем повторно
        if (strpos($token, 'enc:') === 0) {
            return $token;
        }
        
        // TODO: заменить на proper encryption
        return 'enc:' . base64_encode($token);
    }
}

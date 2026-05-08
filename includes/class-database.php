<?php
/**
 * Работа с базой данных
 * 
 * Security: Все запросы через $wpdb->prepare()
 */

if (!defined('ABSPATH')) {
    exit;
}

class WTB_Database {
    
    private $table_sessions;
    private $table_messages;
    
    public function __construct() {
        global $wpdb;
        $this->table_sessions = $wpdb->prefix . WTB_TABLE_SESSIONS;
        $this->table_messages = $wpdb->prefix . WTB_TABLE_MESSAGES;
    }
    
    /**
     * Создание новой сессии чата
     * 
     * @param array $data Данные сессии
     * @return int|false ID созданной сессии или false
     */
    public function create_session($data) {
        global $wpdb;
        
        $session_key = $this->generate_session_key();
        
        // Prepared statement для INSERT
        $result = $wpdb->query($wpdb->prepare(
            "INSERT INTO {$this->table_sessions} 
            (session_key, visitor_name, visitor_email, visitor_phone, status, consent_given_at, consent_ip, consent_text, operator_name, operator_photo) 
            VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s)",
            $session_key,
            sanitize_text_field($data['visitor_name'] ?? ''),
            sanitize_email($data['visitor_email'] ?? ''),
            sanitize_text_field($data['visitor_phone'] ?? ''),
            'active',
            !empty($data['consent_given_at']) ? $data['consent_given_at'] : null,
            !empty($data['consent_ip']) ? sanitize_text_field($data['consent_ip']) : null,
            !empty($data['consent_text']) ? sanitize_textarea_field($data['consent_text']) : null,
            !empty($data['operator_name']) ? sanitize_text_field($data['operator_name']) : null,
            !empty($data['operator_photo']) ? esc_url_raw($data['operator_photo']) : null
        ));
        
        if ($result === false) {
            return false;
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Выбор случайного оператора из списка
     * 
     * @return array|null ['name' => ..., 'photo' => ...] или null
     */
    public function pick_random_operator() {
        $list = get_option('wtb_operator_list', '');
        if (empty($list)) {
            $default_name = get_option('wtb_operator_name', 'Оператор');
            $default_photo = get_option('wtb_operator_photo', '');
            if (empty($default_name) && empty($default_photo)) {
                return null;
            }
            return array('name' => $default_name, 'photo' => $default_photo);
        }
        
        $lines = array_filter(array_map('trim', explode("\n", $list)));
        if (empty($lines)) {
            return null;
        }
        
        $line = $lines[array_rand($lines)];
        $parts = array_map('trim', explode('|', $line, 2));
        return array(
            'name' => sanitize_text_field($parts[0]),
            'photo' => !empty($parts[1]) ? esc_url_raw($parts[1]) : ''
        );
    }
    
    /**
     * Получение сессии по ключу
     * 
     * @param string $session_key
     * @return object|null
     */
    public function get_session($session_key) {
        global $wpdb;
        
        // Prepared statement для SELECT
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_sessions} WHERE session_key = %s LIMIT 1",
            $session_key
        ));
    }
    
    /**
     * Обновление Telegram chat ID для сессии
     * 
     * @param int $session_id
     * @param string $telegram_chat_id
     * @return bool
     */
    public function update_telegram_chat_id($session_id, $telegram_chat_id) {
        global $wpdb;
        
        $result = $wpdb->query($wpdb->prepare(
            "UPDATE {$this->table_sessions} 
            SET telegram_chat_id = %s 
            WHERE id = %d",
            $telegram_chat_id,
            $session_id
        ));
        
        return $result !== false;
    }
    
    /**
     * Сохранение сообщения
     * 
     * @param array $data Данные сообщения
     * @return int|false
     */
    public function save_message($data) {
        global $wpdb;
        
        $result = $wpdb->query($wpdb->prepare(
            "INSERT INTO {$this->table_messages} 
            (session_id, direction, content, telegram_message_id, sender_type) 
            VALUES (%d, %s, %s, %s, %s)",
            intval($data['session_id']),
            $data['direction'], // 'to_tg' или 'from_tg'
            sanitize_textarea_field($data['content']),
            !empty($data['telegram_message_id']) ? intval($data['telegram_message_id']) : null,
            $data['sender_type'] ?? 'visitor'
        ));
        
        if ($result === false) {
            return false;
        }
        
        // Обновляем timestamp сессии
        $wpdb->query($wpdb->prepare(
            "UPDATE {$this->table_sessions} SET updated_at = NOW() WHERE id = %d",
            intval($data['session_id'])
        ));
        
        return $wpdb->insert_id;
    }
    
    /**
     * Обновление telegram_message_id для сообщения
     * 
     * @param int $message_id
     * @param int $telegram_message_id
     * @return bool
     */
    public function update_message_telegram_id($message_id, $telegram_message_id) {
        global $wpdb;
        
        $result = $wpdb->query($wpdb->prepare(
            "UPDATE {$this->table_messages} SET telegram_message_id = %d WHERE id = %d",
            intval($telegram_message_id),
            intval($message_id)
        ));
        
        return $result !== false;
    }
    
    /**
     * Получение сообщений сессии
     * 
     * @param int $session_id
     * @param int $limit
     * @return array
     */
    public function get_session_messages($session_id, $limit = 100) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_messages} 
            WHERE session_id = %d 
            ORDER BY created_at ASC 
            LIMIT %d",
            intval($session_id),
            intval($limit)
        ));
    }
    
    /**
     * Сохранение FAQ с логированием согласия
     * 
     * @param array $data
     * @return int|false
     */
    public function save_faq_submission($data) {
        global $wpdb;
        
        $table_faq = $wpdb->prefix . 'wtb_faq_submissions';
        
        $result = $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table_faq} 
            (message, name, phone, email, consent_given_at, consent_ip, consent_text) 
            VALUES (%s, %s, %s, %s, %s, %s, %s)",
            sanitize_textarea_field($data['message'] ?? ''),
            sanitize_text_field($data['name'] ?? ''),
            sanitize_text_field($data['phone'] ?? ''),
            sanitize_email($data['email'] ?? ''),
            !empty($data['consent_given_at']) ? $data['consent_given_at'] : null,
            !empty($data['consent_ip']) ? sanitize_text_field($data['consent_ip']) : null,
            !empty($data['consent_text']) ? sanitize_textarea_field($data['consent_text']) : null
        ));
        
        return $result !== false ? $wpdb->insert_id : false;
    }
    
    /**
     * Получение активных сессий
     * 
     * @param int $hours За сколько часов
     * @return array
     */
    public function get_active_sessions($hours = 24) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_sessions} 
            WHERE status = 'active' 
            AND updated_at > DATE_SUB(NOW(), INTERVAL %d HOUR)
            ORDER BY updated_at DESC",
            intval($hours)
        ));
    }
    
    /**
     * Закрытие сессии
     * 
     * @param int $session_id
     * @return bool
     */
    public function close_session($session_id) {
        global $wpdb;
        
        $result = $wpdb->query($wpdb->prepare(
            "UPDATE {$this->table_sessions} 
            SET status = 'closed' 
            WHERE id = %d",
            intval($session_id)
        ));
        
        return $result !== false;
    }
    
    /**
     * Rate limiting: проверка времени последнего сообщения
     * 
     * @param int $session_id
     * @param int $limit_seconds
     * @return bool true если можно отправлять
     */
    public function check_rate_limit($session_id, $limit_seconds = 5) {
        global $wpdb;
        
        $last_message = $wpdb->get_var($wpdb->prepare(
            "SELECT created_at FROM {$this->table_messages} 
            WHERE session_id = %d 
            ORDER BY created_at DESC 
            LIMIT 1",
            intval($session_id)
        ));
        
        if (!$last_message) {
            return true;
        }
        
        $last_time = mysql2date('U', $last_message);
        return (current_time('timestamp') - $last_time) >= $limit_seconds;
    }
    
    /**
     * Генерация уникального ключа сессии
     * 
     * Security: cryptographically secure random
     */
    private function generate_session_key() {
        return bin2hex(random_bytes(16)); // 32 символа
    }
}

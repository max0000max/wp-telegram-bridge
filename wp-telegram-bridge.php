<?php
/**
 * Plugin Name: WP Telegram Bridge
 * Description: Пересылка сообщений между чатом на сайте и Telegram
 * Version: 1.0.0
 * Author: IWE
 * License: GPL v2 or later
 * Text Domain: wp-telegram-bridge
 */

// Защита от прямого доступа
if (!defined('ABSPATH')) {
    exit;
}

// Константы плагина
define('WTB_VERSION', '1.1.0');
define('WTB_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WTB_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WTB_TABLE_SESSIONS', 'wtb_sessions');
define('WTB_TABLE_MESSAGES', 'wtb_messages');

// Автозагрузка классов
require_once WTB_PLUGIN_DIR . 'includes/class-activator.php';
require_once WTB_PLUGIN_DIR . 'includes/class-database.php';
require_once WTB_PLUGIN_DIR . 'includes/class-telegram-api.php';

// Активация/деактивация
register_activation_hook(__FILE__, array('WTB_Activator', 'activate'));
register_deactivation_hook(__FILE__, array('WTB_Activator', 'deactivate'));

// Миграции БД
function wtb_maybe_migrate_db() {
    $db_version = get_option('wtb_db_version', '0');
    
    if (version_compare($db_version, '1.1.0', '<')) {
        global $wpdb;
        $table_messages = $wpdb->prefix . WTB_TABLE_MESSAGES;
        
        // Добавляем telegram_message_id для reply routing
        $column_exists = $wpdb->get_results($wpdb->prepare(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s",
            DB_NAME,
            $table_messages,
            'telegram_message_id'
        ));
        
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE {$table_messages} ADD COLUMN telegram_message_id bigint(20) DEFAULT NULL AFTER content");
        }
        
        update_option('wtb_db_version', '1.1.0');
    }
    
    if (version_compare($db_version, '1.2.0', '<')) {
        global $wpdb;
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        $table_sessions = $wpdb->prefix . WTB_TABLE_SESSIONS;
        $table_faq = $wpdb->prefix . 'wtb_faq_submissions';
        $charset_collate = $wpdb->get_charset_collate();
        
        // Добавляем колонки согласия в сессии
        $columns = array(
            'consent_given_at' => 'datetime DEFAULT NULL',
            'consent_ip' => 'varchar(100) DEFAULT NULL',
            'consent_text' => 'text DEFAULT NULL'
        );
        foreach ($columns as $column => $def) {
            $exists = $wpdb->get_results($wpdb->prepare(
                "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s",
                DB_NAME,
                $table_sessions,
                $column
            ));
            if (empty($exists)) {
                $wpdb->query("ALTER TABLE {$table_sessions} ADD COLUMN {$column} {$def}");
            }
        }
        
        // Создаём таблицу FAQ с логированием согласия
        $sql_faq = "CREATE TABLE {$table_faq} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            message text NOT NULL,
            name varchar(100) DEFAULT NULL,
            phone varchar(50) DEFAULT NULL,
            email varchar(100) DEFAULT NULL,
            consent_given_at datetime DEFAULT NULL,
            consent_ip varchar(100) DEFAULT NULL,
            consent_text text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) {$charset_collate};";
        dbDelta($sql_faq);
        
        update_option('wtb_db_version', '1.2.0');
    }
    
    if (version_compare($db_version, '1.3.0', '<')) {
        global $wpdb;
        $table_sessions = $wpdb->prefix . WTB_TABLE_SESSIONS;
        
        $columns = array(
            'operator_name' => 'varchar(100) DEFAULT NULL',
            'operator_photo' => 'varchar(255) DEFAULT NULL'
        );
        foreach ($columns as $column => $def) {
            $exists = $wpdb->get_results($wpdb->prepare(
                "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s",
                DB_NAME,
                $table_sessions,
                $column
            ));
            if (empty($exists)) {
                $wpdb->query("ALTER TABLE {$table_sessions} ADD COLUMN {$column} {$def}");
            }
        }
        
        update_option('wtb_db_version', '1.3.0');
    }
}

// Инициализация плагина
add_action('plugins_loaded', 'wtb_init');

function wtb_init() {
    // Миграции БД
    wtb_maybe_migrate_db();
    
    // Загрузка текстового домена
    load_plugin_textdomain('wp-telegram-bridge', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    
    // Загрузка классов (нужны и в админке, и на фронтенде)
    require_once WTB_PLUGIN_DIR . 'includes/class-admin.php';
    
    // Инициализация админки
    if (is_admin()) {
        new WTB_Admin();
    }
    
    // Публичная часть (виджет)
    add_action('wp_enqueue_scripts', 'wtb_enqueue_public_assets');
    add_action('wp_footer', 'wtb_render_chat_widget');
    
    // AJAX handlers (logged in and not logged in)
    add_action('wp_ajax_wtb_start_session', 'wtb_ajax_start_session');
    add_action('wp_ajax_nopriv_wtb_start_session', 'wtb_ajax_start_session');
    add_action('wp_ajax_wtb_send_message', 'wtb_ajax_send_message');
    add_action('wp_ajax_nopriv_wtb_send_message', 'wtb_ajax_send_message');
    add_action('wp_ajax_wtb_get_messages', 'wtb_ajax_get_messages');
    add_action('wp_ajax_nopriv_wtb_get_messages', 'wtb_ajax_get_messages');
    add_action('wp_ajax_wtb_submit_faq', 'wtb_ajax_submit_faq');
    add_action('wp_ajax_nopriv_wtb_submit_faq', 'wtb_ajax_submit_faq');
    
    // Cron для получения сообщений из Telegram (fallback для хостингов без webhook)
    add_action('wtb_poll_updates', 'wtb_poll_updates');
    if (!wp_next_scheduled('wtb_poll_updates')) {
        wp_schedule_event(time(), 'every_minute', 'wtb_poll_updates');
    }
}

// AJAX: Start new chat session
function wtb_ajax_start_session() {
    check_ajax_referer('wtb_nonce', 'nonce');
    
    $name = sanitize_text_field($_POST['name'] ?? '');
    $email = sanitize_email($_POST['email'] ?? '');
    $phone = sanitize_text_field($_POST['phone'] ?? '');
    
    if (empty($name)) {
        wp_send_json_error(array('message' => __('Введите имя', 'wp-telegram-bridge')));
    }
    
    if (empty($email)) {
        wp_send_json_error(array('message' => __('Введите email', 'wp-telegram-bridge')));
    }
    
    $consent_text = apply_filters('wtb_consent_text', 'Даю согласие на обработку моих персональных данных и принимаю условия политики (https://callibri.ru/agreement/43439, https://callibri.ru/privacy/43439)');
    
    $db = new WTB_Database();
    $operator = $db->pick_random_operator();
    
    $session_id = $db->create_session(array(
        'visitor_name' => $name,
        'visitor_email' => $email,
        'visitor_phone' => $phone,
        'consent_given_at' => current_time('mysql'),
        'consent_ip' => sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? ''),
        'consent_text' => $consent_text,
        'operator_name' => $operator['name'] ?? get_option('wtb_operator_name', 'Оператор'),
        'operator_photo' => $operator['photo'] ?? get_option('wtb_operator_photo', '')
    ));
    
    if (!$session_id) {
        wp_send_json_error(array('message' => __('Ошибка создания сессии', 'wp-telegram-bridge')));
    }
    
    // Get session key
    global $wpdb;
    $session = $wpdb->get_row($wpdb->prepare(
        "SELECT session_key FROM {$wpdb->prefix}" . WTB_TABLE_SESSIONS . " WHERE id = %d",
        $session_id
    ));
    
    wp_send_json_success(array(
        'session_id' => $session_id,
        'session_key' => $session->session_key,
        'operator_name' => $operator['name'] ?? get_option('wtb_operator_name', 'Оператор'),
        'operator_photo' => $operator['photo'] ?? get_option('wtb_operator_photo', '')
    ));
}

// AJAX: Send message
function wtb_ajax_send_message() {
    check_ajax_referer('wtb_nonce', 'nonce');
    
    $session_key = sanitize_text_field($_POST['session_key'] ?? '');
    $content = sanitize_textarea_field($_POST['content'] ?? '');
    
    if (empty($session_key) || empty($content)) {
        wp_send_json_error(array('message' => __('Неверные данные', 'wp-telegram-bridge')));
    }
    
    $db = new WTB_Database();
    $session = $db->get_session($session_key);
    
    if (!$session) {
        wp_send_json_error(array(
            'message' => __('Сессия не найдена', 'wp-telegram-bridge'),
            'code' => 'session_not_found'
        ));
    }
    
    // Rate limiting
    $rate_limit = intval(get_option('wtb_rate_limit', 5));
    if (!$db->check_rate_limit($session->id, $rate_limit)) {
        wp_send_json_error(array('message' => __('Слишком быстро. Подождите...', 'wp-telegram-bridge')));
    }
    
    // Save message
    $message_db_id = $db->save_message(array(
        'session_id' => $session->id,
        'direction' => 'to_tg',
        'content' => $content,
        'sender_type' => 'visitor'
    ));
    
    // Send to Telegram
    $tg = new WTB_Telegram_API();
    if ($tg->is_configured()) {
        $result = $tg->send_message($content, array(
            'session_id' => $session->id,
            'visitor_name' => $session->visitor_name,
            'visitor_email' => $session->visitor_email,
            'visitor_phone' => $session->visitor_phone
        ));
        
        if (is_wp_error($result)) {
            wp_send_json_error(array(
                'message' => __('Ошибка отправки в Telegram: ', 'wp-telegram-bridge') . $result->get_error_message(),
                'code' => 'telegram_error'
            ));
        }
        
        // Save telegram message_id for reply routing
        if ($message_db_id && !empty($result['result']['message_id'])) {
            $db->update_message_telegram_id($message_db_id, $result['result']['message_id']);
        }
        
        // Link session to Telegram chat for webhook routing
        $telegram_chat_id = get_option('wtb_telegram_chat_id');
        if (!empty($telegram_chat_id) && empty($session->telegram_chat_id)) {
            $db->update_telegram_chat_id($session->id, $telegram_chat_id);
        }
    }
    
    wp_send_json_success();
}

// AJAX: Get messages
function wtb_ajax_get_messages() {
    check_ajax_referer('wtb_nonce', 'nonce');
    
    $session_key = sanitize_text_field($_POST['session_key'] ?? '');
    
    if (empty($session_key)) {
        wp_send_json_error();
    }
    
    $db = new WTB_Database();
    $session = $db->get_session($session_key);
    
    if (!$session) {
        wp_send_json_error(array(
            'message' => __('Сессия не найдена', 'wp-telegram-bridge'),
            'code' => 'session_not_found'
        ));
    }
    
    $messages = $db->get_session_messages($session->id);
    
    foreach ($messages as $msg) {
        $msg->created_ts = mysql2date('U', $msg->created_at);
    }
    
    wp_send_json_success(array(
        'messages' => $messages,
        'operator_name' => $session->operator_name ?: get_option('wtb_operator_name', 'Оператор'),
        'operator_photo' => $session->operator_photo ?: get_option('wtb_operator_photo', '')
    ));
}

// AJAX: Submit FAQ question
function wtb_ajax_submit_faq() {
    check_ajax_referer('wtb_nonce', 'nonce');
    
    $message = sanitize_textarea_field($_POST['message'] ?? '');
    $name = sanitize_text_field($_POST['name'] ?? '');
    $phone = sanitize_text_field($_POST['phone'] ?? '');
    $email = sanitize_email($_POST['email'] ?? '');
    
    if (empty($message)) {
        wp_send_json_error(array('message' => __('Введите сообщение', 'wp-telegram-bridge')));
    }
    
    if (empty($name)) {
        wp_send_json_error(array('message' => __('Введите имя', 'wp-telegram-bridge')));
    }
    
    if (empty($email)) {
        wp_send_json_error(array('message' => __('Введите email', 'wp-telegram-bridge')));
    }
    
    // Save consent log
    $consent_text = apply_filters('wtb_consent_text', 'Даю согласие на обработку моих персональных данных и принимаю условия политики (https://callibri.ru/agreement/43439, https://callibri.ru/privacy/43439)');
    
    $db = new WTB_Database();
    $db->save_faq_submission(array(
        'message' => $message,
        'name' => $name,
        'phone' => $phone,
        'email' => $email,
        'consent_given_at' => current_time('mysql'),
        'consent_ip' => sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? ''),
        'consent_text' => $consent_text
    ));
    
    // Build notification text
    $text = sprintf(
        "❓ Новый вопрос с сайта\n\nИмя: %s\nEmail: %s\nТелефон: %s\nСообщение: %s",
        $name,
        $email,
        $phone ? $phone : '—',
        $message
    );
    
    // Send to Telegram
    $tg = new WTB_Telegram_API();
    if ($tg->is_configured()) {
        $result = $tg->send_message($text);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array(
                'message' => __('Ошибка отправки в Telegram: ', 'wp-telegram-bridge') . $result->get_error_message(),
                'code' => 'telegram_error'
            ));
        }
    }
    
    wp_send_json_success(array('message' => __('Вопрос успешно отправлен', 'wp-telegram-bridge')));
}

/**
 * Обработка входящего сообщения от оператора (Telegram)
 * @param string $from_chat_id
 * @param string $message_text
 * @param string $source 'webhook' или 'poll'
 * @param int|null $reply_to_telegram_message_id message_id бота, на которое ответил оператор
 * @return bool
 */
function wtb_handle_operator_message($from_chat_id, $message_text, $source = 'webhook', $reply_to_telegram_message_id = null, $telegram_message_id = null) {
    global $wpdb;
    
    $session = null;
    $table_sessions = $wpdb->prefix . WTB_TABLE_SESSIONS;
    $table_messages = $wpdb->prefix . WTB_TABLE_MESSAGES;
    
    // 1. Если оператор сделал Reply на сообщение бота — роутим по telegram_message_id
    if (!empty($reply_to_telegram_message_id)) {
        $session = $wpdb->get_row($wpdb->prepare(
            "SELECT s.* FROM {$table_sessions} s 
            INNER JOIN {$table_messages} m ON m.session_id = s.id 
            WHERE m.telegram_message_id = %d 
            AND s.status = 'active' 
            LIMIT 1",
            intval($reply_to_telegram_message_id)
        ));
        
        if ($session) {
            error_log("WTB {$source}: routed by reply_to telegram_message_id=" . $reply_to_telegram_message_id . " -> session " . $session->id);
        }
    }
    
    // 2. Fallback: последняя активная сессия в этом чате
    if (!$session) {
        $session = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_sessions} WHERE telegram_chat_id = %s AND status = 'active' ORDER BY updated_at DESC LIMIT 1",
            $from_chat_id
        ));
    }
    
    if (!$session) {
        error_log("WTB {$source}: no active session for chat_id " . $from_chat_id);
        return false;
    }
    
    // Проверка на дубль (повторные webhook/getUpdates)
    if (!empty($telegram_message_id)) {
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table_messages} 
            WHERE telegram_message_id = %d AND direction = 'from_tg' 
            LIMIT 1",
            $telegram_message_id
        ));
        if ($existing) {
            error_log("WTB {$source}: duplicate ignored by telegram_message_id=" . $telegram_message_id);
            return false;
        }
        $db = new WTB_Database();
        $db->save_message(array(
            'session_id' => $session->id,
            'direction' => 'from_tg',
            'content' => $message_text,
            'sender_type' => 'operator',
            'telegram_message_id' => $telegram_message_id
        ));
        error_log("WTB {$source}: saved message from operator to session " . $session->id . " telegram_message_id=" . $telegram_message_id);
        return true;
    }
    
    $recent_duplicate = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$table_messages} 
        WHERE session_id = %d AND direction = 'from_tg' AND content = %s 
        AND created_at > DATE_SUB(NOW(), INTERVAL 30 SECOND) 
        LIMIT 1",
        $session->id,
        $message_text
    ));
    
    if (!$recent_duplicate) {
        $db = new WTB_Database();
        $db->save_message(array(
            'session_id' => $session->id,
            'direction' => 'from_tg',
            'content' => $message_text,
            'sender_type' => 'operator'
        ));
        error_log("WTB {$source}: saved message from operator to session " . $session->id);
        return true;
    } else {
        error_log("WTB {$source}: duplicate ignored");
        return false;
    }
}

// Webhook processor (shared between rewrite endpoint and admin-ajax fallback)
function wtb_process_webhook() {
    // Security: Verify secret
    $tg = new WTB_Telegram_API();
    if (!$tg->verify_webhook_request()) {
        error_log('WTB Webhook 403: secret mismatch. Expected: ' . get_option('wtb_webhook_secret') . ', Got: ' . ($_GET['secret'] ?? 'empty'));
        status_header(403);
        echo 'Forbidden';
        exit;
    }
    
    // Get data
    $data = $tg->get_webhook_data();
    error_log('WTB Webhook received: ' . json_encode($data));
    
    // Определяем тип обновления: message, edited_message, channel_post
    $update_type = 'unknown';
    $message = array();
    if (!empty($data['message'])) {
        $update_type = 'message';
        $message = $data['message'];
    } elseif (!empty($data['edited_message'])) {
        $update_type = 'edited_message';
        $message = $data['edited_message'];
    } elseif (!empty($data['channel_post'])) {
        $update_type = 'channel_post';
        $message = $data['channel_post'];
    }
    
    // Сохраняем последний webhook для отладки в админке
    update_option('wtb_last_webhook', array(
        'time' => current_time('mysql'),
        'payload' => $data,
        'update_type' => $update_type,
        'chat_id' => $message['chat']['id'] ?? null,
        'text' => $message['text'] ?? null
    ));
    
    if (empty($message['text'])) {
        echo 'OK';
        exit;
    }
    
    $message_text = sanitize_textarea_field($message['text']);
    $from_chat_id = strval($message['chat']['id']);
    $reply_to_id = !empty($message['reply_to_message']['message_id']) ? intval($message['reply_to_message']['message_id']) : null;
    $telegram_message_id = !empty($message['message_id']) ? intval($message['message_id']) : null;
    
    wtb_handle_operator_message($from_chat_id, $message_text, 'webhook', $reply_to_id, $telegram_message_id);
    
    echo 'OK';
    exit;
}

// Legacy webhook endpoint (rewrite rules)
add_action('init', 'wtb_add_rewrite_rules');
add_action('template_redirect', 'wtb_handle_webhook');

function wtb_add_rewrite_rules() {
    add_rewrite_rule('wtb-webhook/?$', 'index.php?wtb_webhook=1', 'top');
    add_rewrite_tag('%wtb_webhook%', '([0-9]+)');
}

// Flush rewrite rules once after plugin update
add_action('init', 'wtb_flush_rewrite_on_update', 99);

function wtb_flush_rewrite_on_update() {
    $flushed = get_option('wtb_rewrite_version', '0');
    if ($flushed !== WTB_VERSION) {
        flush_rewrite_rules();
        update_option('wtb_rewrite_version', WTB_VERSION);
    }
}

function wtb_handle_webhook() {
    if (intval(get_query_var('wtb_webhook')) !== 1) {
        return;
    }
    wtb_process_webhook();
}

// Fallback webhook endpoint (admin-ajax, no rewrite required)
add_action('wp_ajax_nopriv_wtb_webhook', 'wtb_ajax_webhook');
add_action('wp_ajax_wtb_webhook', 'wtb_ajax_webhook');

function wtb_ajax_webhook() {
    wtb_process_webhook();
}

// Cron interval: every minute
add_filter('cron_schedules', 'wtb_cron_schedules');
function wtb_cron_schedules($schedules) {
    $schedules['every_minute'] = array(
        'interval' => 60,
        'display'  => __('Каждую минуту', 'wp-telegram-bridge')
    );
    return $schedules;
}

/**
 * Получение сообщений из Telegram через getUpdates (long polling fallback)
 * @return array|WP_Error array('processed' => int, 'offset' => int) или WP_Error
 */
function wtb_poll_updates() {
    $tg = new WTB_Telegram_API();
    
    if (!$tg->is_configured()) {
        return new WP_Error('not_configured', __('API не настроен', 'wp-telegram-bridge'));
    }
    
    $api_url = apply_filters('wtb_telegram_api_url', get_option('wtb_telegram_api_url', 'https://api.telegram.org/bot'));
    $token = $tg->get_token();
    
    if (empty($token)) {
        return new WP_Error('no_token', __('Токен не указан', 'wp-telegram-bridge'));
    }
    
    $last_update_id = intval(get_option('wtb_last_update_id', 0));
    $is_first_run = ($last_update_id === 0);
    
    if ($is_first_run) {
        // Первый запуск: просто узнаём текущий offset, не обрабатываем старые сообщения
        $url = $api_url . $token . '/getUpdates?limit=1';
    } else {
        $url = $api_url . $token . '/getUpdates?offset=' . ($last_update_id + 1) . '&limit=10';
    }
    
    $request_args = array('timeout' => 30, 'sslverify' => apply_filters('wtb_sslverify', true));
    if (strpos($api_url, 'api.telegram.org') === false) {
        $request_args['headers'] = array('Host' => 'api.telegram.org');
    }
    $request_args = array_merge($request_args, WTB_Telegram_API::get_proxy_args());
    
    $response = wp_remote_get($url, $request_args);
    
    if (is_wp_error($response)) {
        error_log('WTB poll_updates error: ' . $response->get_error_message());
        return $response;
    }
    
    $body = json_decode(wp_remote_retrieve_body($response), true);
    
    if (empty($body['ok'])) {
        return new WP_Error('telegram_error', $body['description'] ?? 'Unknown error');
    }
    
    if (empty($body['result'])) {
        return array('processed' => 0, 'offset' => $last_update_id, 'first_run' => $is_first_run);
    }
    
    $max_update_id = 0;
    $processed = 0;
    
    foreach ($body['result'] as $update) {
        if (!empty($update['update_id'])) {
            $max_update_id = max($max_update_id, $update['update_id']);
        }
        
        // При первом запуске не обрабатываем сообщения, только сохраняем offset
        if ($is_first_run) {
            continue;
        }
        
        // Определяем тип обновления
        $message = array();
        if (!empty($update['message'])) {
            $message = $update['message'];
        } elseif (!empty($update['edited_message'])) {
            $message = $update['edited_message'];
        } elseif (!empty($update['channel_post'])) {
            $message = $update['channel_post'];
        }
        
        if (empty($message['text'])) {
            continue;
        }
        
        $message_text = sanitize_textarea_field($message['text']);
        $from_chat_id = strval($message['chat']['id']);
        $reply_to_id = !empty($message['reply_to_message']['message_id']) ? intval($message['reply_to_message']['message_id']) : null;
        $telegram_message_id = !empty($message['message_id']) ? intval($message['message_id']) : null;
        
        if (wtb_handle_operator_message($from_chat_id, $message_text, 'poll', $reply_to_id, $telegram_message_id)) {
            $processed++;
        }
    }
    
    if ($max_update_id > 0) {
        update_option('wtb_last_update_id', $max_update_id);
    }
    
    return array('processed' => $processed, 'offset' => $max_update_id, 'first_run' => $is_first_run);
}

// Подключение CSS/JS для публичной части
function wtb_enqueue_public_assets() {
    wp_enqueue_script('jquery');
}

// Рендер виджета
function wtb_render_chat_widget() {
    // Проверяем, включен ли чат в настройках
    $enabled = get_option('wtb_enabled', '1');
    if (!$enabled) {
        return;
    }
    
    include WTB_PLUGIN_DIR . 'public/views/chat-widget.php';
}

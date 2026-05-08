<?php
/**
 * Активация и деактивация плагина
 * 
 * Security: Prepared statements, dbDelta для миграций
 */

if (!defined('ABSPATH')) {
    exit;
}

class WTB_Activator {
    
    /**
     * Активация плагина
     */
    public static function activate() {
        global $wpdb;
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Таблица сессий чатов
        $table_sessions = $wpdb->prefix . WTB_TABLE_SESSIONS;
        $sql_sessions = "CREATE TABLE {$table_sessions} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            session_key varchar(32) NOT NULL,
            telegram_chat_id bigint(20) DEFAULT NULL,
            visitor_name varchar(100) DEFAULT NULL,
            visitor_email varchar(100) DEFAULT NULL,
            visitor_phone varchar(50) DEFAULT NULL,
            status varchar(20) DEFAULT 'active',
            consent_given_at datetime DEFAULT NULL,
            consent_ip varchar(100) DEFAULT NULL,
            consent_text text DEFAULT NULL,
            operator_name varchar(100) DEFAULT NULL,
            operator_photo varchar(255) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY session_key (session_key),
            KEY status_updated (status, updated_at)
        ) {$charset_collate};";
        
        // Таблица сообщений
        $table_messages = $wpdb->prefix . WTB_TABLE_MESSAGES;
        $sql_messages = "CREATE TABLE {$table_messages} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            session_id bigint(20) UNSIGNED NOT NULL,
            direction varchar(20) NOT NULL COMMENT 'to_tg или from_tg',
            content text NOT NULL,
            telegram_message_id bigint(20) DEFAULT NULL,
            sender_type varchar(20) DEFAULT 'visitor' COMMENT 'visitor или operator',
            is_read tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY session_time (session_id, created_at),
            KEY direction (direction),
            KEY telegram_msg (telegram_message_id)
        ) {$charset_collate};";
        
        // Выполнение схемы (dbDelta безопасен для обновлений)
        // Таблица FAQ с логированием согласия
        $table_faq = $wpdb->prefix . 'wtb_faq_submissions';
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
        
        dbDelta($sql_sessions);
        dbDelta($sql_messages);
        dbDelta($sql_faq);
        
        // Настройки по умолчанию
        add_option('wtb_enabled', '1');
        add_option('wtb_telegram_token', '');
        add_option('wtb_telegram_chat_id', '');
        add_option('wtb_webhook_secret', self::generate_webhook_secret());
        add_option('wtb_rate_limit', '5'); // секунд между сообщениями
        add_option('wtb_widget_position', 'right');
        add_option('wtb_widget_title', 'Чат с оператором');
        add_option('wtb_operator_name', 'Оператор');
        add_option('wtb_operator_photo', '');
        
        // Флаг версии для миграций
        add_option('wtb_db_version', WTB_VERSION);
        
        // Flush rewrite rules для webhook endpoint
        wtb_add_rewrite_rules();
        flush_rewrite_rules();
    }
    
    /**
     * Деактивация плагина
     */
    public static function deactivate() {
        // Очищаем cron-задачи если есть
        wp_clear_scheduled_hook('wtb_cleanup_old_sessions');
        wp_clear_scheduled_hook('wtb_poll_updates');
    }
    
    /**
     * Удаление плагина (uninstall)
     * Вызывается при полном удалении через uninstall.php
     */
    public static function uninstall() {
        global $wpdb;
        
        // Удаляем таблицы
        $table_sessions = $wpdb->prefix . WTB_TABLE_SESSIONS;
        $table_messages = $wpdb->prefix . WTB_TABLE_MESSAGES;
        
        // Prepared statements не нужны для DROP TABLE
        $wpdb->query("DROP TABLE IF EXISTS {$table_sessions}");
        $wpdb->query("DROP TABLE IF EXISTS {$table_messages}");
        
        // Удаляем настройки
        delete_option('wtb_enabled');
        delete_option('wtb_telegram_token');
        delete_option('wtb_telegram_chat_id');
        delete_option('wtb_webhook_secret');
        delete_option('wtb_rate_limit');
        delete_option('wtb_widget_position');
        delete_option('wtb_widget_title');
        delete_option('wtb_operator_name');
        delete_option('wtb_operator_photo');
        delete_option('wtb_db_version');
    }
    
    /**
     * Генерация секрета для вебхуков
     * Security: случайная строка 32 символа
     */
    private static function generate_webhook_secret() {
        return bin2hex(random_bytes(16));
    }
}

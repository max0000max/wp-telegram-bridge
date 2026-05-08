<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php _e('Активные чаты', 'wp-telegram-bridge'); ?></h1>
    
    <?php if (empty($sessions)) : ?>
        <p><?php _e('Нет активных чатов за последние 7 дней.', 'wp-telegram-bridge'); ?></p>
    <?php else : ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('ID', 'wp-telegram-bridge'); ?></th>
                    <th><?php _e('Ключ сессии', 'wp-telegram-bridge'); ?></th>
                    <th><?php _e('Имя', 'wp-telegram-bridge'); ?></th>
                    <th><?php _e('Email', 'wp-telegram-bridge'); ?></th>
                    <th><?php _e('Telegram Chat', 'wp-telegram-bridge'); ?></th>
                    <th><?php _e('Статус', 'wp-telegram-bridge'); ?></th>
                    <th><?php _e('Последняя активность', 'wp-telegram-bridge'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sessions as $session) : ?>
                    <tr>
                        <td><?php echo esc_html($session->id); ?></td>
                        <td><code><?php echo esc_html(substr($session->session_key, 0, 16)); ?>...</code></td>
                        <td><?php echo esc_html($session->visitor_name ?: '—'); ?></td>
                        <td><?php echo esc_html($session->visitor_email ?: '—'); ?></td>
                        <td><?php echo esc_html($session->telegram_chat_id ?: '—'); ?></td>
                        <td>
                            <span class="wtb-status wtb-status-<?php echo esc_attr($session->status); ?>">
                                <?php echo esc_html($session->status); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html(human_time_diff(strtotime($session->updated_at), current_time('timestamp'))); ?> 
                            <?php _e('назад', 'wp-telegram-bridge'); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <style>
            .wtb-status {
                padding: 3px 8px;
                border-radius: 3px;
                font-size: 12px;
                text-transform: uppercase;
            }
            .wtb-status-active {
                background: #46b450;
                color: white;
            }
            .wtb-status-closed {
                background: #dc3232;
                color: white;
            }
            .wtb-status-timeout {
                background: #ffb900;
                color: black;
            }
        </style>
    <?php endif; ?>
</div>

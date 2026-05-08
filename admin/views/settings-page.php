<?php
if (!defined('ABSPATH')) {
    exit;
}

$webhook_url = WTB_Admin::get_webhook_url();
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <form method="post" action="options.php">
        <?php settings_fields('wtb_settings'); ?>
        <?php do_settings_sections('wtb_settings'); ?>
        
        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('Включить чат', 'wp-telegram-bridge'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="wtb_enabled" value="1" 
                            <?php checked(get_option('wtb_enabled'), '1'); ?>>
                        <?php _e('Показывать виджет чата на сайте', 'wp-telegram-bridge'); ?>
                    </label>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Telegram Bot Token', 'wp-telegram-bridge'); ?></th>
                <td>
                    <input type="password" name="wtb_telegram_token" 
                           value="" 
                           placeholder="<?php _e('Оставьте пустым, чтобы сохранить текущий', 'wp-telegram-bridge'); ?>" 
                           class="regular-text">
                    <p class="description">
                        <?php _e('Получите у @BotFather в Telegram', 'wp-telegram-bridge'); ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Telegram Chat ID', 'wp-telegram-bridge'); ?></th>
                <td>
                    <input type="text" name="wtb_telegram_chat_id" 
                           value="<?php echo esc_attr(get_option('wtb_telegram_chat_id')); ?>" 
                           class="regular-text">
                    <p class="description">
                        <?php _e('ID чата или группы, куда будут приходить сообщения', 'wp-telegram-bridge'); ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Альтернативный URL API', 'wp-telegram-bridge'); ?></th>
                <td>
                    <input type="url" name="wtb_telegram_api_url" 
                           value="<?php echo esc_attr(get_option('wtb_telegram_api_url', 'https://api.telegram.org/bot')); ?>" 
                           class="regular-text"
                           placeholder="https://api.telegram.org/bot">
                    <p class="description">
                        <?php _e('Оставьте по умолчанию. Изменяйте только если получаете ошибку "Could not resolve host" — укажите адрес своего прокси (например, https://tg-api.example.com/bot).', 'wp-telegram-bridge'); ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Прямой IP для api.telegram.org', 'wp-telegram-bridge'); ?></th>
                <td>
                    <input type="text" name="wtb_api_ip" 
                           value="<?php echo esc_attr(get_option('wtb_api_ip', '')); ?>" 
                           class="regular-text"
                           placeholder="149.154.167.220">
                    <p class="description">
                        <?php _e('Для хостингов с заблокированным DNS (InfinityFree и др.). Укажите IP-адрес api.telegram.org — плагин подключится напрямую, минуя DNS. Подсказка: 149.154.167.220', 'wp-telegram-bridge'); ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Прокси', 'wp-telegram-bridge'); ?></th>
                <td>
                    <input type="text" name="wtb_proxy_host" 
                           value="<?php echo esc_attr(get_option('wtb_proxy_host', '')); ?>" 
                           class="regular-text" placeholder="<?php _e('Хост прокси', 'wp-telegram-bridge'); ?>">
                    <input type="number" name="wtb_proxy_port" 
                           value="<?php echo esc_attr(get_option('wtb_proxy_port', '')); ?>" 
                           class="small-text" placeholder="<?php _e('Порт', 'wp-telegram-bridge'); ?>">
                    <p class="description">
                        <?php _e('Например: 127.0.0.1 и 8080 для локального прокси.', 'wp-telegram-bridge'); ?>
                    </p>
                    <br>
                    <input type="text" name="wtb_proxy_username" 
                           value="<?php echo esc_attr(get_option('wtb_proxy_username', '')); ?>" 
                           class="regular-text" placeholder="<?php _e('Логин (опционально)', 'wp-telegram-bridge'); ?>">
                    <input type="password" name="wtb_proxy_password" 
                           value="" 
                           class="regular-text" placeholder="<?php _e('Пароль (опционально)', 'wp-telegram-bridge'); ?>">
                    <p class="description">
                        <?php _e('Оставьте пустым, чтобы сохранить текущий пароль.', 'wp-telegram-bridge'); ?>
                    </p>
                    <br>
                    <select name="wtb_proxy_type">
                        <option value="http" <?php selected(get_option('wtb_proxy_type', 'http'), 'http'); ?>>HTTP</option>
                        <option value="socks5" <?php selected(get_option('wtb_proxy_type', 'http'), 'socks5'); ?>>SOCKS5</option>
                    </select>
                    <p class="description">
                        <?php _e('Тип прокси. SOCKS5 требует поддержки cURL на сервере.', 'wp-telegram-bridge'); ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Проверка подключения', 'wp-telegram-bridge'); ?></th>
                <td>
                    <button type="button" class="button" id="wtb-test-connection">
                        <?php _e('Проверить соединение с Telegram', 'wp-telegram-bridge'); ?>
                    </button>
                    <span id="wtb-test-result" style="margin-left: 10px;"></span>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Rate Limit', 'wp-telegram-bridge'); ?></th>
                <td>
                    <input type="number" name="wtb_rate_limit" 
                           value="<?php echo esc_attr(get_option('wtb_rate_limit', 5)); ?>" 
                           min="1" max="60" class="small-text">
                    <?php _e('секунд между сообщениями', 'wp-telegram-bridge'); ?>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Позиция виджета', 'wp-telegram-bridge'); ?></th>
                <td>
                    <select name="wtb_widget_position">
                        <option value="right" <?php selected(get_option('wtb_widget_position'), 'right'); ?>>
                            <?php _e('Справа', 'wp-telegram-bridge'); ?>
                        </option>
                        <option value="left" <?php selected(get_option('wtb_widget_position'), 'left'); ?>>
                            <?php _e('Слева', 'wp-telegram-bridge'); ?>
                        </option>
                    </select>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Заголовок чата', 'wp-telegram-bridge'); ?></th>
                <td>
                    <input type="text" name="wtb_widget_title" 
                           value="<?php echo esc_attr(get_option('wtb_widget_title', 'Чат с оператором')); ?>" 
                           class="regular-text">
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Имя оператора', 'wp-telegram-bridge'); ?></th>
                <td>
                    <input type="text" name="wtb_operator_name" 
                           value="<?php echo esc_attr(get_option('wtb_operator_name', 'Оператор')); ?>" 
                           class="regular-text">
                    <p class="description">
                        <?php _e('Имя, которое будет отображаться в приветствии виджета', 'wp-telegram-bridge'); ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Фото оператора', 'wp-telegram-bridge'); ?></th>
                <td>
                    <input type="url" name="wtb_operator_photo" 
                           value="<?php echo esc_attr(get_option('wtb_operator_photo', '')); ?>" 
                           class="regular-text"
                           placeholder="https://example.com/avatar.jpg">
                    <p class="description">
                        <?php _e('URL круглого изображения аватара оператора', 'wp-telegram-bridge'); ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Список операторов', 'wp-telegram-bridge'); ?></th>
                <td>
                    <textarea name="wtb_operator_list" 
                              rows="5" 
                              cols="50" 
                              class="large-text code"><?php echo esc_textarea(get_option('wtb_operator_list', '')); ?></textarea>
                    <p class="description">
                        <?php _e('Один оператор на строку в формате: Имя|URL_фото. Пример: Анна|https://site.com/anna.jpg', 'wp-telegram-bridge'); ?>
                    </p>
                </td>
            </tr>
        </table>
        
        <?php submit_button(); ?>
    </form>
    
    <hr>
    
    <h2><?php _e('Webhook для Telegram', 'wp-telegram-bridge'); ?></h2>
    <p><?php _e('Основной URL (требует ЧПУ / rewrite):', 'wp-telegram-bridge'); ?></p>
    <code style="background:#f0f0f0;padding:10px;display:block;margin:10px 0;">
        <?php echo esc_url($webhook_url); ?>
    </code>
    
    <p><?php _e('Fallback URL (не требует rewrite, работает на любом хостинге):', 'wp-telegram-bridge'); ?></p>
    <code style="background:#f0f0f0;padding:10px;display:block;margin:10px 0;">
        <?php echo esc_url(WTB_Admin::get_webhook_ajax_url()); ?>
    </code>
    
    <p>
        <button type="button" class="button" id="wtb-check-webhook">
            <?php _e('Проверить статус webhook', 'wp-telegram-bridge'); ?>
        </button>
        <span id="wtb-webhook-status" style="margin-left:10px;"></span>
    </p>
    
    <p>
        <button type="button" class="button" id="wtb-set-webhook-main">
            <?php _e('Установить webhook (основной)', 'wp-telegram-bridge'); ?>
        </button>
        <button type="button" class="button" id="wtb-set-webhook-fallback">
            <?php _e('Установить webhook (fallback)', 'wp-telegram-bridge'); ?>
        </button>
        <button type="button" class="button" id="wtb-delete-webhook">
            <?php _e('Удалить webhook', 'wp-telegram-bridge'); ?>
        </button>
        <button type="button" class="button" id="wtb-get-updates">
            <?php _e('Проверить getUpdates', 'wp-telegram-bridge'); ?>
        </button>
        <span id="wtb-webhook-action-status" style="margin-left:10px;"></span>
    </p>
    
    <?php
    $tg = new WTB_Telegram_API();
    $webhook_info = $tg->get_webhook_info();
    if (!is_wp_error($webhook_info)) :
    ?>
        <div style="background:#f9f9f9;border:1px solid #ddd;padding:15px;margin:15px 0;">
            <strong><?php _e('Текущий статус webhook (из Telegram):', 'wp-telegram-bridge'); ?></strong><br>
            <?php _e('URL:', 'wp-telegram-bridge'); ?> <code><?php echo esc_html($webhook_info['url'] ?: __('не установлен', 'wp-telegram-bridge')); ?></code><br>
            <?php _e('Ожидают доставки:', 'wp-telegram-bridge'); ?> <?php echo intval($webhook_info['pending_update_count']); ?><br>
            <?php if (!empty($webhook_info['last_error_message'])) : ?>
                <span style="color:#d63638;">
                    <?php _e('Последняя ошибка доставки:', 'wp-telegram-bridge'); ?> 
                    <?php echo esc_html($webhook_info['last_error_message']); ?>
                    <?php if (!empty($webhook_info['last_error_date'])) echo '(' . esc_html($webhook_info['last_error_date']) . ')'; ?>
                </span>
            <?php else : ?>
                <span style="color:#00a32a;"><?php _e('Ошибок доставки нет', 'wp-telegram-bridge'); ?></span>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    
    <div style="background:#fff8e1;border:1px solid #ffe082;padding:15px;margin:15px 0;">
        <strong>⚠ <?php _e('Почему webhook может не работать:', 'wp-telegram-bridge'); ?></strong>
        <ul style="margin:10px 0 0 20px;list-style:disc;">
            <li><?php _e('Локальный сервер (localhost): Telegram не может слать запросы на ваш компьютер. Для тестирования webhook на локалке используйте ngrok (см. ниже).', 'wp-telegram-bridge'); ?></li>
            <li><?php _e('InfinityFree / бесплатный хостинг: Telegram API может отклонять webhook из-за некорректного SSL-сертификата.', 'wp-telegram-bridge'); ?></li>
            <li><?php _e('Хостинг блокирует входящие POST-запросы от внешних сервисов.', 'wp-telegram-bridge'); ?></li>
        </ul>
        <p><strong><?php _e('Локальная разработка через ngrok:', 'wp-telegram-bridge'); ?></strong></p>
        <ol style="margin:5px 0 0 20px;">
            <li><?php _e('Скачайте ngrok с', 'wp-telegram-bridge'); ?> <a href="https://ngrok.com/download" target="_blank">ngrok.com</a></li>
            <li><?php _e('Запустите:', 'wp-telegram-bridge'); ?> <code>ngrok http 80</code> (или ваш порт, например 8080)</li>
            <li><?php _e('Скопируйте HTTPS-URL (например', 'wp-telegram-bridge'); ?> <code>https://abc123.ngrok.io</code>)</li>
            <li><?php _e('Установите webhook:', 'wp-telegram-bridge'); ?> <code>https://api.telegram.org/botТОКЕН/setWebhook?url=https://abc123.ngrok.io/wp-admin/admin-ajax.php?action=wtb_webhook&secret=СЕКРЕТ</code></li>
        </ol>
    </div>
    
    <div style="background:#e8f5e9;border:1px solid #a5d6a7;padding:15px;margin:15px 0;">
        <strong>✓ <?php _e('Fallback: Long polling (getUpdates)', 'wp-telegram-bridge'); ?></strong>
        <p><?php _e('Если webhook не работает (InfinityFree, блокировка входящих запросов), плагин автоматически забирает сообщения из Telegram каждую минуту через WordPress Cron. Для этого не требуется входящее соединение.', 'wp-telegram-bridge'); ?></p>
        <p>
            <button type="button" class="button" id="wtb-manual-poll">
                <?php _e('Принудительно проверить сообщения', 'wp-telegram-bridge'); ?>
            </button>
            <span id="wtb-manual-poll-result" style="margin-left:10px;"></span>
        </p>
        <p><?php _e('Важно: на слабонагруженных сайтах WordPress Cron срабатывает только при посещении сайта. Если сообщения приходят с задержкой — это норма для бесплатного хостинга.', 'wp-telegram-bridge'); ?></p>
    </div>
    
    <div style="background:#ffebee;border:1px solid #ef9a9a;padding:15px;margin:15px 0;">
        <strong>⚠ <?php _e('Важно для групп:', 'wp-telegram-bridge'); ?></strong>
        <ul style="margin:10px 0 0 20px;list-style:disc;">
            <li><?php _e('У бота должен быть выключен Group Privacy (иначе он не видит сообщения в группе). Проверьте в @BotFather → Bot Settings → Group Privacy → Turn off.', 'wp-telegram-bridge'); ?></li>
            <li><?php _e('Бот должен быть добавлен в группу и иметь права на чтение сообщений.', 'wp-telegram-bridge'); ?></li>
            <li><?php _e('Для ответа конкретному посетителю используйте Reply (ответ) на сообщение бота в группе.', 'wp-telegram-bridge'); ?></li>
        </ul>
    </div>
    
    <h2><?php _e('Отладка входящих webhook', 'wp-telegram-bridge'); ?></h2>
    <?php
    $last_webhook = get_option('wtb_last_webhook');
    if ($last_webhook) : ?>
        <p><?php _e('Время:', 'wp-telegram-bridge'); ?> <code><?php echo esc_html($last_webhook['time']); ?></code></p>
        <p><?php _e('Тип:', 'wp-telegram-bridge'); ?> <code><?php echo esc_html($last_webhook['update_type'] ?? '—'); ?></code></p>
        <p><?php _e('Chat ID от Telegram:', 'wp-telegram-bridge'); ?> <code><?php echo esc_html($last_webhook['chat_id'] ?? '—'); ?></code></p>
        <p><?php _e('Текст:', 'wp-telegram-bridge'); ?> <code><?php echo esc_html($last_webhook['text'] ?? '—'); ?></code></p>
        <details>
            <summary><?php _e('Полный payload (JSON)', 'wp-telegram-bridge'); ?></summary>
            <pre style="background:#f0f0f0;padding:10px;overflow:auto;max-height:300px;"><?php echo esc_html(json_encode($last_webhook['payload'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></pre>
        </details>
    <?php else : ?>
        <p style="color:#999;"><?php _e('Пока не получено ни одного webhook от Telegram.', 'wp-telegram-bridge'); ?></p>
    <?php endif; ?>
    
    <hr>
    
    <h2><?php _e('Инструкция по настройке', 'wp-telegram-bridge'); ?></h2>
    <ol>
        <li><?php _e('Создайте бота через @BotFather в Telegram', 'wp-telegram-bridge'); ?></li>
        <li><?php _e('Получите токен и вставьте его выше', 'wp-telegram-bridge'); ?></li>
        <li><?php _e('Добавьте бота в группу операторов или напишите ему лично', 'wp-telegram-bridge'); ?></li>
        <li><?php _e('Получите Chat ID (через @userinfobot или API)', 'wp-telegram-bridge'); ?></li>
        <li><?php _e('Настройте webhook: отправьте GET запрос на:', 'wp-telegram-bridge'); ?><br>
            <code>https://api.telegram.org/bot[TOKEN]/setWebhook?url=<?php echo urlencode($webhook_url); ?></code>
        </li>
    </ol>
    
    <script>
    jQuery(document).ready(function($) {
        $('#wtb-test-connection').on('click', function() {
            var $btn = $(this);
            var $result = $('#wtb-test-result');
            $btn.prop('disabled', true);
            $result.text('<?php _e("Проверка...", "wp-telegram-bridge"); ?>');
            
            $.post(ajaxurl, {
                action: 'wtb_test_connection',
                nonce: '<?php echo wp_create_nonce('wtb_nonce'); ?>'
            }, function(response) {
                $btn.prop('disabled', false);
                if (response.success) {
                    $result.html('<span style="color:green">✓ <?php _e("Подключено:", "wp-telegram-bridge"); ?> @' + 
                        (response.data.bot_username || '') + '</span>');
                } else {
                    $result.html('<span style="color:red">✗ ' + 
                        (response.data.message || '<?php _e("Ошибка", "wp-telegram-bridge"); ?>') + 
                        '</span>');
                }
            });
        });
        
        $('#wtb-check-webhook').on('click', function() {
            var $btn = $(this);
            var $result = $('#wtb-webhook-status');
            $btn.prop('disabled', true);
            $result.text('<?php _e("Проверка...", "wp-telegram-bridge"); ?>');
            
            $.post(ajaxurl, {
                action: 'wtb_webhook_info',
                nonce: '<?php echo wp_create_nonce('wtb_nonce'); ?>'
            }, function(response) {
                $btn.prop('disabled', false);
                if (response.success) {
                    var info = response.data;
                    var html = '<span style="color:green">✓ <?php _e("Webhook установлен:", "wp-telegram-bridge"); ?> ' + 
                        escHtml(info.url) + '</span>';
                    if (info.last_error_message) {
                        html += '<br><span style="color:red"><?php _e("Последняя ошибка:", "wp-telegram-bridge"); ?> ' + 
                            escHtml(info.last_error_message) + '</span>';
                    }
                    if (info.pending_update_count > 0) {
                        html += '<br><span style="color:orange"><?php _e("Ожидают доставки:", "wp-telegram-bridge"); ?> ' + 
                            info.pending_update_count + '</span>';
                    }
                    $result.html(html);
                } else {
                    $result.html('<span style="color:red">✗ ' + 
                        (response.data.message || '<?php _e("Ошибка", "wp-telegram-bridge"); ?>') + 
                        '</span>');
                }
            });
        });
        
        $('#wtb-set-webhook-main').on('click', function() {
            wtbWebhookAction('wtb_set_webhook', {fallback: 0}, '<?php _e("Webhook установлен", "wp-telegram-bridge"); ?>');
        });
        
        $('#wtb-set-webhook-fallback').on('click', function() {
            wtbWebhookAction('wtb_set_webhook', {fallback: 1}, '<?php _e("Fallback webhook установлен", "wp-telegram-bridge"); ?>');
        });
        
        $('#wtb-delete-webhook').on('click', function() {
            wtbWebhookAction('wtb_delete_webhook', {}, '<?php _e("Webhook удалён", "wp-telegram-bridge"); ?>');
        });
        
        $('#wtb-get-updates').on('click', function() {
            var $btn = $(this);
            var $result = $('#wtb-webhook-action-status');
            $btn.prop('disabled', true);
            $result.text('<?php _e("Запрос...", "wp-telegram-bridge"); ?>');
            
            $.post(ajaxurl, {
                action: 'wtb_get_updates',
                nonce: '<?php echo wp_create_nonce('wtb_nonce'); ?>'
            }, function(response) {
                $btn.prop('disabled', false);
                if (response.success) {
                    var updates = response.data.updates || [];
                    var html = '<span style="color:green">✓ <?php _e("Получено обновлений:", "wp-telegram-bridge"); ?> ' + 
                        response.data.count + '</span>';
                    if (updates.length > 0) {
                        html += '<br><code style="font-size:11px;">' + 
                            escHtml(JSON.stringify(updates, null, 2).substring(0, 500)) + '...</code>';
                    }
                    $result.html(html);
                } else {
                    $result.html('<span style="color:red">✗ ' + 
                        (response.data.message || '<?php _e("Ошибка", "wp-telegram-bridge"); ?>') + 
                        '</span>');
                }
            });
        });
        
        $('#wtb-manual-poll').on('click', function() {
            var $btn = $(this);
            var $result = $('#wtb-manual-poll-result');
            $btn.prop('disabled', true);
            $result.text('<?php _e("Проверка...", "wp-telegram-bridge"); ?>');
            
            $.post(ajaxurl, {
                action: 'wtb_manual_poll',
                nonce: '<?php echo wp_create_nonce('wtb_nonce'); ?>'
            }, function(response) {
                $btn.prop('disabled', false);
                if (response.success) {
                    $result.html('<span style="color:green">✓ ' + 
                        (response.data.message || '<?php _e("Готово", "wp-telegram-bridge"); ?>') + 
                        '</span>');
                } else {
                    $result.html('<span style="color:red">✗ ' + 
                        (response.data.message || '<?php _e("Ошибка", "wp-telegram-bridge"); ?>') + 
                        '</span>');
                }
            });
        });
        
        function wtbWebhookAction(action, extra, successText) {
            var $result = $('#wtb-webhook-action-status');
            $result.text('<?php _e("Выполнение...", "wp-telegram-bridge"); ?>');
            var data = {
                action: action,
                nonce: '<?php echo wp_create_nonce('wtb_nonce'); ?>'
            };
            $.extend(data, extra);
            $.post(ajaxurl, data, function(response) {
                if (response.success) {
                    $result.html('<span style="color:green">✓ ' + successText + '</span>');
                } else {
                    $result.html('<span style="color:red">✗ ' + 
                        (response.data.message || '<?php _e("Ошибка", "wp-telegram-bridge"); ?>') + 
                        '</span>');
                }
            });
        }
        
        function escHtml(text) {
            return $('<div>').text(text).html();
        }
    });
    </script>
</div>

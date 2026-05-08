# WP Telegram Bridge

Плагин WordPress для пересылки сообщений между чатом на сайте и Telegram. Двусторонняя связь оператор ↔ посетитель.

## Возможности

- 💬 Плавающий виджет чата на сайте
- 📱 Пересылка сообщений в Telegram
- 💬 Ответы оператора из Telegram → на сайт
- 🛡️ Безопасность: prepared statements, rate limiting, CSP headers
- 📊 История чатов в админке WordPress

## Требования

- WordPress 5.0+
- PHP 7.4+
- HTTPS (обязательно для вебхуков Telegram)

## Установка

1. Скопируйте папку `wp-telegram-bridge` в `/wp-content/plugins/`
2. Активируйте плагин в меню "Плагины"
3. Перейдите в "TG Bridge" → "Настройки"
4. Настройте Telegram Bot Token и Chat ID

## Настройка Telegram

1. Создайте бота через [@BotFather](https://t.me/BotFather)
2. Получите токен (например: `123456789:ABCdefGHIjklMNOpqrsTUVwxyz`)
3. Добавьте бота в группу операторов
4. Получите Chat ID через [@userinfobot](https://t.me/userinfobot) или API
5. Настройте webhook (URL показан в настройках плагина)

### Ручная настройка webhook

```bash
curl -X GET "https://api.telegram.org/bot<TOKEN>/setWebhook?url=<WEBHOOK_URL>"
```

## Безопасность

- ✅ Prepared statements для всех SQL-запросов
- ✅ Rate limiting: лимит на частоту сообщений
- ✅ CSP headers для виджета
- ✅ Шифрование токена Telegram (base64, для production рекомендуется defuse/php-encryption)
- ✅ Проверка nonce во всех AJAX-запросах
- ✅ Верификация webhook по секретному ключу

## Архитектура

```
┌─────────────┐      AJAX/WebSocket      ┌─────────────────┐
│  Виджет WP  │ ◄──────────────────────► │  Плагин WP      │
│  (frontend) │                          │  (PHP/MySQL)    │
└─────────────┘                          └────────┬────────┘
                                                  │
                                                  │ HTTPS
                                                  ▼
                                          ┌─────────────────┐
                                          │ Telegram API    │
                                          └────────┬────────┘
                                                   │
                                                   ▼
                                           ┌───────────────┐
                                           │ Оператор      │
                                           │ (Telegram)    │
                                           └───────────────┘
```

## Структура БД

### wtb_sessions
- id, session_key, telegram_chat_id
- visitor_name, visitor_email
- status (active/closed/timeout)
- created_at, updated_at

### wtb_messages
- id, session_id, direction (to_tg/from_tg)
- content, sender_type, is_read, created_at

## Разработка

### Структура плагина

```
wp-telegram-bridge/
├── wp-telegram-bridge.php      # Main file
├── uninstall.php               # Uninstall handler
├── includes/
│   ├── class-activator.php     # Activation/Deactivation
│   ├── class-database.php      # DB operations (prepared)
│   ├── class-telegram-api.php  # Telegram integration
│   └── class-admin.php         # Admin panel
├── public/
│   ├── css/chat-widget.css     # Widget styles
│   └── views/chat-widget.php   # Widget template
└── admin/
    └── views/                  # Admin templates
```

## TODO (v2)

- [ ] WebSockets вместо polling
- [ ] Поддержка файлов/изображений
- [ ] Несколько операторов
- [ ] Оффлайн-режим (email-уведомления)
- [ ] Proper encryption для токена (defuse/php-encryption)
- [ ] Интеграция с WooCommerce

## Лицензия

GPL v2 or later
# wp-telegram-bridge
# wp-telegram-bridge
# wp-telegram-bridge

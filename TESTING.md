# Тестирование WP Telegram Bridge

## Подготовка тестовой среды

1. Установить WordPress (локально или на сервере)
2. Включить HTTPS (обязательно для Telegram webhook)
3. Скопировать плагин в `wp-content/plugins/wp-telegram-bridge/`
4. Активировать плагин

### Быстрая локальная установка

См. подробную инструкцию: [`docker/README.md`](docker/README.md)

> **Важно:** Telegram webhook принимает только валидные HTTPS-сертификаты. Локальный self-signed сертификат подходит для тестирования фронтенда, но **НЕ** для webhook. Для webhook используй [LocalTunnel](https://localtunnel.me).
>
> ```bash
> # Установка
> npm install -g localtunnel
>
> # Запуск туннеля
> lt --port 8443 --local-host localhost
>
> # Затем вставь выданный HTTPS-URL в настройки плагина
> ```

---

## Тест-кейсы

### TC-1: Активация плагина
**Шаги:**
1. Перейти в Plugins → активировать "WP Telegram Bridge"

**Ожидаемый результат:**
- Плагин активирован без ошибок
- В меню появился пункт "TG Bridge"
- Созданы таблицы в БД: `{prefix}wtb_sessions`, `{prefix}wtb_messages`

**Проверка БД:**
```sql
SHOW TABLES LIKE '%wtb%';
```

---

### TC-2: Создание сессии (AJAX)

**Шаги:**
1. Открыть страницу сайта (фронтенд)
2. Открыть DevTools → Network
3. Кликнуть на виджет чата
4. Ввести имя и нажать "Начать чат"

**Ожидаемый результат:**
- AJAX запрос `wtb_start_session` возвращает `success: true`
- В ответе есть `session_key` и `session_id`
- В БД появилась запись в `wtb_sessions`

---

### TC-3: Отправка сообщения (AJAX)

**Шаги:**
1. Продолжение TC-2 (сессия создана)
2. Ввести сообщение и отправить

**Ожидаемый результат:**
- AJAX запрос `wtb_send_message` возвращает `success: true`
- Сообщение появилось в `wtb_messages` (direction='to_tg')
- Если настроен Telegram — сообщение пришло в чат

---

### TC-4: Получение сообщений (AJAX polling)

**Шаги:**
1. Продолжение TC-3
2. Подождать 5 секунд (polling interval)

**Ожидаемый результат:**
- AJAX запрос `wtb_get_messages` возвращает историю
- Новые сообщения отображаются в виджете

---

### TC-5: Webhook от Telegram

**Подготовка:**
1. Создать бота через @BotFather
2. Получить token и chat_id
3. В настройках плагина ввести token и chat_id
4. Настроить webhook (см. README)

**Шаги:**
1. Отправить сообщение боту из Telegram

**Ожидаемый результат:**
- Webhook получен (200 OK)
- Сообщение сохранено в `wtb_messages` (direction='from_tg')
- При следующем polling сообщение появится в виджете

---

## Чеклист безопасности

- [ ] Rate limiting работает (нельзя спамить сообщения)
- [ ] SQL-инъекция невозможна (prepared statements)
- [ ] XSS невозможен (sanitize_text_field на выводе)
- [ ] Webhook проверяет secret
- [ ] AJAX проверяет nonce

---

## Отладка

### Включить debug

```php
// wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

### Логи webhook

Добавить в начало `wtb_handle_webhook()`:
```php
error_log('WTB Webhook: ' . json_encode($data));
```

### Проверка Telegram API

```bash
# Проверить вебхук
curl https://api.telegram.org/bot<TOKEN>/getWebhookInfo

# Отправить тестовое сообщение
curl -X POST https://api.telegram.org/bot<TOKEN>/sendMessage \
  -d chat_id=<CHAT_ID> \
  -d text="Test message"
```

---

## Известные ограничения (v1.0.0)

1. Polling раз в 5 секунд (не real-time WebSocket)
2. Один оператор на сессию (по telegram_chat_id)
3. Нет поддержки файлов
4. Токен шифруется base64 (не криптостойко)


# Локальное тестовое окружение WP Telegram Bridge

## Быстрый выбор

| Вариант | Когда выбрать | Требует sudo |
|---------|---------------|--------------|
| **A. Docker** | Чистое изолированное окружение, приближенное к production | Да (для установки Docker) |
| **B. LAMP** | Начать тестирование прямо сейчас без установки Docker | Нет |

> ⚠️ **Telegram webhook требует валидный HTTPS.** Локальный self-signed сертификат подходит для тестирования фронтенда, но **НЕ** для webhook. Для webhook используй [LocalTunnel](https://localtunnel.me) или [Cloudflare Tunnel](https://developers.cloudflare.com/cloudflare-one/connections/connect-networks/).

---

## Вариант A: Docker (рекомендуемый)

### 1. Установка Docker (требуется sudo)

```bash
# Обнови пакеты
sudo apt-get update

# Установи зависимости
sudo apt-get install -y ca-certificates curl gnupg

# Добавь официальный GPG-ключ Docker
sudo install -m 0755 -d /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/debian/gpg | sudo gpg --dearmor -o /etc/apt/keyrings/docker.gpg
sudo chmod a+r /etc/apt/keyrings/docker.gpg

# Добавь репозиторий
echo \
  "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/debian \
  $(. /etc/os-release && echo "$VERSION_CODENAME") stable" | \
  sudo tee /etc/apt/sources.list.d/docker.list > /dev/null

# Установи Docker Engine + Compose plugin
sudo apt-get update
sudo apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin

# Добавь текущего пользователя в группу docker (перелогинься после)
sudo usermod -aG docker $USER
```

> Перелогинься или выполни `newgrp docker`, чтобы применить группу.

### 2. Подготовка окружения

```bash
cd wp-telegram-bridge/docker

# Скопируй переменные окружения
sed 's/^/export /' .env.example > .env
# Или просто: cp .env.example .env
# Отредактируй пароли в .env

# Сгенерируй self-signed сертификат
chmod +x setup-ssl.sh
./setup-ssl.sh
```

### 3. Запуск

```bash
docker compose up -d
```

WordPress будет доступен по адресам:
- `http://localhost:8080` → редирект на HTTPS
- `https://localhost:8443` (с самоподписанным сертификатом)
- `https://wp-test.local:8443` (добавь `127.0.0.1 wp-test.local` в `/etc/hosts`)

> Порты `8080` и `8443` выбраны потому, что `80` и `443` на машине заняты системным Apache.

### 4. Установка WordPress

1. Открой `https://localhost:8443`
2. Пройди мастер установки WordPress
3. Войди в админку
4. Активируй плагин **WP Telegram Bridge**

### 5. HTTPS для Telegram webhook (LocalTunnel)

Установи [LocalTunnel](https://localtunnel.me) и запусти:

```bash
npm install -g localtunnel
lt --port 8443 --local-host localhost
```

Скопируй выданный HTTPS-URL (например, `https://lucky-dog-42.loca.lt`) и используй его как webhook URL в настройках плагина:

```
https://lucky-dog-42.loca.lt/wp-json/wtb/v1/webhook
```

---

## Вариант B: LAMP (быстрый старт)

На этой машине уже установлен и работает стек **Apache 2.4 + PHP 8.4 + MariaDB 11.8**.

### 1. Создание БД

```bash
sudo mariadb -e "
CREATE DATABASE IF NOT EXISTS wordpress_wptb;
CREATE USER IF NOT EXISTS 'wpuser'@'localhost' IDENTIFIED BY 'ChangeMeWP_12345';
GRANT ALL PRIVILEGES ON wordpress_wptb.* TO 'wpuser'@'localhost';
FLUSH PRIVILEGES;
"
```

### 2. Установка WordPress

```bash
# Создадим директорию в домашней папке (не требует прав root на /var/www)
mkdir -p ~/wordpress-wptb
cd ~/wordpress-wptb

# Скачаем WordPress
wget https://wordpress.org/latest.tar.gz
tar -xzf latest.tar.gz --strip-components=1
rm latest.tar.gz

# Скопируем плагин
mkdir -p wp-content/plugins/wp-telegram-bridge
cp -r /home/user/IWE/wp-telegram-bridge/* wp-content/plugins/wp-telegram-bridge/

# Права
chmod -R 755 .
```

### 3. Виртуальный хост Apache + HTTPS

Создай конфиг:

```bash
sudo tee /etc/apache2/sites-available/wp-test.conf << 'EOF'
<VirtualHost *:80>
    ServerName wp-test.local
    DocumentRoot /home/user/wordpress-wptb
    <Directory /home/user/wordpress-wptb>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
EOF
```

Включи модуль rewrite и SSL:

```bash
sudo a2enmod rewrite ssl
sudo a2ensite wp-test
sudo systemctl reload apache2
```

Сгенерируй self-signed сертификат:

```bash
sudo openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
  -keyout /etc/ssl/private/wp-test.key \
  -out /etc/ssl/certs/wp-test.crt \
  -subj "/CN=wp-test.local"
```

Добавь HTTPS-хост:

```bash
sudo tee /etc/apache2/sites-available/wp-test-ssl.conf << 'EOF'
<IfModule mod_ssl.c>
<VirtualHost *:443>
    ServerName wp-test.local
    DocumentRoot /home/user/wordpress-wptb
    SSLEngine on
    SSLCertificateFile /etc/ssl/certs/wp-test.crt
    SSLCertificateKeyFile /etc/ssl/private/wp-test.key
    <Directory /home/user/wordpress-wptb>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
</IfModule>
EOF

sudo a2ensite wp-test-ssl
sudo systemctl reload apache2
```

Добавь в `/etc/hosts`:

```bash
echo "127.0.0.1 wp-test.local" | sudo tee -a /etc/hosts
```

### 4. Финальная настройка WordPress

1. Открой `http://wp-test.local`
2. Укажи БД: `wordpress_wptb`, пользователь: `wpuser`, пароль: `ChangeMeWP_12345`
3. Заверши установку
4. Войди в админку → Плагины → активируй **WP Telegram Bridge**

### 5. Webhook через LocalTunnel

```bash
npm install -g localtunnel
lt --port 80 --local-host wp-test.local
```

---

## Безопасность во время тестирования

1. **Сложные пароли:** используй уникальные пароли в `.env` и для админки WordPress.
2. **Не коммить секреты:** `.env` и `certs/` добавлены в `.gitignore` (проверь).
3. **Изоляция сети:** Docker-контейнеры работают в отдельной bridge-сети (`wptb_net`).
4. **Read-only монтирование плагина:** в `docker-compose.yml` плагин примонтирован как `:ro`.
5. **Rate limiting:** плагин имеет встроенный rate limit на AJAX-запросы.
6. **Debug:** включай `WP_DEBUG` только локально.

---

## Полезные команды

```bash
# Логи контейнеров
docker compose logs -f

# Перезапуск
docker compose restart

# Полный сброс (удалит ВСЕ данные)
docker compose down -v

# Резервная копия БД из контейнера
docker exec wptb-db mariadb-dump -u wpuser -pChangeMeWP_12345 wordpress > backup.sql
```

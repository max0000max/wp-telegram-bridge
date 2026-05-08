#!/usr/bin/env bash
set -euo pipefail

CERT_DIR="$(dirname "$0")/certs"
mkdir -p "$CERT_DIR"

openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
  -keyout "$CERT_DIR/key.pem" \
  -out "$CERT_DIR/cert.pem" \
  -subj "/C=RU/ST=Local/L=Local/O=WP-Telegram-Bridge/OU=Dev/CN=wp-test.local"

echo "✅ SSL сертификаты созданы в $CERT_DIR"
echo "   cert.pem  — публичный ключ"
echo "   key.pem   — приватный ключ"
echo ""
echo "⚠️  Важно: Telegram webhook НЕ принимает самоподписанные сертификаты."
echo "   Для тестирования webhook используй LocalTunnel:"
echo "   npm install -g localtunnel"
echo "   lt --port 8443 --local-host localhost"

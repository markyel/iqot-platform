#!/bin/bash

# ============================================
# n8n - Установка на том же сервере
# ============================================

set -e

echo "🔄 Установка n8n"
echo "================"

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

N8N_PORT=5678
N8N_DOMAIN="n8n.iqot.ai"  # Поддомен для n8n

echo -e "${YELLOW}Шаг 1: Установка n8n через npm${NC}"
npm install -g n8n

echo -e "${YELLOW}Шаг 2: Создание systemd сервиса${NC}"
cat > /etc/systemd/system/n8n.service << 'SERVICE'
[Unit]
Description=n8n - Workflow Automation
After=network.target

[Service]
Type=simple
User=www-data
Environment="N8N_PORT=5678"
Environment="N8N_PROTOCOL=https"
Environment="N8N_HOST=N8N_DOMAIN_PLACEHOLDER"
Environment="WEBHOOK_URL=https://N8N_DOMAIN_PLACEHOLDER/"
Environment="GENERIC_TIMEZONE=Europe/Moscow"
Environment="N8N_BASIC_AUTH_ACTIVE=true"
Environment="N8N_BASIC_AUTH_USER=admin"
Environment="N8N_BASIC_AUTH_PASSWORD=CHANGE_THIS_PASSWORD"
ExecStart=/usr/bin/n8n
Restart=on-failure
RestartSec=5

[Install]
WantedBy=multi-user.target
SERVICE

sed -i "s/N8N_DOMAIN_PLACEHOLDER/${N8N_DOMAIN}/g" /etc/systemd/system/n8n.service

echo -e "${YELLOW}Шаг 3: Настройка Nginx для n8n${NC}"
cat > /etc/nginx/sites-available/n8n << 'NGINX'
server {
    listen 80;
    server_name N8N_DOMAIN_PLACEHOLDER;

    location / {
        proxy_pass http://127.0.0.1:5678;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_buffering off;
        proxy_cache off;
        chunked_transfer_encoding off;
    }
}
NGINX

sed -i "s/N8N_DOMAIN_PLACEHOLDER/${N8N_DOMAIN}/g" /etc/nginx/sites-available/n8n
ln -sf /etc/nginx/sites-available/n8n /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx

echo -e "${YELLOW}Шаг 4: Запуск n8n${NC}"
systemctl daemon-reload
systemctl enable n8n
systemctl start n8n

echo ""
echo -e "${GREEN}✓ n8n установлен!${NC}"
echo ""
echo "Доступ: http://${N8N_DOMAIN}:${N8N_PORT}"
echo ""
echo "⚠️  ВАЖНО: Измените пароль в /etc/systemd/system/n8n.service"
echo "   После изменения выполните:"
echo "   systemctl daemon-reload && systemctl restart n8n"
echo ""
echo "SSL сертификат:"
echo "   certbot --nginx -d ${N8N_DOMAIN}"

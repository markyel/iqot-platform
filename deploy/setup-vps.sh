#!/bin/bash

# ============================================
# IQOT Platform - Скрипт развёртывания
# Для Ubuntu 24.04 (Beget VPS)
# ============================================

set -e

echo "🚀 IQOT Platform - Установка"
echo "=============================="

# Цвета для вывода
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

# Переменные (измените под себя)
DOMAIN="iqot.ai"
DB_NAME="iqot"
DB_USER="iqot_user"
DB_PASS="$(openssl rand -base64 16)"
APP_DIR="/var/www/iqot"

echo -e "${YELLOW}Шаг 1: Обновление системы${NC}"
apt update && apt upgrade -y

echo -e "${YELLOW}Шаг 2: Установка PHP 8.3 и расширений${NC}"
apt install -y software-properties-common
add-apt-repository -y ppa:ondrej/php
apt update
apt install -y php8.3 php8.3-fpm php8.3-mysql php8.3-mbstring php8.3-xml php8.3-bcmath php8.3-curl php8.3-zip php8.3-gd php8.3-intl php8.3-redis

echo -e "${YELLOW}Шаг 3: Установка MySQL${NC}"
apt install -y mysql-server
systemctl enable mysql
systemctl start mysql

# Создание базы данных
mysql -e "CREATE DATABASE IF NOT EXISTS ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';"
mysql -e "GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost';"
mysql -e "FLUSH PRIVILEGES;"

echo -e "${GREEN}✓ База данных создана${NC}"
echo "  Имя: ${DB_NAME}"
echo "  Пользователь: ${DB_USER}"
echo "  Пароль: ${DB_PASS}"

echo -e "${YELLOW}Шаг 4: Установка Nginx${NC}"
apt install -y nginx
systemctl enable nginx

echo -e "${YELLOW}Шаг 5: Установка Composer${NC}"
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

echo -e "${YELLOW}Шаг 6: Установка Node.js 20${NC}"
curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
apt install -y nodejs

echo -e "${YELLOW}Шаг 7: Создание директории проекта${NC}"
mkdir -p ${APP_DIR}
chown -R www-data:www-data ${APP_DIR}

echo -e "${YELLOW}Шаг 8: Настройка Nginx${NC}"
cat > /etc/nginx/sites-available/iqot << 'NGINX'
server {
    listen 80;
    server_name DOMAIN_PLACEHOLDER www.DOMAIN_PLACEHOLDER;
    root /var/www/iqot/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;
    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    # Gzip
    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_types text/plain text/css application/json application/javascript text/xml application/xml;
}
NGINX

sed -i "s/DOMAIN_PLACEHOLDER/${DOMAIN}/g" /etc/nginx/sites-available/iqot
ln -sf /etc/nginx/sites-available/iqot /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default
nginx -t && systemctl reload nginx

echo -e "${YELLOW}Шаг 9: Настройка PHP-FPM${NC}"
sed -i 's/;cgi.fix_pathinfo=1/cgi.fix_pathinfo=0/' /etc/php/8.3/fpm/php.ini
sed -i 's/upload_max_filesize = 2M/upload_max_filesize = 64M/' /etc/php/8.3/fpm/php.ini
sed -i 's/post_max_size = 8M/post_max_size = 64M/' /etc/php/8.3/fpm/php.ini
systemctl restart php8.3-fpm

echo -e "${YELLOW}Шаг 10: Установка Certbot (SSL)${NC}"
apt install -y certbot python3-certbot-nginx

echo ""
echo -e "${GREEN}============================================${NC}"
echo -e "${GREEN}✓ Базовая установка завершена!${NC}"
echo -e "${GREEN}============================================${NC}"
echo ""
echo "Следующие шаги:"
echo ""
echo "1. Загрузите код проекта в ${APP_DIR}:"
echo "   cd ${APP_DIR}"
echo "   # скопируйте файлы проекта"
echo ""
echo "2. Установите зависимости:"
echo "   cd ${APP_DIR}"
echo "   composer install --no-dev --optimize-autoloader"
echo "   npm install && npm run build"
echo ""
echo "3. Настройте .env:"
echo "   cp .env.example .env"
echo "   php artisan key:generate"
echo "   # Отредактируйте .env:"
echo "   #   DB_DATABASE=${DB_NAME}"
echo "   #   DB_USERNAME=${DB_USER}"
echo "   #   DB_PASSWORD=${DB_PASS}"
echo ""
echo "4. Запустите миграции:"
echo "   php artisan migrate"
echo "   php artisan storage:link"
echo ""
echo "5. Создайте админа:"
echo "   php artisan make:filament-user"
echo ""
echo "6. Права доступа:"
echo "   chown -R www-data:www-data ${APP_DIR}"
echo "   chmod -R 775 ${APP_DIR}/storage ${APP_DIR}/bootstrap/cache"
echo ""
echo "7. SSL сертификат (после настройки DNS):"
echo "   certbot --nginx -d ${DOMAIN} -d www.${DOMAIN}"
echo ""
echo -e "${YELLOW}Данные для подключения к БД:${NC}"
echo "   Host: localhost"
echo "   Database: ${DB_NAME}"
echo "   Username: ${DB_USER}"
echo "   Password: ${DB_PASS}"
echo ""
echo "Сохраните эти данные!"

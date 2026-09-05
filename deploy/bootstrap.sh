#!/usr/bin/env bash
# Подготовка чистого Ubuntu 24.04 под «Слайдушу». Запускать от root:
#   bash bootstrap.sh
#
# Ставит систему целиком: swap, файрвол, nginx, PHP 8.4, Node, Postgres,
# supervisor, библиотеки для Chrome. Приложение НЕ разворачивает —
# это следующий шаг, руками, по DEPLOY.md.
set -euo pipefail

if [ "$(id -u)" -ne 0 ]; then
    echo "Запускать от root" >&2
    exit 1
fi

step() { echo; echo "── $* ─────────────────────────"; }

step "Swap 2 ГБ"
# Сборка фронта берёт больше гигабайта; без swap система под нагрузкой
# убивает воркер вместо того, чтобы притормозить
if ! swapon --show | grep -q /swapfile; then
    fallocate -l 2G /swapfile
    chmod 600 /swapfile
    mkswap /swapfile
    swapon /swapfile
    grep -q '^/swapfile' /etc/fstab || echo '/swapfile none swap sw 0 0' >> /etc/fstab
else
    echo "уже есть"
fi

step "Обновление системы"
export DEBIAN_FRONTEND=noninteractive
apt update && apt upgrade -y

step "Базовые пакеты"
apt install -y nginx postgresql supervisor git unzip curl ca-certificates \
    software-properties-common certbot python3-certbot-nginx

step "PHP 8.4"
add-apt-repository -y ppa:ondrej/php
apt update
apt install -y php8.4-fpm php8.4-cli php8.4-pgsql php8.4-mbstring \
    php8.4-xml php8.4-curl php8.4-zip php8.4-gd php8.4-intl php8.4-bcmath

step "Node 22"
curl -fsSL https://deb.nodesource.com/setup_22.x | bash -
apt install -y nodejs

step "Composer"
if [ ! -x /usr/local/bin/composer ]; then
    curl -sS https://getcomposer.org/installer | php -- \
        --install-dir=/usr/local/bin --filename=composer
else
    echo "уже есть"
fi

step "Библиотеки для Chrome"
# Сам браузер поставим из проекта: npx puppeteer browsers install chrome-headless-shell
apt install -y libnss3 libatk1.0-0t64 libatk-bridge2.0-0t64 libcups2t64 libdrm2 \
    libxkbcommon0 libxcomposite1 libxdamage1 libxfixes3 libxrandr2 libgbm1 \
    libasound2t64 libpango-1.0-0 libcairo2 fonts-liberation

step "Файрвол"
ufw allow OpenSSH
ufw allow 'Nginx Full'
ufw --force enable

step "Проверка версий"
php -v | head -1
node -v
composer -V
psql --version
nginx -v

echo
echo "Готово. Дальше — база и код, шаги 5–9 в DEPLOY.md."

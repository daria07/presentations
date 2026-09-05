#!/usr/bin/env bash
# Деплой: запускать на сервере из /var/www/slaidusha под пользователем deploy.
#   bash deploy/deploy.sh
set -euo pipefail

cd "$(dirname "$0")/.."

echo "→ Останавливаем приём запросов"
php artisan down --retry=15 || true
trap 'php artisan up || true' EXIT

echo "→ Забираем код"
git pull --ff-only

echo "→ PHP-зависимости"
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

echo "→ Фронт"
npm ci
npm run build

echo "→ Миграции"
php artisan migrate --force

echo "→ Кэши"
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

echo "→ Перезапускаем воркер"
# queue:restart только просит воркер выйти; поднимает его обратно supervisor
php artisan queue:restart

echo "Готово"

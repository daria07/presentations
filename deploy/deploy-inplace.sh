#!/usr/bin/env bash
# СТАРАЯ выкладка — в рабочую папку, с «техническими работами».
# Нужна только до перевода сервера на версии (deploy/migrate-to-releases.sh):
# автодеплой вызывает её, пока на сервере нет /var/www/slaidusha/repo.
# После перевода не используется, её можно удалить.
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

#!/usr/bin/env bash
# Деплой без простоя: каждая версия собирается в своей папке, а сайт
# переключается на неё одной ссылкой, когда всё готово.
#
#   /var/www/slaidusha/
#     repo/               git-клон, отсюда берётся код и этот скрипт
#     releases/<версия>/  собранные версии, последние KEEP штук
#     shared/.env         общий для всех версий
#     shared/storage/     общий: презентации, логи, сессии
#     current -> releases/<версия>   на что смотрят nginx и воркер
#
# Запускать под deploy, после git pull в repo:
#   cd /var/www/slaidusha/repo && git pull --ff-only && bash deploy/deploy.sh
#
# Пока версия собирается, сайт работает на старой. Миграции идут до
# переключения, при старом коде — поэтому они должны быть совместимы
# с ним: добавлять колонки можно, переименовывать и удалять — нет.
#
# Служебные режимы (их зовёт migrate-to-releases.sh):
#   --build-only        собрать версию, не включая; печатает её имя
#   --activate <имя>    включить уже собранную версию
set -euo pipefail

BASE="$(cd "$(dirname "$0")/../.." && pwd)"
REPO="$BASE/repo"
KEEP=3

# Включает версию: миграции, кэши, переключение ссылки, воркеры
activate() {
    local name=$1
    cd "$BASE/releases/$name"

    echo "→ Миграции"
    php artisan migrate --force

    echo "→ Кэши"
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan event:cache

    echo "→ Переключаем сайт на $name"
    # Ссылка относительная: папку проекта можно переименовать.
    # mv -T заменяет ссылку атомарно — нет мгновения, когда её нет
    ln -sfn "releases/$name" "$BASE/current.new"
    mv -Tf "$BASE/current.new" "$BASE/current"

    echo "→ Перезапускаем воркер"
    # Воркер доделает текущую генерацию и поднимется уже на новой версии
    php artisan queue:restart

    echo "→ Убираем старые версии"
    # Имя начинается с даты: по нему и сортируем, новые сверху.
    # Текущую не трогаем, даже если после отката она не самая новая
    ls -1 "$BASE/releases" | sort -r | tail -n +$((KEEP + 1)) \
        | grep -vx "$name" | sed "s|^|$BASE/releases/|" | xargs -r rm -rf

    echo "Готово: $name"
}

if [ "${1:-}" = "--activate" ]; then
    activate "${2:?имя версии}"
    exit 0
fi

NAME="$(date +%Y%m%d-%H%M%S)-$(git -C "$REPO" rev-parse --short HEAD)"
RELEASE="$BASE/releases/$NAME"

# Сборка не удалась — недоделанную папку убираем, сайт не тронут
trap '[ -d "$RELEASE" ] && [ "$(readlink "$BASE/current" 2>/dev/null)" != "releases/$NAME" ] && rm -rf "$RELEASE"' ERR

echo "→ Собираем $NAME"
mkdir -p "$RELEASE"
git -C "$REPO" archive HEAD | tar -x -C "$RELEASE"
cd "$RELEASE"

# Общие файлы — ссылками, относительными по той же причине
rm -rf storage
ln -s ../../shared/storage storage
ln -s ../../shared/.env .env

echo "→ PHP-зависимости"
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

echo "→ Фронт"
npm ci
npm run build

ln -sfn ../storage/app/public public/storage

if [ "${1:-}" = "--build-only" ]; then
    trap - ERR
    echo "$NAME"
    exit 0
fi

activate "$NAME"

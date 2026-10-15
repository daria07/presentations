#!/usr/bin/env bash
# Откат на предыдущую версию: сайт переключается обратно мгновенно.
#
#   bash /var/www/slaidusha/repo/deploy/rollback.sh            # на предыдущую
#   bash /var/www/slaidusha/repo/deploy/rollback.sh <версия>   # на конкретную
#   ls /var/www/slaidusha/releases                              # какие есть
#
# Миграции не откатываются: база остаётся как есть. Поэтому они
# и должны быть совместимы со старым кодом (см. deploy.sh).
set -euo pipefail

BASE="$(cd "$(dirname "$0")/../.." && pwd)"
CURRENT="$(basename "$(readlink "$BASE/current")")"

if [ -n "${1:-}" ]; then
    TARGET=$1
else
    # Ближайшая старше текущей; имя начинается с даты
    TARGET="$(ls -1 "$BASE/releases" | sort | awk -v c="$CURRENT" '$0 == c { print prev; exit } { prev = $0 }')"
fi

[ -n "$TARGET" ] && [ -d "$BASE/releases/$TARGET" ] || { echo "Нет такой версии: ${TARGET:-—}"; exit 1; }

echo "→ $CURRENT → $TARGET"
ln -sfn "releases/$TARGET" "$BASE/current.new"
mv -Tf "$BASE/current.new" "$BASE/current"

cd "$BASE/current"
php artisan queue:restart
echo "Готово. Вернуться: bash $0 $CURRENT"

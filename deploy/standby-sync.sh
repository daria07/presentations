#!/usr/bin/env bash
#
# Запасной сервер: забрать свежую копию с боевого. Запускать от root
# на ЗАПАСНОМ сервере; крон ставит standby-setup.sh, раз в 15 минут.
#
# Что забираем: код всех версий и общие файлы (/var/www/slaidusha
# целиком, без логов), браузер для PDF, сертификаты и базу. База —
# одной транзакцией: восстановление либо проходит целиком, либо копия
# остаётся прежней, полуразобранной она не бывает.
#
# На боевом (после failover.sh) скрипт ничего не делает: иначе он
# затёр бы новые данные старыми.
set -euo pipefail

APP=/var/www/slaidusha
OLD=${OLD:-$(cat /etc/slaidusha-standby-old 2>/dev/null || true)}
LOG=/var/log/slaidusha-standby.log
DUMP=/var/backups/slaidusha-standby.dump

: "${OLD:?Не задан адрес боевого сервера: /etc/slaidusha-standby-old}"

if [ -f "$APP/.active" ]; then
    echo "$(date '+%F %T') этот сервер боевой — синхронизация не нужна" >> "$LOG"
    exit 0
fi

# Два запуска одновременно не нужны: крон мог догнать долгий предыдущий
exec 9> /run/slaidusha-standby.lock
flock -n 9 || { echo "уже идёт"; exit 0; }

SSH="ssh -o BatchMode=yes -o ConnectTimeout=20 -o ServerAliveInterval=15"
started=$(date +%s)

# Код, версии, .env, презентации. Логи у каждого сервера свои
mkdir -p "$APP"
rsync -a --delete -e "$SSH" \
    --exclude '/.active' \
    --exclude '/shared/storage/logs/' \
    "root@$OLD:$APP/" "$APP/"

# Браузер для печати PDF — ставится в домашнюю папку deploy
mkdir -p /home/deploy/.cache
rsync -a --delete -e "$SSH" "root@$OLD:/home/deploy/.cache/puppeteer/" /home/deploy/.cache/puppeteer/ || true
chown -R deploy:deploy /home/deploy/.cache

# Сертификаты: продлевает их боевой, копия берёт свежие
rsync -a --delete -e "$SSH" "root@$OLD:/etc/letsencrypt/" /etc/letsencrypt/

# База: имя и пользователь — из того же .env, что у сайта
env_val() { grep -E "^$1=" "$APP/shared/.env" | tail -n1 | cut -d= -f2- | tr -d '"'"'"; }
DB=$(env_val DB_DATABASE)
DB_USER=$(env_val DB_USERNAME)

$SSH "root@$OLD" "sudo -u postgres pg_dump -Fc '$DB'" > "$DUMP.tmp"
mv "$DUMP.tmp" "$DUMP"
chmod 600 "$DUMP"

sudo -u postgres pg_restore --clean --if-exists --no-owner --role="$DB_USER" \
    --single-transaction --exit-on-error -d "$DB" < "$DUMP"

systemctl reload nginx

echo "$(date '+%F %T') копия обновлена за $(( $(date +%s) - started )) с" >> "$LOG"

#!/usr/bin/env bash
# Ежедневный бэкап: дамп базы и файлы презентаций.
# Ставится в крон от root:
#   0 4 * * * /var/www/slaidusha/deploy/backup.sh >> /var/log/slaidusha-backup.log 2>&1
set -euo pipefail

DEST=/var/backups/slaidusha
KEEP_DB=14      # дней хранения дампов базы
KEEP_FILES=7    # дней хранения архивов с PDF
DAY=$(date +%F)

mkdir -p "$DEST"

# База. Дамп идёт через gzip сразу, чтобы не занимать диск в два раза.
sudo -u postgres pg_dump slaidusha | gzip > "$DEST/db-$DAY.sql.gz"

# Файлы презентаций. Они восстановимы перегенерацией, но перегенерация
# стоит денег, поэтому дешевле хранить архив.
tar -czf "$DEST/files-$DAY.tar.gz" -C /var/www/slaidusha/storage app

# .env не в гите, а без него сервер не поднять — кладём рядом.
# Внутри ключи, поэтому права только для root.
cp /var/www/slaidusha/.env "$DEST/env-$DAY"
chmod 600 "$DEST/env-$DAY"

find "$DEST" -name 'db-*.sql.gz'    -mtime +$KEEP_DB    -delete
find "$DEST" -name 'env-*'          -mtime +$KEEP_DB    -delete
find "$DEST" -name 'files-*.tar.gz' -mtime +$KEEP_FILES -delete

echo "$(date '+%F %T') бэкап готов: $(du -sh "$DEST" | cut -f1) всего"

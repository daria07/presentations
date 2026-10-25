#!/usr/bin/env bash
#
# Запасной сервер «Слайдуши»: первая настройка. От root, на НОВОМ
# сервере, после deploy/bootstrap.sh:
#
#   OLD=155.212.209.205 bash standby-setup.sh
#
# До запуска ключ этого сервера должен лежать у боевого в
# /root/.ssh/authorized_keys — по нему копия ходит за данными.
#
# Что получится: точная копия сайта, база и файлы обновляются раз
# в 15 минут. Сайт на копии запущен, но воркер очереди — нет: он
# включается только при переключении (failover.sh), иначе две копии
# брались бы за одни и те же задачи.
set -euo pipefail

OLD=${OLD:?Укажи адрес боевого сервера: OLD=155.212.209.205 bash standby-setup.sh}
APP=/var/www/slaidusha
HERE=$(cd "$(dirname "$0")" && pwd)
SSH="ssh -o BatchMode=yes -o ConnectTimeout=20 -o StrictHostKeyChecking=accept-new"

step() { echo; echo "── $* ─────────────────────────"; }

step "Доступ к боевому серверу"
$SSH "root@$OLD" true || {
    echo "Нет доступа к root@$OLD по ключу. Добавь на боевом в /root/.ssh/authorized_keys:"
    cat /root/.ssh/id_ed25519.pub 2>/dev/null || echo "(ключа нет: ssh-keygen -t ed25519 -N '' -f /root/.ssh/id_ed25519)"
    exit 1
}
echo "$OLD" > /etc/slaidusha-standby-old

step "Пользователь deploy"
if ! id deploy >/dev/null 2>&1; then
    adduser --disabled-password --gecos "" deploy
    usermod -aG www-data deploy
fi
# Те же ключи, что на боевом: вход для автодеплоя с GitHub и доступ к репозиторию
rsync -a -e "$SSH" "root@$OLD:/home/deploy/.ssh/" /home/deploy/.ssh/
chown -R deploy:deploy /home/deploy/.ssh

step "База"
ENV=$($SSH "root@$OLD" "cat $APP/shared/.env")
env_val() { echo "$ENV" | grep -E "^$1=" | tail -n1 | cut -d= -f2- | tr -d '"'"'"; }
DB=$(env_val DB_DATABASE)
DB_USER=$(env_val DB_USERNAME)
DB_PASS=$(env_val DB_PASSWORD)

if ! sudo -u postgres psql -tAc "SELECT 1 FROM pg_roles WHERE rolname='$DB_USER'" | grep -q 1; then
    echo "CREATE ROLE \"$DB_USER\" LOGIN PASSWORD :'pw';" | sudo -u postgres psql -v pw="$DB_PASS"
fi
if ! sudo -u postgres psql -tAc "SELECT 1 FROM pg_database WHERE datname='$DB'" | grep -q 1; then
    sudo -u postgres createdb -O "$DB_USER" "$DB"
fi

step "Первая копия: код, файлы, сертификаты, база"
bash "$HERE/standby-sync.sh"

step "Nginx"
rsync -a -e "$SSH" "root@$OLD:/etc/nginx/sites-available/" /etc/nginx/sites-available/
ln -sfn /etc/nginx/sites-available/slaidusha /etc/nginx/sites-enabled/slaidusha
[ -f /etc/nginx/sites-available/default-deny ] && ln -sfn /etc/nginx/sites-available/default-deny /etc/nginx/sites-enabled/default-deny
rm -f /etc/nginx/sites-enabled/default
# Настройки SSL, которые certbot кладёт рядом с конфигом сайта
rsync -a -e "$SSH" "root@$OLD:/etc/letsencrypt/options-ssl-nginx.conf" /etc/letsencrypt/ 2>/dev/null || true
nginx -t && systemctl reload nginx

step "Крон: копия раз в 15 минут и журнал здоровья"
( crontab -l 2>/dev/null | grep -v -e standby-sync.sh -e health.sh || true
  echo "*/15 * * * * bash $APP/repo/deploy/standby-sync.sh >> /var/log/slaidusha-standby.log 2>&1"
  echo "* * * * * $APP/repo/deploy/health.sh"
) | crontab -

step "Проверка"
code=$(curl -s -o /dev/null -w '%{http_code}' --max-time 20 --resolve slaidusha.ru:443:127.0.0.1 https://slaidusha.ru/up || true)
echo "Сайт на копии отвечает: $code (нужно 200)"
echo
echo "Готово. Копия обновляется раз в 15 минут: tail /var/log/slaidusha-standby.log"
echo "Переключение при сбое: bash $APP/repo/deploy/failover.sh"

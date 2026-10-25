#!/usr/bin/env bash
#
# Переключение на запасной сервер. От root, на ЗАПАСНОМ:
#
#   bash /var/www/slaidusha/repo/deploy/failover.sh
#
# 1. Последний раз пробует забрать свежие данные с боевого — если тот
#    ещё отвечает (при сбое сети у хостинга серверы между собой часто
#    видят друг друга, хотя посетители до боевого не доходят).
# 2. Выключает синхронизацию: отныне этот сервер — боевой, и старые
#    данные не должны затереть новые.
# 3. Включает воркер очереди — без него презентации не собираются.
#
# Домен переключается руками, в DNS Бегета — скрипт напомнит.
set -euo pipefail

APP=/var/www/slaidusha
OLD=$(cat /etc/slaidusha-standby-old 2>/dev/null || echo "?")
HERE=$(cd "$(dirname "$0")" && pwd)

if [ -f "$APP/.active" ]; then
    echo "Этот сервер уже боевой."
    exit 0
fi

read -r -p "Переключить сайт на этот сервер? Синхронизация со старым выключится навсегда. [y/N] " yes
case "${yes,,}" in
    y|yes|д|да) ;;
    *) echo "Не переключаю."; exit 1 ;;
esac

echo "→ Забираем свежие данные со старого сервера (до 3 минут)…"
# Копия скрипта: синхронизация перезаписывает папку, из которой он лежит
cp "$HERE/standby-sync.sh" /tmp/standby-sync.sh
if timeout 180 bash /tmp/standby-sync.sh; then
    echo "  данные свежие"
else
    echo "  старый сервер не ответил — работаем с последней копией:"
    tail -n 1 /var/log/slaidusha-standby.log || true
fi

echo "→ Этот сервер теперь боевой"
touch "$APP/.active"
crontab -l 2>/dev/null | grep -v standby-sync.sh | crontab -

echo "→ Включаем воркер очереди"
cp "$APP/repo/deploy/worker.conf" /etc/supervisor/conf.d/slaidusha-worker.conf
supervisorctl reread && supervisorctl update
sleep 3
supervisorctl status

NEW=$(curl -s --max-time 5 https://ifconfig.me || hostname -I | awk '{print $1}')

cat <<MSG

Готово. Осталось руками:

1. DNS в Бегете: у slaidusha.ru и www.slaidusha.ru A-запись → $NEW
   Посетители начнут попадать сюда за время TTL записи.

2. Если старый сервер ($OLD) доступен — остановить на нём приём
   задач, чтобы данные не разъехались:
     ssh root@$OLD 'supervisorctl stop all; sudo -u deploy php $APP/current/artisan down'

3. В GitHub → Settings → Secrets → адрес сервера для автодеплоя → $NEW

4. На этом сервере продление сертификата: certbot renew --dry-run
MSG

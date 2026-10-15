#!/usr/bin/env bash
# Одноразовый перевод сервера на выкладку версиями (см. deploy.sh).
# Запускать от root из старой папки проекта:
#
#   cd /var/www/slaidusha && git pull --ff-only
#   bash deploy/migrate-to-releases.sh
#
# Пока собирается первая версия, сайт работает как обычно. Потом он
# уходит в «технические работы» примерно на полминуты: переносим
# storage, меняем папки местами, перенастраиваем nginx, воркер и крон.
# Если воркер в этот момент делает презентацию, он её доделает —
# это может занять до пяти минут.
#
# Старая папка остаётся рядом как /var/www/slaidusha.old. Пока она
# есть, всё можно вернуть (команды — в конце вывода). Через день-два
# её можно удалить.
set -euo pipefail

APP=/var/www/slaidusha
NEXT=/var/www/slaidusha.next
OLD=/var/www/slaidusha.old
U=deploy
NGINX=/etc/nginx/sites-available/slaidusha
WORKER=/etc/supervisor/conf.d/slaidusha-worker.conf
FPM=php8.4-fpm

step() { echo; echo "=== $* ==="; }
as_deploy() { sudo -u "$U" -H "$@"; }

# --- Проверки до каких-либо изменений ---------------------------------

[ "$(id -u)" = 0 ] || { echo "Запускать от root"; exit 1; }
[ -L "$APP/current" ] && { echo "Сервер уже переведён: $APP/current существует"; exit 0; }
[ -d "$APP/.git" ] || { echo "$APP — не git-клон, что-то не так"; exit 1; }
for p in "$NEXT" "$OLD"; do
    [ -e "$p" ] && { echo "$p уже существует — разберитесь с ним и запустите снова"; exit 1; }
done
command -v rsync >/dev/null || apt-get install -y -q rsync
[ -f "$NGINX" ] || { echo "Нет $NGINX"; exit 1; }
[ -f "$WORKER" ] || { echo "Нет $WORKER"; exit 1; }
if grep -q "^CHROME_PATH=.*$APP" "$APP/.env"; then
    echo "CHROME_PATH в .env указывает внутрь $APP — после переезда путь сломается. Поправьте и запустите снова"
    exit 1
fi

# --- 1. Каркас и первая версия, сайт работает ------------------------

step "1/7 Каркас $NEXT"
mkdir -p "$NEXT/releases" "$NEXT/shared"
chown -R "$U:$U" "$NEXT"

# Адрес GitHub читаем от deploy: от root git откажется работать в чужой
# папке («dubious ownership») и вернёт пустоту — и repo/ останется без origin
ORIGIN="$(as_deploy git -C "$APP" remote get-url origin)"
[ -n "$ORIGIN" ] || { echo "Не удалось прочитать origin в $APP"; exit 1; }
as_deploy git clone -q "$APP" "$NEXT/repo"
as_deploy git -C "$NEXT/repo" remote set-url origin "$ORIGIN"

cp -p "$APP/.env" "$NEXT/shared/.env"

step "2/7 Копия storage (сайт работает)"
rsync -a "$APP/storage/" "$NEXT/shared/storage/"

step "3/7 Собираем первую версию (сайт работает, пару минут)"
as_deploy bash "$NEXT/repo/deploy/deploy.sh" --build-only
NAME="$(ls -1 "$NEXT/releases" | sort | tail -n 1)"
[ -n "$NAME" ] && [ -d "$NEXT/releases/$NAME/vendor" ] || { echo "Сборка не удалась"; exit 1; }
echo "Собрана: $NAME"

# Конфиги правим заранее и проверяем, пока сайт работает: nginx и
# supervisor прочтут их только при перезагрузке, после переключения
cp -p "$NGINX" "$NGINX.before-releases"
cp -p "$WORKER" "$WORKER.before-releases"

sed -i "s|root $APP/public;|root $APP/current/public;|" "$NGINX"
grep -q "root $APP/current/public;" "$NGINX" || { cp -p "$NGINX.before-releases" "$NGINX"; echo "Не нашёл root $APP/public в $NGINX"; exit 1; }
nginx -t || { cp -p "$NGINX.before-releases" "$NGINX"; echo "nginx -t не прошёл, конфиг возвращён"; exit 1; }

sed -i \
    -e "s|$APP/artisan|$APP/current/artisan|" \
    -e "s|^directory=$APP\$|directory=$APP/current|" \
    -e "s|$APP/storage/logs|$APP/shared/storage/logs|" \
    "$WORKER"

# --- 2. Переключение, сайт на техработах -----------------------------

# С этого места сайт на техработах. Если что-то упадёт — подсказка,
# как вернуть всё назад
trap 'echo; echo "!!! Сбой. Вернуть как было:"; echo "  [ -d $OLD ] && mv $APP $NEXT && mv $OLD $APP"; echo "  cp $NGINX.before-releases $NGINX && systemctl reload nginx"; echo "  cp $WORKER.before-releases $WORKER && supervisorctl reread && supervisorctl update"; echo "  sudo -u $U php $APP/artisan up"' ERR

step "4/7 Технические работы: останавливаем приём и воркер"
as_deploy php "$APP/artisan" down --retry=15 || true
supervisorctl stop 'slaidusha-worker:*'

# Догоняем storage: всё, что успело появиться за время сборки.
# Метку техработ не переносим, иначе новый сайт откроется закрытым
rsync -a --delete --exclude framework/down "$APP/storage/" "$NEXT/shared/storage/"
cp -p "$APP/.env" "$NEXT/shared/.env"

step "5/7 Меняем папки местами"
mv "$APP" "$OLD"
mv "$NEXT" "$APP"

# Пути зашиты в кэш настроек — собираем его уже на новом месте
as_deploy bash "$APP/repo/deploy/deploy.sh" --activate "$NAME"

step "6/7 nginx, воркер, php-fpm, крон"
systemctl reload nginx

supervisorctl reread
supervisorctl update
supervisorctl start 'slaidusha-worker:*' || true

# Сбрасываем кэш путей и opcache у php-fpm
systemctl reload "$FPM"

for who in root "$U"; do
    if crontab -u "$who" -l >/dev/null 2>&1; then
        crontab -u "$who" -l \
            | sed -e "s|$APP/deploy/|$APP/repo/deploy/|g" -e "s|$APP/artisan|$APP/current/artisan|g" \
            | crontab -u "$who" -
    fi
done

trap - ERR

step "7/7 Проверка"
sleep 2
code=$(curl -sS -o /dev/null -w '%{http_code}' https://slaidusha.ru/up || true)
echo "https://slaidusha.ru/up → $code"
supervisorctl status 'slaidusha-worker:*' || true
echo "Крон root:"; crontab -l 2>/dev/null | grep slaidusha || echo "  (нет строк про slaidusha)"

cat <<DONE

Готово. Сайт работает из $APP/current → releases/$NAME
Дальше выкладки идут сами, без техработ.

Если что-то не так — вернуть как было:
  mv $APP $NEXT && mv $OLD $APP
  cp $NGINX.before-releases $NGINX && systemctl reload nginx
  cp $WORKER.before-releases $WORKER && supervisorctl reread && supervisorctl update
  (и в crontab -e вернуть пути $APP/deploy/…)

Всё работает — через день-два: rm -rf $OLD
DONE

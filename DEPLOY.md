# Деплой «Слайдуши» на VPS

Ubuntu 24.04, минимум 2 ГБ памяти (Chrome при печати PDF съедает около 400 МБ
на процесс), 20 ГБ диска. Всё ниже — от root, кроме шагов, где явно сказано
иначе.

## 1. DNS

У регистратора домена:

```
A    @      <IP сервера>
A    www    <IP сервера>
```

Дальше подождать до получаса и проверить: `dig +short slaidusha.ru`.
Пока DNS не разъехался, сертификат выпустить не получится.

## 2. Пользователь и доступ

```bash
adduser --disabled-password --gecos "" deploy
usermod -aG www-data deploy
mkdir -p /home/deploy/.ssh && cp ~/.ssh/authorized_keys /home/deploy/.ssh/
chown -R deploy:deploy /home/deploy/.ssh && chmod 700 /home/deploy/.ssh
```

В `/etc/ssh/sshd_config`: `PermitRootLogin no`, `PasswordAuthentication no`,
затем `systemctl restart ssh`. Не закрывай текущую сессию, пока не проверишь
вход под `deploy` из другого окна.

Файрвол:

```bash
ufw allow OpenSSH && ufw allow 'Nginx Full' && ufw enable
```

## 3. Пакеты

```bash
apt update && apt upgrade -y
apt install -y nginx postgresql supervisor git unzip curl \
  certbot python3-certbot-nginx

# PHP 8.4
add-apt-repository -y ppa:ondrej/php && apt update
apt install -y php8.4-fpm php8.4-cli php8.4-pgsql php8.4-mbstring \
  php8.4-xml php8.4-curl php8.4-zip php8.4-gd php8.4-intl php8.4-bcmath

# Node 22
curl -fsSL https://deb.nodesource.com/setup_22.x | bash - && apt install -y nodejs

# Composer
curl -sS https://getcomposer.org/installer | php -- \
  --install-dir=/usr/local/bin --filename=composer
```

## 4. Chrome для печати PDF

Browsershot запускает настоящий браузер. Сначала системные библиотеки:

```bash
apt install -y libnss3 libatk1.0-0 libatk-bridge2.0-0 libcups2 libdrm2 \
  libxkbcommon0 libxcomposite1 libxdamage1 libxfixes3 libxrandr2 \
  libgbm1 libasound2t64 libpango-1.0-0 libcairo2 fonts-liberation
```

Сам браузер ставится уже после `npm ci` (шаг 7), из папки проекта под
пользователем `deploy`:

```bash
npx puppeteer browsers install chrome-headless-shell
```

Отдельной командой — потому что в `.npmrc` стоит `ignore-scripts=true`,
и автоматически при установке пакетов браузер не скачается.

## 5. База

```bash
sudo -u postgres createuser slaidusha --pwprompt
sudo -u postgres createdb slaidusha --owner=slaidusha
```

Пароль сразу положи в `.env`, второй раз его не покажут.

## 6. Код

```bash
mkdir -p /var/www && cd /var/www
git clone <адрес репозитория> slaidusha
chown -R deploy:www-data slaidusha
cd slaidusha
chmod -R 775 storage bootstrap/cache
```

Дальше всё — под `deploy` (`su - deploy`), не под root: иначе кэши и логи
окажутся с чужими правами и воркер их не перезапишет.

## 7. Настройки

```bash
cp .env.example .env
php artisan key:generate
```

Что поменять в `.env` относительно локального:

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://slaidusha.ru

DB_DATABASE=slaidusha
DB_USERNAME=slaidusha
DB_PASSWORD=<пароль из шага 5>

QUEUE_CONNECTION=database
SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true

ANTHROPIC_API_KEY=<ключ>
ANTHROPIC_BASE_URL=https://cheapai.io

BILLING_PROVIDER=fake     # пока ЮKassa не подключена
```

`APP_DEBUG=false` — обязательно: иначе на странице ошибки видны ключи из
окружения.

Затем зависимости и сборка:

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
npx puppeteer browsers install chrome-headless-shell
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

## 7a. Права на storage

Приложение пишут два разных пользователя: `deploy` (artisan, воркер) и
`www-data` (PHP-FPM под nginx). Если файл создан одним, а прочитать или
дописать его нужно другому — получается глухой 500 без записи в журнал:
Laravel не может даже сообщить об ошибке, потому что не может писать в
собственный лог.

Поэтому setgid на папках — не украшение, а обязательный шаг:

```bash
chown -R deploy:www-data storage bootstrap/cache
find storage bootstrap/cache -type d -exec chmod 2775 {} \;
find storage bootstrap/cache -type f -exec chmod 664 {} \;
```

`2775` означает: всё новое внутри автоматически получает группу
`www-data`. Без этого права разъедутся снова после первой же генерации.

Проверка: `ls -l storage/logs/` — у файлов должна быть группа `www-data`
и права `-rw-rw-r--`.

## 8. Nginx и сертификат

```bash
cp deploy/nginx.conf /etc/nginx/sites-available/slaidusha
ln -s /etc/nginx/sites-available/slaidusha /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default
nginx -t && systemctl reload nginx

certbot --nginx -d slaidusha.ru -d www.slaidusha.ru
```

Конфиг в репозитории — только http, без ssl: иначе nginx не стартует,
пока сертификата нет, и certbot не сможет пройти проверку. Certbot сам
добавит блок на 443, скопирует туда настройки и поставит редирект с 80.
Продление он тоже настроит сам (таймер `certbot.timer`).

## 9. Очередь

```bash
cp deploy/worker.conf /etc/supervisor/conf.d/slaidusha-worker.conf
supervisorctl reread && supervisorctl update
supervisorctl status
```

Должно быть два процесса `slaidusha-worker:*` в состоянии RUNNING.

## 10. Почта

Сейчас `MAIL_MAILER=log` — письма о готовой презентации никуда не уходят.
Для домена подойдёт Яндекс 360 для бизнеса или почта Mail.ru: подтверждаешь
домен, создаёшь ящик `hello@slaidusha.ru`, дальше в `.env`:

```
MAIL_MAILER=smtp
MAIL_HOST=smtp.yandex.ru
MAIL_PORT=465
MAIL_ENCRYPTION=ssl
MAIL_USERNAME=hello@slaidusha.ru
MAIL_PASSWORD=<пароль приложения>
MAIL_FROM_ADDRESS=hello@slaidusha.ru
```

Без SPF и DKIM письма будут падать в спам — записи даёт почтовый провайдер,
добавляются в DNS домена.

## 10a. Библиотеки для Chrome

Если PDF не печатается, а в журнале `error while loading shared
libraries` — не хватает системных библиотек браузера (шаг 4). Проверить,
чего именно, можно так, под `deploy`:

```bash
CHROME=$(ls -d ~/.cache/puppeteer/chrome-headless-shell/*/chrome-headless-shell-linux64/chrome-headless-shell)
$CHROME --version
ldd $CHROME | grep 'not found'
```

## 11. Проверка после первого деплоя

- [ ] `https://slaidusha.ru` открывается, замок в адресной строке
- [ ] регистрация и вход работают, письмо о подтверждении приходит
- [ ] генерация презентации доходит до готовности (следить: `tail -f storage/logs/worker.log`)
- [ ] PDF открывается и скачивается
- [ ] публичная ссылка `/p/{токен}` работает из режима инкогнито
- [ ] `php artisan deck:list` не показывает зависших задач

## 12. Дальнейшие деплои

```bash
su - deploy && cd /var/www/slaidusha && bash deploy/deploy.sh
```

## 12a. Защита от ботов

Сканеры находят новый сайт за минуты и начинают перебирать `/.env`,
`/.git/config`, `/wp-login.php`. Две меры закрывают почти всё.

**Заглушка для чужих доменов.** Боты стучатся по IP и с посторонним
`Host` — без блока по умолчанию они попадают в наш сайт:

```bash
cp deploy/nginx-default.conf /etc/nginx/sites-available/default-deny
ln -sf /etc/nginx/sites-available/default-deny /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx
```

**fail2ban** — банит по IP тех, кто перебирает пароли по SSH или ищет
чужие файлы:

```bash
apt install -y fail2ban
cp deploy/fail2ban-nginx.conf /etc/fail2ban/jail.d/slaidusha.conf
systemctl enable --now fail2ban
fail2ban-client status
```

Посмотреть, кого поймали: `fail2ban-client status nginx-botsearch`.
Разбанить свой IP, если случайно попал: `fail2ban-client set sshd unbanip <IP>`.

## 13. Бэкапы

Скрипт `deploy/backup.sh` делает три вещи: дамп базы, архив файлов
презентаций и копию `.env` (его нет в гите, а без него сервер не
поднять). Дампы хранятся 14 дней, архивы файлов — 7.

Ставим в крон от root:

```bash
crontab -e
```

и добавляем строку:

```
0 4 * * * /var/www/slaidusha/deploy/backup.sh >> /var/log/slaidusha-backup.log 2>&1
```

Проверить сразу, не дожидаясь ночи:

```bash
/var/www/slaidusha/deploy/backup.sh
ls -lh /var/backups/slaidusha/
```

**Бэкап, который не проверяли восстановлением, — это не бэкап.**
Раз в пару месяцев стоит убедиться, что дамп разворачивается:

```bash
sudo -u postgres createdb slaidusha_check
gunzip -c /var/backups/slaidusha/db-<дата>.sql.gz | sudo -u postgres psql slaidusha_check
sudo -u postgres psql slaidusha_check -c 'select count(*) from presentations;'
sudo -u postgres dropdb slaidusha_check
```

И отдельно: копии лежат на том же сервере. От сбоя приложения и от
случайного `delete` они спасают, от потери самого сервера — нет.
Как только появятся платящие пользователи, стоит забирать `db-*.sql.gz`
куда-то ещё — хотя бы раз в неделю на свой компьютер:

```bash
scp root@slaidusha.ru:/var/backups/slaidusha/db-$(date +%F).sql.gz ~/backups/
```

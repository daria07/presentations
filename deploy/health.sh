#!/usr/bin/env bash
#
# Журнал здоровья сервера: раз в минуту одна строка.
#
#   * * * * * /var/www/slaidusha/repo/deploy/health.sh
#
# Зачем: когда сайт «периодически недоступен», надо понять, чья это
# беда. Если в минуту сбоя строка есть и сайт изнутри ответил быстро —
# сервер жив, проблема в сети между ним и человеком. Если строки нет,
# сайт ответил ошибкой или долго, а память на нуле — проблема у нас.
#
# Что пишем: нагрузку, свободную память, число процессов php-fpm и
# Chrome (он печатает PDF и ест память), и как сайт ответил изнутри —
# код и время. Запрос идёт на 127.0.0.1, мимо внешней сети, но через
# тот же nginx и php-fpm, что обслуживают посетителей.

LOG=/var/log/slaidusha-health.log
HOST=slaidusha.ru

ts=$(date '+%F %T')
load=$(cut -d' ' -f1-3 /proc/loadavg)
mem=$(awk '/MemAvailable/ {printf "%d", $2/1024}' /proc/meminfo)
swap=$(awk '/SwapTotal/ {t=$2} /SwapFree/ {f=$2} END {printf "%d", (t-f)/1024}' /proc/meminfo)
fpm=$(pgrep -c php-fpm || true)
chrome=$(pgrep -c -f 'chrom(e|ium)' || true)
conns=$(ss -Htn state established '( sport = :443 )' 2>/dev/null | wc -l)

site=$(curl -s -o /dev/null -w '%{http_code} %{time_total}s' --max-time 20 \
    --resolve "$HOST:443:127.0.0.1" "https://$HOST/up" || true)

echo "$ts load=${load// /,} mem_free=${mem}MB swap_used=${swap}MB fpm=$fpm chrome=$chrome https_conns=$conns site=${site:-нет ответа}" >> "$LOG"

# Журнал не растёт бесконечно: держим последние ~2 недели
if [ "$(wc -l < "$LOG")" -gt 21000 ]; then
    tail -n 20160 "$LOG" > "$LOG.tmp" && mv "$LOG.tmp" "$LOG"
fi

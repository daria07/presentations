#!/usr/bin/env bash
# Деплой с Mac одной командой:
#
#   bash deploy/remote.sh
#
# Заходит на сервер, забирает из GitHub последний main и запускает
# deploy/deploy.sh под пользователем deploy. Это вместо деплоя из
# GitHub Actions: подключения из-за рубежа до сервера (VDSina) не
# доходят, а с Mac до сервера и с сервера до GitHub — доходят.
#
# Сервер можно переопределить: SLAIDUSHA_HOST=1.2.3.4 bash deploy/remote.sh
set -euo pipefail

HOST="${SLAIDUSHA_HOST:-138.16.170.16}"

cd "$(dirname "$0")/.."

# Сервер берёт код из GitHub, а не с Mac: незапушенное не уедет
git fetch -q origin main
if [ -n "$(git log origin/main..HEAD --oneline 2>/dev/null)" ]; then
    echo "Есть незапушенные коммиты — сначала git push:"
    git log origin/main..HEAD --oneline
    exit 1
fi

echo "→ Деплоим $(git rev-parse --short origin/main) на $HOST"

ssh -o ServerAliveInterval=30 "root@$HOST" \
    'sudo -u deploy bash -c "cd /var/www/slaidusha/repo && git pull --ff-only && bash deploy/deploy.sh"'

code=$(curl -sS -o /dev/null -w '%{http_code}' --max-time 20 https://slaidusha.ru/up || true)
echo "→ slaidusha.ru/up ответил $code"

#!/usr/bin/env bash
# Démarre MailPilot : serveur web, file d'attente et planificateur (Ctrl+C pour tout arrêter).
set -euo pipefail
cd "$(dirname "$0")"

# Ajoute php/mailpilot.ini aux réglages PHP (taille d'upload, mémoire).
export PHP_INI_SCAN_DIR="${PHP_INI_SCAN_DIR:-}:$(pwd)/php"

if [ ! -f .env ]; then
    cp .env.example .env
    php artisan key:generate --force
    php artisan mailpilot:configure-env
fi

FIRST_RUN=0
if [ ! -f database/database.sqlite ]; then
    touch database/database.sqlite
    FIRST_RUN=1
fi

php artisan migrate --force
[ "$FIRST_RUN" = 1 ] && php artisan db:seed --force

if [ ! -f public/build/manifest.json ]; then
    npm install
    npm run build
fi

# Premier port libre à partir de 8000 (ou de MAILPILOT_PORT).
PORT=$(php -r '$p = (int) $argv[1]; while (@fsockopen("127.0.0.1", $p, $e, $s, 0.2)) { $p++; } echo $p;' "${MAILPILOT_PORT:-8000}")
URL="http://127.0.0.1:$PORT"

trap 'echo; echo "Arrêt de MailPilot..."; kill 0' INT TERM EXIT

php artisan serve --host=127.0.0.1 --port="$PORT" &
# Le worker s'arrête de lui-même après un gros import (seuil mémoire) : on le relance aussitôt.
(while true; do
    php artisan queue:work --queue=sends,default --tries=1 --timeout=600 --sleep=3 --memory=448
    sleep 1
done) &
php artisan schedule:work &

sleep 3
echo
echo "MailPilot est disponible sur $URL (Ctrl+C pour arrêter)"
if command -v open >/dev/null 2>&1; then open "$URL"
elif command -v xdg-open >/dev/null 2>&1; then xdg-open "$URL" >/dev/null 2>&1 || true
fi

wait

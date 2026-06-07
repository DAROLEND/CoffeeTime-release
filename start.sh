#!/bin/bash
set -e

DB_PORT="${DB_PORT:-3306}"
MAX_TRIES=30
TRIES=0

echo "[start] Waiting for MySQL at ${DB_HOST}:${DB_PORT}..."
until (echo > /dev/tcp/"$DB_HOST"/"$DB_PORT") 2>/dev/null; do
    TRIES=$((TRIES + 1))
    if [ "$TRIES" -ge "$MAX_TRIES" ]; then
        echo "[start] MySQL not reachable, starting Apache anyway."
        exec apache2-foreground
    fi
    echo "[start] Attempt ${TRIES}/${MAX_TRIES}..."
    sleep 2
done
echo "[start] MySQL TCP port is open."
sleep 3

MYSQL="mysql -h${DB_HOST} -P${DB_PORT} -u${DB_USER} -p${DB_PASS} --ssl=0"

echo "[start] Resetting database..."
$MYSQL -e "DROP DATABASE IF EXISTS \`${DB_NAME}\`; CREATE DATABASE \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;" \
    && echo "[start] Database reset." \
    || { echo "[start] Reset failed, trying import anyway."; }

echo "[start] Importing database..."
$MYSQL --force "${DB_NAME}" < /var/www/html/CoffeeTime.sql \
    && echo "[start] Database imported successfully." \
    || echo "[start] Import finished with warnings."

echo "[start] Verifying import..."
TABLE_COUNT=$($MYSQL -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${DB_NAME}';" 2>/dev/null || echo "0")
echo "[start] Tables in DB: ${TABLE_COUNT}"

echo "[start] Fixing Apache MPM..."
rm -f /etc/apache2/mods-enabled/mpm_event.conf \
      /etc/apache2/mods-enabled/mpm_event.load \
      /etc/apache2/mods-enabled/mpm_worker.conf \
      /etc/apache2/mods-enabled/mpm_worker.load
a2enmod mpm_prefork 2>/dev/null || true

exec apache2-foreground

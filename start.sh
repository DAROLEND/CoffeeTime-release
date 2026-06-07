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

echo "[start] Checking database state..."
USER_COUNT=$($MYSQL -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${DB_NAME}' AND table_name='users';" 2>/dev/null || echo "0")

if [ "$USER_COUNT" -eq "0" ]; then
    echo "[start] Database is empty — importing initial schema..."
    $MYSQL -e "CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;" \
        && echo "[start] Database ensured."
    $MYSQL --force "${DB_NAME}" < /var/www/html/CoffeeTime.sql \
        && echo "[start] Database imported successfully." \
        || echo "[start] Import finished with warnings."
else
    echo "[start] Database already has data — skipping import to preserve user data."
fi

echo "[start] Verifying tables..."
TABLE_COUNT=$($MYSQL -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${DB_NAME}';" 2>/dev/null || echo "0")
echo "[start] Tables in DB: ${TABLE_COUNT}"

echo "[start] Fixing Apache MPM..."
rm -f /etc/apache2/mods-enabled/mpm_event.conf \
      /etc/apache2/mods-enabled/mpm_event.load \
      /etc/apache2/mods-enabled/mpm_worker.conf \
      /etc/apache2/mods-enabled/mpm_worker.load
a2enmod mpm_prefork 2>/dev/null || true

exec apache2-foreground

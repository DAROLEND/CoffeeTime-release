#!/bin/bash
set -e

DB_PORT="${DB_PORT:-3306}"
MAX_TRIES=30
TRIES=0

echo "[start] Waiting for MySQL at ${DB_HOST}:${DB_PORT}..."
until mysqladmin ping -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" --silent 2>/dev/null; do
    TRIES=$((TRIES + 1))
    if [ "$TRIES" -ge "$MAX_TRIES" ]; then
        echo "[start] MySQL not available after ${MAX_TRIES} attempts, starting Apache anyway."
        exec apache2-foreground
    fi
    echo "[start] Attempt ${TRIES}/${MAX_TRIES}..."
    sleep 2
done
echo "[start] MySQL is ready."

TABLE_COUNT=$(mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" \
    -e "SHOW TABLES;" 2>/dev/null | wc -l)

if [ "$TABLE_COUNT" -lt 2 ]; then
    echo "[start] Importing database..."
    mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" \
        < /var/www/html/CoffeeTime.sql && echo "[start] Database imported." \
        || echo "[start] Import failed, continuing anyway."
else
    echo "[start] Database already initialized, skipping import."
fi

exec apache2-foreground

#!/bin/bash
set -e

DB_PORT="${DB_PORT:-3306}"
MAX_TRIES=30
TRIES=0

echo "[start] Waiting for MySQL at ${DB_HOST}:${DB_PORT}..."
until (echo > /dev/tcp/"$DB_HOST"/"$DB_PORT") 2>/dev/null; do
    TRIES=$((TRIES + 1))
    if [ "$TRIES" -ge "$MAX_TRIES" ]; then
        echo "[start] MySQL not reachable after ${MAX_TRIES} attempts, starting Apache anyway."
        exec apache2-foreground
    fi
    echo "[start] Attempt ${TRIES}/${MAX_TRIES}..."
    sleep 2
done
echo "[start] MySQL TCP port is open."

sleep 2

MYSQL_CMD="mysql -h${DB_HOST} -P${DB_PORT} -u${DB_USER} -p${DB_PASS} --ssl=0 ${DB_NAME}"

TABLE_COUNT=$($MYSQL_CMD -e "SHOW TABLES;" 2>/dev/null | wc -l || echo "0")

if [ "$TABLE_COUNT" -lt 2 ]; then
    echo "[start] Importing database..."
    $MYSQL_CMD < /var/www/html/CoffeeTime.sql \
        && echo "[start] Database imported." \
        || echo "[start] Import failed, continuing anyway."
else
    echo "[start] Database already initialized (${TABLE_COUNT} tables)."
fi

exec apache2-foreground

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
sleep 2

MYSQL="mysql -h${DB_HOST} -P${DB_PORT} -u${DB_USER} -p${DB_PASS} --ssl=0"

TABLE_COUNT=$($MYSQL "$DB_NAME" -e "SHOW TABLES;" 2>/dev/null | wc -l || echo "0")

if [ "$TABLE_COUNT" -lt 2 ]; then
    echo "[start] Importing database..."
    $MYSQL --force "$DB_NAME" < /var/www/html/CoffeeTime.sql \
        && echo "[start] Database imported." \
        || echo "[start] Import finished with warnings."
else
    echo "[start] Checking database integrity..."
    ORDER_ITEMS_OK=$($MYSQL "$DB_NAME" -e "SELECT 1 FROM order_items LIMIT 1;" 2>/dev/null | wc -l || echo "0")
    if [ "$ORDER_ITEMS_OK" -lt 1 ]; then
        echo "[start] Detected corrupted tables, reimporting..."
        $MYSQL -e "DROP DATABASE \`${DB_NAME}\`; CREATE DATABASE \`${DB_NAME}\`;" 2>/dev/null || true
        $MYSQL --force "$DB_NAME" < /var/www/html/CoffeeTime.sql \
            && echo "[start] Database reimported." \
            || echo "[start] Reimport finished with warnings."
    else
        echo "[start] Database OK (${TABLE_COUNT} tables)."
    fi
fi

echo "[start] Fixing Apache MPM..."
rm -f /etc/apache2/mods-enabled/mpm_event.conf \
      /etc/apache2/mods-enabled/mpm_event.load \
      /etc/apache2/mods-enabled/mpm_worker.conf \
      /etc/apache2/mods-enabled/mpm_worker.load
a2enmod mpm_prefork 2>/dev/null || true

exec apache2-foreground

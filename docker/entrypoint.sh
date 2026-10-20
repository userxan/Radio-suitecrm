#!/usr/bin/env bash
set -e

echo "[Entrypoint] Initializing Radio CRM web container..."

# 1. Wait for MariaDB to be reachable
echo "[Entrypoint] Waiting for MariaDB..."
MAX_TRIES=30
COUNT=0
while [ $COUNT -lt $MAX_TRIES ]; do
    if mariadb -h"${DB_HOST:-mariadb}" -P"${DB_PORT:-3306}" -u"${DB_USER:-suitecrm_user}" -p"${DB_PASS}" -e "SELECT 1;" >/dev/null 2>&1 || \
       mariadb -h"${DB_HOST:-mariadb}" -P"${DB_PORT:-3306}" -uroot -p"${DB_ROOT_PASS}" -e "SELECT 1;" >/dev/null 2>&1; then
        echo "[Entrypoint] MariaDB is ready."
        break
    fi
    COUNT=$((COUNT + 1))
    sleep 1
done

# 2. Ensure SuiteCRM configuration & site_url
if [ -f /opt/radio_crm_repo/scripts/ensure_suitecrm_config.sh ]; then
    bash /opt/radio_crm_repo/scripts/ensure_suitecrm_config.sh || true
fi

# 3. Synchronize admin user & password from .env to database
if [ -f /opt/radio_crm_repo/scripts/sync_admin_user.php ]; then
    php /opt/radio_crm_repo/scripts/sync_admin_user.php || true
fi

echo "[Entrypoint] Starting Apache web server..."
exec apachectl -D FOREGROUND

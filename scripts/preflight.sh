#!/usr/bin/env bash
set -euo pipefail

echo "============================================================"
echo "Radio CRM - Preflight Check"
echo "============================================================"
date
hostname -I

echo "[1/5] Checking Docker & containers..."
docker --version
docker compose version
docker ps --format "table {{.Names}}\t{{.Image}}\t{{.Status}}\t{{.Ports}}"

echo "[2/5] Checking SuiteCRM 8 installation root (/var/www/html)..."
if [[ ! -f /var/www/html/bin/console ]] || [[ ! -f /var/www/html/composer.json ]]; then
    echo "  + SuiteCRM 8.10.1 core files not found in /var/www/html."
    echo "  + Downloading and extracting official SuiteCRM 8.10.1 release..."
    if ! command -v unzip >/dev/null 2>&1; then
        apt-get update -qq && DEBIAN_FRONTEND=noninteractive apt-get install -y -qq unzip
    fi
    mkdir -p /var/www/html /tmp/suitecrm_dl
    curl -fsSL https://github.com/SuiteCRM/SuiteCRM-Core/releases/download/v8.10.1/SuiteCRM-8.10.1.zip -o /tmp/suitecrm_dl/suitecrm.zip
    unzip -q /tmp/suitecrm_dl/suitecrm.zip -d /var/www/html
    rm -rf /tmp/suitecrm_dl
    chown -R 33:33 /var/www/html
    chmod -R 775 /var/www/html
    echo "  + Restarting web container to mount newly extracted SuiteCRM files..."
    docker restart radio_crm_web >/dev/null 2>&1 || true
    sleep 3
fi

if [[ ! -f /var/www/html/bin/console ]] || [[ ! -f /var/www/html/composer.json ]]; then
    echo "ERROR: Failed to initialize SuiteCRM 8 in /var/www/html" >&2
    exit 1
fi
docker exec -u www-data radio_crm_web php /var/www/html/bin/console --version

echo "[3/5] Checking PHP & MariaDB inside containers..."
docker exec radio_crm_web php -v | head -2
docker exec radio_crm_mariadb mariadb --version
docker exec radio_crm_mariadb sh -c 'mariadb -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" -e "SHOW DATABASES LIKE \"$MARIADB_DATABASE\";"'

# If database has 0 tables, seed base SuiteCRM 8.10.1 schema
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
TABLE_COUNT="$(docker exec radio_crm_mariadb sh -c 'mariadb -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" "$MARIADB_DATABASE" -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = \"$MARIADB_DATABASE\";"' 2>/dev/null || echo "0")"
if [[ "${TABLE_COUNT}" =~ ^[0-9]+$ ]] && [[ "${TABLE_COUNT}" -eq 0 ]] && [[ -f "${REPO_DIR}/docker/suitecrm_base_db.sql.gz" ]]; then
    echo "  + Database suitecrm_db is empty (0 tables). Seeding base SuiteCRM 8.10.1 schema..."
    gunzip -c "${REPO_DIR}/docker/suitecrm_base_db.sql.gz" | docker exec -i radio_crm_mariadb sh -c 'mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" "$MARIADB_DATABASE"'
    echo "  + Base database schema seeded successfully."
fi

# Ensure configuration, config.php, and installer locks are in place before HTTP check
if [ -f "${REPO_DIR}/scripts/ensure_suitecrm_config.sh" ]; then
    bash "${REPO_DIR}/scripts/ensure_suitecrm_config.sh"
fi

echo "[4/5] Checking Apache config & local HTTP response..."
docker exec radio_crm_web apachectl configtest
curl -sI http://127.0.0.1 | head -5

echo "[5/5] Checking Myriad export & backup directories..."
mkdir -p /srv/myriad_exports/myriad_daily_logs /srv/radio_crm_backups
chown -R 33:33 /srv/myriad_exports
ls -ld /srv/myriad_exports /srv/myriad_exports/myriad_daily_logs /srv/radio_crm_backups

echo "Preflight checks PASSED."

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
    echo "ERROR: /var/www/html is missing bin/console or composer.json" >&2
    exit 1
fi
docker exec -u www-data radio_crm_web php /var/www/html/bin/console --version

echo "[3/5] Checking PHP & MariaDB inside containers..."
docker exec radio_crm_web php -v | head -2
docker exec radio_crm_mariadb mariadb --version
docker exec radio_crm_mariadb sh -c 'mariadb -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" -e "SHOW DATABASES LIKE \"$MARIADB_DATABASE\";"'

echo "[4/5] Checking Apache config & local HTTP response..."
docker exec radio_crm_web apachectl configtest
curl -sI http://127.0.0.1 | head -5

echo "[5/5] Checking Myriad export & backup directories..."
mkdir -p /srv/myriad_exports/myriad_daily_logs /srv/radio_crm_backups
chown -R 33:33 /srv/myriad_exports
ls -ld /srv/myriad_exports /srv/myriad_exports/myriad_daily_logs /srv/radio_crm_backups

echo "Preflight checks PASSED."

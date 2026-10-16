#!/usr/bin/env bash
set -euo pipefail

BACKUP_ROOT="/srv/radio_crm_backups"
TIMESTAMP="$(date +%Y%m%d_%H%M%S)"
LABEL="${1:-checkpoint}"
SNAPSHOT_DIR="${BACKUP_ROOT}/radio_crm_backup_${TIMESTAMP}_${LABEL}"

mkdir -p "${SNAPSHOT_DIR}"

echo "[Backup] Creating snapshot at ${SNAPSHOT_DIR} ..."

# 1. Record versions and environment metadata
{
    echo "timestamp=${TIMESTAMP}"
    echo "label=${LABEL}"
    docker exec radio_crm_web php -v | head -1
    docker exec radio_crm_mariadb mariadb --version
    docker exec -u www-data radio_crm_web php /var/www/html/bin/console --version 2>/dev/null || true
} > "${SNAPSHOT_DIR}/versions.txt"

# 2. Dump MariaDB suitecrm_db to compressed SQL
echo "[Backup] Dumping database suitecrm_db ..."
docker exec radio_crm_mariadb sh -c 'mariadb-dump -uroot -p"$MARIADB_ROOT_PASSWORD" --single-transaction --routines --triggers "$MARIADB_DATABASE"' \
    | gzip -c > "${SNAPSHOT_DIR}/suitecrm_db.sql.gz"

# 3. Archive custom files, RT_RadioCampaigns module, defaultExt, and Myriad exports
echo "[Backup] Archiving application files ..."
tar -czf "${SNAPSHOT_DIR}/radio_crm_files.tar.gz" \
    -C / \
    var/www/html/public/legacy/config.php \
    var/www/html/public/legacy/custom \
    var/www/html/extensions/defaultExt \
    srv/myriad_exports \
    $( [ -d /var/www/html/public/legacy/modules/RT_RadioCampaigns ] && echo "var/www/html/public/legacy/modules/RT_RadioCampaigns" || true ) \
    2>/dev/null || true

# 4. Create restore_radio_crm.sh inside snapshot directory (Section 18.2)
cat > "${SNAPSHOT_DIR}/restore_radio_crm.sh" <<'EOF'
#!/usr/bin/env bash
set -euo pipefail
SNAP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

echo "Restoring files from ${SNAP_DIR}/radio_crm_files.tar.gz ..."
tar -xzf "${SNAP_DIR}/radio_crm_files.tar.gz" -C /
chown -R 33:33 /var/www/html/public/legacy/custom /var/www/html/extensions/defaultExt /srv/myriad_exports 2>/dev/null || true

if [[ "${1:-}" == "--with-db" ]]; then
    echo "Restoring database from ${SNAP_DIR}/suitecrm_db.sql.gz ..."
    gunzip -c "${SNAP_DIR}/suitecrm_db.sql.gz" | docker exec -i radio_crm_mariadb sh -c 'mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" "$MARIADB_DATABASE"'
fi

docker exec -u www-data radio_crm_web php /var/www/html/bin/console cache:clear
echo "Restore complete."
EOF
chmod +x "${SNAPSHOT_DIR}/restore_radio_crm.sh"

# 5. Update symbolic link latest_radio_crm_backup
ln -sfn "${SNAPSHOT_DIR}" "${BACKUP_ROOT}/latest_radio_crm_backup"

echo "[Backup] Completed successfully: ${SNAPSHOT_DIR}"
ls -lh "${SNAPSHOT_DIR}"

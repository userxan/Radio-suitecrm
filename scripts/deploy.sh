#!/usr/bin/env bash
set -euo pipefail

REPO_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
APP_ROOT="/var/www/html"

echo "============================================================"
echo "PUKfm Radio CRM - Deploying Customizations & Running Migrations"
echo "============================================================"

# 1. Pre-create all required target directories (Section 17)
mkdir -p \
    "${APP_ROOT}/public/legacy/modules/RT_RadioCampaigns/language" \
    "${APP_ROOT}/public/legacy/modules/RT_RadioCampaigns/metadata" \
    "${APP_ROOT}/public/legacy/custom/include/RadioCRM" \
    "${APP_ROOT}/public/legacy/custom/metadata" \
    "${APP_ROOT}/public/legacy/custom/modules/AOS_Quotes/metadata" \
    "${APP_ROOT}/public/legacy/custom/modules/AOS_Quotes/tpls" \
    "${APP_ROOT}/public/legacy/custom/modules/AOS_Quotes/views" \
    "${APP_ROOT}/public/legacy/custom/Extension/application/Ext/EntryPointRegistry" \
    "${APP_ROOT}/public/legacy/custom/Extension/application/Ext/Include" \
    "${APP_ROOT}/public/legacy/custom/Extension/application/Ext/Language" \
    "${APP_ROOT}/public/legacy/custom/Extension/application/Ext/TableDictionary" \
    "${APP_ROOT}/public/legacy/custom/Extension/modules/AOS_Quotes/Ext/Language" \
    "${APP_ROOT}/public/legacy/custom/Extension/modules/AOS_Quotes/Ext/LogicHooks" \
    "${APP_ROOT}/public/legacy/custom/Extension/modules/AOS_Quotes/Ext/Vardefs" \
    "${APP_ROOT}/extensions/defaultExt/config/modules/RT_RadioCampaigns/recordview/actions" \
    "${APP_ROOT}/extensions/defaultExt/modules/RT_RadioCampaigns/Process/Service/RecordActions" \
    "${APP_ROOT}/logs/legacy" \
    "/srv/myriad_exports/myriad_daily_logs" \
    "/srv/myriad_exports/myriad_reconciliation" \
    "/srv/radio_crm_backups"

ln -sfn "/srv/myriad_exports/myriad_reconciliation" "/srv/myriad_reconciliation"
docker exec radio_crm_web ln -sfn "/srv/myriad_exports/myriad_reconciliation" "/srv/myriad_reconciliation" || true

# 2. Sync source tree into /var/www/html
echo "[Deploy] Syncing src/ into ${APP_ROOT} ..."
rsync -a "${REPO_DIR}/src/" "${APP_ROOT}/"

# 3. Set ownership to www-data (UID 33)
chown -R 33:33 \
    "${APP_ROOT}/cache" \
    "${APP_ROOT}/public" \
    "${APP_ROOT}/extensions/defaultExt" \
    "${APP_ROOT}/logs" \
    "/srv/myriad_exports"
chmod -R 2775 "/srv/myriad_exports"

# 4. Run PHP syntax checks on all custom & migration PHP files
echo "[Deploy] Running PHP syntax checks..."
docker exec radio_crm_web bash -c '
    set -e
    for f in $(find /opt/radio_crm_repo/src /opt/radio_crm_repo/migrations -name "*.php"); do
        php -l "$f" >/dev/null
    done
'
echo "  = All PHP files passed syntax validation."

# 5. Execute numbered migrations 001..006 as www-data
echo "[Deploy] Executing database migrations and Quick Repair..."
for mig in \
    001_create_rt_radiocampaigns_tables.php \
    002_create_aos_quotes_schedule_columns.php \
    003_seed_radio_catalogue_products.php \
    004_professional_quote_pdf_template.php \
    005_radio_crm_market_enhancements.php \
    006_quick_repair_and_rebuild.php
do
    docker exec -u www-data radio_crm_web php "/opt/radio_crm_repo/migrations/${mig}"
done

# 6. Clear SuiteCRM 8 Symfony cache and ensure cache ownership
echo "[Deploy] Clearing SuiteCRM 8 Symfony cache..."
docker exec -u www-data radio_crm_web php /var/www/html/bin/console cache:clear
chown -R 33:33 "${APP_ROOT}/cache" "${APP_ROOT}/public/legacy/cache" "${APP_ROOT}/logs"
chmod -R 775 "${APP_ROOT}/cache" "${APP_ROOT}/public/legacy/cache"

echo "[Deploy] Deployment completed successfully."

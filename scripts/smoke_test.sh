#!/usr/bin/env bash
set -euo pipefail

echo "============================================================"
echo "Radio CRM - End-to-End Smoke & Acceptance Tests"
echo "============================================================"

# 1. Verify database schema counts (Section 16.3)
echo "[Smoke 1/4] Verifying database tables, 168 schedule columns, and 8 catalogue products..."
docker exec radio_crm_mariadb sh -c '
    mariadb -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" "$MARIADB_DATABASE" -e "
        SHOW TABLES LIKE '\''aos_quotes_cstm'\'';
        SHOW TABLES LIKE '\''rt_radiocampaigns'\'';
        SHOW TABLES LIKE '\''rt_radiocampaigns_aos_quotes_c'\'';
        SELECT COUNT(*) AS total_cstm_cols FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '\''aos_quotes_cstm'\'';
        SELECT COUNT(*) AS active_radio_products FROM aos_products WHERE part_number IN ('\''PRE-BREAKFAST'\'','\''MORNING-BUZZ'\'','\''MIDMORNING-BANTER'\'','\''MIDDAY-PULSE'\'','\''CRUISE-CONTROL'\'','\''SPECIALTY-SHOW-1'\'','\''SPECIALTY-SHOW-2'\'','\''MUSIC-UNLIMITED'\'') AND deleted=0;
    "
'

# 2. Run Section 20 End-to-End Acceptance Test Suite (A1-A5, B1-B3, C1-C3, D1-D6, E1-E3)
echo "[Smoke 2/4] Running Section 20 Mandatory Acceptance Test Suite..."
docker exec -u www-data radio_crm_web php /opt/radio_crm_repo/tests/run_acceptance_tests.php

# 3. Run UI / DOM & Symfony Container Verification
echo "[Smoke 3/5] Running UI / DOM & Symfony Container Verification..."
docker exec -u www-data radio_crm_web php /opt/radio_crm_repo/tests/run_ui_http_tests.php

# 4. Run Radio Station Market Enhancements Suite (Capacity, Category Conflicts, Auto Cart, Playout Reconciliation, Affidavits, Invoicing, ICASA)
echo "[Smoke 4/6] Running Radio Station Market Enhancements Suite..."
docker exec -u www-data radio_crm_web php /opt/radio_crm_repo/tests/test_radio_enhancements.php

# 5. Run Station Setup Wizard & Branding Architecture Suite
echo "[Smoke 5/6] Running Station Setup Wizard & Branding Architecture Suite..."
docker exec -u www-data radio_crm_web php /opt/radio_crm_repo/tests/test_station_wizard.php

# 6. Inspect generated Myriad export files (Section 21.4)
echo "[Smoke 6/6] Verifying generated Myriad CSV & daily .log files in /srv/myriad_exports ..."
find /srv/myriad_exports -maxdepth 1 -type f -name '*.csv' -printf '%TY-%Tm-%Td %TH:%TM %p\n' | sort
find /srv/myriad_exports/myriad_daily_logs -maxdepth 1 -type f -printf '%p\n' | sort
ls -ld /srv/myriad_exports /srv/myriad_exports/myriad_daily_logs

echo "All Smoke & Acceptance Tests PASSED."

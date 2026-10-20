#!/usr/bin/env bash
set -euo pipefail

REPO_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

echo "============================================================"
echo "Radio CRM - Master Installer & Verification Suite"
echo "============================================================"

chmod +x \
    "${REPO_DIR}/scripts/preflight.sh" \
    "${REPO_DIR}/scripts/backup.sh" \
    "${REPO_DIR}/scripts/deploy.sh" \
    "${REPO_DIR}/scripts/smoke_test.sh"

# 1. Preflight check
"${REPO_DIR}/scripts/preflight.sh"

# 2. Pre-deployment backup checkpoint
"${REPO_DIR}/scripts/backup.sh" pre_deploy

# 3. Deploy customizations, run numbered migrations, Quick Repair & cache clear
"${REPO_DIR}/scripts/deploy.sh"

# 4. Run mandatory end-to-end acceptance tests & UI verification
"${REPO_DIR}/scripts/smoke_test.sh"

# 5. Post-verification backup checkpoint
"${REPO_DIR}/scripts/backup.sh" verified_release

# 6. Purge test records and any demo data for a 100% clean production system
echo "[Purge] Leaving system 100% clean and fresh for production use..."
docker exec radio_crm_web php /opt/radio_crm_repo/scripts/purge_demo_data.php
docker exec radio_crm_web php /opt/radio_crm_repo/scripts/sync_admin_user.php

echo "============================================================"
echo "Radio CRM installation and verification COMPLETE!"
echo "============================================================"

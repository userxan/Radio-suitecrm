#!/usr/bin/env bash
set -euo pipefail

REPO_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

echo "============================================================"
echo "PUKfm Radio CRM - Master Installer & Verification Suite"
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

echo "============================================================"
echo "PUKfm Radio CRM installation and verification COMPLETE!"
echo "============================================================"

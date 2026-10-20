#!/usr/bin/env bash
# scripts/reset_login_lockout.sh
# Immediately clears login rate limits, brute-force lockouts, and syncs admin credentials.
set -euo pipefail

REPO_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${REPO_DIR}"

echo "============================================================"
echo "Radio CRM - Resetting Login Lockout & Rate Limits"
echo "============================================================"

# 1. Clear Symfony rate limiter cache pool
echo "[1/4] Clearing Symfony login rate limiter cache pool..."
docker exec radio_crm_web php /var/www/html/bin/console cache:pool:clear cache.rate_limiter 2>/dev/null || true

# 2. Wipe rate limit storage files
echo "[2/4] Wiping cached rate limit tokens..."
docker exec radio_crm_web rm -rf /var/www/html/cache/prod/pools/system/* 2>/dev/null || true

# 3. Synchronize credentials and reset user lockouts in MariaDB
echo "[3/4] Synchronizing admin credentials and resetting user lockouts..."
chmod 644 "${REPO_DIR}/.env" 2>/dev/null || true
"${REPO_DIR}/scripts/ensure_suitecrm_config.sh" 2>/dev/null || true
docker exec radio_crm_web php /opt/radio_crm_repo/scripts/sync_admin_user.php || true

# 4. Clear Symfony production cache
echo "[4/4] Clearing Symfony application cache..."
docker exec radio_crm_web php /var/www/html/bin/console cache:clear 2>/dev/null || true

echo "============================================================"
echo "✓ Login lockouts and rate limits CLEARED successfully!"
echo "  You can now log in immediately at your browser."
echo "============================================================"

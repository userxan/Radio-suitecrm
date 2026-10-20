#!/usr/bin/env bash
set -euo pipefail

REPO_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
APP_ROOT="/var/www/html"
LEGACY_DIR="${APP_ROOT}/public/legacy"

# Pre-create application and legacy directories if needed
mkdir -p "${APP_ROOT}" "${LEGACY_DIR}"

# 1. Safely parse .env without naked 'source' (avoids bash errors on unquoted spaces e.g. "1 minute")
if [ -f "${REPO_DIR}/.env" ]; then
    chmod 644 "${REPO_DIR}/.env" 2>/dev/null || true
    # Auto-quote LOGIN_THROTTLING_INTERVAL if unquoted
    sed -i 's/^LOGIN_THROTTLING_INTERVAL=1 minute$/LOGIN_THROTTLING_INTERVAL="1 minute"/' "${REPO_DIR}/.env" 2>/dev/null || true

    while IFS= read -r line || [ -n "$line" ]; do
        # Strip leading and trailing whitespace
        line="$(echo "$line" | sed -e 's/^[[:space:]]*//' -e 's/[[:space:]]*$//')"
        [ -z "$line" ] && continue
        [[ "$line" =~ ^# ]] && continue

        if [[ "$line" =~ ^([A-Za-z_][A-Za-z0-9_]*)=(.*)$ ]]; then
            key="${BASH_REMATCH[1]}"
            val="${BASH_REMATCH[2]}"
            # Strip surrounding double or single quotes
            val="${val%\"}"
            val="${val#\"}"
            val="${val%\'}"
            val="${val#\'}"
            export "$key=$val" 2>/dev/null || true
        fi
    done < "${REPO_DIR}/.env"

    # Make .env readable to www-data and sync copy into APP_ROOT
    cp -f "${REPO_DIR}/.env" "${APP_ROOT}/.env.radiocrm" 2>/dev/null || true
    chmod 644 "${APP_ROOT}/.env.radiocrm" 2>/dev/null || true
    chown 33:33 "${APP_ROOT}/.env.radiocrm" 2>/dev/null || true
fi

DB_HOST="${DB_HOST:-mariadb}"
DB_PORT="${DB_PORT:-3306}"
DB_NAME="${DB_NAME:-suitecrm_db}"
DB_USER="${DB_USER:-suitecrm_user}"
DB_PASS="${DB_PASS:-ChangeMe_DB_Pass!}"
SUITECRM_SITE_URL="${SUITECRM_SITE_URL:-}"

# Auto-detect public or primary IP if SUITECRM_SITE_URL is not set or default
if [ -z "${SUITECRM_SITE_URL}" ] || [ "${SUITECRM_SITE_URL}" = "http://127.0.0.1" ] || [ "${SUITECRM_SITE_URL}" = "http://84.8.141.105" ]; then
    DETECTED_IP="$(curl -s4 --max-time 2 https://ifconfig.me 2>/dev/null || hostname -I | awk '{print $1}' || echo '')"
    if [ -n "${DETECTED_IP}" ]; then
        SUITECRM_SITE_URL="http://${DETECTED_IP}"
    else
        SUITECRM_SITE_URL="http://127.0.0.1"
    fi
fi

# 2. Prevent SuiteCRM 8 installer redirects: touch .installed_checked and .curl_check_main_page
touch "${APP_ROOT}/.installed_checked" "${APP_ROOT}/.curl_check_main_page"
chown 33:33 "${APP_ROOT}/.installed_checked" "${APP_ROOT}/.curl_check_main_page" 2>/dev/null || true
chmod 664 "${APP_ROOT}/.installed_checked" "${APP_ROOT}/.curl_check_main_page" 2>/dev/null || true

# 3. Ensure /var/www/html/.env.local exists for Symfony
if [ ! -f "${APP_ROOT}/.env.local" ]; then
    echo "[Config] Generating ${APP_ROOT}/.env.local for SuiteCRM 8..."
    ENCODED_PASS="$(python3 -c "import urllib.parse, sys; print(urllib.parse.quote(sys.argv[1]))" "${DB_PASS}" 2>/dev/null || echo "${DB_PASS}")"
    APP_SECRET="$(head -c 32 /dev/urandom | md5sum | head -c 32)"
    cat > "${APP_ROOT}/.env.local" <<EOF
DATABASE_URL="mysql://${DB_USER}:${ENCODED_PASS}@${DB_HOST}:${DB_PORT}/${DB_NAME}"
APP_SECRET=${APP_SECRET}
EOF
    chown 33:33 "${APP_ROOT}/.env.local"
    chmod 664 "${APP_ROOT}/.env.local"
fi

# 4. Ensure /var/www/html/public/legacy/config.php exists for Legacy SuiteCRM
NEED_CONFIG=0
if [ ! -f "${LEGACY_DIR}/config.php" ]; then
    NEED_CONFIG=1
elif ! grep -q "'db_name'" "${LEGACY_DIR}/config.php" 2>/dev/null; then
    NEED_CONFIG=1
fi

if [ "${NEED_CONFIG}" -eq 1 ] && [ -f "${REPO_DIR}/docker/config.php.template" ]; then
    echo "[Config] Generating ${LEGACY_DIR}/config.php from template..."
    UNIQUE_KEY="$(head -c 32 /dev/urandom | md5sum | head -c 32)"
    sed \
        -e "s|{{DB_HOST}}|${DB_HOST}|g" \
        -e "s|{{DB_PORT}}|${DB_PORT}|g" \
        -e "s|{{DB_NAME}}|${DB_NAME}|g" \
        -e "s|{{DB_USER}}|${DB_USER}|g" \
        -e "s|{{DB_PASS}}|${DB_PASS}|g" \
        -e "s|{{SUITECRM_SITE_URL}}|${SUITECRM_SITE_URL}|g" \
        -e "s|{{UNIQUE_KEY}}|${UNIQUE_KEY}|g" \
        "${REPO_DIR}/docker/config.php.template" > "${LEGACY_DIR}/config.php"
    chown 33:33 "${LEGACY_DIR}/config.php"
    chmod 664 "${LEGACY_DIR}/config.php"
elif [ -f "${LEGACY_DIR}/config.php" ]; then
    # Ensure installer is locked in existing config.php
    sed -i "s|'installer_locked' => false|'installer_locked' => true|g" "${LEGACY_DIR}/config.php" 2>/dev/null || true
    sed -i "s|'installed' => false|'installed' => true|g" "${LEGACY_DIR}/config.php" 2>/dev/null || true
    if [ -n "${SUITECRM_SITE_URL}" ]; then
        CURRENT_SITE_URL="$(grep "'site_url'" "${LEGACY_DIR}/config.php" 2>/dev/null | head -n 1 | sed -E "s/.*'site_url' => '([^']*)'.*/\1/" || echo '')"
        if [ -z "${CURRENT_SITE_URL}" ] || [ "${CURRENT_SITE_URL}" = "http://84.8.141.105" ] || [ "${CURRENT_SITE_URL}" = "http://127.0.0.1" ]; then
            echo "[Config] Updating site_url in ${LEGACY_DIR}/config.php to ${SUITECRM_SITE_URL} ..."
            sed -i "s|'site_url' => '.*'|'site_url' => '${SUITECRM_SITE_URL}'|g" "${LEGACY_DIR}/config.php" 2>/dev/null || true
        fi
    fi
fi

# 5. Ensure config_override.php locks installer and handles dynamic site_url
cat > "${LEGACY_DIR}/config_override.php" <<'EOF'
<?php
// Auto-generated Radio CRM configuration overrides
$sugar_config['installer_locked'] = true;
$sugar_config['installed_locked'] = true;
$sugar_config['installed'] = true;
$sugar_config['upload_maxsize'] = 67108864;

// Dynamic site_url for multi-IP/domain access
if (isset($_SERVER['HTTP_HOST']) && !empty($_SERVER['HTTP_HOST'])) {
    $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $sugar_config['site_url'] = $proto . $_SERVER['HTTP_HOST'];
}
EOF
chown 33:33 "${LEGACY_DIR}/config_override.php" 2>/dev/null || true
chmod 664 "${LEGACY_DIR}/config_override.php" 2>/dev/null || true

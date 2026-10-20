#!/usr/bin/env bash
# ==============================================================================
# Radio CRM - 1-Line Automated Server Installer
# Usage on fresh Ubuntu / Debian server:
#   curl -fsSL https://raw.githubusercontent.com/userxan/Radio-suitecrm/master/quick_install.sh | sudo bash
# ==============================================================================
set -euo pipefail

TARGET_DIR="${1:-/opt/suitecrm}"
REPO_URL="https://github.com/userxan/Radio-suitecrm.git"
TARBALL_URL="https://github.com/userxan/Radio-suitecrm/archive/refs/heads/master.tar.gz"

echo "=================================================================="
echo "  Radio CRM — Automated Fresh Server Installer"
echo "  Target Directory: ${TARGET_DIR}"
echo "=================================================================="

# 1. Require root / sudo
if [ "$(id -u)" -ne 0 ]; then
    echo "ERROR: Please run as root or with sudo:"
    echo "  curl -fsSL https://raw.githubusercontent.com/userxan/Radio-suitecrm/master/quick_install.sh | sudo bash"
    exit 1
fi

# 2. Install base prerequisites (curl, git, tar, ca-certificates, unzip)
echo "[1/6] Installing system prerequisites..."
apt-get update -qq
DEBIAN_FRONTEND=noninteractive apt-get install -y -qq curl git tar unzip ca-certificates jq rsync

# 3. Ensure Docker and Docker Compose plugin are installed
if ! command -v docker >/dev/null 2>&1; then
    echo "[2/6] Docker not found. Installing official Docker Engine..."
    curl -fsSL https://get.docker.com | sh
    systemctl enable --now docker
else
    echo "[2/6] Docker already installed ($(docker --version))."
fi

if ! docker compose version >/dev/null 2>&1; then
    echo "  + Installing Docker Compose plugin..."
    apt-get update -qq && DEBIAN_FRONTEND=noninteractive apt-get install -y -qq docker-compose-plugin
fi

# 4. Clone repository or download archive (Zero credentials required)
echo "[3/6] Fetching Radio CRM codebase into ${TARGET_DIR}..."
if [ -d "${TARGET_DIR}/.git" ]; then
    echo "  = Existing Git repository detected. Pulling latest master..."
    cd "${TARGET_DIR}"
    git pull origin master || true
else
    mkdir -p "${TARGET_DIR}"
    if git clone "${REPO_URL}" "${TARGET_DIR}" 2>/dev/null; then
        echo "  = Cloned repository via HTTPS (no username/password needed)."
    else
        echo "  = Git clone failed, falling back to direct archive download..."
        curl -fsSL "${TARBALL_URL}" | tar -xz -C "${TARGET_DIR}" --strip-components=1
    fi
fi

cd "${TARGET_DIR}"

# 5. Ensure SuiteCRM 8.10.1 core files exist in /var/www/html
if [ ! -f /var/www/html/bin/console ]; then
    echo "  + SuiteCRM 8.10.1 core not found in /var/www/html. Downloading official release..."
    mkdir -p /var/www/html /tmp/suitecrm_dl
    curl -fsSL https://github.com/SuiteCRM/SuiteCRM-Core/releases/download/v8.10.1/SuiteCRM-8.10.1.zip -o /tmp/suitecrm_dl/suitecrm.zip
    unzip -q /tmp/suitecrm_dl/suitecrm.zip -d /var/www/html
    rm -rf /tmp/suitecrm_dl
    chown -R 33:33 /var/www/html
    chmod -R 775 /var/www/html
fi

mkdir -p /srv/myriad_exports/myriad_daily_logs /srv/myriad_exports/myriad_reconciliation /srv/radio_crm_backups
chown -R 33:33 /srv/myriad_exports
chmod -R 2775 /srv/myriad_exports

# 6. Prepare .env configuration
echo "[4/6] Configuring environment settings..."
if [ ! -f .env ]; then
    cp .env.example .env
fi
# Auto-detect public / primary host IP if SUITECRM_SITE_URL is empty or placeholder
CURRENT_SITE_URL="$(grep '^SUITECRM_SITE_URL=' .env 2>/dev/null | cut -d '=' -f2- || echo '')"
if [ -z "${CURRENT_SITE_URL}" ] || [ "${CURRENT_SITE_URL}" = "http://127.0.0.1" ] || [ "${CURRENT_SITE_URL}" = "http://84.8.141.105" ]; then
    DETECTED_IP="$(curl -s4 --max-time 2 https://ifconfig.me 2>/dev/null || hostname -I | awk '{print $1}')"
    if [ -n "${DETECTED_IP}" ]; then
        sed -i "s|^SUITECRM_SITE_URL=.*|SUITECRM_SITE_URL=http://${DETECTED_IP}|g" .env
        echo "  = Configured SUITECRM_SITE_URL=http://${DETECTED_IP}"
    fi
fi

# 7. Launch Docker containers (build local image without pulling from Docker Hub)
echo "[5/6] Building and launching Docker containers..."
docker compose up -d --build

echo "[6/6] Running master installer, migrations, and verification suite..."
chmod +x ./install_radio_crm.sh ./scripts/*.sh
./install_radio_crm.sh

SITE_URL="$(grep '^SUITECRM_SITE_URL=' .env | cut -d '=' -f2- || echo 'http://<your-server-ip>')"
ADMIN_USER="$(grep '^SUITECRM_ADMIN_USER=' .env | cut -d '=' -f2- || echo 'admin')"

echo "=================================================================="
echo "  ✓ Radio CRM Installation COMPLETE!"
echo ""
echo "  Web URL:      ${SITE_URL}"
echo "  Admin User:   ${ADMIN_USER}"
echo "  Installed at: ${TARGET_DIR}"
echo ""
echo "  To start managing your station, open the Web URL above in"
echo "  your browser and log in!"
echo "=================================================================="

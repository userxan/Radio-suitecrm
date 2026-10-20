# Radio CRM — Complete Installation & Deployment Guide

This guide provides end-to-end instructions for deploying the **Radio CRM & Broadcast Management System** on a fresh server or deploying to multiple radio station servers.

---

## Table of Contents
1. [Prerequisites & System Requirements](#1-prerequisites--system-requirements)
2. [Step 1: Server Preparation & Docker Installation](#step-1-server-preparation--docker-installation)
3. [Step 2: Clone the Repository](#step-2-clone-the-repository)
4. [Step 3: Environment Configuration (.env)](#step-3-environment-configuration-env)
5. [Step 4: Station Profile Customization (Optional)](#step-4-station-profile-customization-optional)
6. [Step 5: Launch Containers & Run Master Installer](#step-5-launch-containers--run-master-installer)
7. [Step 6: Web Setup Wizard & First-Run Configuration](#step-6-web-setup-wizard--first-run-configuration)
8. [Step 7: Production Hardening & SSL Reverse Proxy (Nginx)](#step-7-production-hardening--ssl-reverse-proxy-nginx)
9. [Step 8: Broadcast Automation (Myriad) Folder Sharing](#step-8-broadcast-automation-myriad-folder-sharing)
10. [Step 9: Backups, Maintenance & Updates](#step-9-backups-maintenance--updates)
11. [Troubleshooting & Verification](#troubleshooting--verification)

---

## 1. Prerequisites & System Requirements

### Hardware Requirements
- **CPU**: 2+ vCPUs (4 vCPUs recommended for multi-user sales teams)
- **RAM**: 4 GB minimum (8 GB recommended)
- **Disk Space**: 25 GB+ SSD storage (accounting for audio attachments, backups, and playout logs)

### Operating System
- **Recommended**: Ubuntu 24.04 LTS or Ubuntu 22.04 LTS
- **Supported**: Debian 12, Rocky Linux 9, AlmaLinux 9

### Network & Firewall
Ensure the following ports are open in your server firewall / cloud security group:
- `Port 80` (HTTP)
- `Port 443` (HTTPS)
- `Port 22` (SSH)

---

## Step 1: Server Preparation & Docker Installation

Log into your server via SSH as a sudo-enabled user:

```bash
# 1. Update OS packages
sudo apt-get update && sudo apt-get upgrade -y

# 2. Install essential tools
sudo apt-get install -y ca-certificates curl git gnupg lsb-release rsync jq ufw

# 3. Install Docker Engine and Docker Compose Plugin (Official Docker repository)
curl -fsSL https://get.docker.com | sh

# 4. Add your user to the docker group (to run docker without sudo)
sudo usermod -aG docker $USER

# 5. Apply the new group membership (or log out and back in)
newgrp docker

# 6. Verify Docker installation
docker --version
docker compose version
```

---

## Step 2: Clone the Repository

> [!TIP]
> **Prefer a 1-command installer?** You can skip Steps 1 through 5 entirely by running:  
> `curl -fsSL https://raw.githubusercontent.com/userxan/Radio-suitecrm/master/quick_install.sh | sudo bash`

Because this repository is **public**, you do **not** need a GitHub username, password, or Personal Access Token (PAT) to clone or copy the repo:

```bash
# Clone the repository directly (no login or password required):
sudo git clone https://github.com/userxan/Radio-suitecrm.git /opt/suitecrm

# (Alternative) If you don't want to use Git, download and extract the archive directly:
# sudo mkdir -p /opt/suitecrm && curl -fsSL https://github.com/userxan/Radio-suitecrm/archive/refs/heads/master.tar.gz | sudo tar -xz -C /opt/suitecrm --strip-components=1

# Grant ownership to your current user
sudo chown -R $USER:$USER /opt/suitecrm

# Navigate to the repository
cd /opt/suitecrm
```

---

## Step 3: Environment Configuration (.env)

Copy the example environment file and customize your passwords and domain:

```bash
cp .env.example .env
nano .env
```

### Key Configuration Variables

```ini
# --- SUITECRM SYSTEM SETTINGS ---
SUITECRM_VERSION=8.10.1
SUITECRM_SITE_URL=http://your-server-ip-or-domain.com
SUITECRM_ADMIN_USER=admin
SUITECRM_ADMIN_PASS=ChooseAStrongPassword123!

# --- MARIADB DATABASE SETTINGS ---
DB_HOST=mariadb
DB_PORT=3306
DB_NAME=suitecrm_db
DB_USER=suitecrm_user
DB_PASS=ChooseAStrongDatabasePassword456!
DB_ROOT_PASS=ChooseAStrongRootPassword789!

# --- LOCALIZATION ---
TZ=Africa/Johannesburg
```

> [!IMPORTANT]
> Change all passwords from defaults. Set `SUITECRM_SITE_URL` to your server's public IP or domain name (e.g. `https://crm.yourstation.co.za`). Do not include a trailing slash.

---

## Step 4: Station Profile Customization (Optional)

If you want to pre-seed your station's custom name, logo, show titles, and rate card **before** launching:

```bash
# Copy the example profile template
cp station_profile.example.json station_profile.json

# Edit station name, frequency, slogan, manager name, and show titles
nano station_profile.json
```

If `station_profile.json` exists in the repository root, the installer will automatically import your custom profile during the database migration phase! If omitted, the default station configuration is loaded and can be customized via the Web Wizard later.

---

## Step 5: Launch Containers & Run Master Installer

### 1. Build & Start the Docker Stack
```bash
docker compose up -d --build
```

Verify that both containers are running:
```bash
docker compose ps
```
You should see:
- `radio_crm_mariadb` (Status: `healthy`)
- `radio_crm_web` (Status: `Up`)

### 2. Run the Master Installer
Execute the automated installer script:
```bash
sudo ./install_radio_crm.sh
```

What the installer executes automatically:
1. **Preflight Check** ([`scripts/preflight.sh`](scripts/preflight.sh)): Verifies ports, permissions, and database connectivity.
2. **Pre-Deployment Backup** ([`scripts/backup.sh`](scripts/backup.sh)): Captures a safety baseline snapshot.
3. **Deployment & Migrations** ([`scripts/deploy.sh`](scripts/deploy.sh)):
   - Pre-creates `/srv/myriad_exports` and `/srv/myriad_reconciliation`.
   - Syncs all custom modules, logic hooks, and services to `/var/www/html/`.
   - Runs database migrations `001` through `007`.
   - Executes Quick Repair & Rebuild to compile extension registries.
   - Clears the Symfony application cache.
4. **End-to-End Verification** ([`scripts/smoke_test.sh`](scripts/smoke_test.sh)):
   - Runs all 20 Section 20 Acceptance Tests.
   - Runs 5 UI and container integration tests.
   - Runs 10 Radio Station Market Enhancement tests.
   - Runs 10 Station Setup Wizard tests.

You should see:
```text
All Smoke & Acceptance Tests PASSED.
Radio CRM installation and verification COMPLETE!
```

---

## Step 6: Web Setup Wizard & First-Run Configuration

Once the installer finishes, log into the CRM in your web browser:

1. **Open the Login Page**: `http://<your-server-ip>/`
2. **Login Credentials**:
   - Username: `admin` (or the user set in `.env`)
   - Password: The password configured in `SUITECRM_ADMIN_PASS`

3. **Launch the Station Setup Wizard**:
   - Access URL: `http://<your-server-ip>/legacy/index.php?entryPoint=radio_station_wizard`
   - Or navigate in the menu: **Administration → Radio Station Management → Station Setup & Rate Card Wizard**

### Wizard Configuration Steps:
- **Tab 1: 🏢 Station Branding & Logo**:
  - Enter your Station Name, Callsign, Dial Frequency, and Slogan.
  - Upload your Station Logo (PNG with transparent background recommended).
  - Enter Station Manager signatory name (prints on Broadcast Affidavits), studio address, telephone, billing email, and VAT number.
- **Tab 2: 🎙️ Show Schedule & Dayparts**:
  - Rename the 8 broadcast daypart titles (e.g. rename *Morning Buzz* to *The Breakfast Club*).
- **Tab 3: 💰 Rate Card & Product Catalogue**:
  - Set your 30-second unit ad rates per show slot.
  - 15s (50%), 45s (150%), and 60s (200%) rates calculate automatically.
  - Adjust production fees (voiceover, scriptwriting, audio mastering).
- **Tab 4: 📡 Traffic & Myriad Playout Rules**:
  - Verify max hourly spots (default: 20 spots / 10 minutes max ad time).
  - Verify Myriad cart allocation range (default: 2000 to 2999).
- Click **"💾 Save & Apply Station Configuration"**.

---

## Step 7: Production Hardening & SSL Reverse Proxy (Nginx)

For production, run Nginx as a reverse proxy with a free Let's Encrypt SSL certificate:

```bash
# 1. Install Nginx and Certbot
sudo apt-get install -y nginx certbot python3-certbot-nginx

# 2. Create Nginx site configuration
sudo nano /etc/nginx/sites-available/radio-crm.conf
```

Paste the following configuration (replace `crm.yourstation.co.za` with your real domain):

```nginx
server {
    listen 80;
    server_name crm.yourstation.co.za;

    client_max_body_size 50M;

    location / {
        proxy_pass http://127.0.0.1:80;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_read_timeout 300s;
        proxy_connect_timeout 300s;
    }
}
```

Enable the site and obtain SSL:
```bash
sudo ln -s /etc/nginx/sites-available/radio-crm.conf /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx

# Obtain free SSL certificate with Let's Encrypt
sudo certbot --nginx -d crm.yourstation.co.za
```

Update your `.env` file to use `https://`:
```bash
# In .env:
SUITECRM_SITE_URL=https://crm.yourstation.co.za
```
Then run `sudo ./scripts/deploy.sh` to update URL references.

---

## Step 8: Broadcast Automation (Myriad) Folder Sharing

Myriad playout computers in your on-air studios need access to export folders:
- Exported advert schedules & MP3s: `/srv/myriad_exports`
- Playout As-Run logs for reconciliation: `/srv/myriad_reconciliation`

Install Samba to share these directories with Windows broadcast workstations:

```bash
# 1. Install Samba
sudo apt-get install -y samba

# 2. Add share definition to /etc/samba/smb.conf
sudo tee -a /etc/samba/smb.conf << 'EOF'

[MyriadExports]
   path = /srv/myriad_exports
   read only = no
   browsable = yes
   writable = yes
   guest ok = no
   create mask = 0664
   directory mask = 2775
   force user = www-data
   force group = www-data

[MyriadReconciliation]
   path = /srv/myriad_exports/myriad_reconciliation
   read only = no
   browsable = yes
   writable = yes
   guest ok = no
   create mask = 0664
   directory mask = 2775
   force user = www-data
   force group = www-data
EOF

# 3. Create a Samba user for studio computers
sudo smbpasswd -a studio_user

# 4. Restart Samba
sudo systemctl restart smbd
```

In Windows on the Myriad playout machine:
Map network drive to `\\<server-ip>\MyriadExports` and `\\<server-ip>\MyriadReconciliation`.

---

## Step 9: Backups, Maintenance & Updates

### Automated Daily Backups (Cron)
Add a root cron job to take daily snapshot backups:
```bash
sudo crontab -e
```
Add the following line (runs every morning at 02:00):
```cron
0 2 * * * /opt/suitecrm/scripts/backup.sh nightly_auto >> /srv/radio_crm_backups/backup.log 2>&1
```

### Pulling Software Updates
When new improvements or bug fixes are published to the repository:
```bash
cd /opt/suitecrm

# 1. Pull latest code
git pull origin master

# 2. Deploy updates & execute new migrations
sudo ./scripts/deploy.sh

# 3. Verify zero regressions
sudo ./scripts/smoke_test.sh
```

### Manual Backup Checkpoint
```bash
sudo /opt/suitecrm/scripts/backup.sh pre_upgrade_checkpoint
```

### Restoring from a Backup
Every backup directory under `/srv/radio_crm_backups/` contains an automated restore script:
```bash
sudo /srv/radio_crm_backups/radio_crm_backup_<TIMESTAMP>_<LABEL>/restore_radio_crm.sh
```

---

## Troubleshooting & Verification

### Run Automated Diagnostics
```bash
# Run full 45-test acceptance and verification suite
sudo /opt/suitecrm/scripts/smoke_test.sh
```

### View Application and Playout Logs
```bash
# SuiteCRM legacy log
docker exec radio_crm_web tail -n 50 /var/www/html/logs/legacy/suitecrm.log

# Apache web error log
docker exec radio_crm_web tail -n 50 /var/log/apache2/error.log

# Inspect generated Myriad CSV exports
ls -la /srv/myriad_exports/

# Ingest playout logs manually
docker exec -u www-data radio_crm_web php /opt/radio_crm_repo/scripts/reconcile_myriad_logs.php

# Generate monthly ICASA broadcast report
docker exec -u www-data radio_crm_web php /opt/radio_crm_repo/scripts/generate_icasa_report.php
```

### Rebuilding Extension Caches Manually
```bash
docker exec -u www-data radio_crm_web php /opt/radio_crm_repo/migrations/007_quick_repair_and_rebuild.php
docker exec -u www-data radio_crm_web php /var/www/html/bin/console cache:clear
```

### Purging Demo / Seed Data for a 100% Fresh Installation
If you want to clear all sample / preloaded accounts, contacts, leads, opportunities, activities, and test campaigns while preserving the Radio Rate Card catalogue, station settings, and admin user:
```bash
docker exec radio_crm_web php /opt/radio_crm_repo/scripts/purge_demo_data.php
```

### "Site Could Not Be Reached" Troubleshooting Checklist

If you see **"This site can't be reached"**, **"Connection Refused"**, or **"Connection Timed Out"** in your browser after installation:

1. **Explicitly type `http://` (not `https://`)**:
   - Modern browsers (Chrome, Edge, Safari) default to `https://`.
   - The web container serves over HTTP on port 80 out of the box unless an SSL reverse proxy / certificate is configured.
   - Enter `http://<YOUR_SERVER_IP>` in the address bar (e.g. `http://84.8.141.105`).

2. **Use the Server's Public IPv4 Address (not Private/VPC IP)**:
   - If your server terminal shows an IP like `10.0.0.85` or `172.x.x.x` (private RFC 1918 addresses), external browsers cannot connect directly to that private IP over the Internet.
   - Check your public IP by running:
     ```bash
     curl -4 ifconfig.me
     ```
   - Connect using `http://<PUBLIC_IP>`.

3. **Cloud Firewall / Security Group (AWS, Azure, GCP, Hetzner, DigitalOcean)**:
   - Cloud providers block incoming port 80 by default.
   - Open your cloud console (e.g. AWS EC2 Security Groups, Azure NSG, Hetzner Cloud Console).
   - Add an **Inbound Rule**:
     - **Type**: HTTP
     - **Protocol**: TCP
     - **Port**: 80
     - **Source**: `0.0.0.0/0` (Anywhere)

4. **Ubuntu Host Firewall (`ufw`)**:
   - Verify whether UFW is running:
     ```bash
     sudo ufw status
     ```
   - If `Status: active`, allow HTTP and SSH:
     ```bash
     sudo ufw allow 80/tcp
     sudo ufw allow 22/tcp
     sudo ufw reload
     ```

5. **Verify Web Server is Responding Locally**:
   - Test locally on the server CLI:
     ```bash
     curl -IL http://127.0.0.1
     ```
   - If it returns `HTTP/1.1 200 OK`, the web application is running properly and the reachability issue is network/firewall related.

6. **Update `SUITECRM_SITE_URL` in `.env`**:
   - Check `/opt/suitecrm/.env` and verify `SUITECRM_SITE_URL` matches your public IP or domain:
     ```bash
     grep '^SUITECRM_SITE_URL=' /opt/suitecrm/.env
     ```
   - Run `./scripts/deploy.sh` to synchronize the URL into SuiteCRM.

### "Invalid Credentials" Troubleshooting Checklist

If logging into SuiteCRM displays **"Invalid credentials"**:

1. **Verify Username Spelling**:
   - The default username is **`admin`** (check for typos like `adimn`).
   - If configured as `Xander` or another username in `.env`, try both `admin` and `Xander`.

2. **Synchronize Credentials from `.env` to the Database**:
   - The base database image seeds a default password hash that may differ from your newly edited `SUITECRM_ADMIN_PASS` in `.env`.
   - Run the automated admin credential sync script:
     ```bash
     docker exec radio_crm_web php /opt/radio_crm_repo/scripts/sync_admin_user.php
     ```
   - This immediately hashes `SUITECRM_ADMIN_PASS` (e.g. `1114`) and updates both `admin` and `Xander` in the database with active status and administrator privileges.

3. **Verify Directly in Database (Emergency Reset)**:
   ```bash
   docker exec -u www-data radio_crm_web php /opt/radio_crm_repo/scripts/sync_admin_user.php
   ```

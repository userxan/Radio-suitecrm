# Radio CRM — SuiteCRM 8.10.1 Full Technical Rebuild

This repository contains the complete, reproducible source code, Docker infrastructure, numbered database/vardef/product migrations, operational scripts, and automated acceptance tests for the **Radio CRM** built on **SuiteCRM 8.10.1** (`PHP 8.3`, `MariaDB 10.11`, `Apache 2.4`).

---

## 🚀 Quick Start & Installation Guide

> [!IMPORTANT]
> **Looking to install on a fresh server or deploy across multiple radio stations?**  
> Please see the full step-by-step guide: **[INSTALL.md](INSTALL.md)**  
> It covers hardware specifications, Docker setup, `.env` variables, automated master installation, Nginx SSL reverse proxy, Samba share setup for Myriad studio playout machines, and automated backup crons.

### ⚡ 1-Line Automated Server Install (No GitHub Login Required):
Run this single command on a fresh Ubuntu / Debian server:
```bash
curl -fsSL https://raw.githubusercontent.com/userxan/Radio-suitecrm/master/quick_install.sh | sudo bash
```
*This automatically installs Docker, fetches the Radio CRM codebase without requiring any GitHub credentials, configures `.env`, starts the containers, executes all migrations, and runs verification tests.*

---

### Manual Install (No Username/Password Needed):
Because this repository is **public**, you do **not** need a GitHub username, password, or Personal Access Token:

```bash
# 1. Clone directly (no password needed):
git clone https://github.com/userxan/Radio-suitecrm.git /opt/suitecrm && cd /opt/suitecrm

# Or download the tarball directly if you don't even have git installed:
# curl -fsSL https://github.com/userxan/Radio-suitecrm/archive/refs/heads/master.tar.gz | tar -xz -C /opt/suitecrm --strip-components=1 && cd /opt/suitecrm

# 2. Configure environment:
cp .env.example .env && nano .env

# 3. Launch and run master installer:
docker compose up -d --build && sudo ./install_radio_crm.sh
```

---

## 1. Architecture & Workflow Overview

```mermaid
flowchart TD
    WIZ["Station Setup Wizard\n(Branding, Logo, Rate Cards, Dayparts)"] -->|Config & Products| RATE["AOS_Products Catalogue\n(30s/15s/45s/60s Ad Slots + Production Fees)"]
    RATE -->|Select & Price| A["AOS_Quotes EditView\n(168 Hourly Cells + Start/End Dates)"]
    A -->|AJAX Sync + before_save/after_save Hooks| B["Calculated Quote Items\n(aos_products_quotes + 15% VAT + Clean PDF)"]
    B -->|Convert to Radio Campaign\n(convert_quote_to_radio_campaign.php)| C["RT_RadioCampaigns Record\n(Idempotent 1:1 Upsert + Join Table)"]
    C -->|Export to Myriad\n(ExportToMyriadAction Symfony Service)| D["/srv/myriad_exports/\n12-Col Master CSV + Daily .log Files"]
    D -->|Playout on Myriad Automation| PLAY["Studio Playout Workstations"]
    PLAY -->|Export As-Run Logs| RECON["Playout Reconciliation Engine\n(reconcile_myriad_logs.php)"]
    RECON --> AFFIDAVIT["Signed Broadcast Affidavits\n(generate_broadcast_affidavit.php)"]
    RECON --> ICASA["ICASA Compliance Reports\n(generate_icasa_report.php)"]
    RECON --> INVOICE["AOS_Invoices Billing Conversion\n(convert_campaign_to_invoice.php)"]
```

### Key Modules & Capabilities
1. **Station Setup Wizard & Multi-Station Branding (`radio_station_wizard.php`)**:
   - Web-based 4-tab onboarding wizard accessible via SuiteCRM Administration.
   - Station branding: Station name, frequency, callsign, slogan, station manager signatory, billing details, and live station logo upload with image preview.
   - Daypart scheduling: Configurable 8 show dayparts.
   - Rate Card engine: Live 30s base rate editing with automated 15s (50%), 45s (150%), and 60s (200%) scaling synchronized directly to `aos_products`.
   - Traffic rules: Max hourly commercial duration enforcement (default 10 mins / 20 spots) and Myriad cart allocation range.
   - Headless CLI / unattended provisioning via `scripts/setup_station.php` and `station_profile.json`.
2. **Shared Domain Service (`RadioScheduleService.php`)**:
   - Single source of truth for the 8 radio show slots, 24 hourly bands, and 7 weekdays (`24 x 7 = 168` fields).
   - Exact inclusive calendar-date weekday occurrence math (`countWeekdayOccurrences`) independent of server timezone.
   - Even spacing of multiple plays inside an hour (`60 / (N + 1)` minutes: 1 play $\rightarrow$ `:30:00`, 2 plays $\rightarrow$ `:20:00` and `:40:00`, 3 plays $\rightarrow$ `:15:00`, `:30:00`, `:45:00`).
3. **Weekly Advert Schedule on `AOS_Quotes`**:
   - 168 integer schedule fields + `campaign_start_date_c` and `campaign_end_date_c` in `aos_quotes_cstm`.
   - Rendered inside `<form name="EditView">` via `CustomAOS_QuotesViewEdit` (`view.edit.php`) and `weekly_advert_schedule.tpl`.
   - Live client-side & AJAX (`radio_schedule_ajax_sync`) calculation of **Weekly Spots**, **Total Spots Across Campaign**, and automatic line-item sync.
   - Authoritative server-side recalculation and `aos_products_quotes` upsert via `RadioQuoteScheduleProducts` (`before_save`, `after_save`, `after_retrieve`).
   - Natural-language line descriptions for customer-facing Quote PDFs with zero debug/schedule-dump text.
4. **`RT_RadioCampaigns` Custom Module**:
   - Registered in SuiteCRM `beanList`, `beanFiles`, `moduleList`, `app_list_strings`, and `rt_radiocampaigns_aos_quotes_c` relationship dictionary.
   - Idempotent conversion from `AOS_Quotes` DetailView via `convert_quote_to_radio_campaign` entryPoint.
5. **Native SuiteCRM 8 `Export to Myriad` Record Action**:
   - Registered in `extensions/defaultExt/config/modules/RT_RadioCampaigns/recordview/actions/export-to-myriad.php`.
   - Handled by Symfony `ProcessHandlerInterface` service `ExportToMyriadAction.php` (`record-radio-campaign-export-to-myriad`).
   - Validates `myriad_cart_number`, linked Quote, positive spots, and valid dates, writes timestamped 12-column CSV and per-date `.log` files to `/srv/myriad_exports/`, and updates `status = exported_to_myriad` only after all files are flushed to disk.
6. **Radio Station Market Enhancements**:
   - **Playout As-Run Log Reconciliation** (`scripts/reconcile_myriad_logs.php`): Matches Myriad playback logs against scheduled spots, calculating play counts, missed spots, and make-good schedules.
   - **Automated Broadcast Affidavits** (`generate_broadcast_affidavit.php`): Generates certified compliance proof-of-performance documents with station manager signature.
   - **ICASA Compliance Logs** (`scripts/generate_icasa_report.php`): Summarizes total advertising duration, percentage of hourly broadcast time, and music/talk/ad split for South African regulatory reporting.
   - **One-Click Invoice Conversion** (`convert_campaign_to_invoice.php`): Converts fulfilled radio campaigns into `AOS_Invoices` with reconciled spot counts and payment terms.
   - **Client Schedule Review Portal** (`radio_campaign_client_review.php`): Secure client link to preview campaign flighting calendar and playout logs.

---

## 2. Repository Structure

```text
/opt/suitecrm/
├── Dockerfile                          # Ubuntu 24.04 + Apache 2.4 + PHP 8.3 image definition
├── docker-compose.yml                  # radio_crm_web + radio_crm_mariadb orchestration
├── .env.example                        # Template for environment variables (no secrets in Git)
├── station_profile.example.json        # Template for automated station branding & rate cards
├── install_radio_crm.sh                # One-command master installer, verifier & backup script
├── INSTALL.md                          # Full step-by-step installation & deployment guide
├── MANIFEST.sha256                     # SHA-256 checksum manifest of all tracked project files
├── docker/
│   ├── apache-suitecrm.conf            # Apache VirtualHost -> /var/www/html/public
│   ├── mariadb-custom.cnf              # MariaDB 10.11 UTF8MB4 & tuning config
│   └── php-suitecrm.ini                # PHP 8.3 memory, upload, max_input_vars=5000 config
├── migrations/
│   ├── 001_create_rt_radiocampaigns_tables.php
│   ├── 002_create_aos_quotes_schedule_columns.php
│   ├── 003_seed_radio_catalogue_products.php
│   ├── 004_professional_quote_pdf_template.php
│   ├── 005_radio_crm_market_enhancements.php
│   ├── 006_radio_station_settings_table.php
│   └── 007_quick_repair_and_rebuild.php
├── scripts/
│   ├── preflight.sh                    # Host, container, DB, Apache, and directory checks
│   ├── backup.sh                       # Full DB + custom file snapshot & restore script generator
│   ├── deploy.sh                       # File sync, permissions, syntax check, migrations & cache clear
│   ├── smoke_test.sh                   # Runs full 45-test acceptance & verification suites
│   ├── setup_station.php               # CLI station manager for profile imports & branding checks
│   ├── reconcile_myriad_logs.php       # Ingests Myriad as-run logs & flags missed spots
│   └── generate_icasa_report.php       # Generates monthly ICASA advertising compliance report
├── src/                                # Mirrored into /var/www/html/ by scripts/deploy.sh
│   ├── extensions/defaultExt/
│   │   ├── config/modules/RT_RadioCampaigns/recordview/actions/export-to-myriad.php
│   │   └── modules/RT_RadioCampaigns/Process/Service/RecordActions/ExportToMyriadAction.php
│   └── public/legacy/
│       ├── custom/
│       │   ├── convert_quote_to_radio_campaign.php
│       │   ├── convert_campaign_to_invoice.php
│       │   ├── export_radio_campaign_to_myriad.php
│       │   ├── generate_broadcast_affidavit.php
│       │   ├── radio_campaign_client_review.php
│       │   ├── radio_schedule_ajax_sync.php
│       │   ├── radio_station_wizard.php
│       │   ├── reconcile_radio_campaign_logs.php
│       │   ├── include/RadioCRM/
│       │   │   ├── RadioScheduleService.php
│       │   │   └── RadioStationConfigService.php
│       │   ├── metadata/rt_radiocampaigns_aos_quotesMetaData.php
│       │   ├── modules/AOS_Quotes/
│       │   └── Extension/
│       └── modules/RT_RadioCampaigns/
└── tests/
    ├── run_acceptance_tests.php        # Section 20 Mandatory Acceptance Suite (20 tests)
    ├── run_ui_http_tests.php           # UI/DOM form hierarchy & Symfony container verification (5 tests)
    ├── test_radio_enhancements.php     # Reconciliation, ICASA, Invoices & Affidavits (10 tests)
    └── test_station_wizard.php         # Wizard, branding, rate card & import/export (10 tests)
```

---

## 3. Operations & Maintenance Commands

### Run Full Deployment, Migrations, and Acceptance Tests
```bash
sudo ./install_radio_crm.sh
```

### Run Only Smoke & Acceptance Tests (45 Tests)
```bash
sudo ./scripts/smoke_test.sh
```

### Inspect Station Configuration or Import Profile via CLI
```bash
# Check current station settings
docker exec -u www-data radio_crm_web php /opt/radio_crm_repo/scripts/setup_station.php status

# Headless profile import
docker exec -u www-data radio_crm_web php /opt/radio_crm_repo/scripts/setup_station.php import /opt/radio_crm_repo/station_profile.json
```

### Create a Manual Backup Snapshot
```bash
sudo ./scripts/backup.sh manual_checkpoint
```

### Rollback / Restore from any Backup Snapshot
Every backup directory under `/srv/radio_crm_backups/radio_crm_backup_<timestamp>_<label>/` includes an executable `restore_radio_crm.sh` script:
```bash
sudo /srv/radio_crm_backups/radio_crm_backup_<timestamp>_<label>/restore_radio_crm.sh
```


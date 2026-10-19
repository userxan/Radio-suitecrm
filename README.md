# Radio CRM — SuiteCRM 8.10.1 Full Technical Rebuild

This repository contains the complete, reproducible source code, Docker infrastructure, numbered database/vardef/product migrations, operational scripts, and automated acceptance tests for the ** Radio CRM** built on **SuiteCRM 8.10.1** (`PHP 8.3`, `MariaDB 10.11`, `Apache 2.4`).

---

## 1. Architecture & Workflow Overview

```mermaid
flowchart LR
    A["AOS_Quotes EditView\n(168 Hourly Cells + Start/End Dates)"] -->|AJAX Sync + before_save/after_save Hooks| B["8 Radio Catalogue Products\n(aos_products_quotes + 15% VAT + Clean PDF)"]
    B -->|Convert to Radio Campaign\n(convert_quote_to_radio_campaign.php)| C["RT_RadioCampaigns Record\n(Idempotent 1:1 Upsert + Join Table)"]
    C -->|Export to Myriad\n(ExportToMyriadAction Symfony Service)| D["/srv/myriad_exports/\n12-Col Master CSV + Daily .log Files"]
```

### Key Components
1. **Shared Domain Service (`RadioScheduleService.php`)**:
   - Single source of truth for the 8 radio show slots (`PRE-BREAKFAST`, `MORNING-BUZZ`, `MIDMORNING-BANTER`, `MIDDAY-PULSE`, `CRUISE-CONTROL`, `SPECIALTY-SHOW-1`, `SPECIALTY-SHOW-2`, `MUSIC-UNLIMITED`), 24 hourly bands, and 7 weekdays (`24 x 7 = 168` fields).
   - Exact inclusive calendar-date weekday occurrence math (`countWeekdayOccurrences`) independent of server timezone.
   - Even spacing of multiple plays inside an hour (`60 / (N + 1)` minutes: 1 play $\rightarrow$ `:30:00`, 2 plays $\rightarrow$ `:20:00` and `:40:00`, 3 plays $\rightarrow$ `:15:00`, `:30:00`, `:45:00`).
2. **Weekly Advert Schedule on `AOS_Quotes`**:
   - 168 integer schedule fields + `campaign_start_date_c` and `campaign_end_date_c` in `aos_quotes_cstm`.
   - Rendered inside `<form name="EditView">` via `CustomAOS_QuotesViewEdit` (`view.edit.php`) and `weekly_advert_schedule.tpl`.
   - Live client-side & AJAX (`radio_schedule_ajax_sync`) calculation of **Weekly Spots**, **Total Spots Across Campaign**, and automatic line-item sync.
   - Authoritative server-side recalculation and `aos_products_quotes` upsert via `RadioQuoteScheduleProducts` (`before_save`, `after_save`, `after_retrieve`).
   - Natural-language line descriptions for customer-facing Quote PDFs with zero debug/schedule-dump text.
3. **`RT_RadioCampaigns` Custom Module**:
   - Registered in SuiteCRM `beanList`, `beanFiles`, `moduleList`, `app_list_strings`, and `rt_radiocampaigns_aos_quotes_c` relationship dictionary.
   - Idempotent conversion from `AOS_Quotes` DetailView via `convert_quote_to_radio_campaign` entryPoint.
4. **Native SuiteCRM 8 `Export to Myriad` Record Action**:
   - Registered in `extensions/defaultExt/config/modules/RT_RadioCampaigns/recordview/actions/export-to-myriad.php`.
   - Handled by Symfony `ProcessHandlerInterface` service `ExportToMyriadAction.php` (`record-radio-campaign-export-to-myriad`).
   - Enforces authentication and record-level ACL (`view` and `export`), validates `myriad_cart_number`, linked Quote, positive spots, and valid dates, writes timestamped 12-column CSV and per-date `.log` files to `/srv/myriad_exports/`, and updates `status = exported_to_myriad` only after all files are flushed to disk.

---

## 2. Repository Structure

```text
/home/ubuntu/suitecrm/
├── Dockerfile                          # Ubuntu 24.04 + Apache 2.4 + PHP 8.3 image definition
├── docker-compose.yml                  # radio_crm_web + radio_crm_mariadb orchestration
├── .env.example                        # Template for environment variables (no secrets in Git)
├── install_radio_crm.sh                # One-command master installer, verifier & backup script
├── MANIFEST.sha256                     # SHA-256 checksum manifest of all tracked project files
├── docker/
│   ├── apache-suitecrm.conf            # Apache VirtualHost -> /var/www/html/public
│   ├── mariadb-custom.cnf              # MariaDB 10.11 UTF8MB4 & tuning config
│   └── php-suitecrm.ini                # PHP 8.3 memory, upload, max_input_vars=5000 config
├── migrations/
│   ├── 001_create_rt_radiocampaigns_tables.php
│   ├── 002_create_aos_quotes_schedule_columns.php
│   ├── 003_seed_radio_catalogue_products.php
│   └── 004_quick_repair_and_rebuild.php
├── scripts/
│   ├── preflight.sh                    # Host, container, DB, Apache, and directory checks
│   ├── backup.sh                       # Full DB + custom file snapshot & restore script generator
│   ├── deploy.sh                       # File sync, permissions, syntax check, migrations & cache clear
│   └── smoke_test.sh                   # Runs full acceptance & UI/Symfony verification suites
├── src/                                # Mirrored into /var/www/html/ by scripts/deploy.sh
│   ├── extensions/defaultExt/
│   │   ├── config/modules/RT_RadioCampaigns/recordview/actions/export-to-myriad.php
│   │   └── modules/RT_RadioCampaigns/Process/Service/RecordActions/ExportToMyriadAction.php
│   └── public/legacy/
│       ├── custom/
│       │   ├── convert_quote_to_radio_campaign.php
│       │   ├── export_radio_campaign_to_myriad.php
│       │   ├── radio_schedule_ajax_sync.php
│       │   ├── include/RadioCRM/RadioScheduleService.php
│       │   ├── metadata/rt_radiocampaigns_aos_quotesMetaData.php
│       │   ├── modules/AOS_Quotes/
│       │   └── Extension/
│       └── modules/RT_RadioCampaigns/
└── tests/
    ├── run_acceptance_tests.php        # Section 20 Mandatory Acceptance Suite (A1-A5, B1-B3, C1-C3, D1-D6, E1-E3)
    └── run_ui_http_tests.php           # UI/DOM form hierarchy & Symfony container verification (UI-1..UI-4)
```

---

## 3. Deployment & Verification Commands

### Run Full Deployment, Migrations, and Acceptance Tests
```bash
sudo /home/ubuntu/suitecrm/install_radio_crm.sh
```

### Run Only Smoke & Acceptance Tests
```bash
sudo /home/ubuntu/suitecrm/scripts/smoke_test.sh
```

### Create a Manual Backup Snapshot
```bash
sudo /home/ubuntu/suitecrm/scripts/backup.sh manual_checkpoint
```

### Rollback / Restore from any Backup Snapshot
Every backup directory under `/srv/radio_crm_backups/radio_crm_backup_<timestamp>_<label>/` includes an executable `restore_radio_crm.sh` script:
```bash
sudo /srv/radio_crm_backups/radio_crm_backup_<timestamp>_<label>/restore_radio_crm.sh
```

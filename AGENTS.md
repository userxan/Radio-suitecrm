# Radio CRM Development & Deployment Guidelines

## 1. Active Production Environment Protection
- **Status**: The live Docker container stack (`radio_crm_web` and `radio_crm_mariadb`) is an active production installation.
- **Data Preservation**: The live MariaDB database (`suitecrm_db`) contains production radio station data, active campaigns, playout records, and custom station branding in `radio_station_settings` (currently configured for the operating station, e.g. PUKfm 93.6).
- **Rule**: Never run destructive table drops, migration resets, or commands that wipe `radio_station_settings` or replace live database values with generic defaults.
- **Deployment Process**: Whenever changes are made to `src/`, always run `sudo ./scripts/deploy.sh` to sync the files, apply migrations idempotently, compile extensions, and clear Symfony cache. Verify with `sudo ./scripts/smoke_test.sh` to ensure 100% test pass rate.

## 2. Strict White-Label Policy for Git & GitHub
- **Status**: The GitHub repository (`origin/master`) is a generic, reusable distribution of the Radio CRM for any radio station worldwide.
- **Rule**: Zero hardcoded station names, callsigns, frequencies, slogans, or station-specific logos in tracked code, filenames, migration scripts, templates, tests, or documentation.
- **Implementation**:
  - All station identity (name, callsign, frequency, logo URL, manager name, contact info) must be queried dynamically at runtime via `RadioStationConfigService::getSettings($db)`.
  - Generic fallbacks in code and templates must use universal defaults (e.g., `Community Radio FM`, `RADIO`, `101.5 FM`, `radio_station_logo.png`).
  - Rate cards and products in migrations must use generic names (e.g., `Radio Rate Card`, `Radio Advert: Morning Buzz (06:00 - 09:00)`).
  - Tests must assert against dynamic values or generic patterns (e.g. `[A-Za-z0-9_-]+_Adverts_` regexes) rather than hardcoded station names.
  - Local station profiles (`station_profile.json`, `*.local.json`) must remain in `.gitignore` and never be committed.

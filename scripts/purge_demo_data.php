<?php
// scripts/purge_demo_data.php
// Purges all preloaded demo / seed data (Accounts, Contacts, Leads, Opportunities,
// Activities, Cases, Quotes, Invoices, Test Campaigns, Seed Users) for a 100% fresh installation.

declare(strict_types=1);

$repoDir = dirname(__DIR__);
$envPaths = [
    $repoDir . '/.env',
    '/opt/radio_crm_repo/.env',
    '/var/www/html/.env.radiocrm',
    '/var/www/html/.env',
    '/var/www/.env'
];

foreach ($envPaths as $envFile) {
    if (file_exists($envFile) && is_readable($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines) {
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || strpos($line, '#') === 0) {
                    continue;
                }
                if (strpos($line, '=') !== false) {
                    list($k, $v) = explode('=', $line, 2);
                    $k = trim($k);
                    $v = trim(trim($v), "\"'");
                    putenv("{$k}={$v}");
                    $_ENV[$k] = $v;
                }
            }
            break;
        }
    }
}

$dbHost = getenv('DB_HOST') ?: 'mariadb';
$dbPort = getenv('DB_PORT') ?: '3306';
$dbName = getenv('DB_NAME') ?: 'suitecrm_db';
$dbUser = getenv('DB_USER') ?: 'suitecrm_user';
$dbPass = getenv('DB_PASS') ?: 'ChangeMe_DB_Pass!';
$dbRootPass = getenv('DB_ROOT_PASS') ?: 'ChangeMe_Root_Pass!';

$mysqli = @new mysqli($dbHost, $dbUser, $dbPass, $dbName, (int)$dbPort);
if ($mysqli->connect_error) {
    $mysqli = @new mysqli($dbHost, 'root', $dbRootPass, $dbName, (int)$dbPort);
    if ($mysqli->connect_error) {
        fwrite(STDERR, "[Purge] Database connection failed: " . $mysqli->connect_error . "\n");
        exit(1);
    }
}

echo "============================================================\n";
echo "Radio CRM - Purging Preloaded Demo / Seed Data\n";
echo "============================================================\n";

$tablesToTruncate = [
    // CRM Business Entities
    'accounts',
    'accounts_cstm',
    'accounts_audit',
    'accounts_contacts',
    'accounts_opportunities',
    'accounts_bugs',
    'accounts_cases',
    'contacts',
    'contacts_cstm',
    'contacts_audit',
    'contacts_users',
    'contacts_cases',
    'leads',
    'leads_cstm',
    'leads_audit',
    'opportunities',
    'opportunities_cstm',
    'opportunities_audit',
    'opportunities_contacts',
    'cases',
    'cases_cstm',
    'cases_audit',
    'cases_bugs',
    'bugs',
    'bugs_cstm',
    'project',
    'project_cstm',
    'project_task',
    'project_task_cstm',
    'notes',
    'notes_cstm',
    // Activities
    'calls',
    'calls_cstm',
    'calls_users',
    'calls_contacts',
    'calls_leads',
    'meetings',
    'meetings_cstm',
    'meetings_users',
    'meetings_contacts',
    'meetings_leads',
    'tasks',
    'tasks_cstm',
    // Emails & Feeds
    'emails',
    'emails_text',
    'emails_beans',
    'emails_email_addr_rel',
    'sugarfeed',
    'tracker',
    'tracker_perf',
    'tracker_sessions',
    'tracker_queries',
    'tracker_tracker_queries',
    // Quotes, Invoices & Radio Campaigns
    'aos_quotes',
    'aos_quotes_cstm',
    'aos_quotes_audit',
    'aos_products_quotes',
    'aos_products_quotes_audit',
    'aos_line_item_groups',
    'aos_line_item_groups_audit',
    'aos_invoices',
    'aos_invoices_cstm',
    'aos_invoices_audit',
    'rt_radiocampaigns',
    'rt_radiocampaigns_cstm',
    'rt_radiocampaigns_audit',
    'rt_radiocampaigns_aos_quotes_c',
    'rt_radiocampaign_plays',
];

$cleared = 0;
foreach ($tablesToTruncate as $tbl) {
    // Check if table exists
    $check = $mysqli->query("SHOW TABLES LIKE '{$tbl}'");
    if ($check && $check->num_rows > 0) {
        $mysqli->query("TRUNCATE TABLE `{$tbl}`");
        $cleared++;
    }
}
echo "  ✓ Cleared {$cleared} demo business tables.\n";

// Remove all secondary and seed demo users, keeping only primary system admin
$mysqli->query("DELETE FROM users WHERE id != '1'");
$mysqli->query("UPDATE users SET 
    user_name = 'admin',
    first_name = 'System',
    last_name = 'Administrator',
    title = 'System Administrator',
    department = NULL,
    phone_work = NULL,
    phone_home = NULL,
    phone_mobile = NULL,
    phone_other = NULL,
    phone_fax = NULL,
    address_street = NULL,
    address_city = NULL,
    address_state = NULL,
    address_country = NULL,
    address_postalcode = NULL,
    status = 'Active',
    employee_status = 'Active',
    is_admin = 1,
    deleted = 0
WHERE id = '1'");
echo "  ✓ Removed all secondary and demo users (User ID 1 reset to clean System Administrator).\n";

// Clean orphan and personal email addresses; establish clean default admin email
$mysqli->query("DELETE FROM email_addr_bean_rel");
$mysqli->query("DELETE FROM email_addresses");
$mysqli->query("INSERT INTO email_addresses (id, email_address, email_address_caps, invalid_email, opt_out, confirm_opt_in, date_created, date_modified, deleted)
                VALUES ('1', 'admin@radiocrm.local', 'ADMIN@RADIOCRM.LOCAL', 0, 0, 'not-opt-in', NOW(), NOW(), 0)");
$mysqli->query("INSERT INTO email_addr_bean_rel (id, email_address_id, bean_id, bean_module, primary_address, reply_to_address, date_created, date_modified, deleted)
                VALUES ('1', '1', '1', 'Users', 1, 0, NOW(), NOW(), 0)");
echo "  ✓ Cleaned up email addresses and established admin@radiocrm.local.\n";

// Clean orphan user preferences
$mysqli->query("DELETE FROM user_preferences WHERE assigned_user_id != '1'");

// Sanitize station settings and calendar
$mysqli->query("UPDATE radio_station_settings SET setting_value = 'traffic@station.local' WHERE setting_key = 'station_email'");
$mysqli->query("UPDATE radio_station_settings SET setting_value = 'Broadcasting House, Studio 1, Main Campus' WHERE setting_key = 'studio_address'");
$mysqli->query("TRUNCATE TABLE vcals");

// Reset auto-increment on quotes and invoices
$mysqli->query("ALTER TABLE aos_quotes AUTO_INCREMENT = 1");
$mysqli->query("ALTER TABLE aos_invoices AUTO_INCREMENT = 1");

// Clean exported test playout and audio files from /srv/myriad_exports/
$exportDirs = [
    '/srv/myriad_exports/myriad_daily_logs',
    '/srv/myriad_exports/myriad_audio',
    '/srv/myriad_exports/myriad_reconciliation',
];
foreach ($exportDirs as $dir) {
    if (is_dir($dir)) {
        $files = glob("{$dir}/*");
        if ($files) {
            foreach ($files as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
        }
    }
}

// Clean CSV files in /srv/myriad_exports
$csvFiles = glob("/srv/myriad_exports/*.csv");
if ($csvFiles) {
    foreach ($csvFiles as $f) {
        if (is_file($f)) {
            @unlink($f);
        }
    }
}
echo "  ✓ Purged test playout logs and export files.\n";

// Verify Rate Card Catalogue and PDF templates survived intact
$prodCheck = $mysqli->query("SELECT count(*) as cnt FROM aos_products WHERE deleted = 0");
$prodCount = $prodCheck ? (int)$prodCheck->fetch_assoc()['cnt'] : 0;

$tplCheck = $mysqli->query("SELECT count(*) as cnt FROM aos_pdf_templates WHERE type = 'AOS_Quotes' AND deleted = 0");
$tplCount = $tplCheck ? (int)$tplCheck->fetch_assoc()['cnt'] : 0;

$userCheck = $mysqli->query("SELECT count(*) as cnt FROM users WHERE status = 'Active' AND deleted = 0");
$userCount = $userCheck ? (int)$userCheck->fetch_assoc()['cnt'] : 0;

echo "  = Active Rate Card Catalogue Products: {$prodCount}\n";
echo "  = Professional PDF Quotation Templates: {$tplCount}\n";
echo "  = Active Administrators / Users: {$userCount}\n";

echo "============================================================\n";
echo "✓ System purged successfully! Radio CRM is now 100% fresh.\n";
echo "============================================================\n";

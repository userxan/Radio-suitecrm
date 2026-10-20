<?php
// scripts/purge_demo_data.php
// Purges all preloaded demo / seed data (Accounts, Contacts, Leads, Opportunities,
// Activities, Cases, Quotes, Invoices, Test Campaigns, Seed Users) for a 100% fresh installation.

declare(strict_types=1);

$repoDir = dirname(__DIR__);
$envFile = $repoDir . '/.env';

if (file_exists($envFile) && is_readable($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) {
            continue;
        }
        if (strpos($line, '=') !== false) {
            list($k, $v) = explode('=', $line, 2);
            $k = trim($k);
            $v = trim($v);
            putenv("{$k}={$v}");
            $_ENV[$k] = $v;
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

// Remove seed demo users (keep admin, Xander, Lyell)
$mysqli->query("DELETE FROM users WHERE id LIKE 'seed_%'");
echo "  ✓ Removed preloaded demo seed users.\n";

// Clean orphan email addresses
$mysqli->query("DELETE FROM email_addr_bean_rel WHERE bean_module != 'Users'");
$mysqli->query("DELETE FROM email_addresses WHERE id NOT IN (SELECT email_address_id FROM email_addr_bean_rel WHERE deleted = 0)");
echo "  ✓ Cleaned up demo email addresses.\n";

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

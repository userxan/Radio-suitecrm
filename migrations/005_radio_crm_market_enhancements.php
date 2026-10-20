<?php

declare(strict_types=1);

/**
 * Migration 005: Radio CRM Market Enhancements
 * - Adds industry category, reconciliation tracking, and production/script fields to rt_radiocampaigns.
 * - Creates rt_radiocampaign_plays audit table for As-Run playout reconciliation.
 * - Adds industry_category_c, spot_duration_c, and applied_package_c to aos_quotes_cstm and accounts_cstm.
 * - Seeds radio industry dropdowns and ensures /srv/myriad_reconciliation directory exists.
 */

if (PHP_SAPI !== 'cli') {
    die('CLI only');
}

$legacyDir = '/var/www/html/public/legacy';
chdir($legacyDir);
if (!defined('sugarEntry')) {
    define('sugarEntry', true);
}
require_once 'include/entryPoint.php';

global $db;
if (!isset($db) || !is_object($db)) {
    require_once 'include/database/DBManagerFactory.php';
    $db = DBManagerFactory::getInstance();
    $GLOBALS['db'] = $db;
}

echo "[Migration 005] Applying Radio CRM market enhancements schema updates...\n";

// 1. Add columns to rt_radiocampaigns
$campaignCols = [
    'industry_category' => "VARCHAR(100) DEFAULT 'other'",
    'reconciled_spots' => "INT(11) DEFAULT 0",
    'missed_spots' => "INT(11) DEFAULT 0",
    'reconciliation_status' => "VARCHAR(50) DEFAULT 'Pending'",
    'script_text' => "TEXT DEFAULT NULL",
    'voice_talent' => "VARCHAR(255) DEFAULT NULL",
    'production_status' => "VARCHAR(50) DEFAULT 'Draft'",
    'client_approval_token' => "VARCHAR(64) DEFAULT NULL",
    'client_approved_at' => "DATETIME DEFAULT NULL",
    'client_feedback' => "TEXT DEFAULT NULL",
];

foreach ($campaignCols as $col => $def) {
    $chk = $db->query("SHOW COLUMNS FROM rt_radiocampaigns LIKE '{$col}'", false);
    if (!$chk || !$db->fetchByAssoc($chk)) {
        echo "  + Adding column '{$col}' to rt_radiocampaigns...\n";
        $db->query("ALTER TABLE rt_radiocampaigns ADD COLUMN {$col} {$def}", true);
    }
}

// 2. Create rt_radiocampaign_plays table
echo "[Migration 005] Ensuring rt_radiocampaign_plays table exists...\n";
$db->query("CREATE TABLE IF NOT EXISTS rt_radiocampaign_plays (
    id CHAR(36) NOT NULL PRIMARY KEY,
    campaign_id CHAR(36) NOT NULL,
    scheduled_time DATETIME DEFAULT NULL,
    actual_time DATETIME DEFAULT NULL,
    cart_number VARCHAR(50) DEFAULT NULL,
    status VARCHAR(50) DEFAULT 'Aired',
    spot_length VARCHAR(50) DEFAULT '30 sec',
    log_source VARCHAR(255) DEFAULT NULL,
    date_entered DATETIME DEFAULT NULL,
    deleted TINYINT(1) DEFAULT 0,
    INDEX idx_play_camp (campaign_id, deleted),
    INDEX idx_play_actual (actual_time, deleted),
    INDEX idx_play_cart (cart_number, deleted)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci", true);

// 3. Add custom columns to accounts_cstm
$db->query("CREATE TABLE IF NOT EXISTS accounts_cstm (id_c CHAR(36) NOT NULL PRIMARY KEY) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", false);
$acctChk = $db->query("SHOW COLUMNS FROM accounts_cstm LIKE 'industry_category_c'", false);
if (!$acctChk || !$db->fetchByAssoc($acctChk)) {
    echo "  + Adding column 'industry_category_c' to accounts_cstm...\n";
    $db->query("ALTER TABLE accounts_cstm ADD COLUMN industry_category_c VARCHAR(100) DEFAULT 'other'", true);
}

// 4. Add custom columns to aos_quotes_cstm
$quoteCols = [
    'industry_category_c' => "VARCHAR(100) DEFAULT 'other'",
    'spot_duration_c' => "VARCHAR(20) DEFAULT '30 sec'",
    'applied_package_c' => "VARCHAR(100) DEFAULT NULL",
];
foreach ($quoteCols as $col => $def) {
    $chk = $db->query("SHOW COLUMNS FROM aos_quotes_cstm LIKE '{$col}'", false);
    if (!$chk || !$db->fetchByAssoc($chk)) {
        echo "  + Adding column '{$col}' to aos_quotes_cstm...\n";
        $db->query("ALTER TABLE aos_quotes_cstm ADD COLUMN {$col} {$def}", true);
    }
}

// 5. Ensure language strings and dropdown lists exist
echo "[Migration 005] Writing language definitions for radio categories, packages, and statuses...\n";
$langContent = <<<'PHP'
<?php
if (!isset($app_list_strings)) {
    $app_list_strings = [];
}

$app_list_strings['radio_industry_category_list'] = [
    'other' => 'General / Other',
    'automotive' => 'Automotive & Dealerships',
    'dining' => 'Restaurants & Fast Food',
    'hospitality' => 'Nightlife, Bars & Events',
    'financial' => 'Banking, Insurance & Financial Services',
    'campus' => 'Campus Retail & Student Services',
    'telecom' => 'Telecommunications & Tech',
    'health' => 'Healthcare, Fitness & Wellness',
    'education' => 'Education & University Departments',
];

$app_list_strings['radio_spot_duration_list'] = [
    '15 sec' => '15 Seconds (50% Rate)',
    '30 sec' => '30 Seconds (Standard)',
    '45 sec' => '45 Seconds (150% Rate)',
    '60 sec' => '60 Seconds (200% Rate)',
];

$app_list_strings['radio_production_status_list'] = [
    'Draft' => 'Draft',
    'Scripting' => 'Scripting',
    'Voicing' => 'Voice Recording',
    'Mixing' => 'Audio Mixing',
    'Client Review' => 'Client Review',
    'Approved' => 'Approved for Broadcast',
    'Archived' => 'Archived',
];

$app_list_strings['radio_reconciliation_status_list'] = [
    'Pending' => 'Pending Reconciliation',
    'Fully Reconciled' => 'Fully Reconciled',
    'Discrepancy' => 'Discrepancy / Missed Spots',
    'Resolved' => 'Resolved / Made-Good',
];

$app_list_strings['radio_packages_list'] = [
    '' => '-- Standard Schedule --',
    'option_1' => 'Option 1: 50 Prime Time Recorded Ads (Weekly - R5,999)',
    'option_2' => 'Option 2: 50 Prime Time Live Reads (Weekly - R6,999)',
    'option_3' => 'Option 3: 120 Prime Time Recorded Ads (Monthly - R15,999)',
    'option_4' => 'Option 4: 120 Prime Time Live Reads (Monthly - R16,999)',
];
PHP;

$customExtLangDir = $legacyDir . '/custom/Extension/application/Ext/Language';
if (!is_dir($customExtLangDir)) {
    @mkdir($customExtLangDir, 0775, true);
}
file_put_contents($customExtLangDir . '/en_us.radio_crm_enhancements.php', $langContent);

$customIncLangDir = $legacyDir . '/custom/include/language';
if (!is_dir($customIncLangDir)) {
    @mkdir($customIncLangDir, 0775, true);
}
file_put_contents($customIncLangDir . '/en_us.radio_crm_enhancements.php', $langContent);

// 6. Ensure reconciliation directory exists
$reconDir = '/srv/myriad_reconciliation';
if (!is_dir($reconDir)) {
    @mkdir($reconDir, 02775, true);
    @chown($reconDir, 'www-data');
    @chgrp($reconDir, 'www-data');
}

echo "[Migration 005] Completed successfully.\n";

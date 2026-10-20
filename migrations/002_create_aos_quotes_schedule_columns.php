<?php

declare(strict_types=1);

/**
 * Migration 002: Idempotently create aos_quotes_cstm (with primary key id_c),
 * campaign_start_date_c, campaign_end_date_c, and all 168 schedule columns (Section 5.1 & 16).
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
require_once 'custom/include/RadioCRM/RadioScheduleService.php';

use RadioCRM\RadioScheduleService;

global $db;
if (!isset($db) || !is_object($db)) {
    require_once 'include/database/DBManagerFactory.php';
    $db = DBManagerFactory::getInstance();
    $GLOBALS['db'] = $db;
}

echo "[Migration 002] Ensuring aos_quotes_cstm and 168 schedule + 2 date columns exist...\n";

$db->query("CREATE TABLE IF NOT EXISTS aos_quotes_cstm (
    id_c CHAR(36) NOT NULL PRIMARY KEY
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

// Fetch existing columns on aos_quotes_cstm
$existingCols = [];
$res = $db->query(
    "SELECT COLUMN_NAME FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'aos_quotes_cstm'",
    false
);
while ($res && ($row = $db->fetchByAssoc($res))) {
    $existingCols[strtolower((string) $row['COLUMN_NAME'])] = true;
}

$alterClauses = [];
if (!isset($existingCols['campaign_start_date_c'])) {
    $alterClauses[] = "ADD COLUMN campaign_start_date_c DATE DEFAULT NULL";
}
if (!isset($existingCols['campaign_end_date_c'])) {
    $alterClauses[] = "ADD COLUMN campaign_end_date_c DATE DEFAULT NULL";
}

$allScheduleFields = RadioScheduleService::getAllScheduleFields();
foreach ($allScheduleFields as $fieldName => $meta) {
    if (!isset($existingCols[strtolower($fieldName)])) {
        $alterClauses[] = "ADD COLUMN {$fieldName} INT(11) DEFAULT 0";
    }
}

if (!empty($alterClauses)) {
    // Batch ALTER TABLE in chunks of 30 columns for safety and speed
    $chunks = array_chunk($alterClauses, 30);
    foreach ($chunks as $chunk) {
        $sql = "ALTER TABLE aos_quotes_cstm " . implode(', ', $chunk);
        $db->query($sql);
    }
    echo "  + Added " . count($alterClauses) . " missing column(s) to aos_quotes_cstm.\n";
} else {
    echo "  = All 170 custom columns already present on aos_quotes_cstm.\n";
}

// Verify exact count of schedule columns
$verifyRes = $db->query(
    "SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'aos_quotes_cstm'
       AND COLUMN_NAME REGEXP '^(mon|tue|wed|thu|fri|sat|sun)_.*_c$'",
    false
);
$verifyRow = $verifyRes ? $db->fetchByAssoc($verifyRes) : ['cnt' => 0];
$scheduleColCount = (int) ($verifyRow['cnt'] ?? 0);
echo "  = Verified schedule columns on aos_quotes_cstm: {$scheduleColCount} (expected 168)\n";
if ($scheduleColCount !== 168) {
    fwrite(STDERR, "ERROR: Expected 168 schedule columns, found {$scheduleColCount}\n");
    exit(1);
}

echo "[Migration 002] Complete.\n";

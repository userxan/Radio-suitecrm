<?php

declare(strict_types=1);

/**
 * Migration 001: Idempotently create/update rt_radiocampaigns, rt_radiocampaigns_cstm,
 * rt_radiocampaigns_audit, and rt_radiocampaigns_aos_quotes_c tables (Section 4 & 16).
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

function columnExists(object $db, string $table, string $column): bool
{
    $tEsc = $db->quote($table);
    $cEsc = $db->quote($column);
    $res = $db->query(
        "SELECT COLUMN_NAME FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$tEsc}' AND COLUMN_NAME = '{$cEsc}'",
        false
    );
    return (bool) ($res && $db->fetchByAssoc($res));
}

echo "[Migration 001] Ensuring rt_radiocampaigns tables exist...\n";

$db->query("CREATE TABLE IF NOT EXISTS rt_radiocampaigns (
    id CHAR(36) NOT NULL PRIMARY KEY,
    name VARCHAR(255) DEFAULT NULL,
    date_entered DATETIME DEFAULT NULL,
    date_modified DATETIME DEFAULT NULL,
    modified_user_id CHAR(36) DEFAULT NULL,
    created_by CHAR(36) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    deleted TINYINT(1) DEFAULT 0,
    assigned_user_id CHAR(36) DEFAULT NULL,
    start_date DATE DEFAULT NULL,
    end_date DATE DEFAULT NULL,
    number_of_spots INT(11) DEFAULT 0,
    spot_length VARCHAR(100) DEFAULT '30 sec',
    status VARCHAR(100) DEFAULT 'Draft',
    myriad_cart_number VARCHAR(255) DEFAULT NULL,
    quote_reference VARCHAR(255) DEFAULT NULL,
    linked_quote_id CHAR(36) DEFAULT NULL,
    INDEX idx_rt_rc_del_name (deleted, name),
    INDEX idx_rt_rc_linked_quote (linked_quote_id, deleted)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

$requiredCols = [
    'start_date' => "DATE DEFAULT NULL",
    'end_date' => "DATE DEFAULT NULL",
    'number_of_spots' => "INT(11) DEFAULT 0",
    'spot_length' => "VARCHAR(100) DEFAULT '30 sec'",
    'status' => "VARCHAR(100) DEFAULT 'Draft'",
    'myriad_cart_number' => "VARCHAR(255) DEFAULT NULL",
    'quote_reference' => "VARCHAR(255) DEFAULT NULL",
    'linked_quote_id' => "CHAR(36) DEFAULT NULL",
];

foreach ($requiredCols as $col => $def) {
    if (!columnExists($db, 'rt_radiocampaigns', $col)) {
        $db->query("ALTER TABLE rt_radiocampaigns ADD COLUMN {$col} {$def}");
        echo "  + Added column rt_radiocampaigns.{$col}\n";
    }
}

$db->query("CREATE TABLE IF NOT EXISTS rt_radiocampaigns_cstm (
    id_c CHAR(36) NOT NULL PRIMARY KEY
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

$db->query("CREATE TABLE IF NOT EXISTS rt_radiocampaigns_audit (
    id CHAR(36) NOT NULL PRIMARY KEY,
    parent_id CHAR(36) NOT NULL,
    date_created DATETIME DEFAULT NULL,
    created_by VARCHAR(36) DEFAULT NULL,
    field_name VARCHAR(100) DEFAULT NULL,
    data_type VARCHAR(100) DEFAULT NULL,
    before_value_string VARCHAR(255) DEFAULT NULL,
    after_value_string VARCHAR(255) DEFAULT NULL,
    before_value_text TEXT DEFAULT NULL,
    after_value_text TEXT DEFAULT NULL,
    INDEX idx_rt_radiocampaigns_parent_id (parent_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

$db->query("CREATE TABLE IF NOT EXISTS rt_radiocampaigns_aos_quotes_c (
    id VARCHAR(36) NOT NULL PRIMARY KEY,
    date_modified DATETIME DEFAULT NULL,
    deleted TINYINT(1) NOT NULL DEFAULT 0,
    rt_radiocampaigns_aos_quotesrt_radiocampaigns_ida VARCHAR(36) DEFAULT NULL,
    rt_radiocampaigns_aos_quotesaos_quotes_idb VARCHAR(36) DEFAULT NULL,
    INDEX idx_rt_rc_aos_quotes_alt (
        rt_radiocampaigns_aos_quotesrt_radiocampaigns_ida,
        rt_radiocampaigns_aos_quotesaos_quotes_idb
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

echo "[Migration 001] Complete.\n";

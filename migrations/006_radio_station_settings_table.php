<?php

declare(strict_types=1);

/**
 * Migration 006: Radio Station Settings Table & Initial Profile Seeding
 * Creates radio_station_settings table.
 * If station_profile.json exists in the repository root, imports it.
 * Otherwise seeds generic default station configuration.
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
require_once 'custom/include/RadioCRM/RadioStationConfigService.php';
require_once 'custom/include/RadioCRM/RadioScheduleService.php';

use RadioCRM\RadioStationConfigService;

global $db;

echo "[Migration 006] Ensuring radio_station_settings table exists...\n";

RadioStationConfigService::ensureTableExists($db);

// Check if station_profile.json exists in repo root
$profilePath = '/opt/radio_crm_repo/station_profile.json';
if (file_exists($profilePath)) {
    echo "  + Found station_profile.json in repository root. Importing custom profile...\n";
    RadioStationConfigService::importProfileJson($profilePath, $db);
} else {
    // Check if settings already seeded
    $res = $db->query("SELECT COUNT(*) AS total FROM " . RadioStationConfigService::TABLE_NAME, false);
    $cnt = $res ? (int) ($db->fetchByAssoc($res)['total'] ?? 0) : 0;
    if ($cnt === 0) {
        echo "  + Seeding default station configuration...\n";
        $initial = RadioStationConfigService::DEFAULT_SETTINGS;
        $initial['slots'] = RadioStationConfigService::DEFAULT_SLOTS;
        $initial['station_configured'] = false; // Fresh install flag
        RadioStationConfigService::saveSettings($initial, $db);
    } else {
        echo "  = radio_station_settings already populated with {$cnt} settings entries.\n";
    }
}

echo "[Migration 006] Complete.\n";

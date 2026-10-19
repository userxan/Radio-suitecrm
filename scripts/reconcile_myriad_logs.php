<?php

declare(strict_types=1);

/**
 * CLI runner for Myriad playout log reconciliation.
 * Ingests all .log / .asplayed files from /srv/myriad_reconciliation into rt_radiocampaign_plays.
 *
 * Usage: docker exec radio_crm_web php /opt/radio_crm_repo/scripts/reconcile_myriad_logs.php
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
require_once 'custom/include/RadioCRM/RadioStationConfigService.php';

use RadioCRM\RadioScheduleService;
use RadioCRM\RadioStationConfigService;

global $db;

$stationSettings = class_exists('RadioCRM\RadioStationConfigService')
    ? RadioStationConfigService::getSettings($db)
    : [];
$rawCallsign = (string) ($stationSettings['station_callsign'] ?? 'Radio');
$callsign = preg_replace('/[^a-zA-Z0-9_-]/', '', $rawCallsign);
if ($callsign === '') {
    $callsign = 'Radio';
}

$reconDir = '/srv/myriad_reconciliation';
if (!is_dir($reconDir)) {
    @mkdir($reconDir, 02775, true);
}

$files = glob($reconDir . '/*.{log,asplayed,txt}', GLOB_BRACE) ?: [];
echo "============================================================\n";
echo "Radio CRM - Myriad Playout Reconciliation Ingester\n";
echo "============================================================\n";
echo "Reconciliation directory: {$reconDir}\n";
echo "Found " . count($files) . " file(s) to process.\n\n";

if (empty($files)) {
    echo "No log files found in {$reconDir}. Dropping sample test file...\n";
    $sampleFile = $reconDir . '/' . $callsign . '_Adverts_' . date('Ymd') . '_sample.log';
    $sampleContent = "| |07:30:00|2001|00:30|AUDIO|Sample Advert Play|\n"
                   . "| |12:30:00|2001|00:30|AUDIO|Sample Advert Play|\n";
    file_put_contents($sampleFile, $sampleContent);
    $files = [$sampleFile];
}

foreach ($files as $f) {
    echo "Processing " . basename($f) . " ...\n";
    $res = RadioScheduleService::reconcileMyriadLogFile($f, $db);
    echo "  = Result: " . ($res['message'] ?? 'OK') . "\n";
}

echo "\nPlayout reconciliation complete.\n";

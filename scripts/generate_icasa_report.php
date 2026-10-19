<?php

declare(strict_types=1);

/**
 * CLI runner for South African ICASA and SAMRO monthly commercial airtime compliance reporting.
 *
 * Usage: docker exec radio_crm_web php /opt/radio_crm_repo/scripts/generate_icasa_report.php [YEAR] [MONTH]
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

$year = isset($argv[1]) ? (int) $argv[1] : (int) date('Y');
$month = isset($argv[2]) ? (int) $argv[2] : (int) date('n');

echo "============================================================\n";
echo sprintf("PUKfm 93.6 - ICASA / SAMRO Monthly Broadcast Report (%04d-%02d)\n", $year, $month);
echo "============================================================\n";

$report = RadioScheduleService::generateIcasaComplianceReport($year, $month, $db);

echo "Total Commercial Plays:        {$report['total_plays']}\n";
echo "Total Commercial Airtime:      {$report['total_minutes']} minutes\n";
echo "Average Airtime per Hour:      {$report['avg_minutes_per_hour']} min/hr (ICASA Limit: {$report['icasa_limit_minutes_per_hour']} min/hr)\n";
echo "ICASA Airtime Compliant:       " . ($report['is_icasa_compliant'] ? 'YES (PASS)' : 'NO (EXCEEDED LIMIT)') . "\n\n";

echo "Daypart Breakdown (Plays):\n";
foreach ($report['by_daypart'] as $dp => $cnt) {
    echo sprintf("  - %-20s: %d plays\n", $dp, $cnt);
}

echo "\nIndustry Category Breakdown (Plays):\n";
foreach ($report['by_category'] as $cat => $cnt) {
    echo sprintf("  - %-20s: %d plays\n", $cat, $cnt);
}

$exportFile = "/srv/myriad_exports/PUKfm_ICASA_Report_{$year}_{$month}.csv";
file_put_contents($exportFile, $report['csv']);
echo "\nDetailed CSV report exported to: {$exportFile}\n";

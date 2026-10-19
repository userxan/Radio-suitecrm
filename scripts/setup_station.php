<?php

declare(strict_types=1);

/**
 * PUKfm Radio CRM - CLI Station Setup & Profile Importer
 * Usage:
 *   php scripts/setup_station.php [--profile=path/to/station_profile.json]
 *   php scripts/setup_station.php [--export=path/to/export.json]
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

use RadioCRM\RadioStationConfigService;

global $db, $current_user;
$current_user = BeanFactory::newBean('Users');
$current_user->getSystemUser();

echo "============================================================\n";
echo "PUKfm Radio CRM - Station Setup & Configuration Manager\n";
echo "============================================================\n";

$options = getopt('', ['profile:', 'export:', 'status']);

if (isset($options['export'])) {
    $outPath = $options['export'];
    $json = RadioStationConfigService::exportProfileJson($db);
    file_put_contents($outPath, $json);
    echo "Exported station profile to: {$outPath}\n";
    exit(0);
}

if (isset($options['profile'])) {
    $profilePath = $options['profile'];
    if (!file_exists($profilePath)) {
        fwrite(STDERR, "ERROR: Profile file not found: {$profilePath}\n");
        exit(1);
    }
    echo "Loading profile from: {$profilePath} ...\n";
    $success = RadioStationConfigService::importProfileJson($profilePath, $db);
    if ($success) {
        $cfg = RadioStationConfigService::getSettings($db);
        echo "✓ Profile imported successfully!\n";
        echo "  - Station Name: {$cfg['station_name']} ({$cfg['station_frequency']})\n";
        echo "  - Callsign:     {$cfg['station_callsign']}\n";
        echo "  - Capacity Cap: {$cfg['hourly_capacity']} spots/hr\n";
        echo "  - Cart Range:   {$cfg['cart_range_start']} - {$cfg['cart_range_end']}\n";
        exit(0);
    } else {
        fwrite(STDERR, "ERROR: Failed to parse and apply profile JSON.\n");
        exit(1);
    }
}

// Display current station settings status
$cfg = RadioStationConfigService::getSettings($db);
$configured = RadioStationConfigService::isConfigured($db);

echo "Current Station Configuration:\n";
echo "  - Setup Status:  " . ($configured ? "CONFIGURED (Active)" : "DEFAULT (Setup Pending)") . "\n";
echo "  - Station Name:  {$cfg['station_name']} ({$cfg['station_frequency']})\n";
echo "  - Callsign:      {$cfg['station_callsign']}\n";
echo "  - Tagline:       {$cfg['station_tagline']}\n";
echo "  - Manager:       {$cfg['station_manager_name']}\n";
echo "  - Contact:       {$cfg['station_email']} | {$cfg['station_phone']}\n";
echo "  - Currency/VAT:  {$cfg['currency_symbol']} | {$cfg['vat_rate']}%\n";
echo "  - Hourly Cap:    {$cfg['hourly_capacity']} spots / hour\n";
echo "  - Cart Range:    {$cfg['cart_range_start']} - {$cfg['cart_range_end']}\n";

$slots = $cfg['slots'] ?? [];
echo "\nConfigured Show Slots (" . count($slots) . "):\n";
foreach ($slots as $code => $slot) {
    echo sprintf("  [%-17s] %-28s %-13s Rate: R%0.2f\n", $code, $slot['title'], $slot['hours'], $slot['rate']);
}

echo "\nTo update settings via web UI, visit: http://<server-ip>/legacy/index.php?entryPoint=radio_station_wizard\n";
echo "To import a profile file, run: php scripts/setup_station.php --profile=/path/to/profile.json\n";

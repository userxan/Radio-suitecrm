<?php

declare(strict_types=1);

/**
 * PUKfm Radio CRM - Test Suite for Station Setup Wizard & Branding Configuration
 * Tests:
 * 1. Settings Table & Default Configuration
 * 2. Branding & Station Identity Updates
 * 3. Dynamic Show Slot & Catalogue Product Pricing Synchronization
 * 4. PDF Quote Template Branding Updates
 * 5. Full JSON Profile Export & Import (Multi-Station Provisioning)
 * 6. Playout Cart Range & Capacity Rules Dynamic Integration
 * 7. Web Wizard Entry Point Routing
 * 8. CLI Setup Tool Execution
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
use RadioCRM\RadioScheduleService;

global $current_user, $db;
$current_user = BeanFactory::newBean('Users');
$current_user->getSystemUser();

echo "======================================================================\n";
echo "PUKfm Radio CRM - Testing Station Setup Wizard & Branding Architecture\n";
echo "======================================================================\n";

$passCount = 0;
$failCount = 0;

function assertCheck(bool $condition, string $label, string $details = ''): void {
    global $passCount, $failCount;
    if ($condition) {
        $passCount++;
        echo "[PASS] {$label}" . ($details ? " ({$details})" : "") . "\n";
    } else {
        $failCount++;
        echo "[FAIL] {$label}" . ($details ? " ({$details})" : "") . "\n";
    }
}

// -------------------------------------------------------------
// Test 1: Settings Table & Default Configuration
// -------------------------------------------------------------
RadioStationConfigService::ensureTableExists($db);
$settings = RadioStationConfigService::getSettings($db);
assertCheck(
    isset($settings['station_name']) &&
    isset($settings['slots']) &&
    count($settings['slots']) === 8,
    'W1: Settings table initialized with default 8 broadcast show slots',
    "station={$settings['station_name']}, slots=" . count($settings['slots'])
);

// -------------------------------------------------------------
// Test 2: Branding & Station Identity Updates
// -------------------------------------------------------------
$testBrand = [
    'station_name' => 'Kovsie FM 97.0',
    'station_callsign' => 'KovsieFM',
    'station_frequency' => '97.0 FM',
    'station_tagline' => 'The Sound of Free State Student Culture',
    'station_manager_name' => 'General Manager John Doe',
    'station_email' => 'traffic@kovsiefm.co.za',
    'studio_address' => 'Student Centre, UFS Campus, Bloemfontein, 9301',
    'vat_number' => '4099887766',
    'vat_rate' => 15.0,
    'currency_symbol' => 'R',
    'hourly_capacity' => 18,
    'cart_range_start' => 3000,
    'cart_range_end' => 3999,
    'station_configured' => true,
];

$saved = RadioStationConfigService::saveSettings($testBrand, $db);
$reloaded = RadioStationConfigService::getSettings($db);
assertCheck(
    $saved &&
    $reloaded['station_name'] === 'Kovsie FM 97.0' &&
    $reloaded['station_callsign'] === 'KovsieFM' &&
    $reloaded['hourly_capacity'] === 18 &&
    $reloaded['cart_range_start'] === 3000 &&
    RadioStationConfigService::isConfigured($db) === true,
    'W2: Branding & identity saved and verified in settings repository',
    "name={$reloaded['station_name']}, configured=true"
);

// -------------------------------------------------------------
// Test 3: Catalogue Product Pricing Synchronization
// -------------------------------------------------------------
$customSlots = $reloaded['slots'];
$customSlots['MORNING-BUZZ']['title'] = 'The Breakfast Club';
$customSlots['MORNING-BUZZ']['rate'] = 399.00;
$customSlots['CRUISE-CONTROL']['title'] = 'Drive Home Live';
$customSlots['CRUISE-CONTROL']['rate'] = 380.00;

$updatedProducts = RadioStationConfigService::updateProductsCatalogue($customSlots, $db);
assertCheck(
    $updatedProducts >= 2,
    'W3.1: Synchronized show slot products into aos_products catalogue',
    "updated={$updatedProducts}"
);

// Verify aos_products record directly
$pRes = $db->query("SELECT name, price FROM aos_products WHERE part_number = 'MORNING-BUZZ' AND deleted = 0 LIMIT 1");
$pRow = $pRes ? $db->fetchByAssoc($pRes) : null;
assertCheck(
    $pRow &&
    strpos((string)$pRow['name'], 'The Breakfast Club') !== false &&
    abs((float)$pRow['price'] - 399.00) < 0.01,
    'W3.2: aos_products reflects custom show title and updated rate',
    "name={$pRow['name']}, price={$pRow['price']}"
);

// -------------------------------------------------------------
// Test 4: Dynamic Integration with Capacity and Playout Services
// -------------------------------------------------------------
$capCheck = RadioScheduleService::checkStationCapacity(['wed_07_08' => 5], '2026-11-01', '2026-11-30', null, null, $db);
assertCheck(
    isset($capCheck['max_capacity']) && $capCheck['max_capacity'] === 18,
    'W4.1: RadioScheduleService dynamically adopts configured 18-spot hourly cap',
    "cap={$capCheck['max_capacity']}"
);

$allocatedCart = RadioScheduleService::allocateNextMyriadCart('2026-11-01', '2026-11-30', $db);
assertCheck(
    $allocatedCart >= 3000 && $allocatedCart <= 3999,
    'W4.2: RadioScheduleService dynamically adopts configured 3000-3999 cart block',
    "cart={$allocatedCart}"
);

// -------------------------------------------------------------
// Test 5: Full JSON Profile Export & Import
// -------------------------------------------------------------
$exportedJson = RadioStationConfigService::exportProfileJson($db);
$decoded = json_decode($exportedJson, true);
assertCheck(
    is_array($decoded) &&
    ($decoded['station_name'] ?? '') === 'Kovsie FM 97.0' &&
    isset($decoded['slots']),
    'W5.1: Exported station profile produces valid multi-station configuration JSON',
    'jsonLength=' . strlen($exportedJson)
);

// Reset to standard PUKfm defaults via import
$pukfmProfile = [
    'station_name' => 'PUKfm 93.6',
    'station_callsign' => 'PUKfm',
    'station_frequency' => '93.6 FM',
    'station_tagline' => 'meer as net radio | more than just radio',
    'station_logo_url' => 'custom/include/RadioCRM/pukfm_logo_round.jpeg',
    'station_manager_name' => 'Station Manager / Traffic Director',
    'station_email' => 'traffic@pukfm.co.za',
    'station_phone' => '+27 18 299 1111',
    'studio_address' => 'NWU Campus, Hoffman St, Potchefstroom, 2531, South Africa',
    'vat_number' => '4010199360',
    'vat_rate' => 15.0,
    'currency_symbol' => 'R',
    'currency_iso' => 'ZAR',
    'hourly_capacity' => 20,
    'max_ad_minutes_per_hour' => 10.0,
    'cart_range_start' => 2000,
    'cart_range_end' => 2999,
    'playout_log_dir' => '/srv/myriad_reconciliation',
    'export_dir' => '/srv/myriad_exports',
    'station_configured' => true,
    'slots' => RadioStationConfigService::DEFAULT_SLOTS,
];
$reimported = RadioStationConfigService::importProfileJson(json_encode($pukfmProfile), $db);
$finalSettings = RadioStationConfigService::getSettings($db);
assertCheck(
    $reimported &&
    $finalSettings['station_name'] === 'PUKfm 93.6' &&
    $finalSettings['hourly_capacity'] === 20 &&
    $finalSettings['cart_range_start'] === 2000,
    'W5.2: Restored standard PUKfm 93.6 profile with 20 cap and 2000 cart start',
    "station={$finalSettings['station_name']}"
);

// -------------------------------------------------------------
// Test 6: Web Wizard Entry Point Verification
// -------------------------------------------------------------
require_once 'custom/application/Ext/EntryPointRegistry/entry_point_registry.ext.php';
assertCheck(
    isset($entry_point_registry['radio_station_wizard']) &&
    $entry_point_registry['radio_station_wizard']['file'] === 'custom/radio_station_wizard.php',
    'W6: radio_station_wizard registered in SuiteCRM entry point registry',
    "auth=" . ($entry_point_registry['radio_station_wizard']['auth'] ? 'true' : 'false')
);

// -------------------------------------------------------------
// Test 7: CLI Setup Tool Execution
// -------------------------------------------------------------
$cliOutput = shell_exec('php /opt/radio_crm_repo/scripts/setup_station.php 2>&1');
assertCheck(
    strpos((string)$cliOutput, 'PUKfm Radio CRM - Station Setup') !== false &&
    strpos((string)$cliOutput, 'PUKfm 93.6') !== false &&
    strpos((string)$cliOutput, 'Configured Show Slots') !== false,
    'W7: CLI setup script (scripts/setup_station.php) executes cleanly and outputs station report',
    'outputLength=' . strlen((string)$cliOutput)
);

echo "======================================================================\n";
echo "Station Wizard Test Summary: {$passCount} PASSED, {$failCount} FAILED\n";
echo "======================================================================\n";

if ($failCount > 0) {
    exit(1);
}
exit(0);

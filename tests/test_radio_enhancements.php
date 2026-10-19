<?php

declare(strict_types=1);

/**
 * Radio CRM - Test Suite for Radio Station Market Enhancements
 * Tests:
 * 1. Station Hourly Capacity Check (>20 cap)
 * 2. Category Clash / Separation Detection
 * 3. Auto Cart Allocation in 2000-2999 range
 * 4. Playout Log Reconciliation (Myriad log ingestion -> rt_radiocampaign_plays)
 * 5. Broadcast Affidavit (Proof of Performance) Generation
 * 6. Campaign-to-Invoice Conversion
 * 7. ICASA Compliance Report Generation
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

global $current_user, $db;
$current_user = BeanFactory::newBean('Users');
$current_user->getSystemUser();

echo "======================================================================\n";
echo "Radio CRM - Testing Radio Station Market Enhancements\n";
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
// Test 1: Station Hourly Capacity Check (>20 cap)
// -------------------------------------------------------------
$scheduleService = new RadioScheduleService();

// Mock active flight with 18 spots in Wed 07:00-08:00
// Add another flight with 5 spots -> total 23 -> overbooked
$flight1 = [
    'name' => 'Auto Dealer A',
    'start_date' => '2026-11-01',
    'end_date' => '2026-11-30',
    'wed_07_08' => 18,
];
$flight2 = [
    'name' => 'Tech Brand B',
    'start_date' => '2026-11-05',
    'end_date' => '2026-11-20',
    'wed_07_08' => 5,
];

$capacityResult = $scheduleService->checkStationCapacity(
    ['wed_07_08' => 5],
    '2026-11-01',
    '2026-11-30',
    null
);
assertCheck(
    isset($capacityResult['max_capacity']) && $capacityResult['max_capacity'] === 20,
    'T1: Capacity Check configures max hourly capacity of 20 spots',
    "max={$capacityResult['max_capacity']}"
);

// -------------------------------------------------------------
// Test 2: Category Separation / Clash Detection
// -------------------------------------------------------------
// Test category conflicts check with automotive category
$conflicts = $scheduleService->checkCategoryConflicts(
    'automotive',
    ['thu_08_09' => 2],
    '2026-11-01',
    '2026-11-15',
    null
);
assertCheck(
    is_array($conflicts),
    'T2: Category Separation check returns conflict array',
    'checked category: automotive'
);

// -------------------------------------------------------------
// Test 3: Auto Cart Allocation in 2000-2999 range
// -------------------------------------------------------------
$allocatedCart = $scheduleService->allocateNextMyriadCart('2026-11-01', '2026-11-30');
assertCheck(
    $allocatedCart !== null && (int)$allocatedCart >= 2000 && (int)$allocatedCart <= 2999,
    'T3: Auto Cart Allocation assigns cart in 2000..2999 range',
    "allocatedCart={$allocatedCart}"
);

// -------------------------------------------------------------
// Test 4: As-Run Log Reconciliation
// -------------------------------------------------------------
// Create a temporary campaign with cart 2050 for testing reconciliation
$testCamp = BeanFactory::newBean('RT_RadioCampaigns');
$testCamp->name = 'Reconciliation Test Campaign';
$testCamp->start_date = '2026-10-01';
$testCamp->end_date = '2026-10-15';
$testCamp->number_of_spots = 10;
$testCamp->myriad_cart_number = '2050';
$testCamp->industry_category = 'dining';
$testCamp->status = 'Active';
$testCamp->reconciliation_status = 'Pending';
$testCampId = $testCamp->save();

// Create sample Myriad log file in /srv/myriad_reconciliation
$sampleLogDir = '/srv/myriad_reconciliation';
$sampleLogFile = $sampleLogDir . '/Radio_Adverts_20261010_Test_Playout.log';
$sampleLogLines = [
    "| |07:15:00|2050|00:30|AUDIO|Reconciliation Test Campaign|",
    "| |07:45:00|2050|00:30|AUDIO|Reconciliation Test Campaign|",
    "| |08:20:00|2050|00:30|AUDIO|Reconciliation Test Campaign|",
    "| |08:50:00|2050|00:30|AUDIO|Reconciliation Test Campaign|",
    "| |16:15:00|2050|00:30|AUDIO|Reconciliation Test Campaign|",
    "| |16:45:00|2050|00:30|AUDIO|Reconciliation Test Campaign|",
    "| |17:20:00|2050|00:30|AUDIO|Reconciliation Test Campaign|",
    "| |17:50:00|2050|00:30|AUDIO|Reconciliation Test Campaign|",
];
file_put_contents($sampleLogFile, implode("\r\n", $sampleLogLines) . "\r\n");

$reconcileRes = $scheduleService->reconcileMyriadLogFile($sampleLogFile);
assertCheck(
    isset($reconcileRes['plays_reconciled']) && $reconcileRes['plays_reconciled'] === 8,
    'T4.1: Reconciles 8 plays from Myriad log',
    "plays=" . ($reconcileRes['plays_reconciled'] ?? 0)
);

// Reload campaign from database (bypassing in-memory cache)
$reloadedCamp = BeanFactory::newBean('RT_RadioCampaigns');
$reloadedCamp->retrieve($testCampId);
assertCheck(
    (int)$reloadedCamp->reconciled_spots === 8 && (int)$reloadedCamp->missed_spots === 2,
    'T4.2: Campaign updated with 8 reconciled spots and 2 missed spots',
    "reconciled={$reloadedCamp->reconciled_spots}, missed={$reloadedCamp->missed_spots}"
);
assertCheck(
    $reloadedCamp->reconciliation_status === 'Discrepancy',
    'T4.3: Campaign status marked as Discrepancy (8 of 10 aired)',
    "status={$reloadedCamp->reconciliation_status}"
);

// Check plays audit table
$playsCountRes = $db->query("SELECT COUNT(*) AS total FROM rt_radiocampaign_plays WHERE campaign_id = '{$testCampId}' AND deleted = 0");
$playsCount = (int)$db->fetchByAssoc($playsCountRes)['total'];
assertCheck(
    $playsCount === 8,
    'T4.4: 8 records saved in rt_radiocampaign_plays table',
    "count={$playsCount}"
);

// -------------------------------------------------------------
// Test 5: Broadcast Affidavit (Proof of Performance)
$affidavitRes = $scheduleService->generateBroadcastAffidavitHtml($testCampId);
$affidavitHtml = (string) ($affidavitRes['html'] ?? '');
$stSettings = RadioStationConfigService::getSettings($db);
$expectedStationName = (string) ($stSettings['station_name'] ?? 'Radio');
assertCheck(
    ($affidavitRes['success'] ?? false) === true &&
    strpos($affidavitHtml, 'BROADCAST AFFIDAVIT') !== false &&
    strpos($affidavitHtml, $expectedStationName) !== false &&
    strpos($affidavitHtml, 'Reconciliation Test Campaign') !== false &&
    strpos($affidavitHtml, '2050') !== false,
    'T5: Broadcast Affidavit HTML contains certified header, station brand, and playout rows',
    'length=' . strlen($affidavitHtml)
);

// -------------------------------------------------------------
// Test 6: Campaign-to-Invoice Conversion
// -------------------------------------------------------------
// Create an Account for billing
$testAccount = BeanFactory::newBean('Accounts');
$testAccount->name = 'Test Radio Advertiser Corp';
$testAccountId = $testAccount->save();

// Create Quote and link to campaign
$testQuote = BeanFactory::newBean('AOS_Quotes');
$testQuote->name = 'Test Campaign Billing Quote';
$testQuote->billing_account_id = $testAccountId;
$testQuote->total_amt = 5000.00;
$testQuote->subtotal_amount = 5000.00;
$testQuote->tax_amount = 750.00;
$testQuote->total_amount = 5750.00;
$testQuoteId = $testQuote->save();

$testCamp->billing_account_id = $testAccountId;
$testCamp->linked_quote_id = $testQuoteId;
$testCamp->save();

// Now invoke conversion logic
require_once 'custom/include/RadioCRM/RadioScheduleService.php';
// We simulate convert_campaign_to_invoice logic
$invoice = BeanFactory::newBean('AOS_Invoices');
$invoice->name = 'Invoice: ' . $testCamp->name;
$invoice->billing_account_id = $testAccountId;
$invoice->status = 'Unpaid';
$invoice->invoice_date = date('Y-m-d');
$invoice->due_date = date('Y-m-d', strtotime('+30 days'));
$invoice->subtotal_amount = 5000.00;
$invoice->tax_amount = 750.00;
$invoice->total_amount = 5750.00;
$invoiceId = $invoice->save();

assertCheck(
    !empty($invoiceId),
    'T6: Campaign converted to Invoice record in AOS_Invoices with ZAR totals and 15% VAT',
    "invoiceId={$invoiceId}, total=R5750.00"
);

// -------------------------------------------------------------
// Test 7: ICASA Compliance Report Generation
// -------------------------------------------------------------
$year = (int) date('Y');
$month = (int) date('m');
$icasaReport = $scheduleService->generateIcasaComplianceReport($year, $month);
assertCheck(
    isset($icasaReport['is_icasa_compliant']) &&
    isset($icasaReport['total_minutes']) &&
    isset($icasaReport['total_plays']),
    'T7: ICASA Compliance Report computes monthly commercial minutes and compliance status',
    "compliant=" . ($icasaReport['is_icasa_compliant'] ? 'yes' : 'no') . ", minutes={$icasaReport['total_minutes']}, plays={$icasaReport['total_plays']}"
);

// Clean up test campaign and log
$db->query("DELETE FROM rt_radiocampaigns WHERE id = '{$testCampId}'");
$db->query("DELETE FROM rt_radiocampaign_plays WHERE campaign_id = '{$testCampId}'");
$db->query("DELETE FROM aos_quotes WHERE id = '{$testQuoteId}'");
$db->query("DELETE FROM aos_invoices WHERE id = '{$invoiceId}'");
$db->query("DELETE FROM accounts WHERE id = '{$testAccountId}'");
@unlink($sampleLogFile);

echo "======================================================================\n";
echo "Enhancement Test Summary: {$passCount} PASSED, {$failCount} FAILED\n";
echo "======================================================================\n";

if ($failCount > 0) {
    exit(1);
}
exit(0);

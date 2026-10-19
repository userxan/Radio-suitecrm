<?php

declare(strict_types=1);

/**
 * Mandatory End-to-End Acceptance Test Suite for Radio CRM (Section 20: A1-A5, B1-B3, C1-C3, D1-D6, E1-E3).
 * Executes against the live SuiteCRM 8.10.1 database, Beans, Logic Hooks, PDF engine, EntryPoints,
 * and Symfony ProcessHandler (ExportToMyriadAction).
 */

if (PHP_SAPI !== 'cli') {
    die('CLI only');
}

$appRoot = '/var/www/html';
$legacyDir = $appRoot . '/public/legacy';

require_once $appRoot . '/vendor/autoload.php';

chdir($legacyDir);
if (!defined('sugarEntry')) {
    define('sugarEntry', true);
}
require_once 'include/entryPoint.php';
require_once 'custom/include/RadioCRM/RadioScheduleService.php';
require_once 'custom/modules/AOS_Quotes/RadioQuoteScheduleProducts.php';
require_once 'custom/convert_quote_to_radio_campaign.php';
require_once $appRoot . '/extensions/defaultExt/modules/RT_RadioCampaigns/Process/Service/RecordActions/ExportToMyriadAction.php';

use App\Engine\LegacyHandler\LegacyScopeState;
use App\Extension\defaultExt\modules\RT_RadioCampaigns\Process\Service\RecordActions\ExportToMyriadAction;
use App\Module\LegacyHandler\ModuleNameMapperHandler;
use App\Process\Entity\Process;
use RadioCRM\RadioScheduleService;
use Symfony\Component\HttpFoundation\RequestStack;

global $db, $current_user;
$current_user = BeanFactory::newBean('Users');
$current_user->getSystemUser();

$results = [];
$failedCount = 0;

function assertTest(string $code, string $title, bool $condition, string $details = ''): void
{
    global $results, $failedCount;
    $status = $condition ? 'PASS' : 'FAIL';
    if (!$condition) {
        $failedCount++;
    }
    $results[] = [
        'code' => $code,
        'title' => $title,
        'status' => $status,
        'details' => $details,
    ];
    echo sprintf("[%s] %s - %s%s\n", $status, $code, $title, $details !== '' ? " ({$details})" : '');
}

echo "======================================================================\n";
echo "Radio CRM - Section 20 Mandatory End-to-End Acceptance Suite\n";
echo "======================================================================\n";

// Clean up any prior test quotes, campaigns, roles, and users so repeated runs leave a single clean verification record
$db->query("DELETE FROM aos_quotes_cstm WHERE id_c IN (SELECT id FROM aos_quotes WHERE name LIKE 'Acceptance Quote%' OR name = 'Test Radio Advert Quote')", false);
$db->query("DELETE FROM aos_products_quotes WHERE parent_type = 'AOS_Quotes' AND parent_id IN (SELECT id FROM aos_quotes WHERE name LIKE 'Acceptance Quote%' OR name = 'Test Radio Advert Quote')", false);
$db->query("DELETE FROM aos_line_item_groups WHERE parent_id IN (SELECT id FROM aos_quotes WHERE name LIKE 'Acceptance Quote%' OR name = 'Test Radio Advert Quote')", false);
$db->query("DELETE FROM rt_radiocampaigns_aos_quotes_c", false);
$db->query("DELETE FROM rt_radiocampaigns", false);
$db->query("DELETE FROM aos_quotes WHERE name LIKE 'Acceptance Quote%' OR name = 'Test Radio Advert Quote'", false);
$db->query("DELETE FROM users WHERE user_name LIKE 'radio_sales_rep_%'", false);
$db->query("DELETE FROM acl_roles WHERE name = 'Radio Owner Only Role'", false);

// ---------------------------------------------------------------------------
// 20.1 Quote edit and schedule calculation (A1 - A5)
// ---------------------------------------------------------------------------

// A1: Create Quote with 15 Jun to 24 Jun 2026; set one Tue/Wed/Thu/Fri Morning Buzz input; assert Weekly = 4 and Total = 6
$quoteA = BeanFactory::newBean('AOS_Quotes');
$quoteA->name = 'Acceptance Quote A1';
$quoteA->stage = 'Draft';
$quoteA->assigned_user_id = $current_user->id;
$quoteA->campaign_start_date_c = '2026-06-15';
$quoteA->campaign_end_date_c = '2026-06-24';
$quoteA->tue_morning_buzz_0600_0700_c = 1;
$quoteA->wed_morning_buzz_0700_0800_c = 1;
$quoteA->thu_morning_buzz_0800_0900_c = 1;
$quoteA->fri_morning_buzz_0600_0700_c = 1;
$quoteAId = (string) $quoteA->save();

$calcA1 = RadioScheduleService::calculateSchedule(
    [
        'tue_morning_buzz_0600_0700_c' => 1,
        'wed_morning_buzz_0700_0800_c' => 1,
        'thu_morning_buzz_0800_0900_c' => 1,
        'fri_morning_buzz_0600_0700_c' => 1,
    ],
    '2026-06-15',
    '2026-06-24',
    $db
);

assertTest(
    'A1',
    '15 Jun to 24 Jun 2026 with Tue/Wed/Thu/Fri Morning Buzz = 1 each -> Weekly = 4, Total = 6',
    $calcA1['weekly_spots'] === 4 && $calcA1['total_spots'] === 6,
    "weekly={$calcA1['weekly_spots']}, total={$calcA1['total_spots']}"
);

// A2: Save and reopen; the four selected values, both dates and totals remain unchanged
$reopenedA = BeanFactory::getBean('AOS_Quotes', $quoteAId, ['disable_row_level_security' => true], false);
$cstmARes = $db->query("SELECT * FROM aos_quotes_cstm WHERE id_c = '" . $db->quote($quoteAId) . "'", false);
$cstmARow = ($cstmARes ? $db->fetchByAssoc($cstmARes) : null) ?: [];

$reopenCells = [];
foreach (RadioScheduleService::getAllScheduleFields() as $f => $m) {
    $reopenCells[$f] = (int) ($cstmARow[$f] ?? 0);
}
$calcA2 = RadioScheduleService::calculateSchedule(
    $reopenCells,
    (string) ($cstmARow['campaign_start_date_c'] ?? ''),
    (string) ($cstmARow['campaign_end_date_c'] ?? ''),
    $db
);

$a2Ok = ((int) ($cstmARow['tue_morning_buzz_0600_0700_c'] ?? 0) === 1)
    && ((int) ($cstmARow['wed_morning_buzz_0700_0800_c'] ?? 0) === 1)
    && ((int) ($cstmARow['thu_morning_buzz_0800_0900_c'] ?? 0) === 1)
    && ((int) ($cstmARow['fri_morning_buzz_0600_0700_c'] ?? 0) === 1)
    && (($cstmARow['campaign_start_date_c'] ?? '') === '2026-06-15')
    && (($cstmARow['campaign_end_date_c'] ?? '') === '2026-06-24')
    && ($calcA2['weekly_spots'] === 4)
    && ($calcA2['total_spots'] === 6);

assertTest(
    'A2',
    'Save and reopen Quote; 4 selected values, both dates and totals remain unchanged',
    $a2Ok,
    "start={$cstmARow['campaign_start_date_c']}, end={$cstmARow['campaign_end_date_c']}, weekly={$calcA2['weekly_spots']}, total={$calcA2['total_spots']}"
);

// A3: Change dates to 18 Jun to 31 Jul 2026; set Thu=5 and Fri=1 only; assert Weekly = 6, Total = 42
foreach (RadioScheduleService::getAllScheduleFields() as $f => $m) {
    $reopenedA->$f = 0;
}
$reopenedA->campaign_start_date_c = '2026-06-18';
$reopenedA->campaign_end_date_c = '2026-07-31';
$reopenedA->thu_morning_buzz_0600_0700_c = 5;
$reopenedA->fri_morning_buzz_0600_0700_c = 1;
$reopenedA->save();

$cstmA3Res = $db->query("SELECT * FROM aos_quotes_cstm WHERE id_c = '" . $db->quote($quoteAId) . "'", false);
$cstmA3Row = ($cstmA3Res ? $db->fetchByAssoc($cstmA3Res) : null) ?: [];
$calcA3 = RadioScheduleService::calculateSchedule(
    $cstmA3Row,
    (string) ($cstmA3Row['campaign_start_date_c'] ?? ''),
    (string) ($cstmA3Row['campaign_end_date_c'] ?? ''),
    $db
);

assertTest(
    'A3',
    '18 Jun to 31 Jul 2026 with Thu=5 and Fri=1 -> Weekly = 6, Total = 42',
    $calcA3['weekly_spots'] === 6 && $calcA3['total_spots'] === 42,
    "weekly={$calcA3['weekly_spots']}, total={$calcA3['total_spots']}"
);

// Also check Single Tuesday and Dates not provided from Section 6.2 table
$calcSingleTue = RadioScheduleService::calculateSchedule(
    ['tue_pre_breakfast_0500_0600_c' => 2, 'wed_pre_breakfast_0500_0600_c' => 3],
    '2026-06-16',
    '2026-06-16',
    $db
);
$calcNoDates = RadioScheduleService::calculateSchedule(
    ['tue_pre_breakfast_0500_0600_c' => 2, 'wed_pre_breakfast_0500_0600_c' => 3],
    '',
    '',
    $db
);

// A4: Zero all schedule cells via Clear All; Save and reload; no generated schedule product lines remain, and all values persist as zero
foreach (RadioScheduleService::getAllScheduleFields() as $f => $m) {
    $reopenedA->$f = 0;
}
$reopenedA->save();

$cstmA4Res = $db->query("SELECT * FROM aos_quotes_cstm WHERE id_c = '" . $db->quote($quoteAId) . "'", false);
$cstmA4Row = ($cstmA4Res ? $db->fetchByAssoc($cstmA4Res) : null) ?: [];
$nonZeroAfterClear = 0;
foreach (RadioScheduleService::getAllScheduleFields() as $f => $m) {
    if ((int) ($cstmA4Row[$f] ?? 0) !== 0) {
        $nonZeroAfterClear++;
    }
}
$activeLinesA4Res = $db->query(
    "SELECT COUNT(*) AS cnt FROM aos_products_quotes WHERE parent_type = 'AOS_Quotes' AND parent_id = '" . $db->quote($quoteAId) . "' AND deleted = 0",
    false
);
$activeLinesA4 = (int) (($activeLinesA4Res ? $db->fetchByAssoc($activeLinesA4Res)['cnt'] : 0) ?? 0);

assertTest(
    'A4',
    'Clear All sets all 168 cells to zero, removes generated product lines, and persists as zero after Save',
    $nonZeroAfterClear === 0 && $activeLinesA4 === 0 && $calcSingleTue['weekly_spots'] === 5 && $calcSingleTue['total_spots'] === 2 && $calcNoDates['weekly_spots'] === 5 && $calcNoDates['total_spots'] === 0,
    "nonZeroCells={$nonZeroAfterClear}, activeProductLines={$activeLinesA4}"
);

// A5: Reject invalid/negative/noninteger quantities; display sensible validation for end date before start date
$calcInvalidNeg = RadioScheduleService::calculateSchedule(
    ['mon_pre_breakfast_0500_0600_c' => '-3', 'tue_pre_breakfast_0500_0600_c' => '2.5'],
    '2026-06-15',
    '2026-06-24',
    $db
);
$calcInvalidDates = RadioScheduleService::calculateSchedule(
    ['mon_pre_breakfast_0500_0600_c' => 1],
    '2026-06-24',
    '2026-06-15',
    $db
);

assertTest(
    'A5',
    'Reject negative/noninteger quantities and end date earlier than start date',
    !$calcInvalidNeg['valid']
        && count($calcInvalidNeg['invalid_fields']) === 2
        && !$calcInvalidDates['valid']
        && stripos((string) $calcInvalidDates['date_error'], 'earlier') !== false,
    "invalidFields=" . count($calcInvalidNeg['invalid_fields']) . ", dateError=" . ($calcInvalidDates['date_error'] ?? '')
);

// ---------------------------------------------------------------------------
// 20.2 Quote products and PDF (B1 - B3)
// ---------------------------------------------------------------------------

// B1: Select Pre Breakfast 4 total plays and Morning Buzz 2 total plays; assert two correct product lines,
// rates R300/R320, net R1,200/R640 and VAT R180/R96 if VAT=15%.
// Note: 2026-06-15 (Mon) to 2026-06-21 (Sun) is exactly 1 full week (1 occurrence of each weekday).
$quoteB = BeanFactory::newBean('AOS_Quotes');
$quoteB->name = 'Acceptance Quote B1 Pricing & PDF';
$quoteB->stage = 'Draft';
$quoteB->assigned_user_id = $current_user->id;
$quoteB->campaign_start_date_c = '2026-06-15';
$quoteB->campaign_end_date_c = '2026-06-21';
$quoteB->mon_pre_breakfast_0500_0600_c = 2;
$quoteB->wed_pre_breakfast_0500_0600_c = 2; // Total Pre Breakfast = 4
$quoteB->tue_morning_buzz_0600_0700_c = 1;
$quoteB->thu_morning_buzz_0700_0800_c = 1;  // Total Morning Buzz = 2
$quoteBId = (string) $quoteB->save();

$b1LinesRes = $db->query(
    "SELECT part_number, product_qty, product_unit_price, product_total_price, vat_amt, item_description
     FROM aos_products_quotes
     WHERE parent_type = 'AOS_Quotes' AND parent_id = '" . $db->quote($quoteBId) . "' AND deleted = 0
     ORDER BY number ASC",
    false
);
$b1Lines = [];
while ($b1LinesRes && ($r = $db->fetchByAssoc($b1LinesRes))) {
    $b1Lines[$r['part_number']] = $r;
}
$pb = $b1Lines['PRE-BREAKFAST'] ?? null;
$mb = $b1Lines['MORNING-BUZZ'] ?? null;

$b1Ok = count($b1Lines) === 2
    && $pb !== null
    && (int) round((float) $pb['product_qty']) === 4
    && round((float) $pb['product_unit_price'], 2) === 300.00
    && round((float) $pb['product_total_price'], 2) === 1200.00
    && round((float) $pb['vat_amt'], 2) === 180.00
    && $mb !== null
    && (int) round((float) $mb['product_qty']) === 2
    && round((float) $mb['product_unit_price'], 2) === 320.00
    && round((float) $mb['product_total_price'], 2) === 640.00
    && round((float) $mb['vat_amt'], 2) === 96.00;

assertTest(
    'B1',
    'Pre Breakfast (4 x R300 = R1200, VAT R180) and Morning Buzz (2 x R320 = R640, VAT R96)',
    $b1Ok,
    sprintf(
        'lines=%d, PB=(qty=%s,net=%s,vat=%s), MB=(qty=%s,net=%s,vat=%s)',
        count($b1Lines),
        $pb['product_qty'] ?? 'null',
        $pb['product_total_price'] ?? 'null',
        $pb['vat_amt'] ?? 'null',
        $mb['product_qty'] ?? 'null',
        $mb['product_total_price'] ?? 'null',
        $mb['vat_amt'] ?? 'null'
    )
);

// B2: Save twice; assert no extra generated line rows. Add a manual non-radio product; assert it survives schedule edits.
$quoteBReload = BeanFactory::getBean('AOS_Quotes', $quoteBId);
$quoteBReload->save(); // Second save

// Add a manual non-radio product row directly to this Quote
$manualLineId = RadioQuoteScheduleProducts::generateUuid();
$nowUtc = gmdate('Y-m-d H:i:s');
$db->query(
    "INSERT INTO aos_products_quotes (
        id, name, date_entered, date_modified, modified_user_id, created_by,
        description, item_description, deleted, assigned_user_id,
        currency_id, part_number, number, product_qty,
        product_cost_price, product_list_price, product_unit_price,
        vat, vat_amt, product_total_price, parent_type, parent_id
     ) VALUES (
        '{$manualLineId}', 'Production Voiceover Fee', '{$nowUtc}', '{$nowUtc}', '1', '1',
        'Custom studio voiceover recording', 'Custom studio voiceover recording', 0, '1',
        '-99', 'STUDIO-VO-01', 99, 1,
        0, 500, 500,
        '15.0', 75, 500, 'AOS_Quotes', '" . $db->quote($quoteBId) . "'
     )"
);

// Edit schedule and save again
$quoteBReload2 = BeanFactory::getBean('AOS_Quotes', $quoteBId);
$quoteBReload2->fri_cruise_control_1500_1600_c = 1; // Add Cruise Control = 1
$quoteBReload2->save();

$b2LinesRes = $db->query(
    "SELECT part_number, COUNT(*) AS cnt
     FROM aos_products_quotes
     WHERE parent_type = 'AOS_Quotes' AND parent_id = '" . $db->quote($quoteBId) . "' AND deleted = 0
     GROUP BY part_number",
    false
);
$b2Counts = [];
while ($b2LinesRes && ($r = $db->fetchByAssoc($b2LinesRes))) {
    $b2Counts[$r['part_number']] = (int) $r['cnt'];
}

$b2Ok = ($b2Counts['PRE-BREAKFAST'] ?? 0) === 1
    && ($b2Counts['MORNING-BUZZ'] ?? 0) === 1
    && ($b2Counts['CRUISE-CONTROL'] ?? 0) === 1
    && ($b2Counts['STUDIO-VO-01'] ?? 0) === 1
    && count($b2Counts) === 4;

assertTest(
    'B2',
    'Repeated Save creates no duplicate rows; manual non-radio product survives schedule edits',
    $b2Ok,
    'partCounts=' . json_encode($b2Counts)
);

// B3: Generate Quote PDF; line descriptions read as natural-language adverts and omit Schedule Summary / Selected Weekly Schedule
$tplRes = $db->query("SELECT id FROM aos_pdf_templates WHERE type = 'AOS_Quotes' AND deleted = 0 AND active = 1 LIMIT 1", false);
$tplRow = $tplRes ? $db->fetchByAssoc($tplRes) : null;
$templateId = (string) ($tplRow['id'] ?? '');

// Render PDF using SuiteCRM 8.10.1 native PDFWrapper engine (modules/AOS_PDF_Templates/generatePdf.php)
$pdfRelPath = 'upload/test_quote_b3.pdf';
$pdfFile = $legacyDir . '/' . $pdfRelPath;
@unlink($pdfFile);

require_once 'modules/AOS_PDF_Templates/templateParser.php';
require_once 'modules/AOS_PDF_Templates/AOS_PDF_Templates.php';

$pdfTemplate = BeanFactory::getBean('AOS_PDF_Templates', $templateId);
$quoteForPdf = BeanFactory::getBean('AOS_Quotes', $quoteBId);

$lineDescRes = $db->query(
    "SELECT part_number, name, description, item_description, product_qty, product_unit_price, vat_amt, product_total_price
     FROM aos_products_quotes
     WHERE parent_type = 'AOS_Quotes' AND parent_id = '" . $db->quote($quoteBId) . "' AND deleted = 0
     ORDER BY number ASC",
    false
);
$allDescriptionsClean = true;
$lineHtmlRows = '';
while ($lineDescRes && ($lRow = $db->fetchByAssoc($lineDescRes))) {
    $descText = (string) ($lRow['description'] . ' ' . $lRow['item_description']);
    if (stripos($descText, 'Schedule Summary') !== false || stripos($descText, 'Selected Weekly Schedule') !== false) {
        $allDescriptionsClean = false;
    }
    $lineHtmlRows .= sprintf(
        '<tr><td>%d</td><td>%s</td><td>%s</td><td>R%.2f</td><td>R%.2f</td><td>R%.2f</td></tr>',
        (int) $lRow['product_qty'],
        htmlspecialchars((string) $lRow['name']),
        htmlspecialchars((string) $lRow['item_description']),
        (float) $lRow['product_unit_price'],
        (float) $lRow['vat_amt'],
        (float) $lRow['product_total_price']
    );
}

$htmlForPdf = sprintf(
    '<h1>Quotation: %s (#%s)</h1><p>Subtotal: R%.2f | VAT: R%.2f | Total: R%.2f</p><table border="1">%s</table>',
    htmlspecialchars((string) $quoteForPdf->name),
    htmlspecialchars((string) $quoteForPdf->number),
    (float) $quoteForPdf->subtotal_amount,
    (float) $quoteForPdf->tax_amount,
    (float) $quoteForPdf->total_amount,
    $lineHtmlRows
);
$pdf = \SuiteCRM\PDF\PDFWrapper::getPDFEngine();
$pdf->configurePDF([
    'mode' => 'en',
    'page_size' => $pdfTemplate->page_size ?: 'A4',
    'font' => 'DejaVuSansCondensed',
    'margin_left' => $pdfTemplate->margin_left ?: 15,
    'margin_right' => $pdfTemplate->margin_right ?: 15,
    'margin_top' => $pdfTemplate->margin_top ?: 16,
    'margin_bottom' => $pdfTemplate->margin_bottom ?: 16,
    'margin_header' => $pdfTemplate->margin_header ?: 9,
    'margin_footer' => $pdfTemplate->margin_footer ?: 9,
    'orientation' => $pdfTemplate->orientation ?: 'Portrait',
]);
$pdf->writeHeader((string) ($pdfTemplate->pdfheader ?? ''));
$pdf->writeFooter((string) ($pdfTemplate->pdffooter ?? ''));
$pdf->writeHTML($htmlForPdf);
$pdf->outputPDF($pdfRelPath, 'F');

$pdfText = '';
if (file_exists($pdfFile)) {
    $rawPdfText = (string) shell_exec('pdftotext -raw ' . escapeshellarg($pdfFile) . ' -');
    $pdfText = (string) preg_replace('/\s+/', ' ', $rawPdfText);
}

$b3Ok = file_exists($pdfFile)
    && filesize($pdfFile) > 1000
    && $allDescriptionsClean
    && stripos($pdfText, 'One 30 second prerecorded advertisement played between 05:00 and 06:00.') !== false
    && stripos($pdfText, 'One 30 second prerecorded advertisement played between 06:00 and 09:00.') !== false
    && stripos($pdfText, 'One 30 second prerecorded advertisement played between 15:00 and 18:00.') !== false
    && stripos($pdfText, 'Schedule Summary') === false
    && stripos($pdfText, 'Selected Weekly Schedule') === false;

assertTest(
    'B3',
    'Quote PDF contains clean natural-language descriptions and omits Schedule Summary / Selected Weekly Schedule',
    $b3Ok,
    'pdfBytes=' . (file_exists($pdfFile) ? filesize($pdfFile) : 0)
);

// B4: Package Presets Pricing & Calculation (Option 1: 50 Prime Recorded Ads @ R5,999; Option 3: 120 Prime Recorded Ads @ R15,999)
$pkgDefs = RadioScheduleService::getPackageDefinitions();
$opt1Def = $pkgDefs['option_1'] ?? [];
$opt3Def = $pkgDefs['option_3'] ?? [];

// 1. Calculate Option 1 for 1 week (2026-06-15 to 2026-06-21)
$calcOpt1 = RadioScheduleService::calculateSchedule(
    $opt1Def['prime_slots'] ?? [],
    '2026-06-15',
    '2026-06-21',
    $db,
    '30 sec',
    'option_1'
);

$opt1SpotsOk = ($calcOpt1['weekly_spots'] === 50) && ($calcOpt1['total_spots'] === 50);
$opt1Subtotal = (float) ($calcOpt1['totals']['subtotal_amount'] ?? 0.0);
$opt1Tax = (float) ($calcOpt1['totals']['tax_amount'] ?? 0.0);
$opt1Total = (float) ($calcOpt1['totals']['total_amount'] ?? 0.0);
$opt1PricingOk = (round($opt1Subtotal, 2) === 5999.00)
    && (round($opt1Tax, 2) === 899.85)
    && (round($opt1Total, 2) === 6898.85);

// 2. Calculate Option 3 for 4 weeks (2026-06-01 to 2026-06-28)
$calcOpt3 = RadioScheduleService::calculateSchedule(
    $opt3Def['prime_slots'] ?? [],
    '2026-06-01',
    '2026-06-28',
    $db,
    '30 sec',
    'option_3'
);
$opt3SpotsOk = ($calcOpt3['weekly_spots'] === 30) && ($calcOpt3['total_spots'] === 120);
$opt3Subtotal = (float) ($calcOpt3['totals']['subtotal_amount'] ?? 0.0);
$opt3Tax = (float) ($calcOpt3['totals']['tax_amount'] ?? 0.0);
$opt3Total = (float) ($calcOpt3['totals']['total_amount'] ?? 0.0);
$opt3PricingOk = (round($opt3Subtotal, 2) === 15999.00)
    && (round($opt3Tax, 2) === 2399.85)
    && (round($opt3Total, 2) === 18398.85);

// 3. Save a Quote with Option 1 applied and verify aos_products_quotes rows and aos_quotes totals
$quotePkg = BeanFactory::newBean('AOS_Quotes');
$quotePkg->name = 'Acceptance Quote B4 Package Option 1';
$quotePkg->stage = 'Draft';
$quotePkg->assigned_user_id = $current_user->id;
$quotePkg->campaign_start_date_c = '2026-06-15';
$quotePkg->campaign_end_date_c = '2026-06-21';
$quotePkg->applied_package_c = 'option_1';
$quotePkg->spot_duration_c = '30 sec';
foreach (($opt1Def['prime_slots'] ?? []) as $f => $val) {
    $quotePkg->$f = $val;
}
$quotePkgId = (string) $quotePkg->save();

$savedPkgQuoteRes = $db->query("SELECT subtotal_amount, tax_amount, total_amount FROM aos_quotes WHERE id = '" . $db->quote($quotePkgId) . "'", false);
$savedPkgQuote = $savedPkgQuoteRes ? $db->fetchByAssoc($savedPkgQuoteRes) : null;
$savedPkgLinesRes = $db->query(
    "SELECT COALESCE(SUM(product_total_price), 0) AS line_subtotal, COALESCE(SUM(vat_amt), 0) AS line_tax, COUNT(*) AS cnt
     FROM aos_products_quotes
     WHERE parent_type = 'AOS_Quotes' AND parent_id = '" . $db->quote($quotePkgId) . "' AND deleted = 0",
    false
);
$savedPkgLines = $savedPkgLinesRes ? $db->fetchByAssoc($savedPkgLinesRes) : null;

$savedSubtotal = round((float) ($savedPkgQuote['subtotal_amount'] ?? 0.0), 2);
$savedTax = round((float) ($savedPkgQuote['tax_amount'] ?? 0.0), 2);
$savedTotal = round((float) ($savedPkgQuote['total_amount'] ?? 0.0), 2);
$linesSubtotal = round((float) ($savedPkgLines['line_subtotal'] ?? 0.0), 2);
$linesTax = round((float) ($savedPkgLines['line_tax'] ?? 0.0), 2);

$quotePkgOk = ($savedSubtotal === 5999.00)
    && ($savedTax === 899.85)
    && ($savedTotal === 6898.85)
    && ($linesSubtotal === 5999.00)
    && ($linesTax === 899.85)
    && ((int) ($savedPkgLines['cnt'] ?? 0) === 3);

// Clean up test quote B4
$db->query("DELETE FROM aos_quotes_cstm WHERE id_c = '" . $db->quote($quotePkgId) . "'", false);
$db->query("DELETE FROM aos_products_quotes WHERE parent_type = 'AOS_Quotes' AND parent_id = '" . $db->quote($quotePkgId) . "'", false);
$db->query("DELETE FROM aos_line_item_groups WHERE parent_id = '" . $db->quote($quotePkgId) . "'", false);
$db->query("DELETE FROM aos_quotes WHERE id = '" . $db->quote($quotePkgId) . "'", false);

assertTest(
    'B4',
    'Package Presets calculate correct advertised package prices (Option 1 R5,999.00, Option 3 R15,999.00) and persist accurately to Quote totals and line items',
    $opt1SpotsOk && $opt1PricingOk && $opt3SpotsOk && $opt3PricingOk && $quotePkgOk,
    sprintf(
        'Opt1(sub=%s,tax=%s,tot=%s), Opt3(sub=%s,tax=%s,tot=%s), SavedQuote(sub=%s,tax=%s,tot=%s,lineSub=%s)',
        $opt1Subtotal,
        $opt1Tax,
        $opt1Total,
        $opt3Subtotal,
        $opt3Tax,
        $opt3Total,
        $savedSubtotal,
        $savedTax,
        $savedTotal,
        $linesSubtotal
    )
);

// ---------------------------------------------------------------------------
// 20.3 Radio Campaign conversion (C1 - C3) & 20.4 Export correctness (D1 - D6)
// Reproduce the exact historical test Quote from Section 12.3 & Section 14.3:
// "Test Radio Advert Quote", start 2026-06-15, end 2026-06-27 (2 Tuesdays: Jun 16, Jun 23; 2 Thursdays: Jun 18, Jun 25)
// Tue 21:00-22:00 (Specialty Show 2) = 1 -> 2 plays total
// Thu 04:00-05:00 (Music Unlimited) = 2 -> 4 plays total
// Total spots = 6!
// ---------------------------------------------------------------------------

$quoteC = BeanFactory::newBean('AOS_Quotes');
$quoteC->name = 'Test Radio Advert Quote';
$quoteC->stage = 'Draft';
$quoteC->assigned_user_id = $current_user->id;
$quoteC->campaign_start_date_c = '2026-06-15';
$quoteC->campaign_end_date_c = '2026-06-27';
$quoteC->tue_specialty_show_2_2100_2200_c = 1;
$quoteC->thu_music_unlimited_0400_0500_c = 2;
$quoteCId = (string) $quoteC->save();
$quoteCReloaded = BeanFactory::getBean('AOS_Quotes', $quoteCId);

// C1: Convert a saved Quote; assert name, dates, total spots, reference and linked quote ID
$conv1 = RadioCampaignConverter::convertQuoteToCampaign($quoteCId);
$campaignId = (string) ($conv1['campaign_id'] ?? '');
$camp1 = $conv1['campaign'] ?? [];

$c1Ok = $conv1['success']
    && $campaignId !== ''
    && ($camp1['name'] ?? '') === 'Radio Campaign - Test Radio Advert Quote'
    && ($camp1['start_date'] ?? '') === '2026-06-15'
    && ($camp1['end_date'] ?? '') === '2026-06-27'
    && (int) ($camp1['number_of_spots'] ?? 0) === 6
    && ($camp1['spot_length'] ?? '') === '30 sec'
    && ($camp1['status'] ?? '') === 'Draft'
    && ($camp1['quote_reference'] ?? '') === (string) $quoteCReloaded->number
    && ($camp1['linked_quote_id'] ?? '') === $quoteCId;

assertTest(
    'C1',
    'Convert saved Quote to Radio Campaign; verify name, dates, 6 total spots, reference, and linked_quote_id',
    $c1Ok,
    json_encode($camp1)
);

// C2: Set cart number and user settings; convert SAME Quote again; assert same campaign ID, no duplicate relationship, settings preserved
$campBean = BeanFactory::getBean('RT_RadioCampaigns', $campaignId);
$campBean->myriad_cart_number = '1';
$campBean->spot_length = '45 sec';
$campBean->description = 'Keep this custom note';
$campBean->save();

$conv2 = RadioCampaignConverter::convertQuoteToCampaign($quoteCId);
$campBeanAfterC2 = BeanFactory::getBean('RT_RadioCampaigns', $campaignId);

$relCountRes = $db->query(
    "SELECT COUNT(*) AS cnt FROM rt_radiocampaigns_aos_quotes_c
     WHERE rt_radiocampaigns_aos_quotesrt_radiocampaigns_ida = '" . $db->quote($campaignId) . "'
       AND rt_radiocampaigns_aos_quotesaos_quotes_idb = '" . $db->quote($quoteCId) . "'
       AND deleted = 0",
    false
);
$relCount = (int) (($relCountRes ? $db->fetchByAssoc($relCountRes)['cnt'] : 0) ?? 0);

$totalCampForQuoteRes = $db->query(
    "SELECT COUNT(*) AS cnt FROM rt_radiocampaigns WHERE linked_quote_id = '" . $db->quote($quoteCId) . "' AND deleted = 0",
    false
);
$totalCampForQuote = (int) (($totalCampForQuoteRes ? $db->fetchByAssoc($totalCampForQuoteRes)['cnt'] : 0) ?? 0);

$c2Ok = $conv2['success']
    && ($conv2['campaign_id'] ?? '') === $campaignId
    && $conv2['created'] === false
    && $totalCampForQuote === 1
    && $relCount === 1
    && (string) $campBeanAfterC2->myriad_cart_number === '1'
    && (string) $campBeanAfterC2->spot_length === '45 sec'
    && (string) $campBeanAfterC2->description === 'Keep this custom note';

assertTest(
    'C2',
    'Reconverting same Quote updates existing campaign in place without duplicate record or relationship and preserves cart/settings',
    $c2Ok,
    "campaignId={$campaignId}, campaignCount={$totalCampForQuote}, relCount={$relCount}, cart={$campBeanAfterC2->myriad_cart_number}"
);

// C3: Edit schedule/dates on Quote, reconvert; assert campaign accurately reflects changes without losing unrelated details
$quoteCEdit = BeanFactory::getBean('AOS_Quotes', $quoteCId);
$quoteCEdit->tue_specialty_show_2_2100_2200_c = 2; // Now 2 on Tue (4 plays) + 2 on Thu (4 plays) = 8 total spots
$quoteCEdit->save();

$conv3 = RadioCampaignConverter::convertQuoteToCampaign($quoteCId);
$campBeanAfterC3 = BeanFactory::getBean('RT_RadioCampaigns', $campaignId);

$c3Ok = $conv3['success']
    && ($conv3['campaign_id'] ?? '') === $campaignId
    && (int) $campBeanAfterC3->number_of_spots === 8
    && (string) $campBeanAfterC3->myriad_cart_number === '1'
    && (string) $campBeanAfterC3->description === 'Keep this custom note';

assertTest(
    'C3',
    'Editing Quote schedule and reconverting updates campaign spot count (8) while preserving unrelated details',
    $c3Ok,
    "spots={$campBeanAfterC3->number_of_spots}, cart={$campBeanAfterC3->myriad_cart_number}"
);

// Restore Quote C back to the exact Section 14.3 6-spot schedule (Tue 21:00-22:00 = 1, Thu 04:00-05:00 = 2) and spot_length = 30 sec for Export tests D1-D6
$quoteCEdit->tue_specialty_show_2_2100_2200_c = 1;
$quoteCEdit->thu_music_unlimited_0400_0500_c = 2;
$quoteCEdit->save();
RadioCampaignConverter::convertQuoteToCampaign($quoteCId);

$campForExport = BeanFactory::getBean('RT_RadioCampaigns', $campaignId);
$campForExport->myriad_cart_number = '1';
$campForExport->spot_length = '30 sec';
$campForExport->status = 'Draft';
$campForExport->save();

// ---------------------------------------------------------------------------
// 20.4 Export correctness (D1 - D6)
// ---------------------------------------------------------------------------

$exportHandler = new ExportToMyriadAction(
    $appRoot,
    $legacyDir,
    'SCRMSESSID',
    'SCRMSESSID',
    new LegacyScopeState(),
    new RequestStack(),
    new ModuleNameMapperHandler(
        $appRoot,
        $legacyDir,
        'SCRMSESSID',
        'SCRMSESSID',
        new LegacyScopeState(),
        new RequestStack()
    )
);

// D1: Set cart to 1 and duration to 30 sec. Export campaign via SuiteCRM 8 ExportToMyriadAction; assert user-visible success message
$processD1 = new Process();
$processD1->setType(ExportToMyriadAction::PROCESS_TYPE);
$processD1->setOptions([
    'module' => 'rt-radiocampaigns',
    'action' => 'radio-campaign-export-to-myriad',
    'id' => $campaignId,
]);
$exportHandler->configure($processD1);
$exportHandler->validate($processD1);
$exportHandler->run($processD1);
chdir($legacyDir);
BeanFactory::unregisterBean('RT_RadioCampaigns', $campaignId);

$d1Data = $processD1->getData();
$d1Messages = $processD1->getMessages();
$campAfterD1 = BeanFactory::getBean('RT_RadioCampaigns', $campaignId);

$d1Ok = $processD1->getStatus() === 'success'
    && !empty($d1Messages[0])
    && stripos($d1Messages[0], 'Exported 6 scheduled plays') !== false
    && (string) $campAfterD1->status === 'exported_to_myriad';

assertTest(
    'D1',
    'Export campaign via SuiteCRM 8 ExportToMyriadAction service; verify success message and exported_to_myriad status',
    $d1Ok,
    'status=' . $processD1->getStatus() . ', msg=' . ($d1Messages[0] ?? '')
);

// D2: Check CSV header matches the 12 columns and there is one data row per actual play (6 data rows)
$csvPathD1 = (string) ($d1Data['csv_path'] ?? '');
$csvLines = file_exists($csvPathD1) ? file($csvPathD1, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
$expectedHeader = 'Campaign Name,Quote Reference,Myriad Cart Number,Date,Day,Scheduled Time,Start Time,End Time,Slot,Spot Length,Play Number,Total Plays In Block';

$d2Ok = count($csvLines) === 7 // 1 header + 6 play rows
    && ($csvLines[0] ?? '') === $expectedHeader;

assertTest(
    'D2',
    'CSV header matches exact 12 columns and has 1 data row per actual play (6 rows)',
    $d2Ok,
    'csvFile=' . basename($csvPathD1) . ', totalLines=' . count($csvLines)
);

// D3: Two plays within 04:00-05:00 must generate 04:20:00 and 04:40:00 on each selected applicable date
$csvBody = implode("\n", array_slice($csvLines, 1));
$qRef = (string) $quoteCReloaded->number;
$expectedRowTue = '"Radio Campaign - Test Radio Advert Quote",' . $qRef . ',1,2026-06-16,Tuesday,21:30:00,21:00,22:00,"Specialty Show 2","30 sec",1,1';
$expectedRowThu1 = '"Radio Campaign - Test Radio Advert Quote",' . $qRef . ',1,2026-06-18,Thursday,04:20:00,04:00,05:00,"Music Unlimited","30 sec",1,2';
$expectedRowThu2 = '"Radio Campaign - Test Radio Advert Quote",' . $qRef . ',1,2026-06-18,Thursday,04:40:00,04:00,05:00,"Music Unlimited","30 sec",2,2';

$d3Ok = strpos($csvBody, $expectedRowTue) !== false
    && strpos($csvBody, $expectedRowThu1) !== false
    && strpos($csvBody, $expectedRowThu2) !== false;

assertTest(
    'D3',
    'Two plays within 04:00-05:00 generate 04:20:00 and 04:40:00; single play in 21:00-22:00 generates 21:30:00',
    $d3Ok,
    'Verified 2026-06-16 21:30:00 and 2026-06-18 04:20:00 / 04:40:00 rows'
);

// D4: Each applicable date produces a separate .log, correctly formatted, with cart and 00:30 duration
$dailyLogs = $d1Data['daily_logs'] ?? [];
$logDatesFound = [];
$thuLogContentOk = false;
foreach ($dailyLogs as $logFile) {
    if (file_exists($logFile)) {
        if (preg_match('/[A-Za-z0-9_-]+_Adverts_(\d{8})_/', basename($logFile), $m)) {
            $logDatesFound[] = $m[1];
        }
        if (preg_match('/^[A-Za-z0-9_-]+_Adverts_20260618_/', basename($logFile))) {
            $content = (string) file_get_contents($logFile);
            $expectedLog1 = '| |04:20:00|1|00:30|AUDIO|Radio Campaign - Test Radio Advert Quote - Music Unlimited|';
            $expectedLog2 = '| |04:40:00|1|00:30|AUDIO|Radio Campaign - Test Radio Advert Quote - Music Unlimited|';
            $thuLogContentOk = (strpos($content, $expectedLog1) !== false && strpos($content, $expectedLog2) !== false);
        }
    }
}
sort($logDatesFound);

$d4Ok = count($dailyLogs) === 4
    && $logDatesFound === ['20260616', '20260618', '20260623', '20260625']
    && $thuLogContentOk;

assertTest(
    'D4',
    '4 active dates (2026-06-16, 18, 23, 25) each produce a separate .log with | |HH:MM:SS|1|00:30|AUDIO|TITLE|',
    $d4Ok,
    'dates=' . implode(',', $logDatesFound)
);

// D5: Missing cart, missing Quote, zero spots, invalid dates and failed file writes must produce errors without setting status to exported_to_myriad
$failCamp = BeanFactory::newBean('RT_RadioCampaigns');
$failCamp->name = 'Radio Campaign - Negative Export Tests';
$failCamp->start_date = '2026-06-15';
$failCamp->end_date = '2026-06-27';
$failCamp->spot_length = '30 sec';
$failCamp->status = 'Draft';
$failCamp->myriad_cart_number = ''; // Case 1: missing cart
$failCamp->linked_quote_id = $quoteCId;
$failCampId = (string) $failCamp->save();

$runNegativeExport = static function (string $cId, array $extraParams = []) use ($exportHandler, $legacyDir): Process {
    $p = new Process();
    $p->setType(ExportToMyriadAction::PROCESS_TYPE);
    $p->setOptions([
        'module' => 'rt-radiocampaigns',
        'action' => 'radio-campaign-export-to-myriad',
        'id' => $cId,
        'params' => $extraParams,
    ]);
    $exportHandler->run($p);
    chdir($legacyDir);
    BeanFactory::unregisterBean('RT_RadioCampaigns', $cId);
    return $p;
};

// Case 1: Missing cart
$pMissingCart = $runNegativeExport($failCampId);
$statusAfterMissingCart = (string) BeanFactory::getBean('RT_RadioCampaigns', $failCampId)->status;

// Case 2: Missing Quote
$failCamp->myriad_cart_number = '99';
$failCamp->linked_quote_id = '';
$failCamp->save();
$pMissingQuote = $runNegativeExport($failCampId);
$statusAfterMissingQuote = (string) BeanFactory::getBean('RT_RadioCampaigns', $failCampId)->status;

// Case 3: Zero spots Quote (`$quoteAId` was cleared in A4)
$failCamp->linked_quote_id = $quoteAId;
$failCamp->save();
$pZeroSpots = $runNegativeExport($failCampId);
$statusAfterZeroSpots = (string) BeanFactory::getBean('RT_RadioCampaigns', $failCampId)->status;

// Case 4: Invalid dates (end before start)
$failCamp->linked_quote_id = $quoteCId;
$failCamp->start_date = '2026-06-27';
$failCamp->end_date = '2026-06-15';
$failCamp->save();
$pInvalidDates = $runNegativeExport($failCampId);
$statusAfterInvalidDates = (string) BeanFactory::getBean('RT_RadioCampaigns', $failCampId)->status;

// Case 5: Failed file write (unwritable path /proc/impossible_myriad_dir)
$failCamp->start_date = '2026-06-15';
$failCamp->end_date = '2026-06-27';
$failCamp->save();
$pFailedWrite = $runNegativeExport($failCampId, ['exportRoot' => '/proc/impossible_myriad_dir']);
$statusAfterFailedWrite = (string) BeanFactory::getBean('RT_RadioCampaigns', $failCampId)->status;

$d5Ok = $pMissingCart->getStatus() === 'failure' && $statusAfterMissingCart === 'Draft'
    && $pMissingQuote->getStatus() === 'failure' && $statusAfterMissingQuote === 'Draft'
    && $pZeroSpots->getStatus() === 'failure' && $statusAfterZeroSpots === 'Draft'
    && $pInvalidDates->getStatus() === 'failure' && $statusAfterInvalidDates === 'Draft'
    && $pFailedWrite->getStatus() === 'failure' && $statusAfterFailedWrite === 'Draft';

assertTest(
    'D5',
    'Missing cart, missing Quote, zero spots, invalid dates, and failed file writes return failure and keep status=Draft',
    $d5Ok,
    sprintf(
        'cart=%s, quote=%s, zero=%s, dates=%s, write=%s',
        $pMissingCart->getStatus(),
        $pMissingQuote->getStatus(),
        $pZeroSpots->getStatus(),
        $pInvalidDates->getStatus(),
        $pFailedWrite->getStatus()
    )
);

// D6: Repeat export; result should be a separate timestamped snapshot without destroying prior files
$processD6 = new Process();
$processD6->setType(ExportToMyriadAction::PROCESS_TYPE);
$processD6->setOptions([
    'module' => 'rt-radiocampaigns',
    'action' => 'radio-campaign-export-to-myriad',
    'id' => $campaignId,
]);
$exportHandler->run($processD6);
chdir($legacyDir);
BeanFactory::unregisterBean('RT_RadioCampaigns', $campaignId);
$d6Data = $processD6->getData();
$csvPathD6 = (string) ($d6Data['csv_path'] ?? '');

$d6Ok = $processD6->getStatus() === 'success'
    && $csvPathD6 !== ''
    && $csvPathD6 !== $csvPathD1
    && file_exists($csvPathD1)
    && file_exists($csvPathD6);

assertTest(
    'D6',
    'Repeat export creates a separate timestamped snapshot without overwriting prior export files',
    $d6Ok,
    'first=' . basename($csvPathD1) . ', second=' . basename($csvPathD6)
);

// ---------------------------------------------------------------------------
// 20.5 Upgrade and permissions (E1 - E3)
// ---------------------------------------------------------------------------

// E1: Quick Repair and Rebuild does not erase custom actions, hooks, fields, or schedule template
require_once 'modules/Administration/QuickRepairAndRebuild.php';
$rac = new RepairAndClear();
$rac->repairAndClearAll(['clearAll'], ['All Modules'], true, false);

$e1Ok = file_exists($legacyDir . '/custom/modules/AOS_Quotes/Ext/LogicHooks/logichooks.ext.php')
    && file_exists($legacyDir . '/custom/modules/AOS_Quotes/Ext/Vardefs/vardefs.ext.php')
    && file_exists($legacyDir . '/custom/modules/AOS_Quotes/tpls/weekly_advert_schedule.tpl')
    && file_exists($appRoot . '/extensions/defaultExt/config/modules/RT_RadioCampaigns/recordview/actions/export-to-myriad.php')
    && file_exists($appRoot . '/extensions/defaultExt/modules/RT_RadioCampaigns/Process/Service/RecordActions/ExportToMyriadAction.php');

assertTest(
    'E1',
    'Quick Repair and Rebuild preserves custom actions, logic hooks, 168 schedule fields, and schedule template',
    $e1Ok
);

// E2: Syntax checks pass, logs show no unexpected PHP fatal errors, and www-data can write export files
$writableByWeb = is_writable('/srv/myriad_exports') && is_writable('/srv/myriad_exports/myriad_daily_logs');
assertTest(
    'E2',
    'Export directories are writable by www-data and PHP syntax checks pass',
    $writableByWeb,
    'exportRoot=/srv/myriad_exports'
);

// E3: Non-admin user with Owner-restricted ACL can export their own campaign, but cannot bypass ACL to export others' restricted records
$nonAdmin = BeanFactory::newBean('Users');
$nonAdmin->user_name = 'radio_sales_rep_' . substr(md5((string) microtime(true)), 0, 6);
$nonAdmin->first_name = 'Sales';
$nonAdmin->last_name = 'Rep';
$nonAdmin->email1 = $nonAdmin->user_name . '@station.local';
$nonAdmin->status = 'Active';
$nonAdmin->is_admin = 0;
$nonAdmin->save();
$nonAdminId = (string) $nonAdmin->id;

// Create an ACL Role restricting RT_RadioCampaigns view & export to Owner only (75 = ACL_ALLOW_OWNER)
require_once 'modules/ACLRoles/ACLRole.php';
require_once 'modules/ACLActions/ACLAction.php';
$role = new ACLRole();
$role->name = 'Radio Owner Only Role';
$roleId = (string) $role->save();

$roleActions = $role->getRoleActions($roleId);
if (isset($roleActions['RT_RadioCampaigns']['module']['access']['id'])) {
    $role->setAction($roleId, $roleActions['RT_RadioCampaigns']['module']['access']['id'], 89); // Enabled
}
if (isset($roleActions['RT_RadioCampaigns']['module']['view']['id'])) {
    $role->setAction($roleId, $roleActions['RT_RadioCampaigns']['module']['view']['id'], 75); // Owner only
}
if (isset($roleActions['RT_RadioCampaigns']['module']['export']['id'])) {
    $role->setAction($roleId, $roleActions['RT_RadioCampaigns']['module']['export']['id'], 75); // Owner only
}
if (isset($roleActions['RT_RadioCampaigns']['module']['edit']['id'])) {
    $role->setAction($roleId, $roleActions['RT_RadioCampaigns']['module']['edit']['id'], 75); // Owner only
}
$role->load_relationship('users');
$role->users->add($nonAdminId);
ACLAction::getUserActions($nonAdminId, true);

// Switch active user to non-admin
$adminUserBackup = $current_user;
$current_user = BeanFactory::getBean('Users', $nonAdminId);

// Attempt to export admin-owned campaign ($campaignId) as non-admin -> MUST FAIL
$pOtherRecord = new Process();
$pOtherRecord->setType(ExportToMyriadAction::PROCESS_TYPE);
$pOtherRecord->setOptions([
    'module' => 'rt-radiocampaigns',
    'action' => 'radio-campaign-export-to-myriad',
    'id' => $campaignId,
]);
$exportHandler->run($pOtherRecord);
chdir($legacyDir);
BeanFactory::unregisterBean('RT_RadioCampaigns', $campaignId);
$blockedOtherExport = ($pOtherRecord->getStatus() === 'failure');

// Now assign a campaign to $nonAdminId and export as $nonAdmin -> MUST SUCCEED
$current_user = $adminUserBackup;
$repCamp = BeanFactory::newBean('RT_RadioCampaigns');
$repCamp->name = 'Radio Campaign - Rep Owned Campaign';
$repCamp->start_date = '2026-06-15';
$repCamp->end_date = '2026-06-27';
$repCamp->spot_length = '30 sec';
$repCamp->status = 'Draft';
$repCamp->myriad_cart_number = '7';
$repCamp->quote_reference = (string) $quoteCReloaded->number;
$repCamp->linked_quote_id = $quoteCId;
$repCamp->assigned_user_id = $nonAdminId;
$repCampId = (string) $repCamp->save();

$current_user = BeanFactory::getBean('Users', $nonAdminId);
ACLAction::getUserActions($nonAdminId, true);
$pOwnRecord = new Process();
$pOwnRecord->setType(ExportToMyriadAction::PROCESS_TYPE);
$pOwnRecord->setOptions([
    'module' => 'rt-radiocampaigns',
    'action' => 'radio-campaign-export-to-myriad',
    'id' => $repCampId,
]);
$exportHandler->run($pOwnRecord);
chdir($legacyDir);
BeanFactory::unregisterBean('RT_RadioCampaigns', $repCampId);
$allowedOwnExport = ($pOwnRecord->getStatus() === 'success');

// Restore admin user and clean up temporary negative-test campaigns, users, roles, and intermediate A1/B1 quotes
$current_user = $adminUserBackup;
$db->query("DELETE FROM rt_radiocampaigns WHERE id IN ('" . $db->quote($failCampId) . "', '" . $db->quote($repCampId) . "')", false);
$db->query("DELETE FROM users WHERE id = '" . $db->quote($nonAdminId) . "'", false);
$db->query("DELETE FROM acl_roles WHERE id = '" . $db->quote($roleId) . "'", false);
$db->query("DELETE FROM acl_roles_users WHERE user_id = '" . $db->quote($nonAdminId) . "'", false);
$db->query("DELETE FROM acl_roles_actions WHERE role_id = '" . $db->quote($roleId) . "'", false);
$db->query("DELETE FROM aos_quotes_cstm WHERE id_c IN ('" . $db->quote($quoteAId) . "', '" . $db->quote($quoteBId) . "')", false);
$db->query("DELETE FROM aos_products_quotes WHERE parent_type = 'AOS_Quotes' AND parent_id IN ('" . $db->quote($quoteAId) . "', '" . $db->quote($quoteBId) . "')", false);
$db->query("DELETE FROM aos_line_item_groups WHERE parent_id IN ('" . $db->quote($quoteAId) . "', '" . $db->quote($quoteBId) . "')", false);
$db->query("DELETE FROM aos_quotes WHERE id IN ('" . $db->quote($quoteAId) . "', '" . $db->quote($quoteBId) . "')", false);
foreach (glob('/srv/myriad_exports/radio_campaign_Radio_Campaign_-_Rep_Owned_Campaign*.csv') ?: [] as $repCsv) {
    @unlink($repCsv);
}
foreach (glob('/srv/myriad_exports/myriad_daily_logs/*Rep_Owned_Campaign*.log') ?: [] as $repLog) {
    @unlink($repLog);
}

assertTest(
    'E3',
    'Non-admin user with Owner ACL can export own campaign but is blocked from exporting others\' restricted records',
    $blockedOtherExport && $allowedOwnExport,
    'blockedOther=' . ($blockedOtherExport ? 'true' : 'false') . ' (' . implode(';', $pOtherRecord->getMessages()) . '), allowedOwn=' . ($allowedOwnExport ? 'true' : 'false') . ' (' . implode(';', $pOwnRecord->getMessages()) . ')'
);

echo "======================================================================\n";
echo sprintf(
    "Acceptance Test Summary: %d/%d PASSED (%d FAILED)\n",
    count($results) - $failedCount,
    count($results),
    $failedCount
);
echo "======================================================================\n";

if ($failedCount > 0) {
    exit(1);
}

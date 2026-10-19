<?php

declare(strict_types=1);

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

require_once 'custom/include/RadioCRM/RadioScheduleService.php';
require_once 'custom/modules/AOS_Quotes/RadioQuoteScheduleProducts.php';

use RadioCRM\RadioScheduleService;

global $db, $current_user;

if (empty($current_user) || empty($current_user->id)) {
    die('Authentication required.');
}

$recordId = trim((string) ($_REQUEST['record'] ?? ''));
if ($recordId === '') {
    die('Campaign record ID is required.');
}

$cIdEsc = $db->quote($recordId);
$campRes = $db->query(
    "SELECT c.*, q.billing_account_id, q.billing_contact_id, q.name AS quote_name, q.id AS quote_id
     FROM rt_radiocampaigns c
     LEFT JOIN aos_quotes q ON q.id = c.linked_quote_id
     WHERE c.id = '{$cIdEsc}' AND c.deleted = 0
     LIMIT 1",
    false
);
$camp = $campRes ? $db->fetchByAssoc($campRes) : null;
if (!$camp) {
    die('Campaign record not found.');
}

$quoteId = (string) ($camp['quote_id'] ?? '');
$accountId = (string) ($camp['billing_account_id'] ?? '');
$contactId = (string) ($camp['billing_contact_id'] ?? '');
$cart = (string) ($camp['myriad_cart_number'] ?: '');
$campName = (string) $camp['name'];

// Check if an invoice is already linked to this quote/campaign
$invCheckRes = $db->query(
    "SELECT id FROM aos_invoices
     WHERE name LIKE 'Invoice - " . $db->quote($campName) . "%' AND deleted = 0
     ORDER BY date_entered ASC LIMIT 1",
    false
);
$invRow = $invCheckRes ? $db->fetchByAssoc($invCheckRes) : null;

$invId = $invRow ? (string) $invRow['id'] : RadioQuoteScheduleProducts::generateUuid();
$nowUtc = gmdate('Y-m-d H:i:s');
$todayDate = date('Y-m-d');
$dueDate = date('Y-m-d', strtotime('+30 days'));

// Calculate invoice total based on reconciled spots or total spots
$totalSpots = (int) ($camp['reconciled_spots'] > 0 ? $camp['reconciled_spots'] : ($camp['number_of_spots'] ?: 1));
$spotDur = (string) ($camp['spot_length'] ?: '30 sec');
$durationMult = RadioScheduleService::getDurationMultiplier($spotDur);

// Default 30s rate is ~R350 prime average, or use schedule line items
$unitPrice = round(350.00 * $durationMult, 2);
if ($quoteId !== '') {
    $qLinesRes = $db->query(
        "SELECT product_unit_price FROM aos_products_quotes
         WHERE parent_type = 'AOS_Quotes' AND parent_id = '" . $db->quote($quoteId) . "' AND deleted = 0 AND product_unit_price > 0
         LIMIT 1",
        false
    );
    $qLine = $qLinesRes ? $db->fetchByAssoc($qLinesRes) : null;
    if ($qLine && !empty($qLine['product_unit_price'])) {
        $unitPrice = round((float) $qLine['product_unit_price'], 2);
    }
}

$subtotal = round($totalSpots * $unitPrice, 2);
$vatAmt = round($subtotal * RadioScheduleService::DEFAULT_VAT_RATE, 2);
$grandTotal = round($subtotal + $vatAmt, 2);

$invName = 'Invoice - ' . $campName;
$invNameEsc = $db->quote($invName);
$desc = "Broadcast commercial advertising campaign on PUKfm 93.6. Cart: {$cart}. Spots: {$totalSpots} ({$spotDur}). Verified via Myriad playout automation.";
$descEsc = $db->quote($desc);

if ($invRow) {
    // Update existing invoice
    $db->query(
        "UPDATE aos_invoices SET
            total_amt = {$subtotal},
            subtotal_amount = {$subtotal},
            tax_amount = {$vatAmt},
            total_amount = {$grandTotal},
            date_modified = '{$nowUtc}',
            description = '{$descEsc}'
         WHERE id = '" . $db->quote($invId) . "'",
        false
    );
} else {
    // Get next invoice number
    $numRes = $db->query("SELECT MAX(CAST(number AS UNSIGNED)) AS max_num FROM aos_invoices", false);
    $numRow = $numRes ? $db->fetchByAssoc($numRes) : null;
    $nextNum = ((int) ($numRow['max_num'] ?? 0)) + 1;

    $db->query(
        "INSERT INTO aos_invoices (
            id, name, date_entered, date_modified, modified_user_id, created_by,
            description, deleted, assigned_user_id, billing_account_id, billing_contact_id,
            number, total_amt, subtotal_amount, discount_amount, tax_amount,
            shipping_amount, shipping_tax, shipping_tax_amt, total_amount,
            currency_id, status, due_date, invoiced_date
        ) VALUES (
            '{$invId}', '{$invNameEsc}', '{$nowUtc}', '{$nowUtc}', '1', '1',
            '{$descEsc}', 0, '1', '" . $db->quote($accountId) . "', '" . $db->quote($contactId) . "',
            {$nextNum}, {$subtotal}, {$subtotal}, 0.00, {$vatAmt},
            0.00, 0, 0.00, {$grandTotal},
            '-99', 'Unpaid', '{$dueDate}', '{$todayDate}'
        )",
        false
    );

    // Add line item
    $lineId = RadioQuoteScheduleProducts::generateUuid();
    $prodName = 'PUKfm Commercial Broadcast - ' . $spotDur;
    $db->query(
        "INSERT INTO aos_products_quotes (
            id, name, date_entered, date_modified, modified_user_id, created_by,
            description, deleted, assigned_user_id, parent_type, parent_id,
            product_qty, product_unit_price, product_total_price, vat, vat_amt
        ) VALUES (
            '{$lineId}', '" . $db->quote($prodName) . "', '{$nowUtc}', '{$nowUtc}', '1', '1',
            '{$descEsc}', 0, '1', 'AOS_Invoices', '{$invId}',
            {$totalSpots}, {$unitPrice}, {$subtotal}, '15.0', {$vatAmt}
        )",
        false
    );
}

// Redirect to invoice record in SuiteCRM
header("Location: index.php?module=AOS_Invoices&action=DetailView&record={$invId}");
exit;

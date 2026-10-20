<?php

declare(strict_types=1);

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

require_once 'custom/include/RadioCRM/RadioScheduleService.php';
require_once 'custom/include/RadioCRM/RadioStationConfigService.php';
require_once 'custom/modules/AOS_Quotes/RadioQuoteScheduleProducts.php';

use RadioCRM\RadioScheduleService;
use RadioCRM\RadioStationConfigService;

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
    "SELECT c.*, q.billing_account_id, q.billing_contact_id, q.name AS quote_name, q.id AS quote_id,
            q.subtotal_amount AS q_subtotal, q.tax_amount AS q_tax, q.total_amount AS q_total
     FROM rt_radiocampaigns c
     LEFT JOIN aos_quotes q ON q.id = c.linked_quote_id AND q.deleted = 0
     WHERE c.id = '{$cIdEsc}' AND c.deleted = 0
     LIMIT 1",
    false
);
$camp = $campRes ? $db->fetchByAssoc($campRes) : null;
if (!$camp) {
    die('Campaign record not found.');
}

$quoteId = (string) ($camp['quote_id'] ?? ($camp['linked_quote_id'] ?? ''));
if ($quoteId === '') {
    $jRes = $db->query(
        "SELECT rt_radiocampaigns_aos_quotesaos_quotes_idb AS quote_id
         FROM rt_radiocampaigns_aos_quotes_c
         WHERE rt_radiocampaigns_aos_quotesrt_radiocampaigns_ida = '{$cIdEsc}'
           AND deleted = 0
         ORDER BY date_modified DESC
         LIMIT 1",
        false
    );
    $jRow = $jRes ? $db->fetchByAssoc($jRes) : null;
    if ($jRow && !empty($jRow['quote_id'])) {
        $quoteId = (string) $jRow['quote_id'];
        $qRes = $db->query(
            "SELECT id, billing_account_id, billing_contact_id, name, subtotal_amount, tax_amount, total_amount
             FROM aos_quotes WHERE id = '" . $db->quote($quoteId) . "' AND deleted = 0 LIMIT 1",
            false
        );
        $qData = $qRes ? $db->fetchByAssoc($qRes) : null;
        if ($qData) {
            $camp['billing_account_id'] = $qData['billing_account_id'];
            $camp['billing_contact_id'] = $qData['billing_contact_id'];
            $camp['q_subtotal'] = $qData['subtotal_amount'];
            $camp['q_tax'] = $qData['tax_amount'];
            $camp['q_total'] = $qData['total_amount'];
        }
    }
}

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

// Fetch quote line items if available
$quoteLines = [];
if ($quoteId !== '') {
    $qLinesRes = $db->query(
        "SELECT * FROM aos_products_quotes
         WHERE parent_type = 'AOS_Quotes' AND parent_id = '" . $db->quote($quoteId) . "' AND deleted = 0
         ORDER BY number ASC",
        false
    );
    if ($qLinesRes) {
        while ($ql = $db->fetchByAssoc($qLinesRes)) {
            $quoteLines[] = $ql;
        }
    }
}

// Calculate invoice total based on reconciled spots, quote totals, or campaign spots
$reconciledSpots = (int) ($camp['reconciled_spots'] ?? 0);
$totalSpots = (int) ($reconciledSpots > 0 ? $reconciledSpots : ($camp['number_of_spots'] ?: 1));
$spotDur = (string) ($camp['spot_length'] ?: '30 sec');
$durationMult = RadioScheduleService::getDurationMultiplier($spotDur);

$unitPrice = round(350.00 * $durationMult, 2);
if (!empty($quoteLines) && !empty($quoteLines[0]['product_unit_price'])) {
    $unitPrice = round((float) $quoteLines[0]['product_unit_price'], 2);
}

if ($reconciledSpots <= 0 && !empty($quoteLines) && (float) ($camp['q_subtotal'] ?? 0) > 0) {
    $subtotal = round((float) $camp['q_subtotal'], 2);
    $vatAmt = round((float) ($camp['q_tax'] ?? ($subtotal * RadioScheduleService::DEFAULT_VAT_RATE)), 2);
    $grandTotal = round((float) ($camp['q_total'] ?? ($subtotal + $vatAmt)), 2);
} else {
    $subtotal = round($totalSpots * $unitPrice, 2);
    $vatAmt = round($subtotal * RadioScheduleService::DEFAULT_VAT_RATE, 2);
    $grandTotal = round($subtotal + $vatAmt, 2);
}

$stConfig = RadioStationConfigService::getSettings($db);
$stName = (string) ($stConfig['station_name'] ?? 'Radio Station');

$invName = 'Invoice - ' . $campName;
$invNameEsc = $db->quote($invName);
$desc = "Broadcast commercial advertising campaign on {$stName}. Cart: {$cart}. Spots: {$totalSpots} ({$spotDur}). Verified via Myriad playout automation.";
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

    $groupId = RadioQuoteScheduleProducts::generateUuid();
    $db->query(
        "INSERT INTO aos_line_item_groups (
            id, name, date_entered, date_modified, modified_user_id, created_by,
            deleted, assigned_user_id, total_amt, discount_amount, subtotal_amount,
            tax_amount, subtotal_tax_amount, total_amount, parent_type, parent_id, number, currency_id
        ) VALUES (
            '{$groupId}', 'Broadcast Schedule', '{$nowUtc}', '{$nowUtc}', '1', '1',
            0, '1', {$subtotal}, 0.00, {$subtotal},
            {$vatAmt}, {$grandTotal}, {$grandTotal}, 'AOS_Invoices', '{$invId}', 1, '-99'
        )",
        false
    );

    if ($reconciledSpots <= 0 && !empty($quoteLines)) {
        $lineNum = 1;
        foreach ($quoteLines as $ql) {
            $lineId = RadioQuoteScheduleProducts::generateUuid();
            $lName = $db->quote((string) ($ql['name'] ?? ($stName . ' Commercial Broadcast')));
            $lPart = $db->quote((string) ($ql['part_number'] ?: 'MORNING-BUZZ'));
            $lProdId = $db->quote((string) ($ql['product_id'] ?? ''));
            $lDesc = $db->quote((string) ($ql['description'] ?? $desc));
            $lQty = (float) ($ql['product_qty'] ?? 1);
            $lUnit = (float) ($ql['product_unit_price'] ?? $unitPrice);
            $lTot = (float) ($ql['product_total_price'] ?? round($lQty * $lUnit, 2));
            $lVat = $db->quote((string) ($ql['vat'] ?? '15.0'));
            $lVatAmt = (float) ($ql['vat_amt'] ?? round($lTot * RadioScheduleService::DEFAULT_VAT_RATE, 2));
            $db->query(
                "INSERT INTO aos_products_quotes (
                    id, name, part_number, product_id, group_id, number, date_entered, date_modified,
                    modified_user_id, created_by, description, deleted, assigned_user_id,
                    parent_type, parent_id, product_qty, product_list_price, product_unit_price,
                    product_total_price, vat, vat_amt, currency_id
                ) VALUES (
                    '{$lineId}', '{$lName}', '{$lPart}', '{$lProdId}', '{$groupId}', {$lineNum}, '{$nowUtc}', '{$nowUtc}',
                    '1', '1', '{$lDesc}', 0, '1',
                    'AOS_Invoices', '{$invId}', {$lQty}, {$lUnit}, {$lUnit},
                    {$lTot}, '{$lVat}', {$lVatAmt}, '-99'
                )",
                false
            );
            $lineNum++;
        }
    } else {
        // Add line item with part_number for ICASA & Invoice slot reporting
        $lineId = RadioQuoteScheduleProducts::generateUuid();
        $prodName = $stName . ' Commercial Broadcast - ' . $spotDur;
        $db->query(
            "INSERT INTO aos_products_quotes (
                id, name, part_number, group_id, number, date_entered, date_modified, modified_user_id, created_by,
                description, deleted, assigned_user_id, parent_type, parent_id,
                product_qty, product_list_price, product_unit_price, product_total_price, vat, vat_amt, currency_id
            ) VALUES (
                '{$lineId}', '" . $db->quote($prodName) . "', 'MORNING-BUZZ', '{$groupId}', 1, '{$nowUtc}', '{$nowUtc}', '1', '1',
                '{$descEsc}', 0, '1', 'AOS_Invoices', '{$invId}',
                {$totalSpots}, {$unitPrice}, {$unitPrice}, {$subtotal}, '15.0', {$vatAmt}, '-99'
            )",
            false
        );
    }

    if ($quoteId !== '') {
        $db->query(
            "UPDATE aos_quotes SET invoice_status = 'Invoiced', date_modified = '{$nowUtc}' WHERE id = '" . $db->quote($quoteId) . "'",
            false
        );
    }
}

// Redirect to invoice record in SuiteCRM
header("Location: index.php?module=AOS_Invoices&action=DetailView&record={$invId}");
exit;

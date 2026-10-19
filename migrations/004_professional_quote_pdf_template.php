<?php

declare(strict_types=1);

/**
 * Migration 004: Replace the default "Standard Radio Quote PDF" template with a professional,
 * branded layout (logo header, styled line-item table, totals panel, footer).
 *
 * Note: SuiteCRM stores template HTML entity-encoded and the PDF generator strips raw tags,
 * so all HTML is passed through htmlspecialchars() before saving.
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

global $db;

$stationSettings = class_exists('RadioCRM\RadioStationConfigService')
    ? RadioStationConfigService::getSettings($db)
    : [];
$stationName = htmlspecialchars((string) ($stationSettings['station_name'] ?? 'Community Radio FM'), ENT_QUOTES);
$stationTagline = htmlspecialchars((string) ($stationSettings['station_tagline'] ?? 'The Voice of the Community'), ENT_QUOTES);

global $sugar_config;
$customLogoUrl = (string) ($stationSettings['station_logo_url'] ?? '');
if (!empty($customLogoUrl) && file_exists($legacyDir . '/' . ltrim($customLogoUrl, '/'))) {
    $logo = rtrim((string) $sugar_config['site_url'], '/') . '/legacy/' . ltrim($customLogoUrl, '/');
} else {
    $logo = rtrim((string) $sugar_config['site_url'], '/') . '/legacy/custom/include/RadioCRM/radio_station_logo.png';
}

$purple = '#6B2C91';
$teal = '#1A8CA3';
$grey = '#5F6B78';
$light = '#F3EEF7';

$headerHtml = <<<HTML
<table width="100%" cellpadding="2" cellspacing="0">
<tr>
<td width="16%"><img src="{$logo}" width="62" height="62" /></td>
<td width="50%"><font size="15" color="{$purple}"><b>{$stationName}</b></font><br /><font size="8" color="{$grey}">{$stationTagline}</font></td>
<td width="34%" align="right"><font size="20" color="{$teal}"><b>QUOTATION</b></font></td>
</tr>
</table>
<hr />
HTML;

$bodyHtml = <<<HTML
<table width="100%" cellpadding="6" cellspacing="0">
<tr>
<td width="48%" bgcolor="{$light}"><font size="10" color="{$purple}"><b>PREPARED FOR</b></font><br /><font size="9"><b>\$aos_quotes_billing_account</b><br />Attn: \$aos_quotes_billing_contact</font></td>
<td width="4%"> </td>
<td width="48%" bgcolor="{$light}"><font size="10" color="{$purple}"><b>QUOTE DETAILS</b></font><br /><font size="9"><b>Quote No:</b> #\$aos_quotes_number<br /><b>Reference:</b> \$aos_quotes_name<br /><b>Campaign Start:</b> \$aos_quotes_campaign_start_date_c<br /><b>Campaign End:</b> \$aos_quotes_campaign_end_date_c</font></td>
</tr>
</table>
<br />
<table width="100%" cellpadding="5" cellspacing="0" border="0">
<tr bgcolor="{$purple}">
<td width="8%" align="center"><font size="9" color="#FFFFFF"><b>Qty</b></font></td>
<td width="26%"><font size="9" color="#FFFFFF"><b>Product</b></font></td>
<td width="30%"><font size="9" color="#FFFFFF"><b>Description</b></font></td>
<td width="12%" align="right"><font size="9" color="#FFFFFF"><b>Unit Price</b></font></td>
<td width="12%" align="right"><font size="9" color="#FFFFFF"><b>VAT</b></font></td>
<td width="12%" align="right"><font size="9" color="#FFFFFF"><b>Total</b></font></td>
</tr>
<tr>
<td width="8%" align="center" style="border-bottom: 1px solid #DDDDDD;"><font size="9">\$aos_products_quotes_product_qty</font></td>
<td width="26%" style="border-bottom: 1px solid #DDDDDD;"><font size="9"><b>\$aos_products_quotes_name</b></font><br /><font size="7" color="{$grey}">\$aos_products_quotes_part_number</font></td>
<td width="30%" style="border-bottom: 1px solid #DDDDDD;"><font size="8">\$aos_products_quotes_item_description</font></td>
<td width="12%" align="right" style="border-bottom: 1px solid #DDDDDD;"><font size="9">\$aos_products_quotes_product_unit_price</font></td>
<td width="12%" align="right" style="border-bottom: 1px solid #DDDDDD;"><font size="9">\$aos_products_quotes_vat_amt</font></td>
<td width="12%" align="right" style="border-bottom: 1px solid #DDDDDD;"><font size="9"><b>\$aos_products_quotes_product_total_price</b></font></td>
</tr>
</table>
<br />
<table width="100%" cellpadding="5" cellspacing="0">
<tr>
<td width="56%"> </td>
<td width="24%"><font size="10" color="{$grey}">Subtotal (Net)</font></td>
<td width="20%" align="right"><font size="10">\$aos_quotes_subtotal_amount</font></td>
</tr>
<tr>
<td width="56%"> </td>
<td width="24%"><font size="10" color="{$grey}">VAT (15%)</font></td>
<td width="20%" align="right"><font size="10">\$aos_quotes_tax_amount</font></td>
</tr>
<tr>
<td width="56%"> </td>
<td width="24%" bgcolor="{$teal}"><font size="10" color="#FFFFFF"><b>TOTAL (Incl. VAT)</b></font></td>
<td width="20%" bgcolor="{$teal}" align="right"><font size="10" color="#FFFFFF"><b>\$aos_quotes_total_amount</b></font></td>
</tr>
</table>
<br /><br />
<font size="8" color="{$grey}">All amounts are in South African Rand (ZAR). This quotation is subject to standard radio advertising terms and conditions.</font>
HTML;

$footerHtml = <<<HTML
<hr />
<table width="100%" cellpadding="2" cellspacing="0">
<tr>
<td width="75%"><font size="8" color="{$grey}">{$stationName} | {$stationTagline}</font></td>
<td width="25%" align="right"><font size="8" color="{$grey}">Page {PAGENO}</font></td>
</tr>
</table>
HTML;

// SuiteCRM converts newlines to <br /> before rendering, which breaks table layout in TCPDF,
// so flatten each fragment to a single line.
foreach (['headerHtml', 'bodyHtml', 'footerHtml'] as $var) {
    $$var = trim((string) preg_replace('/>\s+</', '><', preg_replace('/\s*\R\s*/', ' ', $$var)));
}

$res = $db->query("SELECT id FROM aos_pdf_templates WHERE (name = 'Standard Radio Quote PDF' OR name LIKE '%Standard Quote PDF') AND deleted = 0 LIMIT 1", false);
$row = $res ? $db->fetchByAssoc($res) : null;
if (!$row) {
    echo "[Migration 004] Template 'Standard Radio Quote PDF' not found; run migration 003 first.\n";
    exit(1);
}

$db->query(sprintf(
    "UPDATE aos_pdf_templates SET name = 'Standard Radio Quote PDF', description = '%s', pdfheader = '%s', pdffooter = '%s',
        margin_left = 15, margin_right = 15, margin_top = 38, margin_bottom = 20,
        margin_header = 8, margin_footer = 8, date_modified = '%s'
     WHERE id = '%s'",
    $db->quote(htmlspecialchars($bodyHtml)),
    $db->quote(htmlspecialchars($headerHtml)),
    $db->quote(htmlspecialchars($footerHtml)),
    gmdate('Y-m-d H:i:s'),
    $db->quote($row['id'])
), false);

echo "[Migration 004] Professional Quote PDF template applied (id {$row['id']}).\n";

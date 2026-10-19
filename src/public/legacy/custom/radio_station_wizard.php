<?php

declare(strict_types=1);

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

require_once 'custom/include/RadioCRM/RadioStationConfigService.php';
require_once 'custom/include/RadioCRM/RadioScheduleService.php';

use RadioCRM\RadioStationConfigService;
use RadioCRM\RadioScheduleService;

global $current_user, $db;

if (empty($current_user) || empty($current_user->id)) {
    header('Location: index.php?module=Users&action=Login');
    exit;
}

if (!$current_user->is_admin) {
    die('<div style="padding:40px; font-family:sans-serif; text-align:center;"><h2>Access Denied</h2><p>The Radio Station Setup Wizard requires Administrator privileges.</p><a href="index.php">Return to SuiteCRM</a></div>');
}

$isEmbedded = (!empty($_REQUEST['module']) && $_REQUEST['module'] === 'Administration');
$notice = '';
$noticeType = 'success';

// -------------------------------------------------------------
// 1. Handle JSON Profile Export
// -------------------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] === 'export_json') {
    $json = RadioStationConfigService::exportProfileJson($db);
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="station_profile.json"');
    echo $json;
    exit;
}

// -------------------------------------------------------------
// 2. Handle JSON Profile Import
// -------------------------------------------------------------
if (isset($_POST['action']) && $_POST['action'] === 'import_json') {
    if (!empty($_FILES['profile_json']['tmp_name']) && is_uploaded_file($_FILES['profile_json']['tmp_name'])) {
        $raw = (string) file_get_contents($_FILES['profile_json']['tmp_name']);
        if (RadioStationConfigService::importProfileJson($raw, $db)) {
            $notice = '✓ Station profile imported successfully! Branding, products, packages, and rate cards updated.';
            $noticeType = 'success';
        } else {
            $notice = 'ERROR: Invalid JSON profile file.';
            $noticeType = 'error';
        }
    }
}

// -------------------------------------------------------------
// 3. Handle Form Save
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['action'] ?? '') === 'save_wizard')) {
    $settingsData = [
        'station_name' => trim((string) ($_POST['station_name'] ?? 'Community Radio FM')),
        'station_callsign' => trim((string) ($_POST['station_callsign'] ?? 'RADIO')),
        'station_frequency' => trim((string) ($_POST['station_frequency'] ?? '101.5 FM')),
        'station_tagline' => trim((string) ($_POST['station_tagline'] ?? '')),
        'station_manager_name' => trim((string) ($_POST['station_manager_name'] ?? '')),
        'station_email' => trim((string) ($_POST['station_email'] ?? '')),
        'station_phone' => trim((string) ($_POST['station_phone'] ?? '')),
        'studio_address' => trim((string) ($_POST['studio_address'] ?? '')),
        'vat_number' => trim((string) ($_POST['vat_number'] ?? '')),
        'vat_rate' => (float) ($_POST['vat_rate'] ?? 15.0),
        'currency_symbol' => trim((string) ($_POST['currency_symbol'] ?? 'R')),
        'currency_iso' => trim((string) ($_POST['currency_iso'] ?? 'ZAR')),
        'hourly_capacity' => max(1, (int) ($_POST['hourly_capacity'] ?? 20)),
        'max_ad_minutes_per_hour' => max(1.0, (float) ($_POST['max_ad_minutes_per_hour'] ?? 10.0)),
        'cart_range_start' => (int) ($_POST['cart_range_start'] ?? 2000),
        'cart_range_end' => (int) ($_POST['cart_range_end'] ?? 2999),
        'playout_log_dir' => trim((string) ($_POST['playout_log_dir'] ?? '/srv/myriad_reconciliation')),
        'export_dir' => trim((string) ($_POST['export_dir'] ?? '/srv/myriad_exports')),
        'station_configured' => true,
    ];

    // Handle logo upload if provided
    if (!empty($_FILES['station_logo']['tmp_name']) && is_uploaded_file($_FILES['station_logo']['tmp_name'])) {
        $err = null;
        $savedPath = RadioStationConfigService::handleLogoUpload($_FILES['station_logo'], $err);
        if ($savedPath !== null) {
            $settingsData['station_logo_url'] = $savedPath;
        } elseif ($err) {
            $notice = "Logo upload warning: {$err}. Other settings were saved.";
            $noticeType = 'warning';
        }
    } else {
        $existingLogo = trim((string) ($_POST['existing_logo_url'] ?? ''));
        if ($existingLogo !== '') {
            $settingsData['station_logo_url'] = $existingLogo;
        }
    }

    // Process show slot titles, product codes, hours, and rates
    $postedSlots = $_POST['slots'] ?? [];
    if (is_array($postedSlots)) {
        $newSlots = [];
        foreach ($postedSlots as $idx => $slotInput) {
            $title = trim((string) ($slotInput['title'] ?? ''));
            $partNumber = trim((string) ($slotInput['part_number'] ?? ''));
            $origPartNumber = trim((string) ($slotInput['orig_part_number'] ?? $partNumber));
            $hours = trim((string) ($slotInput['hours'] ?? ''));
            $rate = (float) ($slotInput['rate'] ?? 0.0);
            $weekendRate = isset($slotInput['weekend_rate']) && $slotInput['weekend_rate'] !== ''
                ? (float) $slotInput['weekend_rate']
                : $rate;
            $desc = trim((string) ($slotInput['description'] ?? ''));

            if ($title === '' && $partNumber === '') {
                continue;
            }

            // Standardize slot key from part number or index
            $slotKey = strtoupper(preg_replace('/[^a-zA-Z0-9_-]/', '-', $partNumber ?: (string) $idx));
            $newSlots[$slotKey] = [
                'part_number' => $partNumber ?: $slotKey,
                'orig_part_number' => $origPartNumber,
                'title' => $title ?: $slotKey,
                'hours' => $hours,
                'rate' => $rate,
                'weekend_rate' => $weekendRate,
                'description' => $desc,
            ];
        }
        $settingsData['slots'] = $newSlots;
    }

    // Process package presets
    $postedPackages = $_POST['packages'] ?? [];
    if (is_array($postedPackages)) {
        $newPackages = [];
        foreach ($postedPackages as $key => $pkgInput) {
            $name = trim((string) ($pkgInput['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $type = trim((string) ($pkgInput['type'] ?? 'weekly'));
            $spots = (int) ($pkgInput['total_spots'] ?? 0);
            $pkgRate = (float) ($pkgInput['rate'] ?? 0.0);
            $pkgDesc = trim((string) ($pkgInput['description'] ?? ''));
            $pkgKey = strtolower(preg_replace('/[^a-zA-Z0-9_-]/', '_', (string) $key));

            $newPackages[$pkgKey] = [
                'key' => $pkgKey,
                'name' => $name,
                'type' => $type,
                'total_spots' => $spots,
                'rate' => $pkgRate,
                'description' => $pkgDesc,
            ];
        }
        $settingsData['package_presets'] = $newPackages;
    }

    // Process duration multipliers
    $postedMultipliers = $_POST['duration_multipliers'] ?? [];
    if (is_array($postedMultipliers) && !empty($postedMultipliers)) {
        $newMultipliers = [];
        foreach ($postedMultipliers as $durKey => $mVal) {
            $newMultipliers[$durKey] = (float) $mVal;
        }
        $settingsData['duration_multipliers'] = $newMultipliers;
    }

    // Process category discounts
    $postedCats = $_POST['categories'] ?? [];
    if (is_array($postedCats) && !empty($postedCats)) {
        $newCats = [];
        foreach ($postedCats as $catKey => $cInput) {
            $catName = trim((string) ($cInput['name'] ?? ''));
            if ($catName === '') {
                continue;
            }
            $newCats[$catKey] = [
                'name' => $catName,
                'discount' => (float) ($cInput['discount'] ?? 0.0),
            ];
        }
        $settingsData['category_discounts'] = $newCats;
    }

    if (RadioStationConfigService::saveSettings($settingsData, $db)) {
        $notice = '✓ Station setup saved successfully! All branding, product codes, rate cards, packages, and PDF templates updated across the program.';
        $noticeType = 'success';
    } else {
        $notice = 'ERROR: Failed to save station settings to database.';
        $noticeType = 'error';
    }
}

// Fetch current active settings
$cfg = RadioStationConfigService::getSettings($db);
$slots = $cfg['slots'] ?? RadioStationConfigService::DEFAULT_SLOTS;
$packages = $cfg['package_presets'] ?? (RadioStationConfigService::DEFAULT_SETTINGS['package_presets'] ?? []);
$durationMultipliers = $cfg['duration_multipliers'] ?? (RadioStationConfigService::DEFAULT_SETTINGS['duration_multipliers'] ?? []);
$categories = $cfg['category_discounts'] ?? (RadioStationConfigService::DEFAULT_SETTINGS['category_discounts'] ?? []);

$stationName = htmlspecialchars((string) ($cfg['station_name'] ?? 'Community Radio FM'), ENT_QUOTES);
$stationCallsign = htmlspecialchars((string) ($cfg['station_callsign'] ?? 'RADIO'), ENT_QUOTES);
$stationFreq = htmlspecialchars((string) ($cfg['station_frequency'] ?? '101.5 FM'), ENT_QUOTES);
$stationTagline = htmlspecialchars((string) ($cfg['station_tagline'] ?? ''), ENT_QUOTES);
$logoUrl = htmlspecialchars((string) ($cfg['station_logo_url'] ?? 'custom/include/RadioCRM/radio_station_logo.png'), ENT_QUOTES);
$managerName = htmlspecialchars((string) ($cfg['station_manager_name'] ?? ''), ENT_QUOTES);
$email = htmlspecialchars((string) ($cfg['station_email'] ?? ''), ENT_QUOTES);
$phone = htmlspecialchars((string) ($cfg['station_phone'] ?? ''), ENT_QUOTES);
$address = htmlspecialchars((string) ($cfg['studio_address'] ?? ''), ENT_QUOTES);
$vatNum = htmlspecialchars((string) ($cfg['vat_number'] ?? ''), ENT_QUOTES);
$vatRate = (float) ($cfg['vat_rate'] ?? 15.0);
$currencySymbol = htmlspecialchars((string) ($cfg['currency_symbol'] ?? 'R'), ENT_QUOTES);
$currencyIso = htmlspecialchars((string) ($cfg['currency_iso'] ?? 'ZAR'), ENT_QUOTES);
$hourlyCap = (int) ($cfg['hourly_capacity'] ?? 20);
$maxAdMin = (float) ($cfg['max_ad_minutes_per_hour'] ?? 10.0);
$cartStart = (int) ($cfg['cart_range_start'] ?? 2000);
$cartEnd = (int) ($cfg['cart_range_end'] ?? 2999);
$playoutDir = htmlspecialchars((string) ($cfg['playout_log_dir'] ?? '/srv/myriad_reconciliation'), ENT_QUOTES);
$exportDir = htmlspecialchars((string) ($cfg['export_dir'] ?? '/srv/myriad_exports'), ENT_QUOTES);

?>
<?php if (!$isEmbedded): ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Radio Station Setup & Rate Card Manager - SuiteCRM</title>
</head>
<body style="margin:0; padding:20px; background:#f4f6f8; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
<?php endif; ?>

<div class="radio-wizard-root <?= $isEmbedded ? 'radio-wizard-embedded' : 'radio-wizard-standalone' ?>">
    <style>
    .radio-wizard-root {
        width: 100%;
        max-width: 100%;
        box-sizing: border-box;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        color: #1e293b;
        line-height: 1.5;
        margin-bottom: 30px;
    }
    .radio-wizard-root * {
        box-sizing: border-box;
    }
    .radio-wizard-root .wizard-card {
        background: #ffffff;
        border: 1px solid #d0d7de;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        overflow: hidden;
        margin-top: 10px;
    }
    .radio-wizard-root .wizard-banner {
        background: linear-gradient(135deg, #5E2B83 0%, #35134d 100%);
        color: #ffffff;
        padding: 22px 28px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 16px;
    }
    .radio-wizard-root .wizard-banner h1 {
        margin: 0;
        font-size: 22px;
        font-weight: 700;
        color: #ffffff;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .radio-wizard-root .wizard-banner .subtitle {
        margin: 4px 0 0 0;
        font-size: 13px;
        opacity: 0.9;
        color: #f1f5f9;
    }
    .radio-wizard-root .btn-back {
        background: rgba(255,255,255,0.18);
        color: #ffffff;
        padding: 8px 16px;
        border-radius: 6px;
        text-decoration: none;
        font-weight: 600;
        font-size: 13px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: background 0.15s ease;
    }
    .radio-wizard-root .btn-back:hover {
        background: rgba(255,255,255,0.3);
        color: #ffffff;
    }
    /* Tabs Navigation */
    .radio-wizard-root .wizard-tabs {
        display: flex;
        flex-wrap: wrap;
        background: #f8fafc;
        border-bottom: 2px solid #e2e8f0;
    }
    .radio-wizard-root .tab-btn {
        flex: 1 1 180px;
        padding: 13px 18px;
        text-align: center;
        background: none;
        border: none;
        border-bottom: 3px solid transparent;
        cursor: pointer;
        font-size: 13.5px;
        font-weight: 600;
        color: #64748b;
        transition: all 0.15s ease;
        white-space: nowrap;
    }
    .radio-wizard-root .tab-btn:hover:not(.active) {
        background: #f1f5f9;
        color: #1e293b;
    }
    .radio-wizard-root .tab-btn.active {
        color: #5E2B83;
        border-bottom-color: #5E2B83;
        background: #ffffff;
    }
    /* Wizard Body */
    .radio-wizard-root .wizard-content {
        padding: 28px 32px;
    }
    .radio-wizard-root .tab-panel {
        display: none;
    }
    .radio-wizard-root .tab-panel.active {
        display: block;
    }
    /* Alerts */
    .radio-wizard-root .notice-box {
        padding: 12px 18px;
        margin-bottom: 20px;
        border-radius: 6px;
        font-size: 14px;
        font-weight: 500;
    }
    .radio-wizard-root .notice-success {
        background: #ecfdf5;
        border-left: 4px solid #10b981;
        color: #065f46;
    }
    .radio-wizard-root .notice-error {
        background: #fef2f2;
        border-left: 4px solid #ef4444;
        color: #991b1b;
    }
    .radio-wizard-root .notice-warning {
        background: #fffbeb;
        border-left: 4px solid #f59e0b;
        color: #92400e;
    }
    /* Form Layouts */
    .radio-wizard-root .section-header {
        margin: 0 0 8px 0;
        font-size: 18px;
        font-weight: 700;
        color: #5E2B83;
    }
    .radio-wizard-root .section-desc {
        font-size: 13px;
        color: #64748b;
        margin: 0 0 20px 0;
    }
    .radio-wizard-root .grid-3 {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
        gap: 16px;
        margin-bottom: 18px;
    }
    .radio-wizard-root .grid-2 {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(360px, 1fr));
        gap: 16px;
        margin-bottom: 18px;
    }
    .radio-wizard-root .form-group {
        display: flex;
        flex-direction: column;
    }
    .radio-wizard-root .form-group label {
        font-weight: 600;
        font-size: 12.5px;
        margin-bottom: 5px;
        color: #334155;
    }
    .radio-wizard-root .form-group input,
    .radio-wizard-root .form-group textarea,
    .radio-wizard-root .form-group select {
        padding: 8px 12px;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        font-size: 13.5px;
        font-family: inherit;
        color: #1e293b;
        background: #ffffff;
    }
    .radio-wizard-root .form-group input:focus,
    .radio-wizard-root .form-group textarea:focus,
    .radio-wizard-root .form-group select:focus {
        outline: none;
        border-color: #5E2B83;
        box-shadow: 0 0 0 2px rgba(94, 43, 131, 0.15);
    }
    .radio-wizard-root .form-group .hint {
        font-size: 11.5px;
        color: #64748b;
        margin-top: 4px;
    }
    /* Logo Preview */
    .radio-wizard-root .logo-box {
        display: flex;
        align-items: center;
        gap: 20px;
        padding: 16px;
        background: #f8fafc;
        border: 1px dashed #cbd5e1;
        border-radius: 6px;
    }
    .radio-wizard-root .logo-preview {
        width: 80px;
        height: 80px;
        object-fit: contain;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        padding: 4px;
    }
    /* Tables */
    .radio-wizard-root .table-container {
        overflow-x: auto;
        margin-top: 10px;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
    }
    .radio-wizard-root .data-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
        text-align: left;
    }
    .radio-wizard-root .data-table th {
        background: #f1f5f9;
        padding: 10px 12px;
        font-size: 11.5px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #475569;
        border-bottom: 2px solid #cbd5e1;
    }
    .radio-wizard-root .data-table td {
        padding: 8px 12px;
        border-bottom: 1px solid #e2e8f0;
        vertical-align: middle;
    }
    .radio-wizard-root .data-table tr:hover {
        background: #f8fafc;
    }
    .radio-wizard-root .data-table input,
    .radio-wizard-root .data-table select {
        padding: 6px 10px;
        border: 1px solid #cbd5e1;
        border-radius: 4px;
        font-size: 13px;
        font-family: inherit;
        background: #ffffff;
    }
    .radio-wizard-root .data-table input:focus,
    .radio-wizard-root .data-table select:focus {
        outline: none;
        border-color: #5E2B83;
    }
    /* Table Action Bar */
    .radio-wizard-root .table-actions-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
    }
    .radio-wizard-root .btn-add-row {
        background: #0969da;
        color: #ffffff;
        border: none;
        padding: 7px 14px;
        font-size: 13px;
        font-weight: 600;
        border-radius: 5px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .radio-wizard-root .btn-add-row:hover {
        background: #0856b3;
    }
    .radio-wizard-root .btn-delete-row {
        background: #fff0f0;
        color: #cf222e;
        border: 1px solid #ffd0d0;
        padding: 4px 8px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 12px;
        font-weight: 600;
    }
    .radio-wizard-root .btn-delete-row:hover {
        background: #cf222e;
        color: #ffffff;
    }
    /* Wizard Footer */
    .radio-wizard-root .wizard-footer {
        padding: 18px 32px;
        background: #f8fafc;
        border-top: 1px solid #e2e8f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 14px;
    }
    .radio-wizard-root .btn-save-main {
        background: #5E2B83;
        color: #ffffff;
        border: none;
        padding: 11px 26px;
        font-size: 14.5px;
        font-weight: 700;
        border-radius: 6px;
        cursor: pointer;
        box-shadow: 0 2px 4px rgba(94, 43, 131, 0.2);
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: background 0.15s ease;
    }
    .radio-wizard-root .btn-save-main:hover {
        background: #7336a1;
    }
    .radio-wizard-root .btn-tool {
        background: #ffffff;
        color: #475569;
        border: 1px solid #cbd5e1;
        padding: 7px 14px;
        font-size: 13px;
        font-weight: 600;
        border-radius: 5px;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .radio-wizard-root .btn-tool:hover {
        background: #f1f5f9;
        color: #1e293b;
    }
    </style>

    <?php if ($isEmbedded): ?>
        <div style="margin-bottom: 12px; font-size: 12px; color: #64748b;">
            <a href="index.php?module=Administration&action=index" style="color: #5E2B83; font-weight: 600; text-decoration: none;">Administration</a>
            &nbsp;&gt;&nbsp;
            <span style="font-weight: 600; color: #1e293b;">Radio Station Setup & Rate Card Manager</span>
        </div>
    <?php endif; ?>

    <div class="wizard-card">
        <div class="wizard-banner">
            <div>
                <h1>📻 Radio Station Setup & Rate Card Manager</h1>
                <p class="subtitle">Customize station branding, legal identity, show lineup, editable product codes, rate cards, and package presets.</p>
            </div>
            <a href="index.php?module=Administration&action=index" class="btn-back">← Back to Admin</a>
        </div>

        <!-- Navigation Tabs -->
        <div class="wizard-tabs">
            <button type="button" class="tab-btn active" onclick="switchRadioTab('tab-branding')">1. 🏢 Station Branding & Legal</button>
            <button type="button" class="tab-btn" onclick="switchRadioTab('tab-slots')">2. 🎙️ Show Schedule & Rate Card Slots</button>
            <button type="button" class="tab-btn" onclick="switchRadioTab('tab-packages')">3. 📦 Package Deals & Presets</button>
            <button type="button" class="tab-btn" onclick="switchRadioTab('tab-durations')">4. ⏱️ Durations & Categories</button>
            <button type="button" class="tab-btn" onclick="switchRadioTab('tab-playout')">5. 📡 Traffic & Playout Rules</button>
            <button type="button" class="tab-btn" onclick="switchRadioTab('tab-profile')">6. 🛠️ Backup & JSON Profile</button>
        </div>

        <form method="POST" action="<?= $isEmbedded ? 'index.php?module=Administration&action=radio_station_wizard' : 'index.php?entryPoint=radio_station_wizard' ?>" enctype="multipart/form-data" id="radioWizardForm">
            <input type="hidden" name="action" value="save_wizard">
            <input type="hidden" name="existing_logo_url" value="<?= $logoUrl ?>">

            <div class="wizard-content">
                <?php if ($notice): ?>
                    <div class="notice-box notice-<?= $noticeType ?>">
                        <?= htmlspecialchars($notice, ENT_QUOTES) ?>
                    </div>
                <?php endif; ?>

                <!-- TAB 1: STATION BRANDING & LEGAL -->
                <div id="tab-branding" class="tab-panel active">
                    <h3 class="section-header">Station Identity & Legal Details</h3>
                    <p class="section-desc">These values automatically populate Quote headers, Broadcast Affidavits, Tax Invoices, and CRM branding.</p>

                    <div class="grid-3">
                        <div class="form-group">
                            <label for="station_name">Station Name *</label>
                            <input type="text" id="station_name" name="station_name" value="<?= $stationName ?>" required>
                            <span class="hint">e.g. Community Radio FM or Metro 93.6</span>
                        </div>
                        <div class="form-group">
                            <label for="station_callsign">Callsign Code</label>
                            <input type="text" id="station_callsign" name="station_callsign" value="<?= $stationCallsign ?>">
                            <span class="hint">Identifier prefix used for playout logs (e.g. RADIO)</span>
                        </div>
                        <div class="form-group">
                            <label for="station_frequency">On-Air Frequency</label>
                            <input type="text" id="station_frequency" name="station_frequency" value="<?= $stationFreq ?>">
                            <span class="hint">e.g. 101.5 FM</span>
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 18px;">
                        <label for="station_tagline">Station Tagline / Slogan</label>
                        <input type="text" id="station_tagline" name="station_tagline" value="<?= $stationTagline ?>">
                        <span class="hint">Prints directly on Quotation & Invoice headers</span>
                    </div>

                    <div class="form-group" style="margin-bottom: 22px;">
                        <label>Station Logo</label>
                        <div class="logo-box">
                            <img id="radioLogoPreview" src="<?= $logoUrl ?>" alt="Station Logo" class="logo-preview" onerror="this.src='custom/include/RadioCRM/radio_station_logo.png'">
                            <div style="flex:1;">
                                <input type="file" id="station_logo" name="station_logo" accept="image/png,image/jpeg,image/svg+xml,image/webp" onchange="previewRadioLogo(this)">
                                <div class="hint" style="margin-top:6px;">Upload PNG or JPEG (Transparent PNG recommended). Automatically scales onto PDFs, Broadcast Affidavits, and Web Headers.</div>
                            </div>
                        </div>
                    </div>

                    <h4 style="margin: 20px 0 10px 0; color: #334155; border-bottom: 1px solid #e2e8f0; padding-bottom: 6px;">Contact & Authorised Signatory</h4>

                    <div class="grid-2">
                        <div class="form-group">
                            <label for="station_manager_name">Station Manager / Signatory</label>
                            <input type="text" id="station_manager_name" name="station_manager_name" value="<?= $managerName ?>">
                            <span class="hint">Appears on the Broadcast Affidavit certification sign-off block</span>
                        </div>
                        <div class="form-group">
                            <label for="station_email">Billing / Traffic Email</label>
                            <input type="email" id="station_email" name="station_email" value="<?= $email ?>">
                            <span class="hint">Main contact address for advertiser queries</span>
                        </div>
                    </div>

                    <div class="grid-2">
                        <div class="form-group">
                            <label for="station_phone">Studio / Office Phone</label>
                            <input type="text" id="station_phone" name="station_phone" value="<?= $phone ?>">
                        </div>
                        <div class="form-group">
                            <label for="studio_address">Studio Physical Address</label>
                            <textarea id="studio_address" name="studio_address" rows="2"><?= $address ?></textarea>
                        </div>
                    </div>

                    <div class="grid-3">
                        <div class="form-group">
                            <label for="vat_number">Tax / VAT Registration Number</label>
                            <input type="text" id="vat_number" name="vat_number" value="<?= $vatNum ?>">
                        </div>
                        <div class="form-group">
                            <label for="currency_symbol">Currency Symbol</label>
                            <input type="text" id="currency_symbol" name="currency_symbol" value="<?= $currencySymbol ?>">
                            <span class="hint">e.g. R, $, €, £</span>
                        </div>
                        <div class="form-group">
                            <label for="vat_rate">Default VAT Rate (%)</label>
                            <input type="number" step="0.1" id="vat_rate" name="vat_rate" value="<?= $vatRate ?>">
                            <span class="hint">Standard 15.0% for South Africa</span>
                        </div>
                    </div>
                </div>

                <!-- TAB 2: SHOW SCHEDULE & RATE CARD SLOTS -->
                <div id="tab-slots" class="tab-panel">
                    <div class="table-actions-bar">
                        <div>
                            <h3 class="section-header" style="margin-bottom:2px;">Show Schedule & Rate Card Slots</h3>
                            <p class="section-desc" style="margin-bottom:0;">
                                Customize product codes, show titles, broadcast hours, and weekday vs weekend 30-second spot rates.
                                You can add additional slots or edit existing ones. Changes immediately synchronize into the <strong>Product Catalogue</strong> and <strong>Quotes</strong>.
                            </p>
                        </div>
                        <button type="button" class="btn-add-row" onclick="addSlotRow()">
                            + Add Show Slot
                        </button>
                    </div>

                    <div class="table-container">
                        <table class="data-table" id="slotsTable">
                            <thead>
                                <tr>
                                    <th width="14%">Product Code *</th>
                                    <th width="22%">Show Title *</th>
                                    <th width="14%">Hours</th>
                                    <th width="14%">Mon–Fri Rate (30s)</th>
                                    <th width="14%">Sat–Sun Rate (30s)</th>
                                    <th width="16%">Description</th>
                                    <th width="6%">Action</th>
                                </tr>
                            </thead>
                            <tbody id="slotsTableBody">
                                <?php $sIdx = 0; ?>
                                <?php foreach ($slots as $code => $slot): ?>
                                    <?php
                                        $sIdx++;
                                        $pn = htmlspecialchars((string) ($slot['part_number'] ?? $code), ENT_QUOTES);
                                        $title = htmlspecialchars((string) ($slot['title'] ?? ''), ENT_QUOTES);
                                        $hours = htmlspecialchars((string) ($slot['hours'] ?? ''), ENT_QUOTES);
                                        $rate = (float) ($slot['rate'] ?? 0.0);
                                        $wkndRate = (float) ($slot['weekend_rate'] ?? $rate);
                                        $desc = htmlspecialchars((string) ($slot['description'] ?? ''), ENT_QUOTES);
                                        $rowId = "slot_row_{$sIdx}";
                                    ?>
                                    <tr id="<?= $rowId ?>">
                                        <td>
                                            <input type="text" name="slots[<?= $code ?>][part_number]" value="<?= $pn ?>" style="width:100%; font-family:monospace; font-weight:600;" required>
                                            <input type="hidden" name="slots[<?= $code ?>][orig_part_number]" value="<?= $pn ?>">
                                        </td>
                                        <td>
                                            <input type="text" name="slots[<?= $code ?>][title]" value="<?= $title ?>" style="width:100%;" required>
                                        </td>
                                        <td>
                                            <input type="text" name="slots[<?= $code ?>][hours]" value="<?= $hours ?>" placeholder="06:00 - 09:00" style="width:100%;">
                                        </td>
                                        <td>
                                            <div style="display:flex; align-items:center; gap:4px;">
                                                <span><?= $currencySymbol ?></span>
                                                <input type="number" step="0.01" name="slots[<?= $code ?>][rate]" value="<?= number_format($rate, 2, '.', '') ?>" style="width:100px; font-weight:600;" required>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="display:flex; align-items:center; gap:4px;">
                                                <span><?= $currencySymbol ?></span>
                                                <input type="number" step="0.01" name="slots[<?= $code ?>][weekend_rate]" value="<?= number_format($wkndRate, 2, '.', '') ?>" style="width:100px; font-weight:600;" required>
                                            </div>
                                        </td>
                                        <td>
                                            <input type="text" name="slots[<?= $code ?>][description]" value="<?= $desc ?>" style="width:100%;" placeholder="Slot description">
                                        </td>
                                        <td style="text-align:center;">
                                            <button type="button" class="btn-delete-row" title="Delete slot" onclick="deleteTableRow('<?= $rowId ?>')">✕</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div style="margin-top:10px; font-size:12px; color:#64748b;">
                        💡 <em>Tip: 15-second spots calculate at 50%, 45-second spots at 150%, and 60-second spots at 200% of these base rates.</em>
                    </div>
                </div>

                <!-- TAB 3: PACKAGE PRESETS & DEALS -->
                <div id="tab-packages" class="tab-panel">
                    <div class="table-actions-bar">
                        <div>
                            <h3 class="section-header" style="margin-bottom:2px;">Commercial Package Presets</h3>
                            <p class="section-desc" style="margin-bottom:0;">
                                Configure bulk package options (e.g. Options 1–4). These presets appear in the Quote dropdown and automatically calculate package lump-sum pricing and spot allocations.
                            </p>
                        </div>
                        <button type="button" class="btn-add-row" onclick="addPackageRow()">
                            + Add Package Preset
                        </button>
                    </div>

                    <div class="table-container">
                        <table class="data-table" id="packagesTable">
                            <thead>
                                <tr>
                                    <th width="15%">Package Key</th>
                                    <th width="28%">Package Display Name *</th>
                                    <th width="14%">Cycle Type</th>
                                    <th width="12%">Total Spots</th>
                                    <th width="15%">Package Rate (Excl. VAT) *</th>
                                    <th width="10%">Action</th>
                                </tr>
                            </thead>
                            <tbody id="packagesTableBody">
                                <?php $pIdx = 0; ?>
                                <?php foreach ($packages as $k => $pkg): ?>
                                    <?php
                                        $pIdx++;
                                        $keyEsc = htmlspecialchars((string) ($pkg['key'] ?? $k), ENT_QUOTES);
                                        $nameEsc = htmlspecialchars((string) ($pkg['name'] ?? ''), ENT_QUOTES);
                                        $type = (string) ($pkg['type'] ?? 'weekly');
                                        $spots = (int) ($pkg['total_spots'] ?? 0);
                                        $pRate = (float) ($pkg['rate'] ?? 0.0);
                                        $pRowId = "pkg_row_{$pIdx}";
                                    ?>
                                    <tr id="<?= $pRowId ?>">
                                        <td>
                                            <code><?= $keyEsc ?></code>
                                            <input type="hidden" name="packages[<?= $keyEsc ?>][key]" value="<?= $keyEsc ?>">
                                        </td>
                                        <td>
                                            <input type="text" name="packages[<?= $keyEsc ?>][name]" value="<?= $nameEsc ?>" style="width:100%;" required>
                                        </td>
                                        <td>
                                            <select name="packages[<?= $keyEsc ?>][type]" style="width:100%;">
                                                <option value="weekly" <?= $type === 'weekly' ? 'selected' : '' ?>>Weekly</option>
                                                <option value="monthly" <?= $type === 'monthly' ? 'selected' : '' ?>>Monthly</option>
                                            </select>
                                        </td>
                                        <td>
                                            <input type="number" name="packages[<?= $keyEsc ?>][total_spots]" value="<?= $spots ?>" min="1" style="width:100px;">
                                        </td>
                                        <td>
                                            <div style="display:flex; align-items:center; gap:4px;">
                                                <span><?= $currencySymbol ?></span>
                                                <input type="number" step="0.01" name="packages[<?= $keyEsc ?>][rate]" value="<?= number_format($pRate, 2, '.', '') ?>" style="width:120px; font-weight:600;" required>
                                            </div>
                                        </td>
                                        <td style="text-align:center;">
                                            <button type="button" class="btn-delete-row" title="Delete package" onclick="deleteTableRow('<?= $pRowId ?>')">✕</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- TAB 4: DURATIONS & CATEGORIES -->
                <div id="tab-durations" class="tab-panel">
                    <h3 class="section-header">Spot Durations & Advertiser Industry Categories</h3>
                    <p class="section-desc">Adjust duration price multipliers and configure industry categories with optional default discounts.</p>

                    <h4 style="color:#334155; margin-bottom:8px;">Spot Duration Multipliers (Base: 30 sec = 1.00)</h4>
                    <div class="grid-3" style="margin-bottom:24px;">
                        <?php foreach (['15 sec', '30 sec', '45 sec', '60 sec'] as $dur): ?>
                            <?php $val = (float) ($durationMultipliers[$dur] ?? RadioScheduleService::DURATION_MULTIPLIERS[$dur]); ?>
                            <div class="form-group">
                                <label><?= $dur ?> Multiplier</label>
                                <input type="number" step="0.05" name="duration_multipliers[<?= $dur ?>]" value="<?= number_format($val, 2, '.', '') ?>">
                                <span class="hint"><?= round($val * 100) ?>% of standard 30s base rate</span>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <h4 style="color:#334155; margin-bottom:8px;">Advertiser Categories & Discounts</h4>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th width="25%">Category Key</th>
                                    <th width="45%">Display Name</th>
                                    <th width="30%">Default Discount (%)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categories as $catKey => $catInfo): ?>
                                    <tr>
                                        <td><code><?= htmlspecialchars((string) $catKey, ENT_QUOTES) ?></code></td>
                                        <td>
                                            <input type="text" name="categories[<?= $catKey ?>][name]" value="<?= htmlspecialchars((string) ($catInfo['name'] ?? ''), ENT_QUOTES) ?>" style="width:100%;">
                                        </td>
                                        <td>
                                            <input type="number" step="0.5" min="0" max="100" name="categories[<?= $catKey ?>][discount]" value="<?= (float) ($catInfo['discount'] ?? 0.0) ?>" style="width:100px;"> %
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- TAB 5: TRAFFIC & PLAYOUT -->
                <div id="tab-playout" class="tab-panel">
                    <h3 class="section-header">Traffic Capacity & Automation Playout Rules</h3>
                    <p class="section-desc">Enforce broadcast limits (ICASA standards) and manage Myriad automation cart allocation blocks.</p>

                    <div class="grid-2">
                        <div class="form-group">
                            <label for="hourly_capacity">Max Commercial Spots per Hour</label>
                            <input type="number" id="hourly_capacity" name="hourly_capacity" value="<?= $hourlyCap ?>" min="1" max="60">
                            <span class="hint">Standard 20 spots. Triggers visual overbooking alerts in schedule booking grids.</span>
                        </div>
                        <div class="form-group">
                            <label for="max_ad_minutes_per_hour">Max Commercial Minutes per Broadcast Hour</label>
                            <input type="number" step="0.5" id="max_ad_minutes_per_hour" name="max_ad_minutes_per_hour" value="<?= $maxAdMin ?>">
                            <span class="hint">ICASA compliance: 10.0 minutes/hour for community radio stations.</span>
                        </div>
                    </div>

                    <div class="grid-2">
                        <div class="form-group">
                            <label for="cart_range_start">Myriad Cart Allocation Range Start</label>
                            <input type="number" id="cart_range_start" name="cart_range_start" value="<?= $cartStart ?>">
                            <span class="hint">Default: 2000 (standard commercial audio cart block)</span>
                        </div>
                        <div class="form-group">
                            <label for="cart_range_end">Myriad Cart Allocation Range End</label>
                            <input type="number" id="cart_range_end" name="cart_range_end" value="<?= $cartEnd ?>">
                            <span class="hint">Default: 2999</span>
                        </div>
                    </div>

                    <div class="grid-2" style="margin-top:10px;">
                        <div class="form-group">
                            <label for="playout_log_dir">Myriad As-Run Playout Log Folder</label>
                            <input type="text" id="playout_log_dir" name="playout_log_dir" value="<?= $playoutDir ?>">
                            <span class="hint">Path where playout logs are monitored for broadcast reconciliation</span>
                        </div>
                        <div class="form-group">
                            <label for="export_dir">Traffic Log Export Destination Folder</label>
                            <input type="text" id="export_dir" name="export_dir" value="<?= $exportDir ?>">
                            <span class="hint">Path where exported daily .log files and CSV schedule matrices are saved</span>
                        </div>
                    </div>
                </div>

                <!-- TAB 6: BACKUP & JSON PROFILE -->
                <div id="tab-profile" class="tab-panel">
                    <h3 class="section-header">Station Profile Backup & Migration</h3>
                    <p class="section-desc">Export your station's complete configuration, branding, catalogue, and rules to JSON, or restore from a previously exported profile.</p>

                    <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:20px; max-width:600px;">
                        <h4 style="margin-top:0; color:#334155;">Export Configuration</h4>
                        <p style="font-size:13px; color:#64748b;">Download an authoritative JSON snapshot containing all show dayparts, rate card prices, packages, and station information.</p>
                        <a href="<?= $isEmbedded ? 'index.php?module=Administration&action=radio_station_wizard&action=export_json' : 'index.php?entryPoint=radio_station_wizard&action=export_json' ?>" class="btn-tool" style="font-size:13.5px; padding:9px 18px;">
                            📥 Export Station Profile (JSON)
                        </a>

                        <hr style="margin:20px 0; border:0; border-top:1px solid #e2e8f0;">

                        <h4 style="color:#334155; margin-bottom:8px;">Import Configuration</h4>
                        <p style="font-size:13px; color:#64748b;">Upload a station profile JSON file to provision or restore all settings in a single action.</p>
                        <button type="button" class="btn-tool" onclick="document.getElementById('radioImportFileInput').click()" style="font-size:13.5px; padding:9px 18px;">
                            📤 Import Station Profile (JSON)
                        </button>
                    </div>
                </div>
            </div>

            <!-- Wizard Footer Action Bar -->
            <div class="wizard-footer">
                <div style="display:flex; gap:10px;">
                    <a href="<?= $isEmbedded ? 'index.php?module=Administration&action=radio_station_wizard&action=export_json' : 'index.php?entryPoint=radio_station_wizard&action=export_json' ?>" class="btn-tool" title="Export profile JSON">
                        📥 Export JSON
                    </a>
                    <button type="button" class="btn-tool" onclick="document.getElementById('radioImportFileInput').click()">
                        📤 Import JSON
                    </button>
                </div>
                <button type="submit" class="btn-save-main">
                    💾 Save & Apply Station Configuration
                </button>
            </div>
        </form>

        <!-- Hidden form for JSON profile upload -->
        <form method="POST" action="<?= $isEmbedded ? 'index.php?module=Administration&action=radio_station_wizard' : 'index.php?entryPoint=radio_station_wizard' ?>" enctype="multipart/form-data" id="radioImportForm" style="display:none;">
            <input type="hidden" name="action" value="import_json">
            <input type="file" id="radioImportFileInput" name="profile_json" accept=".json,application/json" onchange="document.getElementById('radioImportForm').submit()">
        </form>
    </div>
</div>

<script>
function switchRadioTab(tabId) {
    document.querySelectorAll('.radio-wizard-root .tab-panel').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.radio-wizard-root .tab-btn').forEach(el => el.classList.remove('active'));
    
    const targetPanel = document.getElementById(tabId);
    if (targetPanel) {
        targetPanel.classList.add('active');
    }
    
    const clickedBtn = Array.from(document.querySelectorAll('.radio-wizard-root .tab-btn')).find(b => {
        const oc = b.getAttribute('onclick') || '';
        return oc.indexOf(tabId) !== -1;
    });
    if (clickedBtn) {
        clickedBtn.classList.add('active');
    }
}

function previewRadioLogo(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById('radioLogoPreview');
            if (preview) {
                preview.src = e.target.result;
            }
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function deleteTableRow(rowId) {
    if (confirm('Are you sure you want to remove this item?')) {
        const row = document.getElementById(rowId);
        if (row) {
            row.remove();
        }
    }
}

var nextSlotIndex = <?= count($slots) + 10 ?>;
function addSlotRow() {
    nextSlotIndex++;
    const rowId = 'slot_row_new_' + nextSlotIndex;
    const key = 'SLOT_' + nextSlotIndex;
    const tbody = document.getElementById('slotsTableBody');
    if (!tbody) return;

    const tr = document.createElement('tr');
    tr.id = rowId;
    tr.innerHTML = `
        <td>
            <input type="text" name="slots[${key}][part_number]" value="SLOT-${nextSlotIndex}" style="width:100%; font-family:monospace; font-weight:600;" required>
            <input type="hidden" name="slots[${key}][orig_part_number]" value="">
        </td>
        <td>
            <input type="text" name="slots[${key}][title]" value="New Show Slot ${nextSlotIndex}" style="width:100%;" required>
        </td>
        <td>
            <input type="text" name="slots[${key}][hours]" value="" placeholder="10:00 - 14:00" style="width:100%;">
        </td>
        <td>
            <div style="display:flex; align-items:center; gap:4px;">
                <span><?= $currencySymbol ?></span>
                <input type="number" step="0.01" name="slots[${key}][rate]" value="350.00" style="width:100px; font-weight:600;" required>
            </div>
        </td>
        <td>
            <div style="display:flex; align-items:center; gap:4px;">
                <span><?= $currencySymbol ?></span>
                <input type="number" step="0.01" name="slots[${key}][weekend_rate]" value="300.00" style="width:100px; font-weight:600;" required>
            </div>
        </td>
        <td>
            <input type="text" name="slots[${key}][description]" value="" style="width:100%;" placeholder="Slot description">
        </td>
        <td style="text-align:center;">
            <button type="button" class="btn-delete-row" title="Delete slot" onclick="deleteTableRow('${rowId}')">✕</button>
        </td>
    `;
    tbody.appendChild(tr);
}

var nextPkgIndex = <?= count($packages) + 10 ?>;
function addPackageRow() {
    nextPkgIndex++;
    const rowId = 'pkg_row_new_' + nextPkgIndex;
    const key = 'option_' + nextPkgIndex;
    const tbody = document.getElementById('packagesTableBody');
    if (!tbody) return;

    const tr = document.createElement('tr');
    tr.id = rowId;
    tr.innerHTML = `
        <td>
            <code>${key}</code>
            <input type="hidden" name="packages[${key}][key]" value="${key}">
        </td>
        <td>
            <input type="text" name="packages[${key}][name]" value="Option ${nextPkgIndex}: Custom Package Deal" style="width:100%;" required>
        </td>
        <td>
            <select name="packages[${key}][type]" style="width:100%;">
                <option value="weekly">Weekly</option>
                <option value="monthly">Monthly</option>
            </select>
        </td>
        <td>
            <input type="number" name="packages[${key}][total_spots]" value="50" min="1" style="width:100px;">
        </td>
        <td>
            <div style="display:flex; align-items:center; gap:4px;">
                <span><?= $currencySymbol ?></span>
                <input type="number" step="0.01" name="packages[${key}][rate]" value="5999.00" style="width:120px; font-weight:600;" required>
            </div>
        </td>
        <td style="text-align:center;">
            <button type="button" class="btn-delete-row" title="Delete package" onclick="deleteTableRow('${rowId}')">✕</button>
        </td>
    `;
    tbody.appendChild(tr);
}
</script>

<?php if (!$isEmbedded): ?>
</body>
</html>
<?php endif; ?>

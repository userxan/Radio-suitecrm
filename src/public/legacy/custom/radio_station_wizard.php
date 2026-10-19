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
            $notice = '✓ Station profile imported successfully! Branding, products, and rate cards updated.';
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

    // Process show slot titles, hours, and rates
    $currentSettings = RadioStationConfigService::getSettings($db);
    $currentSlots = $currentSettings['slots'] ?? RadioStationConfigService::DEFAULT_SLOTS;

    $postedSlots = $_POST['slots'] ?? [];
    if (is_array($postedSlots)) {
        foreach ($postedSlots as $code => $slotInput) {
            if (!isset($currentSlots[$code])) {
                continue;
            }
            $currentSlots[$code]['title'] = trim((string) ($slotInput['title'] ?? $currentSlots[$code]['title']));
            $currentSlots[$code]['hours'] = trim((string) ($slotInput['hours'] ?? $currentSlots[$code]['hours']));
            $currentSlots[$code]['rate'] = (float) ($slotInput['rate'] ?? $currentSlots[$code]['rate']);
            $currentSlots[$code]['description'] = trim((string) ($slotInput['description'] ?? $currentSlots[$code]['description']));
        }
        $settingsData['slots'] = $currentSlots;
    }

    if (RadioStationConfigService::saveSettings($settingsData, $db)) {
        $notice = '✓ Station setup saved successfully! Branding, Quote PDF template, and catalogue products updated.';
        $noticeType = 'success';
    } else {
        $notice = 'ERROR: Failed to save station settings to database.';
        $noticeType = 'error';
    }
}

// Fetch current active settings
$cfg = RadioStationConfigService::getSettings($db);
$slots = $cfg['slots'] ?? RadioStationConfigService::DEFAULT_SLOTS;

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

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Radio Station Setup & Rate Card Wizard - SuiteCRM</title>
<style>
:root {
    --primary: #5E2B83;
    --primary-light: #7b3ba8;
    --accent: #1B8FA3;
    --bg-page: #f8fafc;
    --card-bg: #ffffff;
    --text-main: #1e293b;
    --text-muted: #64748b;
    --border: #e2e8f0;
    --success-bg: #ecfdf5;
    --success-border: #10b981;
    --success-text: #065f46;
    --error-bg: #fef2f2;
    --error-border: #ef4444;
    --error-text: #991b1b;
}

* { box-sizing: border-box; }
body {
    margin: 0;
    padding: 24px;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    background-color: var(--bg-page);
    color: var(--text-main);
    line-height: 1.5;
}

.wizard-container {
    max-width: 1040px;
    margin: 0 auto;
    background: var(--card-bg);
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.06);
    border: 1px solid var(--border);
    overflow: hidden;
}

.wizard-header {
    background: linear-gradient(135deg, var(--primary) 0%, #3e165b 100%);
    color: #fff;
    padding: 24px 32px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.wizard-header h1 {
    margin: 0;
    font-size: 22px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.wizard-header .subtitle {
    margin: 4px 0 0 0;
    font-size: 13px;
    opacity: 0.85;
}

.btn-header-back {
    background: rgba(255,255,255,0.18);
    color: #fff;
    padding: 8px 16px;
    border-radius: 4px;
    text-decoration: none;
    font-weight: 600;
    font-size: 13px;
    transition: background 0.15s ease;
}
.btn-header-back:hover { background: rgba(255,255,255,0.3); }

/* Tabs */
.tabs-nav {
    display: flex;
    background: #f1f5f9;
    border-bottom: 1px solid var(--border);
}
.tab-btn {
    flex: 1;
    padding: 14px 16px;
    text-align: center;
    background: none;
    border: none;
    border-bottom: 3px solid transparent;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    color: var(--text-muted);
    transition: all 0.15s ease;
}
.tab-btn.active {
    color: var(--primary);
    border-bottom-color: var(--primary);
    background: #fff;
}
.tab-btn:hover:not(.active) {
    background: #e2e8f0;
    color: var(--text-main);
}

.wizard-body { padding: 32px; }

.tab-content { display: none; }
.tab-content.active { display: block; }

.notice-banner {
    padding: 12px 18px;
    margin-bottom: 24px;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 500;
}
.notice-success { background: var(--success-bg); border-left: 4px solid var(--success-border); color: var(--success-text); }
.notice-error { background: var(--error-bg); border-left: 4px solid var(--error-border); color: var(--error-text); }
.notice-warning { background: #fffbeb; border-left: 4px solid #f59e0b; color: #92400e; }

/* Form grid */
.form-grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}
.form-grid-3 {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 16px;
    margin-bottom: 20px;
}
.form-group {
    display: flex;
    flex-direction: column;
}
.form-group label {
    font-weight: 600;
    font-size: 13px;
    margin-bottom: 6px;
    color: #334155;
}
.form-group input, .form-group textarea, .form-group select {
    padding: 9px 12px;
    border: 1px solid var(--border);
    border-radius: 6px;
    font-size: 14px;
    color: var(--text-main);
    background: #fff;
}
.form-group input:focus, .form-group textarea:focus, .form-group select:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 2px rgba(94, 43, 131, 0.15);
}
.form-group .hint {
    font-size: 11px;
    color: var(--text-muted);
    margin-top: 4px;
}

/* Logo preview box */
.logo-upload-box {
    display: flex;
    gap: 24px;
    align-items: center;
    padding: 16px;
    border: 1px dashed #cbd5e1;
    border-radius: 6px;
    background: #f8fafc;
}
.logo-preview-img {
    width: 80px;
    height: 80px;
    object-fit: contain;
    background: #fff;
    border: 1px solid var(--border);
    border-radius: 6px;
    padding: 4px;
}

/* Rate table */
.rate-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
}
.rate-table th {
    background: #f1f5f9;
    padding: 10px 12px;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    text-align: left;
    color: #475569;
    border-bottom: 2px solid var(--border);
}
.rate-table td {
    padding: 10px 12px;
    border-bottom: 1px solid var(--border);
    font-size: 13px;
    vertical-align: middle;
}
.rate-table input {
    padding: 6px 10px;
    border: 1px solid var(--border);
    border-radius: 4px;
    font-size: 13px;
}

/* Actions footer */
.wizard-footer {
    padding: 20px 32px;
    background: #f8fafc;
    border-top: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.btn-save {
    background: var(--primary);
    color: #fff;
    border: none;
    padding: 12px 28px;
    font-size: 15px;
    font-weight: 600;
    border-radius: 6px;
    cursor: pointer;
    box-shadow: 0 2px 4px rgba(94, 43, 131, 0.2);
    transition: background 0.15s ease;
}
.btn-save:hover { background: var(--primary-light); }

.secondary-actions {
    display: flex;
    gap: 12px;
}
.btn-secondary {
    background: #fff;
    color: #475569;
    border: 1px solid var(--border);
    padding: 8px 14px;
    font-size: 13px;
    font-weight: 600;
    border-radius: 6px;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.btn-secondary:hover { background: #f1f5f9; color: var(--text-main); }
</style>
</head>
<body>

<div class="wizard-container">
    <div class="wizard-header">
        <div>
            <h1>📻 Radio Station Setup & Onboarding Wizard</h1>
            <p class="subtitle">Quick setup guide to customize station branding, show dayparts, pricing rate cards, and playout rules.</p>
        </div>
        <a href="index.php" class="btn-header-back">← Back to SuiteCRM</a>
    </div>

    <!-- Navigation Tabs -->
    <div class="tabs-nav">
        <button type="button" class="tab-btn active" onclick="switchTab('tab-branding')">1. 🏢 Station Branding & Logo</button>
        <button type="button" class="tab-btn" onclick="switchTab('tab-schedule')">2. 🎙️ Show Schedule & Dayparts</button>
        <button type="button" class="tab-btn" onclick="switchTab('tab-rates')">3. 💰 Rate Card & Pricing</button>
        <button type="button" class="tab-btn" onclick="switchTab('tab-traffic')">4. 📡 Traffic & Myriad Playout</button>
    </div>

    <form method="POST" action="index.php?entryPoint=radio_station_wizard" enctype="multipart/form-data" id="wizardForm">
        <input type="hidden" name="action" value="save_wizard">
        <input type="hidden" name="existing_logo_url" value="<?= $logoUrl ?>">

        <div class="wizard-body">
            <?php if ($notice): ?>
                <div class="notice-banner notice-<?= $noticeType ?>">
                    <?= htmlspecialchars($notice, ENT_QUOTES) ?>
                </div>
            <?php endif; ?>

            <!-- TAB 1: BRANDING & IDENTITY -->
            <div id="tab-branding" class="tab-content active">
                <h3 style="margin-top:0; color:var(--primary);">Station Identity & Legal Information</h3>
                <p style="font-size:13px; color:var(--text-muted); margin-bottom:20px;">
                    These details appear automatically on your Quote PDFs, Broadcast Affidavits, Tax Invoices, and CRM header.
                </p>

                <div class="form-grid-3">
                    <div class="form-group">
                        <label for="station_name">Station Name *</label>
                        <input type="text" id="station_name" name="station_name" value="<?= $stationName ?>" required>
                        <span class="hint">e.g. Metro FM or Voice of Cape Town</span>
                    </div>
                    <div class="form-group">
                        <label for="station_callsign">Callsign</label>
                        <input type="text" id="station_callsign" name="station_callsign" value="<?= $stationCallsign ?>">
                        <span class="hint">Short station code (e.g. RADIO)</span>
                    </div>
                    <div class="form-group">
                        <label for="station_frequency">On-Air Frequency</label>
                        <input type="text" id="station_frequency" name="station_frequency" value="<?= $stationFreq ?>">
                        <span class="hint">e.g. 101.5 FM</span>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:20px;">
                    <label for="station_tagline">Station Slogan / Tagline</label>
                    <input type="text" id="station_tagline" name="station_tagline" value="<?= $stationTagline ?>">
                    <span class="hint">Printed on quotation headers (e.g. Your Voice, Your Music)</span>
                </div>

                <div class="form-group" style="margin-bottom:24px;">
                    <label>Station Logo</label>
                    <div class="logo-upload-box">
                        <img id="logoPreview" src="<?= $logoUrl ?>" alt="Logo Preview" class="logo-preview-img" onerror="this.src='custom/include/RadioCRM/radio_station_logo.png'">
                        <div style="flex:1;">
                            <input type="file" id="station_logo" name="station_logo" accept="image/png,image/jpeg,image/svg+xml,image/webp" onchange="previewLogo(this)">
                            <div class="hint" style="margin-top:6px;">Upload PNG or JPEG (Transparent PNG recommended). Automatically scales onto PDFs, Affidavits, and Web Header.</div>
                        </div>
                    </div>
                </div>

                <h4 style="color:#334155; margin-bottom:12px; border-bottom:1px solid var(--border); padding-bottom:6px;">Station Contact & Signatory</h4>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label for="station_manager_name">Station Manager / Signatory</label>
                        <input type="text" id="station_manager_name" name="station_manager_name" value="<?= $managerName ?>">
                        <span class="hint">Prints on the Broadcast Affidavit certification sign-off line</span>
                    </div>
                    <div class="form-group">
                        <label for="station_email">Traffic / Commercial Billing Email</label>
                        <input type="email" id="station_email" name="station_email" value="<?= $email ?>">
                        <span class="hint">Contact address for client campaign queries</span>
                    </div>
                </div>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label for="station_phone">Studio / Office Phone</label>
                        <input type="text" id="station_phone" name="station_phone" value="<?= $phone ?>">
                    </div>
                    <div class="form-group">
                        <label for="studio_address">Studio Physical Address</label>
                        <textarea id="studio_address" name="studio_address" rows="2"><?= $address ?></textarea>
                    </div>
                </div>

                <div class="form-grid-3">
                    <div class="form-group">
                        <label for="vat_number">Tax / VAT Registration Number</label>
                        <input type="text" id="vat_number" name="vat_number" value="<?= $vatNum ?>">
                    </div>
                    <div class="form-group">
                        <label for="currency_symbol">Currency Symbol</label>
                        <input type="text" id="currency_symbol" name="currency_symbol" value="<?= $currencySymbol ?>">
                        <span class="hint">e.g. R, $, £, €</span>
                    </div>
                    <div class="form-group">
                        <label for="vat_rate">Default VAT Rate (%)</label>
                        <input type="number" step="0.1" id="vat_rate" name="vat_rate" value="<?= $vatRate ?>">
                        <span class="hint">Default 15.0% for South Africa</span>
                    </div>
                </div>
            </div>

            <!-- TAB 2: SHOW SCHEDULE & DAYPARTS -->
            <div id="tab-schedule" class="tab-content">
                <h3 style="margin-top:0; color:var(--primary);">On-Air Programme Lineup & Dayparts</h3>
                <p style="font-size:13px; color:var(--text-muted); margin-bottom:20px;">
                    Customize the 8 broadcast daypart show titles. Changes will immediately update your 168-hour weekly booking schedule grid and product descriptions.
                </p>

                <table class="rate-table">
                    <thead>
                        <tr>
                            <th width="15%">Time Slot</th>
                            <th width="20%">Product Code</th>
                            <th width="30%">Show Title (On-Air Name)</th>
                            <th width="35%">Show Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($slots as $code => $slot): ?>
                            <tr>
                                <td>
                                    <input type="text" name="slots[<?= $code ?>][hours]" value="<?= htmlspecialchars((string) ($slot['hours'] ?? ''), ENT_QUOTES) ?>" style="width:110px; font-weight:600;">
                                </td>
                                <td><code><?= htmlspecialchars($code, ENT_QUOTES) ?></code></td>
                                <td>
                                    <input type="text" name="slots[<?= $code ?>][title]" value="<?= htmlspecialchars((string) ($slot['title'] ?? ''), ENT_QUOTES) ?>" style="width:100%;">
                                </td>
                                <td>
                                    <input type="text" name="slots[<?= $code ?>][description]" value="<?= htmlspecialchars((string) ($slot['description'] ?? ''), ENT_QUOTES) ?>" style="width:100%;">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- TAB 3: RATE CARD & PRICING -->
            <div id="tab-rates" class="tab-content">
                <h3 style="margin-top:0; color:var(--primary);">Rate Card: 30-Second Unit Spot Rates</h3>
                <p style="font-size:13px; color:var(--text-muted); margin-bottom:15px;">
                    Set standard 30-second advert spot pricing per show slot. Spot lengths (15s @ 50%, 45s @ 150%, 60s @ 200%) scale automatically from these base rates.
                </p>

                <table class="rate-table">
                    <thead>
                        <tr>
                            <th width="25%">Show Slot</th>
                            <th width="20%">Broadcast Hours</th>
                            <th width="25%">30s Base Rate (Excl. VAT)</th>
                            <th width="30%">Standard 15s / 60s Scaling</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($slots as $code => $slot): ?>
                            <?php $rate = (float) ($slot['rate'] ?? 0.0); ?>
                            <tr>
                                <td><strong><?= htmlspecialchars((string) ($slot['title'] ?? $code), ENT_QUOTES) ?></strong></td>
                                <td><?= htmlspecialchars((string) ($slot['hours'] ?? ''), ENT_QUOTES) ?></td>
                                <td>
                                    <?= $currencySymbol ?>
                                    <input type="number" step="0.01" name="slots[<?= $code ?>][rate]" value="<?= number_format($rate, 2, '.', '') ?>" style="width:110px; font-weight:600;">
                                </td>
                                <td style="color:#64748b; font-size:12px;">
                                    15s: <?= $currencySymbol ?><?= number_format($rate * 0.5, 2) ?> | 60s: <?= $currencySymbol ?><?= number_format($rate * 2.0, 2) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- TAB 4: TRAFFIC & PLAYOUT -->
            <div id="tab-traffic" class="tab-content">
                <h3 style="margin-top:0; color:var(--primary);">Traffic Capacity & Automation Playout Rules</h3>
                <p style="font-size:13px; color:var(--text-muted); margin-bottom:20px;">
                    Enforce commercial airtime limits (e.g. ICASA community radio rules) and configure Myriad automation cart allocation ranges.
                </p>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label for="hourly_capacity">Max Commercial Spots per Hour</label>
                        <input type="number" id="hourly_capacity" name="hourly_capacity" value="<?= $hourlyCap ?>" min="1" max="60">
                        <span class="hint">Default 20 spots. Triggers overbooking warnings on schedule booking grids.</span>
                    </div>
                    <div class="form-group">
                        <label for="max_ad_minutes_per_hour">Max Commercial Minutes per Broadcast Hour</label>
                        <input type="number" step="0.5" id="max_ad_minutes_per_hour" name="max_ad_minutes_per_hour" value="<?= $maxAdMin ?>">
                        <span class="hint">ICASA compliance standard: 10.0 minutes/hour for community radio.</span>
                    </div>
                </div>

                <div class="form-grid-2">
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

                <div class="form-group" style="margin-top:10px;">
                    <label for="playout_log_dir">Myriad As-Run Playout Log Folder</label>
                    <input type="text" id="playout_log_dir" name="playout_log_dir" value="<?= $playoutDir ?>">
                    <span class="hint">Shared folder where Myriad exports playout `.log` / `.asplayed` files for automated reconciliation</span>
                </div>
            </div>
        </div>

        <!-- Wizard Action Bar -->
        <div class="wizard-footer">
            <div class="secondary-actions">
                <a href="index.php?entryPoint=radio_station_wizard&action=export_json" class="btn-secondary" title="Export full station profile as JSON">
                    📥 Export Profile (JSON)
                </a>
                <button type="button" class="btn-secondary" onclick="document.getElementById('importFile').click()">
                    📤 Import Profile (JSON)
                </button>
            </div>
            <button type="submit" class="btn-save">
                💾 Save & Apply Station Configuration
            </button>
        </div>
    </form>

    <!-- Hidden file form for JSON profile import -->
    <form method="POST" action="index.php?entryPoint=radio_station_wizard" enctype="multipart/form-data" id="importForm" style="display:none;">
        <input type="hidden" name="action" value="import_json">
        <input type="file" id="importFile" name="profile_json" accept=".json,application/json" onchange="document.getElementById('importForm').submit()">
    </form>
</div>

<script>
function switchTab(tabId) {
    document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
    
    document.getElementById(tabId).classList.add('active');
    
    const clickedBtn = Array.from(document.querySelectorAll('.tab-btn')).find(b => b.getAttribute('onclick').includes(tabId));
    if (clickedBtn) {
        clickedBtn.classList.add('active');
    }
}

function previewLogo(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('logoPreview').src = e.target.result;
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>

</body>
</html>

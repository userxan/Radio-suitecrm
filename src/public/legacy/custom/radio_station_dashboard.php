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

$isEmbedded = (!empty($_REQUEST['module']) && $_REQUEST['module'] === 'Administration');
$year = isset($_REQUEST['year']) ? max(2020, (int) $_REQUEST['year']) : (int) date('Y');
$month = isset($_REQUEST['month']) ? max(1, min(12, (int) $_REQUEST['month'])) : (int) date('n');
$subAction = trim((string) ($_REQUEST['subaction'] ?? ''));

$metrics = RadioScheduleService::getStationDashboardMetrics($year, $month, $db);
$icasa = $metrics['icasa'] ?? [];

if ($subAction === 'export_icasa_csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header(sprintf('Content-Disposition: attachment; filename="ICASA_Compliance_%04d_%02d.csv"', $year, $month));
    echo (string) ($icasa['csv'] ?? '');
    exit;
}

$prod = $metrics['pipeline_counts'] ?? [];
$revBySlot = $metrics['revenue_by_slot'] ?? [];
$expiring = $metrics['expiring_campaigns'] ?? [];
$curr = htmlspecialchars((string) ($metrics['currency_symbol'] ?? 'R'), ENT_QUOTES);
$stationName = htmlspecialchars((string) ($metrics['station_name'] ?? 'Community Radio FM'), ENT_QUOTES);
$totalRev = (float) ($metrics['total_booked_revenue'] ?? 0.0);
$totalSpots = (int) ($metrics['total_booked_spots'] ?? 0);
$isCompliant = !empty($icasa['is_icasa_compliant']);
$periodLabel = sprintf('%04d-%02d', $year, $month);

$maxSlotRev = 1.0;
foreach ($revBySlot as $sr) {
    $rVal = (float) ($sr['booked_revenue'] ?? 0.0);
    if ($rVal > $maxSlotRev) {
        $maxSlotRev = $rVal;
    }
}
?>
<?php if (!$isEmbedded): ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $stationName ?> — Executive Revenue & ICASA Dashboard</title>
</head>
<body style="margin:0; padding:20px; background:#f4f6f8; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;">
<?php endif; ?>

<div class="radio-dash-root" style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif; color:#1e293b;">
    <style>
    .radio-dash-root * { box-sizing: border-box; }
    .radio-dash-banner {
        background: linear-gradient(135deg, #5E2B83 0%, #35134d 100%);
        color: #fff;
        padding: 22px 28px;
        border-radius: 8px 8px 0 0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 16px;
    }
    .radio-dash-banner h1 { margin: 0; font-size: 22px; color: #fff; }
    .radio-dash-banner p { margin: 4px 0 0 0; font-size: 13px; color: #e2e8f0; }
    .radio-dash-btn {
        background: rgba(255,255,255,0.18);
        color: #fff;
        padding: 8px 14px;
        border-radius: 6px;
        text-decoration: none;
        font-weight: 600;
        font-size: 13px;
        border: 1px solid rgba(255,255,255,0.3);
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .radio-dash-btn:hover { background: rgba(255,255,255,0.3); color: #fff; }
    .radio-dash-body {
        background: #fff;
        border: 1px solid #cbd5e1;
        border-top: none;
        border-radius: 0 0 8px 8px;
        padding: 24px 28px;
    }
    .kpi-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
        gap: 16px;
        margin-bottom: 24px;
    }
    .kpi-card {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-left: 4px solid #5E2B83;
        border-radius: 6px;
        padding: 14px 18px;
    }
    .kpi-card .lbl { font-size: 11.5px; text-transform: uppercase; font-weight: 700; color: #64748b; }
    .kpi-card .val { font-size: 22px; font-weight: 800; color: #1e293b; margin-top: 4px; }
    .kpi-card .sub { font-size: 12px; color: #475569; margin-top: 2px; }
    .dash-grid-2 {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(420px, 1fr));
        gap: 22px;
        margin-bottom: 24px;
    }
    .dash-panel {
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 18px 20px;
        background: #fff;
    }
    .dash-panel h3 {
        margin: 0 0 12px 0;
        font-size: 16px;
        color: #5E2B83;
        border-bottom: 1px solid #f1f5f9;
        padding-bottom: 8px;
    }
    .dash-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .dash-table th { background: #f1f5f9; text-align: left; padding: 8px 10px; font-size: 11.5px; text-transform: uppercase; color: #475569; }
    .dash-table td { padding: 8px 10px; border-bottom: 1px solid #f1f5f9; }
    </style>

    <div class="radio-dash-banner">
        <div>
            <h1>📊 <?= $stationName ?> — Executive Revenue, Renewal & ICASA Dashboard</h1>
            <p>Real-time show revenue attribution, expiring campaign renewals, studio production tracker, and ICASA regulatory compliance.</p>
        </div>
        <div style="display:flex; gap:8px; flex-wrap:wrap;">
            <a href="index.php?entryPoint=generate_presenter_liveread_sheet&date=<?= urlencode(date('Y-m-d')) ?>" target="_blank" class="radio-dash-btn">
                🎙️ Today's Studio Live-Read Sheet
            </a>
            <a href="<?= $isEmbedded ? 'index.php?module=Administration&action=radio_station_dashboard&subaction=export_icasa_csv&year=' . $year . '&month=' . $month : 'index.php?entryPoint=radio_station_dashboard&subaction=export_icasa_csv&year=' . $year . '&month=' . $month ?>" class="radio-dash-btn">
                📥 Export ICASA CSV (<?= htmlspecialchars($periodLabel, ENT_QUOTES) ?>)
            </a>
            <a href="<?= $isEmbedded ? 'index.php?module=Administration&action=radio_station_wizard' : 'index.php?entryPoint=radio_station_wizard' ?>" class="radio-dash-btn">
                ⚙️ Station Setup Wizard
            </a>
        </div>
    </div>

    <div class="radio-dash-body">
        <!-- Top KPI Row -->
        <div class="kpi-grid">
            <div class="kpi-card">
                <div class="lbl">Invoiced Campaign Revenue</div>
                <div class="val" style="color:#5E2B83;"><?= $curr ?><?= number_format($totalRev, 2) ?></div>
                <div class="sub"><?= (int) ($metrics['active_campaigns_count'] ?? 0) ?> active campaigns (<?= $totalSpots ?> invoiced spots)</div>
            </div>
            <div class="kpi-card" style="border-left-color:#059669;">
                <div class="lbl">ICASA Regulatory Status (Invoiced Only)</div>
                <div class="val" style="color:#059669;"><?= $isCompliant ? 'COMPLIANT' : 'REVIEW REQUIRED' ?></div>
                <div class="sub"><?= (float) ($icasa['total_minutes'] ?? 0.0) ?> total commercial mins (Avg <?= (float) ($icasa['avg_minutes_per_hour'] ?? 0.0) ?>m/hr)</div>
            </div>
            <div class="kpi-card" style="border-left-color:#d97706;">
                <div class="lbl">Upcoming Renewals</div>
                <div class="val" style="color:#d97706;"><?= count($expiring) ?> Campaigns</div>
                <div class="sub">Proactive sales follow-up queue</div>
            </div>
            <div class="kpi-card" style="border-left-color:#0284c7;">
                <div class="lbl">Invoiced Playout Plays (<?= htmlspecialchars($periodLabel, ENT_QUOTES) ?>)</div>
                <div class="val" style="color:#0284c7;"><?= (int) ($icasa['total_plays'] ?? 0) ?> Plays</div>
                <div class="sub">Sourced strictly from Invoices</div>
            </div>
        </div>

        <div class="dash-grid-2">
            <!-- Revenue by Show Slot -->
            <div class="dash-panel">
                <h3>🎙️ Invoiced Revenue & Spot Volume by Show Slot</h3>
                <table class="dash-table">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Show Title</th>
                            <th>Invoiced Spots</th>
                            <th>Invoiced Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($revBySlot as $sInfo): ?>
                            <?php
                                $sRev = (float) ($sInfo['booked_revenue'] ?? 0.0);
                                $sSpots = (int) ($sInfo['booked_spots'] ?? 0);
                                $pct = $maxSlotRev > 0 ? min(100, round(($sRev / $maxSlotRev) * 100)) : 0;
                            ?>
                            <tr>
                                <td style="font-family:monospace; font-weight:700;"><?= htmlspecialchars((string) ($sInfo['part_number'] ?? ''), ENT_QUOTES) ?></td>
                                <td>
                                    <div style="font-weight:600;"><?= htmlspecialchars((string) ($sInfo['title'] ?? ''), ENT_QUOTES) ?></div>
                                    <div style="background:#f1f5f9; height:6px; border-radius:3px; margin-top:4px; overflow:hidden;">
                                        <div style="background:#5E2B83; width:<?= $pct ?>%; height:100%;"></div>
                                    </div>
                                </td>
                                <td style="font-weight:600;"><?= $sSpots ?></td>
                                <td style="font-weight:700; color:#065f46;"><?= $curr ?><?= number_format($sRev, 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Studio Production Pipeline & Expiring Campaigns -->
            <div class="dash-panel">
                <h3>🎧 Studio Copy & Production Pipeline</h3>
                <div style="display:grid; grid-template-columns:repeat(2,1fr); gap:10px; margin-bottom:20px;">
                    <?php foreach ($prod as $stage => $cnt): ?>
                        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:10px 14px; display:flex; justify-content:space-between; align-items:center;">
                            <span style="font-size:12.5px; font-weight:600; color:#334155;"><?= htmlspecialchars((string) $stage, ENT_QUOTES) ?></span>
                            <span style="background:#5E2B83; color:#fff; font-weight:700; font-size:12px; padding:2px 8px; border-radius:12px;"><?= (int) $cnt ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>

                <h3>⏰ Active & Expiring Campaigns (Renewal Pipeline)</h3>
                <?php if (empty($expiring)): ?>
                    <p style="font-size:13px; color:#64748b;">No active campaigns are expiring within the next 14 days.</p>
                <?php else: ?>
                    <table class="dash-table">
                        <thead>
                            <tr>
                                <th>Campaign</th>
                                <th>End Date</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($expiring as $ex): ?>
                                <tr>
                                    <td style="font-weight:600;"><?= htmlspecialchars((string) ($ex['name'] ?? ''), ENT_QUOTES) ?></td>
                                    <td><?= htmlspecialchars((string) ($ex['end_date'] ?? ''), ENT_QUOTES) ?></td>
                                    <td><span style="background:#fef3c7; color:#92400e; padding:2px 8px; border-radius:4px; font-weight:700; font-size:11.5px;"><?= htmlspecialchars((string) ($ex['status'] ?? 'Active'), ENT_QUOTES) ?></span></td>
                                    <td>
                                        <a href="index.php?module=RT_RadioCampaigns&action=DetailView&record=<?= urlencode((string) ($ex['id'] ?? '')) ?>" style="color:#5E2B83; font-weight:700; text-decoration:none;">Open →</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if (!$isEmbedded): ?>
</body>
</html>
<?php endif; ?>

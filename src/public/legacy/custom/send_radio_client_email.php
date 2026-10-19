<?php

declare(strict_types=1);

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

require_once 'custom/include/RadioCRM/RadioScheduleService.php';
require_once 'custom/include/RadioCRM/RadioStationConfigService.php';

use RadioCRM\RadioScheduleService;
use RadioCRM\RadioStationConfigService;

global $db;

$campaignId = trim((string) ($_REQUEST['record'] ?? ''));
$emailType = trim((string) ($_REQUEST['type'] ?? 'approval'));
$recipient = trim((string) ($_REQUEST['email'] ?? ''));
$format = trim((string) ($_REQUEST['format'] ?? 'html'));

if ($campaignId === '') {
    if ($format === 'json') {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Missing campaign record ID']);
        exit;
    }
    die('Missing campaign record ID.');
}

$result = RadioScheduleService::sendClientNotificationEmail($campaignId, $emailType, $recipient, $db);

if ($format === 'json') {
    header('Content-Type: application/json');
    echo json_encode($result);
    exit;
}

$station = RadioStationConfigService::getStationBranding($db);
$stationName = htmlspecialchars((string) ($station['name'] ?? 'Community Radio FM'), ENT_QUOTES);
$recEsc = htmlspecialchars($campaignId, ENT_QUOTES);
$msgEsc = htmlspecialchars((string) ($result['message'] ?? ''), ENT_QUOTES);
$recipEsc = htmlspecialchars((string) ($result['recipient'] ?? ''), ENT_QUOTES);
$subjEsc = htmlspecialchars((string) ($result['subject'] ?? ''), ENT_QUOTES);
$outboxEsc = htmlspecialchars((string) ($result['outbox_file'] ?? ''), ENT_QUOTES);
$isOk = !empty($result['success']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title><?= $stationName ?> — Client Email Dispatch</title>
<style>
body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f4f6f8; padding: 40px 20px; color: #1e293b; }
.card { max-width: 680px; margin: 0 auto; background: #fff; border: 1px solid #cbd5e1; border-radius: 10px; overflow: hidden; box-shadow: 0 8px 24px rgba(0,0,0,0.06); }
.hdr { background: #5E2B83; color: #fff; padding: 20px 26px; }
.hdr h2 { margin: 0; font-size: 20px; }
.body { padding: 26px; }
.badge { display: inline-block; padding: 6px 12px; border-radius: 6px; font-weight: 700; font-size: 13px; margin-bottom: 16px; }
.badge-ok { background: #dcfce7; color: #166534; }
.badge-err { background: #fee2e2; color: #991b1b; }
.meta { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 14px 18px; font-size: 13.5px; line-height: 1.7; margin-bottom: 22px; }
.btn { display: inline-block; background: #5E2B83; color: #fff; text-decoration: none; padding: 10px 20px; border-radius: 6px; font-weight: 700; font-size: 13.5px; margin-right: 10px; }
.btn-sec { background: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; }
</style>
</head>
<body>
<div class="card">
    <div class="hdr">
        <h2>📧 <?= $stationName ?> — Client Notification Dispatch</h2>
    </div>
    <div class="body">
        <div class="badge <?= $isOk ? 'badge-ok' : 'badge-err' ?>">
            <?= $isOk ? '✓ Email Dispatched & Logged to Station Outbox' : '⚠ Dispatch Error' ?>
        </div>
        <p style="margin-top:0; font-size:14.5px;"><?= $msgEsc ?></p>
        <?php if ($isOk): ?>
        <div class="meta">
            <div><strong>Recipient:</strong> <?= $recipEsc ?></div>
            <div><strong>Subject:</strong> <?= $subjEsc ?></div>
            <div><strong>Audit Outbox Path:</strong> <code><?= $outboxEsc ?></code></div>
        </div>
        <?php endif; ?>
        <div>
            <a class="btn" href="index.php?module=RT_RadioCampaigns&action=DetailView&record=<?= urlencode($campaignId) ?>">← Return to Campaign</a>
            <a class="btn btn-sec" href="index.php?entryPoint=radio_campaign_client_review&record=<?= urlencode($campaignId) ?>" target="_blank">🔍 Open Client Approval Portal</a>
            <a class="btn btn-sec" href="index.php?entryPoint=generate_broadcast_affidavit&record=<?= urlencode($campaignId) ?>" target="_blank">📜 View Broadcast Affidavit</a>
        </div>
    </div>
</div>
</body>
</html>
<?php
exit;

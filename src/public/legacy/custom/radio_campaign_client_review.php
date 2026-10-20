<?php

declare(strict_types=1);

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

require_once 'custom/include/RadioCRM/RadioScheduleService.php';

use RadioCRM\RadioScheduleService;

global $db;

$token = trim((string) ($_REQUEST['token'] ?? ''));
if ($token === '' || strlen($token) < 16) {
    die('Invalid or missing client approval token.');
}

$tokEsc = $db->quote($token);
$res = $db->query(
    "SELECT id, name, start_date, end_date, number_of_spots, spot_length, status,
            myriad_cart_number, script_text, voice_talent, production_status,
            client_approved_at, client_feedback, filename
     FROM rt_radiocampaigns
     WHERE client_approval_token = '{$tokEsc}' AND deleted = 0
     LIMIT 1",
    false
);
$camp = $res ? $db->fetchByAssoc($res) : null;
if (!$camp) {
    die('Campaign record not found for this authorization link.');
}

$campId = (string) $camp['id'];
$cIdEsc = $db->quote($campId);
$nowUtc = gmdate('Y-m-d H:i:s');
$actionTaken = '';

// Handle client form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = trim((string) ($_POST['client_action'] ?? ''));
    if ($postAction === 'approve') {
        $db->query(
            "UPDATE rt_radiocampaigns
             SET production_status = 'Approved',
                 client_approved_at = '{$nowUtc}',
                 date_modified = '{$nowUtc}'
             WHERE id = '{$cIdEsc}'",
            false
        );
        $camp['production_status'] = 'Approved';
        $camp['client_approved_at'] = $nowUtc;
        $actionTaken = 'approved';
    } elseif ($postAction === 'feedback') {
        $fbText = trim((string) ($_POST['feedback_text'] ?? ''));
        if ($fbText !== '') {
            $existing = trim((string) ($camp['client_feedback'] ?? ''));
            $newFb = ($existing !== '' ? $existing . "\n---\n" : '') . "[Client Feedback " . date('Y-m-d H:i') . "]: " . $fbText;
            $newFbEsc = $db->quote($newFb);
            $db->query(
                "UPDATE rt_radiocampaigns
                 SET client_feedback = '{$newFbEsc}',
                     production_status = 'Scripting',
                     date_modified = '{$nowUtc}'
                 WHERE id = '{$cIdEsc}'",
                false
            );
            $camp['client_feedback'] = $newFb;
            $camp['production_status'] = 'Scripting';
            $actionTaken = 'feedback_submitted';
        }
    }
}

// Find audio file if available
$audioInfo = RadioScheduleService::findCampaignAudioFile($db, $campId, (string) ($camp['filename'] ?? ''));
$hasAudio = is_array($audioInfo) && !empty($audioInfo['source_path']) && file_exists($audioInfo['source_path']);
$audioMime = $hasAudio ? (string) ($audioInfo['mime_type'] ?? 'audio/mpeg') : 'audio/mpeg';
$audioOrigName = $hasAudio ? (string) ($audioInfo['original_name'] ?? ($camp['filename'] ?? 'advert.mp3')) : (string) ($camp['filename'] ?? '');

// Serve audio stream if stream=1 requested
if (isset($_GET['stream']) && $_GET['stream'] === '1' && $hasAudio) {
    header('Content-Type: ' . $audioMime);
    header('Accept-Ranges: bytes');
    header('Content-Length: ' . filesize($audioInfo['source_path']));
    readfile($audioInfo['source_path']);
    exit;
}

require_once 'custom/include/RadioCRM/RadioStationConfigService.php';
$stConfig = \RadioCRM\RadioStationConfigService::getSettings($db);
$stName = htmlspecialchars((string) ($stConfig['station_name'] ?? 'Radio Station'), ENT_QUOTES);
$stSlogan = htmlspecialchars((string) ($stConfig['station_slogan'] ?? 'On-Air Broadcast'), ENT_QUOTES);
$logoRel = htmlspecialchars(ltrim((string) ($stConfig['station_logo_url'] ?? 'custom/include/RadioCRM/radio_station_logo.png'), '/'), ENT_QUOTES);

$campName = htmlspecialchars((string) $camp['name'], ENT_QUOTES);
$scriptText = htmlspecialchars((string) ($camp['script_text'] ?: 'Commercial script pending authoring by station production team.'), ENT_QUOTES);
$talent = htmlspecialchars((string) ($camp['voice_talent'] ?: ($stName . ' Voice Team')), ENT_QUOTES);
$status = htmlspecialchars((string) $camp['production_status'], ENT_QUOTES);
$dates = htmlspecialchars("{$camp['start_date']} to {$camp['end_date']}", ENT_QUOTES);
$spots = (int) $camp['number_of_spots'];
$dur = htmlspecialchars((string) $camp['spot_length'], ENT_QUOTES);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $stName ?> - Commercial Creative Approval</title>
<style>
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background: #f6f8fa; margin: 0; padding: 20px; color: #24292f; }
.card { max-width: 720px; margin: 30px auto; background: #ffffff; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); padding: 32px; border: 1px solid #d0d7de; }
.header { display: flex; align-items: center; border-bottom: 2px solid #5E2B83; padding-bottom: 18px; margin-bottom: 24px; }
.header img { width: 64px; height: 64px; margin-right: 18px; object-fit: contain; }
.header h2 { margin: 0; color: #5E2B83; font-size: 22px; }
.header p { margin: 4px 0 0; color: #57606a; font-size: 13px; }
.badge { display: inline-block; padding: 4px 10px; border-radius: 12px; font-weight: 600; font-size: 12px; }
.badge-draft { background: #ddf4ff; color: #0969da; }
.badge-approved { background: #dafbe1; color: #1a7f37; }
.badge-review { background: #fff8c5; color: #9a6700; }
.meta-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 20px; font-size: 14px; }
.meta-item { background: #f6f8fa; padding: 10px 14px; border-radius: 6px; }
.script-box { background: #fffdf5; border: 1px solid #e5d8b8; border-radius: 6px; padding: 18px; font-family: Georgia, serif; font-size: 15px; line-height: 1.6; white-space: pre-wrap; margin-bottom: 24px; }
.audio-box { background: #f0f4f8; border: 1px solid #d0d7de; border-radius: 6px; padding: 18px; margin-bottom: 24px; text-align: center; }
.btn { display: inline-block; padding: 10px 22px; border-radius: 6px; font-weight: 600; font-size: 14px; cursor: pointer; border: none; text-decoration: none; }
.btn-approve { background: #1a7f37; color: #fff; margin-right: 12px; }
.btn-feedback { background: #6e7781; color: #fff; }
.alert-success { background: #dafbe1; color: #1a7f37; border: 1px solid #4ac26b; padding: 12px 16px; border-radius: 6px; margin-bottom: 20px; font-weight: 600; }
</style>
</head>
<body>
<div class="card">
    <div class="header">
        <img src="<?= $logoRel ?>" alt="<?= $stName ?>" onerror="this.src='custom/include/RadioCRM/radio_station_logo.png'" />
        <div>
            <h2><?= $stName ?> Commercial Creative Approval</h2>
            <p><?= $stSlogan ?></p>
        </div>
    </div>

    <?php if ($actionTaken === 'approved'): ?>
        <div class="alert-success">✓ Commercial approved for broadcast! Our station operations team has been notified.</div>
    <?php elseif ($actionTaken === 'feedback_submitted'): ?>
        <div class="alert-success">✓ Thank you! Your revision notes have been submitted to the production team.</div>
    <?php endif; ?>

    <div class="meta-grid">
        <div class="meta-item"><strong>Campaign:</strong> <?= $campName ?></div>
        <div class="meta-item"><strong>Flight Dates:</strong> <?= $dates ?></div>
        <div class="meta-item"><strong>Contracted Spots:</strong> <?= $spots ?> (<?= $dur ?>)</div>
        <div class="meta-item"><strong>Voice Talent:</strong> <?= $talent ?></div>
        <div class="meta-item">
            <strong>Status:</strong>
            <span class="badge <?= $status === 'Approved' ? 'badge-approved' : 'badge-review' ?>"><?= $status ?></span>
        </div>
        <div class="meta-item"><strong>Cart:</strong> <?= htmlspecialchars((string) ($camp['myriad_cart_number'] ?: 'Auto-Allocated'), ENT_QUOTES) ?></div>
    </div>

    <h3 style="color:#5E2B83; margin-bottom:8px;">1. Commercial Script / Voice Copy</h3>
    <div class="script-box"><?= $scriptText ?></div>

    <?php if ($hasAudio): ?>
        <h3 style="color:#5E2B83; margin-bottom:8px;">2. Advert Audio Preview</h3>
        <div class="audio-box">
            <audio controls style="width: 100%; max-width: 500px;">
                <source src="index.php?entryPoint=radio_campaign_client_review&token=<?= htmlspecialchars($token, ENT_QUOTES) ?>&stream=1" type="<?= htmlspecialchars($audioMime, ENT_QUOTES) ?>">
                Your browser does not support audio playback.
            </audio>
            <div style="font-size:12px; color:#57606a; margin-top:8px;">Uploaded File: <?= htmlspecialchars($audioOrigName, ENT_QUOTES) ?></div>
        </div>
    <?php endif; ?>

    <h3 style="color:#5E2B83; margin-bottom:8px;">3. Sign-off & Client Decision</h3>
    <?php if ($status === 'Approved'): ?>
        <div class="alert-success">
            This commercial was officially approved on <?= htmlspecialchars((string) $camp['client_approved_at'], ENT_QUOTES) ?> UTC.
        </div>
    <?php else: ?>
        <form method="POST" style="margin-top:14px;">
            <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES) ?>">
            <p style="font-size:14px; color:#57606a; margin-bottom:14px;">
                Please review the script and audio preview above. Click <strong>Approve for Broadcast</strong> to authorize playout on <?= $stName ?>, or provide revision requests below.
            </p>
            <div style="margin-bottom:18px;">
                <button type="submit" name="client_action" value="approve" class="btn btn-approve">✓ Approve for Broadcast</button>
            </div>
            <div style="border-top:1px solid #d0d7de; padding-top:18px;">
                <label style="display:block; font-weight:600; font-size:13px; margin-bottom:6px;">Need changes? Submit revision requests:</label>
                <textarea name="feedback_text" rows="3" style="width:100%; box-sizing:border-box; padding:8px; border:1px solid #ccc; border-radius:4px;" placeholder="Type requested pronunciation changes, text edits, or voice notes here..."></textarea>
                <div style="margin-top:8px;">
                    <button type="submit" name="client_action" value="feedback" class="btn btn-feedback">Submit Feedback</button>
                </div>
            </div>
        </form>
    <?php endif; ?>
</div>
</body>
</html>

<?php

declare(strict_types=1);

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

require_once 'custom/include/RadioCRM/RadioScheduleService.php';

use RadioCRM\RadioScheduleService;

global $db, $current_user;

if (empty($current_user) || empty($current_user->id)) {
    die('Authentication required.');
}

$recordId = trim((string) ($_REQUEST['record'] ?? ($_REQUEST['id'] ?? '')));
if ($recordId === '') {
    die('Campaign record ID is required.');
}

$cIdEsc = $db->quote($recordId);
$campRes = $db->query(
    "SELECT id, name, filename, file_mime_type FROM rt_radiocampaigns WHERE id = '{$cIdEsc}' AND deleted = 0 LIMIT 1",
    false
);
$camp = $campRes ? $db->fetchByAssoc($campRes) : null;
if (!$camp) {
    die('Radio Campaign record not found.');
}

// 1. Stream or download existing audio file
if ((isset($_GET['stream']) && $_GET['stream'] === '1') || (isset($_GET['download']) && $_GET['download'] === '1')) {
    $audioInfo = RadioScheduleService::findCampaignAudioFile($db, $recordId, (string) ($camp['filename'] ?? ''));
    if (!is_array($audioInfo) || empty($audioInfo['source_path']) || !is_file($audioInfo['source_path'])) {
        http_response_code(404);
        die('No audio file attached to this campaign.');
    }

    $filePath = (string) $audioInfo['source_path'];
    $origName = (string) ($audioInfo['original_name'] ?: 'advert.mp3');
    $mimeType = (string) ($audioInfo['mime_type'] ?: RadioScheduleService::detectAudioMimeType($origName, $filePath));
    $fileSize = (int) filesize($filePath);

    while (ob_get_level() && @ob_end_clean()) {
        // flush buffers
    }

    header('Cache-Control: no-cache, must-revalidate');
    header('Accept-Ranges: bytes');
    header('Content-Type: ' . $mimeType);

    $safeDispName = str_replace(['"', "\r", "\n"], '', $origName);
    if (isset($_GET['download']) && $_GET['download'] === '1') {
        header('Content-Disposition: attachment; filename="' . $safeDispName . '"');
        header('Content-Length: ' . $fileSize);
        readfile($filePath);
        exit;
    }

    header('Content-Disposition: inline; filename="' . $safeDispName . '"');

    if (isset($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d+)-(\d*)/', (string) $_SERVER['HTTP_RANGE'], $m)) {
        $start = (int) $m[1];
        $end = ($m[2] !== '') ? (int) $m[2] : ($fileSize - 1);
        if ($start >= 0 && $start < $fileSize && $end >= $start && $end < $fileSize) {
            $length = $end - $start + 1;
            http_response_code(206);
            header("Content-Range: bytes {$start}-{$end}/{$fileSize}");
            header('Content-Length: ' . $length);
            $fh = fopen($filePath, 'rb');
            if ($fh) {
                fseek($fh, $start);
                echo fread($fh, $length);
                fclose($fh);
            }
            exit;
        }
    }

    header('Content-Length: ' . $fileSize);
    readfile($filePath);
    exit;
}

// 2. Remove existing audio file
if (isset($_REQUEST['remove_audio']) && (string) $_REQUEST['remove_audio'] === '1') {
    RadioScheduleService::removeCampaignAudioFile($db, $recordId);
    if (isset($_REQUEST['ajax']) && (string) $_REQUEST['ajax'] === '1') {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Audio file removed.']);
        exit;
    }
    header("Location: index.php?module=RT_RadioCampaigns&action=DetailView&record={$recordId}&audio_status=removed");
    exit;
}

// 3. Handle MP3 / WAV upload
$fileField = null;
if (isset($_FILES['campaign_audio_file']) && is_array($_FILES['campaign_audio_file'])) {
    $fileField = $_FILES['campaign_audio_file'];
} elseif (isset($_FILES['file_file']) && is_array($_FILES['file_file'])) {
    $fileField = $_FILES['file_file'];
}

if (!$fileField || ($fileField['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    $errCode = (int) ($fileField['error'] ?? UPLOAD_ERR_NO_FILE);
    $errMsg = match ($errCode) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Audio file exceeds maximum allowed upload size (64 MB).',
        UPLOAD_ERR_PARTIAL => 'Audio file was only partially uploaded. Please try again.',
        UPLOAD_ERR_NO_FILE => 'Please choose an MP3 (.mp3) or WAV (.wav) file before clicking Upload.',
        default => 'Audio upload error (code ' . $errCode . ').',
    };
    if (isset($_REQUEST['ajax']) && (string) $_REQUEST['ajax'] === '1') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $errMsg]);
        exit;
    }
    header("Location: index.php?module=RT_RadioCampaigns&action=DetailView&record={$recordId}&audio_error=" . rawurlencode($errMsg));
    exit;
}

$tmpPath = (string) ($fileField['tmp_name'] ?? '');
$origName = (string) ($fileField['name'] ?? 'advert.mp3');

$saveRes = RadioScheduleService::saveCampaignAudioFile($db, $recordId, $tmpPath, $origName);

if (isset($_REQUEST['ajax']) && (string) $_REQUEST['ajax'] === '1') {
    header('Content-Type: application/json');
    echo json_encode($saveRes);
    exit;
}

if (!empty($saveRes['success'])) {
    header("Location: index.php?module=RT_RadioCampaigns&action=DetailView&record={$recordId}&audio_status=uploaded");
} else {
    $errMsg = (string) ($saveRes['message'] ?? 'Audio upload failed.');
    header("Location: index.php?module=RT_RadioCampaigns&action=DetailView&record={$recordId}&audio_error=" . rawurlencode($errMsg));
}
exit;

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

$recordId = trim((string) ($_REQUEST['record'] ?? ''));
$reconDir = '/srv/myriad_reconciliation';
if (!is_dir($reconDir)) {
    @mkdir($reconDir, 02775, true);
}

$files = glob($reconDir . '/*.{log,asplayed,txt}', GLOB_BRACE) ?: [];
$results = [];

// If files exist in /srv/myriad_reconciliation, ingest them
foreach ($files as $f) {
    $res = RadioScheduleService::reconcileMyriadLogFile($f, $db);
    $results[] = [
        'file' => basename($f),
        'result' => $res,
    ];
}

// If record ID was provided and no files existed in reconciliation folder,
// simulate/reconcile from existing myriad_exports daily logs if available
if (empty($results) && $recordId !== '') {
    $cIdEsc = $db->quote($recordId);
    $cRes = $db->query("SELECT id, name, myriad_cart_number, start_date, end_date FROM rt_radiocampaigns WHERE id = '{$cIdEsc}'", false);
    $cRow = $cRes ? $db->fetchByAssoc($cRes) : null;
    if ($cRow && !empty($cRow['myriad_cart_number'])) {
        $cart = $cRow['myriad_cart_number'];
        $dailyFiles = glob('/srv/myriad_exports/myriad_daily_logs/*.log') ?: [];
        foreach ($dailyFiles as $df) {
            $content = file_get_contents($df);
            if ($content !== false && strpos($content, "|{$cart}|") !== false) {
                $res = RadioScheduleService::reconcileMyriadLogFile($df, $db);
                $results[] = [
                    'file' => basename($df),
                    'result' => $res,
                ];
            }
        }
    }
}

// If JSON requested via AJAX
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'success' => true,
        'results' => $results,
    ]);
    return;
}

// HTML redirect back to campaign record or summary
if ($recordId !== '') {
    $msg = urlencode('Reconciliation completed. Processed ' . count($results) . ' log file(s).');
    header("Location: index.php?module=RT_RadioCampaigns&action=DetailView&record={$recordId}&recon_msg={$msg}");
    exit;
}

echo "<h3>PUKfm Playout Log Reconciliation</h3>";
echo "<p>Processed " . count($results) . " log file(s).</p>";
echo "<pre>" . print_r($results, true) . "</pre>";
echo "<p><a href='index.php?module=RT_RadioCampaigns&action=index'>Back to Radio Campaigns</a></p>";

<?php

declare(strict_types=1);

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

require_once 'custom/include/RadioCRM/RadioScheduleService.php';

use RadioCRM\RadioScheduleService;

global $db;

$targetDate = isset($_REQUEST['date']) && $_REQUEST['date'] !== '' ? (string) $_REQUEST['date'] : date('Y-m-d');
$campaignId = isset($_REQUEST['record']) && $_REQUEST['record'] !== '' ? (string) $_REQUEST['record'] : null;

$res = RadioScheduleService::generatePresenterLiveReadSheetHtml($targetDate, $campaignId, $db);
header('Content-Type: text/html; charset=utf-8');
echo (string) ($res['html'] ?? '');
exit;

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
if ($recordId === '') {
    die('Campaign record ID is required.');
}

$res = RadioScheduleService::generateBroadcastAffidavitHtml($recordId, $db);
if (!$res['success']) {
    die('Error generating affidavit: ' . htmlspecialchars($res['message'] ?? 'Unknown error'));
}

header('Content-Type: text/html; charset=UTF-8');
echo $res['html'];
exit;

<?php

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

require_once 'custom/include/RadioCRM/RadioScheduleService.php';
require_once 'custom/modules/AOS_Quotes/RadioQuoteScheduleProducts.php';

use RadioCRM\RadioScheduleService;

header('Content-Type: application/json; charset=UTF-8');

global $current_user, $db;

if (empty($current_user) || empty($current_user->id)) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Authentication required.',
    ]);
    return;
}

if (class_exists('ACLController') && !ACLController::checkAccess('AOS_Quotes', 'edit', true)) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Access denied for Quotes.',
    ]);
    return;
}

$rawBody = file_get_contents('php://input');
$payload = [];
if (is_string($rawBody) && trim($rawBody) !== '') {
    $decoded = json_decode($rawBody, true);
    if (is_array($decoded)) {
        $payload = $decoded;
    }
}
if (empty($payload)) {
    $payload = $_POST;
}

$quoteId = trim((string) ($payload['quote_id'] ?? $_REQUEST['record'] ?? ''));
if ($quoteId !== '' && class_exists('BeanFactory')) {
    $quoteBean = BeanFactory::getBean('AOS_Quotes', $quoteId);
    if (empty($quoteBean) || empty($quoteBean->id) || !$quoteBean->ACLAccess('view')) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'You do not have access to this Quote record.',
        ]);
        return;
    }
}

$action = trim((string) ($payload['action'] ?? $_REQUEST['action'] ?? 'calculate'));
$startDate = isset($payload['campaign_start_date_c']) ? (string) $payload['campaign_start_date_c'] : null;
$endDate = isset($payload['campaign_end_date_c']) ? (string) $payload['campaign_end_date_c'] : null;
$spotDuration = isset($payload['spot_duration_c']) ? (string) $payload['spot_duration_c'] : null;
$industryCategory = isset($payload['industry_category_c']) ? (string) $payload['industry_category_c'] : null;
$appliedPackage = isset($payload['applied_package_c']) ? (string) $payload['applied_package_c'] : null;
$cells = (isset($payload['cells']) && is_array($payload['cells'])) ? $payload['cells'] : $payload;

if (is_object($db)) {
    RadioQuoteScheduleProducts::ensureCatalogueProductsExist($db);
}

if ($action === 'get_packages') {
    echo json_encode([
        'success' => true,
        'action' => 'get_packages',
        'packages' => RadioScheduleService::getPackageDefinitions(),
    ]);
    return;
}

if ($action === 'check_avails') {
    $capacity = RadioScheduleService::checkStationCapacity(
        $cells,
        $startDate,
        $endDate,
        $quoteId !== '' ? $quoteId : null,
        null,
        $db ?? null
    );
    echo json_encode([
        'success' => true,
        'action' => 'check_avails',
        'capacity' => $capacity,
    ]);
    return;
}

if ($action === 'check_conflicts') {
    $conflicts = RadioScheduleService::checkCategoryConflicts(
        $industryCategory ?? '',
        $cells,
        $startDate,
        $endDate,
        null,
        $db ?? null
    );
    echo json_encode([
        'success' => true,
        'action' => 'check_conflicts',
        'conflicts' => $conflicts,
    ]);
    return;
}

$result = RadioScheduleService::calculateSchedule($cells, $startDate, $endDate, $db ?? null, $spotDuration, $appliedPackage);

if (!$result['valid']) {
    echo json_encode([
        'success' => false,
        'message' => $result['date_error'] ?? 'Invalid schedule input values.',
        'weekly_spots' => $result['weekly_spots'],
        'total_spots' => $result['total_spots'],
        'invalid_fields' => $result['invalid_fields'],
    ]);
    return;
}

// Compute avails and conflicts if dates are valid
$capacity = null;
$conflicts = [];
if ($result['date_range_valid']) {
    $capacity = RadioScheduleService::checkStationCapacity(
        $result['cells'],
        $result['start_date'],
        $result['end_date'],
        $quoteId !== '' ? $quoteId : null,
        null,
        $db ?? null
    );
    if (!empty($industryCategory) && $industryCategory !== 'other') {
        $conflicts = RadioScheduleService::checkCategoryConflicts(
            $industryCategory,
            $result['cells'],
            $result['start_date'],
            $result['end_date'],
            null,
            $db ?? null
        );
    }
}

echo json_encode([
    'success' => true,
    'quote_id' => $quoteId,
    'start_date' => $result['start_date'],
    'end_date' => $result['end_date'],
    'dates_provided' => $result['dates_provided'],
    'date_range_valid' => $result['date_range_valid'],
    'spot_duration' => $result['spot_duration'] ?? '30 sec',
    'duration_multiplier' => $result['duration_multiplier'] ?? 1.0,
    'applied_package' => $result['applied_package'] ?? '',
    'weekly_spots' => $result['weekly_spots'],
    'total_spots' => $result['total_spots'],
    'programmes' => $result['programmes'],
    'active_products' => $result['active_products'],
    'totals' => $result['totals'],
    'capacity' => $capacity,
    'conflicts' => $conflicts,
]);

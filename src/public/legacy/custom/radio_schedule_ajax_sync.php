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

$startDate = isset($payload['campaign_start_date_c']) ? (string) $payload['campaign_start_date_c'] : null;
$endDate = isset($payload['campaign_end_date_c']) ? (string) $payload['campaign_end_date_c'] : null;
$cells = (isset($payload['cells']) && is_array($payload['cells'])) ? $payload['cells'] : $payload;

if (is_object($db)) {
    RadioQuoteScheduleProducts::ensureCatalogueProductsExist($db);
}

$result = RadioScheduleService::calculateSchedule($cells, $startDate, $endDate, $db ?? null);

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

echo json_encode([
    'success' => true,
    'quote_id' => $quoteId,
    'start_date' => $result['start_date'],
    'end_date' => $result['end_date'],
    'dates_provided' => $result['dates_provided'],
    'date_range_valid' => $result['date_range_valid'],
    'weekly_spots' => $result['weekly_spots'],
    'total_spots' => $result['total_spots'],
    'programmes' => $result['programmes'],
    'active_products' => $result['active_products'],
    'totals' => $result['totals'],
]);

<?php

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

require_once 'custom/include/RadioCRM/RadioScheduleService.php';

use RadioCRM\RadioScheduleService;

global $db, $current_user;

$campaignId = trim((string) ($_REQUEST['record'] ?? $_REQUEST['id'] ?? ''));
if ($campaignId === '') {
    SugarApplication::appendErrorMessage('Campaign record ID is required.');
    SugarApplication::redirect('index.php?module=RT_RadioCampaigns&action=index');
    return;
}

if (class_exists('ACLController')) {
    if (!ACLController::checkAccess('RT_RadioCampaigns', 'view', true)
        || !ACLController::checkAccess('RT_RadioCampaigns', 'export', true)
    ) {
        SugarApplication::appendErrorMessage('You do not have permission to export Radio Campaigns.');
        SugarApplication::redirect('index.php?module=RT_RadioCampaigns&action=DetailView&record=' . urlencode($campaignId));
        return;
    }
}

$campaign = BeanFactory::getBean('RT_RadioCampaigns', $campaignId);
if (empty($campaign) || empty($campaign->id) || !empty($campaign->deleted)) {
    SugarApplication::appendErrorMessage('Radio Campaign record not found.');
    SugarApplication::redirect('index.php?module=RT_RadioCampaigns&action=index');
    return;
}

if ((method_exists($campaign, 'ACLAccess') && !$campaign->ACLAccess('view'))
    || (method_exists($campaign, 'ACLAccess') && !$campaign->ACLAccess('export'))
) {
    SugarApplication::appendErrorMessage('Access denied: you are not authorized to export this Radio Campaign.');
    SugarApplication::redirect('index.php?module=RT_RadioCampaigns&action=DetailView&record=' . urlencode($campaignId));
    return;
}

$quoteId = trim((string) ($campaign->linked_quote_id ?? ''));
if ($quoteId === '') {
    SugarApplication::appendErrorMessage('Radio Campaign does not have a linked source Quote.');
    SugarApplication::redirect('index.php?module=RT_RadioCampaigns&action=DetailView&record=' . urlencode($campaignId));
    return;
}

$quote = BeanFactory::getBean('AOS_Quotes', $quoteId);
if (empty($quote) || empty($quote->id) || !empty($quote->deleted)) {
    SugarApplication::appendErrorMessage('Linked source Quote record not found.');
    SugarApplication::redirect('index.php?module=RT_RadioCampaigns&action=DetailView&record=' . urlencode($campaignId));
    return;
}

$qIdEsc = $db->quote((string) $quote->id);
$cstmRes = $db->query("SELECT * FROM aos_quotes_cstm WHERE id_c = '{$qIdEsc}'", false);
$cstmRow = ($cstmRes ? $db->fetchByAssoc($cstmRes) : null) ?: [];

$quoteScheduleData = [];
foreach (RadioScheduleService::getAllScheduleFields() as $fieldName => $meta) {
    $quoteScheduleData[$fieldName] = $cstmRow[$fieldName] ?? ($quote->$fieldName ?? 0);
}

$campaignData = [
    'id' => (string) $campaign->id,
    'name' => (string) $campaign->name,
    'start_date' => (string) ($campaign->start_date ?: ($cstmRow['campaign_start_date_c'] ?? '')),
    'end_date' => (string) ($campaign->end_date ?: ($cstmRow['campaign_end_date_c'] ?? '')),
    'spot_length' => (string) ($campaign->spot_length ?: '30 sec'),
    'myriad_cart_number' => (string) ($campaign->myriad_cart_number ?? ''),
    'quote_reference' => (string) ($campaign->quote_reference ?: ($quote->number ?? '')),
    'linked_quote_id' => (string) $quote->id,
];

$exportResult = RadioScheduleService::exportCampaignToMyriad($campaignData, $quoteScheduleData);
if (!$exportResult['success']) {
    SugarApplication::appendErrorMessage($exportResult['message']);
    SugarApplication::redirect('index.php?module=RT_RadioCampaigns&action=DetailView&record=' . urlencode($campaignId));
    return;
}

$campaign->status = 'exported_to_myriad';
$campaign->number_of_spots = (int) ($exportResult['total_plays'] ?? $campaign->number_of_spots);
$campaign->save();

SugarApplication::redirect('index.php?module=RT_RadioCampaigns&action=DetailView&record=' . urlencode($campaignId));

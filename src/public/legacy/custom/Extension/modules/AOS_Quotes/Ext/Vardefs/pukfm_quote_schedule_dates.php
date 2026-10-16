<?php

if (!class_exists('\RadioCRM\RadioScheduleService')) {
    $servicePath = dirname(__DIR__, 4) . '/include/RadioCRM/RadioScheduleService.php';
    if (file_exists($servicePath)) {
        require_once $servicePath;
    } elseif (file_exists('custom/include/RadioCRM/RadioScheduleService.php')) {
        require_once 'custom/include/RadioCRM/RadioScheduleService.php';
    }
}

$dictionary['AOS_Quotes']['custom_fields'] = true;

$dictionary['AOS_Quotes']['fields']['campaign_start_date_c'] = [
    'name' => 'campaign_start_date_c',
    'vname' => 'LBL_CAMPAIGN_START_DATE',
    'type' => 'date',
    'source' => 'custom_fields',
    'audited' => false,
    'massupdate' => false,
    'importable' => 'true',
    'reportable' => true,
];

$dictionary['AOS_Quotes']['fields']['campaign_end_date_c'] = [
    'name' => 'campaign_end_date_c',
    'vname' => 'LBL_CAMPAIGN_END_DATE',
    'type' => 'date',
    'source' => 'custom_fields',
    'audited' => false,
    'massupdate' => false,
    'importable' => 'true',
    'reportable' => true,
];

if (class_exists('\RadioCRM\RadioScheduleService')) {
    foreach (\RadioCRM\RadioScheduleService::getAllScheduleFields() as $fieldName => $meta) {
        $labelKey = 'LBL_' . strtoupper($fieldName);
        $dictionary['AOS_Quotes']['fields'][$fieldName] = [
            'name' => $fieldName,
            'vname' => $labelKey,
            'type' => 'int',
            'len' => 11,
            'default' => 0,
            'source' => 'custom_fields',
            'audited' => false,
            'massupdate' => false,
            'importable' => 'true',
            'reportable' => true,
        ];
    }
}

$dictionary['AOS_Quotes']['fields']['rt_radiocampaigns_aos_quotes'] = [
    'name' => 'rt_radiocampaigns_aos_quotes',
    'type' => 'link',
    'relationship' => 'rt_radiocampaigns_aos_quotes',
    'source' => 'non-db',
    'module' => 'RT_RadioCampaigns',
    'bean_name' => 'RT_RadioCampaigns',
    'vname' => 'LBL_RT_RADIOCAMPAIGNS_AOS_QUOTES_FROM_RT_RADIOCAMPAIGNS_TITLE',
];

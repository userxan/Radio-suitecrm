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

$dictionary['AOS_Quotes']['fields']['industry_category_c'] = [
    'name' => 'industry_category_c',
    'vname' => 'LBL_INDUSTRY_CATEGORY',
    'type' => 'enum',
    'options' => 'radio_industry_category_list',
    'len' => 100,
    'default' => 'other',
    'source' => 'custom_fields',
    'audited' => true,
    'reportable' => true,
    'importable' => 'true',
];

$dictionary['AOS_Quotes']['fields']['spot_duration_c'] = [
    'name' => 'spot_duration_c',
    'vname' => 'LBL_SPOT_LENGTH',
    'type' => 'enum',
    'options' => 'radio_spot_duration_list',
    'len' => 20,
    'default' => '30 sec',
    'source' => 'custom_fields',
    'audited' => true,
    'reportable' => true,
    'importable' => 'true',
];

$dictionary['AOS_Quotes']['fields']['applied_package_c'] = [
    'name' => 'applied_package_c',
    'vname' => 'LBL_APPLIED_PACKAGE',
    'type' => 'enum',
    'options' => 'radio_packages_list',
    'len' => 100,
    'default' => '',
    'source' => 'custom_fields',
    'audited' => true,
    'reportable' => true,
    'importable' => 'true',
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

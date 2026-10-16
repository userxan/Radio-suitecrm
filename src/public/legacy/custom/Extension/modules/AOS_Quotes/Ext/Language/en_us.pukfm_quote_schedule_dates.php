<?php

if (!class_exists('\RadioCRM\RadioScheduleService')) {
    $servicePath = dirname(__DIR__, 4) . '/include/RadioCRM/RadioScheduleService.php';
    if (file_exists($servicePath)) {
        require_once $servicePath;
    } elseif (file_exists('custom/include/RadioCRM/RadioScheduleService.php')) {
        require_once 'custom/include/RadioCRM/RadioScheduleService.php';
    }
}

$mod_strings['LBL_CAMPAIGN_START_DATE'] = 'Campaign Start Date';
$mod_strings['LBL_CAMPAIGN_END_DATE'] = 'Campaign End Date';
$mod_strings['LBL_WEEKLY_ADVERT_SCHEDULE'] = 'Weekly Advert Schedule';
$mod_strings['LBL_CONVERT_TO_RADIO_CAMPAIGN'] = 'Convert to Radio Campaign';
$mod_strings['LBL_RT_RADIOCAMPAIGNS_AOS_QUOTES_FROM_RT_RADIOCAMPAIGNS_TITLE'] = 'Radio Campaigns';

if (class_exists('\RadioCRM\RadioScheduleService')) {
    foreach (\RadioCRM\RadioScheduleService::getAllScheduleFields() as $fieldName => $meta) {
        $labelKey = 'LBL_' . strtoupper($fieldName);
        $mod_strings[$labelKey] = sprintf(
            '%s - %s (%s)',
            $meta['day_label'],
            $meta['slot_label'],
            $meta['hour_label']
        );
    }
}

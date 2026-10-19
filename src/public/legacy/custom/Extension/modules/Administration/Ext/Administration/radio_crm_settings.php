<?php

$admin_option_defs = [];
$admin_option_defs['Administration']['radio_station_wizard'] = [
    'Administration',
    'LBL_RADIO_STATION_SETUP_TITLE',
    'LBL_RADIO_STATION_SETUP_DESC',
    './index.php?entryPoint=radio_station_wizard',
    'admin-radio-settings',
];

$admin_group_header['radio_crm'] = [
    'LBL_RADIO_STATION_ADMIN_TITLE',
    '',
    false,
    $admin_option_defs,
    'LBL_RADIO_STATION_ADMIN_DESC',
    'radio_crm_settings',
];

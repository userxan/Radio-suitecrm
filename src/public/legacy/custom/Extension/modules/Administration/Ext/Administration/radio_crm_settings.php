<?php

$admin_option_defs = [];
$admin_option_defs['Administration']['radio_station_wizard'] = [
    'Administration',
    'LBL_RADIO_STATION_SETUP_TITLE',
    'LBL_RADIO_STATION_SETUP_DESC',
    './index.php?module=Administration&action=radio_station_wizard',
    'admin-aos-settings',
];
$admin_option_defs['Administration']['radio_station_dashboard'] = [
    'Administration',
    'LBL_RADIO_STATION_DASHBOARD_TITLE',
    'LBL_RADIO_STATION_DASHBOARD_DESC',
    './index.php?module=Administration&action=radio_station_dashboard',
    'admin-aos-settings',
];
$admin_option_defs['Administration']['radio_liveread_sheet'] = [
    'Administration',
    'LBL_RADIO_LIVEREAD_SHEET_TITLE',
    'LBL_RADIO_LIVEREAD_SHEET_DESC',
    './index.php?module=Administration&action=radio_liveread_sheet',
    'admin-aos-settings',
];

$admin_group_header[] = [
    'LBL_RADIO_STATION_ADMIN_TITLE',
    '',
    false,
    $admin_option_defs,
    'LBL_RADIO_STATION_ADMIN_DESC',
    'radio_crm_settings',
];


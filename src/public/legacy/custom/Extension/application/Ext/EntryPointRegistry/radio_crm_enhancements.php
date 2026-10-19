<?php

$entry_point_registry['radio_campaign_reconcile_logs'] = [
    'file' => 'custom/reconcile_radio_campaign_logs.php',
    'auth' => true,
];

$entry_point_registry['generate_broadcast_affidavit'] = [
    'file' => 'custom/generate_broadcast_affidavit.php',
    'auth' => true,
];

$entry_point_registry['convert_campaign_to_invoice'] = [
    'file' => 'custom/convert_campaign_to_invoice.php',
    'auth' => true,
];

$entry_point_registry['radio_campaign_client_review'] = [
    'file' => 'custom/radio_campaign_client_review.php',
    'auth' => false,
];

$entry_point_registry['radio_station_wizard'] = [
    'file' => 'custom/radio_station_wizard.php',
    'auth' => true,
];

$entry_point_registry['generate_presenter_liveread_sheet'] = [
    'file' => 'custom/generate_presenter_liveread_sheet.php',
    'auth' => false,
];

$entry_point_registry['send_radio_client_email'] = [
    'file' => 'custom/send_radio_client_email.php',
    'auth' => true,
];

$entry_point_registry['radio_station_dashboard'] = [
    'file' => 'custom/radio_station_dashboard.php',
    'auth' => true,
];

<?php

declare(strict_types=1);

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

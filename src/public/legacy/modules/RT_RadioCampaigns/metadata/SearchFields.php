<?php

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

$module_name = 'RT_RadioCampaigns';
$searchFields[$module_name] = [
    'name' => ['query_type' => 'default'],
    'status' => ['query_type' => 'default', 'options' => 'rt_campaign_status_list', 'template_var' => 'STATUS_OPTIONS'],
    'myriad_cart_number' => ['query_type' => 'default'],
    'quote_reference' => ['query_type' => 'default'],
    'current_user_only' => [
        'query_type' => 'default',
        'db_field' => ['assigned_user_id'],
        'my_items' => true,
        'vname' => 'LBL_CURRENT_USER_FILTER',
        'type' => 'bool',
    ],
    'assigned_user_id' => ['query_type' => 'default'],
];

<?php

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

$module_name = 'RT_RadioCampaigns';
$searchdefs[$module_name] = [
    'layout' => [
        'basic_search' => [
            'name',
            'current_user_only' => [
                'name' => 'current_user_only',
                'label' => 'LBL_CURRENT_USER_FILTER',
                'type' => 'bool',
            ],
        ],
        'advanced_search' => [
            'name',
            'status',
            'start_date',
            'end_date',
            'myriad_cart_number',
            'quote_reference',
            'assigned_user_id' => [
                'name' => 'assigned_user_id',
                'label' => 'LBL_ASSIGNED_TO',
                'type' => 'enum',
                'function' => [
                    'name' => 'get_user_array',
                    'params' => [false],
                ],
            ],
        ],
    ],
    'templateMeta' => [
        'maxColumns' => '3',
        'maxColumnsBasic' => '4',
        'widths' => ['label' => '10', 'field' => '30'],
    ],
];

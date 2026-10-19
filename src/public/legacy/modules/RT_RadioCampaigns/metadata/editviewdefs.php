<?php

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

$module_name = 'RT_RadioCampaigns';
$viewdefs[$module_name]['EditView'] = [
    'templateMeta' => [
        'form' => [
            'enctype' => 'multipart/form-data',
        ],
        'maxColumns' => '2',
        'widths' => [
            ['label' => '10', 'field' => '30'],
            ['label' => '10', 'field' => '30'],
        ],
        'useTabs' => false,
    ],
    'panels' => [
        'default' => [
            [
                'name',
                'assigned_user_name',
            ],
            [
                'start_date',
                'end_date',
            ],
            [
                'number_of_spots',
                'spot_length',
            ],
            [
                'status',
                'myriad_cart_number',
            ],
            [
                'file',
                'quote_reference',
            ],
            [
                'linked_quote_id',
                '',
            ],
            [
                'description',
            ],
        ],
    ],
];

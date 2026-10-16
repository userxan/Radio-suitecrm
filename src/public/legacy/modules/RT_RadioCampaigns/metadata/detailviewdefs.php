<?php

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

$module_name = 'RT_RadioCampaigns';
$viewdefs[$module_name]['DetailView'] = [
    'templateMeta' => [
        'form' => [
            'buttons' => [
                'EDIT',
                'DUPLICATE',
                'DELETE',
            ],
        ],
        'maxColumns' => '2',
        'widths' => [
            ['label' => '10', 'field' => '30'],
            ['label' => '10', 'field' => '30'],
        ],
        'useTabs' => false,
    ],
    'recordActions' => [
        'actions' => [
            'radio-campaign-export-to-myriad' => [
                'key' => 'radio-campaign-export-to-myriad',
                'labelKey' => 'LBL_EXPORT_TO_MYRIAD',
                'priority' => 500,
                'asyncProcess' => true,
                'modes' => ['detail'],
                'acl' => ['view', 'export'],
                'params' => [
                    'displayConfirmation' => true,
                    'confirmationLabel' => 'LBL_EXPORT_TO_MYRIAD',
                    'disableOnRun' => true,
                ],
            ],
        ],
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
                'quote_reference',
                'linked_quote_id',
            ],
            [
                'description',
            ],
        ],
    ],
];

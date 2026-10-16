<?php

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

$module_name = 'RT_RadioCampaigns';
$listViewDefs[$module_name] = [
    'NAME' => [
        'width' => '25%',
        'label' => 'LBL_NAME',
        'default' => true,
        'link' => true,
    ],
    'START_DATE' => [
        'width' => '12%',
        'label' => 'LBL_START_DATE',
        'default' => true,
    ],
    'END_DATE' => [
        'width' => '12%',
        'label' => 'LBL_END_DATE',
        'default' => true,
    ],
    'NUMBER_OF_SPOTS' => [
        'width' => '12%',
        'label' => 'LBL_NUMBER_OF_SPOTS',
        'default' => true,
    ],
    'STATUS' => [
        'width' => '15%',
        'label' => 'LBL_STATUS',
        'default' => true,
    ],
    'MYRIAD_CART_NUMBER' => [
        'width' => '14%',
        'label' => 'LBL_MYRIAD_CART_NUMBER',
        'default' => true,
    ],
    'QUOTE_REFERENCE' => [
        'width' => '10%',
        'label' => 'LBL_QUOTE_REFERENCE',
        'default' => false,
    ],
    'ASSIGNED_USER_NAME' => [
        'width' => '10%',
        'label' => 'LBL_ASSIGNED_TO_NAME',
        'module' => 'Employees',
        'id' => 'ASSIGNED_USER_ID',
        'default' => false,
    ],
];

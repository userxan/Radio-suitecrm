<?php

$dictionary['rt_radiocampaigns_aos_quotes'] = [
    'true_relationship_type' => 'many-to-many',
    'relationships' => [
        'rt_radiocampaigns_aos_quotes' => [
            'lhs_module' => 'RT_RadioCampaigns',
            'lhs_table' => 'rt_radiocampaigns',
            'lhs_key' => 'id',
            'rhs_module' => 'AOS_Quotes',
            'rhs_table' => 'aos_quotes',
            'rhs_key' => 'id',
            'relationship_type' => 'many-to-many',
            'join_table' => 'rt_radiocampaigns_aos_quotes_c',
            'join_key_lhs' => 'rt_radiocampaigns_aos_quotesrt_radiocampaigns_ida',
            'join_key_rhs' => 'rt_radiocampaigns_aos_quotesaos_quotes_idb',
        ],
    ],
    'table' => 'rt_radiocampaigns_aos_quotes_c',
    'fields' => [
        0 => [
            'name' => 'id',
            'type' => 'varchar',
            'len' => 36,
        ],
        1 => [
            'name' => 'date_modified',
            'type' => 'datetime',
        ],
        2 => [
            'name' => 'deleted',
            'type' => 'bool',
            'len' => '1',
            'default' => '0',
            'required' => true,
        ],
        3 => [
            'name' => 'rt_radiocampaigns_aos_quotesrt_radiocampaigns_ida',
            'type' => 'varchar',
            'len' => 36,
        ],
        4 => [
            'name' => 'rt_radiocampaigns_aos_quotesaos_quotes_idb',
            'type' => 'varchar',
            'len' => 36,
        ],
    ],
    'indices' => [
        0 => [
            'name' => 'rt_radiocampaigns_aos_quotesspk',
            'type' => 'primary',
            'fields' => ['id'],
        ],
        1 => [
            'name' => 'rt_radiocampaigns_aos_quotes_alt',
            'type' => 'alternate_key',
            'fields' => [
                'rt_radiocampaigns_aos_quotesrt_radiocampaigns_ida',
                'rt_radiocampaigns_aos_quotesaos_quotes_idb',
            ],
        ],
    ],
];

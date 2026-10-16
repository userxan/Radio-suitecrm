<?php
$module_name = 'AOS_Quotes';
$_object_name = 'aos_quotes';
$viewdefs[$module_name] = [
    'DetailView' => [
        'templateMeta' => [
            'form' => [
                'buttons' => [
                    0 => 'EDIT',
                    1 => 'DUPLICATE',
                    2 => 'DELETE',
                    3 => 'FIND_DUPLICATES',
                    4 => [
                        'customCode' => '<input type="button" class="button" onClick="showPopup(\'pdf\');" value="{$MOD.LBL_PRINT_AS_PDF}">',
                    ],
                    5 => [
                        'customCode' => '<input type="button" class="button" onClick="showPopup(\'emailpdf\');" value="{$MOD.LBL_EMAIL_PDF}">',
                    ],
                    6 => [
                        'customCode' => '<input type="submit" class="button" onClick="this.form.action.value=\'createOpportunity\';" value="{$MOD.LBL_CREATE_OPPORTUNITY}">',
                        'sugar_html' => [
                            'type' => 'submit',
                            'value' => '{$MOD.LBL_CREATE_OPPORTUNITY}',
                            'htmlOptions' => [
                                'class' => 'button',
                                'id' => 'create_contract_button',
                                'title' => '{$MOD.LBL_CREATE_OPPORTUNITY}',
                                'onclick' => "this.form.action.value='createOpportunity';",
                                'name' => 'Create Opportunity',
                            ],
                        ],
                    ],
                    7 => [
                        'customCode' => '<input type="submit" class="button" onClick="this.form.action.value=\'createContract\';" value="{$MOD.LBL_CREATE_CONTRACT}">',
                        'sugar_html' => [
                            'type' => 'submit',
                            'value' => '{$MOD.LBL_CREATE_CONTRACT}',
                            'htmlOptions' => [
                                'class' => 'button',
                                'id' => 'create_contract_button',
                                'title' => '{$MOD.LBL_CREATE_CONTRACT}',
                                'onclick' => "this.form.action.value='createContract';",
                                'name' => 'Create Contract',
                            ],
                        ],
                    ],
                    8 => [
                        'customCode' => '<input type="submit" class="button" onClick="this.form.action.value=\'converToInvoice\';" value="{$MOD.LBL_CONVERT_TO_INVOICE}">',
                        'sugar_html' => [
                            'type' => 'submit',
                            'value' => '{$MOD.LBL_CONVERT_TO_INVOICE}',
                            'htmlOptions' => [
                                'class' => 'button',
                                'id' => 'convert_to_invoice_button',
                                'title' => '{$MOD.LBL_CONVERT_TO_INVOICE}',
                                'onclick' => "this.form.action.value='converToInvoice';",
                                'name' => 'Convert to Invoice',
                            ],
                        ],
                    ],
                    9 => [
                        'customCode' => '<input type="button" class="button" id="convert_to_radio_campaign_button" onClick="window.location.href=\'index.php?entryPoint=convert_quote_to_radio_campaign&record={$fields.id.value}\';" value="Convert to Radio Campaign">',
                        'sugar_html' => [
                            'type' => 'button',
                            'value' => 'Convert to Radio Campaign',
                            'htmlOptions' => [
                                'class' => 'button',
                                'id' => 'convert_to_radio_campaign_button',
                                'title' => 'Convert to Radio Campaign',
                                'onclick' => 'window.location.href=\'index.php?entryPoint=convert_quote_to_radio_campaign&record={$fields.id.value}\';',
                                'name' => 'Convert to Radio Campaign',
                            ],
                        ],
                    ],
                ],
            ],
            'maxColumns' => '2',
            'widths' => [
                0 => ['label' => '10', 'field' => '30'],
                1 => ['label' => '10', 'field' => '30'],
            ],
            'useTabs' => true,
            'tabDefs' => [
                'LBL_PANEL_OVERVIEW' => [
                    'newTab' => true,
                    'panelDefault' => 'expanded',
                ],
                'LBL_QUOTE_TO' => [
                    'newTab' => true,
                    'panelDefault' => 'expanded',
                ],
                'LBL_LINE_ITEMS' => [
                    'newTab' => true,
                    'panelDefault' => 'expanded',
                ],
                'LBL_PANEL_ASSIGNMENT' => [
                    'newTab' => true,
                    'panelDefault' => 'expanded',
                ],
            ],
        ],
        'recordActions' => [
            'actions' => [
                'print-as-pdf' => [
                    'key' => 'print-as-pdf',
                    'labelKey' => 'LBL_PRINT_AS_PDF',
                    'asyncProcess' => true,
                    'modes' => ['detail'],
                    'acl' => ['view'],
                    'aclModule' => 'AOS_PDF_Templates',
                    'params' => [
                        'selectModal' => [
                            'module' => 'AOS_PDF_Templates',
                        ],
                    ],
                ],
            ],
        ],
        'panels' => [
            'LBL_PANEL_OVERVIEW' => [
                0 => [
                    0 => ['name' => 'name', 'label' => 'LBL_NAME'],
                    1 => ['name' => 'opportunity', 'label' => 'LBL_OPPORTUNITY'],
                ],
                1 => [
                    0 => ['name' => 'number', 'label' => 'LBL_QUOTE_NUMBER'],
                    1 => ['name' => 'stage', 'label' => 'LBL_STAGE'],
                ],
                2 => [
                    0 => ['name' => 'expiration', 'label' => 'LBL_EXPIRATION'],
                    1 => ['name' => 'invoice_status', 'label' => 'LBL_INVOICE_STATUS'],
                ],
                3 => [
                    0 => ['name' => 'assigned_user_name', 'label' => 'LBL_ASSIGNED_TO'],
                    1 => ['name' => 'term', 'label' => 'LBL_TERM'],
                ],
                4 => [
                    0 => ['name' => 'approval_status', 'label' => 'LBL_APPROVAL_STATUS'],
                    1 => ['name' => 'approval_issue', 'label' => 'LBL_APPROVAL_ISSUE'],
                ],
                5 => [
                    0 => ['name' => 'campaign_start_date_c', 'label' => 'LBL_CAMPAIGN_START_DATE'],
                    1 => ['name' => 'campaign_end_date_c', 'label' => 'LBL_CAMPAIGN_END_DATE'],
                ],
                6 => [
                    0 => [
                        'name' => 'campaign_start_date_c',
                        'label' => 'LBL_WEEKLY_ADVERT_SCHEDULE',
                        'customCode' => '<div id="pukfm_weekly_total_bar">Total Spots: <strong id="radio_total_spots_detail">{$PUKFM_TOTAL_SPOTS|default:0}</strong> &nbsp;|&nbsp; Weekly spots: <strong id="pukfm_weekly_total">{$PUKFM_WEEKLY_SPOTS|default:0}</strong></div>',
                    ],
                    1 => '',
                ],
            ],
            'LBL_QUOTE_TO' => [
                0 => [
                    0 => ['name' => 'billing_account', 'label' => 'LBL_BILLING_ACCOUNT'],
                    1 => ['name' => 'billing_contact', 'label' => 'LBL_BILLING_CONTACT'],
                ],
                1 => [
                    0 => [
                        'name' => 'billing_address_street',
                        'label' => 'LBL_BILLING_ADDRESS',
                        'type' => 'address',
                        'displayParams' => ['key' => 'billing'],
                    ],
                    1 => [
                        'name' => 'shipping_address_street',
                        'label' => 'LBL_SHIPPING_ADDRESS',
                        'type' => 'address',
                        'displayParams' => ['key' => 'shipping'],
                    ],
                ],
            ],
            'lbl_line_items' => [
                0 => [
                    0 => ['name' => 'currency_id', 'studio' => 'visible', 'label' => 'LBL_CURRENCY'],
                    1 => '',
                ],
                1 => [
                    0 => ['name' => 'line_items', 'label' => 'LBL_LINE_ITEMS'],
                ],
                2 => [
                    0 => ['name' => 'total_amt', 'label' => 'LBL_TOTAL_AMT'],
                ],
                3 => [
                    0 => ['name' => 'discount_amount', 'label' => 'LBL_DISCOUNT_AMOUNT'],
                ],
                4 => [
                    0 => ['name' => 'subtotal_amount', 'label' => 'LBL_SUBTOTAL_AMOUNT'],
                ],
                5 => [
                    0 => ['name' => 'shipping_amount', 'label' => 'LBL_SHIPPING_AMOUNT'],
                ],
                6 => [
                    0 => ['name' => 'shipping_tax_amt', 'label' => 'LBL_SHIPPING_TAX_AMT'],
                ],
                7 => [
                    0 => ['name' => 'tax_amount', 'label' => 'LBL_TAX_AMOUNT'],
                ],
                8 => [
                    0 => ['name' => 'total_amount', 'label' => 'LBL_GRAND_TOTAL'],
                ],
            ],
            'LBL_PANEL_ASSIGNMENT' => [
                0 => [
                    0 => [
                        'name' => 'date_entered',
                        'customCode' => '{$fields.date_entered.value} {$APP.LBL_BY} {$fields.created_by_name.value}',
                    ],
                    1 => [
                        'name' => 'date_modified',
                        'label' => 'LBL_DATE_MODIFIED',
                        'customCode' => '{$fields.date_modified.value} {$APP.LBL_BY} {$fields.modified_by_name.value}',
                    ],
                ],
            ],
        ],
    ],
];

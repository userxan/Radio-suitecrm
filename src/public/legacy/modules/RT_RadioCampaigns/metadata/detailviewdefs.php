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
                [
                    'customCode' => '<input type="button" class="button" id="export_to_myriad_btn" value="{$MOD.LBL_EXPORT_TO_MYRIAD}" onclick="window.location.href=\'index.php?entryPoint=export_radio_campaign_to_myriad&record={$fields.id.value}\';" />',
                ],
                [
                    'customCode' => '<input type="button" class="button" id="reconcile_logs_btn" value="{$MOD.LBL_RECONCILE_LOGS}" onclick="window.location.href=\'index.php?entryPoint=radio_campaign_reconcile_logs&record={$fields.id.value}\';" />',
                ],
                [
                    'customCode' => '<input type="button" class="button" id="schedule_makegood_btn" value="{$MOD.LBL_MAKE_GOOD}" onclick="window.location.href=\'index.php?entryPoint=radio_campaign_reconcile_logs&record={$fields.id.value}&makegood=1\';" />',
                ],
                [
                    'customCode' => '<input type="button" class="button" id="presenter_liveread_btn" value="{$MOD.LBL_LIVEREAD_SHEET}" onclick="window.open(\'index.php?entryPoint=generate_presenter_liveread_sheet&record={$fields.id.value}\', \'_blank\');" />',
                ],
                [
                    'customCode' => '<input type="button" class="button" id="broadcast_affidavit_btn" value="{$MOD.LBL_BROADCAST_AFFIDAVIT}" onclick="window.open(\'index.php?entryPoint=generate_broadcast_affidavit&record={$fields.id.value}\', \'_blank\');" />',
                ],
                [
                    'customCode' => '<input type="button" class="button" id="convert_to_invoice_btn" value="{$MOD.LBL_CONVERT_TO_INVOICE}" onclick="window.location.href=\'index.php?entryPoint=convert_campaign_to_invoice&record={$fields.id.value}\';" />',
                ],
                [
                    'customCode' => '{if !empty($fields.client_approval_token.value)}<input type="button" class="button" id="client_review_btn" value="Client Review Link" onclick="window.open(\'index.php?entryPoint=radio_campaign_client_review&token={$fields.client_approval_token.value}\', \'_blank\');" />{/if}',
                ],
                [
                    'customCode' => '<input type="button" class="button" id="email_client_review_btn" value="{$MOD.LBL_EMAIL_CLIENT_REVIEW}" onclick="window.location.href=\'index.php?entryPoint=send_radio_client_email&record={$fields.id.value}&type=approval\';" />',
                ],
                [
                    'customCode' => '<input type="button" class="button" id="email_affidavit_btn" value="{$MOD.LBL_EMAIL_AFFIDAVIT}" onclick="window.location.href=\'index.php?entryPoint=send_radio_client_email&record={$fields.id.value}&type=affidavit\';" />',
                ],
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
                'industry_category',
                'quote_reference',
            ],
            [
                'file',
                'linked_quote_id',
            ],
            [
                'reconciliation_status',
                'reconciled_spots',
            ],
            [
                'missed_spots',
                'production_status',
            ],
            [
                'voice_talent',
                'client_approved_at',
            ],
            [
                'script_text',
                'client_feedback',
            ],
            [
                'description',
                'client_approval_token',
            ],
        ],
    ],
];

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
                    'customCode' => '<input type="button" class="button" id="convert_to_invoice_btn" value="{if !empty($MOD.LBL_CONVERT_TO_INVOICE)}{$MOD.LBL_CONVERT_TO_INVOICE}{else}Convert to Invoice{/if}" onclick="window.location.href=\'index.php?entryPoint=convert_campaign_to_invoice&record={$fields.id.value}\';" style="background-color:#1a7f37;color:#ffffff;font-weight:600;" />',
                ],
                [
                    'customCode' => '<input type="button" class="button" id="export_to_myriad_btn" value="{if !empty($MOD.LBL_EXPORT_TO_MYRIAD)}{$MOD.LBL_EXPORT_TO_MYRIAD}{else}Export to Myriad{/if}" onclick="window.location.href=\'index.php?entryPoint=export_radio_campaign_to_myriad&record={$fields.id.value}\';" />',
                ],
                [
                    'customCode' => '<input type="button" class="button" id="reconcile_logs_btn" value="{if !empty($MOD.LBL_RECONCILE_LOGS)}{$MOD.LBL_RECONCILE_LOGS}{else}Reconcile Playout Logs{/if}" onclick="window.location.href=\'index.php?entryPoint=radio_campaign_reconcile_logs&record={$fields.id.value}\';" />',
                ],
                [
                    'customCode' => '<input type="button" class="button" id="schedule_makegood_btn" value="{if !empty($MOD.LBL_MAKE_GOOD)}{$MOD.LBL_MAKE_GOOD}{else}Schedule Make-Good Spots{/if}" onclick="window.location.href=\'index.php?entryPoint=radio_campaign_reconcile_logs&record={$fields.id.value}&makegood=1\';" />',
                ],
                [
                    'customCode' => '<input type="button" class="button" id="presenter_liveread_btn" value="{if !empty($MOD.LBL_LIVEREAD_SHEET)}{$MOD.LBL_LIVEREAD_SHEET}{else}Studio Live-Read Sheet{/if}" onclick="window.open(\'index.php?entryPoint=generate_presenter_liveread_sheet&record={$fields.id.value}\', \'_blank\');" />',
                ],
                [
                    'customCode' => '<input type="button" class="button" id="broadcast_affidavit_btn" value="{if !empty($MOD.LBL_BROADCAST_AFFIDAVIT)}{$MOD.LBL_BROADCAST_AFFIDAVIT}{else}Broadcast Affidavit{/if}" onclick="window.open(\'index.php?entryPoint=generate_broadcast_affidavit&record={$fields.id.value}\', \'_blank\');" />',
                ],
                [
                    'customCode' => '{if !empty($fields.client_approval_token.value)}<input type="button" class="button" id="client_review_btn" value="Client Review Link" onclick="window.open(\'index.php?entryPoint=radio_campaign_client_review&token={$fields.client_approval_token.value}\', \'_blank\');" />{/if}',
                ],
                [
                    'customCode' => '<input type="button" class="button" id="email_client_review_btn" value="{if !empty($MOD.LBL_EMAIL_CLIENT_REVIEW)}{$MOD.LBL_EMAIL_CLIENT_REVIEW}{else}Email Client Approval Link{/if}" onclick="window.location.href=\'index.php?entryPoint=send_radio_client_email&record={$fields.id.value}&type=approval\';" />',
                ],
                [
                    'customCode' => '<input type="button" class="button" id="email_affidavit_btn" value="{if !empty($MOD.LBL_EMAIL_AFFIDAVIT)}{$MOD.LBL_EMAIL_AFFIDAVIT}{else}Email Broadcast Affidavit{/if}" onclick="window.location.href=\'index.php?entryPoint=send_radio_client_email&record={$fields.id.value}&type=affidavit\';" />',
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
            'radio-campaign-convert-to-invoice' => [
                'key' => 'radio-campaign-convert-to-invoice',
                'labelKey' => 'LBL_CONVERT_TO_INVOICE',
                'priority' => 490,
                'asyncProcess' => true,
                'modes' => ['detail'],
                'acl' => ['view'],
                'params' => [
                    'displayConfirmation' => false,
                    'disableOnRun' => true,
                ],
            ],
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
                [
                    'name' => 'file',
                    'label' => 'LBL_AUDIO_FILE',
                    'customCode' => '<div id="detail_audio_field_box" style="padding:4px 0;">'
                        . '{if !empty($fields.file.value) || !empty($fields.filename.value)}'
                        . '<div style="margin-bottom:6px;">'
                        . '<strong>🎵 <a href="index.php?entryPoint=upload_campaign_audio&record={$fields.id.value}&download=1" class="tabDetailViewDFLink">{if !empty($fields.file.value)}{$fields.file.value}{else}{$fields.filename.value}{/if}</a></strong> '
                        . '<a href="index.php?entryPoint=upload_campaign_audio&record={$fields.id.value}&download=1" style="margin-left:8px;font-size:12px;color:#0969da;text-decoration:underline;">[Download]</a> '
                        . '<a href="index.php?entryPoint=upload_campaign_audio&record={$fields.id.value}&remove_audio=1" onclick="return confirm(\'Remove attached audio file?\');" style="margin-left:6px;font-size:12px;color:#cf222e;text-decoration:underline;">[Remove]</a>'
                        . '</div>'
                        . '<audio controls preload="metadata" style="height:34px;max-width:340px;vertical-align:middle;margin-bottom:6px;display:block;">'
                        . '<source src="index.php?entryPoint=upload_campaign_audio&record={$fields.id.value}&stream=1" />'
                        . '</audio>'
                        . '{else}'
                        . '<div style="color:#57606a;font-size:12px;margin-bottom:6px;">No MP3 or WAV audio file uploaded yet.</div>'
                        . '{/if}'
                        . '<div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">'
                        . '<input type="file" id="inline_campaign_audio_input" accept=".mp3,.wav,audio/mpeg,audio/wav,audio/x-wav" style="font-size:12px;" />'
                        . '<button type="button" class="button" id="inline_campaign_audio_btn" onclick="window.uploadCampaignAudioInline(\'{$fields.id.value}\');" style="padding:3px 10px;font-size:12px;background:#5E2B83;color:#fff;border:none;border-radius:4px;cursor:pointer;">{if !empty($fields.file.value) || !empty($fields.filename.value)}Replace MP3 / WAV{else}Upload MP3 / WAV{/if}</button>'
                        . '<span id="inline_campaign_audio_status" style="font-size:12px;font-weight:600;"></span>'
                        . '</div>'
                        . '</div>',
                ],
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

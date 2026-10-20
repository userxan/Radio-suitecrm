<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * SuiteCRM 8 RecordView Action & Module Routing configuration for RT_RadioCampaigns.
 * Safely merges into module.recordview.actions and legacy.module_routing without overwriting default actions.
 */
$exportActionDef = [
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
];

$convertInvoiceActionDef = [
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
];

if (isset($container) && $container instanceof ContainerBuilder) {
    $recordViewActions = $container->hasParameter('module.recordview.actions')
        ? (array) $container->getParameter('module.recordview.actions')
        : [];
    $recordViewActions['modules']['rt-radiocampaigns']['actions']['radio-campaign-convert-to-invoice'] = $convertInvoiceActionDef;
    $recordViewActions['modules']['RT_RadioCampaigns']['actions']['radio-campaign-convert-to-invoice'] = $convertInvoiceActionDef;
    $recordViewActions['modules']['rt-radiocampaigns']['actions']['radio-campaign-export-to-myriad'] = $exportActionDef;
    $recordViewActions['modules']['RT_RadioCampaigns']['actions']['radio-campaign-export-to-myriad'] = $exportActionDef;
    $container->setParameter('module.recordview.actions', $recordViewActions);

    // Route RT_RadioCampaigns record view to the full-featured Legacy DetailView/EditView inside the SuiteCRM 8 shell
    // (matching quotes and invoices) so all campaign action buttons and the MP3/WAV Audio Studio are always visible.
    if ($container->hasParameter('legacy.module_routing')) {
        $moduleRouting = (array) $container->getParameter('legacy.module_routing');
        $moduleRouting['rt-radiocampaigns'] = [
            'index' => true,
            'list' => true,
            'record' => false,
        ];
        $container->setParameter('legacy.module_routing', $moduleRouting);
    }
}

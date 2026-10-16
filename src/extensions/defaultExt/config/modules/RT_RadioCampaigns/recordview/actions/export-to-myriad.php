<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * SuiteCRM 8 RecordView Action configuration for RT_RadioCampaigns: Export to Myriad (Section 13.2 & 13.3).
 * Safely merges into module.recordview.actions without overwriting default actions.
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

if (isset($container) && $container instanceof ContainerBuilder) {
    $recordViewActions = $container->hasParameter('module.recordview.actions')
        ? (array) $container->getParameter('module.recordview.actions')
        : [];
    $recordViewActions['modules']['rt-radiocampaigns']['actions']['radio-campaign-export-to-myriad'] = $exportActionDef;
    $recordViewActions['modules']['RT_RadioCampaigns']['actions']['radio-campaign-export-to-myriad'] = $exportActionDef;
    $container->setParameter('module.recordview.actions', $recordViewActions);
}

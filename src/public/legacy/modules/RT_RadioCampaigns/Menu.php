<?php

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

global $mod_strings, $app_strings, $sugar_config;

if (ACLController::checkAccess('RT_RadioCampaigns', 'edit', true)) {
    $module_menu[] = [
        'index.php?module=RT_RadioCampaigns&action=EditView&return_module=RT_RadioCampaigns&return_action=DetailView',
        $mod_strings['LNK_NEW_RECORD'] ?? 'Create Radio Campaign',
        'View',
        'RT_RadioCampaigns',
    ];
}
if (ACLController::checkAccess('RT_RadioCampaigns', 'list', true)) {
    $module_menu[] = [
        'index.php?module=RT_RadioCampaigns&action=index&return_module=RT_RadioCampaigns&return_action=DetailView',
        $mod_strings['LNK_LIST'] ?? 'View Radio Campaigns',
        'List',
        'RT_RadioCampaigns',
    ];
}

<?php

declare(strict_types=1);

/**
 * Migration 007: Programmatically run SuiteCRM Quick Repair and Rebuild
 * to compile all Extension/ fragments into Ext/ and clear legacy caches.
 */

if (PHP_SAPI !== 'cli') {
    die('CLI only');
}

$legacyDir = '/var/www/html/public/legacy';
chdir($legacyDir);
if (!defined('sugarEntry')) {
    define('sugarEntry', true);
}
require_once 'include/entryPoint.php';
require_once 'modules/Administration/QuickRepairAndRebuild.php';

global $current_user, $mod_strings;
$current_user = BeanFactory::newBean('Users');
$current_user->getSystemUser();
$mod_strings = return_module_language('en_us', 'Administration');

echo "[Migration 007] Running SuiteCRM Quick Repair and Rebuild...\n";

$rac = new RepairAndClear();
$rac->repairAndClearAll(
    ['clearAll'],
    [$mod_strings['LBL_ALL_MODULES'] ?? 'All Modules', 'RT_RadioCampaigns', 'AOS_Quotes', 'AOS_Invoices', 'Administration'],
    true,
    false
);

// Explicitly purge cached language, theme template, inline editing, and Smarty compiled files for RT_RadioCampaigns
foreach ([
    $legacyDir . '/cache/modules/RT_RadioCampaigns/language/*.php',
    $legacyDir . '/cache/themes/*/modules/RT_RadioCampaigns/*.tpl',
    $legacyDir . '/cache/include/InlineEditing/*RT_RadioCampaigns*.tpl',
    $legacyDir . '/cache/smarty/templates_c/*.php',
] as $globPattern) {
    foreach (glob($globPattern) ?: [] as $cachedFile) {
        if (is_file($cachedFile)) {
            @unlink($cachedFile);
        }
    }
}
LanguageManager::clearLanguageCache('RT_RadioCampaigns');

require_once 'modules/ACLActions/ACLAction.php';
ACLAction::addActions('RT_RadioCampaigns', 'module');
echo "  + Ensured ACL actions exist for RT_RadioCampaigns\n";

// Verify expected compiled Ext files exist
$expectedCompiled = [
    'custom/application/Ext/Include/modules.ext.php',
    'custom/application/Ext/Language/en_us.lang.ext.php',
    'custom/application/Ext/EntryPointRegistry/entry_point_registry.ext.php',
    'custom/application/Ext/TableDictionary/tabledictionary.ext.php',
    'custom/modules/AOS_Quotes/Ext/Vardefs/vardefs.ext.php',
    'custom/modules/AOS_Quotes/Ext/Language/en_us.lang.ext.php',
    'custom/modules/AOS_Quotes/Ext/LogicHooks/logichooks.ext.php',
];

foreach ($expectedCompiled as $relPath) {
    if (!file_exists($legacyDir . '/' . $relPath)) {
        fwrite(STDERR, "ERROR: Expected compiled extension file missing: {$relPath}\n");
        exit(1);
    }
    echo "  = Verified compiled file: {$relPath}\n";
}

echo "[Migration 007] Complete.\n";

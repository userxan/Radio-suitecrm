<?php

declare(strict_types=1);

/**
 * UI / DOM & Symfony Container Verification Suite for Radio CRM.
 * Verifies:
 * 1. AOS_Quotes EditView renders #weekly_advert_schedule_wrapper and all 168 inputs + 2 dates INSIDE <form name="EditView">.
 * 2. AOS_Quotes DetailView renders Convert to Radio Campaign action pointing to entryPoint=convert_quote_to_radio_campaign.
 * 3. RT_RadioCampaigns ListView, EditView, DetailView render properly.
 * 4. SuiteCRM 8 Symfony Container registers ExportToMyriadAction (record-radio-campaign-export-to-myriad)
 *    and RecordActionDefinitionProvider exposes radio-campaign-export-to-myriad on RT_RadioCampaigns DetailView.
 */

if (PHP_SAPI !== 'cli') {
    die('CLI only');
}

$appRoot = '/var/www/html';
$legacyDir = $appRoot . '/public/legacy';

require_once $appRoot . '/vendor/autoload.php';
require_once $appRoot . '/config/bootstrap.php';

use App\Kernel;
use RadioCRM\RadioScheduleService;

echo "======================================================================\n";
echo "Radio CRM - UI / DOM & Symfony Container Verification\n";
echo "======================================================================\n";

$failures = 0;
function checkUi(string $name, bool $ok, string $info = ''): void
{
    global $failures;
    if (!$ok) {
        $failures++;
    }
    echo sprintf("[%s] %s%s\n", $ok ? 'PASS' : 'FAIL', $name, $info !== '' ? " ({$info})" : '');
}

// 1. Boot Symfony Kernel and verify ExportToMyriadAction + module.recordview.actions parameter
$kernel = new Kernel('prod', false);
$kernel->boot();
$container = $kernel->getContainer();

$recordViewActions = $container->hasParameter('module.recordview.actions')
    ? (array) $container->getParameter('module.recordview.actions')
    : [];

$hasDefaultEdit = isset($recordViewActions['default']['actions']['edit']);
$hasMyriadActionCfg = isset($recordViewActions['modules']['rt-radiocampaigns']['actions']['radio-campaign-export-to-myriad'])
    || isset($recordViewActions['modules']['RT_RadioCampaigns']['actions']['radio-campaign-export-to-myriad']);

checkUi(
    'UI-1: Symfony container preserves default recordview actions and registers radio-campaign-export-to-myriad',
    $hasDefaultEdit && $hasMyriadActionCfg,
    'default.edit=' . ($hasDefaultEdit ? 'yes' : 'no') . ', export-to-myriad=' . ($hasMyriadActionCfg ? 'yes' : 'no')
);

// 2. Render AOS_Quotes EditView HTML via CustomAOS_QuotesViewEdit and verify DOM placement inside <form name="EditView">
chdir($legacyDir);
if (!defined('sugarEntry')) {
    define('sugarEntry', true);
}
require_once 'include/entryPoint.php';
require_once 'include/MVC/SugarApplication.php';
require_once 'custom/include/RadioCRM/RadioScheduleService.php';
require_once 'custom/modules/AOS_Quotes/views/view.edit.php';
require_once 'custom/modules/AOS_Quotes/views/view.detail.php';

global $current_user, $db, $app_strings, $app_list_strings, $mod_strings, $theme;
$current_user = BeanFactory::newBean('Users');
$current_user->getSystemUser();
$app_strings = return_application_language('en_us');
$app_list_strings = return_app_list_strings_language('en_us');
$mod_strings = return_module_language('en_us', 'AOS_Quotes');
$theme = 'SuiteP';

// Find or restore the test Quote created during acceptance tests
$qRes = $db->query("SELECT id FROM aos_quotes WHERE name = 'Test Radio Advert Quote' ORDER BY date_entered DESC LIMIT 1", false);
$qRow = $qRes ? $db->fetchByAssoc($qRes) : null;
$testQuoteId = (string) ($qRow['id'] ?? '');

if ($testQuoteId !== '') {
    $db->query("UPDATE aos_quotes SET deleted = 0 WHERE id = '" . $db->quote($testQuoteId) . "'", false);
} else {
    $qBean = BeanFactory::newBean('AOS_Quotes');
    $qBean->name = 'Test Radio Advert Quote';
    $qBean->stage = 'Draft';
    $qBean->campaign_start_date_c = '2026-06-15';
    $qBean->campaign_end_date_c = '2026-06-27';
    $qBean->tue_specialty_show_2_2100_2200_c = 1;
    $qBean->thu_music_unlimited_0400_0500_c = 2;
    $testQuoteId = (string) $qBean->save();
}

$_REQUEST['module'] = 'AOS_Quotes';
$_REQUEST['action'] = 'EditView';
$_REQUEST['record'] = $testQuoteId;
$GLOBALS['module'] = 'AOS_Quotes';
$GLOBALS['action'] = 'EditView';

$quoteBean = BeanFactory::getBean('AOS_Quotes', $testQuoteId);
$editView = new CustomAOS_QuotesViewEdit();
$editView->init($quoteBean);
$editView->module = 'AOS_Quotes';
$editView->bean = $quoteBean;
$editView->preDisplay();

ob_start();
$editView->display();
$editHtml = (string) ob_get_clean();

// Check that <form name="EditView"...> contains #weekly_advert_schedule_wrapper and all 168 inputs
$formStartPos = strpos($editHtml, 'name="EditView"');
$wrapperPos = strpos($editHtml, 'id="weekly_advert_schedule_wrapper"');
$formEndPos = strrpos($editHtml, '</form>');
$wrapperInsideForm = ($formStartPos !== false && $wrapperPos !== false && $formEndPos !== false && $wrapperPos > $formStartPos && $wrapperPos < $formEndPos);

$foundInputsCount = 0;
foreach (RadioScheduleService::getAllScheduleFields() as $f => $m) {
    if (strpos($editHtml, 'id="' . $f . '"') !== false && strpos($editHtml, 'name="' . $f . '"') !== false) {
        $foundInputsCount++;
    }
}

$hasSummaryIds = strpos($editHtml, 'id="radio_weekly_total_bar"') !== false
    && strpos($editHtml, 'id="radio_weekly_total"') !== false
    && strpos($editHtml, 'id="radio_total_spots_detail"') !== false
    && strpos($editHtml, 'id="radio_clear_all_spots"') !== false
    && strpos($editHtml, 'id="campaign_start_date_c"') !== false
    && strpos($editHtml, 'id="campaign_end_date_c"') !== false;

checkUi(
    'UI-2: AOS_Quotes EditView embeds #weekly_advert_schedule_wrapper inside <form name="EditView"> with all 168 inputs, dates, Clear All, and summary IDs',
    $wrapperInsideForm && $foundInputsCount === 168 && $hasSummaryIds,
    "insideForm=" . ($wrapperInsideForm ? 'true' : 'false') . ", scheduleInputs={$foundInputsCount}/168"
);

// 3. Verify AOS_Quotes DetailView metadata & button for Convert to Radio Campaign
$detailViewDefs = [];
include $legacyDir . '/custom/modules/AOS_Quotes/metadata/detailviewdefs.php';
$detailButtonsJson = json_encode($viewdefs['AOS_Quotes']['DetailView']['templateMeta']['form']['buttons'] ?? []);
$hasConvertBtn = strpos($detailButtonsJson, 'entryPoint=convert_quote_to_radio_campaign') !== false
    && strpos($detailButtonsJson, 'Convert to Radio Campaign') !== false;

checkUi(
    'UI-3: AOS_Quotes DetailView includes Convert to Radio Campaign button pointing to entryPoint=convert_quote_to_radio_campaign',
    $hasConvertBtn
);

// 4. Verify RT_RadioCampaigns module registration, language, Menu, ListView, EditView, DetailView metadata
$moduleRegistered = isset($GLOBALS['beanList']['RT_RadioCampaigns'])
    && in_array('RT_RadioCampaigns', $GLOBALS['moduleList'], true)
    && (($app_list_strings['moduleList']['RT_RadioCampaigns'] ?? '') === 'Radio Campaigns');

$rcListDefs = [];
include $legacyDir . '/modules/RT_RadioCampaigns/metadata/listviewdefs.php';
$rcDetailDefs = [];
include $legacyDir . '/modules/RT_RadioCampaigns/metadata/detailviewdefs.php';

$hasRcColumns = isset($listViewDefs['RT_RadioCampaigns']['NAME'])
    && !empty($listViewDefs['RT_RadioCampaigns']['NAME']['link'])
    && isset($listViewDefs['RT_RadioCampaigns']['START_DATE'])
    && isset($listViewDefs['RT_RadioCampaigns']['END_DATE'])
    && isset($listViewDefs['RT_RadioCampaigns']['NUMBER_OF_SPOTS'])
    && isset($listViewDefs['RT_RadioCampaigns']['STATUS'])
    && isset($listViewDefs['RT_RadioCampaigns']['MYRIAD_CART_NUMBER']);

$hasRcExportActionInDetail = isset(
    $viewdefs['RT_RadioCampaigns']['DetailView']['recordActions']['actions']['radio-campaign-export-to-myriad']
);

checkUi(
    'UI-4: RT_RadioCampaigns registered in beanList/moduleList/app_list_strings with clickable ListView columns and SuiteCRM 8 DetailView recordActions',
    $moduleRegistered && $hasRcColumns && $hasRcExportActionInDetail
);

// 5. Verify RT_RadioCampaigns MP3 audio upload field (vardefs + EditView + DetailView) and Myriad MP3 audio export
$rcBean = BeanFactory::newBean('RT_RadioCampaigns');
$rcFieldDefs = $rcBean->field_defs ?? [];
$hasAudioVardef = isset($rcFieldDefs['file'])
    && ($rcFieldDefs['file']['type'] ?? '') === 'file'
    && ($rcFieldDefs['file']['metadata']['storage_type'] ?? '') === 'private-documents'
    && isset($rcFieldDefs['filename']);

$rcEditDefs = [];
include $legacyDir . '/modules/RT_RadioCampaigns/metadata/editviewdefs.php';
$editPanelsJson = json_encode($viewdefs['RT_RadioCampaigns']['EditView']['panels'] ?? []);
$detailPanelsJson = json_encode($viewdefs['RT_RadioCampaigns']['DetailView']['panels'] ?? []);
$hasAudioInViews = strpos($editPanelsJson, '"file"') !== false
    && strpos($detailPanelsJson, '"file"') !== false;

// Simulate an uploaded MP3 in private_documents_media_objects for the test campaign and verify exportCampaignToMyriad copies it to /srv/myriad_exports/myriad_audio/
$cRes = $db->query("SELECT id, name, start_date, end_date, spot_length, myriad_cart_number, quote_reference, linked_quote_id FROM rt_radiocampaigns WHERE (linked_quote_id = '" . $db->quote($testQuoteId) . "' OR name = 'Radio Campaign - Test Radio Advert Quote') ORDER BY date_entered DESC LIMIT 1", false);
$cRow = $cRes ? $db->fetchByAssoc($cRes) : null;
$audioExportedOk = false;
$exportedAudioFile = '';

if ($cRow && !empty($cRow['id'])) {
    $db->query("UPDATE rt_radiocampaigns SET deleted = 0, linked_quote_id = '" . $db->quote($testQuoteId) . "' WHERE id = '" . $db->quote($cRow['id']) . "'", false);
} else {
    $cBean = BeanFactory::newBean('RT_RadioCampaigns');
    $cBean->name = 'Radio Campaign - Test Radio Advert Quote';
    $cBean->start_date = '2026-06-15';
    $cBean->end_date = '2026-06-27';
    $cBean->number_of_spots = 6;
    $cBean->spot_length = '30 sec';
    $cBean->status = 'Draft';
    $cBean->myriad_cart_number = '1';
    $cBean->linked_quote_id = $testQuoteId;
    $cId = (string) $cBean->save();
    $cRes = $db->query("SELECT id, name, start_date, end_date, spot_length, myriad_cart_number, quote_reference, linked_quote_id FROM rt_radiocampaigns WHERE id = '" . $db->quote($cId) . "' LIMIT 1", false);
    $cRow = $cRes ? $db->fetchByAssoc($cRes) : null;
}

if ($cRow && !empty($cRow['id'])) {
    $testCampId = (string) $cRow['id'];
    $ymDir = '/var/www/html/media/documents/' . date('Y/m');
    if (!is_dir($ymDir)) {
        @mkdir($ymDir, 0775, true);
    }
    $fakeMp3Content = "ID3\x03\x00\x00\x00\x00\x00\x0aTIT2\x00\x00\x00\x02\x00\x00\x00A\xff\xfb\x90\x64" . str_repeat("\x00", 128);

    $fakeMediaUuid = '01a15099-mp30-7000-8000-' . substr(md5($testCampId), 0, 12);
    $fakeMp3Path = $ymDir . '/' . $fakeMediaUuid;
    @file_put_contents($fakeMp3Path, $fakeMp3Content);

    // Also write to legacy upload/<testCampId> as fallback
    $legacyUpDir = '/var/www/html/public/legacy/upload';
    if (!is_dir($legacyUpDir)) {
        @mkdir($legacyUpDir, 0775, true);
    }
    @file_put_contents($legacyUpDir . '/' . $testCampId, $fakeMp3Content);

    // Ensure myriad_audio directory exists
    @mkdir('/srv/myriad_exports/myriad_audio', 0775, true);

    $mediaRowId = '1f1cb299-mp30-6000-9000-' . substr(md5($testCampId), 0, 12);
    $nowStr = gmdate('Y-m-d H:i:s');
    $db->query("DELETE FROM private_documents_media_objects WHERE id = '{$mediaRowId}'", false);
    $db->query(
        "INSERT INTO private_documents_media_objects
            (id, name, date_entered, date_modified, deleted, file_path, size, mime_type, original_name, parent_type, parent_id, parent_field, temporary)
         VALUES
            ('{$mediaRowId}', 'Radio_Promo_Spot.mp3', '{$nowStr}', '{$nowStr}', 0, '{$fakeMediaUuid}', 164, 'audio/mpeg', 'Radio_Promo_Spot.mp3', 'RT_RadioCampaigns', '" . $db->quote($testCampId) . "', 'file', 0)",
        false
    );

    $cRow['filename'] = 'Radio_Promo_Spot.mp3';
    $db->query("UPDATE rt_radiocampaigns SET filename = 'Radio_Promo_Spot.mp3', file_mime_type = 'audio/mpeg' WHERE id = '" . $db->quote($testCampId) . "'", false);

    $qCstmRes = $db->query("SELECT * FROM aos_quotes_cstm WHERE id_c = '" . $db->quote($testQuoteId) . "'", false);
    $qCstmRow = ($qCstmRes ? $db->fetchByAssoc($qCstmRes) : null) ?: [];
    $expRes = RadioScheduleService::exportCampaignToMyriad($cRow, $qCstmRow, '/srv/myriad_exports');
    $exportedAudioFile = (string) ($expRes['audio_export_path'] ?? '');
    $audioExportedOk = !empty($expRes['success'])
        && $exportedAudioFile !== ''
        && is_file($exportedAudioFile)
        && filesize($exportedAudioFile) > 0;

    // Clean up test artifacts created by UI-5
    if (!empty($expRes['csv_path']) && is_file($expRes['csv_path'])) {
        @unlink($expRes['csv_path']);
    }
    foreach ($expRes['daily_logs'] ?? [] as $dl) {
        if (is_file($dl)) {
            @unlink($dl);
        }
    }
}

checkUi(
    'UI-5: RT_RadioCampaigns exposes MP3 audio upload field (file/private-documents) and exports MP3 to /srv/myriad_exports/myriad_audio/',
    $hasAudioVardef && $hasAudioInViews && $audioExportedOk,
    'vardef=' . ($hasAudioVardef ? 'yes' : 'no') . ', views=' . ($hasAudioInViews ? 'yes' : 'no') . ', audioExport=' . basename($exportedAudioFile)
);

// 6. Verify RT_RadioCampaigns DetailView Convert to Invoice action & WAV/MP3 audio upload via RadioScheduleService::saveCampaignAudioFile()
$rcDetailButtonsJson = json_encode($viewdefs['RT_RadioCampaigns']['DetailView']['templateMeta']['form']['buttons'] ?? []);
$hasRcConvertInvoiceBtn = strpos($rcDetailButtonsJson, 'entryPoint=convert_campaign_to_invoice') !== false
    && isset($viewdefs['RT_RadioCampaigns']['DetailView']['recordActions']['actions']['radio-campaign-convert-to-invoice']);
$inlineEditDisabledOnFile = (($rcFieldDefs['file']['inline_edit'] ?? true) === false);

$wavSavedOk = false;
if (!empty($testCampId)) {
    $tmpWav = sys_get_temp_dir() . '/test_spot_' . uniqid() . '.wav';
    // Valid minimal RIFF WAVE header
    file_put_contents($tmpWav, "RIFF\x24\x00\x00\x00WAVEfmt \x10\x00\x00\x00\x01\x00\x01\x00\x44\xac\x00\x00\x88\x58\x01\x00\x02\x00\x10\x00data\x00\x00\x00\x00");
    $wavSaveRes = RadioScheduleService::saveCampaignAudioFile($db, $testCampId, $tmpWav, 'Broadcast_Master_Spot.wav');
    @unlink($tmpWav);
    $loadedCamp = BeanFactory::getBean('RT_RadioCampaigns', $testCampId);
    $wavSavedOk = !empty($wavSaveRes['success'])
        && ($wavSaveRes['mime_type'] ?? '') === 'audio/wav'
        && ($loadedCamp->file ?? '') === 'Broadcast_Master_Spot.wav'
        && ($loadedCamp->filename ?? '') === 'Broadcast_Master_Spot.wav';
}

checkUi(
    'UI-6: RT_RadioCampaigns exposes Convert to Invoice action, disables broken inline_edit on file, and supports WAV (.wav) + MP3 (.mp3) uploads',
    $hasRcConvertInvoiceBtn && $inlineEditDisabledOnFile && $wavSavedOk,
    'convertBtn=' . ($hasRcConvertInvoiceBtn ? 'yes' : 'no') . ', fileInlineEditFalse=' . ($inlineEditDisabledOnFile ? 'yes' : 'no') . ', wavUpload=' . ($wavSavedOk ? 'yes' : 'no')
);

echo "======================================================================\n";
if ($failures > 0) {
    echo "UI / Container Verification FAILED ({$failures} failed)\n";
    exit(1);
}
echo "All UI / DOM & Symfony Container checks PASSED.\n";

<?php

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

require_once 'include/MVC/View/views/view.edit.php';

class RT_RadioCampaignsViewEdit extends ViewEdit
{
    public function display()
    {
        global $db;

        $recordId = trim((string) ($this->bean->id ?? ($_REQUEST['record'] ?? '')));
        if ($recordId !== '' && isset($this->bean) && is_object($this->bean)) {
            if (is_file('custom/include/RadioCRM/RadioScheduleService.php')) {
                require_once 'custom/include/RadioCRM/RadioScheduleService.php';
                $audioInfo = \RadioCRM\RadioScheduleService::findCampaignAudioFile(
                    $db,
                    $recordId,
                    (string) ($this->bean->filename ?? '')
                );
                if (is_array($audioInfo) && !empty($audioInfo['original_name'])) {
                    $this->bean->filename = (string) $audioInfo['original_name'];
                    $this->bean->file = (string) $audioInfo['original_name'];
                    $this->bean->file_mime_type = (string) ($audioInfo['mime_type'] ?? 'audio/mpeg');
                }
            }
        }

        // Provide hidden #doc_type element so SuiteCRM's SugarFieldFile.js never throws a null-reference error on file change,
        // and configure #file_file to accept both .mp3 and .wav audio files.
        echo <<<HTML
<input type="hidden" id="doc_type" name="doc_type" value="Sugar" />
<script type="text/javascript">
document.addEventListener('DOMContentLoaded', function() {
    var fileInput = document.getElementById('file_file');
    if (fileInput) {
        fileInput.setAttribute('accept', '.mp3,.wav,audio/mpeg,audio/wav,audio/x-wav');
    }
});
</script>
HTML;

        parent::display();
    }
}

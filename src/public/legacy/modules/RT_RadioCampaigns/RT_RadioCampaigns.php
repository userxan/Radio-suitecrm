<?php

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

require_once 'include/SugarObjects/templates/basic/Basic.php';

class RT_RadioCampaigns extends Basic
{
    public $new_schema = true;
    public $module_dir = 'RT_RadioCampaigns';
    public $object_name = 'RT_RadioCampaigns';
    public $table_name = 'rt_radiocampaigns';
    public $importable = true;
    public $disable_row_level_security = false;

    public $id;
    public $name;
    public $date_entered;
    public $date_modified;
    public $modified_user_id;
    public $modified_by_name;
    public $created_by;
    public $created_by_name;
    public $description;
    public $deleted;
    public $created_by_link;
    public $modified_user_link;
    public $assigned_user_id;
    public $assigned_user_name;
    public $assigned_user_link;

    public $start_date;
    public $end_date;
    public $number_of_spots;
    public $spot_length = '30 sec';
    public $status = 'Draft';
    public $myriad_cart_number;
    public $quote_reference;
    public $linked_quote_id;
    public $filename;
    public $file_mime_type;
    public $file_url;
    public $file;
    public $show_preview = false;

    public $industry_category = 'other';
    public $reconciled_spots = 0;
    public $missed_spots = 0;
    public $reconciliation_status = 'Pending';
    public $script_text;
    public $voice_talent;
    public $production_status = 'Draft';
    public $client_approval_token;
    public $client_approved_at;
    public $client_feedback;

    public function __construct()
    {
        parent::__construct();
        $this->show_preview = false;
    }

    public function retrieve($id = -1, $encode = true, $deleted = true)
    {
        $ret = parent::retrieve($id, $encode, $deleted);
        if ($ret) {
            $this->syncAudioFields();
        }
        return $ret;
    }

    public function fill_in_additional_detail_fields()
    {
        parent::fill_in_additional_detail_fields();
        $this->syncAudioFields();
    }

    public function fill_in_additional_list_fields()
    {
        parent::fill_in_additional_list_fields();
        $this->syncAudioFields();
    }

    protected function syncAudioFields(): void
    {
        $this->show_preview = false;
        if (!empty($this->id)) {
            if (is_file('custom/include/RadioCRM/RadioScheduleService.php')) {
                require_once 'custom/include/RadioCRM/RadioScheduleService.php';
                $audioInfo = \RadioCRM\RadioScheduleService::findCampaignAudioFile(
                    $this->db ?? ($GLOBALS['db'] ?? null),
                    (string) $this->id,
                    (string) ($this->filename ?? '')
                );
                if (is_array($audioInfo) && !empty($audioInfo['original_name'])) {
                    if (empty($this->filename)) {
                        $this->filename = (string) $audioInfo['original_name'];
                    }
                    if (empty($this->file_mime_type) && !empty($audioInfo['mime_type'])) {
                        $this->file_mime_type = (string) $audioInfo['mime_type'];
                    }
                    // Ensure legacy upload/<id> exists so legacy download.php works
                    $legacyPath = '/var/www/html/public/legacy/upload/' . $this->id;
                    if (!is_file($legacyPath) && !empty($audioInfo['source_path']) && is_file($audioInfo['source_path'])) {
                        @copy($audioInfo['source_path'], $legacyPath);
                        @chmod($legacyPath, 0664);
                    }
                }
            }
        }
        if (!empty($this->filename) && empty($this->file)) {
            $this->file = (string) $this->filename;
        }
    }

    public function save($check_notify = false)
    {
        if (empty($this->id)) {
            $this->id = create_guid();
            $this->new_with_id = true;
        }

        $removingFile = (isset($_REQUEST['remove_file_file']) && (string) $_REQUEST['remove_file_file'] === '1');
        if ($removingFile) {
            $this->file = '';
            $this->filename = '';
            $this->file_mime_type = '';
            if (is_file('custom/include/RadioCRM/RadioScheduleService.php')) {
                require_once 'custom/include/RadioCRM/RadioScheduleService.php';
                \RadioCRM\RadioScheduleService::removeCampaignAudioFile($this->db ?? ($GLOBALS['db'] ?? null), (string) $this->id);
            }
        } else {
            // Check if a file was uploaded via file_file or campaign_audio_file
            if (is_string($this->file) && $this->file !== '') {
                $this->filename = $this->file;
            } elseif (empty($this->filename) && !empty($this->fetched_row['filename'])) {
                $this->filename = (string) $this->fetched_row['filename'];
                $this->file = $this->filename;
            } elseif (!empty($this->filename) && empty($this->file)) {
                $this->file = (string) $this->filename;
            }

            if (!empty($this->filename) && is_file('custom/include/RadioCRM/RadioScheduleService.php')) {
                require_once 'custom/include/RadioCRM/RadioScheduleService.php';
                $this->file_mime_type = \RadioCRM\RadioScheduleService::detectAudioMimeType((string) $this->filename);
            }
        }

        $savedId = parent::save($check_notify);

        // If a file was uploaded into legacy upload/<id> during EditView save, sync it to private_documents_media_objects as well
        if (!$removingFile && !empty($this->id) && !empty($this->filename)) {
            $legacyPath = '/var/www/html/public/legacy/upload/' . $this->id;
            if (is_file($legacyPath) && isset($_FILES['file_file']) && ($_FILES['file_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                if (is_file('custom/include/RadioCRM/RadioScheduleService.php')) {
                    require_once 'custom/include/RadioCRM/RadioScheduleService.php';
                    \RadioCRM\RadioScheduleService::saveCampaignAudioFile(
                        $this->db ?? ($GLOBALS['db'] ?? null),
                        (string) $this->id,
                        $legacyPath,
                        (string) $this->filename
                    );
                }
            }
        }

        return $savedId;
    }

    public function deleteAttachment($isduplicate = 'false')
    {
        if ($this->ACLAccess('edit')) {
            if ($isduplicate === 'true') {
                return 'true';
            }
            if (!empty($this->id) && is_file('custom/include/RadioCRM/RadioScheduleService.php')) {
                require_once 'custom/include/RadioCRM/RadioScheduleService.php';
                \RadioCRM\RadioScheduleService::removeCampaignAudioFile($this->db ?? ($GLOBALS['db'] ?? null), (string) $this->id);
            }
            $this->file = '';
            $this->filename = '';
            $this->file_mime_type = '';
            $this->save();
            return 'true';
        }
        return 'false';
    }

    public function bean_implements($interface)
    {
        switch ($interface) {
            case 'ACL':
                return true;
        }
        return false;
    }
}

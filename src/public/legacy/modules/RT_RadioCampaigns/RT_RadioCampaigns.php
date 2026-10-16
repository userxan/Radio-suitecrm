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

    public function __construct()
    {
        parent::__construct();
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

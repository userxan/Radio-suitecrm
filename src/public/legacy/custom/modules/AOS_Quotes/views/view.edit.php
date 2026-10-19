<?php

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

require_once 'modules/AOS_Quotes/views/view.edit.php';
require_once 'custom/include/RadioCRM/RadioScheduleService.php';

use RadioCRM\RadioScheduleService;

#[\AllowDynamicProperties]
class CustomAOS_QuotesViewEdit extends AOS_QuotesViewEdit
{
    public function display()
    {
        $catalogue = RadioScheduleService::getCatalogueProducts($GLOBALS['db'] ?? null);
        $slotsForTpl = [];
        foreach (RadioScheduleService::SLOTS as $slotKey => $slot) {
            $pn = $slot['part_number'];
            $rate = $catalogue[$pn]['price'] ?? $slot['default_rate'];
            $slotsForTpl[$slotKey] = [
                'key' => $slotKey,
                'label' => $slot['label'],
                'part_number' => $pn,
                'rate' => $rate,
                'hours' => $slot['hours'],
            ];
        }

        // Load saved values from bean or preserve entered $_REQUEST values on validation reload
        $inputMap = [];
        foreach (RadioScheduleService::getAllScheduleFields() as $fieldName => $meta) {
            if (isset($_REQUEST[$fieldName])) {
                $val = $_REQUEST[$fieldName];
            } elseif (isset($this->bean->$fieldName)) {
                $val = $this->bean->$fieldName;
            } else {
                $val = 0;
            }
            $isInvalid = false;
            $inputMap[$fieldName] = RadioScheduleService::parseCellQuantity($val, $isInvalid);
        }

        $rawStart = $_REQUEST['campaign_start_date_c'] ?? ($this->bean->campaign_start_date_c ?? '');
        $rawEnd = $_REQUEST['campaign_end_date_c'] ?? ($this->bean->campaign_end_date_c ?? '');

        if (empty($rawStart) && empty($this->bean->id)) {
            $rawStart = date('Y-m-d');
        }
        if (empty($rawEnd) && empty($this->bean->id)) {
            $rawEnd = date('Y-m-t');
        }

        $calc = RadioScheduleService::calculateSchedule(
            $inputMap,
            (string) $rawStart,
            (string) $rawEnd,
            $GLOBALS['db'] ?? null,
            $_REQUEST['spot_duration_c'] ?? ($this->bean->spot_duration_c ?? '30 sec')
        );

        $tplVars = [
            'PUKFM_SLOTS' => $slotsForTpl,
            'PUKFM_DAYS' => RadioScheduleService::DAYS,
            'PUKFM_CELL_VALUES' => $calc['cells'],
            'PUKFM_START_DATE' => $calc['start_date'] ?? '',
            'PUKFM_END_DATE' => $calc['end_date'] ?? '',
            'PUKFM_WEEKLY_SPOTS' => $calc['weekly_spots'],
            'PUKFM_TOTAL_SPOTS' => $calc['total_spots'],
            'PUKFM_INDUSTRY_CATEGORY' => $_REQUEST['industry_category_c'] ?? ($this->bean->industry_category_c ?? 'other'),
            'PUKFM_SPOT_DURATION' => $_REQUEST['spot_duration_c'] ?? ($this->bean->spot_duration_c ?? '30 sec'),
            'PUKFM_APPLIED_PACKAGE' => $_REQUEST['applied_package_c'] ?? ($this->bean->applied_package_c ?? ''),
            'PUKFM_PACKAGES' => RadioScheduleService::getPackageDefinitions(),
        ];

        foreach ($tplVars as $k => $v) {
            if (!empty($this->ss)) {
                $this->ss->assign($k, $v);
            }
            if (!empty($this->ev) && !empty($this->ev->ss)) {
                $this->ev->ss->assign($k, $v);
            }
        }

        parent::display();
    }
}

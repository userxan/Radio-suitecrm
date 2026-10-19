<?php

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

require_once 'modules/AOS_Quotes/views/view.detail.php';
require_once 'custom/include/RadioCRM/RadioScheduleService.php';

use RadioCRM\RadioScheduleService;

#[\AllowDynamicProperties]
class CustomAOS_QuotesViewDetail extends AOS_QuotesViewDetail
{
    public function display()
    {
        $inputMap = [];
        foreach (RadioScheduleService::getAllScheduleFields() as $fieldName => $meta) {
            $inputMap[$fieldName] = $this->bean->$fieldName ?? 0;
        }
        $calc = RadioScheduleService::calculateSchedule(
            $inputMap,
            (string) ($this->bean->campaign_start_date_c ?? ''),
            (string) ($this->bean->campaign_end_date_c ?? ''),
            $GLOBALS['db'] ?? null
        );

        $tplVars = [
            'RADIO_START_DATE' => $calc['start_date'] ?? '',
            'RADIO_END_DATE' => $calc['end_date'] ?? '',
            'RADIO_WEEKLY_SPOTS' => $calc['weekly_spots'],
            'RADIO_TOTAL_SPOTS' => $calc['total_spots'],
        ];

        foreach ($tplVars as $k => $v) {
            if (!empty($this->ss)) {
                $this->ss->assign($k, $v);
            }
            if (!empty($this->dv) && !empty($this->dv->ss)) {
                $this->dv->ss->assign($k, $v);
            }
        }

        parent::display();
    }
}

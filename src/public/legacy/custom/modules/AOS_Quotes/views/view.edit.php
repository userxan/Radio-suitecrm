<?php

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

require_once 'modules/AOS_Quotes/views/view.edit.php';
require_once 'custom/include/RadioCRM/RadioScheduleService.php';
require_once 'custom/include/RadioCRM/RadioStationConfigService.php';

use RadioCRM\RadioScheduleService;
use RadioCRM\RadioStationConfigService;

#[\AllowDynamicProperties]
class CustomAOS_QuotesViewEdit extends AOS_QuotesViewEdit
{
    public function display()
    {
        $catalogue = RadioScheduleService::getCatalogueProducts($GLOBALS['db'] ?? null);
        $stConfig = RadioStationConfigService::getSettings($GLOBALS['db'] ?? null);
        $confSlots = $stConfig['slots'] ?? RadioStationConfigService::DEFAULT_SLOTS;

        $slotsForTpl = [];
        foreach (RadioScheduleService::SLOTS as $slotKey => $slot) {
            $confSlot = $confSlots[$slotKey] ?? ($confSlots[$slot['part_number']] ?? null);
            $pn = !empty($confSlot['part_number']) ? $confSlot['part_number'] : $slot['part_number'];
            $rate = $catalogue[$pn]['price'] ?? ($confSlot['rate'] ?? $slot['default_rate']);
            $label = !empty($confSlot['title']) ? $confSlot['title'] : $slot['label'];

            $slotDefRate = (float) $slot['default_rate'];
            $customScale = ($slotDefRate > 0 && abs($rate - $slotDefRate) > 0.001)
                ? ($rate / $slotDefRate)
                : 1.0;

            $wkRates = [];
            $wkndRates = [];
            foreach (array_keys($slot['hours']) as $hourKey) {
                $wkRates[] = round(RadioScheduleService::getRateCardHourlyPrice('mon', $hourKey) * $customScale, 2);
                $wkndRates[] = isset($confSlot['weekend_rate']) && is_numeric($confSlot['weekend_rate'])
                    ? round((float) $confSlot['weekend_rate'] * ($cellBaseRate = RadioScheduleService::getRateCardHourlyPrice('sat', $hourKey) / 350.0 > 0 ? 1.0 : 1.0), 2)
                    : round(RadioScheduleService::getRateCardHourlyPrice('sat', $hourKey) * $customScale, 2);
            }
            $minWk = min($wkRates);
            $maxWk = max($wkRates);
            $minWknd = min($wkndRates);
            $maxWknd = max($wkndRates);

            $wkLabel = ($minWk === $maxWk) ? sprintf('R%.2f', $minWk) : sprintf('R%.2f - R%.2f', $minWk, $maxWk);
            $wkndLabel = ($minWknd === $maxWknd) ? sprintf('R%.2f', $minWknd) : sprintf('R%.2f - R%.2f', $minWknd, $maxWknd);

            $slotsForTpl[$slotKey] = [
                'key' => $slotKey,
                'label' => $label,
                'part_number' => $pn,
                'rate' => $rate,
                'weekday_rate' => $minWk,
                'weekday_rate_label' => $wkLabel,
                'weekend_rate' => $minWknd,
                'weekend_rate_label' => $wkndLabel,
                'hours' => $slot['hours'],
                'programme_time' => $confSlot['hours'] ?? ($slot['programme_time'] ?? ''),
            ];
        }

        $cellRates = [];
        foreach (RadioScheduleService::getAllScheduleFields() as $fieldName => $meta) {
            $slotKey = $meta['slot_key'];
            $confSlot = $confSlots[$slotKey] ?? ($confSlots[RadioScheduleService::SLOTS[$slotKey]['part_number']] ?? null);
            $pn = !empty($confSlot['part_number']) ? $confSlot['part_number'] : RadioScheduleService::SLOTS[$slotKey]['part_number'];
            $slotDefRate = (float) (RadioScheduleService::SLOTS[$slotKey]['default_rate'] ?? 350.00);
            $catPrice = isset($catalogue[$pn]['price'])
                ? (float) $catalogue[$pn]['price']
                : $slotDefRate;
            $customScale = ($slotDefRate > 0 && abs($catPrice - $slotDefRate) > 0.001)
                ? ($catPrice / $slotDefRate)
                : 1.0;
            $cellRates[$fieldName] = round(RadioScheduleService::getRateCardHourlyPrice($meta['day_key'], $meta['hour_key']) * $customScale, 2);
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

        $appliedPackage = $_REQUEST['applied_package_c'] ?? ($this->bean->applied_package_c ?? '');
        $calc = RadioScheduleService::calculateSchedule(
            $inputMap,
            (string) $rawStart,
            (string) $rawEnd,
            $GLOBALS['db'] ?? null,
            $_REQUEST['spot_duration_c'] ?? ($this->bean->spot_duration_c ?? '30 sec'),
            $appliedPackage
        );

        $packages = RadioScheduleService::getPackageDefinitions($GLOBALS['db'] ?? null);
        $extrasCatalogue = RadioScheduleService::getRateCardExtrasCatalogue($GLOBALS['db'] ?? null);

        $tplVars = [
            'RADIO_SLOTS' => $slotsForTpl,
            'RADIO_DAYS' => RadioScheduleService::DAYS,
            'RADIO_CELL_VALUES' => $calc['cells'],
            'RADIO_CELL_RATES' => $cellRates,
            'RADIO_START_DATE' => $calc['start_date'] ?? '',
            'RADIO_END_DATE' => $calc['end_date'] ?? '',
            'RADIO_WEEKLY_SPOTS' => $calc['weekly_spots'],
            'RADIO_TOTAL_SPOTS' => $calc['total_spots'],
            'RADIO_INDUSTRY_CATEGORY' => $_REQUEST['industry_category_c'] ?? ($this->bean->industry_category_c ?? 'other'),
            'RADIO_SPOT_DURATION' => $_REQUEST['spot_duration_c'] ?? ($this->bean->spot_duration_c ?? '30 sec'),
            'RADIO_APPLIED_PACKAGE' => $_REQUEST['applied_package_c'] ?? ($this->bean->applied_package_c ?? ''),
            'RADIO_PACKAGES' => $packages,
            'RADIO_PACKAGES_JSON' => json_encode($packages, JSON_UNESCAPED_SLASHES),
            'RADIO_EXTRAS_CATALOGUE' => $extrasCatalogue,
            'RADIO_EXTRAS_JSON' => json_encode($extrasCatalogue, JSON_UNESCAPED_SLASHES),
            'RADIO_CURRENCY' => $stConfig['currency_symbol'] ?? 'R',
            'RADIO_STATION_CONFIGURED' => RadioStationConfigService::isConfigured($GLOBALS['db'] ?? null),
            'RADIO_STATION_NAME' => $stConfig['station_name'] ?? 'Community Radio FM',
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

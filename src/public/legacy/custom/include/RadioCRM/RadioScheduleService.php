<?php

declare(strict_types=1);

namespace RadioCRM;

require_once __DIR__ . '/RadioStationConfigService.php';

use DateTimeImmutable;
use DateTimeZone;

class RadioScheduleService
{
    public const TIMEZONE = 'Africa/Johannesburg';
    public const DEFAULT_VAT_RATE = 0.15;
    public const DEFAULT_VAT_STRING = '15.0';

    public const MAX_HOURLY_CAPACITY = 20; // 10 minutes max per broadcast hour (standard ICASA limit)
    public const CART_RANGE_START = 2000;
    public const CART_RANGE_END = 2999;
    public const DURATION_MULTIPLIERS = [
        '15 sec' => 0.50,
        '30 sec' => 1.00,
        '45 sec' => 1.50,
        '60 sec' => 2.00,
    ];

    /**
     * Weekday mapping to PHP date('w') where Sunday = 0, Monday = 1, ..., Saturday = 6
     */
    public const DAYS = [
        'mon' => ['label' => 'Mon', 'full' => 'Monday', 'php_w' => 1],
        'tue' => ['label' => 'Tue', 'full' => 'Tuesday', 'php_w' => 2],
        'wed' => ['label' => 'Wed', 'full' => 'Wednesday', 'php_w' => 3],
        'thu' => ['label' => 'Thu', 'full' => 'Thursday', 'php_w' => 4],
        'fri' => ['label' => 'Fri', 'full' => 'Friday', 'php_w' => 5],
        'sat' => ['label' => 'Sat', 'full' => 'Saturday', 'php_w' => 6],
        'sun' => ['label' => 'Sun', 'full' => 'Sunday', 'php_w' => 0],
    ];

    /**
     * Definitive 8 programming slots and 24 one-hour rows (Section 5 & Section 7.1)
     */
    public const SLOTS = [
        'pre_breakfast' => [
            'key' => 'pre_breakfast',
            'label' => 'Pre Breakfast',
            'part_number' => 'PRE-BREAKFAST',
            'product_name' => 'PRE-BREAKFAST',
            'default_rate' => 120.00,
            'programme_time' => '05:00-06:00',
            'description' => 'One 30 second prerecorded advertisement played between 05:00 and 06:00.',
            'hours' => [
                '0500_0600' => ['start' => '05:00', 'end' => '06:00', 'label' => '05:00 - 06:00'],
            ],
        ],
        'morning_buzz' => [
            'key' => 'morning_buzz',
            'label' => 'Morning Buzz',
            'part_number' => 'MORNING-BUZZ',
            'product_name' => 'MORNING-BUZZ',
            'default_rate' => 350.00,
            'programme_time' => '06:00-09:00',
            'description' => 'One 30 second prerecorded advertisement played between 06:00 and 09:00.',
            'hours' => [
                '0600_0700' => ['start' => '06:00', 'end' => '07:00', 'label' => '06:00 - 07:00'],
                '0700_0800' => ['start' => '07:00', 'end' => '08:00', 'label' => '07:00 - 08:00'],
                '0800_0900' => ['start' => '08:00', 'end' => '09:00', 'label' => '08:00 - 09:00'],
            ],
        ],
        'midmorning_banter' => [
            'key' => 'midmorning_banter',
            'label' => 'Midmorning Banter',
            'part_number' => 'MIDMORNING-BANTER',
            'product_name' => 'MIDMORNING-BANTER',
            'default_rate' => 320.00,
            'programme_time' => '09:00-12:00',
            'description' => 'One 30 second prerecorded advertisement played between 09:00 and 12:00.',
            'hours' => [
                '0900_1000' => ['start' => '09:00', 'end' => '10:00', 'label' => '09:00 - 10:00'],
                '1000_1100' => ['start' => '10:00', 'end' => '11:00', 'label' => '10:00 - 11:00'],
                '1100_1200' => ['start' => '11:00', 'end' => '12:00', 'label' => '11:00 - 12:00'],
            ],
        ],
        'midday_pulse' => [
            'key' => 'midday_pulse',
            'label' => 'Midday Pulse',
            'part_number' => 'MIDDAY-PULSE',
            'product_name' => 'MIDDAY-PULSE',
            'default_rate' => 350.00,
            'programme_time' => '12:00-15:00',
            'description' => 'One 30 second prerecorded advertisement played between 12:00 and 15:00.',
            'hours' => [
                '1200_1300' => ['start' => '12:00', 'end' => '13:00', 'label' => '12:00 - 13:00'],
                '1300_1400' => ['start' => '13:00', 'end' => '14:00', 'label' => '13:00 - 14:00'],
                '1400_1500' => ['start' => '14:00', 'end' => '15:00', 'label' => '14:00 - 15:00'],
            ],
        ],
        'cruise_control' => [
            'key' => 'cruise_control',
            'label' => 'Cruise Control',
            'part_number' => 'CRUISE-CONTROL',
            'product_name' => 'CRUISE-CONTROL',
            'default_rate' => 350.00,
            'programme_time' => '15:00-18:00',
            'description' => 'One 30 second prerecorded advertisement played between 15:00 and 18:00.',
            'hours' => [
                '1500_1600' => ['start' => '15:00', 'end' => '16:00', 'label' => '15:00 - 16:00'],
                '1600_1700' => ['start' => '16:00', 'end' => '17:00', 'label' => '16:00 - 17:00'],
                '1700_1800' => ['start' => '17:00', 'end' => '18:00', 'label' => '17:00 - 18:00'],
            ],
        ],
        'specialty_show_1' => [
            'key' => 'specialty_show_1',
            'label' => 'Specialty Show 1',
            'part_number' => 'SPECIALTY-SHOW-1',
            'product_name' => 'SPECIALTY-SHOW-1',
            'default_rate' => 300.00,
            'programme_time' => '18:00-21:00',
            'description' => 'One 30 second prerecorded advertisement played between 18:00 and 21:00.',
            'hours' => [
                '1800_1900' => ['start' => '18:00', 'end' => '19:00', 'label' => '18:00 - 19:00'],
                '1900_2000' => ['start' => '19:00', 'end' => '20:00', 'label' => '19:00 - 20:00'],
                '2000_2100' => ['start' => '20:00', 'end' => '21:00', 'label' => '20:00 - 21:00'],
            ],
        ],
        'specialty_show_2' => [
            'key' => 'specialty_show_2',
            'label' => 'Specialty Show 2',
            'part_number' => 'SPECIALTY-SHOW-2',
            'product_name' => 'SPECIALTY-SHOW-2',
            'default_rate' => 220.00,
            'programme_time' => '21:00-00:00',
            'description' => 'One 30 second prerecorded advertisement played between 21:00 and 00:00.',
            'hours' => [
                '2100_2200' => ['start' => '21:00', 'end' => '22:00', 'label' => '21:00 - 22:00'],
                '2200_2300' => ['start' => '22:00', 'end' => '23:00', 'label' => '22:00 - 23:00'],
                '2300_0000' => ['start' => '23:00', 'end' => '00:00', 'label' => '23:00 - 00:00'],
            ],
        ],
        'music_unlimited' => [
            'key' => 'music_unlimited',
            'label' => 'Music Unlimited',
            'part_number' => 'MUSIC-UNLIMITED',
            'product_name' => 'MUSIC-UNLIMITED',
            'default_rate' => 120.00,
            'programme_time' => '00:00-05:00',
            'description' => 'One 30 second prerecorded advertisement played between 00:00 and 05:00.',
            'hours' => [
                '0000_0100' => ['start' => '00:00', 'end' => '01:00', 'label' => '00:00 - 01:00'],
                '0100_0200' => ['start' => '01:00', 'end' => '02:00', 'label' => '01:00 - 02:00'],
                '0200_0300' => ['start' => '02:00', 'end' => '03:00', 'label' => '02:00 - 03:00'],
                '0300_0400' => ['start' => '03:00', 'end' => '04:00', 'label' => '03:00 - 04:00'],
                '0400_0500' => ['start' => '04:00', 'end' => '05:00', 'label' => '04:00 - 05:00'],
            ],
        ],
    ];

    /**
     * Official Rate Card 2026 hourly unit pricing for Recorded Ads (30 Seconds).
     * Differentiates Monday-Friday (weekday) vs Saturday-Sunday (weekend).
     */
    public static function getRateCardHourlyPrice(string $dayKey, string $hourKey): float
    {
        $isWeekend = ($dayKey === 'sat' || $dayKey === 'sun');
        if (!$isWeekend) {
            return match ($hourKey) {
                '0000_0100', '0100_0200', '0200_0300', '0300_0400', '0400_0500', '0500_0600' => 120.00,
                '0600_0700', '0700_0800', '0800_0900' => 350.00,
                '0900_1000', '1000_1100', '1100_1200' => 320.00,
                '1200_1300', '1300_1400', '1400_1500' => 350.00,
                '1500_1600', '1600_1700', '1700_1800' => 350.00,
                '1800_1900', '1900_2000', '2000_2100' => 300.00,
                '2100_2200', '2200_2300', '2300_0000' => 220.00,
                default => 220.00,
            };
        }

        return match ($hourKey) {
            '0000_0100', '0100_0200', '0200_0300', '0300_0400', '0400_0500', '0500_0600' => 120.00,
            '0600_0700', '0700_0800', '0800_0900', '0900_1000' => 300.00,
            '1000_1100', '1100_1200', '1200_1300', '1300_1400' => 350.00,
            '1400_1500', '1500_1600', '1600_1700', '1700_1800' => 350.00,
            '1800_1900', '1900_2000', '2000_2100', '2100_2200' => 220.00,
            '2200_2300', '2300_0000' => 120.00,
            default => 220.00,
        };
    }

    /**
     * Returns metadata for all 168 schedule fields keyed by exact column name.
     *
     * @return array<string, array{field:string, day_key:string, day_label:string, day_full:string, php_w:int, slot_key:string, slot_label:string, part_number:string, hour_key:string, start_time:string, end_time:string, hour_label:string}>
     */
    public static function getAllScheduleFields(): array
    {
        static $fields = null;
        if ($fields !== null) {
            return $fields;
        }
        $fields = [];
        foreach (self::SLOTS as $slotKey => $slot) {
            foreach ($slot['hours'] as $hourKey => $hourInfo) {
                foreach (self::DAYS as $dayKey => $dayInfo) {
                    $fieldName = "{$dayKey}_{$slotKey}_{$hourKey}_c";
                    $fields[$fieldName] = [
                        'field' => $fieldName,
                        'day_key' => $dayKey,
                        'day_label' => $dayInfo['label'],
                        'day_full' => $dayInfo['full'],
                        'php_w' => $dayInfo['php_w'],
                        'slot_key' => $slotKey,
                        'slot_label' => $slot['label'],
                        'part_number' => $slot['part_number'],
                        'hour_key' => $hourKey,
                        'start_time' => $hourInfo['start'],
                        'end_time' => $hourInfo['end'],
                        'hour_label' => $hourInfo['label'],
                    ];
                }
            }
        }
        return $fields;
    }

    /**
     * Normalize a date string into YYYY-MM-DD in Africa/Johannesburg timezone.
     * Returns null if empty or invalid.
     */
    public static function normalizeDate(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }
        $raw = trim($raw);
        if ($raw === '' || $raw === '0000-00-00') {
            return null;
        }

        $tz = new DateTimeZone(self::TIMEZONE);

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $raw, $m)) {
            if (checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
                return sprintf('%04d-%02d-%02d', (int) $m[1], (int) $m[2], (int) $m[3]);
            }
            return null;
        }

        // Try SuiteCRM TimeDate if available for user-formatted dates
        if (class_exists('\TimeDate')) {
            try {
                $td = \TimeDate::getInstance();
                $dbDate = $td->to_db_date($raw, false);
                if (is_string($dbDate) && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $dbDate, $m)) {
                    if (checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
                        return $dbDate;
                    }
                }
            } catch (\Throwable $e) {
                // Fall through to standard formats
            }
        }

        foreach (['d/m/Y', 'm/d/Y', 'd-m-Y', 'Y/m/d', 'j M Y', 'd M Y'] as $fmt) {
            $dt = DateTimeImmutable::createFromFormat('!' . $fmt, $raw, $tz);
            if ($dt instanceof DateTimeImmutable) {
                return $dt->format('Y-m-d');
            }
        }

        return null;
    }

    /**
     * Deterministic weekday occurrence count in inclusive [start, end] range (Section 6.3).
     */
    public static function weekdayOccurrences(
        DateTimeImmutable $start,
        DateTimeImmutable $end,
        int $phpWeekday
    ): int {
        if ($end < $start) {
            return 0;
        }
        $startW = (int) $start->format('w'); // Sunday = 0
        $offset = ($phpWeekday - $startW + 7) % 7;
        $first = $start->modify('+' . $offset . ' days');
        if ($first > $end) {
            return 0;
        }
        $days = (int) $first->diff($end)->format('%a');
        return 1 + intdiv($days, 7);
    }

    /**
     * Sanitize a cell input to a nonnegative integer (blank -> 0).
     * Also returns whether the raw value was invalid (negative or non-integer).
     */
    public static function parseCellQuantity(mixed $val, bool &$isInvalid = false): int
    {
        $isInvalid = false;
        if ($val === null || $val === '' || $val === false) {
            return 0;
        }
        if (is_int($val)) {
            if ($val < 0) {
                $isInvalid = true;
                return 0;
            }
            return $val;
        }
        $str = trim((string) $val);
        if ($str === '') {
            return 0;
        }
        if (!preg_match('/^\d+$/', $str)) {
            $isInvalid = true;
            return 0;
        }
        return (int) $str;
    }

    /**
     * Clean product line description to ensure no verbose schedule summary appears in Quote or PDF (Section 11.1).
     */
    public static function cleanRadioLineDescription(?string $description, string $partNumber = ''): string
    {
        foreach (self::SLOTS as $slot) {
            if ($partNumber !== '' && strcasecmp($slot['part_number'], $partNumber) === 0) {
                return $slot['description'];
            }
        }
        if ($description === null) {
            return '';
        }
        $cleaned = preg_replace('/(Schedule Summary|Selected Weekly Schedule).*$/is', '', $description);
        $cleaned = trim((string) $cleaned);
        return $cleaned;
    }

    /**
     * Fetch current unit prices and product metadata from aos_products table if DB is available,
     * falling back to the 8 historical default rates.
     *
     * @param mixed $db SuiteCRM DBManager instance or null
     * @return array<string, array{id:string, name:string, part_number:string, price:float, description:string, slot_key:string, slot_label:string}>
     */
    public static function getCatalogueProducts(mixed $db = null): array
    {
        $catalogue = [];
        foreach (self::SLOTS as $slotKey => $slot) {
            $pn = $slot['part_number'];
            $catalogue[$pn] = [
                'id' => '',
                'name' => $slot['product_name'],
                'part_number' => $pn,
                'price' => (float) $slot['default_rate'],
                'description' => $slot['description'],
                'slot_key' => $slotKey,
                'slot_label' => $slot['label'],
            ];
        }

        if ($db === null && isset($GLOBALS['db']) && is_object($GLOBALS['db'])) {
            $db = $GLOBALS['db'];
        }

        if (is_object($db) && method_exists($db, 'query')) {
            $partList = array_map(static fn($s) => "'" . $s['part_number'] . "'", self::SLOTS);
            $sql = "SELECT id, name, part_number, price, description
                    FROM aos_products
                    WHERE deleted = 0 AND part_number IN (" . implode(',', $partList) . ")
                    ORDER BY date_entered ASC";
            $res = $db->query($sql, false);
            if ($res) {
                while ($row = $db->fetchByAssoc($res)) {
                    $pn = strtoupper(trim((string) ($row['part_number'] ?? '')));
                    if (isset($catalogue[$pn]) && $catalogue[$pn]['id'] === '') {
                        $catalogue[$pn]['id'] = (string) $row['id'];
                        $catalogue[$pn]['name'] = (string) ($row['name'] ?: $pn);
                        if (isset($row['price']) && is_numeric($row['price']) && (float) $row['price'] > 0) {
                            $catalogue[$pn]['price'] = round((float) $row['price'], 2);
                        }
                        $catalogue[$pn]['description'] = self::cleanRadioLineDescription(
                            (string) ($row['description'] ?? ''),
                            $pn
                        );
                    }
                }
            }
        }

        return $catalogue;
    }

    /**
     * Authoritative calculation of weekly spots, campaign total spots, validation status,
     * and aggregated product lines across the 8 programmes (Sections 6, 7, 8, 9).
     *
     * @param array<string, mixed> $inputData Map containing schedule field values (or Bean properties)
     * @param string|null $rawStartDate
     * @param string|null $rawEndDate
     * @param mixed $db Optional DB instance for live catalogue rates
     * @param string|null $spotDuration
     * @param string|null $appliedPackage
     * @return array<string, mixed>
     */
    public static function calculateSchedule(
        array $inputData,
        ?string $rawStartDate,
        ?string $rawEndDate,
        mixed $db = null,
        ?string $spotDuration = null,
        ?string $appliedPackage = null
    ): array {
        $tz = new DateTimeZone(self::TIMEZONE);
        $startStr = self::normalizeDate($rawStartDate);
        $endStr = self::normalizeDate($rawEndDate);

        $datesProvided = ($startStr !== null && $endStr !== null);
        $dateRangeValid = false;
        $dateError = null;
        $startDt = null;
        $endDt = null;

        if (($rawStartDate !== null && trim($rawStartDate) !== '' && $startStr === null)
            || ($rawEndDate !== null && trim($rawEndDate) !== '' && $endStr === null)
        ) {
            $dateError = 'Invalid date format provided.';
        } elseif ($datesProvided) {
            $startDt = new DateTimeImmutable($startStr . ' 00:00:00', $tz);
            $endDt = new DateTimeImmutable($endStr . ' 00:00:00', $tz);
            if ($endDt < $startDt) {
                $dateError = 'Campaign End Date cannot be earlier than Campaign Start Date.';
            } else {
                $dateRangeValid = true;
            }
        }

        // Precompute weekday occurrence counts for 0..6 when dates are valid
        $weekdayRepeats = [];
        if ($dateRangeValid && $startDt !== null && $endDt !== null) {
            for ($w = 0; $w <= 6; $w++) {
                $weekdayRepeats[$w] = self::weekdayOccurrences($startDt, $endDt, $w);
            }
        }

        $allFields = self::getAllScheduleFields();
        $catalogue = self::getCatalogueProducts($db);

        $spotDur = $spotDuration ?? ($inputData['spot_duration_c'] ?? ($inputData['spot_length'] ?? '30 sec'));
        $durationMultiplier = self::getDurationMultiplier($spotDur);

        $appliedPkg = $appliedPackage ?? ($inputData['applied_package_c'] ?? '');
        $pkgDefinitions = self::getPackageDefinitions();
        $isPackage = !empty($appliedPkg) && isset($pkgDefinitions[$appliedPkg]);
        $pkgDef = $isPackage ? $pkgDefinitions[$appliedPkg] : null;

        $weeklySpots = 0;
        $totalSpots = 0;
        $invalidFields = [];
        $sanitizedCells = [];

        $slotAggregates = [];
        foreach (self::SLOTS as $slotKey => $slot) {
            $pn = $slot['part_number'];
            $cat = $catalogue[$pn];
            $basePrice = (float) $cat['price'];
            $scaledPrice = round($basePrice * $durationMultiplier, 2);
            $slotAggregates[$slotKey] = [
                'slot_key' => $slotKey,
                'slot_label' => $slot['label'],
                'part_number' => $pn,
                'product_id' => $cat['id'],
                'product_name' => $cat['name'],
                'unit_price' => $scaledPrice,
                'description' => $cat['description'],
                'weekly_qty' => 0,
                'total_qty' => 0,
                'weekly_net' => 0.0,
                'total_net' => 0.0,
                'net_total' => 0.0,
                'vat_rate' => self::DEFAULT_VAT_STRING,
                'vat_amt' => 0.0,
                'gross_total' => 0.0,
            ];
        }

        foreach ($allFields as $fieldName => $meta) {
            $rawVal = $inputData[$fieldName] ?? 0;
            $isInvalid = false;
            $qty = self::parseCellQuantity($rawVal, $isInvalid);
            if ($isInvalid) {
                $invalidFields[] = $fieldName;
            }
            $sanitizedCells[$fieldName] = $qty;

            if ($qty <= 0) {
                continue;
            }

            $weeklySpots += $qty;
            $slotKey = $meta['slot_key'];
            $slotAggregates[$slotKey]['weekly_qty'] += $qty;

            $cellBaseRate = self::getRateCardHourlyPrice($meta['day_key'], $meta['hour_key']);
            $slotDefRate = (float) (self::SLOTS[$slotKey]['default_rate'] ?? 350.00);
            $catPrice = isset($catalogue[$slotAggregates[$slotKey]['part_number']]['price'])
                ? (float) $catalogue[$slotAggregates[$slotKey]['part_number']]['price']
                : $slotDefRate;
            $customScale = ($slotDefRate > 0 && abs($catPrice - $slotDefRate) > 0.001)
                ? ($catPrice / $slotDefRate)
                : 1.0;
            $cellUnitRate = round($cellBaseRate * $customScale * $durationMultiplier, 2);

            $cellWeeklyNet = round($qty * $cellUnitRate, 2);
            $slotAggregates[$slotKey]['weekly_net'] = round($slotAggregates[$slotKey]['weekly_net'] + $cellWeeklyNet, 2);

            if ($dateRangeValid) {
                $repeats = $weekdayRepeats[$meta['php_w']] ?? 0;
                $fieldTotalPlays = $qty * $repeats;
                $totalSpots += $fieldTotalPlays;
                $slotAggregates[$slotKey]['total_qty'] += $fieldTotalPlays;
                $cellTotalNet = round($fieldTotalPlays * $cellUnitRate, 2);
                $slotAggregates[$slotKey]['total_net'] = round($slotAggregates[$slotKey]['total_net'] + $cellTotalNet, 2);
            }
        }

        $activeProducts = [];
        $subtotalAmount = 0.0;
        $taxAmount = 0.0;
        $totalAmount = 0.0;

        if ($isPackage && $dateRangeValid && $totalSpots > 0) {
            $pkgBaseRate = round((float) $pkgDef['rate'] * $durationMultiplier, 2);
            $packageCount = max(1, (int) round($totalSpots / (float) $pkgDef['total_spots']));
            $targetSubtotal = round($packageCount * $pkgBaseRate, 2);

            $activeKeys = [];
            foreach ($slotAggregates as $slotKey => $agg) {
                if ($agg['total_qty'] > 0) {
                    $activeKeys[] = $slotKey;
                }
            }

            $accumulatedNet = 0.0;
            $countActive = count($activeKeys);
            for ($i = 0; $i < $countActive; $i++) {
                $slotKey = $activeKeys[$i];
                $lineQty = $slotAggregates[$slotKey]['total_qty'];
                if ($i === $countActive - 1) {
                    $net = round($targetSubtotal - $accumulatedNet, 2);
                } else {
                    $net = round(($lineQty / $totalSpots) * $targetSubtotal, 2);
                }
                $accumulatedNet = round($accumulatedNet + $net, 2);
                $unitPrice = $lineQty > 0 ? round($net / $lineQty, 4) : 0.0;
                $vat = round($net * self::DEFAULT_VAT_RATE, 2);
                $gross = round($net + $vat, 2);

                $slotAggregates[$slotKey]['unit_price'] = $unitPrice;
                $slotAggregates[$slotKey]['net_total'] = $net;
                $slotAggregates[$slotKey]['vat_amt'] = $vat;
                $slotAggregates[$slotKey]['gross_total'] = $gross;
                $activeProducts[] = $slotAggregates[$slotKey];

                $subtotalAmount = round($subtotalAmount + $net, 2);
                $taxAmount = round($taxAmount + $vat, 2);
                $totalAmount = round($totalAmount + $gross, 2);
            }
        } else {
            if ($isPackage && !$dateRangeValid && $weeklySpots > 0) {
                $pkgBaseRate = round((float) $pkgDef['rate'] * $durationMultiplier, 2);
                $ratePerSpot = $pkgBaseRate / (float) $pkgDef['total_spots'];
                foreach ($slotAggregates as $slotKey => &$agg) {
                    if ($agg['weekly_qty'] > 0) {
                        $agg['unit_price'] = round($ratePerSpot, 4);
                    }
                }
                unset($agg);
            }

            foreach ($slotAggregates as $slotKey => &$agg) {
                $lineQty = $dateRangeValid ? $agg['total_qty'] : 0;
                if ($dateRangeValid) {
                    $net = round((float) $agg['total_net'], 2);
                    $unitPrice = $lineQty > 0 ? round($net / $lineQty, 4) : (float) $agg['unit_price'];
                } else {
                    $weeklyQty = $agg['weekly_qty'];
                    $net = 0.0;
                    $unitPrice = $weeklyQty > 0 ? round($agg['weekly_net'] / $weeklyQty, 4) : (float) $agg['unit_price'];
                }
                $vat = round($net * self::DEFAULT_VAT_RATE, 2);
                $gross = round($net + $vat, 2);

                $agg['unit_price'] = $unitPrice;
                $agg['net_total'] = $net;
                $agg['vat_amt'] = $vat;
                $agg['gross_total'] = $gross;

                if ($lineQty > 0) {
                    $activeProducts[] = $agg;
                    $subtotalAmount = round($subtotalAmount + $net, 2);
                    $taxAmount = round($taxAmount + $vat, 2);
                    $totalAmount = round($totalAmount + $gross, 2);
                }
            }
            unset($agg);
        }

        return [
            'start_date' => $startStr,
            'end_date' => $endStr,
            'dates_provided' => $datesProvided,
            'date_range_valid' => $dateRangeValid,
            'date_error' => $dateError,
            'invalid_fields' => $invalidFields,
            'valid' => ($dateError === null && count($invalidFields) === 0),
            'weekly_spots' => $weeklySpots,
            'total_spots' => $dateRangeValid ? $totalSpots : 0,
            'spot_duration' => $spotDur,
            'duration_multiplier' => $durationMultiplier,
            'applied_package' => $appliedPkg,
            'is_package' => $isPackage,
            'package_definition' => $pkgDef,
            'cells' => $sanitizedCells,
            'programmes' => array_values($slotAggregates),
            'active_products' => $activeProducts,
            'totals' => [
                'total_amt' => $subtotalAmount,
                'subtotal_amount' => $subtotalAmount,
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
            ],
        ];
    }

    /**
     * Format spot_length (e.g. "30 sec", "30", "45 sec") into Myriad MM:SS duration (e.g. "00:30")
     * and canonical CSV string (e.g. "30 sec").
     *
     * @return array{csv_length:string, myriad_mmss:string, seconds:int}
     */
    public static function formatSpotLength(?string $spotLength): array
    {
        $raw = trim((string) ($spotLength ?? ''));
        $seconds = 30;
        if (preg_match('/(\d+)/', $raw, $m)) {
            $val = (int) $m[1];
            if ($val > 0) {
                $seconds = $val;
            }
        }
        $mins = intdiv($seconds, 60);
        $secs = $seconds % 60;
        return [
            'csv_length' => $seconds . ' sec',
            'myriad_mmss' => sprintf('%02d:%02d', $mins, $secs),
            'seconds' => $seconds,
        ];
    }

    /**
     * Compute evenly spaced play times within a 1-hour block (Section 14.4).
     * interval = floor((end_seconds - start_seconds) / (N + 1))
     * play j (1..N) at start_seconds + interval * j
     *
     * @return list<string> List of HH:MM:SS strings
     */
    public static function computeBlockPlayTimes(string $startHourHHMM, string $endHourHHMM, int $playsInBlock): array
    {
        if ($playsInBlock <= 0) {
            return [];
        }
        [$sh, $sm] = array_map('intval', explode(':', $startHourHHMM));
        [$eh, $em] = array_map('intval', explode(':', $endHourHHMM));

        $startSeconds = ($sh * 3600) + ($sm * 60);
        $endSeconds = ($eh * 3600) + ($em * 60);
        // Handle 23:00-00:00 crossing midnight
        if ($endSeconds <= $startSeconds) {
            $endSeconds += 86400;
        }

        $duration = $endSeconds - $startSeconds; // 3600 for 1-hour block
        $interval = intdiv($duration, $playsInBlock + 1);

        $times = [];
        for ($j = 1; $j <= $playsInBlock; $j++) {
            $playSec = ($startSeconds + ($interval * $j)) % 86400;
            $hh = intdiv($playSec, 3600);
            $mm = intdiv($playSec % 3600, 60);
            $ss = $playSec % 60;
            $times[] = sprintf('%02d:%02d:%02d', $hh, $mm, $ss);
        }
        return $times;
    }

    /**
     * Sanitize campaign name for safe filenames (Section 14.5 & 14.6).
     */
    public static function safeFilenameSegment(string $name): string
    {
        $safe = preg_replace('/[^A-Za-z0-9_\-]+/', '_', trim($name));
        $safe = trim((string) $safe, '_');
        return $safe !== '' ? $safe : 'Campaign';
    }

    /**
     * Sanitize log title to remove pipes and CR/LF (Section 14.5).
     */
    public static function sanitizeMyriadTitle(string $text): string
    {
        $clean = str_replace(['|', "\r", "\n"], [' ', ' ', ' '], $text);
        $clean = preg_replace('/\s+/', ' ', $clean);
        return trim((string) $clean);
    }

    /**
     * Format a single CSV line matching Section 14.2 & 14.3 dialect:
     * Strings containing spaces or text fields quoted where appropriate, matching:
     * "Radio Campaign - Test Radio Advert Quote",5,1,2026-06-16,Tuesday,21:30:00,21:00,22:00,"Specialty Show 2","30 sec",1,1
     */
    public static function formatCsvRow(array $row): string
    {
        $quoteStr = static function (string $val): string {
            return '"' . str_replace('"', '""', $val) . '"';
        };
        $quoteIfNeeded = static function (string $val) use ($quoteStr): string {
            if (preg_match('/^[0-9]+$/', $val)) {
                return $val;
            }
            if (preg_match('/[, "\r\n]/', $val)) {
                return $quoteStr($val);
            }
            return $val;
        };

        return implode(',', [
            $quoteStr((string) $row['campaign_name']),
            $quoteIfNeeded((string) $row['quote_reference']),
            $quoteIfNeeded((string) $row['myriad_cart_number']),
            (string) $row['date'],
            (string) $row['day'],
            (string) $row['scheduled_time'],
            (string) $row['start_time'],
            (string) $row['end_time'],
            $quoteStr((string) $row['slot']),
            $quoteStr((string) $row['spot_length']),
            (string) ((int) $row['play_number']),
            (string) ((int) $row['total_plays_in_block']),
        ]);
    }

    /**
     * Generate Myriad CSV and daily .log files atomically and validate output (Section 13.4 & Section 14).
     *
     * @param array<string, mixed> $campaignData
     * @param array<string, mixed> $quoteScheduleData
     * @param string $exportRoot Default /srv/myriad_exports
     * @return array{success:bool, message:string, csv_path?:string, daily_logs?:list<string>, total_plays?:int, active_days?:int}
     */
    public static function exportCampaignToMyriad(
        array $campaignData,
        array $quoteScheduleData,
        string $exportRoot = '/srv/myriad_exports'
    ): array {
        $campaignName = trim((string) ($campaignData['name'] ?? ''));
        $quoteRef = trim((string) ($campaignData['quote_reference'] ?? ''));
        $cartNumber = trim((string) ($campaignData['myriad_cart_number'] ?? ''));
        $spotLengthRaw = trim((string) ($campaignData['spot_length'] ?? '30 sec'));
        $startDateStr = self::normalizeDate((string) ($campaignData['start_date'] ?? ''));
        $endDateStr = self::normalizeDate((string) ($campaignData['end_date'] ?? ''));

        if ($campaignName === '') {
            return ['success' => false, 'message' => 'Campaign name is missing.'];
        }
        if ($cartNumber === '') {
            return ['success' => false, 'message' => 'Myriad Cart Number is required before exporting.'];
        }
        // Sanitize cart number against pipes/newlines
        $cartNumber = self::sanitizeMyriadTitle($cartNumber);

        if ($startDateStr === null || $endDateStr === null) {
            return ['success' => false, 'message' => 'Campaign Start Date and End Date are required before exporting.'];
        }

        $tz = new DateTimeZone(self::TIMEZONE);
        $startDt = new DateTimeImmutable($startDateStr . ' 00:00:00', $tz);
        $endDt = new DateTimeImmutable($endDateStr . ' 00:00:00', $tz);
        if ($endDt < $startDt) {
            return ['success' => false, 'message' => 'Campaign End Date cannot be earlier than Start Date.'];
        }

        $calc = self::calculateSchedule($quoteScheduleData, $startDateStr, $endDateStr);
        if (!$calc['valid']) {
            return ['success' => false, 'message' => $calc['date_error'] ?? 'Invalid schedule values on linked Quote.'];
        }
        if ($calc['total_spots'] <= 0) {
            return ['success' => false, 'message' => 'Linked Quote has zero scheduled spots in the campaign date range.'];
        }

        $spotInfo = self::formatSpotLength($spotLengthRaw);
        $allFields = self::getAllScheduleFields();
        $cells = $calc['cells'];

        // Map weekday number (0..6) -> list of active hour blocks ordered chronologically 00:00 -> 23:00
        $weekdayBlocks = [0 => [], 1 => [], 2 => [], 3 => [], 4 => [], 5 => [], 6 => []];
        foreach ($allFields as $fieldName => $meta) {
            $qty = (int) ($cells[$fieldName] ?? 0);
            if ($qty > 0) {
                $weekdayBlocks[$meta['php_w']][] = [
                    'field' => $fieldName,
                    'qty' => $qty,
                    'slot_label' => $meta['slot_label'],
                    'start_time' => $meta['start_time'],
                    'end_time' => $meta['end_time'],
                ];
            }
        }

        foreach ($weekdayBlocks as $w => &$blocks) {
            usort($blocks, static fn(array $a, array $b): int => strcmp($a['start_time'], $b['start_time']));
        }
        unset($blocks);

        $csvRows = [];
        $dailyLogLines = []; // keyed by YYYY-MM-DD

        for ($cursor = $startDt; $cursor <= $endDt; $cursor = $cursor->modify('+1 day')) {
            $w = (int) $cursor->format('w');
            if (empty($weekdayBlocks[$w])) {
                continue;
            }
            $dateYmd = $cursor->format('Y-m-d');
            $dayFull = $cursor->format('l');

            foreach ($weekdayBlocks[$w] as $block) {
                $totalPlaysInBlock = (int) $block['qty'];
                $playTimes = self::computeBlockPlayTimes(
                    $block['start_time'],
                    $block['end_time'],
                    $totalPlaysInBlock
                );

                foreach ($playTimes as $idx => $scheduledTime) {
                    $playNumber = $idx + 1;
                    $csvRows[] = [
                        'campaign_name' => $campaignName,
                        'quote_reference' => $quoteRef,
                        'myriad_cart_number' => $cartNumber,
                        'date' => $dateYmd,
                        'day' => $dayFull,
                        'scheduled_time' => $scheduledTime,
                        'start_time' => $block['start_time'],
                        'end_time' => $block['end_time'],
                        'slot' => $block['slot_label'],
                        'spot_length' => $spotInfo['csv_length'],
                        'play_number' => $playNumber,
                        'total_plays_in_block' => $totalPlaysInBlock,
                    ];

                    $logTitle = self::sanitizeMyriadTitle($campaignName . ' - ' . $block['slot_label']);
                    // Exact Section 14.5 format: | |HH:MM:SS|CART|MM:SS|AUDIO|TITLE|
                    $dailyLogLines[$dateYmd][] = sprintf(
                        '| |%s|%s|%s|AUDIO|%s|',
                        $scheduledTime,
                        $cartNumber,
                        $spotInfo['myriad_mmss'],
                        $logTitle
                    );
                }
            }
        }

        if (count($csvRows) !== $calc['total_spots']) {
            return [
                'success' => false,
                'message' => sprintf(
                    'Schedule expansion count mismatch: expected %d plays, generated %d.',
                    $calc['total_spots'],
                    count($csvRows)
                ),
            ];
        }

        $dailyDir = rtrim($exportRoot, '/') . '/myriad_daily_logs';
        if (!is_dir($exportRoot) && !@mkdir($exportRoot, 0775, true)) {
            return ['success' => false, 'message' => 'Cannot create Myriad export directory: ' . $exportRoot];
        }
        if (!is_dir($dailyDir) && !@mkdir($dailyDir, 0775, true)) {
            return ['success' => false, 'message' => 'Cannot create Myriad daily log directory: ' . $dailyDir];
        }
        if (!is_writable($exportRoot) || !is_writable($dailyDir)) {
            return ['success' => false, 'message' => 'Myriad export directories are not writable by web user.'];
        }

        // Timestamp + optional suffix so rapid repeated exports never overwrite prior snapshots (Section 14.1 & D6)
        $now = new DateTimeImmutable('now', $tz);
        $timestamp = $now->format('Ymd_His');
        $safeName = self::safeFilenameSegment($campaignName);

        $csvFilename = "radio_campaign_{$safeName}_{$timestamp}.csv";
        $csvPath = rtrim($exportRoot, '/') . '/' . $csvFilename;
        $suffix = 1;
        while (file_exists($csvPath)) {
            $timestamp = $now->format('Ymd_His') . '_' . $suffix;
            $csvFilename = "radio_campaign_{$safeName}_{$timestamp}.csv";
            $csvPath = rtrim($exportRoot, '/') . '/' . $csvFilename;
            $suffix++;
        }

        // Build CSV content with exact 12-column header (Section 14.2)
        $csvHeader = 'Campaign Name,Quote Reference,Myriad Cart Number,Date,Day,Scheduled Time,Start Time,End Time,Slot,Spot Length,Play Number,Total Plays In Block';
        $csvContentLines = [$csvHeader];
        foreach ($csvRows as $row) {
            $csvContentLines[] = self::formatCsvRow($row);
        }
        $csvContent = implode("\r\n", $csvContentLines) . "\r\n";

        // Atomic write for CSV
        $tmpCsv = $csvPath . '.tmp.' . uniqid('', true);
        if (@file_put_contents($tmpCsv, $csvContent, LOCK_EX) === false || !@rename($tmpCsv, $csvPath)) {
            @unlink($tmpCsv);
            return ['success' => false, 'message' => 'Failed to write Myriad CSV export file.'];
        }
        @chmod($csvPath, 0664);

        $stationSettings = class_exists('RadioStationConfigService')
            ? RadioStationConfigService::getSettings()
            : [];
        $rawCallsign = (string) ($stationSettings['station_callsign'] ?? 'Radio');
        $callsign = preg_replace('/[^a-zA-Z0-9_-]/', '', $rawCallsign);
        if ($callsign === '') {
            $callsign = 'Radio';
        }

        // Atomic write for each active day's .log file (Section 14.5)
        $writtenLogs = [];
        foreach ($dailyLogLines as $dateYmd => $lines) {
            $ymdCompact = str_replace('-', '', $dateYmd);
            $logFilename = "{$callsign}_Adverts_{$ymdCompact}_{$safeName}_{$timestamp}.log";
            $logPath = $dailyDir . '/' . $logFilename;
            $tmpLog = $logPath . '.tmp.' . uniqid('', true);
            $logContent = implode("\r\n", $lines) . "\r\n";
            if (@file_put_contents($tmpLog, $logContent, LOCK_EX) === false || !@rename($tmpLog, $logPath)) {
                @unlink($tmpLog);
                // Clean up partial export files on failure
                @unlink($csvPath);
                foreach ($writtenLogs as $wl) {
                    @unlink($wl);
                }
                return ['success' => false, 'message' => 'Failed to write daily Myriad .log file for ' . $dateYmd];
            }
            @chmod($logPath, 0664);
            $writtenLogs[] = $logPath;
        }

        // Export attached MP3 / audio file if present on the campaign
        $audioInfo = null;
        if (!empty($campaignData['audio_file']) && is_array($campaignData['audio_file'])) {
            $audioInfo = $campaignData['audio_file'];
        } elseif (!empty($campaignData['id'])) {
            $audioInfo = self::findCampaignAudioFile(
                $GLOBALS['db'] ?? null,
                (string) $campaignData['id'],
                (string) ($campaignData['filename'] ?? '')
            );
        }

        $exportedAudioPath = null;
        $exportedAudioFilename = null;
        if (is_array($audioInfo)
            && !empty($audioInfo['source_path'])
            && is_file((string) $audioInfo['source_path'])
            && is_readable((string) $audioInfo['source_path'])
        ) {
            $audioDir = rtrim($exportRoot, '/') . '/myriad_audio';
            if (is_dir($audioDir) || @mkdir($audioDir, 0775, true)) {
                $origName = trim((string) ($audioInfo['original_name'] ?? 'advert.mp3'));
                $ext = strtolower((string) pathinfo($origName, PATHINFO_EXTENSION));
                if ($ext === '') {
                    $ext = 'mp3';
                }
                $baseOrig = (string) pathinfo($origName, PATHINFO_FILENAME);
                $safeOrigBase = self::safeFilenameSegment($baseOrig !== '' ? $baseOrig : 'advert');
                $safeCart = self::safeFilenameSegment($cartNumber);

                $exportedAudioFilename = "CART_{$safeCart}_{$safeOrigBase}.{$ext}";
                $audioDest = $audioDir . '/' . $exportedAudioFilename;
                $tmpAudio = $audioDest . '.tmp.' . uniqid('', true);
                if (@copy((string) $audioInfo['source_path'], $tmpAudio) && @rename($tmpAudio, $audioDest)) {
                    @chmod($audioDest, 0664);
                    $exportedAudioPath = $audioDest;
                } else {
                    @unlink($tmpAudio);
                }
            }
        }

        $msg = sprintf(
            'Exported %d scheduled plays across %d active day(s) to Myriad CSV (%s) and daily .log files.',
            count($csvRows),
            count($writtenLogs),
            $csvFilename
        );
        if ($exportedAudioFilename !== null) {
            $msg .= sprintf(' Audio file exported as %s.', $exportedAudioFilename);
        }

        $result = [
            'success' => true,
            'message' => $msg,
            'csv_path' => $csvPath,
            'csv_filename' => $csvFilename,
            'daily_logs' => $writtenLogs,
            'total_plays' => count($csvRows),
            'active_days' => count($writtenLogs),
        ];
        if ($exportedAudioPath !== null) {
            $result['audio_export_path'] = $exportedAudioPath;
            $result['audio_filename'] = $exportedAudioFilename;
        }
        return $result;
    }

    /**
     * Locate an uploaded MP3 / audio file for an RT_RadioCampaigns record in either
     * SuiteCRM 8 private_documents_media_objects or legacy upload/ storage.
     *
     * @return array{source_path:string, original_name:string, mime_type:string}|null
     */
    public static function findCampaignAudioFile(
        mixed $db,
        string $campaignId,
        string $fallbackFilename = ''
    ): ?array {
        $campaignId = trim($campaignId);
        if ($campaignId === '') {
            return null;
        }

        if ($db === null && isset($GLOBALS['db']) && is_object($GLOBALS['db'])) {
            $db = $GLOBALS['db'];
        }

        // 1. Check SuiteCRM 8 private_documents_media_objects table
        if (is_object($db) && method_exists($db, 'query')) {
            $tblCheck = $db->query("SHOW TABLES LIKE 'private_documents_media_objects'", false);
            if ($tblCheck && $db->fetchByAssoc($tblCheck)) {
                $cIdEsc = $db->quote($campaignId);
                $res = $db->query(
                    "SELECT id, file_path, original_name, mime_type, date_entered
                     FROM private_documents_media_objects
                     WHERE parent_type = 'RT_RadioCampaigns'
                       AND parent_id = '{$cIdEsc}'
                       AND deleted = 0
                     ORDER BY date_entered DESC
                     LIMIT 1",
                    false
                );
                $row = $res ? $db->fetchByAssoc($res) : null;
                if ($row && !empty($row['file_path'])) {
                    $filePath = basename((string) $row['file_path']);
                    $candidates = [];
                    if (!empty($row['date_entered']) && preg_match('/^(\d{4})-(\d{2})/', (string) $row['date_entered'], $m)) {
                        $candidates[] = "/var/www/html/media/documents/{$m[1]}/{$m[2]}/{$filePath}";
                    }
                    $candidates[] = "/var/www/html/media/documents/{$filePath}";
                    $globMatches = glob("/var/www/html/media/documents/*/*/{$filePath}") ?: [];
                    foreach ($globMatches as $gm) {
                        $candidates[] = $gm;
                    }
                    foreach ($candidates as $cand) {
                        if (is_file($cand) && is_readable($cand)) {
                            return [
                                'source_path' => $cand,
                                'original_name' => (string) ($row['original_name'] ?: ($fallbackFilename ?: 'advert.mp3')),
                                'mime_type' => (string) ($row['mime_type'] ?: 'audio/mpeg'),
                            ];
                        }
                    }
                }
            }
        }

        // 2. Check legacy upload directory (/var/www/html/public/legacy/upload/<campaignId>)
        $legacyCandidates = [
            "/var/www/html/public/legacy/upload/{$campaignId}",
            "upload/{$campaignId}",
        ];
        foreach ($legacyCandidates as $cand) {
            if (is_file($cand) && is_readable($cand)) {
                return [
                    'source_path' => realpath($cand) ?: $cand,
                    'original_name' => $fallbackFilename !== '' ? $fallbackFilename : "campaign_{$campaignId}.mp3",
                    'mime_type' => 'audio/mpeg',
                ];
            }
        }

        return null;
    }

    /**
     * Get duration multiplier for rate card calculations.
     */
    public static function getDurationMultiplier(?string $duration): float
    {
        $d = trim((string) ($duration ?? ''));
        if (preg_match('/(\d+)/', $d, $m)) {
            $key = $m[1] . ' sec';
            if (isset(self::DURATION_MULTIPLIERS[$key])) {
                return self::DURATION_MULTIPLIERS[$key];
            }
        }
        return self::DURATION_MULTIPLIERS[$d] ?? 1.00;
    }

    /**
     * Get definitive radio rate card package definitions (Options 1 to 4).
     *
     * @return array<string, array{key:string, name:string, type:string, total_spots:int, rate:float, description:string, prime_slots:array<string, int>}>
     */
    public static function getPackageDefinitions(): array
    {
        return [
            'option_1' => [
                'key' => 'option_1',
                'name' => 'Option 1: 50 Prime Time Recorded Ads',
                'type' => 'weekly',
                'total_spots' => 50,
                'rate' => 5999.00,
                'description' => 'Weekly package: 50 Prime Time Recorded Ads (06:00-09:00, 12:00-15:00, 15:00-18:00)',
                'prime_slots' => [
                    'mon_morning_buzz_0700_0800_c' => 2, 'mon_midday_pulse_1200_1300_c' => 2, 'mon_cruise_control_1600_1700_c' => 2, 'mon_cruise_control_1700_1800_c' => 4,
                    'tue_morning_buzz_0700_0800_c' => 2, 'tue_midday_pulse_1200_1300_c' => 2, 'tue_cruise_control_1600_1700_c' => 2, 'tue_cruise_control_1700_1800_c' => 4,
                    'wed_morning_buzz_0700_0800_c' => 2, 'wed_midday_pulse_1200_1300_c' => 2, 'wed_cruise_control_1600_1700_c' => 2, 'wed_cruise_control_1700_1800_c' => 4,
                    'thu_morning_buzz_0700_0800_c' => 2, 'thu_midday_pulse_1200_1300_c' => 2, 'thu_cruise_control_1600_1700_c' => 2, 'thu_cruise_control_1700_1800_c' => 4,
                    'fri_morning_buzz_0700_0800_c' => 2, 'fri_midday_pulse_1200_1300_c' => 2, 'fri_cruise_control_1600_1700_c' => 2, 'fri_cruise_control_1700_1800_c' => 4,
                ],
            ],
            'option_2' => [
                'key' => 'option_2',
                'name' => 'Option 2: 50 Prime Time Live Reads',
                'type' => 'weekly',
                'total_spots' => 50,
                'rate' => 6999.00,
                'description' => 'Weekly package: 50 Prime Time Live Reads',
                'prime_slots' => [
                    'mon_morning_buzz_0700_0800_c' => 2, 'mon_midday_pulse_1200_1300_c' => 2, 'mon_cruise_control_1600_1700_c' => 2, 'mon_cruise_control_1700_1800_c' => 4,
                    'tue_morning_buzz_0700_0800_c' => 2, 'tue_midday_pulse_1200_1300_c' => 2, 'tue_cruise_control_1600_1700_c' => 2, 'tue_cruise_control_1700_1800_c' => 4,
                    'wed_morning_buzz_0700_0800_c' => 2, 'wed_midday_pulse_1200_1300_c' => 2, 'wed_cruise_control_1600_1700_c' => 2, 'wed_cruise_control_1700_1800_c' => 4,
                    'thu_morning_buzz_0700_0800_c' => 2, 'thu_midday_pulse_1200_1300_c' => 2, 'thu_cruise_control_1600_1700_c' => 2, 'thu_cruise_control_1700_1800_c' => 4,
                    'fri_morning_buzz_0700_0800_c' => 2, 'fri_midday_pulse_1200_1300_c' => 2, 'fri_cruise_control_1600_1700_c' => 2, 'fri_cruise_control_1700_1800_c' => 4,
                ],
            ],
            'option_3' => [
                'key' => 'option_3',
                'name' => 'Option 3: 120 Prime Time Recorded Ads (30 x 4 weeks)',
                'type' => 'monthly',
                'total_spots' => 120,
                'rate' => 15999.00,
                'description' => 'Monthly package: 120 Prime Time Recorded Ads across 4 weeks',
                'prime_slots' => [
                    'mon_morning_buzz_0700_0800_c' => 2, 'mon_midday_pulse_1200_1300_c' => 2, 'mon_cruise_control_1600_1700_c' => 2,
                    'tue_morning_buzz_0700_0800_c' => 2, 'tue_midday_pulse_1200_1300_c' => 2, 'tue_cruise_control_1600_1700_c' => 2,
                    'wed_morning_buzz_0700_0800_c' => 2, 'wed_midday_pulse_1200_1300_c' => 2, 'wed_cruise_control_1600_1700_c' => 2,
                    'thu_morning_buzz_0700_0800_c' => 2, 'thu_midday_pulse_1200_1300_c' => 2, 'thu_cruise_control_1600_1700_c' => 2,
                    'fri_morning_buzz_0700_0800_c' => 2, 'fri_midday_pulse_1200_1300_c' => 2, 'fri_cruise_control_1600_1700_c' => 2,
                ],
            ],
            'option_4' => [
                'key' => 'option_4',
                'name' => 'Option 4: 120 Prime Time Live Reads (30 x 4 weeks)',
                'type' => 'monthly',
                'total_spots' => 120,
                'rate' => 16999.00,
                'description' => 'Monthly package: 120 Prime Time Live Reads across 4 weeks',
                'prime_slots' => [
                    'mon_morning_buzz_0700_0800_c' => 2, 'mon_midday_pulse_1200_1300_c' => 2, 'mon_cruise_control_1600_1700_c' => 2,
                    'tue_morning_buzz_0700_0800_c' => 2, 'tue_midday_pulse_1200_1300_c' => 2, 'tue_cruise_control_1600_1700_c' => 2,
                    'wed_morning_buzz_0700_0800_c' => 2, 'wed_midday_pulse_1200_1300_c' => 2, 'wed_cruise_control_1600_1700_c' => 2,
                    'thu_morning_buzz_0700_0800_c' => 2, 'thu_midday_pulse_1200_1300_c' => 2, 'thu_cruise_control_1600_1700_c' => 2,
                    'fri_morning_buzz_0700_0800_c' => 2, 'fri_midday_pulse_1200_1300_c' => 2, 'fri_cruise_control_1600_1700_c' => 2,
                ],
            ],
        ];
    }

    /**
     * Check broadcast capacity across all active campaigns for the requested schedule.
     * Enforces MAX_HOURLY_CAPACITY (20 spots / 10 minutes per broadcast hour).
     *
     * @param array<string, mixed> $requestedSchedule
     * @return array{valid:bool, max_capacity:int, violations:array<string, mixed>, capacity_matrix:array<string, mixed>}
     */
    public static function checkStationCapacity(
        array $requestedSchedule,
        ?string $startDate,
        ?string $endDate,
        ?string $excludeQuoteId = null,
        ?string $excludeCampaignId = null,
        mixed $db = null
    ): array {
        if ($db === null && isset($GLOBALS['db']) && is_object($GLOBALS['db'])) {
            $db = $GLOBALS['db'];
        }

        $maxCapacity = (int) RadioStationConfigService::getSetting('hourly_capacity', self::MAX_HOURLY_CAPACITY, $db);

        $allFields = self::getAllScheduleFields();
        $matrix = [];
        foreach ($allFields as $f => $m) {
            $req = (int) ($requestedSchedule[$f] ?? 0);
            $matrix[$f] = [
                'field' => $f,
                'label' => $m['slot_label'] . ' (' . $m['hour_label'] . ')',
                'day' => $m['day_label'],
                'booked' => 0,
                'requested' => $req,
                'remaining' => $maxCapacity,
                'status' => 'available',
            ];
        }

        $normStart = self::normalizeDate((string) ($startDate ?? ''));
        $normEnd = self::normalizeDate((string) ($endDate ?? ''));

        if (is_object($db) && method_exists($db, 'query') && $normStart !== null && $normEnd !== null) {
            $startEsc = $db->quote($normStart);
            $endEsc = $db->quote($normEnd);

            $whereExclude = '';
            if ($excludeQuoteId !== null && trim($excludeQuoteId) !== '') {
                $whereExclude .= " AND q.id != '" . $db->quote(trim($excludeQuoteId)) . "'";
            }
            if ($excludeCampaignId !== null && trim($excludeCampaignId) !== '') {
                $whereExclude .= " AND c.id != '" . $db->quote(trim($excludeCampaignId)) . "'";
            }

            $sql = "SELECT qc.*
                    FROM rt_radiocampaigns c
                    JOIN aos_quotes_cstm qc ON qc.id_c = c.linked_quote_id
                    JOIN aos_quotes q ON q.id = qc.id_c
                    WHERE c.deleted = 0
                      AND c.status NOT IN ('Cancelled', 'Archived')
                      AND c.start_date <= '{$endEsc}'
                      AND c.end_date >= '{$startEsc}'
                      {$whereExclude}";
            $res = $db->query($sql, false);
            if ($res) {
                while ($row = $db->fetchByAssoc($res)) {
                    foreach ($allFields as $f => $m) {
                        $bVal = (int) ($row[$f] ?? 0);
                        if ($bVal > 0) {
                            $matrix[$f]['booked'] += $bVal;
                        }
                    }
                }
            }
        }

        $violations = [];
        foreach ($matrix as $f => &$item) {
            $total = $item['booked'] + $item['requested'];
            $item['remaining'] = max(0, $maxCapacity - $item['booked']);
            if ($item['booked'] >= $maxCapacity) {
                $item['status'] = 'full';
            } elseif ($item['booked'] >= ($maxCapacity / 2)) {
                $item['status'] = 'filling';
            } else {
                $item['status'] = 'available';
            }

            if ($total > $maxCapacity) {
                $violations[$f] = [
                    'field' => $f,
                    'label' => $item['label'],
                    'day' => $item['day'],
                    'booked' => $item['booked'],
                    'requested' => $item['requested'],
                    'total' => $total,
                    'limit' => $maxCapacity,
                    'excess' => $total - $maxCapacity,
                ];
            }
        }
        unset($item);

        return [
            'valid' => empty($violations),
            'max_capacity' => $maxCapacity,
            'violations' => $violations,
            'capacity_matrix' => $matrix,
        ];
    }

    /**
     * Check for competitor / industry category conflicts against existing active campaigns.
     *
     * @param array<string, mixed> $requestedSchedule
     * @return list<array<string, mixed>>
     */
    public static function checkCategoryConflicts(
        string $industryCategory,
        array $requestedSchedule,
        ?string $startDate,
        ?string $endDate,
        ?string $excludeCampaignId = null,
        mixed $db = null
    ): array {
        $cat = strtolower(trim($industryCategory));
        if ($cat === '' || $cat === 'other') {
            return [];
        }

        if ($db === null && isset($GLOBALS['db']) && is_object($GLOBALS['db'])) {
            $db = $GLOBALS['db'];
        }

        $normStart = self::normalizeDate((string) ($startDate ?? ''));
        $normEnd = self::normalizeDate((string) ($endDate ?? ''));
        if (!is_object($db) || !method_exists($db, 'query') || $normStart === null || $normEnd === null) {
            return [];
        }

        $startEsc = $db->quote($normStart);
        $endEsc = $db->quote($normEnd);
        $catEsc = $db->quote($cat);

        $exclClause = '';
        if ($excludeCampaignId !== null && trim($excludeCampaignId) !== '') {
            $exclClause = " AND c.id != '" . $db->quote(trim($excludeCampaignId)) . "'";
        }

        $allFields = self::getAllScheduleFields();
        $activeFields = [];
        foreach ($allFields as $f => $m) {
            if (!empty($requestedSchedule[$f]) && (int) $requestedSchedule[$f] > 0) {
                $activeFields[$f] = $m;
            }
        }
        if (empty($activeFields)) {
            return [];
        }

        $sql = "SELECT c.id, c.name, c.industry_category, qc.*
                FROM rt_radiocampaigns c
                JOIN aos_quotes_cstm qc ON qc.id_c = c.linked_quote_id
                WHERE c.deleted = 0
                  AND c.status NOT IN ('Cancelled', 'Archived')
                  AND c.industry_category = '{$catEsc}'
                  AND c.start_date <= '{$endEsc}'
                  AND c.end_date >= '{$startEsc}'
                  {$exclClause}";
        $res = $db->query($sql, false);
        $conflicts = [];
        if ($res) {
            while ($row = $db->fetchByAssoc($res)) {
                $cName = (string) ($row['name'] ?? 'Competing Campaign');
                $cId = (string) ($row['id'] ?? '');
                foreach ($activeFields as $f => $m) {
                    $compSpots = (int) ($row[$f] ?? 0);
                    if ($compSpots > 0) {
                        $conflicts[] = [
                            'campaign_id' => $cId,
                            'campaign_name' => $cName,
                            'category' => $cat,
                            'field' => $f,
                            'slot_label' => $m['slot_label'],
                            'day' => $m['day_label'],
                            'hour' => $m['hour_label'],
                            'competing_spots' => $compSpots,
                            'requested_spots' => (int) $requestedSchedule[$f],
                        ];
                    }
                }
            }
        }
        return $conflicts;
    }

    /**
     * Allocate the next available Myriad cart number in the reserved 2000..2999 range.
     */
    public static function allocateNextMyriadCart(
        string $startDate,
        string $endDate,
        mixed $db = null
    ): int {
        if ($db === null && isset($GLOBALS['db']) && is_object($GLOBALS['db'])) {
            $db = $GLOBALS['db'];
        }

        $cartStart = (int) RadioStationConfigService::getSetting('cart_range_start', self::CART_RANGE_START, $db);
        $cartEnd = (int) RadioStationConfigService::getSetting('cart_range_end', self::CART_RANGE_END, $db);

        $startStr = self::normalizeDate($startDate) ?? date('Y-m-d');
        $endStr = self::normalizeDate($endDate) ?? date('Y-m-d', strtotime('+30 days'));

        $usedCarts = [];
        if (is_object($db) && method_exists($db, 'query')) {
            $startEsc = $db->quote($startStr);
            $endEsc = $db->quote($endStr);
            $res = $db->query(
                "SELECT DISTINCT myriad_cart_number
                 FROM rt_radiocampaigns
                 WHERE deleted = 0
                   AND myriad_cart_number IS NOT NULL
                   AND myriad_cart_number != ''
                   AND status NOT IN ('Cancelled', 'Archived')
                   AND start_date <= '{$endEsc}'
                   AND end_date >= '{$startEsc}'",
                false
            );
            if ($res) {
                while ($row = $db->fetchByAssoc($res)) {
                    $num = (int) ($row['myriad_cart_number'] ?? 0);
                    if ($num >= $cartStart && $num <= $cartEnd) {
                        $usedCarts[$num] = true;
                    }
                }
            }
        }

        for ($cart = $cartStart; $cart <= $cartEnd; $cart++) {
            if (!isset($usedCarts[$cart])) {
                return $cart;
            }
        }

        return $cartStart;
    }

    /**
     * Ingest and reconcile a Myriad playout .asplayed or .log file into rt_radiocampaign_plays.
     *
     * @return array{success:bool, message:string, plays_reconciled?:int, campaigns_updated?:int}
     */
    public static function reconcileMyriadLogFile(string $filePath, mixed $db = null): array
    {
        if (!is_file($filePath) || !is_readable($filePath)) {
            return ['success' => false, 'message' => "Reconciliation file not readable: {$filePath}"];
        }

        if ($db === null && isset($GLOBALS['db']) && is_object($GLOBALS['db'])) {
            $db = $GLOBALS['db'];
        }
        if (!is_object($db) || !method_exists($db, 'query')) {
            return ['success' => false, 'message' => 'Database connection required.'];
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false || empty($lines)) {
            return ['success' => false, 'message' => "Log file is empty: {$filePath}"];
        }

        $filename = basename($filePath);
        $defaultDate = null;
        if (preg_match('/(\d{4})(\d{2})(\d{2})/', $filename, $m)) {
            $defaultDate = "{$m[1]}-{$m[2]}-{$m[3]}";
        }

        $reconciledPlays = 0;
        $matchedCampaigns = [];
        $nowUtc = gmdate('Y-m-d H:i:s');

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $matched = false;
            $playTime = null;
            $cart = null;
            $durStr = '30 sec';

            if (preg_match('/^\|\s*\|(\d{2}:\d{2}:\d{2})\|([^|]+)\|([^|]+)\|AUDIO\|([^|]*)\|/i', $line, $lm)) {
                $playTime = $lm[1];
                $cart = trim($lm[2]);
                $durStr = trim($lm[3]);
                $matched = true;
            } elseif (preg_match('/^(\d{4}-\d{2}-\d{2})\s+(\d{2}:\d{2}:\d{2})[,|]([^,|]+)/', $line, $lm)) {
                $defaultDate = $lm[1];
                $playTime = $lm[2];
                $cart = trim($lm[3]);
                $matched = true;
            }

            if (!$matched || empty($cart)) {
                continue;
            }

            $dateStr = $defaultDate ?? date('Y-m-d');
            $fullTimestamp = "{$dateStr} {$playTime}";

            $cartEsc = $db->quote($cart);
            $dateEsc = $db->quote($dateStr);
            $campRes = $db->query(
                "SELECT id, name, number_of_spots, start_date, end_date
                 FROM rt_radiocampaigns
                 WHERE myriad_cart_number = '{$cartEsc}'
                   AND deleted = 0
                   AND start_date <= '{$dateEsc}'
                   AND end_date >= '{$dateEsc}'
                 LIMIT 1",
                false
            );
            $camp = $campRes ? $db->fetchByAssoc($campRes) : null;
            if (!$camp || empty($camp['id'])) {
                continue;
            }

            $campId = (string) $camp['id'];
            $campIdEsc = $db->quote($campId);

            $timeEsc = $db->quote($fullTimestamp);
            $dupChk = $db->query(
                "SELECT id FROM rt_radiocampaign_plays
                 WHERE campaign_id = '{$campIdEsc}'
                   AND actual_time = '{$timeEsc}'
                   AND deleted = 0 LIMIT 1",
                false
            );
            if ($dupChk && $db->fetchByAssoc($dupChk)) {
                continue;
            }

            $playId = self::generateGuid();
            $durEsc = $db->quote($durStr);
            $srcEsc = $db->quote($filename);
            $db->query(
                "INSERT INTO rt_radiocampaign_plays (
                    id, campaign_id, scheduled_time, actual_time, cart_number,
                    status, spot_length, log_source, date_entered, deleted
                ) VALUES (
                    '{$playId}', '{$campIdEsc}', '{$timeEsc}', '{$timeEsc}', '{$cartEsc}',
                    'Aired', '{$durEsc}', '{$srcEsc}', '{$nowUtc}', 0
                )",
                false
            );

            $reconciledPlays++;
            $matchedCampaigns[$campId] = (int) ($matchedCampaigns[$campId] ?? 0) + 1;
        }

        foreach ($matchedCampaigns as $cId => $newCount) {
            $cIdEsc = $db->quote($cId);
            $cntRes = $db->query(
                "SELECT COUNT(*) AS total_aired FROM rt_radiocampaign_plays
                 WHERE campaign_id = '{$cIdEsc}' AND status = 'Aired' AND deleted = 0",
                false
            );
            $cntRow = $cntRes ? $db->fetchByAssoc($cntRes) : null;
            $totalAired = (int) ($cntRow['total_aired'] ?? 0);

            $cMetaRes = $db->query("SELECT number_of_spots, end_date FROM rt_radiocampaigns WHERE id = '{$cIdEsc}'", false);
            $cMeta = $cMetaRes ? $db->fetchByAssoc($cMetaRes) : null;
            $schedSpots = (int) ($cMeta['number_of_spots'] ?? 0);
            $endDate = (string) ($cMeta['end_date'] ?? '');

            $missed = max(0, $schedSpots - $totalAired);
            $status = 'Pending';
            if ($totalAired >= $schedSpots && $schedSpots > 0) {
                $status = 'Fully Reconciled';
                $missed = 0;
            } elseif ($endDate !== '' && $endDate < date('Y-m-d') && $totalAired < $schedSpots) {
                $status = 'Discrepancy';
            }

            $db->query(
                "UPDATE rt_radiocampaigns
                 SET reconciled_spots = {$totalAired},
                     missed_spots = {$missed},
                     reconciliation_status = '{$status}',
                     date_modified = '{$nowUtc}'
                 WHERE id = '{$cIdEsc}'",
                false
            );
        }

        return [
            'success' => true,
            'message' => sprintf('Reconciled %d spot play(s) across %d campaign(s) from %s.', $reconciledPlays, count($matchedCampaigns), $filename),
            'plays_reconciled' => $reconciledPlays,
            'campaigns_updated' => count($matchedCampaigns),
        ];
    }

    /**
     * Generate HTML for Broadcast Affidavit / Proof of Performance document.
     *
     * @return array{success:bool, message?:string, html?:string, verified_plays?:int, scheduled_spots?:int}
     */
    public static function generateBroadcastAffidavitHtml(string $campaignId, mixed $db = null): array
    {
        $campaignId = trim($campaignId);
        if ($campaignId === '') {
            return ['success' => false, 'message' => 'Campaign ID is required.'];
        }

        if ($db === null && isset($GLOBALS['db']) && is_object($GLOBALS['db'])) {
            $db = $GLOBALS['db'];
        }
        if (!is_object($db) || !method_exists($db, 'query')) {
            return ['success' => false, 'message' => 'Database connection required.'];
        }

        $cIdEsc = $db->quote($campaignId);
        $campRes = $db->query(
            "SELECT c.*, a.name AS account_name
             FROM rt_radiocampaigns c
             LEFT JOIN aos_quotes q ON q.id = c.linked_quote_id
             LEFT JOIN accounts a ON a.id = q.billing_account_id
             WHERE c.id = '{$cIdEsc}' AND c.deleted = 0
             LIMIT 1",
            false
        );
        $camp = $campRes ? $db->fetchByAssoc($campRes) : null;
        if (!$camp) {
            return ['success' => false, 'message' => 'Campaign record not found.'];
        }

        $playsRes = $db->query(
            "SELECT actual_time, cart_number, spot_length, log_source
             FROM rt_radiocampaign_plays
             WHERE campaign_id = '{$cIdEsc}' AND status = 'Aired' AND deleted = 0
             ORDER BY actual_time ASC",
            false
        );
        $plays = [];
        if ($playsRes) {
            while ($p = $db->fetchByAssoc($playsRes)) {
                $plays[] = $p;
            }
        }

        $stationSettings = RadioStationConfigService::getSettings($db);
        $stName = htmlspecialchars((string) ($stationSettings['station_name'] ?? 'Community Radio FM'), ENT_QUOTES);
        $stTagline = htmlspecialchars((string) ($stationSettings['station_tagline'] ?? 'The Voice of the Community'), ENT_QUOTES);
        $stAddress = htmlspecialchars((string) ($stationSettings['studio_address'] ?? 'Broadcast House, Studio 1'), ENT_QUOTES);
        $stManager = htmlspecialchars((string) ($stationSettings['station_manager_name'] ?? 'Station Manager / Programme Director'), ENT_QUOTES);

        $customLogoUrl = (string) ($stationSettings['station_logo_url'] ?? '');
        $logoPath = '/var/www/html/public/legacy/' . ltrim($customLogoUrl, '/');
        if (!file_exists($logoPath) || empty($customLogoUrl)) {
            $logoPath = '/var/www/html/public/legacy/custom/include/RadioCRM/radio_station_logo.png';
        }
        $logoHtml = file_exists($logoPath)
            ? '<img src="' . $logoPath . '" width="60" height="60" style="vertical-align:middle;" />'
            : '';

        $campName = htmlspecialchars((string) $camp['name'], ENT_QUOTES);
        $acctName = htmlspecialchars((string) ($camp['account_name'] ?: 'Valued Client'), ENT_QUOTES);
        $cartNo = htmlspecialchars((string) ($camp['myriad_cart_number'] ?: 'N/A'), ENT_QUOTES);
        $startDate = htmlspecialchars((string) $camp['start_date'], ENT_QUOTES);
        $endDate = htmlspecialchars((string) $camp['end_date'], ENT_QUOTES);
        $scheduledSpots = (int) ($camp['number_of_spots'] ?? 0);
        $verifiedCount = count($plays);
        $nowDate = date('d F Y');

        $rowsHtml = '';
        if (empty($plays)) {
            $rowsHtml = '<tr><td colspan="4" style="text-align:center; padding: 12px; color: #666;">No reconciled broadcast logs on record for this campaign yet.</td></tr>';
        } else {
            foreach ($plays as $i => $pl) {
                $num = $i + 1;
                $t = htmlspecialchars((string) $pl['actual_time'], ENT_QUOTES);
                $l = htmlspecialchars((string) $pl['spot_length'], ENT_QUOTES);
                $src = htmlspecialchars((string) $pl['log_source'], ENT_QUOTES);
                $rowsHtml .= "<tr style='border-bottom: 1px solid #ddd;'>"
                    . "<td style='padding: 6px; text-align: center;'>{$num}</td>"
                    . "<td style='padding: 6px; font-family: monospace;'>{$t}</td>"
                    . "<td style='padding: 6px; text-align: center;'>{$l}</td>"
                    . "<td style='padding: 6px; font-size: 10px; color: #555;'>{$src}</td>"
                    . "</tr>";
            }
        }

        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Broadcast Affidavit - {$campName}</title>
<style>
body { font-family: Arial, sans-serif; font-size: 12px; color: #222; margin: 20px; }
table { width: 100%; border-collapse: collapse; margin-top: 10px; }
th { background: #5E2B83; color: #fff; padding: 8px; text-align: left; }
td { padding: 6px; }
.header { border-bottom: 3px solid #5E2B83; padding-bottom: 10px; }
.meta-box { background: #f7f5fa; border: 1px solid #d4c7e6; padding: 12px; border-radius: 4px; margin-top: 15px; }
.cert { margin-top: 25px; padding: 12px; background: #eef8f9; border-left: 4px solid #1B8FA3; font-style: italic; }
</style>
</head>
<body>
<div class="header">
    <table border="0">
        <tr>
            <td width="15%">{$logoHtml}</td>
            <td width="55%">
                <h2 style="margin:0; color:#5E2B83;">{$stName}</h2>
                <div style="color:#666; font-size:11px;">{$stAddress}<br>{$stTagline}</div>
            </td>
            <td width="30%" align="right">
                <h3 style="margin:0; color:#1B8FA3;">BROADCAST AFFIDAVIT</h3>
                <div style="font-size:11px; color:#555;">Proof of Performance<br>Issued: {$nowDate}</div>
            </td>
        </tr>
    </table>
</div>

<div class="meta-box">
    <table border="0">
        <tr>
            <td width="20%"><strong>Advertiser:</strong></td>
            <td width="30%">{$acctName}</td>
            <td width="20%"><strong>Campaign:</strong></td>
            <td width="30%">{$campName}</td>
        </tr>
        <tr>
            <td><strong>Myriad Cart Number:</strong></td>
            <td>{$cartNo}</td>
            <td><strong>Broadcast Flight:</strong></td>
            <td>{$startDate} to {$endDate}</td>
        </tr>
        <tr>
            <td><strong>Contracted Spots:</strong></td>
            <td>{$scheduledSpots}</td>
            <td><strong>Certified Aired Plays:</strong></td>
            <td><strong style="color:#5E2B83;">{$verifiedCount}</strong></td>
        </tr>
    </table>
</div>

<div class="cert">
    This document serves as the official Broadcast Affidavit and Proof of Performance certifying that the commercial advertisement referenced above was broadcast on {$stName} as scheduled on the dates and times verified below by our Myriad automated broadcast playout logs.
</div>

<h4 style="color:#5E2B83; margin-top:20px; margin-bottom:5px;">Verified Playout Audit Log</h4>
<table border="1" bordercolor="#ccc" cellpadding="4" cellspacing="0">
    <thead>
        <tr>
            <th width="8%" style="text-align:center;">#</th>
            <th width="35%">Broadcast Timestamp</th>
            <th width="20%" style="text-align:center;">Spot Duration</th>
            <th width="37%">Automation Source Log</th>
        </tr>
    </thead>
    <tbody>
        {$rowsHtml}
    </tbody>
</table>

<table width="100%" style="margin-top:50px;" border="0">
    <tr>
        <td width="45%" style="border-top: 1px solid #333; text-align: center;">
            <strong>{$stManager}</strong><br>
            {$stName} Broadcaster
        </td>
        <td width="10%">&nbsp;</td>
        <td width="45%" style="border-top: 1px solid #333; text-align: center;">
            <strong>Commercial Traffic / Operations Manager</strong><br>
            {$stName} Traffic Department
        </td>
    </tr>
</table>
</body>
</html>
HTML;

        return [
            'success' => true,
            'campaign_id' => $campaignId,
            'campaign_name' => $camp['name'],
            'html' => $html,
            'verified_plays' => $verifiedCount,
            'scheduled_spots' => $scheduledSpots,
        ];
    }

    /**
     * Generate South African ICASA and SAMRO monthly commercial airtime compliance report.
     *
     * @return array{success:bool, year:int, month:int, total_plays:int, total_minutes:float, avg_minutes_per_hour:float, is_icasa_compliant:bool, csv:string}
     */
    public static function generateIcasaComplianceReport(int $year, int $month, mixed $db = null): array
    {
        if ($db === null && isset($GLOBALS['db']) && is_object($GLOBALS['db'])) {
            $db = $GLOBALS['db'];
        }
        if (!is_object($db) || !method_exists($db, 'query')) {
            return ['success' => false, 'message' => 'Database connection required.'];
        }

        $startDate = sprintf('%04d-%02d-01 00:00:00', $year, $month);
        $lastDay = (int) date('t', strtotime("{$year}-{$month}-01"));
        $endDate = sprintf('%04d-%02d-%02d 23:59:59', $year, $month, $lastDay);

        $startEsc = $db->quote($startDate);
        $endEsc = $db->quote($endDate);

        $sql = "SELECT p.actual_time, p.spot_length, p.cart_number, c.name AS campaign_name, c.industry_category
                FROM rt_radiocampaign_plays p
                JOIN rt_radiocampaigns c ON c.id = p.campaign_id
                WHERE p.actual_time >= '{$startEsc}'
                  AND p.actual_time <= '{$endEsc}'
                  AND p.status = 'Aired'
                  AND p.deleted = 0
                ORDER BY p.actual_time ASC";
        $res = $db->query($sql, false);

        $totalPlays = 0;
        $totalSeconds = 0;
        $byCategory = [];
        $byDaypart = [
            'morning_buzz' => 0,
            'daytime' => 0,
            'cruise_control' => 0,
            'evening_night' => 0,
        ];

        $csvRows = ["Actual Timestamp,Cart Number,Duration (Sec),Campaign,Industry Category"];

        if ($res) {
            while ($row = $db->fetchByAssoc($res)) {
                $totalPlays++;
                $durInfo = self::formatSpotLength((string) $row['spot_length']);
                $secs = (int) $durInfo['seconds'];
                $totalSeconds += $secs;

                $cat = (string) ($row['industry_category'] ?: 'other');
                $byCategory[$cat] = ($byCategory[$cat] ?? 0) + 1;

                $hour = (int) date('H', strtotime((string) $row['actual_time']));
                if ($hour >= 6 && $hour < 9) {
                    $byDaypart['morning_buzz']++;
                } elseif ($hour >= 9 && $hour < 15) {
                    $byDaypart['daytime']++;
                } elseif ($hour >= 15 && $hour < 18) {
                    $byDaypart['cruise_control']++;
                } else {
                    $byDaypart['evening_night']++;
                }

                $csvRows[] = sprintf(
                    '"%s","%s",%d,"%s","%s"',
                    $row['actual_time'],
                    $row['cart_number'],
                    $secs,
                    str_replace('"', '""', (string) $row['campaign_name']),
                    $cat
                );
            }
        }

        $totalMinutes = round($totalSeconds / 60, 2);
        $totalBroadcastHours = 24 * $lastDay;
        $avgMinutesPerHour = $totalBroadcastHours > 0 ? round($totalMinutes / $totalBroadcastHours, 2) : 0.0;
        $icasaLimit = (float) RadioStationConfigService::getSetting('max_ad_minutes_per_hour', 10.0, $db);

        return [
            'success' => true,
            'year' => $year,
            'month' => $month,
            'days_in_month' => $lastDay,
            'total_plays' => $totalPlays,
            'total_minutes' => $totalMinutes,
            'avg_minutes_per_hour' => $avgMinutesPerHour,
            'icasa_limit_minutes_per_hour' => $icasaLimit,
            'is_icasa_compliant' => ($avgMinutesPerHour <= $icasaLimit),
            'by_category' => $byCategory,
            'by_daypart' => $byDaypart,
            'csv' => implode("\r\n", $csvRows) . "\r\n",
        ];
    }

    /**
     * Generate a standard 36-char GUID / UUID v4.
     */
    public static function generateGuid(): string
    {
        if (function_exists('create_guid')) {
            return (string) \create_guid();
        }
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}

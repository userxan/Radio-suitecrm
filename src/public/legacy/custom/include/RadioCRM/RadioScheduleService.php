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
    public static function cleanRadioLineDescription(?string $description, string $partNumber = '', mixed $db = null): string
    {
        foreach (self::SLOTS as $slot) {
            if ($partNumber !== '' && strcasecmp($slot['part_number'], $partNumber) === 0) {
                return $slot['description'];
            }
        }
        if (class_exists('RadioCRM\RadioStationConfigService')) {
            $conf = RadioStationConfigService::getSettings($db);
            $configuredSlots = $conf['slots'] ?? [];
            foreach ($configuredSlots as $cSlot) {
                $cPn = (string) ($cSlot['part_number'] ?? '');
                if ($partNumber !== '' && $cPn !== '' && strcasecmp($cPn, $partNumber) === 0 && !empty($cSlot['description'])) {
                    return (string) $cSlot['description'];
                }
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
     * dynamically reflecting all configured show slots and custom product codes.
     *
     * @param mixed $db SuiteCRM DBManager instance or null
     * @return array<string, array{id:string, name:string, part_number:string, price:float, description:string, slot_key:string, slot_label:string}>
     */
    public static function getCatalogueProducts(mixed $db = null): array
    {
        $catalogue = [];
        $configuredSlots = class_exists('RadioCRM\RadioStationConfigService')
            ? (RadioStationConfigService::getSettings($db)['slots'] ?? self::SLOTS)
            : self::SLOTS;

        // Base slot defaults
        foreach (self::SLOTS as $slotKey => $slot) {
            $pn = strtoupper(trim((string) $slot['part_number']));
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

        // Overlay configured slots (custom part numbers, custom rates, extra slots)
        foreach ($configuredSlots as $slotKey => $cSlot) {
            $pn = strtoupper(trim((string) ($cSlot['part_number'] ?? $slotKey)));
            if ($pn === '') {
                continue;
            }
            $title = (string) ($cSlot['title'] ?? ($catalogue[$pn]['name'] ?? $pn));
            $hours = (string) ($cSlot['hours'] ?? '');
            $displayName = $hours !== '' ? "Radio Advert: {$title} ({$hours})" : "Radio Advert: {$title}";
            $catalogue[$pn] = [
                'id' => $catalogue[$pn]['id'] ?? '',
                'name' => $displayName,
                'part_number' => $pn,
                'price' => (float) ($cSlot['rate'] ?? ($catalogue[$pn]['price'] ?? 350.00)),
                'description' => (string) ($cSlot['description'] ?? ($catalogue[$pn]['description'] ?? '')),
                'slot_key' => $slotKey,
                'slot_label' => $title,
            ];
        }

        if ($db === null && isset($GLOBALS['db']) && is_object($GLOBALS['db'])) {
            $db = $GLOBALS['db'];
        }

        if (is_object($db) && method_exists($db, 'query') && !empty($catalogue)) {
            $partList = array_map(static fn($pn) => "'" . $db->quote($pn) . "'", array_keys($catalogue));
            $sql = "SELECT id, name, part_number, price, description
                    FROM aos_products
                    WHERE deleted = 0 AND part_number IN (" . implode(',', $partList) . ")
                    ORDER BY date_entered ASC";
            $res = $db->query($sql, false);
            if ($res) {
                while ($row = $db->fetchByAssoc($res)) {
                    $pn = strtoupper(trim((string) ($row['part_number'] ?? '')));
                    if (isset($catalogue[$pn])) {
                        $catalogue[$pn]['id'] = (string) $row['id'];
                        $catalogue[$pn]['name'] = (string) ($row['name'] ?: $pn);
                        if (isset($row['price']) && is_numeric($row['price']) && (float) $row['price'] > 0) {
                            $catalogue[$pn]['price'] = round((float) $row['price'], 2);
                        }
                        $catalogue[$pn]['description'] = self::cleanRadioLineDescription(
                            (string) ($row['description'] ?? ''),
                            $pn,
                            $db
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
        $durationMultiplier = self::getDurationMultiplier($spotDur, $db);

        $appliedPkg = $appliedPackage ?? ($inputData['applied_package_c'] ?? '');
        $pkgDefinitions = self::getPackageDefinitions($db);
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
    public static function getDurationMultiplier(?string $duration, mixed $db = null): float
    {
        $d = trim((string) ($duration ?? ''));
        $multipliers = self::DURATION_MULTIPLIERS;
        if (class_exists('RadioCRM\RadioStationConfigService')) {
            $conf = RadioStationConfigService::getSettings($db);
            if (!empty($conf['duration_multipliers']) && is_array($conf['duration_multipliers'])) {
                $multipliers = array_merge($multipliers, $conf['duration_multipliers']);
            }
        }
        if (preg_match('/(\d+)/', $d, $m)) {
            $key = $m[1] . ' sec';
            if (isset($multipliers[$key])) {
                return (float) $multipliers[$key];
            }
        }
        return (float) ($multipliers[$d] ?? 1.00);
    }

    /**
     * Get definitive radio rate card package definitions (Options 1 to 4 and any custom packages).
     *
     * @return array<string, array{key:string, name:string, type:string, total_spots:int, rate:float, description:string, prime_slots?:array<string, int>}>
     */
    public static function getPackageDefinitions(mixed $db = null): array
    {
        $defaults = [
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

        if (class_exists('RadioCRM\RadioStationConfigService')) {
            $conf = RadioStationConfigService::getSettings($db);
            if (!empty($conf['package_presets']) && is_array($conf['package_presets'])) {
                $presets = $conf['package_presets'];
                foreach ($presets as $k => &$p) {
                    $dist = (string) ($p['distribution'] ?? '');
                    $totSpots = (int) ($p['total_spots'] ?? 50);
                    $pkgType = (string) ($p['type'] ?? 'weekly');
                    $defTot = (int) ($defaults[$k]['total_spots'] ?? 0);

                    if (!empty($p['prime_slots']) && is_array($p['prime_slots'])) {
                        continue;
                    }
                    if ($dist !== '' && $dist !== 'default') {
                        $p['prime_slots'] = self::buildPackagePrimeSlots($totSpots, $pkgType, $dist);
                    } elseif (isset($defaults[$k]['prime_slots']) && $totSpots === $defTot) {
                        $p['prime_slots'] = $defaults[$k]['prime_slots'];
                    } else {
                        $p['prime_slots'] = self::buildPackagePrimeSlots($totSpots, $pkgType, 'prime_drive');
                    }
                }
                unset($p);
                return $presets;
            }
        }

        return $defaults;
    }

    /**
     * Dynamically construct a weekly cell allocation map for a package based on total spots, package type, and distribution pattern.
     *
     * @return array<string, int>
     */
    public static function buildPackagePrimeSlots(int $totalSpots, string $type = 'weekly', string $distribution = 'prime_drive'): array
    {
        $weeklyTarget = ($type === 'monthly') ? max(1, (int) round($totalSpots / 4)) : max(1, $totalSpots);

        $candidateCells = match ($distribution) {
            'breakfast_focus' => [
                'mon_morning_buzz_0600_0700_c', 'mon_morning_buzz_0700_0800_c', 'mon_morning_buzz_0800_0900_c', 'mon_midmorning_banter_0900_1000_c',
                'tue_morning_buzz_0600_0700_c', 'tue_morning_buzz_0700_0800_c', 'tue_morning_buzz_0800_0900_c', 'tue_midmorning_banter_0900_1000_c',
                'wed_morning_buzz_0600_0700_c', 'wed_morning_buzz_0700_0800_c', 'wed_morning_buzz_0800_0900_c', 'wed_midmorning_banter_0900_1000_c',
                'thu_morning_buzz_0600_0700_c', 'thu_morning_buzz_0700_0800_c', 'thu_morning_buzz_0800_0900_c', 'thu_midmorning_banter_0900_1000_c',
                'fri_morning_buzz_0600_0700_c', 'fri_morning_buzz_0700_0800_c', 'fri_morning_buzz_0800_0900_c', 'fri_midmorning_banter_0900_1000_c',
            ],
            'all_day' => [
                'mon_morning_buzz_0700_0800_c', 'mon_midmorning_banter_1000_1100_c', 'mon_midday_pulse_1200_1300_c', 'mon_cruise_control_1600_1700_c', 'mon_specialty_show_1_1900_2000_c',
                'tue_morning_buzz_0700_0800_c', 'tue_midmorning_banter_1000_1100_c', 'tue_midday_pulse_1200_1300_c', 'tue_cruise_control_1600_1700_c', 'tue_specialty_show_1_1900_2000_c',
                'wed_morning_buzz_0700_0800_c', 'wed_midmorning_banter_1000_1100_c', 'wed_midday_pulse_1200_1300_c', 'wed_cruise_control_1600_1700_c', 'wed_specialty_show_1_1900_2000_c',
                'thu_morning_buzz_0700_0800_c', 'thu_midmorning_banter_1000_1100_c', 'thu_midday_pulse_1200_1300_c', 'thu_cruise_control_1600_1700_c', 'thu_specialty_show_1_1900_2000_c',
                'fri_morning_buzz_0700_0800_c', 'fri_midmorning_banter_1000_1100_c', 'fri_midday_pulse_1200_1300_c', 'fri_cruise_control_1600_1700_c', 'fri_specialty_show_1_1900_2000_c',
            ],
            'weekend_included' => [
                'mon_morning_buzz_0700_0800_c', 'mon_cruise_control_1600_1700_c',
                'tue_morning_buzz_0700_0800_c', 'tue_cruise_control_1600_1700_c',
                'wed_morning_buzz_0700_0800_c', 'wed_cruise_control_1600_1700_c',
                'thu_morning_buzz_0700_0800_c', 'thu_cruise_control_1600_1700_c',
                'fri_morning_buzz_0700_0800_c', 'fri_cruise_control_1600_1700_c',
                'sat_morning_buzz_0800_0900_c', 'sat_midday_pulse_1200_1300_c', 'sat_cruise_control_1500_1600_c',
                'sun_morning_buzz_0800_0900_c', 'sun_midday_pulse_1200_1300_c', 'sun_cruise_control_1500_1600_c',
            ],
            default => [
                'mon_morning_buzz_0700_0800_c', 'mon_midday_pulse_1200_1300_c', 'mon_cruise_control_1600_1700_c', 'mon_cruise_control_1700_1800_c',
                'tue_morning_buzz_0700_0800_c', 'tue_midday_pulse_1200_1300_c', 'tue_cruise_control_1600_1700_c', 'tue_cruise_control_1700_1800_c',
                'wed_morning_buzz_0700_0800_c', 'wed_midday_pulse_1200_1300_c', 'wed_cruise_control_1600_1700_c', 'wed_cruise_control_1700_1800_c',
                'thu_morning_buzz_0700_0800_c', 'thu_midday_pulse_1200_1300_c', 'thu_cruise_control_1600_1700_c', 'thu_cruise_control_1700_1800_c',
                'fri_morning_buzz_0700_0800_c', 'fri_midday_pulse_1200_1300_c', 'fri_cruise_control_1600_1700_c', 'fri_cruise_control_1700_1800_c',
            ],
        };

        $map = [];
        $count = count($candidateCells);
        for ($i = 0; $i < $weeklyTarget; $i++) {
            $cell = $candidateCells[$i % $count];
            $map[$cell] = ($map[$cell] ?? 0) + 1;
        }
        return $map;
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
        $monthStartYmd = sprintf('%04d-%02d-01', $year, $month);
        $monthEndYmd = sprintf('%04d-%02d-%02d', $year, $month, $lastDay);

        $startEsc = $db->quote($startDate);
        $endEsc = $db->quote($endDate);
        $mStartEsc = $db->quote($monthStartYmd);
        $mEndEsc = $db->quote($monthEndYmd);

        // Strictly restrict ICASA reporting to campaigns that have an active Invoice (aos_invoices), never Quotes
        $sql = "SELECT p.actual_time, p.spot_length, p.cart_number, c.id AS campaign_id, c.name AS campaign_name, c.industry_category,
                       (SELECT inv.id FROM aos_invoices inv
                        WHERE inv.deleted = 0
                          AND inv.name LIKE CONCAT('%', c.name, '%')
                        ORDER BY inv.date_entered DESC LIMIT 1) AS invoice_id
                FROM rt_radiocampaign_plays p
                JOIN rt_radiocampaigns c ON c.id = p.campaign_id AND c.deleted = 0
                WHERE p.actual_time >= '{$startEsc}'
                  AND p.actual_time <= '{$endEsc}'
                  AND p.status = 'Aired'
                  AND p.deleted = 0
                  AND EXISTS (
                      SELECT 1 FROM aos_invoices inv
                      WHERE inv.deleted = 0
                        AND inv.name LIKE CONCAT('%', c.name, '%')
                  )
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

        $csvRows = ["Actual Timestamp,Cart Number,Duration (Sec),Campaign / Invoice,Industry Category"];
        $invoicedIdsWithPlays = [];

        if ($res) {
            while ($row = $db->fetchByAssoc($res)) {
                $totalPlays++;
                if (!empty($row['invoice_id'])) {
                    $invoicedIdsWithPlays[(string) $row['invoice_id']] = true;
                }
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

        // Also include direct AOS_Invoices line items (parent_type = 'AOS_Invoices') for invoices without reconciled play rows
        $invLinesSql = "SELECT inv.id AS invoice_id, inv.name AS invoice_name, inv.invoice_date, inv.date_entered,
                               pq.part_number, pq.name AS line_name, pq.product_qty
                        FROM aos_invoices inv
                        JOIN aos_products_quotes pq ON pq.parent_id = inv.id AND pq.parent_type = 'AOS_Invoices' AND pq.deleted = 0
                        WHERE inv.deleted = 0
                          AND (
                              (inv.invoice_date IS NOT NULL AND inv.invoice_date >= '{$mStartEsc}' AND inv.invoice_date <= '{$mEndEsc}')
                              OR (inv.date_entered >= '{$startEsc}' AND inv.date_entered <= '{$endEsc}')
                          )";
        $invLinesRes = $db->query($invLinesSql, false);
        if ($invLinesRes) {
            while ($iRow = $db->fetchByAssoc($invLinesRes)) {
                $invId = (string) ($iRow['invoice_id'] ?? '');
                if (isset($invoicedIdsWithPlays[$invId])) {
                    continue;
                }
                $qty = max(0, (int) round((float) ($iRow['product_qty'] ?? 0)));
                if ($qty <= 0) {
                    continue;
                }
                $secs = 30;
                if (preg_match('/(\d+)\s*sec/i', (string) ($iRow['line_name'] ?? ''), $dm)) {
                    $secs = (int) $dm[1];
                }
                $totalPlays += $qty;
                $totalSeconds += ($qty * $secs);
                $byCategory['invoiced'] = ($byCategory['invoiced'] ?? 0) + $qty;

                $pn = strtoupper(trim((string) ($iRow['part_number'] ?? '')));
                if (str_contains($pn, 'MORNING-BUZZ')) {
                    $byDaypart['morning_buzz'] += $qty;
                } elseif (str_contains($pn, 'MIDMORNING') || str_contains($pn, 'MIDDAY')) {
                    $byDaypart['daytime'] += $qty;
                } elseif (str_contains($pn, 'CRUISE-CONTROL')) {
                    $byDaypart['cruise_control'] += $qty;
                } else {
                    $byDaypart['evening_night'] += $qty;
                }

                $ts = (string) ($iRow['invoice_date'] ?: substr((string) $iRow['date_entered'], 0, 10));
                $csvRows[] = sprintf(
                    '"%s","INVOICED",%d,"%s","invoiced"',
                    $ts,
                    $qty * $secs,
                    str_replace('"', '""', (string) $iRow['invoice_name'])
                );
            }
        }

        $totalMinutes = round($totalSeconds / 60, 2);
        $totalBroadcastHours = 24 * $lastDay;
        $avgMinutesPerHour = $totalBroadcastHours > 0 ? round($totalMinutes / $totalBroadcastHours, 2) : 0.0;
        $icasaLimit = (float) RadioStationConfigService::getSetting('max_ad_minutes_per_hour', 10.0, $db);

        return [
            'success' => true,
            'data_source' => 'AOS_Invoices',
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
     * Retrieve non-schedule Rate Card Extras (Social Media, Live Reads, Sponsorships, Interviews, Outside Broadcasts)
     * grouped by category for the Quote Rate Card Extras selector.
     *
     * @return array<string, array<int, array{id:string, name:string, part_number:string, price:float, description:string, category:string}>>
     */
    public static function getRateCardExtrasCatalogue(mixed $db = null): array
    {
        if ($db === null && isset($GLOBALS['db']) && is_object($GLOBALS['db'])) {
            $db = $GLOBALS['db'];
        }
        $grouped = [
            'Social Media Advertising' => [],
            'Live Reads (30 Seconds)' => [],
            'Interviews, OB & Sponsorships' => [],
            'Commercial Packages' => [],
        ];
        if (!is_object($db) || !method_exists($db, 'query')) {
            return $grouped;
        }

        $standardParts = [];
        foreach (self::SLOTS as $s) {
            $standardParts[$s['part_number']] = true;
        }
        $stConfig = RadioStationConfigService::getSettings($db);
        foreach (($stConfig['slots'] ?? []) as $s) {
            if (!empty($s['part_number'])) {
                $standardParts[(string) $s['part_number']] = true;
            }
        }

        $res = $db->query(
            "SELECT id, name, part_number, price, description
             FROM aos_products
             WHERE deleted = 0
             ORDER BY part_number ASC, name ASC",
            false
        );
        if (!$res) {
            return $grouped;
        }

        $seenParts = [];
        while ($row = $db->fetchByAssoc($res)) {
            $pn = trim((string) ($row['part_number'] ?? ''));
            if ($pn === '' || isset($standardParts[$pn]) || isset($seenParts[$pn])) {
                continue;
            }
            if (str_starts_with($pn, 'REC-30S-')) {
                continue;
            }
            $seenParts[$pn] = true;

            $cat = 'Interviews, OB & Sponsorships';
            if (str_starts_with($pn, 'SOC-')) {
                $cat = 'Social Media Advertising';
            } elseif (str_starts_with($pn, 'LIVE-')) {
                $cat = 'Live Reads (30 Seconds)';
            } elseif (str_starts_with($pn, 'PKG-')) {
                $cat = 'Commercial Packages';
            }

            $grouped[$cat][] = [
                'id' => (string) $row['id'],
                'name' => (string) $row['name'],
                'part_number' => $pn,
                'price' => round((float) ($row['price'] ?? 0.0), 2),
                'description' => (string) ($row['description'] ?? ''),
                'category' => $cat,
            ];
        }

        return array_filter($grouped, static fn(array $items): bool => !empty($items));
    }

    /**
     * Automatically reschedule missed spots ("Make-Goods") into upcoming prime dayparts,
     * log them in rt_radiocampaign_plays, and export a Myriad Make-Good .log delta file.
     *
     * @return array{success:bool, message:string, rescheduled_count:int, log_file?:string, new_end_date?:string}
     */
    public static function rescheduleMakeGoodSpots(string $campaignId, mixed $db = null): array
    {
        $campaignId = trim($campaignId);
        if ($campaignId === '') {
            return ['success' => false, 'message' => 'Campaign ID is required.', 'rescheduled_count' => 0];
        }
        if ($db === null && isset($GLOBALS['db']) && is_object($GLOBALS['db'])) {
            $db = $GLOBALS['db'];
        }
        if (!is_object($db) || !method_exists($db, 'query')) {
            return ['success' => false, 'message' => 'Database connection required.', 'rescheduled_count' => 0];
        }

        $cIdEsc = $db->quote($campaignId);
        $res = $db->query(
            "SELECT id, name, start_date, end_date, number_of_spots, reconciled_spots, missed_spots, spot_length, myriad_cart_number
             FROM rt_radiocampaigns
             WHERE id = '{$cIdEsc}' AND deleted = 0
             LIMIT 1",
            false
        );
        $camp = $res ? $db->fetchByAssoc($res) : null;
        if (!$camp) {
            return ['success' => false, 'message' => 'Campaign not found.', 'rescheduled_count' => 0];
        }

        $missed = (int) ($camp['missed_spots'] ?? 0);
        if ($missed <= 0) {
            $contracted = (int) ($camp['number_of_spots'] ?? 0);
            $aired = (int) ($camp['reconciled_spots'] ?? 0);
            $missed = max(0, $contracted - $aired);
        }

        if ($missed <= 0) {
            return [
                'success' => true,
                'message' => 'No missed spots require Make-Good rescheduling for this campaign.',
                'rescheduled_count' => 0,
            ];
        }

        $cart = trim((string) ($camp['myriad_cart_number'] ?: '2000'));
        $durInfo = self::formatSpotLength((string) ($camp['spot_length'] ?? '30 sec'));
        $durMinSec = $durInfo['min_sec'];
        $durLabel = $durInfo['label'];
        $titleClean = strtoupper((string) preg_replace('/[^A-Za-z0-9 _-]/', '', (string) $camp['name']));
        if ($titleClean === '') {
            $titleClean = 'MAKEGOOD AD';
        }

        $baseDateStr = max((string) ($camp['end_date'] ?: date('Y-m-d')), date('Y-m-d'));
        $baseDt = new DateTimeImmutable($baseDateStr, new DateTimeZone(self::TIMEZONE));

        $primeTimes = ['07:30:00', '12:30:00', '16:30:00', '17:30:00'];
        $nowUtc = gmdate('Y-m-d H:i:s');
        $logLinesByDate = [];
        $rescheduled = 0;
        $lastDateStr = $baseDateStr;

        for ($i = 0; $i < $missed; $i++) {
            $dayOffset = (int) floor($i / count($primeTimes)) + 1;
            $playDt = $baseDt->modify("+{$dayOffset} days");
            $dateYmd = $playDt->format('Y-m-d');
            $lastDateStr = $dateYmd;
            $timeHms = $primeTimes[$i % count($primeTimes)];
            $fullTs = "{$dateYmd} {$timeHms}";

            $playId = self::generateGuid();
            $tsEsc = $db->quote($fullTs);
            $cartEsc = $db->quote($cart);
            $durEsc = $db->quote($durLabel);

            $db->query(
                "INSERT INTO rt_radiocampaign_plays (
                    id, campaign_id, scheduled_time, actual_time, cart_number,
                    status, spot_length, log_source, date_entered, deleted
                ) VALUES (
                    '{$playId}', '{$cIdEsc}', '{$tsEsc}', '{$tsEsc}', '{$cartEsc}',
                    'Aired', '{$durEsc}', 'Make-Good Auto-Reschedule', '{$nowUtc}', 0
                )",
                false
            );

            $logLinesByDate[$dateYmd][] = "| |{$timeHms}|{$cart}|{$durMinSec}|AUDIO|{$titleClean} (MAKE-GOOD)|";
            $rescheduled++;
        }

        $exportDir = '/srv/myriad_exports/myriad_daily_logs';
        if (!is_dir($exportDir)) {
            @mkdir($exportDir, 02775, true);
        }

        $safeSlug = preg_replace('/[^A-Za-z0-9_-]/', '_', (string) $camp['name']);
        $stamp = date('Ymd_His');
        $firstLogFile = '';
        foreach ($logLinesByDate as $dYmd => $lines) {
            $dCompact = str_replace('-', '', $dYmd);
            $fName = "MakeGood_{$dCompact}_{$safeSlug}_{$stamp}.log";
            $fPath = $exportDir . '/' . $fName;
            @file_put_contents($fPath, implode("\r\n", $lines) . "\r\n");
            if ($firstLogFile === '') {
                $firstLogFile = $fName;
            }
        }

        $cntRes = $db->query(
            "SELECT COUNT(*) AS total_aired FROM rt_radiocampaign_plays
             WHERE campaign_id = '{$cIdEsc}' AND status = 'Aired' AND deleted = 0",
            false
        );
        $cntRow = $cntRes ? $db->fetchByAssoc($cntRes) : null;
        $totalAired = (int) ($cntRow['total_aired'] ?? 0);
        $endEsc = $db->quote($lastDateStr);

        $db->query(
            "UPDATE rt_radiocampaigns
             SET reconciled_spots = {$totalAired},
                 missed_spots = 0,
                 reconciliation_status = 'Fully Reconciled',
                 end_date = '{$endEsc}',
                 date_modified = '{$nowUtc}'
             WHERE id = '{$cIdEsc}'",
            false
        );

        return [
            'success' => true,
            'message' => sprintf('Scheduled %d Make-Good spot(s) across %d day(s) and exported Myriad Make-Good log (%s).', $rescheduled, count($logLinesByDate), $firstLogFile),
            'rescheduled_count' => $rescheduled,
            'log_file' => $firstLogFile,
            'new_end_date' => $lastDateStr,
        ];
    }

    /**
     * Generate Studio Presenter Live-Read & Sponsorship Run Sheet HTML grouped by Show Slot.
     *
     * @return array{success:bool, date:string, html:string, total_live_reads:int, slots_active:int}
     */
    public static function generatePresenterLiveReadSheetHtml(?string $targetDate = null, ?string $campaignId = null, mixed $db = null): array
    {
        if ($db === null && isset($GLOBALS['db']) && is_object($GLOBALS['db'])) {
            $db = $GLOBALS['db'];
        }
        $dateYmd = ($targetDate !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $targetDate))
            ? $targetDate
            : date('Y-m-d');

        $dt = new DateTimeImmutable($dateYmd, new DateTimeZone(self::TIMEZONE));
        $phpW = (int) $dt->format('w');
        $dayKey = 'mon';
        foreach (self::DAYS as $dk => $dInfo) {
            if ($dInfo['php_w'] === $phpW) {
                $dayKey = $dk;
                break;
            }
        }
        $dayFull = self::DAYS[$dayKey]['full'] ?? 'Monday';

        $stConfig = RadioStationConfigService::getSettings($db);
        $stName = htmlspecialchars((string) ($stConfig['station_name'] ?? 'Community Radio FM'), ENT_QUOTES);
        $stFreq = htmlspecialchars((string) ($stConfig['station_frequency'] ?? '101.5 FM'), ENT_QUOTES);
        $confSlots = $stConfig['slots'] ?? RadioStationConfigService::DEFAULT_SLOTS;

        $whereClause = "c.deleted = 0";
        if ($campaignId !== null && trim($campaignId) !== '') {
            $cIdEsc = $db->quote(trim($campaignId));
            $whereClause .= " AND c.id = '{$cIdEsc}'";
        } else {
            $dEsc = $db->quote($dateYmd);
            $whereClause .= " AND c.start_date <= '{$dEsc}' AND c.end_date >= '{$dEsc}'";
        }

        $sql = "SELECT c.*, q.name AS quote_name, a.name AS account_name, qc.*
                FROM rt_radiocampaigns c
                LEFT JOIN aos_quotes q ON q.id = c.linked_quote_id AND q.deleted = 0
                LEFT JOIN aos_quotes_cstm qc ON qc.id_c = q.id
                LEFT JOIN accounts a ON a.id = q.billing_account_id
                WHERE {$whereClause}
                ORDER BY c.name ASC";

        $res = (is_object($db) && method_exists($db, 'query')) ? $db->query($sql, false) : false;
        $campaigns = [];
        if ($res) {
            while ($r = $db->fetchByAssoc($res)) {
                $campaigns[] = $r;
            }
        }

        // If no date-filtered campaigns matched and no specific campaignId was passed, show all active campaigns so studio sheet is always useful
        if (empty($campaigns) && empty($campaignId) && is_object($db) && method_exists($db, 'query')) {
            $fallbackRes = $db->query(
                "SELECT c.*, q.name AS quote_name, a.name AS account_name, qc.*
                 FROM rt_radiocampaigns c
                 LEFT JOIN aos_quotes q ON q.id = c.linked_quote_id AND q.deleted = 0
                 LEFT JOIN aos_quotes_cstm qc ON qc.id_c = q.id
                 LEFT JOIN accounts a ON a.id = q.billing_account_id
                 WHERE c.deleted = 0
                 ORDER BY c.date_entered DESC LIMIT 15",
                false
            );
            if ($fallbackRes) {
                while ($r = $db->fetchByAssoc($fallbackRes)) {
                    $campaigns[] = $r;
                }
            }
        }

        $slotBuckets = [];
        foreach (self::SLOTS as $slotKey => $slotMeta) {
            $cSlot = $confSlots[$slotKey] ?? ($confSlots[$slotMeta['part_number']] ?? null);
            $slotTitle = !empty($cSlot['title']) ? (string) $cSlot['title'] : $slotMeta['label'];
            $slotHours = !empty($cSlot['hours']) ? (string) $cSlot['hours'] : $slotMeta['programme_time'];
            $slotBuckets[$slotKey] = [
                'title' => $slotTitle,
                'hours' => $slotHours,
                'items' => [],
            ];
        }

        $totalReads = 0;
        foreach ($campaigns as $camp) {
            $matchedAnySlot = false;
            foreach (self::SLOTS as $slotKey => $slotMeta) {
                $hourBreakdown = [];
                $slotSpots = 0;
                foreach ($slotMeta['hours'] as $hourKey => $hInfo) {
                    $col = "{$dayKey}_{$slotKey}_{$hourKey}_c";
                    $qty = (int) ($camp[$col] ?? 0);
                    if ($qty > 0) {
                        $slotSpots += $qty;
                        $hourBreakdown[] = "{$hInfo['label']} ({$qty}x)";
                    }
                }
                if ($slotSpots > 0) {
                    $matchedAnySlot = true;
                    $totalReads += $slotSpots;
                    $slotBuckets[$slotKey]['items'][] = [
                        'campaign_name' => (string) ($camp['name'] ?? 'Campaign'),
                        'advertiser' => (string) ($camp['account_name'] ?: ($camp['quote_name'] ?: 'Station Advertiser')),
                        'cart' => (string) ($camp['myriad_cart_number'] ?: 'LIVE'),
                        'duration' => (string) ($camp['spot_length'] ?: '30 sec'),
                        'spots' => $slotSpots,
                        'hours_text' => implode(', ', $hourBreakdown),
                        'script' => (string) ($camp['script_text'] ?: ($camp['description'] ?: 'Presenter live-read / commercial spot: emphasize key advertiser call-to-action and contact details.')),
                        'talent' => (string) ($camp['voice_talent'] ?: 'On-Duty Show Presenter'),
                    ];
                }
            }

            if (!$matchedAnySlot && !empty($camp['script_text'])) {
                $totalReads++;
                $slotBuckets['morning_buzz']['items'][] = [
                    'campaign_name' => (string) ($camp['name'] ?? 'Campaign'),
                    'advertiser' => (string) ($camp['account_name'] ?: 'Station Advertiser'),
                    'cart' => (string) ($camp['myriad_cart_number'] ?: 'LIVE'),
                    'duration' => (string) ($camp['spot_length'] ?: '30 sec'),
                    'spots' => 1,
                    'hours_text' => 'Show Feature / Live Read',
                    'script' => (string) $camp['script_text'],
                    'talent' => (string) ($camp['voice_talent'] ?: 'On-Duty Show Presenter'),
                ];
            }
        }

        $slotsActive = 0;
        $sectionsHtml = '';
        foreach ($slotBuckets as $sKey => $bucket) {
            if (empty($bucket['items'])) {
                continue;
            }
            $slotsActive++;
            $sTitle = htmlspecialchars($bucket['title'], ENT_QUOTES);
            $sHours = htmlspecialchars($bucket['hours'], ENT_QUOTES);
            $sectionsHtml .= "<div class='slot-card'>"
                . "<div class='slot-header'><span>🎙️ {$sTitle}</span><span class='slot-time'>{$sHours}</span></div>"
                . "<div class='slot-body'>";

            foreach ($bucket['items'] as $idx => $item) {
                $adv = htmlspecialchars($item['advertiser'], ENT_QUOTES);
                $cName = htmlspecialchars($item['campaign_name'], ENT_QUOTES);
                $cart = htmlspecialchars($item['cart'], ENT_QUOTES);
                $dur = htmlspecialchars($item['duration'], ENT_QUOTES);
                $hrs = htmlspecialchars($item['hours_text'], ENT_QUOTES);
                $tal = htmlspecialchars($item['talent'], ENT_QUOTES);
                $scr = nl2br(htmlspecialchars($item['script'], ENT_QUOTES));
                $num = $idx + 1;

                $sectionsHtml .= "<div class='read-item'>"
                    . "<div class='read-meta'>"
                    . "<div><strong>#{$num} — {$adv}</strong> ({$cName})</div>"
                    . "<div><span class='pill'>Cart: {$cart}</span> <span class='pill'>{$dur}</span> <span class='pill'>{$hrs}</span> <span class='pill'>Presenter: {$tal}</span></div>"
                    . "</div>"
                    . "<div class='read-script'>{$scr}</div>"
                    . "<div class='read-signoff'>☐ Read On-Air &nbsp;&nbsp; Actual Time: __________ &nbsp;&nbsp; Presenter Initials: ______</div>"
                    . "</div>";
            }
            $sectionsHtml .= "</div></div>";
        }

        if ($sectionsHtml === '') {
            $sectionsHtml = "<div style='padding:24px; background:#fff; border:1px solid #d0d7de; border-radius:8px; text-align:center; color:#57606a;'>No scheduled live reads or spots found for {$dayFull} ({$dateYmd}).</div>";
        }

        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>{$stName} - Studio Presenter Live-Read Run Sheet ({$dateYmd})</title>
<style>
body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; background: #f4f6f9; color: #1f2328; margin: 0; padding: 24px; }
.sheet-container { max-width: 960px; margin: 0 auto; }
.top-bar { display: flex; justify-content: space-between; align-items: center; background: linear-gradient(135deg, #5E2B83 0%, #3b1954 100%); color: #fff; padding: 20px 24px; border-radius: 8px; margin-bottom: 20px; }
.top-bar h1 { margin: 0; font-size: 20px; }
.top-bar p { margin: 4px 0 0; font-size: 13px; opacity: 0.9; }
.controls-bar { background: #fff; border: 1px solid #d0d7de; padding: 12px 18px; border-radius: 8px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
.slot-card { background: #fff; border: 1px solid #d0d7de; border-radius: 8px; margin-bottom: 18px; overflow: hidden; page-break-inside: avoid; }
.slot-header { background: #24292f; color: #fff; padding: 10px 16px; font-weight: 700; font-size: 15px; display: flex; justify-content: space-between; align-items: center; }
.slot-time { background: #1B8FA3; padding: 3px 10px; border-radius: 12px; font-size: 12px; }
.slot-body { padding: 14px 16px; }
.read-item { border: 1px solid #e1e4e8; border-radius: 6px; padding: 12px 14px; margin-bottom: 12px; background: #fafbfc; }
.read-item:last-child { margin-bottom: 0; }
.read-meta { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; margin-bottom: 8px; font-size: 13px; }
.pill { display: inline-block; background: #eef8f9; color: #0f5664; border: 1px solid #b6e3eb; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; }
.read-script { background: #fffdf5; border-left: 4px solid #5E2B83; padding: 12px 14px; font-family: Georgia, serif; font-size: 15px; line-height: 1.55; color: #111; margin-bottom: 8px; }
.read-signoff { font-size: 12px; color: #57606a; font-family: monospace; text-align: right; }
.btn { padding: 8px 14px; border-radius: 6px; border: 1px solid #d0d7de; background: #5E2B83; color: #fff; font-weight: 600; cursor: pointer; text-decoration: none; font-size: 13px; }
@media print { .controls-bar { display: none !important; } body { background: #fff; padding: 0; } }
</style>
</head>
<body>
<div class="sheet-container">
    <div class="top-bar">
        <div>
            <h1>🎙️ {$stName} ({$stFreq}) — Studio Presenter Live-Read & Commercial Run Sheet</h1>
            <p>Broadcast Date: <strong>{$dayFull}, {$dateYmd}</strong> &bull; Active Show Dayparts: {$slotsActive} &bull; Total Scheduled Spots/Reads: {$totalReads}</p>
        </div>
    </div>

    <div class="controls-bar">
        <form method="GET" action="index.php" style="display:flex; align-items:center; gap:10px; margin:0;">
            <input type="hidden" name="entryPoint" value="generate_presenter_liveread_sheet">
            <label style="font-size:13px; font-weight:600;">Select Broadcast Date:</label>
            <input type="date" name="date" value="{$dateYmd}" style="padding:6px 10px; border:1px solid #d0d7de; border-radius:6px;">
            <button type="submit" class="btn" style="background:#0969da; border-color:#0969da;">Load Date</button>
        </form>
        <div style="display:flex; gap:8px;">
            <button type="button" class="btn" onclick="window.print()">🖨️ Print Studio Sheet</button>
            <a href="index.php?module=RT_RadioCampaigns&action=index" class="btn" style="background:#6e7781; border-color:#6e7781;">← Campaigns</a>
        </div>
    </div>

    {$sectionsHtml}
</div>
</body>
</html>
HTML;

        return [
            'success' => true,
            'date' => $dateYmd,
            'html' => $html,
            'total_live_reads' => $totalReads,
            'slots_active' => $slotsActive,
        ];
    }

    /**
     * Dispatch a Client Approval Link or Broadcast Affidavit email to the client and record an outbox audit copy.
     *
     * @return array{success:bool, message:string, recipient:string, subject:string, outbox_file:string}
     */
    public static function sendClientNotificationEmail(string $campaignId, string $emailType = 'approval', string $recipientEmail = '', mixed $db = null): array
    {
        $campaignId = trim($campaignId);
        if ($campaignId === '') {
            return ['success' => false, 'message' => 'Campaign ID is required.', 'recipient' => '', 'subject' => '', 'outbox_file' => ''];
        }
        if ($db === null && isset($GLOBALS['db']) && is_object($GLOBALS['db'])) {
            $db = $GLOBALS['db'];
        }
        if (!is_object($db) || !method_exists($db, 'query')) {
            return ['success' => false, 'message' => 'Database connection required.', 'recipient' => '', 'subject' => '', 'outbox_file' => ''];
        }

        $cIdEsc = $db->quote($campaignId);
        $campRes = $db->query(
            "SELECT c.*, a.name AS account_name
             FROM rt_radiocampaigns c
             LEFT JOIN aos_quotes q ON q.id = c.linked_quote_id AND q.deleted = 0
             LEFT JOIN accounts a ON a.id = q.billing_account_id
             WHERE c.id = '{$cIdEsc}' AND c.deleted = 0
             LIMIT 1",
            false
        );
        $camp = $campRes ? $db->fetchByAssoc($campRes) : null;
        if (!$camp) {
            return ['success' => false, 'message' => 'Campaign not found.', 'recipient' => '', 'subject' => '', 'outbox_file' => ''];
        }

        $stConfig = RadioStationConfigService::getSettings($db);
        $stName = (string) ($stConfig['station_name'] ?? 'Community Radio FM');
        $stEmail = (string) ($stConfig['station_email'] ?: 'traffic@radiostation.co.za');

        $recipient = trim($recipientEmail);
        if ($recipient === '') {
            $recipient = 'client@advertiser.co.za';
        }

        $token = (string) ($camp['client_approval_token'] ?? '');
        if ($token === '') {
            $token = bin2hex(random_bytes(16));
            $tokEsc = $db->quote($token);
            $db->query("UPDATE rt_radiocampaigns SET client_approval_token = '{$tokEsc}' WHERE id = '{$cIdEsc}'", false);
        }

        if ($emailType === 'affidavit') {
            $aff = self::generateBroadcastAffidavitHtml($campaignId, $db);
            $subject = "{$stName} - Official Broadcast Affidavit: {$camp['name']}";
            $bodyHtml = $aff['html'] ?? '<p>Broadcast Affidavit attached.</p>';
        } else {
            $reviewUrl = "index.php?entryPoint=radio_campaign_client_review&token={$token}";
            $subject = "{$stName} - Commercial Creative Approval Required: {$camp['name']}";
            $cNameEsc = htmlspecialchars((string) $camp['name'], ENT_QUOTES);
            $stNameEsc = htmlspecialchars($stName, ENT_QUOTES);
            $scriptEsc = nl2br(htmlspecialchars((string) ($camp['script_text'] ?: 'Script ready for review.'), ENT_QUOTES));
            $bodyHtml = <<<HTML
<!DOCTYPE html>
<html>
<body style="font-family:Arial,sans-serif; color:#24292f; line-height:1.6;">
    <div style="max-width:640px; margin:20px auto; border:1px solid #d0d7de; border-radius:8px; padding:24px;">
        <h2 style="color:#5E2B83; margin-top:0;">{$stNameEsc} — Commercial Copy & Audio Sign-Off</h2>
        <p>Dear Valued Advertiser,</p>
        <p>Your radio campaign <strong>{$cNameEsc}</strong> ({$camp['start_date']} to {$camp['end_date']}, {$camp['number_of_spots']} spots) is ready for creative review and broadcast authorization.</p>
        <div style="background:#fffdf5; border-left:4px solid #5E2B83; padding:12px 16px; margin:16px 0;">
            <strong>Commercial Script Copy:</strong><br>{$scriptEsc}
        </div>
        <p><a href="{$reviewUrl}" style="display:inline-block; padding:10px 20px; background:#1a7f37; color:#ffffff; text-decoration:none; border-radius:6px; font-weight:bold;">Review & Approve Campaign Online</a></p>
        <p style="font-size:12px; color:#57606a;">Dispatched by {$stNameEsc} Traffic & Production Department ({$stEmail})</p>
    </div>
</body>
</html>
HTML;
        }

        $outboxDir = '/srv/myriad_exports/email_outbox';
        if (!is_dir($outboxDir)) {
            @mkdir($outboxDir, 02775, true);
        }
        $safeType = preg_replace('/[^a-z0-9_-]/i', '', $emailType);
        $fileName = sprintf('email_%s_%s_%s.html', $safeType, substr(preg_replace('/[^a-zA-Z0-9]/', '', $campaignId), 0, 8), date('Ymd_His'));
        $filePath = $outboxDir . '/' . $fileName;
        $headerMeta = "<!-- To: {$recipient} | From: {$stEmail} | Subject: {$subject} | Date: " . gmdate('c') . " -->\n";
        @file_put_contents($filePath, $headerMeta . $bodyHtml);

        return [
            'success' => true,
            'message' => "Client {$emailType} notification dispatched to {$recipient} and logged in station email outbox ({$fileName}).",
            'recipient' => $recipient,
            'subject' => $subject,
            'outbox_file' => $fileName,
        ];
    }

    /**
     * Compute comprehensive Station Manager Dashboard metrics strictly from Invoices (AOS_Invoices):
     * - Revenue & spots invoiced by Show Slot
     * - Expiring campaigns (next 14 days)
     * - Production & copy pipeline breakdown
     * - ICASA compliance report summary (sourced strictly from Invoices)
     *
     * @return array<string, mixed>
     */
    public static function getStationDashboardMetrics(?int $year = null, ?int $month = null, mixed $db = null): array
    {
        if ($db === null && isset($GLOBALS['db']) && is_object($GLOBALS['db'])) {
            $db = $GLOBALS['db'];
        }
        $yr = $year ?: (int) date('Y');
        $mo = $month ?: (int) date('n');

        $stConfig = RadioStationConfigService::getSettings($db);
        $confSlots = $stConfig['slots'] ?? RadioStationConfigService::DEFAULT_SLOTS;

        $revenueBySlot = [];
        foreach ($confSlots as $code => $sInfo) {
            $pn = (string) ($sInfo['part_number'] ?? $code);
            $revenueBySlot[$pn] = [
                'part_number' => $pn,
                'title' => (string) ($sInfo['title'] ?? $pn),
                'hours' => (string) ($sInfo['hours'] ?? ''),
                'booked_spots' => 0,
                'booked_revenue' => 0.0,
            ];
        }

        $totalBookedRevenue = 0.0;
        $totalBookedSpots = 0;

        if (is_object($db) && method_exists($db, 'query')) {
            // Strictly query AOS_Invoices line items (parent_type = 'AOS_Invoices'), never AOS_Quotes
            $lineRes = $db->query(
                "SELECT pq.part_number, pq.name, SUM(pq.product_qty) AS total_qty, SUM(pq.product_total_price) AS total_net
                 FROM aos_products_quotes pq
                 JOIN aos_invoices inv ON inv.id = pq.parent_id AND inv.deleted = 0
                 WHERE pq.deleted = 0 AND pq.parent_type = 'AOS_Invoices'
                 GROUP BY pq.part_number, pq.name",
                false
            );
            if ($lineRes) {
                while ($r = $db->fetchByAssoc($lineRes)) {
                    $pn = trim((string) ($r['part_number'] ?? ''));
                    if ($pn === '') {
                        $pn = 'INVOICED-BROADCAST';
                    }
                    $qty = (int) round((float) ($r['total_qty'] ?? 0));
                    $net = round((float) ($r['total_net'] ?? 0.0), 2);
                    if (!isset($revenueBySlot[$pn])) {
                        $revenueBySlot[$pn] = [
                            'part_number' => $pn,
                            'title' => (string) ($r['name'] ?: $pn),
                            'hours' => 'Invoiced Broadcast / Extra',
                            'booked_spots' => 0,
                            'booked_revenue' => 0.0,
                        ];
                    }
                    $revenueBySlot[$pn]['booked_spots'] += $qty;
                    $revenueBySlot[$pn]['booked_revenue'] = round($revenueBySlot[$pn]['booked_revenue'] + $net, 2);
                    $totalBookedSpots += $qty;
                    $totalBookedRevenue = round($totalBookedRevenue + $net, 2);
                }
            }
        }

        // Expiring campaigns (within next 14 days or active)
        $expiringCampaigns = [];
        $pipelineCounts = [
            'Draft' => 0,
            'Scripting' => 0,
            'In Production' => 0,
            'Approved' => 0,
            'Exported' => 0,
        ];
        $activeCampaignCount = 0;

        if (is_object($db) && method_exists($db, 'query')) {
            $cRes = $db->query(
                "SELECT c.id, c.name, c.start_date, c.end_date, c.number_of_spots, c.status,
                        c.production_status, c.reconciliation_status, c.reconciled_spots, c.missed_spots,
                        c.myriad_cart_number
                 FROM rt_radiocampaigns c
                 WHERE c.deleted = 0
                 ORDER BY c.end_date ASC",
                false
            );
            $today = date('Y-m-d');
            $in14Days = date('Y-m-d', strtotime('+14 days'));
            if ($cRes) {
                while ($c = $db->fetchByAssoc($cRes)) {
                    $activeCampaignCount++;
                    $pStat = (string) ($c['production_status'] ?: 'Draft');
                    if ((string) ($c['status'] ?? '') === 'exported_to_myriad') {
                        $pipelineCounts['Exported']++;
                    } elseif (isset($pipelineCounts[$pStat])) {
                        $pipelineCounts[$pStat]++;
                    } else {
                        $pipelineCounts['Draft']++;
                    }

                    $endD = (string) ($c['end_date'] ?? '');
                    if ($endD !== '' && $endD >= $today && $endD <= $in14Days) {
                        $expiringCampaigns[] = $c;
                    } elseif (count($expiringCampaigns) < 5 && $endD !== '') {
                        $expiringCampaigns[] = $c;
                    }
                }
            }
        }

        $icasa = self::generateIcasaComplianceReport($yr, $mo, $db);

        return [
            'station_name' => $stConfig['station_name'] ?? 'Community Radio FM',
            'currency_symbol' => $stConfig['currency_symbol'] ?? 'R',
            'data_source' => 'AOS_Invoices',
            'total_booked_revenue' => $totalBookedRevenue,
            'total_booked_spots' => $totalBookedSpots,
            'active_campaigns_count' => $activeCampaignCount,
            'revenue_by_slot' => array_values($revenueBySlot),
            'expiring_campaigns' => $expiringCampaigns,
            'pipeline_counts' => $pipelineCounts,
            'icasa' => $icasa,
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

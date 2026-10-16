<?php

declare(strict_types=1);

namespace RadioCRM;

use DateTimeImmutable;
use DateTimeZone;

class RadioScheduleService
{
    public const TIMEZONE = 'Africa/Johannesburg';
    public const DEFAULT_VAT_RATE = 0.15;
    public const DEFAULT_VAT_STRING = '15.0';

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
            'default_rate' => 300.00,
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
            'default_rate' => 320.00,
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
            'default_rate' => 350.00,
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
     * @return array<string, mixed>
     */
    public static function calculateSchedule(
        array $inputData,
        ?string $rawStartDate,
        ?string $rawEndDate,
        mixed $db = null
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

        $weeklySpots = 0;
        $totalSpots = 0;
        $invalidFields = [];
        $sanitizedCells = [];

        $slotAggregates = [];
        foreach (self::SLOTS as $slotKey => $slot) {
            $pn = $slot['part_number'];
            $cat = $catalogue[$pn];
            $slotAggregates[$slotKey] = [
                'slot_key' => $slotKey,
                'slot_label' => $slot['label'],
                'part_number' => $pn,
                'product_id' => $cat['id'],
                'product_name' => $cat['name'],
                'unit_price' => $cat['price'],
                'description' => $cat['description'],
                'weekly_qty' => 0,
                'total_qty' => 0,
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

            if ($dateRangeValid) {
                $repeats = $weekdayRepeats[$meta['php_w']] ?? 0;
                $fieldTotalPlays = $qty * $repeats;
                $totalSpots += $fieldTotalPlays;
                $slotAggregates[$slotKey]['total_qty'] += $fieldTotalPlays;
            }
        }

        $activeProducts = [];
        $subtotalAmount = 0.0;
        $taxAmount = 0.0;
        $totalAmount = 0.0;

        foreach ($slotAggregates as $slotKey => &$agg) {
            $lineQty = $dateRangeValid ? $agg['total_qty'] : 0;
            $unitPrice = (float) $agg['unit_price'];
            $net = round($lineQty * $unitPrice, 2);
            $vat = round($net * self::DEFAULT_VAT_RATE, 2);
            $gross = round($net + $vat, 2);

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

        // Atomic write for each active day's .log file (Section 14.5)
        $writtenLogs = [];
        foreach ($dailyLogLines as $dateYmd => $lines) {
            $ymdCompact = str_replace('-', '', $dateYmd);
            $logFilename = "PUKfm_Adverts_{$ymdCompact}_{$safeName}_{$timestamp}.log";
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

        return [
            'success' => true,
            'message' => sprintf(
                'Exported %d scheduled plays across %d active day(s) to Myriad CSV (%s) and daily .log files.',
                count($csvRows),
                count($writtenLogs),
                $csvFilename
            ),
            'csv_path' => $csvPath,
            'csv_filename' => $csvFilename,
            'daily_logs' => $writtenLogs,
            'total_plays' => count($csvRows),
            'active_days' => count($writtenLogs),
        ];
    }
}

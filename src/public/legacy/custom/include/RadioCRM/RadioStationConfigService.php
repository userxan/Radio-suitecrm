<?php

declare(strict_types=1);

namespace RadioCRM;

/**
 * RadioStationConfigService
 * Central management for station branding, callsign, logo, show schedule names,
 * catalogue rate cards, Myriad playout settings, and ICASA broadcast rules.
 */
class RadioStationConfigService
{
    public const TABLE_NAME = 'radio_station_settings';

    public const DEFAULT_SETTINGS = [
        'station_name' => 'Community Radio FM',
        'station_callsign' => 'RADIO',
        'station_frequency' => '101.5 FM',
        'station_tagline' => 'Your Voice, Your Station',
        'station_logo_url' => 'custom/include/RadioCRM/radio_station_logo.png',
        'station_manager_name' => 'Station Manager / Traffic Director',
        'station_email' => 'traffic@station.local',
        'station_phone' => '+27 11 000 0000',
        'studio_address' => 'Broadcast House, 1 Radio Boulevard, Studio City',
        'vat_number' => '4000123456',
        'vat_rate' => 15.0,
        'currency_symbol' => 'R',
        'currency_iso' => 'ZAR',
        'hourly_capacity' => 20,
        'max_ad_minutes_per_hour' => 10.0,
        'cart_range_start' => 2000,
        'cart_range_end' => 2999,
        'playout_log_dir' => '/srv/myriad_reconciliation',
        'export_dir' => '/srv/myriad_exports',
        'station_configured' => false,
    ];

    public const DEFAULT_SLOTS = [
        'PRE-BREAKFAST' => [
            'part_number' => 'PRE-BREAKFAST',
            'title' => 'Pre-Breakfast',
            'hours' => '05:00 - 06:00',
            'rate' => 120.00,
            'description' => 'Early morning warm-up show (05:00 - 06:00).',
        ],
        'MORNING-BUZZ' => [
            'part_number' => 'MORNING-BUZZ',
            'title' => 'Morning Buzz (Breakfast Show)',
            'hours' => '06:00 - 09:00',
            'rate' => 350.00,
            'description' => 'Flagship morning breakfast drive show (06:00 - 09:00). High student & commuter listenership.',
        ],
        'MIDMORNING-BANTER' => [
            'part_number' => 'MIDMORNING-BANTER',
            'title' => 'Midmorning Banter',
            'hours' => '09:00 - 12:00',
            'rate' => 320.00,
            'description' => 'Campus lifestyle, music and entertainment (09:00 - 12:00).',
        ],
        'MIDDAY-PULSE' => [
            'part_number' => 'MIDDAY-PULSE',
            'title' => 'Midday Pulse',
            'hours' => '12:00 - 15:00',
            'rate' => 350.00,
            'description' => 'Lunchtime music, chart hits and campus news (12:00 - 15:00).',
        ],
        'CRUISE-CONTROL' => [
            'part_number' => 'CRUISE-CONTROL',
            'title' => 'Cruise Control (Afternoon Drive)',
            'hours' => '15:00 - 18:00',
            'rate' => 350.00,
            'description' => 'Afternoon drive prime time show (15:00 - 18:00). Peak listener engagement.',
        ],
        'SPECIALTY-SHOW-1' => [
            'part_number' => 'SPECIALTY-SHOW-1',
            'title' => 'Specialty Show 1 (Evening)',
            'hours' => '18:00 - 21:00',
            'rate' => 300.00,
            'description' => 'Evening genre-specific programming (18:00 - 21:00). Focused enthusiast demographics.',
        ],
        'SPECIALTY-SHOW-2' => [
            'part_number' => 'SPECIALTY-SHOW-2',
            'title' => 'Specialty Show 2 (Late Evening)',
            'hours' => '21:00 - 00:00',
            'rate' => 220.00,
            'description' => 'Late-night music, underground and campus culture (21:00 - 00:00).',
        ],
        'MUSIC-UNLIMITED' => [
            'part_number' => 'MUSIC-UNLIMITED',
            'title' => 'Music Unlimited (Overnight)',
            'hours' => '00:00 - 05:00',
            'rate' => 120.00,
            'description' => 'Automated overnight hit music playout (00:00 - 05:00).',
        ],
    ];

    /**
     * Ensure the settings database table exists.
     */
    public static function ensureTableExists(mixed $db = null): void
    {
        if ($db === null && isset($GLOBALS['db']) && is_object($GLOBALS['db'])) {
            $db = $GLOBALS['db'];
        }
        if (!is_object($db) || !method_exists($db, 'query')) {
            return;
        }

        $db->query("CREATE TABLE IF NOT EXISTS " . self::TABLE_NAME . " (
            setting_key VARCHAR(100) NOT NULL PRIMARY KEY,
            setting_value LONGTEXT DEFAULT NULL,
            date_modified DATETIME DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci", false);
    }

    /**
     * Retrieve all station settings.
     *
     * @return array<string, mixed>
     */
    public static function getSettings(mixed $db = null): array
    {
        if ($db === null && isset($GLOBALS['db']) && is_object($GLOBALS['db'])) {
            $db = $GLOBALS['db'];
        }

        self::ensureTableExists($db);

        $settings = self::DEFAULT_SETTINGS;
        $settings['slots'] = self::DEFAULT_SLOTS;

        if (is_object($db) && method_exists($db, 'query')) {
            $res = $db->query("SELECT setting_key, setting_value FROM " . self::TABLE_NAME, false);
            if ($res) {
                while ($row = $db->fetchByAssoc($res)) {
                    $key = $row['setting_key'];
                    $raw = (string) ($row['setting_value'] ?? '');
                    $decoded = json_decode($raw, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $decoded = json_decode(htmlspecialchars_decode($raw, ENT_QUOTES), true);
                    }
                    if (json_last_error() === JSON_ERROR_NONE && (is_array($decoded) || is_bool($decoded) || is_numeric($decoded))) {
                        $settings[$key] = $decoded;
                    } else {
                        $settings[$key] = htmlspecialchars_decode($raw, ENT_QUOTES);
                    }
                }
            }
        }

        return $settings;
    }

    /**
     * Retrieve a single setting by key.
     */
    public static function getSetting(string $key, mixed $default = null, mixed $db = null): mixed
    {
        $all = self::getSettings($db);
        return $all[$key] ?? $default;
    }

    /**
     * Check if the station onboarding wizard has been completed.
     */
    public static function isConfigured(mixed $db = null): bool
    {
        $all = self::getSettings($db);
        return !empty($all['station_configured']);
    }

    /**
     * Persist station settings.
     *
     * @param array<string, mixed> $data
     */
    public static function saveSettings(array $data, mixed $db = null): bool
    {
        if ($db === null && isset($GLOBALS['db']) && is_object($GLOBALS['db'])) {
            $db = $GLOBALS['db'];
        }
        if (!is_object($db) || !method_exists($db, 'query')) {
            return false;
        }

        self::ensureTableExists($db);

        $now = gmdate('Y-m-d H:i:s');
        $allowedKeys = array_keys(self::DEFAULT_SETTINGS);
        $allowedKeys[] = 'slots';

        foreach ($data as $k => $v) {
            if (!in_array($k, $allowedKeys, true)) {
                continue;
            }

            $encoded = is_array($v) || is_bool($v) ? json_encode($v) : (string) $v;
            $kEsc = $db->quote($k);
            $vEsc = $db->quote($encoded);

            $db->query(
                "REPLACE INTO " . self::TABLE_NAME . " (setting_key, setting_value, date_modified)
                 VALUES ('{$kEsc}', '{$vEsc}', '{$now}')",
                false
            );
        }

        // If slots are provided, synchronize them into aos_products
        if (isset($data['slots']) && is_array($data['slots'])) {
            self::updateProductsCatalogue($data['slots'], $db);
        }

        // Synchronize branding into PDF templates
        self::updatePdfTemplate($data, $db);

        return true;
    }

    /**
     * Update show slot products in aos_products.
     *
     * @param array<string, array<string, mixed>> $slots
     */
    public static function updateProductsCatalogue(array $slots, mixed $db = null): int
    {
        if ($db === null && isset($GLOBALS['db']) && is_object($GLOBALS['db'])) {
            $db = $GLOBALS['db'];
        }
        if (!is_object($db) || !method_exists($db, 'query')) {
            return 0;
        }

        $now = gmdate('Y-m-d H:i:s');
        $updated = 0;

        foreach ($slots as $code => $slot) {
            $partNumber = (string) ($slot['part_number'] ?? $code);
            $title = (string) ($slot['title'] ?? '');
            $hours = (string) ($slot['hours'] ?? '');
            $rate = (float) ($slot['rate'] ?? 0.0);
            $desc = (string) ($slot['description'] ?? '');

            if ($title === '') {
                continue;
            }

            $productName = "Radio Advert: {$title} ({$hours})";
            $pnEsc = $db->quote($partNumber);
            $nameEsc = $db->quote($productName);
            $descEsc = $db->quote($desc);

            // Check if product exists
            $chk = $db->query("SELECT id FROM aos_products WHERE part_number = '{$pnEsc}' AND deleted = 0 LIMIT 1", false);
            if ($chk && ($row = $db->fetchByAssoc($chk))) {
                $pId = $db->quote($row['id']);
                $db->query(
                    "UPDATE aos_products
                     SET name = '{$nameEsc}',
                         description = '{$descEsc}',
                         price = {$rate},
                         cost = {$rate},
                         date_modified = '{$now}'
                     WHERE id = '{$pId}'",
                    false
                );
                $updated++;
            } else {
                $newId = RadioScheduleService::generateGuid();
                $db->query(
                    "INSERT INTO aos_products (
                        id, name, part_number, description, price, cost, currency_id, deleted, date_entered, date_modified
                    ) VALUES (
                        '{$newId}', '{$nameEsc}', '{$pnEsc}', '{$descEsc}', {$rate}, {$rate}, '-99', 0, '{$now}', '{$now}'
                    )",
                    false
                );
                $updated++;
            }
        }

        return $updated;
    }

    /**
     * Update the Quote PDF template header with current station branding.
     */
    public static function updatePdfTemplate(array $branding, mixed $db = null): bool
    {
        if ($db === null && isset($GLOBALS['db']) && is_object($GLOBALS['db'])) {
            $db = $GLOBALS['db'];
        }
        if (!is_object($db) || !method_exists($db, 'query')) {
            return false;
        }

        $templateId = 'f4b47a53-2fc1-469c-a5c2-fae4dba3cf55';
        $siteUrl = rtrim((string) ($GLOBALS['sugar_config']['site_url'] ?? ''), '/');
        $stationName = htmlspecialchars((string) ($branding['station_name'] ?? 'Community Radio FM'), ENT_QUOTES);
        $stationTagline = htmlspecialchars((string) ($branding['station_tagline'] ?? 'Your Voice, Your Station'), ENT_QUOTES);
        $logoUrl = (string) ($branding['station_logo_url'] ?? 'custom/include/RadioCRM/radio_station_logo.png');
        $fullLogoUrl = str_starts_with($logoUrl, 'http') ? $logoUrl : "{$siteUrl}/legacy/{$logoUrl}";

        $purple = '#6B2C91';
        $teal = '#1A8CA3';
        $grey = '#5F6B78';

        $headerHtml = <<<HTML
<table width="100%" cellpadding="2" cellspacing="0">
<tr>
<td width="16%"><img src="{$fullLogoUrl}" width="62" height="62" /></td>
<td width="50%"><font size="15" color="{$purple}"><b>{$stationName}</b></font><br /><font size="8" color="{$grey}">{$stationTagline}</font></td>
<td width="34%" align="right"><font size="20" color="{$teal}"><b>QUOTATION</b></font></td>
</tr>
</table>
<hr />
HTML;

        $hdrEsc = $db->quote($headerHtml);
        $now = gmdate('Y-m-d H:i:s');
        $db->query(
            "UPDATE aos_pdf_templates
             SET pdfheader = '{$hdrEsc}', date_modified = '{$now}'
             WHERE id = '{$templateId}'",
            false
        );

        return true;
    }

    /**
     * Handle logo file upload from the wizard.
     *
     * @param array<string, mixed> $fileArray $_FILES['station_logo']
     */
    public static function handleLogoUpload(array $fileArray, ?string &$errorMsg = null): ?string
    {
        if (empty($fileArray['tmp_name']) || !is_uploaded_file($fileArray['tmp_name'])) {
            $errorMsg = 'No uploaded file provided.';
            return null;
        }

        $allowedExts = ['png', 'jpg', 'jpeg', 'gif', 'svg', 'webp'];
        $origName = (string) ($fileArray['name'] ?? 'logo.png');
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

        if (!in_array($ext, $allowedExts, true)) {
            $errorMsg = 'Invalid image type. Allowed: ' . implode(', ', $allowedExts);
            return null;
        }

        $targetDir = '/var/www/html/public/legacy/custom/themes/default/images';
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0775, true);
        }

        $targetFilename = "station_logo_{$ext}";
        $targetPath = "{$targetDir}/{$targetFilename}";

        if (!move_uploaded_file($fileArray['tmp_name'], $targetPath)) {
            $errorMsg = "Failed to write file to {$targetPath}";
            return null;
        }

        @chmod($targetPath, 0664);

        // Also copy to persistent repo location if mounted
        $repoTargetDir = '/opt/radio_crm_repo/src/public/legacy/custom/themes/default/images';
        if (is_dir($repoTargetDir)) {
            @copy($targetPath, "{$repoTargetDir}/{$targetFilename}");
        }

        // Also copy as standard SuiteCRM company logo
        @copy($targetPath, "{$targetDir}/company_logo.png");
        @copy($targetPath, '/srv/myriad_exports/station_logo.png');

        return "custom/themes/default/images/{$targetFilename}";
    }

    /**
     * Import full station profile from a JSON file or JSON string.
     */
    public static function importProfileJson(string $jsonOrFilePath, mixed $db = null): bool
    {
        $raw = file_exists($jsonOrFilePath) ? (string) file_get_contents($jsonOrFilePath) : $jsonOrFilePath;
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return false;
        }

        $decoded['station_configured'] = true;
        return self::saveSettings($decoded, $db);
    }

    /**
     * Export full station profile to JSON string.
     */
    public static function exportProfileJson(mixed $db = null): string
    {
        $settings = self::getSettings($db);
        return (string) json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}

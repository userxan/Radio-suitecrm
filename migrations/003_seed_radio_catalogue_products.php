<?php

declare(strict_types=1);

/**
 * Migration 003: Idempotently seed the 8 definitive PUKfm radio programme products in aos_products
 * and ensure a default Quote PDF template exists in aos_pdf_templates (Section 7.1, 11, 16.3).
 */

if (PHP_SAPI !== 'cli') {
    die('CLI only');
}

$legacyDir = '/var/www/html/public/legacy';
chdir($legacyDir);
if (!defined('sugarEntry')) {
    define('sugarEntry', true);
}
require_once 'include/entryPoint.php';
require_once 'custom/include/RadioCRM/RadioScheduleService.php';
require_once 'custom/modules/AOS_Quotes/RadioQuoteScheduleProducts.php';

use RadioCRM\RadioScheduleService;

global $db, $sugar_config;

echo "[Migration 003] Configuring default system currency to South African Rand (ZAR / R)...\n";
require_once 'modules/Configurator/Configurator.php';
$cfg = new Configurator();
$cfg->config['default_currency_iso4217'] = 'ZAR';
$cfg->config['default_currency_name'] = 'South African Rand';
$cfg->config['default_currency_significant_digits'] = '2';
$cfg->config['default_currency_symbol'] = 'R';
$cfg->handleOverride();

$sugar_config['default_currency_iso4217'] = 'ZAR';
$sugar_config['default_currency_name'] = 'South African Rand';
$sugar_config['default_currency_significant_digits'] = '2';
$sugar_config['default_currency_symbol'] = 'R';

$configPhpPath = $legacyDir . '/config.php';
if (is_writable($configPhpPath)) {
    $configPhp = (string) file_get_contents($configPhpPath);
    $configPhp = preg_replace("/'default_currency_iso4217'\s*=>\s*'[^']*'/", "'default_currency_iso4217' => 'ZAR'", $configPhp);
    $configPhp = preg_replace("/'default_currency_name'\s*=>\s*'[^']*'/", "'default_currency_name' => 'South African Rand'", $configPhp);
    $configPhp = preg_replace("/'default_currency_symbol'\s*=>\s*'[^']*'/", "'default_currency_symbol' => 'R'", $configPhp);
    file_put_contents($configPhpPath, $configPhp);
}

echo "[Migration 003] Ensuring 15% South African VAT rate is configured in vat_list...\n";
global $app_list_strings;
if (!isset($app_list_strings)) {
    $app_list_strings = [];
}
if (!isset($app_list_strings['vat_list'])) {
    $app_list_strings['vat_list'] = [];
}
$app_list_strings['vat_list']['15.0'] = '15%';

$customLangDir = $legacyDir . '/custom/include/language';
if (!is_dir($customLangDir)) {
    mkdir($customLangDir, 0775, true);
}
$customLangFile = $customLangDir . '/en_us.lang.php';
$langContent = "<?php\nif (!isset(\$app_list_strings)) { \$app_list_strings = []; }\n\$app_list_strings['vat_list']['15.0'] = '15%';\n";
file_put_contents($customLangFile, $langContent);

$customExtLangDir = $legacyDir . '/custom/Extension/application/Ext/Language';
if (!is_dir($customExtLangDir)) {
    mkdir($customExtLangDir, 0775, true);
}
file_put_contents($customExtLangDir . '/en_us.pukfm_vat_list.php', $langContent);

if (file_exists('include/portability/Services/Cache/CacheManager.php')) {
    require_once 'include/portability/Services/Cache/CacheManager.php';
    (new CacheManager())->markAsNeedsUpdate('rebuild_all');
    (new CacheManager())->markAsNeedsUpdate('app-metadata-system-configs');
}
if (function_exists('sugar_cache_clear')) {
    sugar_cache_clear('currency_list');
    sugar_cache_clear('app_list_strings');
}

echo "[Migration 003] Ensuring 8 radio catalogue products exist in aos_products...\n";

RadioQuoteScheduleProducts::ensureCatalogueProductsExist($db);

$partList = array_map(static fn($s) => "'" . $s['part_number'] . "'", RadioScheduleService::SLOTS);
$res = $db->query(
    "SELECT COUNT(DISTINCT part_number) AS cnt FROM aos_products
     WHERE part_number IN (" . implode(',', $partList) . ") AND deleted = 0",
    false
);
$row = $res ? $db->fetchByAssoc($res) : ['cnt' => 0];
$cnt = (int) ($row['cnt'] ?? 0);
echo "  = Verified active catalogue products: {$cnt} (expected 8)\n";
if ($cnt !== 8) {
    fwrite(STDERR, "ERROR: Expected 8 catalogue products, found {$cnt}\n");
    exit(1);
}

echo "[Migration 003] Ensuring product categories and all PUKfm Rate Card 2026 items exist in aos_products...\n";

$categoriesDef = [
    'schedule' => [
        'name' => 'Radio Programme Schedule',
        'description' => 'PUKfm 93.6 weekly programme schedule slots for automated quote schedule calculation.',
    ],
    'recorded_ads' => [
        'name' => 'Recorded Ads (30 Seconds)',
        'description' => 'PUKfm Rate Card 2026 - Radio Advertising Rates: Recorded Ads (30 Seconds) (Monday-Friday & Saturday-Sunday). Prices exclude VAT.',
    ],
    'live_reads' => [
        'name' => 'Live Reads (30 Seconds)',
        'description' => 'PUKfm Rate Card 2026 - Radio Advertising Rates: Live Reads (30 Seconds) (Monday-Friday & Saturday-Sunday). Prices exclude VAT.',
    ],
    'packages' => [
        'name' => 'Advertising Packages',
        'description' => 'PUKfm Rate Card 2026 - Weekly and Monthly Prime Time Advertising Packages (Options 1 to 4). Prices exclude VAT.',
    ],
    'social_media' => [
        'name' => 'Social Media Advertising',
        'description' => 'PUKfm Rate Card 2026 - Social Media Advertising Rates (@PUKfm936 on Facebook, TikTok, Instagram, Twitter/X). Prices exclude VAT.',
    ],
    'misc' => [
        'name' => 'Misc. Rates',
        'description' => 'PUKfm Rate Card 2026 - Miscellaneous Rates (Interviews, Powerspots, Sponsored Content Segments, Outside Broadcasts, Simulcasts). Prices exclude VAT.',
    ],
];

$categoryIds = [];
$nowUtc = gmdate('Y-m-d H:i:s');

foreach ($categoriesDef as $catKey => $catInfo) {
    $catNameEsc = $db->quote($catInfo['name']);
    $catDescEsc = $db->quote($catInfo['description']);
    $catRes = $db->query(
        "SELECT id FROM aos_product_categories WHERE name = '{$catNameEsc}' AND deleted = 0 LIMIT 1",
        false
    );
    $catRow = $catRes ? $db->fetchByAssoc($catRes) : null;
    if ($catRow && !empty($catRow['id'])) {
        $catId = (string) $catRow['id'];
        $catIdEsc = $db->quote($catId);
        $db->query(
            "UPDATE aos_product_categories SET description = '{$catDescEsc}', date_modified = '{$nowUtc}' WHERE id = '{$catIdEsc}'",
            false
        );
    } else {
        $catId = RadioQuoteScheduleProducts::generateUuid();
        $catIdEsc = $db->quote($catId);
        $db->query(
            "INSERT INTO aos_product_categories (
                id, name, date_entered, date_modified, modified_user_id, created_by,
                description, deleted, assigned_user_id, is_parent
            ) VALUES (
                '{$catIdEsc}', '{$catNameEsc}', '{$nowUtc}', '{$nowUtc}', '1', '1',
                '{$catDescEsc}', 0, '1', 0
            )",
            false
        );
    }
    $categoryIds[$catKey] = $catId;
}

// Assign the 8 schedule catalogue products to the 'Radio Programme Schedule' category
$schedCatIdEsc = $db->quote($categoryIds['schedule']);
$db->query(
    "UPDATE aos_products
     SET aos_product_category_id = '{$schedCatIdEsc}'
     WHERE part_number IN (" . implode(',', $partList) . ") AND deleted = 0
       AND (aos_product_category_id IS NULL OR aos_product_category_id = '')",
    false
);

// All 46 items from PUKfm_Rate_Card_2026.pdf
$rateCardProducts = [
    // Page 6: Radio Advertising Rates - Recorded Ads (30 Seconds) - Monday to Friday
    [
        'part_number' => 'REC-30S-MF-0600-0900',
        'name' => 'Recorded Ad (30s) - Mon-Fri 06:00 - 09:00',
        'category_key' => 'recorded_ads',
        'type' => 'Good',
        'price' => 350.00,
        'description' => 'PUKfm Rate Card 2026 - Recorded Ads (30 Seconds), Monday to Friday, 06:00 - 09:00. Price excludes VAT.',
    ],
    [
        'part_number' => 'REC-30S-MF-0900-1200',
        'name' => 'Recorded Ad (30s) - Mon-Fri 09:00 - 12:00',
        'category_key' => 'recorded_ads',
        'type' => 'Good',
        'price' => 320.00,
        'description' => 'PUKfm Rate Card 2026 - Recorded Ads (30 Seconds), Monday to Friday, 09:00 - 12:00. Price excludes VAT.',
    ],
    [
        'part_number' => 'REC-30S-MF-1200-1500',
        'name' => 'Recorded Ad (30s) - Mon-Fri 12:00 - 15:00',
        'category_key' => 'recorded_ads',
        'type' => 'Good',
        'price' => 350.00,
        'description' => 'PUKfm Rate Card 2026 - Recorded Ads (30 Seconds), Monday to Friday, 12:00 - 15:00. Price excludes VAT.',
    ],
    [
        'part_number' => 'REC-30S-MF-1500-1800',
        'name' => 'Recorded Ad (30s) - Mon-Fri 15:00 - 18:00',
        'category_key' => 'recorded_ads',
        'type' => 'Good',
        'price' => 350.00,
        'description' => 'PUKfm Rate Card 2026 - Recorded Ads (30 Seconds), Monday to Friday, 15:00 - 18:00. Price excludes VAT.',
    ],
    [
        'part_number' => 'REC-30S-MF-1800-2100',
        'name' => 'Recorded Ad (30s) - Mon-Fri 18:00 - 21:00',
        'category_key' => 'recorded_ads',
        'type' => 'Good',
        'price' => 300.00,
        'description' => 'PUKfm Rate Card 2026 - Recorded Ads (30 Seconds), Monday to Friday, 18:00 - 21:00. Price excludes VAT.',
    ],
    [
        'part_number' => 'REC-30S-MF-2100-0000',
        'name' => 'Recorded Ad (30s) - Mon-Fri 21:00 - 00:00',
        'category_key' => 'recorded_ads',
        'type' => 'Good',
        'price' => 220.00,
        'description' => 'PUKfm Rate Card 2026 - Recorded Ads (30 Seconds), Monday to Friday, 21:00 - 00:00. Price excludes VAT.',
    ],
    [
        'part_number' => 'REC-30S-MF-0000-0600',
        'name' => 'Recorded Ad (30s) - Mon-Fri 00:00 - 06:00',
        'category_key' => 'recorded_ads',
        'type' => 'Good',
        'price' => 120.00,
        'description' => 'PUKfm Rate Card 2026 - Recorded Ads (30 Seconds), Monday to Friday, 00:00 - 06:00. Price excludes VAT.',
    ],

    // Page 6: Radio Advertising Rates - Recorded Ads (30 Seconds) - Saturday and Sunday
    [
        'part_number' => 'REC-30S-SS-0000-0600',
        'name' => 'Recorded Ad (30s) - Sat-Sun 00:00 - 06:00',
        'category_key' => 'recorded_ads',
        'type' => 'Good',
        'price' => 120.00,
        'description' => 'PUKfm Rate Card 2026 - Recorded Ads (30 Seconds), Saturday and Sunday, 00:00 - 06:00. Price excludes VAT.',
    ],
    [
        'part_number' => 'REC-30S-SS-0600-1000',
        'name' => 'Recorded Ad (30s) - Sat-Sun 06:00 - 10:00',
        'category_key' => 'recorded_ads',
        'type' => 'Good',
        'price' => 300.00,
        'description' => 'PUKfm Rate Card 2026 - Recorded Ads (30 Seconds), Saturday and Sunday, 06:00 - 10:00. Price excludes VAT.',
    ],
    [
        'part_number' => 'REC-30S-SS-1000-1400',
        'name' => 'Recorded Ad (30s) - Sat-Sun 10:00 - 14:00',
        'category_key' => 'recorded_ads',
        'type' => 'Good',
        'price' => 350.00,
        'description' => 'PUKfm Rate Card 2026 - Recorded Ads (30 Seconds), Saturday and Sunday, 10:00 - 14:00. Price excludes VAT.',
    ],
    [
        'part_number' => 'REC-30S-SS-1400-1800',
        'name' => 'Recorded Ad (30s) - Sat-Sun 14:00 - 18:00',
        'category_key' => 'recorded_ads',
        'type' => 'Good',
        'price' => 350.00,
        'description' => 'PUKfm Rate Card 2026 - Recorded Ads (30 Seconds), Saturday and Sunday, 14:00 - 18:00. Price excludes VAT.',
    ],
    [
        'part_number' => 'REC-30S-SS-1800-2200',
        'name' => 'Recorded Ad (30s) - Sat-Sun 18:00 - 22:00',
        'category_key' => 'recorded_ads',
        'type' => 'Good',
        'price' => 220.00,
        'description' => 'PUKfm Rate Card 2026 - Recorded Ads (30 Seconds), Saturday and Sunday, 18:00 - 22:00. Price excludes VAT.',
    ],
    [
        'part_number' => 'REC-30S-SS-2200-0000',
        'name' => 'Recorded Ad (30s) - Sat-Sun 22:00 - 00:00',
        'category_key' => 'recorded_ads',
        'type' => 'Good',
        'price' => 120.00,
        'description' => 'PUKfm Rate Card 2026 - Recorded Ads (30 Seconds), Saturday and Sunday, 22:00 - 00:00. Price excludes VAT.',
    ],

    // Page 7: Radio Advertising Rates - Live Reads (30 Seconds) - Monday to Friday
    [
        'part_number' => 'LIVE-30S-MF-0600-0900',
        'name' => 'Live Read (30s) - Mon-Fri 06:00 - 09:00',
        'category_key' => 'live_reads',
        'type' => 'Good',
        'price' => 380.00,
        'description' => 'PUKfm Rate Card 2026 - Live Reads (30 Seconds), Monday to Friday, 06:00 - 09:00. Price excludes VAT.',
    ],
    [
        'part_number' => 'LIVE-30S-MF-0900-1200',
        'name' => 'Live Read (30s) - Mon-Fri 09:00 - 12:00',
        'category_key' => 'live_reads',
        'type' => 'Good',
        'price' => 350.00,
        'description' => 'PUKfm Rate Card 2026 - Live Reads (30 Seconds), Monday to Friday, 09:00 - 12:00. Price excludes VAT.',
    ],
    [
        'part_number' => 'LIVE-30S-MF-1200-1500',
        'name' => 'Live Read (30s) - Mon-Fri 12:00 - 15:00',
        'category_key' => 'live_reads',
        'type' => 'Good',
        'price' => 380.00,
        'description' => 'PUKfm Rate Card 2026 - Live Reads (30 Seconds), Monday to Friday, 12:00 - 15:00. Price excludes VAT.',
    ],
    [
        'part_number' => 'LIVE-30S-MF-1500-1800',
        'name' => 'Live Read (30s) - Mon-Fri 15:00 - 18:00',
        'category_key' => 'live_reads',
        'type' => 'Good',
        'price' => 380.00,
        'description' => 'PUKfm Rate Card 2026 - Live Reads (30 Seconds), Monday to Friday, 15:00 - 18:00. Price excludes VAT.',
    ],
    [
        'part_number' => 'LIVE-30S-MF-1800-2100',
        'name' => 'Live Read (30s) - Mon-Fri 18:00 - 21:00',
        'category_key' => 'live_reads',
        'type' => 'Good',
        'price' => 300.00,
        'description' => 'PUKfm Rate Card 2026 - Live Reads (30 Seconds), Monday to Friday, 18:00 - 21:00. Price excludes VAT.',
    ],
    [
        'part_number' => 'LIVE-30S-MF-2100-0000',
        'name' => 'Live Read (30s) - Mon-Fri 21:00 - 00:00',
        'category_key' => 'live_reads',
        'type' => 'Good',
        'price' => 250.00,
        'description' => 'PUKfm Rate Card 2026 - Live Reads (30 Seconds), Monday to Friday, 21:00 - 00:00. Price excludes VAT.',
    ],

    // Page 7: Radio Advertising Rates - Live Reads (30 Seconds) - Saturday and Sunday
    [
        'part_number' => 'LIVE-30S-SS-0600-1000',
        'name' => 'Live Read (30s) - Sat-Sun 06:00 - 10:00',
        'category_key' => 'live_reads',
        'type' => 'Good',
        'price' => 380.00,
        'description' => 'PUKfm Rate Card 2026 - Live Reads (30 Seconds), Saturday and Sunday, 06:00 - 10:00. Price excludes VAT.',
    ],
    [
        'part_number' => 'LIVE-30S-SS-1000-1400',
        'name' => 'Live Read (30s) - Sat-Sun 10:00 - 14:00',
        'category_key' => 'live_reads',
        'type' => 'Good',
        'price' => 350.00,
        'description' => 'PUKfm Rate Card 2026 - Live Reads (30 Seconds), Saturday and Sunday, 10:00 - 14:00. Price excludes VAT.',
    ],
    [
        'part_number' => 'LIVE-30S-SS-1400-1800',
        'name' => 'Live Read (30s) - Sat-Sun 14:00 - 18:00',
        'category_key' => 'live_reads',
        'type' => 'Good',
        'price' => 380.00,
        'description' => 'PUKfm Rate Card 2026 - Live Reads (30 Seconds), Saturday and Sunday, 14:00 - 18:00. Price excludes VAT.',
    ],
    [
        'part_number' => 'LIVE-30S-SS-1800-2200',
        'name' => 'Live Read (30s) - Sat-Sun 18:00 - 22:00',
        'category_key' => 'live_reads',
        'type' => 'Good',
        'price' => 250.00,
        'description' => 'PUKfm Rate Card 2026 - Live Reads (30 Seconds), Saturday and Sunday, 18:00 - 22:00. Price excludes VAT.',
    ],
    [
        'part_number' => 'LIVE-30S-SS-2200-0000',
        'name' => 'Live Read (30s) - Sat-Sun 22:00 - 00:00',
        'category_key' => 'live_reads',
        'type' => 'Good',
        'price' => 150.00,
        'description' => 'PUKfm Rate Card 2026 - Live Reads (30 Seconds), Saturday and Sunday, 22:00 - 00:00. Price excludes VAT.',
    ],

    // Page 8: Advertising Packages
    [
        'part_number' => 'PKG-WK-OPT1-50-REC',
        'name' => 'Weekly Package - Option 1: 50 Prime Time Recorded Ads',
        'category_key' => 'packages',
        'type' => 'Good',
        'price' => 5999.00,
        'description' => 'PUKfm Rate Card 2026 - Weekly Package (Option 1): 50 Prime Time Recorded Ads. Price excludes VAT.',
    ],
    [
        'part_number' => 'PKG-WK-OPT2-50-LIVE',
        'name' => 'Weekly Package - Option 2: 50 Prime Time Live Reads',
        'category_key' => 'packages',
        'type' => 'Good',
        'price' => 6999.00,
        'description' => 'PUKfm Rate Card 2026 - Weekly Package (Option 2): 50 Prime Time Live Reads. Price excludes VAT.',
    ],
    [
        'part_number' => 'PKG-MO-OPT3-30X4-REC',
        'name' => 'Monthly Package - Option 3: 30 Prime Time Recorded Ads x 4 weeks',
        'category_key' => 'packages',
        'type' => 'Good',
        'price' => 15999.00,
        'description' => 'PUKfm Rate Card 2026 - Monthly Package (Option 3): 30 Prime Time Recorded Ads x 4 weeks (120 ads total). Price excludes VAT.',
    ],
    [
        'part_number' => 'PKG-MO-OPT4-30X4-LIVE',
        'name' => 'Monthly Package - Option 4: 30 Prime Time Live Reads x 4 weeks',
        'category_key' => 'packages',
        'type' => 'Good',
        'price' => 16999.00,
        'description' => 'PUKfm Rate Card 2026 - Monthly Package (Option 4): 30 Prime Time Live Reads x 4 weeks (120 live reads total). Price excludes VAT.',
    ],

    // Page 11: Social Media Advertising Rates - Facebook
    [
        'part_number' => 'SOC-FB-SPONS-POST',
        'name' => 'Facebook - Sponsored Post',
        'category_key' => 'social_media',
        'type' => 'Good',
        'price' => 1000.00,
        'description' => 'PUKfm Rate Card 2026 - Social Media Advertising (Facebook @PUKfm936): Sponsored Post. Price excludes VAT.',
    ],
    [
        'part_number' => 'SOC-FB-STORY-POST-CMB',
        'name' => 'Facebook - Story/Post Combo',
        'category_key' => 'social_media',
        'type' => 'Good',
        'price' => 1200.00,
        'description' => 'PUKfm Rate Card 2026 - Social Media Advertising (Facebook @PUKfm936): Story/Post Combo. Price excludes VAT.',
    ],
    [
        'part_number' => 'SOC-FB-SPONS-PKG-4',
        'name' => 'Facebook - Sponsored Post Package (4 posts)',
        'category_key' => 'social_media',
        'type' => 'Good',
        'price' => 1950.00,
        'description' => 'PUKfm Rate Card 2026 - Social Media Advertising (Facebook @PUKfm936): Sponsored Post Package (4 posts). Price excludes VAT.',
    ],

    // Page 11: Social Media Advertising Rates - TikTok
    [
        'part_number' => 'SOC-TT-SPONS-POST',
        'name' => 'TikTok - Sponsored Post',
        'category_key' => 'social_media',
        'type' => 'Good',
        'price' => 400.00,
        'description' => 'PUKfm Rate Card 2026 - Social Media Advertising (TikTok @PUKfm936): Sponsored Post. Price excludes VAT.',
    ],
    [
        'part_number' => 'SOC-TT-STORY-POST-CMB',
        'name' => 'TikTok - Story/Post Combo',
        'category_key' => 'social_media',
        'type' => 'Good',
        'price' => 800.00,
        'description' => 'PUKfm Rate Card 2026 - Social Media Advertising (TikTok @PUKfm936): Story/Post Combo. Price excludes VAT.',
    ],
    [
        'part_number' => 'SOC-TT-SPONS-PKG-4',
        'name' => 'TikTok - Sponsored Post Package (4 posts)',
        'category_key' => 'social_media',
        'type' => 'Good',
        'price' => 1500.00,
        'description' => 'PUKfm Rate Card 2026 - Social Media Advertising (TikTok @PUKfm936): Sponsored Post Package (4 posts). Price excludes VAT.',
    ],

    // Page 11: Social Media Advertising Rates - Instagram
    [
        'part_number' => 'SOC-IG-SPONS-POST',
        'name' => 'Instagram - Sponsored Post',
        'category_key' => 'social_media',
        'type' => 'Good',
        'price' => 500.00,
        'description' => 'PUKfm Rate Card 2026 - Social Media Advertising (Instagram @PUKfm936): Sponsored Post. Price excludes VAT.',
    ],
    [
        'part_number' => 'SOC-IG-STORY-POST-CMB',
        'name' => 'Instagram - Story/Post Combo',
        'category_key' => 'social_media',
        'type' => 'Good',
        'price' => 700.00,
        'description' => 'PUKfm Rate Card 2026 - Social Media Advertising (Instagram @PUKfm936): Story/Post Combo. Price excludes VAT.',
    ],
    [
        'part_number' => 'SOC-IG-SPONS-PKG-4',
        'name' => 'Instagram - Sponsored Post Package (4 posts)',
        'category_key' => 'social_media',
        'type' => 'Good',
        'price' => 1500.00,
        'description' => 'PUKfm Rate Card 2026 - Social Media Advertising (Instagram @PUKfm936): Sponsored Post Package (4 posts). Price excludes VAT.',
    ],
    [
        'part_number' => 'SOC-IG-STORY',
        'name' => 'Instagram - Instagram Story',
        'category_key' => 'social_media',
        'type' => 'Good',
        'price' => 400.00,
        'description' => 'PUKfm Rate Card 2026 - Social Media Advertising (Instagram @PUKfm936): Instagram Story. Price excludes VAT.',
    ],

    // Page 11: Social Media Advertising Rates - Twitter (X)
    [
        'part_number' => 'SOC-TW-SPONS-TWEET',
        'name' => 'Twitter (X) - Sponsored Tweet',
        'category_key' => 'social_media',
        'type' => 'Good',
        'price' => 500.00,
        'description' => 'PUKfm Rate Card 2026 - Social Media Advertising (Twitter/X @PUKfm936): Sponsored Tweet. Price excludes VAT.',
    ],
    [
        'part_number' => 'SOC-TW-PINNED-1MO',
        'name' => 'Twitter (X) - Pinned Tweet (1 Month)',
        'category_key' => 'social_media',
        'type' => 'Good',
        'price' => 1000.00,
        'description' => 'PUKfm Rate Card 2026 - Social Media Advertising (Twitter/X @PUKfm936): Pinned Tweet (1 Month). Price excludes VAT.',
    ],

    // Page 12: Misc. Rates
    [
        'part_number' => 'MISC-INT-10MIN',
        'name' => '10 Minute Interview (In-person/Telephonic)',
        'category_key' => 'misc',
        'type' => 'Good',
        'price' => 2000.00,
        'description' => 'PUKfm Rate Card 2026 - Misc. Rates: 10 Minute Interview (In-person/Telephonic). Price excludes VAT.',
    ],
    [
        'part_number' => 'MISC-INT-30MIN',
        'name' => '30 Minute Interview (In-person/Telephonic)',
        'category_key' => 'misc',
        'type' => 'Good',
        'price' => 5000.00,
        'description' => 'PUKfm Rate Card 2026 - Misc. Rates: 30 Minute Interview (In-person/Telephonic). Price excludes VAT.',
    ],
    [
        'part_number' => 'MISC-POWERSPOT',
        'name' => 'Powerspot (Prime time shows)',
        'category_key' => 'misc',
        'type' => 'Good',
        'price' => 500.00,
        'description' => 'PUKfm Rate Card 2026 - Misc. Rates: Powerspot (Prime time shows). Price excludes VAT.',
    ],
    [
        'part_number' => 'MISC-SPONS-SEGMENT',
        'name' => 'Sponsored Content Segment (Prime time shows)',
        'category_key' => 'misc',
        'type' => 'Good',
        'price' => 2500.00,
        'description' => 'PUKfm Rate Card 2026 - Misc. Rates: Sponsored Content Segment (Prime time shows). Price excludes VAT.',
    ],
    [
        'part_number' => 'MISC-OB-3HR-POTCH',
        'name' => '3 Hour Outside Broadcast (Around Potchefstroom)',
        'category_key' => 'misc',
        'type' => 'Good',
        'price' => 15000.00,
        'description' => 'PUKfm Rate Card 2026 - Misc. Rates: 3 Hour Outside Broadcast (Around Potchefstroom) (with free spots worth R15,000). Price excludes VAT.',
    ],
    [
        'part_number' => 'MISC-SIMULCAST-3HR',
        'name' => '3 Hour Simulcast',
        'category_key' => 'misc',
        'type' => 'Good',
        'price' => 12000.00,
        'description' => 'PUKfm Rate Card 2026 - Misc. Rates: 3 Hour Simulcast (with free spots worth R12,000). Price excludes VAT.',
    ],
];

$seededRateCardCount = 0;
foreach ($rateCardProducts as $item) {
    $pn = $item['part_number'];
    $pnEsc = $db->quote($pn);
    $nameEsc = $db->quote($item['name']);
    $descEsc = $db->quote($item['description']);
    $typeEsc = $db->quote($item['type']);
    $catIdEsc = $db->quote($categoryIds[$item['category_key']] ?? '');
    $priceStr = number_format((float) $item['price'], 6, '.', '');

    $existingRes = $db->query(
        "SELECT id FROM aos_products WHERE part_number = '{$pnEsc}' AND deleted = 0 ORDER BY date_entered ASC",
        false
    );
    $existingRows = [];
    while ($existingRes && ($r = $db->fetchByAssoc($existingRes))) {
        $existingRows[] = $r;
    }

    if (!empty($existingRows)) {
        $primary = array_shift($existingRows);
        $primaryIdEsc = $db->quote((string) $primary['id']);
        $db->query(
            "UPDATE aos_products SET
                name = '{$nameEsc}',
                date_modified = '{$nowUtc}',
                description = '{$descEsc}',
                type = '{$typeEsc}',
                price = {$priceStr},
                price_usdollar = {$priceStr},
                cost = 0,
                cost_usdollar = 0,
                currency_id = '-99',
                aos_product_category_id = '{$catIdEsc}',
                deleted = 0
             WHERE id = '{$primaryIdEsc}'",
            false
        );
        foreach ($existingRows as $dup) {
            $dupIdEsc = $db->quote((string) $dup['id']);
            $db->query("UPDATE aos_products SET deleted = 1, date_modified = '{$nowUtc}' WHERE id = '{$dupIdEsc}'", false);
        }
    } else {
        $newId = RadioQuoteScheduleProducts::generateUuid();
        $newIdEsc = $db->quote($newId);
        $db->query(
            "INSERT INTO aos_products (
                id, name, date_entered, date_modified, modified_user_id, created_by,
                description, deleted, assigned_user_id, part_number, type,
                cost, cost_usdollar, price, price_usdollar, currency_id, aos_product_category_id
            ) VALUES (
                '{$newIdEsc}', '{$nameEsc}', '{$nowUtc}', '{$nowUtc}', '1', '1',
                '{$descEsc}', 0, '1', '{$pnEsc}', '{$typeEsc}',
                0, 0, {$priceStr}, {$priceStr}, '-99', '{$catIdEsc}'
            )",
            false
        );
    }
    $seededRateCardCount++;
}

echo "  = Seeded/verified {$seededRateCardCount} PUKfm Rate Card 2026 products across " . count($categoryIds) . " categories.\n";

// Ensure at least one active AOS_Quotes PDF template exists in aos_pdf_templates so native Quote PDF generation works
$tplRes = $db->query(
    "SELECT id FROM aos_pdf_templates WHERE type = 'AOS_Quotes' AND deleted = 0 AND active = 1 LIMIT 1",
    false
);
$tplRow = $tplRes ? $db->fetchByAssoc($tplRes) : null;
if (!$tplRow) {
    echo "  + Creating default AOS_Quotes PDF template in aos_pdf_templates...\n";
    $tplId = RadioQuoteScheduleProducts::generateUuid();
    $nowUtc = gmdate('Y-m-d H:i:s');
    $headerHtml = '<p><strong>PUKfm Radio Quotation</strong></p>';
    $bodyHtml = <<<'HTML'
<h2>Quotation: $aos_quotes_name (Quote #$aos_quotes_number)</h2>
<p><strong>Campaign Start Date:</strong> $aos_quotes_campaign_start_date_c &nbsp;&nbsp; <strong>Campaign End Date:</strong> $aos_quotes_campaign_end_date_c</p>
<p><strong>Account:</strong> $aos_quotes_billing_account &nbsp;&nbsp; <strong>Contact:</strong> $aos_quotes_billing_contact</p>
<hr />
<table style="width: 100%; border-collapse: collapse;" border="1" cellpadding="6">
    <thead>
        <tr>
            <th style="text-align: left;">Qty</th>
            <th style="text-align: left;">Product</th>
            <th style="text-align: left;">Part Number</th>
            <th style="text-align: left;">Description</th>
            <th style="text-align: right;">Unit Price</th>
            <th style="text-align: right;">VAT</th>
            <th style="text-align: right;">Total</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>$aos_products_quotes_product_qty</td>
            <td>$aos_products_quotes_name</td>
            <td>$aos_products_quotes_part_number</td>
            <td>$aos_products_quotes_item_description</td>
            <td style="text-align: right;">$aos_products_quotes_product_unit_price</td>
            <td style="text-align: right;">$aos_products_quotes_vat_amt</td>
            <td style="text-align: right;">$aos_products_quotes_product_total_price</td>
        </tr>
    </tbody>
</table>
<hr />
<p style="text-align: right;">
    <strong>Subtotal (Net):</strong> $aos_quotes_subtotal_amount<br />
    <strong>VAT (15%):</strong> $aos_quotes_tax_amount<br />
    <strong>Grand Total (Incl. VAT):</strong> $aos_quotes_total_amount
</p>
HTML;
    $footerHtml = '<p style="font-size: 10px;">Generated by PUKfm Radio CRM</p>';

    $sql = sprintf(
        "INSERT INTO aos_pdf_templates (
            id, name, date_entered, date_modified, modified_user_id, created_by,
            description, deleted, assigned_user_id, active, type,
            pdfheader, pdffooter, margin_left, margin_right, margin_top, margin_bottom,
            margin_header, margin_footer, page_size, orientation
        ) VALUES (
            '%s', 'PUKfm Standard Quote PDF', '%s', '%s', '1', '1',
            '%s', 0, '1', 1, 'AOS_Quotes',
            '%s', '%s', 15, 15, 16, 16,
            9, 9, 'A4', 'Portrait'
        )",
        $db->quote($tplId),
        $nowUtc,
        $nowUtc,
        $db->quote($bodyHtml),
        $db->quote($headerHtml),
        $db->quote($footerHtml)
    );
    $db->query($sql, false);
}

echo "[Migration 003] Complete.\n";

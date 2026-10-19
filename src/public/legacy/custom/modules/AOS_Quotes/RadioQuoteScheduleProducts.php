<?php

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

require_once 'custom/include/RadioCRM/RadioScheduleService.php';

use RadioCRM\RadioScheduleService;

class RadioQuoteScheduleProducts
{
    /** @var array<string, bool> Guard against save recursion */
    private static array $inProgress = [];

    /** @var array<string, bool> Track shutdown registration per Quote ID */
    private static array $shutdownRegistered = [];

    /**
     * Clean radio line description so verbose schedule text never appears in Quote or PDF (Section 11.1).
     */
    public static function cleanRadioLineDescription(?string $description, string $partNumber = ''): string
    {
        return RadioScheduleService::cleanRadioLineDescription($description, $partNumber);
    }

    /**
     * Logic hook entry point registered on AOS_Quotes after_retrieve.
     * Ensures campaign_start_date_c, campaign_end_date_c, and all 168 schedule fields
     * are populated onto the bean from aos_quotes_cstm whenever a Quote is retrieved.
     */
    public function loadScheduleFields($bean, $event, $arguments): void
    {
        if (!is_object($bean) || empty($bean->id)) {
            return;
        }
        $db = $GLOBALS['db'] ?? null;
        if (!is_object($db)) {
            return;
        }
        $qIdEsc = $db->quote((string) $bean->id);
        $res = $db->query("SELECT * FROM aos_quotes_cstm WHERE id_c = '{$qIdEsc}'", false);
        $row = $res ? $db->fetchByAssoc($res) : null;
        if (!$row) {
            return;
        }
        if (array_key_exists('campaign_start_date_c', $row)) {
            $bean->campaign_start_date_c = $row['campaign_start_date_c'];
        }
        if (array_key_exists('campaign_end_date_c', $row)) {
            $bean->campaign_end_date_c = $row['campaign_end_date_c'];
        }
        if (array_key_exists('industry_category_c', $row)) {
            $bean->industry_category_c = $row['industry_category_c'];
        }
        if (array_key_exists('spot_duration_c', $row)) {
            $bean->spot_duration_c = $row['spot_duration_c'];
        }
        if (array_key_exists('applied_package_c', $row)) {
            $bean->applied_package_c = $row['applied_package_c'];
        }
        foreach (RadioScheduleService::getAllScheduleFields() as $fieldName => $meta) {
            if (array_key_exists($fieldName, $row)) {
                $bean->$fieldName = (int) ($row[$fieldName] ?? 0);
            }
        }
    }

    /**
     * Logic hook entry point registered on AOS_Quotes after_save.
     */
    public function syncSelectedProducts($bean, $event, $arguments): void
    {
        if (!is_object($bean) || empty($bean->id)) {
            return;
        }
        $quoteId = (string) $bean->id;
        if (!empty(self::$inProgress[$quoteId])) {
            return;
        }

        // Capture any schedule inputs posted from EditView form onto the bean & cstm table
        self::persistRequestScheduleFieldsIfPresent($bean);

        // Run immediate sync
        self::syncQuoteById($quoteId, $bean);

        // Also register a deferred shutdown sync so that if AOS_Quotes::save() runs save_lines($_POST)
        // AFTER parent::save() (which fires after_save hooks), our authoritative sync runs last and deduplicates.
        if (empty(self::$shutdownRegistered[$quoteId])) {
            self::$shutdownRegistered[$quoteId] = true;
            register_shutdown_function(static function () use ($quoteId): void {
                try {
                    self::syncQuoteById($quoteId, null);
                } catch (\Throwable $e) {
                    // Do not break shutdown
                }
            });
        }
    }

    /**
     * Ensure that if an EditView form POST submitted schedule fields or dates,
     * they are persisted to aos_quotes_cstm even if custom_fields->save() ran before or missed any field.
     */
    public static function persistRequestScheduleFieldsIfPresent(object $bean): void
    {
        if (empty($bean->id) || empty($_REQUEST)) {
            return;
        }
        // Only apply when saving an AOS_Quotes record from a request that includes schedule fields or dates
        $hasScheduleInRequest = isset($_REQUEST['campaign_start_date_c']) || isset($_REQUEST['campaign_end_date_c']);
        $allFields = RadioScheduleService::getAllScheduleFields();
        if (!$hasScheduleInRequest) {
            foreach ($allFields as $f => $m) {
                if (isset($_REQUEST[$f])) {
                    $hasScheduleInRequest = true;
                    break;
                }
            }
        }
        if (!$hasScheduleInRequest) {
            return;
        }

        $db = $GLOBALS['db'] ?? null;
        if (!is_object($db)) {
            return;
        }

        $quoteId = $db->quote((string) $bean->id);
        $res = $db->query("SELECT id_c FROM aos_quotes_cstm WHERE id_c = '{$quoteId}'", false);
        $row = $res ? $db->fetchByAssoc($res) : false;
        if (!$row) {
            $db->query("INSERT INTO aos_quotes_cstm (id_c) VALUES ('{$quoteId}')", false);
        }

        $updates = [];
        if (array_key_exists('campaign_start_date_c', $_REQUEST)) {
            $normStart = RadioScheduleService::normalizeDate((string) $_REQUEST['campaign_start_date_c']);
            $bean->campaign_start_date_c = $normStart;
            $updates[] = $normStart === null
                ? "campaign_start_date_c = NULL"
                : "campaign_start_date_c = '" . $db->quote($normStart) . "'";
        }
        if (array_key_exists('campaign_end_date_c', $_REQUEST)) {
            $normEnd = RadioScheduleService::normalizeDate((string) $_REQUEST['campaign_end_date_c']);
            $bean->campaign_end_date_c = $normEnd;
            $updates[] = $normEnd === null
                ? "campaign_end_date_c = NULL"
                : "campaign_end_date_c = '" . $db->quote($normEnd) . "'";
        }

        foreach ($allFields as $fieldName => $meta) {
            if (array_key_exists($fieldName, $_REQUEST)) {
                $isInv = false;
                $qty = RadioScheduleService::parseCellQuantity($_REQUEST[$fieldName], $isInv);
                $bean->$fieldName = $qty;
                $updates[] = "{$fieldName} = " . (int) $qty;
            }
        }

        if (array_key_exists('industry_category_c', $_REQUEST)) {
            $cat = trim((string) $_REQUEST['industry_category_c']);
            $bean->industry_category_c = $cat;
            $updates[] = "industry_category_c = '" . $db->quote($cat) . "'";
        }
        if (array_key_exists('spot_duration_c', $_REQUEST)) {
            $dur = trim((string) $_REQUEST['spot_duration_c']);
            $bean->spot_duration_c = $dur;
            $updates[] = "spot_duration_c = '" . $db->quote($dur) . "'";
        }
        if (array_key_exists('applied_package_c', $_REQUEST)) {
            $pkg = trim((string) $_REQUEST['applied_package_c']);
            $bean->applied_package_c = $pkg;
            $updates[] = "applied_package_c = '" . $db->quote($pkg) . "'";
        }

        if (!empty($updates)) {
            $sql = "UPDATE aos_quotes_cstm SET " . implode(', ', $updates) . " WHERE id_c = '{$quoteId}'";
            $db->query($sql, false);
        }
    }

    /**
     * Authoritative, idempotent synchronization of schedule -> aos_products_quotes and Quote totals.
     */
    public static function syncQuoteById(string $quoteId, ?object $bean = null): void
    {
        if ($quoteId === '' || !empty(self::$inProgress[$quoteId])) {
            return;
        }
        $db = $GLOBALS['db'] ?? null;
        if (!is_object($db)) {
            return;
        }

        self::$inProgress[$quoteId] = true;
        try {
            $qIdEsc = $db->quote($quoteId);

            // 1. Load Quote + custom fields from DB
            $qRes = $db->query("SELECT id, assigned_user_id, deleted FROM aos_quotes WHERE id = '{$qIdEsc}'", false);
            $qRow = $qRes ? $db->fetchByAssoc($qRes) : null;
            if (!$qRow || !empty($qRow['deleted'])) {
                return;
            }

            $cstmRes = $db->query("SELECT * FROM aos_quotes_cstm WHERE id_c = '{$qIdEsc}'", false);
            $cstmRow = ($cstmRes ? $db->fetchByAssoc($cstmRes) : null) ?: [];

            $allFields = RadioScheduleService::getAllScheduleFields();
            $inputData = [];
            foreach ($allFields as $fieldName => $meta) {
                if ($bean !== null && isset($bean->$fieldName)) {
                    $inputData[$fieldName] = $bean->$fieldName;
                } elseif (array_key_exists($fieldName, $cstmRow) && $cstmRow[$fieldName] !== null) {
                    $inputData[$fieldName] = $cstmRow[$fieldName];
                } else {
                    $inputData[$fieldName] = 0;
                }
            }

            $startDate = ($bean !== null && property_exists($bean, 'campaign_start_date_c') && $bean->campaign_start_date_c !== null)
                ? $bean->campaign_start_date_c
                : ($cstmRow['campaign_start_date_c'] ?? null);
            $endDate = ($bean !== null && property_exists($bean, 'campaign_end_date_c') && $bean->campaign_end_date_c !== null)
                ? $bean->campaign_end_date_c
                : ($cstmRow['campaign_end_date_c'] ?? null);

            // Persist bean custom fields to aos_quotes_cstm if cstm row was missing or changed on bean
            if ($bean !== null) {
                if (empty($cstmRow)) {
                    $db->query("INSERT INTO aos_quotes_cstm (id_c) VALUES ('{$qIdEsc}')", false);
                }
                $cstmUpdates = [];
                $normStart = RadioScheduleService::normalizeDate((string) ($startDate ?? ''));
                $normEnd = RadioScheduleService::normalizeDate((string) ($endDate ?? ''));
                if (($cstmRow['campaign_start_date_c'] ?? null) !== $normStart) {
                    $cstmUpdates[] = $normStart === null
                        ? "campaign_start_date_c = NULL"
                        : "campaign_start_date_c = '" . $db->quote($normStart) . "'";
                }
                if (($cstmRow['campaign_end_date_c'] ?? null) !== $normEnd) {
                    $cstmUpdates[] = $normEnd === null
                        ? "campaign_end_date_c = NULL"
                        : "campaign_end_date_c = '" . $db->quote($normEnd) . "'";
                }
                foreach ($allFields as $f => $m) {
                    $isInv = false;
                    $val = RadioScheduleService::parseCellQuantity($inputData[$f], $isInv);
                    if (!array_key_exists($f, $cstmRow) || (int) $cstmRow[$f] !== $val) {
                        $cstmUpdates[] = "{$f} = " . (int) $val;
                    }
                }
                if (!empty($cstmUpdates)) {
                    $db->query(
                        "UPDATE aos_quotes_cstm SET " . implode(', ', $cstmUpdates) . " WHERE id_c = '{$qIdEsc}'",
                        false
                    );
                }
            }

            // 2. Ensure all 8 catalogue products exist and fetch their metadata
            self::ensureCatalogueProductsExist($db);
            $catalogue = RadioScheduleService::getCatalogueProducts($db);

            $spotDuration = ($bean !== null && property_exists($bean, 'spot_duration_c') && !empty($bean->spot_duration_c))
                ? (string) $bean->spot_duration_c
                : (string) ($cstmRow['spot_duration_c'] ?? '30 sec');

            $appliedPackage = ($bean !== null && property_exists($bean, 'applied_package_c') && !empty($bean->applied_package_c))
                ? (string) $bean->applied_package_c
                : (string) ($cstmRow['applied_package_c'] ?? '');

            // 3. Calculate schedule quantities across inclusive date range
            $calc = RadioScheduleService::calculateSchedule(
                $inputData,
                $startDate !== null ? (string) $startDate : null,
                $endDate !== null ? (string) $endDate : null,
                $db,
                $spotDuration,
                $appliedPackage
            );

            // Map desired programme quantities by part_number
            $desiredByPart = [];
            foreach ($calc['programmes'] as $prog) {
                $desiredByPart[$prog['part_number']] = $prog;
            }

            // Build lookup of radio product IDs -> part_number
            $radioPartByProductId = [];
            foreach ($catalogue as $pn => $catItem) {
                if (!empty($catItem['id'])) {
                    $radioPartByProductId[$catItem['id']] = $pn;
                }
            }

            // 4. Ensure a line item group exists for this Quote if we have active products
            $groupId = self::ensureQuoteLineGroup($db, $quoteId, (string) ($qRow['assigned_user_id'] ?? '1'));

            // 5. Inspect existing non-deleted aos_products_quotes rows for this Quote
            $linesSql = "SELECT id, number, group_id, part_number, product_id, name, description, item_description
                         FROM aos_products_quotes
                         WHERE parent_type = 'AOS_Quotes' AND parent_id = '{$qIdEsc}' AND deleted = 0
                         ORDER BY number ASC, date_entered ASC";
            $linesRes = $db->query($linesSql, false);

            $existingRadioRowsByPart = [];
            $maxLineNumber = 0;

            if ($linesRes) {
                while ($line = $db->fetchByAssoc($linesRes)) {
                    $num = (int) ($line['number'] ?? 0);
                    if ($num > $maxLineNumber) {
                        $maxLineNumber = $num;
                    }
                    $linePart = strtoupper(trim((string) ($line['part_number'] ?? '')));
                    $lineProdId = trim((string) ($line['product_id'] ?? ''));
                    if ($linePart === '' && isset($radioPartByProductId[$lineProdId])) {
                        $linePart = $radioPartByProductId[$lineProdId];
                    }

                    if (isset($desiredByPart[$linePart])) {
                        $existingRadioRowsByPart[$linePart][] = $line;
                    }
                }
            }

            $nowUtc = gmdate('Y-m-d H:i:s');
            $assignedUserId = $db->quote((string) ($qRow['assigned_user_id'] ?? '1'));

            // 6. Create, update, or soft-delete rows for each of the 8 programmes
            $lineOrder = 1;
            foreach (RadioScheduleService::SLOTS as $slotKey => $slot) {
                $pn = $slot['part_number'];
                $prog = $desiredByPart[$pn];
                $qty = $calc['date_range_valid'] ? (int) $prog['total_qty'] : 0;
                $existingRows = $existingRadioRowsByPart[$pn] ?? [];

                if ($qty > 0) {
                    $cleanDesc = self::cleanRadioLineDescription($prog['description'], $pn);
                    $unitPrice = round((float) $prog['unit_price'], 4);
                    $netTotal = round((float) $prog['net_total'], 2);
                    $vatAmt = round((float) $prog['vat_amt'], 2);
                    $prodId = (string) ($prog['product_id'] ?? '');
                    $prodName = (string) ($prog['product_name'] ?: $pn);

                    if (!empty($existingRows)) {
                        // Keep and update the first row; soft-delete any duplicates
                        $primary = array_shift($existingRows);
                        $primaryIdEsc = $db->quote((string) $primary['id']);
                        $rowGroupId = !empty($primary['group_id']) ? (string) $primary['group_id'] : $groupId;

                        $updateSql = sprintf(
                            "UPDATE aos_products_quotes SET
                                date_modified = '%s',
                                number = %d,
                                group_id = '%s',
                                name = '%s',
                                part_number = '%s',
                                product_id = '%s',
                                product_qty = %s,
                                product_cost_price = %s,
                                product_cost_price_usdollar = %s,
                                product_list_price = %s,
                                product_list_price_usdollar = %s,
                                product_unit_price = %s,
                                product_unit_price_usdollar = %s,
                                product_total_price = %s,
                                product_total_price_usdollar = %s,
                                vat = '%s',
                                vat_amt = %s,
                                vat_amt_usdollar = %s,
                                discount = 'Percentage',
                                product_discount = 0,
                                product_discount_usdollar = 0,
                                product_discount_amount = 0,
                                product_discount_amount_usdollar = 0,
                                description = '%s',
                                item_description = '%s',
                                deleted = 0
                             WHERE id = '%s'",
                            $nowUtc,
                            $lineOrder,
                            $db->quote($rowGroupId),
                            $db->quote($prodName),
                            $db->quote($pn),
                            $db->quote($prodId),
                            number_format((float) $qty, 4, '.', ''),
                            number_format(0.0, 6, '.', ''),
                            number_format(0.0, 6, '.', ''),
                            number_format($unitPrice, 6, '.', ''),
                            number_format($unitPrice, 6, '.', ''),
                            number_format($unitPrice, 6, '.', ''),
                            number_format($unitPrice, 6, '.', ''),
                            number_format($netTotal, 6, '.', ''),
                            number_format($netTotal, 6, '.', ''),
                            $db->quote(RadioScheduleService::DEFAULT_VAT_STRING),
                            number_format($vatAmt, 6, '.', ''),
                            number_format($vatAmt, 6, '.', ''),
                            $db->quote($cleanDesc),
                            $db->quote($cleanDesc),
                            $primaryIdEsc
                        );
                        $db->query($updateSql, false);

                        foreach ($existingRows as $dup) {
                            $dupIdEsc = $db->quote((string) $dup['id']);
                            $db->query(
                                "UPDATE aos_products_quotes SET deleted = 1, date_modified = '{$nowUtc}' WHERE id = '{$dupIdEsc}'",
                                false
                            );
                        }
                    } else {
                        // Insert new row for this programme
                        $newLineId = self::generateUuid();
                        $insertSql = sprintf(
                            "INSERT INTO aos_products_quotes (
                                id, name, date_entered, date_modified, modified_user_id, created_by,
                                description, item_description, deleted, assigned_user_id,
                                currency_id, part_number, number, product_qty,
                                product_cost_price, product_cost_price_usdollar,
                                product_list_price, product_list_price_usdollar,
                                product_discount, product_discount_usdollar,
                                product_discount_amount, product_discount_amount_usdollar,
                                discount, product_unit_price, product_unit_price_usdollar,
                                vat_amt, vat_amt_usdollar,
                                product_total_price, product_total_price_usdollar,
                                vat, parent_type, parent_id, product_id, group_id
                            ) VALUES (
                                '%s', '%s', '%s', '%s', '%s', '%s',
                                '%s', '%s', 0, '%s',
                                '-99', '%s', %d, %s,
                                %s, %s,
                                %s, %s,
                                0, 0,
                                0, 0,
                                'Percentage', %s, %s,
                                %s, %s,
                                %s, %s,
                                '%s', 'AOS_Quotes', '%s', '%s', '%s'
                            )",
                            $db->quote($newLineId),
                            $db->quote($prodName),
                            $nowUtc,
                            $nowUtc,
                            $assignedUserId,
                            $assignedUserId,
                            $db->quote($cleanDesc),
                            $db->quote($cleanDesc),
                            $assignedUserId,
                            $db->quote($pn),
                            $lineOrder,
                            number_format((float) $qty, 4, '.', ''),
                            number_format(0.0, 6, '.', ''),
                            number_format(0.0, 6, '.', ''),
                            number_format($unitPrice, 6, '.', ''),
                            number_format($unitPrice, 6, '.', ''),
                            number_format($unitPrice, 6, '.', ''),
                            number_format($unitPrice, 6, '.', ''),
                            number_format($vatAmt, 6, '.', ''),
                            number_format($vatAmt, 6, '.', ''),
                            number_format($netTotal, 6, '.', ''),
                            number_format($netTotal, 6, '.', ''),
                            $db->quote(RadioScheduleService::DEFAULT_VAT_STRING),
                            $qIdEsc,
                            $db->quote($prodId),
                            $db->quote($groupId)
                        );
                        $db->query($insertSql, false);
                    }
                    $lineOrder++;
                } else {
                    // Programme has 0 quantity: soft-delete any existing schedule-generated rows for this programme
                    foreach ($existingRows as $oldRow) {
                        $oldIdEsc = $db->quote((string) $oldRow['id']);
                        $db->query(
                            "UPDATE aos_products_quotes SET deleted = 1, date_modified = '{$nowUtc}' WHERE id = '{$oldIdEsc}'",
                            false
                        );
                    }
                }
            }

            // 7. Recompute Quote and Line Item Group totals across all remaining non-deleted lines (including manual products)
            self::recomputeQuoteTotals($db, $quoteId, $groupId, $bean);
        } finally {
            unset(self::$inProgress[$quoteId]);
        }
    }

    /**
     * Ensure all 8 radio products exist in aos_products without duplication (Section 7.1).
     */
    public static function ensureCatalogueProductsExist(object $db): void
    {
        $nowUtc = gmdate('Y-m-d H:i:s');
        foreach (RadioScheduleService::SLOTS as $slot) {
            $pn = $slot['part_number'];
            $pnEsc = $db->quote($pn);
            $res = $db->query(
                "SELECT id, description FROM aos_products WHERE part_number = '{$pnEsc}' AND deleted = 0 LIMIT 1",
                false
            );
            $row = $res ? $db->fetchByAssoc($res) : null;
            if (!$row) {
                $id = self::generateUuid();
                $nameEsc = $db->quote($slot['product_name']);
                $descEsc = $db->quote($slot['description']);
                $priceStr = number_format((float) $slot['default_rate'], 6, '.', '');
                $sql = "INSERT INTO aos_products (
                            id, name, date_entered, date_modified, modified_user_id, created_by,
                            description, deleted, assigned_user_id, part_number, type,
                            cost, cost_usdollar, price, price_usdollar, currency_id
                        ) VALUES (
                            '{$id}', '{$nameEsc}', '{$nowUtc}', '{$nowUtc}', '1', '1',
                            '{$descEsc}', 0, '1', '{$pnEsc}', 'Good',
                            0, 0, {$priceStr}, {$priceStr}, '-99'
                        )";
                $db->query($sql, false);
            } else {
                // Ensure existing catalogue product description is clean of verbose schedule summaries
                $cleanDesc = self::cleanRadioLineDescription((string) ($row['description'] ?? ''), $pn);
                if ($cleanDesc !== (string) ($row['description'] ?? '')) {
                    $idEsc = $db->quote((string) $row['id']);
                    $descEsc = $db->quote($cleanDesc);
                    $db->query("UPDATE aos_products SET description = '{$descEsc}' WHERE id = '{$idEsc}'", false);
                }
            }
        }
    }

    /**
     * Ensure at least one non-deleted aos_line_item_groups row exists for this Quote.
     */
    private static function ensureQuoteLineGroup(object $db, string $quoteId, string $assignedUserId): string
    {
        $qIdEsc = $db->quote($quoteId);
        $res = $db->query(
            "SELECT id FROM aos_line_item_groups WHERE parent_type = 'AOS_Quotes' AND parent_id = '{$qIdEsc}' AND deleted = 0 ORDER BY number ASC, date_entered ASC LIMIT 1",
            false
        );
        $row = $res ? $db->fetchByAssoc($res) : null;
        if ($row && !empty($row['id'])) {
            return (string) $row['id'];
        }

        $groupId = self::generateUuid();
        $nowUtc = gmdate('Y-m-d H:i:s');
        $userEsc = $db->quote($assignedUserId ?: '1');
        $sql = "INSERT INTO aos_line_item_groups (
                    id, name, date_entered, date_modified, modified_user_id, created_by,
                    deleted, assigned_user_id, total_amt, total_amt_usdollar,
                    discount_amount, discount_amount_usdollar,
                    subtotal_amount, subtotal_amount_usdollar,
                    tax_amount, tax_amount_usdollar,
                    subtotal_tax_amount, subtotal_tax_amount_usdollar,
                    total_amount, total_amount_usdollar,
                    parent_type, parent_id, number, currency_id
                ) VALUES (
                    '{$groupId}', '', '{$nowUtc}', '{$nowUtc}', '{$userEsc}', '{$userEsc}',
                    0, '{$userEsc}', 0, 0,
                    0, 0,
                    0, 0,
                    0, 0,
                    0, 0,
                    0, 0,
                    'AOS_Quotes', '{$qIdEsc}', 1, '-99'
                )";
        $db->query($sql, false);
        return $groupId;
    }

    /**
     * Recompute Quote and group financial totals from all active aos_products_quotes rows.
     */
    private static function recomputeQuoteTotals(
        object $db,
        string $quoteId,
        string $defaultGroupId,
        ?object $bean = null
    ): void {
        $qIdEsc = $db->quote($quoteId);

        // Assign any ungrouped non-deleted lines on this Quote to $defaultGroupId so PDF & DetailView render them
        $grpEsc = $db->quote($defaultGroupId);
        $db->query(
            "UPDATE aos_products_quotes
             SET group_id = '{$grpEsc}'
             WHERE parent_type = 'AOS_Quotes' AND parent_id = '{$qIdEsc}' AND deleted = 0
               AND (group_id IS NULL OR group_id = '')",
            false
        );

        $sumSql = "SELECT
                       COALESCE(SUM(product_total_price), 0) AS net_sum,
                       COALESCE(SUM(product_discount_amount * product_qty), 0) AS disc_sum,
                       COALESCE(SUM(vat_amt), 0) AS tax_sum
                   FROM aos_products_quotes
                   WHERE parent_type = 'AOS_Quotes' AND parent_id = '{$qIdEsc}' AND deleted = 0";
        $res = $db->query($sumSql, false);
        $row = ($res ? $db->fetchByAssoc($res) : null) ?: [];

        $subtotalAmt = round((float) ($row['net_sum'] ?? 0.0), 2);
        $discountAmt = round((float) ($row['disc_sum'] ?? 0.0), 2);
        $totalAmt = round($subtotalAmt + $discountAmt, 2);
        $taxAmt = round((float) ($row['tax_sum'] ?? 0.0), 2);
        $subtotalTaxAmt = round($subtotalAmt + $taxAmt, 2);

        // Check shipping if set on Quote
        $shipRes = $db->query(
            "SELECT COALESCE(shipping_amount, 0) AS shipping_amount, COALESCE(shipping_tax_amt, 0) AS shipping_tax_amt
             FROM aos_quotes WHERE id = '{$qIdEsc}'",
            false
        );
        $shipRow = ($shipRes ? $db->fetchByAssoc($shipRes) : null) ?: [];
        $shippingAmt = round((float) ($shipRow['shipping_amount'] ?? 0.0), 2);
        $shippingTaxAmt = round((float) ($shipRow['shipping_tax_amt'] ?? 0.0), 2);

        $grandTotal = round($subtotalAmt + $taxAmt + $shippingAmt + $shippingTaxAmt, 2);

        $fmt = static fn(float $v): string => number_format($v, 6, '.', '');

        $updateQuoteSql = sprintf(
            "UPDATE aos_quotes SET
                total_amt = %s,
                total_amt_usdollar = %s,
                discount_amount = %s,
                discount_amount_usdollar = %s,
                subtotal_amount = %s,
                subtotal_amount_usdollar = %s,
                tax_amount = %s,
                tax_amount_usdollar = %s,
                subtotal_tax_amount = %s,
                subtotal_tax_amount_usdollar = %s,
                total_amount = %s,
                total_amount_usdollar = %s
             WHERE id = '%s'",
            $fmt($totalAmt),
            $fmt($totalAmt),
            $fmt($discountAmt),
            $fmt($discountAmt),
            $fmt($subtotalAmt),
            $fmt($subtotalAmt),
            $fmt($taxAmt),
            $fmt($taxAmt),
            $fmt($subtotalTaxAmt),
            $fmt($subtotalTaxAmt),
            $fmt($grandTotal),
            $fmt($grandTotal),
            $qIdEsc
        );
        $db->query($updateQuoteSql, false);

        // Update group totals
        $updateGroupSql = sprintf(
            "UPDATE aos_line_item_groups SET
                total_amt = %s,
                total_amt_usdollar = %s,
                discount_amount = %s,
                discount_amount_usdollar = %s,
                subtotal_amount = %s,
                subtotal_amount_usdollar = %s,
                tax_amount = %s,
                tax_amount_usdollar = %s,
                subtotal_tax_amount = %s,
                subtotal_tax_amount_usdollar = %s,
                total_amount = %s,
                total_amount_usdollar = %s
             WHERE id = '%s'",
            $fmt($totalAmt),
            $fmt($totalAmt),
            $fmt($discountAmt),
            $fmt($discountAmt),
            $fmt($subtotalAmt),
            $fmt($subtotalAmt),
            $fmt($taxAmt),
            $fmt($taxAmt),
            $fmt($subtotalTaxAmt),
            $fmt($subtotalTaxAmt),
            $fmt($subtotalTaxAmt),
            $fmt($subtotalTaxAmt),
            $grpEsc
        );
        $db->query($updateGroupSql, false);

        if ($bean !== null) {
            $bean->total_amt = $totalAmt;
            $bean->discount_amount = $discountAmt;
            $bean->subtotal_amount = $subtotalAmt;
            $bean->tax_amount = $taxAmt;
            $bean->subtotal_tax_amount = $subtotalTaxAmt;
            $bean->total_amount = $grandTotal;
        }
    }

    /**
     * Generate a standard 36-char UUID v4.
     */
    public static function generateUuid(): string
    {
        if (function_exists('create_guid')) {
            return (string) create_guid();
        }
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}

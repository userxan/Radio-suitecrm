<?php

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

require_once 'custom/include/RadioCRM/RadioScheduleService.php';
require_once 'custom/modules/AOS_Quotes/RadioQuoteScheduleProducts.php';

use RadioCRM\RadioScheduleService;

class RadioCampaignConverter
{
    /**
     * Idempotently convert or update a Radio Campaign from a saved AOS_Quotes record (Section 12).
     *
     * @return array{success:bool, message:string, campaign_id?:string, created?:bool, campaign?:array<string,mixed>}
     */
    public static function convertQuoteToCampaign(string $quoteId): array
    {
        global $db, $current_user;

        $quoteId = trim($quoteId);
        if ($quoteId === '') {
            return ['success' => false, 'message' => 'Quote record ID is required.'];
        }

        if (class_exists('ACLController')) {
            if (!ACLController::checkAccess('AOS_Quotes', 'view', true)
                || !ACLController::checkAccess('RT_RadioCampaigns', 'edit', true)
            ) {
                return ['success' => false, 'message' => 'Access denied for Quote conversion.'];
            }
        }

        $quote = BeanFactory::getBean('AOS_Quotes', $quoteId);
        if (empty($quote) || empty($quote->id) || !empty($quote->deleted)) {
            return ['success' => false, 'message' => 'Quote record not found.'];
        }
        if (method_exists($quote, 'ACLAccess') && !$quote->ACLAccess('view')) {
            return ['success' => false, 'message' => 'You do not have permission to view this Quote.'];
        }

        // Load custom schedule fields from aos_quotes_cstm + bean
        $qIdEsc = $db->quote((string) $quote->id);
        $cstmRes = $db->query("SELECT * FROM aos_quotes_cstm WHERE id_c = '{$qIdEsc}'", false);
        $cstmRow = ($cstmRes ? $db->fetchByAssoc($cstmRes) : null) ?: [];

        $inputData = [];
        foreach (RadioScheduleService::getAllScheduleFields() as $fieldName => $meta) {
            $inputData[$fieldName] = $cstmRow[$fieldName] ?? ($quote->$fieldName ?? 0);
        }

        $startDate = $cstmRow['campaign_start_date_c'] ?? ($quote->campaign_start_date_c ?? null);
        $endDate = $cstmRow['campaign_end_date_c'] ?? ($quote->campaign_end_date_c ?? null);

        $calc = RadioScheduleService::calculateSchedule(
            $inputData,
            $startDate !== null ? (string) $startDate : null,
            $endDate !== null ? (string) $endDate : null,
            $db,
            $cstmRow['spot_duration_c'] ?? ($quote->spot_duration_c ?? '30 sec'),
            $cstmRow['applied_package_c'] ?? ($quote->applied_package_c ?? null)
        );

        if (!$calc['valid']) {
            return [
                'success' => false,
                'message' => $calc['date_error'] ?? 'Quote contains invalid schedule quantities or dates.',
            ];
        }
        if (!$calc['date_range_valid'] || $calc['start_date'] === null || $calc['end_date'] === null) {
            return [
                'success' => false,
                'message' => 'Campaign Start Date and Campaign End Date must be set on the Quote before converting.',
            ];
        }

        // Find existing linked campaign defensively (Section 12.2)
        $existingCampaignId = self::findLinkedCampaignId($db, (string) $quote->id, (string) ($quote->number ?? ''), (string) $quote->name);

        $created = false;
        $campaignBean = null;
        if ($existingCampaignId !== null) {
            $campaignBean = BeanFactory::getBean('RT_RadioCampaigns', $existingCampaignId);
            if (empty($campaignBean) || empty($campaignBean->id) || !empty($campaignBean->deleted)) {
                $campaignBean = null;
            }
        }

        if ($campaignBean === null) {
            $campaignBean = BeanFactory::newBean('RT_RadioCampaigns');
            $created = true;
        } else {
            if (method_exists($campaignBean, 'ACLAccess') && !$campaignBean->ACLAccess('edit')) {
                return ['success' => false, 'message' => 'You do not have permission to update the linked Radio Campaign.'];
            }
        }

        $campaignBean->name = 'Radio Campaign - ' . trim((string) $quote->name);
        $campaignBean->start_date = $calc['start_date'];
        $campaignBean->end_date = $calc['end_date'];
        $campaignBean->number_of_spots = (int) $calc['total_spots'];
        $campaignBean->quote_reference = (string) ($quote->number ?? '');
        $campaignBean->linked_quote_id = (string) $quote->id;

        if (empty($campaignBean->assigned_user_id)) {
            $campaignBean->assigned_user_id = !empty($quote->assigned_user_id)
                ? (string) $quote->assigned_user_id
                : (string) ($current_user->id ?? '1');
        }

        // Preserve already-entered spot_length, status, and myriad_cart_number on reconversion (Section 12.2 & 12.4)
        $quoteSpotDuration = !empty($cstmRow['spot_duration_c']) ? (string) $cstmRow['spot_duration_c'] : '30 sec';
        if ($created || empty($campaignBean->spot_length)) {
            $campaignBean->spot_length = $quoteSpotDuration;
        }

        $quoteCategory = !empty($cstmRow['industry_category_c']) ? (string) $cstmRow['industry_category_c'] : 'other';
        if ($created || empty($campaignBean->industry_category)) {
            $campaignBean->industry_category = $quoteCategory;
        }

        // Auto-allocate Myriad Cart from range 2000..2999 if not already assigned
        if (empty($campaignBean->myriad_cart_number) || trim((string) $campaignBean->myriad_cart_number) === '') {
            $allocatedCart = RadioScheduleService::allocateNextMyriadCart(
                $campaignBean->start_date,
                $campaignBean->end_date,
                $db
            );
            $campaignBean->myriad_cart_number = (string) $allocatedCart;
        }

        if (empty($campaignBean->client_approval_token)) {
            $campaignBean->client_approval_token = bin2hex(random_bytes(16));
        }

        if ($created || empty($campaignBean->status)) {
            $campaignBean->status = 'Draft';
        }

        $campaignId = (string) $campaignBean->save();

        // Ensure idempotent relationship record in rt_radiocampaigns_aos_quotes_c (Section 12.4)
        self::ensureQuoteCampaignRelationship($db, $campaignId, (string) $quote->id);

        $conflicts = [];
        if (!empty($campaignBean->industry_category) && $campaignBean->industry_category !== 'other') {
            $conflicts = RadioScheduleService::checkCategoryConflicts(
                $campaignBean->industry_category,
                $inputData,
                $campaignBean->start_date,
                $campaignBean->end_date,
                $campaignId,
                $db
            );
        }

        $msg = $created ? 'Radio Campaign created from Quote.' : 'Existing Radio Campaign updated from Quote.';
        if (!empty($conflicts)) {
            $msg .= ' (Category Alert: ' . count($conflicts) . ' potential competitor conflict(s) detected in flight schedule)';
        }

        return [
            'success' => true,
            'message' => $msg,
            'campaign_id' => $campaignId,
            'created' => $created,
            'conflicts' => $conflicts,
            'campaign' => [
                'id' => $campaignId,
                'name' => $campaignBean->name,
                'start_date' => $campaignBean->start_date,
                'end_date' => $campaignBean->end_date,
                'number_of_spots' => (int) $campaignBean->number_of_spots,
                'spot_length' => $campaignBean->spot_length,
                'status' => $campaignBean->status,
                'myriad_cart_number' => $campaignBean->myriad_cart_number,
                'industry_category' => $campaignBean->industry_category,
                'quote_reference' => $campaignBean->quote_reference,
                'linked_quote_id' => $campaignBean->linked_quote_id,
                'client_approval_token' => $campaignBean->client_approval_token,
            ],
        ];
    }

    /**
     * Defensive lookup of an already-linked RT_RadioCampaigns record (Section 12.2).
     */
    private static function findLinkedCampaignId(
        object $db,
        string $quoteId,
        string $quoteNumber,
        string $quoteName
    ): ?string {
        $qIdEsc = $db->quote($quoteId);

        // 1. Primary lookup: exact linked_quote_id on rt_radiocampaigns
        $res = $db->query(
            "SELECT id FROM rt_radiocampaigns WHERE linked_quote_id = '{$qIdEsc}' AND deleted = 0 ORDER BY date_entered ASC LIMIT 1",
            false
        );
        $row = $res ? $db->fetchByAssoc($res) : null;
        if ($row && !empty($row['id'])) {
            return (string) $row['id'];
        }

        // 2. Join table lookup: rt_radiocampaigns_aos_quotes_c
        $joinCheck = $db->query("SHOW TABLES LIKE 'rt_radiocampaigns_aos_quotes_c'", false);
        if ($joinCheck && $db->fetchByAssoc($joinCheck)) {
            $jRes = $db->query(
                "SELECT c.id
                 FROM rt_radiocampaigns c
                 INNER JOIN rt_radiocampaigns_aos_quotes_c j
                    ON j.rt_radiocampaigns_aos_quotesrt_radiocampaigns_ida = c.id
                 WHERE j.rt_radiocampaigns_aos_quotesaos_quotes_idb = '{$qIdEsc}'
                   AND j.deleted = 0
                   AND c.deleted = 0
                 ORDER BY c.date_entered ASC
                 LIMIT 1",
                false
            );
            $jRow = $jRes ? $db->fetchByAssoc($jRes) : null;
            if ($jRow && !empty($jRow['id'])) {
                return (string) $jRow['id'];
            }
        }

        // 3. Optional custom field table rt_radiocampaigns_cstm.linked_quote_id_c if present
        $cstmCheck = $db->query("SHOW TABLES LIKE 'rt_radiocampaigns_cstm'", false);
        if ($cstmCheck && $db->fetchByAssoc($cstmCheck)) {
            $colCheck = $db->query("SHOW COLUMNS FROM rt_radiocampaigns_cstm LIKE 'linked_quote_id_c'", false);
            if ($colCheck && $db->fetchByAssoc($colCheck)) {
                $cRes = $db->query(
                    "SELECT c.id
                     FROM rt_radiocampaigns c
                     INNER JOIN rt_radiocampaigns_cstm cs ON cs.id_c = c.id
                     WHERE cs.linked_quote_id_c = '{$qIdEsc}' AND c.deleted = 0
                     ORDER BY c.date_entered ASC
                     LIMIT 1",
                    false
                );
                $cRow = $cRes ? $db->fetchByAssoc($cRes) : null;
                if ($cRow && !empty($cRow['id'])) {
                    return (string) $cRow['id'];
                }
            }
        }

        // 4. Unambiguous fallback by quote_reference where linked_quote_id is empty/null
        if ($quoteNumber !== '') {
            $refEsc = $db->quote($quoteNumber);
            $expectedNameEsc = $db->quote('Radio Campaign - ' . trim($quoteName));
            $fRes = $db->query(
                "SELECT id FROM rt_radiocampaigns
                 WHERE quote_reference = '{$refEsc}'
                   AND name = '{$expectedNameEsc}'
                   AND (linked_quote_id IS NULL OR linked_quote_id = '')
                   AND deleted = 0
                 ORDER BY date_entered ASC
                 LIMIT 1",
                false
            );
            $fRow = $fRes ? $db->fetchByAssoc($fRes) : null;
            if ($fRow && !empty($fRow['id'])) {
                return (string) $fRow['id'];
            }
        }

        return null;
    }

    /**
     * Ensure rt_radiocampaigns_aos_quotes_c join record exists without duplication.
     */
    private static function ensureQuoteCampaignRelationship(
        object $db,
        string $campaignId,
        string $quoteId
    ): void {
        $joinCheck = $db->query("SHOW TABLES LIKE 'rt_radiocampaigns_aos_quotes_c'", false);
        if (!$joinCheck || !$db->fetchByAssoc($joinCheck)) {
            return;
        }

        $cIdEsc = $db->quote($campaignId);
        $qIdEsc = $db->quote($quoteId);

        $res = $db->query(
            "SELECT id, deleted FROM rt_radiocampaigns_aos_quotes_c
             WHERE rt_radiocampaigns_aos_quotesrt_radiocampaigns_ida = '{$cIdEsc}'
               AND rt_radiocampaigns_aos_quotesaos_quotes_idb = '{$qIdEsc}'
             ORDER BY deleted ASC
             LIMIT 1",
            false
        );
        $row = $res ? $db->fetchByAssoc($res) : null;
        $nowUtc = gmdate('Y-m-d H:i:s');

        if ($row) {
            if (!empty($row['deleted'])) {
                $relIdEsc = $db->quote((string) $row['id']);
                $db->query(
                    "UPDATE rt_radiocampaigns_aos_quotes_c SET deleted = 0, date_modified = '{$nowUtc}' WHERE id = '{$relIdEsc}'",
                    false
                );
            }
            // Soft-delete any accidental extra rows for the same pair
            $keepIdEsc = $db->quote((string) $row['id']);
            $db->query(
                "UPDATE rt_radiocampaigns_aos_quotes_c
                 SET deleted = 1, date_modified = '{$nowUtc}'
                 WHERE rt_radiocampaigns_aos_quotesrt_radiocampaigns_ida = '{$cIdEsc}'
                   AND rt_radiocampaigns_aos_quotesaos_quotes_idb = '{$qIdEsc}'
                   AND id <> '{$keepIdEsc}'
                   AND deleted = 0",
                false
            );
            return;
        }

        $newRelId = RadioQuoteScheduleProducts::generateUuid();
        $db->query(
            "INSERT INTO rt_radiocampaigns_aos_quotes_c (
                id, date_modified, deleted,
                rt_radiocampaigns_aos_quotesrt_radiocampaigns_ida,
                rt_radiocampaigns_aos_quotesaos_quotes_idb
             ) VALUES (
                '{$newRelId}', '{$nowUtc}', 0, '{$cIdEsc}', '{$qIdEsc}'
             )",
            false
        );
    }
}

// Execute when invoked as entryPoint=convert_quote_to_radio_campaign
if (isset($_REQUEST['entryPoint']) && $_REQUEST['entryPoint'] === 'convert_quote_to_radio_campaign') {
    $quoteId = (string) ($_REQUEST['record'] ?? $_REQUEST['quote_id'] ?? $_REQUEST['id'] ?? '');
    $result = RadioCampaignConverter::convertQuoteToCampaign($quoteId);

    if (isset($_REQUEST['format']) && $_REQUEST['format'] === 'json') {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($result);
        return;
    }

    if (!$result['success']) {
        SugarApplication::appendErrorMessage($result['message']);
        if ($quoteId !== '') {
            SugarApplication::redirect('index.php?module=AOS_Quotes&action=DetailView&record=' . urlencode($quoteId));
        } else {
            SugarApplication::redirect('index.php?module=AOS_Quotes&action=index');
        }
        return;
    }

    $campaignId = $result['campaign_id'];
    SugarApplication::redirect('index.php?module=RT_RadioCampaigns&action=DetailView&record=' . urlencode($campaignId));
}

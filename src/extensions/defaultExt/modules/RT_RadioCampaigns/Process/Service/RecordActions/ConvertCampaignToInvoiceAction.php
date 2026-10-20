<?php

declare(strict_types=1);

namespace App\Extension\defaultExt\modules\RT_RadioCampaigns\Process\Service\RecordActions;

use ApiPlatform\Exception\InvalidArgumentException;
use App\Engine\LegacyHandler\LegacyHandler;
use App\Engine\LegacyHandler\LegacyScopeState;
use App\Module\Service\ModuleNameMapperInterface;
use App\Process\Entity\Process;
use App\Process\Service\ProcessHandlerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class ConvertCampaignToInvoiceAction extends LegacyHandler implements ProcessHandlerInterface
{
    protected const MSG_OPTIONS_NOT_FOUND = 'Process options are not defined';
    public const PROCESS_TYPE = 'record-radio-campaign-convert-to-invoice';

    protected ModuleNameMapperInterface $moduleNameMapper;

    public function __construct(
        string $projectDir,
        string $legacyDir,
        string $legacySessionName,
        string $defaultSessionName,
        LegacyScopeState $legacyScopeState,
        RequestStack $requestStack,
        ModuleNameMapperInterface $moduleNameMapper
    ) {
        parent::__construct(
            $projectDir,
            $legacyDir,
            $legacySessionName,
            $defaultSessionName,
            $legacyScopeState,
            $requestStack
        );
        $this->moduleNameMapper = $moduleNameMapper;
    }

    public function getProcessType(): string
    {
        return self::PROCESS_TYPE;
    }

    public function getHandlerKey(): string
    {
        return self::PROCESS_TYPE;
    }

    public function requiredAuthRole(): string
    {
        return 'ROLE_USER';
    }

    public function getRequiredACLs(Process $process): array
    {
        $options = $process->getOptions();
        $module = $options['module'] ?? 'RT_RadioCampaigns';
        $id = $options['id'] ?? '';

        return [
            $module => [
                [
                    'action' => 'view',
                    'record' => $id,
                ],
            ],
        ];
    }

    public function configure(Process $process): void
    {
        $process->setId(self::PROCESS_TYPE);
        $process->setAsync(false);
    }

    public function validate(Process $process): void
    {
        $options = $process->getOptions();
        if (empty($options) || empty($options['id'])) {
            throw new InvalidArgumentException(self::MSG_OPTIONS_NOT_FOUND);
        }
    }

    public function run(Process $process): void
    {
        $options = $process->getOptions();
        $recordId = trim((string) ($options['id'] ?? ''));

        if ($recordId === '') {
            $process->setStatus('failure');
            $process->setMessages(['Campaign record ID is missing.']);
            $process->setData([]);
            return;
        }

        $this->init();
        try {
            $this->runLegacyEntryPoint();
            $this->startLegacyApp('RT_RadioCampaigns');

            require_once 'custom/include/RadioCRM/RadioScheduleService.php';
            require_once 'custom/include/RadioCRM/RadioStationConfigService.php';
            require_once 'custom/modules/AOS_Quotes/RadioQuoteScheduleProducts.php';

            global $db;

            $cIdEsc = $db->quote($recordId);
            $campRes = $db->query(
                "SELECT c.*, q.billing_account_id, q.billing_contact_id, q.name AS quote_name, q.id AS quote_id,
                        q.subtotal_amount AS q_subtotal, q.tax_amount AS q_tax, q.total_amount AS q_total
                 FROM rt_radiocampaigns c
                 LEFT JOIN aos_quotes q ON q.id = c.linked_quote_id AND q.deleted = 0
                 WHERE c.id = '{$cIdEsc}' AND c.deleted = 0
                 LIMIT 1",
                false
            );
            $camp = $campRes ? $db->fetchByAssoc($campRes) : null;
            if (!$camp) {
                $process->setStatus('failure');
                $process->setMessages(['Radio Campaign record not found.']);
                $process->setData([]);
                return;
            }

            $campName = (string) $camp['name'];
            $invCheckRes = $db->query(
                "SELECT id FROM aos_invoices
                 WHERE name LIKE 'Invoice - " . $db->quote($campName) . "%' AND deleted = 0
                 ORDER BY date_entered ASC LIMIT 1",
                false
            );
            $invRow = $invCheckRes ? $db->fetchByAssoc($invCheckRes) : null;
            $invId = $invRow ? (string) $invRow['id'] : \RadioQuoteScheduleProducts::generateUuid();

            $_REQUEST['record'] = $recordId;
            // Create or update invoice via standard logic
            $quoteId = (string) ($camp['quote_id'] ?? ($camp['linked_quote_id'] ?? ''));
            $accountId = (string) ($camp['billing_account_id'] ?? '');
            $contactId = (string) ($camp['billing_contact_id'] ?? '');
            $cart = (string) ($camp['myriad_cart_number'] ?: '');
            $nowUtc = gmdate('Y-m-d H:i:s');
            $todayDate = date('Y-m-d');
            $dueDate = date('Y-m-d', strtotime('+30 days'));

            $totalSpots = (int) (($camp['reconciled_spots'] ?? 0) > 0 ? $camp['reconciled_spots'] : ($camp['number_of_spots'] ?: 1));
            $spotDur = (string) ($camp['spot_length'] ?: '30 sec');
            $durationMult = \RadioCRM\RadioScheduleService::getDurationMultiplier($spotDur);
            $unitPrice = round(350.00 * $durationMult, 2);
            $subtotal = round($totalSpots * $unitPrice, 2);
            $vatAmt = round($subtotal * \RadioCRM\RadioScheduleService::DEFAULT_VAT_RATE, 2);
            $grandTotal = round($subtotal + $vatAmt, 2);

            $stConfig = \RadioCRM\RadioStationConfigService::getSettings($db);
            $stName = (string) ($stConfig['station_name'] ?? 'Radio Station');
            $invName = 'Invoice - ' . $campName;
            $invNameEsc = $db->quote($invName);
            $desc = "Broadcast commercial advertising campaign on {$stName}. Cart: {$cart}. Spots: {$totalSpots} ({$spotDur}). Verified via Myriad playout automation.";
            $descEsc = $db->quote($desc);

            if ($invRow) {
                $db->query(
                    "UPDATE aos_invoices SET
                        total_amt = {$subtotal},
                        subtotal_amount = {$subtotal},
                        tax_amount = {$vatAmt},
                        total_amount = {$grandTotal},
                        date_modified = '{$nowUtc}',
                        description = '{$descEsc}'
                     WHERE id = '" . $db->quote($invId) . "'",
                    false
                );
            } else {
                $numRes = $db->query("SELECT MAX(CAST(number AS UNSIGNED)) AS max_num FROM aos_invoices", false);
                $numRow = $numRes ? $db->fetchByAssoc($numRes) : null;
                $nextNum = ((int) ($numRow['max_num'] ?? 0)) + 1;

                $db->query(
                    "INSERT INTO aos_invoices (
                        id, name, date_entered, date_modified, modified_user_id, created_by,
                        description, deleted, assigned_user_id, billing_account_id, billing_contact_id,
                        number, total_amt, subtotal_amount, discount_amount, tax_amount,
                        shipping_amount, shipping_tax, shipping_tax_amt, total_amount,
                        currency_id, status, due_date, invoiced_date
                    ) VALUES (
                        '{$invId}', '{$invNameEsc}', '{$nowUtc}', '{$nowUtc}', '1', '1',
                        '{$descEsc}', 0, '1', '" . $db->quote($accountId) . "', '" . $db->quote($contactId) . "',
                        {$nextNum}, {$subtotal}, {$subtotal}, 0.00, {$vatAmt},
                        0.00, 0, 0.00, {$grandTotal},
                        '-99', 'Unpaid', '{$dueDate}', '{$todayDate}'
                    )",
                    false
                );
            }

            $process->setStatus('success');
            $process->setMessages(['Invoice generated: ' . $invName]);
            $process->setData([
                'handler' => 'redirect',
                'params' => [
                    'route' => 'invoices/record/' . $invId,
                    'queryParams' => [],
                ],
            ]);
        } finally {
            $this->close();
        }
    }
}

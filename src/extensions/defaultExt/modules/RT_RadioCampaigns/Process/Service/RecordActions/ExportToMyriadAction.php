<?php

declare(strict_types=1);

namespace App\Extension\defaultExt\modules\RT_RadioCampaigns\Process\Service\RecordActions;

use ApiPlatform\Exception\InvalidArgumentException;
use App\Engine\LegacyHandler\LegacyHandler;
use App\Engine\LegacyHandler\LegacyScopeState;
use App\Module\Service\ModuleNameMapperInterface;
use App\Process\Entity\Process;
use App\Process\Service\ProcessHandlerInterface;
use BeanFactory;
use RadioCRM\RadioScheduleService;
use Symfony\Component\HttpFoundation\RequestStack;

class ExportToMyriadAction extends LegacyHandler implements ProcessHandlerInterface
{
    protected const MSG_OPTIONS_NOT_FOUND = 'Process options are not defined';
    public const PROCESS_TYPE = 'record-radio-campaign-export-to-myriad';

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
                [
                    'action' => 'export',
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
        $exportRoot = isset($options['params']['exportRoot']) && is_string($options['params']['exportRoot'])
            ? $options['params']['exportRoot']
            : '/srv/myriad_exports';

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

            global $db, $current_user;

            if (empty($current_user) || empty($current_user->id)) {
                $process->setStatus('failure');
                $process->setMessages(['Authentication required to export Radio Campaign.']);
                $process->setData([]);
                return;
            }

            if (class_exists('\ACLController')) {
                if (!\ACLController::checkAccess('RT_RadioCampaigns', 'view', true)
                    || !\ACLController::checkAccess('RT_RadioCampaigns', 'export', true)
                ) {
                    $process->setStatus('failure');
                    $process->setMessages(['You do not have permission to export Radio Campaigns.']);
                    $process->setData([]);
                    return;
                }
            }

            $campaign = BeanFactory::getBean('RT_RadioCampaigns', $recordId);
            if (empty($campaign) || empty($campaign->id) || !empty($campaign->deleted)) {
                $process->setStatus('failure');
                $process->setMessages(['Radio Campaign record not found.']);
                $process->setData([]);
                return;
            }

            if ((method_exists($campaign, 'ACLAccess') && !$campaign->ACLAccess('view'))
                || (method_exists($campaign, 'ACLAccess') && !$campaign->ACLAccess('export'))
            ) {
                $process->setStatus('failure');
                $process->setMessages(['Access denied: you are not authorized to export this Radio Campaign record.']);
                $process->setData([]);
                return;
            }

            $cartNumber = trim((string) ($campaign->myriad_cart_number ?? ''));
            if ($cartNumber === '') {
                $process->setStatus('failure');
                $process->setMessages(['Myriad Cart Number is required before exporting.']);
                $process->setData([]);
                return;
            }

            // Resolve linked Quote ID from linked_quote_id or relationship join table
            $quoteId = trim((string) ($campaign->linked_quote_id ?? ''));
            if ($quoteId === '' && is_object($db)) {
                $cIdEsc = $db->quote((string) $campaign->id);
                $jRes = $db->query(
                    "SELECT rt_radiocampaigns_aos_quotesaos_quotes_idb AS quote_id
                     FROM rt_radiocampaigns_aos_quotes_c
                     WHERE rt_radiocampaigns_aos_quotesrt_radiocampaigns_ida = '{$cIdEsc}'
                       AND deleted = 0
                     ORDER BY date_modified DESC
                     LIMIT 1",
                    false
                );
                $jRow = $jRes ? $db->fetchByAssoc($jRes) : null;
                if ($jRow && !empty($jRow['quote_id'])) {
                    $quoteId = (string) $jRow['quote_id'];
                }
            }

            if ($quoteId === '') {
                $process->setStatus('failure');
                $process->setMessages(['Radio Campaign does not have a linked source Quote.']);
                $process->setData([]);
                return;
            }

            $quote = BeanFactory::getBean('AOS_Quotes', $quoteId);
            if (empty($quote) || empty($quote->id) || !empty($quote->deleted)) {
                $process->setStatus('failure');
                $process->setMessages(['Linked source Quote record could not be found.']);
                $process->setData([]);
                return;
            }

            $qIdEsc = $db->quote((string) $quote->id);
            $cstmRes = $db->query("SELECT * FROM aos_quotes_cstm WHERE id_c = '{$qIdEsc}'", false);
            $cstmRow = ($cstmRes ? $db->fetchByAssoc($cstmRes) : null) ?: [];

            $quoteScheduleData = [];
            foreach (RadioScheduleService::getAllScheduleFields() as $fieldName => $meta) {
                $quoteScheduleData[$fieldName] = $cstmRow[$fieldName] ?? ($quote->$fieldName ?? 0);
            }

            $campaignData = [
                'id' => (string) $campaign->id,
                'name' => (string) $campaign->name,
                'start_date' => (string) ($campaign->start_date ?: ($cstmRow['campaign_start_date_c'] ?? '')),
                'end_date' => (string) ($campaign->end_date ?: ($cstmRow['campaign_end_date_c'] ?? '')),
                'spot_length' => (string) ($campaign->spot_length ?: '30 sec'),
                'myriad_cart_number' => $cartNumber,
                'quote_reference' => (string) ($campaign->quote_reference ?: ($quote->number ?? '')),
                'linked_quote_id' => (string) $quote->id,
            ];

            $exportResult = RadioScheduleService::exportCampaignToMyriad(
                $campaignData,
                $quoteScheduleData,
                $exportRoot
            );

            if (!$exportResult['success']) {
                $process->setStatus('failure');
                $process->setMessages([$exportResult['message']]);
                $process->setData([]);
                return;
            }

            // Only update status to exported_to_myriad AFTER files are atomically written and verified (Section 13.4 rule 4)
            $campaign->status = 'exported_to_myriad';
            $campaign->number_of_spots = (int) ($exportResult['total_plays'] ?? $campaign->number_of_spots);
            $campaign->save();

            $process->setStatus('success');
            $process->setMessages([$exportResult['message']]);
            $process->setData([
                'reload' => true,
                'csv_path' => $exportResult['csv_path'] ?? '',
                'csv_filename' => $exportResult['csv_filename'] ?? '',
                'daily_logs' => $exportResult['daily_logs'] ?? [],
                'total_plays' => $exportResult['total_plays'] ?? 0,
                'active_days' => $exportResult['active_days'] ?? 0,
            ]);
        } finally {
            $this->close();
        }
    }
}

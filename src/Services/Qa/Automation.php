<?php

declare(strict_types=1);

namespace FP\DMS\Services\Qa;

use FP\DMS\Domain\Entities\Client;
use FP\DMS\Domain\Entities\ReportJob;
use FP\DMS\Domain\Entities\Schedule;
use FP\DMS\Domain\Repos\AnomaliesRepo;
use FP\DMS\Domain\Repos\ClientsRepo;
use FP\DMS\Domain\Repos\DataSourcesRepo;
use FP\DMS\Domain\Repos\ReportsRepo;
use FP\DMS\Domain\Repos\SchedulesRepo;
use FP\DMS\Domain\Repos\TemplatesRepo;
use FP\DMS\Infra\Lock;
use FP\DMS\Infra\Logger;
use FP\DMS\Infra\Mailer;
use FP\DMS\Infra\Options;
use FP\DMS\Infra\Queue;
use FP\DMS\Services\Anomalies\Detector;
use FP\DMS\Services\Connectors\CsvGenericProvider;
use FP\DMS\Services\Connectors\GoogleAdsProvider;
use FP\DMS\Services\Connectors\MetaAdsProvider;
use FP\DMS\Support\Period;
use FP\DMS\Support\Wp;

class Automation
{
    private const CLIENT_NAME = 'QA Demo Client';
    private const CLIENT_EMAIL = 'qa-client@example.com';
    private const OWNER_EMAIL = 'qa-owner@example.com';
    private const QA_NOTES = '{"qa":true,"label":"QA automation"}';
    private const QA_CRON_KEY = 'fpdms-qa-monthly';

    private const GOOGLE_ADS_CSV = <<<CSV
Date,Clicks,Impressions,Cost,Conversions
2025-08-01,120,10000,35.50,4
2025-08-02,80,8000,24.00,2
CSV;

    private const META_ADS_CSV = <<<CSV
Date,Clicks,Impressions,Cost,Purchases
2025-08-01,60,5000,18.00,1
2025-08-02,90,7000,21.00,3
CSV;

    private const GENERIC_CSV = <<<CSV
Date,Users,Sessions,Revenue
2025-08-01,200,260,120.00
2025-08-02,150,210,90.00
CSV;

    private ?Client $client = null;
    private ?Schedule $schedule = null;

    public function seed(): array
    {
        $setup = $this->ensureSetup();
        $status = ($setup['datasources']['google_ads'] === 'ok'
            && $setup['datasources']['meta_ads'] === 'ok'
            && $setup['datasources']['csv_generic'] === 'ok'
            && $setup['schedule'] === 'ok') ? 'PASS' : 'WARN';

        return [
            'qa' => 'seed',
            'client_id' => $setup['client']->id ?? 0,
            'datasources' => $setup['datasources'],
            'schedule' => $setup['schedule'],
            'status' => $status,
        ];
    }

    public function run(bool $ensurePrerequisites = true): array
    {
        if ($ensurePrerequisites) {
            $this->ensureSetup();
        }

        $client = $this->resolveClient();
        if (! $client) {
            return [
                'qa' => 'run',
                'status' => 'FAIL',
                'error' => 'client_missing',
            ];
        }

        $schedule = $this->resolveSchedule($client);
        $period = $this->qaPeriod();
        $templates = new TemplatesRepo();
        $templateId = $schedule?->templateId ?: $templates->findDefault()?->id;

        $job = Queue::enqueue(
            $client->id ?? 0,
            $period['start'],
            $period['end'],
            $templateId,
            $schedule?->id,
            ['qa' => true, 'origin' => 'qa-rest']
        );

        if (! $job instanceof ReportJob) {
            return [
                'qa' => 'run',
                'client_id' => $client->id ?? 0,
                'status' => 'FAIL',
                'error' => 'enqueue_failed',
            ];
        }

        $locks = 'OK';
        $lockOwner = 'qa-rest-' . Wp::generatePassword(6, false, false);
        if (Lock::acquire('queue-global', $lockOwner, 30)) {
            try {
                Queue::tick();
            } finally {
                Lock::release('queue-global', $lockOwner);
            }
            $locks = 'CONTENDED';
        }

        Queue::tick();

        $reports = new ReportsRepo();
        $report = $reports->find($job->id ?? 0);
        if (! $report) {
            return [
                'qa' => 'run',
                'client_id' => $client->id ?? 0,
                'status' => 'FAIL',
                'error' => 'report_missing',
            ];
        }

        if (empty($report->meta['qa'])) {
            $reports->update($report->id ?? 0, ['meta' => array_merge($report->meta, ['qa' => true])]);
            $report = $reports->find($report->id ?? 0) ?? $report;
        }

        $warnings = [];
        $status = strtoupper($report->status);
        $outputStatus = 'PASS';

        if ($status !== 'SUCCESS') {
            $error = (string) ($report->meta['error'] ?? '');
            if ($error && str_contains($error, 'mPDF')) {
                $warnings[] = 'PDF renderer missing (composer install required)';
                $outputStatus = 'WARN';
            } else {
                $outputStatus = 'FAIL';
            }
        }

        $emailMeta = strtolower((string) ($report->meta['mail_status'] ?? 'unknown'));
        $emailStatus = match ($emailMeta) {
            'sent' => 'SENT',
            'failed' => 'FAIL',
            default => strtoupper($emailMeta),
        };

        if ($emailStatus !== 'SENT') {
            $outputStatus = $outputStatus === 'FAIL' ? 'FAIL' : 'WARN';
        }

        Logger::logQa(sprintf(
            'run client=%d report=%d status=%s email=%s locks=%s',
            $client->id ?? 0,
            $report->id ?? 0,
            $status,
            $emailStatus,
            $locks
        ));

        return [
            'qa' => 'run',
            'client_id' => $client->id ?? 0,
            'report_id' => $report->id ?? 0,
            'pdf' => $report->storagePath ?? '',
            'email' => $emailStatus,
            'locks' => $locks,
            'warnings' => $warnings,
            'status' => $outputStatus,
        ];
    }

    public function anomalies(bool $ensurePrerequisites = true): array
    {
        if ($ensurePrerequisites) {
            $this->ensureSetup();
        }

        $client = $this->resolveClient();
        if (! $client) {
            return [
                'qa' => 'anomalies',
                'status' => 'FAIL',
                'error' => 'client_missing',
            ];
        }

        $period = $this->qaPeriod();
        $periodObj = Period::fromStrings($period['start'], $period['end'], $client->timezone);
        $detector = new Detector(new AnomaliesRepo());
        $history = [];
        for ($i = 0; $i < 8; $i++) {
            $history[] = [
                'clicks' => 120 + ($i * 10),
                'sessions' => 300 + ($i * 15),
                'conversions' => 5 + $i,
            ];
        }

        $meta = [
            'metrics_daily' => [[
                'source' => 'qa_fixture',
                'date' => $periodObj->end->format('Y-m-d'),
                'clicks' => 420.0,
                'sessions' => 620.0,
                'conversions' => 18.0,
            ]],
            'previous_totals' => [
                'clicks' => 160.0,
                'sessions' => 360.0,
                'conversions' => 8.0,
            ],
        ];

        $anomalies = $detector->evaluatePeriod($client->id ?? 0, $periodObj, $meta, $history, true);
        $mailer = new Mailer();
        $mailSent = ! empty($anomalies) && $mailer->sendAnomalyAlert($client, $anomalies, $periodObj);

        $settings = Options::getGlobalSettings();
        $webhook = $settings['error_webhook_url'] ?? '';
        if ($webhook && ! empty($anomalies)) {
            Wp::remotePost($webhook, [
                'headers' => ['Content-Type' => 'application/json'],
                'body' => Wp::jsonEncode([
                    'client' => $client->name,
                    'period' => $period,
                    'anomalies' => $anomalies,
                    'qa' => true,
                ]) ?: '[]',
                'timeout' => 5,
            ]);
        }

        $status = 'PASS';
        if (empty($anomalies)) {
            $status = 'FAIL';
        } elseif (! $mailSent) {
            $status = 'WARN';
        }

        Logger::logQa(sprintf(
            'anomalies client=%d detected=%d mail=%s',
            $client->id ?? 0,
            count($anomalies),
            $mailSent ? 'sent' : 'fail'
        ));

        return [
            'qa' => 'anomalies',
            'client_id' => $client->id ?? 0,
            'anomalies' => count($anomalies),
            'severities' => array_values(array_unique(array_map(
                static fn(array $item): string => (string) ($item['severity'] ?? 'warn'),
                $anomalies
            ))),
            'status' => $status,
        ];
    }

    public function all(): array
    {
        $seed = $this->seed();
        $run = $this->run(false);
        $anomalies = $this->anomalies(false);

        $warnings = [];
        if (! empty($run['warnings'])) {
            $warnings = array_merge($warnings, (array) $run['warnings']);
        }

        $overallStatus = 'PASS';
        foreach ([$seed['status'], $run['status'], $anomalies['status']] as $status) {
            if ($status === 'FAIL') {
                $overallStatus = 'FAIL';
                break;
            }
            if ($status === 'WARN' && $overallStatus !== 'FAIL') {
                $overallStatus = 'WARN';
            }
        }

        Logger::logQa(sprintf('all client=%d status=%s', $seed['client_id'], $overallStatus));

        return [
            'qa' => 'all',
            'client_id' => $seed['client_id'],
            'report_id' => $run['report_id'] ?? 0,
            'pdf' => $run['pdf'] ?? '',
            'email' => $run['email'] ?? 'UNKNOWN',
            'anomalies' => $anomalies['anomalies'] ?? 0,
            'locks' => $run['locks'] ?? 'OK',
            'warnings' => array_values(array_unique($warnings)),
            'status' => $overallStatus,
        ];
    }

    public function status(): array
    {
        $client = $this->resolveClient();
        if (! $client) {
            return [
                'qa' => 'status',
                'warnings' => ['client_missing'],
                'status' => 'WARN',
            ];
        }

        $schedulesRepo = new SchedulesRepo();
        $reportsRepo = new ReportsRepo();
        $anomaliesRepo = new AnomaliesRepo();

        $schedules = $schedulesRepo->forClient($client->id ?? 0);
        $reports = $reportsRepo->forClient($client->id ?? 0);
        $lastReport = $reports[0] ?? null;
        $anomaliesCount = $anomaliesRepo->countForClient($client->id ?? 0);

        $warnings = [];
        if ($schedules === []) {
            $warnings[] = 'schedule_missing';
        }
        if (! $lastReport) {
            $warnings[] = 'report_missing';
        }

        return [
            'qa' => 'status',
            'client_id' => $client->id ?? 0,
            'schedules' => count($schedules),
            'last_report' => $lastReport ? [
                'id' => $lastReport->id ?? 0,
                'path' => $lastReport->storagePath,
                'date' => $lastReport->createdAt,
            ] : null,
            'anomalies_count' => $anomaliesCount,
            'last_tick' => Options::getLastTick(),
            'mail_last_result' => $lastReport ? strtoupper((string) ($lastReport->meta['mail_status'] ?? 'unknown')) : 'UNKNOWN',
            'warnings' => $warnings,
            'status' => empty($warnings) ? 'PASS' : 'WARN',
        ];
    }

    public function cleanup(): array
    {
        $client = $this->resolveClient();
        if (! $client) {
            return [
                'qa' => 'cleanup',
                'client_deleted' => 0,
                'datasources_deleted' => 0,
                'schedules_deleted' => 0,
                'reports_deleted' => 0,
                'anomalies_deleted' => 0,
                'status' => 'PASS',
            ];
        }

        $clientId = $client->id ?? 0;
        $datasourcesRepo = new DataSourcesRepo();
        $schedulesRepo = new SchedulesRepo();
        $reportsRepo = new ReportsRepo();
        $anomaliesRepo = new AnomaliesRepo();
        $clientsRepo = new ClientsRepo();

        $datasourcesDeleted = $datasourcesRepo->deleteByClient($clientId);
        $schedulesDeleted = $schedulesRepo->deleteByClient($clientId);

        $reportsDeleted = 0;
        foreach ($reportsRepo->forClient($clientId) as $report) {
            if ($report->storagePath) {
                $upload = Wp::uploadDir();
                $absolute = Wp::trailingSlashIt($upload['basedir']) . ltrim($report->storagePath, '/');
                if (file_exists($absolute)) {
                    @unlink($absolute);
                }
            }
            if ($reportsRepo->delete($report->id ?? 0)) {
                $reportsDeleted++;
            }
        }

        $anomaliesDeleted = $anomaliesRepo->deleteByClient($clientId);
        $clientsRepo->delete($clientId);

        $this->client = null;
        $this->schedule = null;

        Logger::logQa(sprintf(
            'cleanup client=%d datasources=%d schedules=%d reports=%d anomalies=%d',
            $clientId,
            $datasourcesDeleted,
            $schedulesDeleted,
            $reportsDeleted,
            $anomaliesDeleted
        ));

        return [
            'qa' => 'cleanup',
            'client_deleted' => 1,
            'datasources_deleted' => $datasourcesDeleted,
            'schedules_deleted' => $schedulesDeleted,
            'reports_deleted' => $reportsDeleted,
            'anomalies_deleted' => $anomaliesDeleted,
            'status' => 'PASS',
        ];
    }

    /**
     * @return array{client:Client,schedule:Schedule|null,datasources:array<string,string>,schedule:string}
     */
    private function ensureSetup(): array
    {
        Options::ensureDefaults();
        Options::getQaKey();
        $this->ensureOwnerEmail();

        $client = $this->ensureClient();
        $datasources = $this->ensureDataSources($client);
        $schedule = $this->ensureSchedule($client);

        $this->client = $client;
        $this->schedule = $schedule;

        return [
            'client' => $client,
            'datasources' => $datasources,
            'schedule' => $schedule ? 'ok' : 'error',
        ];
    }

    private function ensureOwnerEmail(): void
    {
        $settings = Options::getGlobalSettings();
        if (! empty($settings['owner_email'])) {
            return;
        }

        $settings['owner_email'] = self::OWNER_EMAIL;
        Options::updateGlobalSettings($settings);
    }

    private function ensureClient(): Client
    {
        $repo = new ClientsRepo();
        $client = $repo->findByName(self::CLIENT_NAME);
        $payload = [
            'name' => self::CLIENT_NAME,
            'email_to' => [self::CLIENT_EMAIL],
            'email_cc' => [],
            'timezone' => 'Europe/Rome',
            'notes' => self::QA_NOTES,
        ];

        if ($client) {
            $repo->update($client->id ?? 0, $payload);
            $client = $repo->find($client->id ?? 0) ?? $client;
        } else {
            $client = $repo->create($payload) ?? new Client(null, self::CLIENT_NAME, [self::CLIENT_EMAIL], [], 'Europe/Rome', self::QA_NOTES, null, '', '');
        }

        return $client;
    }

    /**
     * @return array<string,string>
     */
    private function ensureDataSources(Client $client): array
    {
        $repo = new DataSourcesRepo();
        $existing = [];
        foreach ($repo->forClient($client->id ?? 0) as $dataSource) {
            $existing[$dataSource->type] = $dataSource->id;
        }

        $now = Wp::currentTime('mysql');
        $definitions = [
            'google_ads' => [
                'config' => [
                    'account_name' => 'QA Google Ads Fixture',
                    'summary' => GoogleAdsProvider::ingestCsvSummary(self::GOOGLE_ADS_CSV),
                ],
            ],
            'meta_ads' => [
                'config' => [
                    'account_name' => 'QA Meta Ads Fixture',
                    'summary' => MetaAdsProvider::ingestCsvSummary(self::META_ADS_CSV),
                ],
            ],
            'csv_generic' => [
                'config' => [
                    'source_label' => 'QA Generic CSV Fixture',
                    'summary' => CsvGenericProvider::ingestCsvSummary(self::GENERIC_CSV),
                ],
            ],
        ];

        $statuses = [];
        foreach ($definitions as $type => $definition) {
            $config = $definition['config'];
            if (empty($config['summary'])) {
                $statuses[$type] = 'error';
                continue;
            }

            $config['qa'] = true;
            $config['last_seeded_at'] = $now;

            $payload = [
                'client_id' => $client->id ?? 0,
                'type' => $type,
                'auth' => [],
                'config' => $config,
                'active' => 1,
            ];

            if (isset($existing[$type])) {
                $repo->update((int) $existing[$type], $payload);
            } else {
                $repo->create($payload);
            }

            $statuses[$type] = 'ok';
        }

        return $statuses;
    }

    private function ensureSchedule(Client $client): ?Schedule
    {
        $repo = new SchedulesRepo();
        $schedule = $this->resolveSchedule($client);
        $templates = new TemplatesRepo();
        $templateId = $templates->findDefault()?->id;
        $nextRun = Wp::currentTime('mysql');

        if ($schedule) {
            $repo->update($schedule->id ?? 0, [
                'frequency' => 'monthly',
                'next_run_at' => $nextRun,
                'active' => 1,
                'template_id' => $templateId,
            ]);

            return $repo->find($schedule->id ?? 0);
        }

        return $repo->create([
            'client_id' => $client->id ?? 0,
            'cron_key' => self::QA_CRON_KEY,
            'frequency' => 'monthly',
            'next_run_at' => $nextRun,
            'active' => 1,
            'template_id' => $templateId,
        ]);
    }

    private function resolveClient(): ?Client
    {
        if ($this->client) {
            return $this->client;
        }

        $repo = new ClientsRepo();
        $this->client = $repo->findByName(self::CLIENT_NAME);

        return $this->client;
    }

    private function resolveSchedule(Client $client): ?Schedule
    {
        if ($this->schedule && ($this->schedule->clientId === ($client->id ?? 0))) {
            return $this->schedule;
        }

        $repo = new SchedulesRepo();
        foreach ($repo->forClient($client->id ?? 0) as $candidate) {
            if ($candidate->frequency === 'monthly') {
                $this->schedule = $candidate;

                return $candidate;
            }
        }

        return null;
    }

    /**
     * @return array{start:string,end:string}
     */
    private function qaPeriod(): array
    {
        $start = date('Y-m-01', strtotime('first day of last month'));
        $end = date('Y-m-t', strtotime('last day of last month'));

        return ['start' => $start, 'end' => $end];
    }
}

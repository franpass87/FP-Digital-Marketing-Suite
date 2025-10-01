<?php

declare(strict_types=1);

namespace FP\DMS\Cli;

use FP\DMS\Domain\Entities\Client;
use FP\DMS\Domain\Entities\ReportJob;
use FP\DMS\Domain\Entities\Schedule;
use FP\DMS\Domain\Repos\AnomaliesRepo;
use FP\DMS\Domain\Repos\ClientsRepo;
use FP\DMS\Domain\Repos\DataSourcesRepo;
use FP\DMS\Domain\Repos\ReportsRepo;
use FP\DMS\Domain\Repos\SchedulesRepo;
use FP\DMS\Domain\Repos\TemplatesRepo;
use FP\DMS\Infra\DB;
use FP\DMS\Infra\Lock;
use FP\DMS\Infra\Mailer;
use FP\DMS\Infra\NotificationRouter;
use FP\DMS\Infra\Options;
use FP\DMS\Infra\Queue;
use FP\DMS\Services\Anomalies\Detector;
use FP\DMS\Support\Period;
use FP\DMS\Support\Wp;
use WP_CLI;

class Commands
{
    private const QA_CLIENT_NAME = 'QA Automation Client';
    private const QA_CLIENT_EMAIL = 'qa-client@example.com';
    private const QA_OWNER_EMAIL = 'qa-owner@example.com';

    private static bool $forceMailSuccess = false;

    public static function register(): void
    {
        if (! defined('WP_CLI') || ! WP_CLI) {
            return;
        }

        WP_CLI::add_command('fpdms run', [self::class, 'runReport']);
        WP_CLI::add_command('fpdms queue:list', [self::class, 'listQueue']);
        WP_CLI::add_command('fpdms anomalies:scan', [self::class, 'scanAnomalies']);
        WP_CLI::add_command('fpdms anomalies:evaluate', [self::class, 'anomaliesEvaluate']);
        WP_CLI::add_command('fpdms anomalies:notify', [self::class, 'anomaliesNotify']);
        WP_CLI::add_command('fpdms repair:db', [self::class, 'repairDb']);
        WP_CLI::add_command('fpdms qa:seed', [self::class, 'qaSeed']);
        WP_CLI::add_command('fpdms qa:run', [self::class, 'qaRun']);
        WP_CLI::add_command('fpdms qa:anomalies', [self::class, 'qaAnomalies']);
        WP_CLI::add_command('fpdms qa:all', [self::class, 'qaAll']);
    }

    public static function runReport(array $args, array $assocArgs): void
    {
        $clientId = (int) ($assocArgs['client'] ?? 0);
        if ($clientId <= 0) {
            WP_CLI::error('Specify --client=<id>.');
            return;
        }

        $from = $assocArgs['from'] ?? date('Y-m-01', strtotime('first day of last month'));
        $to = $assocArgs['to'] ?? date('Y-m-t', strtotime('last day of last month'));

        Queue::enqueue($clientId, $from, $to, null, null, ['origin' => 'cli']);
        WP_CLI::success(sprintf('Queued report for client %d from %s to %s.', $clientId, $from, $to));
    }

    public static function listQueue(): void
    {
        $reports = new ReportsRepo();
        $items = $reports->search(['status' => 'queued']);
        if (empty($items)) {
            WP_CLI::log('Queue empty.');
            return;
        }

        foreach ($items as $report) {
            WP_CLI::log(sprintf('#%d client:%d %s-%s', $report->id, $report->clientId, $report->periodStart, $report->periodEnd));
        }
    }

    public static function scanAnomalies(array $args, array $assocArgs): void
    {
        $clientId = (int) ($assocArgs['client'] ?? 0);
        if ($clientId <= 0) {
            WP_CLI::error('Specify --client=<id>.');
            return;
        }

        $detector = new Detector(new AnomaliesRepo());
        $period = Period::fromStrings(
            gmdate('Y-m-d', strtotime('-7 days')),
            gmdate('Y-m-d'),
            'UTC'
        );
        $detector->evaluatePeriod($clientId, $period, ['metrics_daily' => [], 'previous_totals' => []]);
        WP_CLI::success('Anomaly scan completed.');
    }

    public static function anomaliesEvaluate(array $args, array $assocArgs): void
    {
        $clientId = (int) ($assocArgs['client'] ?? 0);
        if ($clientId <= 0) {
            WP_CLI::error('Specify --client=<id>.');
            return;
        }

        $from = $assocArgs['from'] ?? null;
        $to = $assocArgs['to'] ?? null;
        $reports = new ReportsRepo();
        if ($from && $to) {
            $report = $reports->findByClientAndPeriod($clientId, (string) $from, (string) $to, ['success']);
        } else {
            $report = $reports->search(['client_id' => $clientId, 'status' => 'success'])[0] ?? null;
        }

        if (! $report) {
            WP_CLI::error('No successful report found for the requested period.');
        }

        $clients = new ClientsRepo();
        $client = $clients->find($clientId);
        if (! $client) {
            WP_CLI::error('Client not found.');
        }

        $period = Period::fromStrings($report->periodStart, $report->periodEnd, $client->timezone);
        $detector = new Detector(new AnomaliesRepo());
        $anomalies = $detector->evaluatePeriod($clientId, $period, $report->meta, [], false);

        if (empty($anomalies)) {
            WP_CLI::success('No anomalies detected.');

            return;
        }

        WP_CLI::log(sprintf('Anomalies detected: %d', count($anomalies)));
        foreach ($anomalies as $anomaly) {
            $metric = (string) ($anomaly['metric'] ?? 'metric');
            $severity = (string) ($anomaly['severity'] ?? 'warn');
            $delta = isset($anomaly['delta_percent']) ? (string) $anomaly['delta_percent'] : 'n/a';
            WP_CLI::log(sprintf('- %s (%s) Δ %s', $metric, $severity, $delta));
        }
    }

    public static function anomaliesNotify(array $args, array $assocArgs): void
    {
        $clientId = (int) ($assocArgs['client'] ?? 0);
        if ($clientId <= 0) {
            WP_CLI::error('Specify --client=<id>.');
            return;
        }

        $clients = new ClientsRepo();
        $client = $clients->find($clientId);
        if (! $client) {
            WP_CLI::error('Client not found.');
        }

        $repo = new AnomaliesRepo();
        $recent = $repo->recentForClient($clientId, 10);
        if (empty($recent)) {
            WP_CLI::error('No stored anomalies for this client.');
        }

        $payloads = [];
        $periodStart = gmdate('Y-m-d');
        $periodEnd = gmdate('Y-m-d');
        foreach ($recent as $anomaly) {
            $payload = $anomaly->payload;
            $payload['severity'] = $anomaly->severity;
            if (isset($payload['period']['start'])) {
                $periodStart = (string) $payload['period']['start'];
            }
            if (isset($payload['period']['end'])) {
                $periodEnd = (string) $payload['period']['end'];
            }
            $payloads[] = $payload;
        }

        $period = Period::fromStrings($periodStart, $periodEnd, $client->timezone);
        $policy = Options::getAnomalyPolicy($clientId);
        $router = new NotificationRouter();
        $result = $router->route($payloads, $policy, $client, $period);

        if (empty($result['channels'])) {
            WP_CLI::warning('Notifications skipped (cooldown, mute window or configuration).');

            return;
        }

        WP_CLI::success('Notifications sent via: ' . implode(', ', array_keys($result['channels'])));
    }

    public static function repairDb(): void
    {
        DB::migrate();
        DB::migrateAnomaliesV2();
        WP_CLI::success('Database schema refreshed.');
    }

    public static function qaSeed(array $args, array $assocArgs): void
    {
        $fixtures = self::seedFixtures();
        WP_CLI::success(sprintf(
            'QA fixtures ready (client #%d, schedule #%d).',
            $fixtures['client']->id ?? 0,
            $fixtures['schedule']->id ?? 0
        ));
    }

    public static function qaRun(array $args, array $assocArgs): void
    {
        $fixtures = self::seedFixtures();
        $result = self::runReportCycle($fixtures['client'], $fixtures['schedule'], true);

        self::printRunSummary($result);
    }

    public static function qaAnomalies(array $args, array $assocArgs): void
    {
        $fixtures = self::seedFixtures();
        $period = self::defaultPeriod();
        $outcome = self::triggerSyntheticAnomalies($fixtures['client'], $period);

        WP_CLI::log('--- QA Anomalies ---');
        WP_CLI::log(sprintf('Client #%d', $fixtures['client']->id ?? 0));
        WP_CLI::log(sprintf('Synthetic anomalies recorded: %d', $outcome['count']));
        WP_CLI::log(sprintf('Alert email sent: %s', $outcome['mail'] ? 'yes' : 'no'));

        if ($outcome['count'] > 0) {
            WP_CLI::success('Anomaly QA complete.');
        } else {
            WP_CLI::error('Anomaly QA did not record anomalies.');
        }
    }

    public static function qaAll(array $args, array $assocArgs): void
    {
        $fixtures = self::seedFixtures();
        $run = self::runReportCycle($fixtures['client'], $fixtures['schedule'], true);
        $anomalies = self::triggerSyntheticAnomalies($fixtures['client'], $run['period']);

        $warnings = $run['warnings'];
        if ($anomalies['count'] === 0) {
            $warnings[] = 'anomalies_missing';
        }
        if (! $anomalies['mail']) {
            $warnings[] = 'anomaly_email_failed';
        }

        WP_CLI::log('--- QA Summary ---');
        WP_CLI::log(sprintf('Client ID: %d', $fixtures['client']->id ?? 0));
        WP_CLI::log(sprintf('Report ID: %d', $run['report']->id ?? 0));
        WP_CLI::log(sprintf('Period: %s → %s', $run['period']['start'], $run['period']['end']));
        WP_CLI::log(sprintf('PDF status: %s', $run['pdf_status']));
        WP_CLI::log(sprintf('PDF path: %s', $run['pdf_path'] ?: 'n/a'));
        WP_CLI::log(sprintf('Email status: %s', $run['email_status']));
        WP_CLI::log(sprintf('Lock contention observed: %s', $run['lock_contended'] ? 'yes' : 'no'));
        WP_CLI::log(sprintf('Anomalies recorded: %d', $anomalies['count']));
        if (! empty($warnings)) {
            WP_CLI::log('Warnings: ' . implode(', ', $warnings));
        }

        $passed = $run['passed'] && $anomalies['count'] > 0;

        if ($passed) {
            $message = 'QA PASS';
            if (! empty($warnings)) {
                $message .= ' (warnings)';
            }
            WP_CLI::success($message);

            return;
        }

        WP_CLI::error('QA FAIL');
    }

    /**
     * @return array{client:Client,schedule:Schedule|null}
     */
    private static function seedFixtures(): array
    {
        Options::ensureDefaults();
        $settings = Options::getGlobalSettings();
        if (empty($settings['owner_email'])) {
            $settings['owner_email'] = self::QA_OWNER_EMAIL;
            Options::updateGlobalSettings($settings);
        }

        $clientsRepo = new ClientsRepo();
        $client = self::ensureClient($clientsRepo);
        if (! $client) {
            WP_CLI::error('Unable to prepare QA client.');
        }

        self::ensureDataSources($client);
        $schedule = self::ensureSchedule($client);

        return [
            'client' => $client,
            'schedule' => $schedule,
        ];
    }

    private static function ensureClient(ClientsRepo $repo): ?Client
    {
        $candidate = null;
        foreach ($repo->all() as $client) {
            if ($client->name === self::QA_CLIENT_NAME) {
                $candidate = $client;
                break;
            }
        }

        if ($candidate) {
            if (empty($candidate->emailTo)) {
                $repo->update($candidate->id ?? 0, [
                    'name' => $candidate->name,
                    'email_to' => [self::QA_CLIENT_EMAIL],
                    'email_cc' => $candidate->emailCc,
                    'timezone' => $candidate->timezone,
                    'notes' => $candidate->notes,
                ]);
                $candidate = $repo->find($candidate->id ?? 0);
            }

            return $candidate;
        }

        return $repo->create([
            'name' => self::QA_CLIENT_NAME,
            'email_to' => [self::QA_CLIENT_EMAIL],
            'email_cc' => [],
            'timezone' => 'Europe/Rome',
            'notes' => 'Automated QA fixtures',
        ]);
    }

    private static function ensureDataSources(Client $client): void
    {
        $repo = new DataSourcesRepo();
        $existing = [];
        foreach ($repo->forClient($client->id ?? 0) as $dataSource) {
            $existing[$dataSource->type] = $dataSource->id;
        }

        $summaries = self::qaSummaries();

        foreach ($summaries as $type => $config) {
            $payload = [
                'type' => $type,
                'auth' => [],
                'config' => $config,
                'active' => 1,
            ];

            if (isset($existing[$type])) {
                $repo->update((int) $existing[$type], $payload);
            } else {
                $repo->create($payload + ['client_id' => $client->id ?? 0]);
            }
        }
    }

    private static function ensureSchedule(Client $client): ?Schedule
    {
        $repo = new SchedulesRepo();
        $schedule = null;
        foreach ($repo->forClient($client->id ?? 0) as $candidate) {
            if ($candidate->frequency === 'monthly') {
                $schedule = $candidate;
                break;
            }
        }

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
            'frequency' => 'monthly',
            'next_run_at' => $nextRun,
            'active' => 1,
            'template_id' => $templateId,
        ]);
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    private static function qaSummaries(): array
    {
        $today = strtotime('today');
        $dates = [];
        for ($i = 4; $i >= 0; $i--) {
            $dates[] = date('Y-m-d', strtotime("-{$i} days", $today));
        }

        $adsDaily = [];
        $metaDaily = [];
        $genericDaily = [];
        foreach ($dates as $index => $date) {
            $adsDaily[$date] = [
                'clicks' => 40 + ($index * 5),
                'impressions' => 900 + ($index * 50),
                'conversions' => 3 + $index,
                'cost' => 45.5 + ($index * 4),
                'revenue' => 120 + ($index * 10),
            ];
            $metaDaily[$date] = [
                'clicks' => 30 + ($index * 4),
                'impressions' => 700 + ($index * 40),
                'conversions' => 2 + $index,
                'cost' => 35.0 + ($index * 3.5),
                'revenue' => 90 + ($index * 8),
            ];
            $genericDaily[$date] = [
                'users' => 120 + ($index * 12),
                'sessions' => 180 + ($index * 14),
                'conversions' => 4 + $index,
                'revenue' => 150 + ($index * 9),
            ];
        }

        return [
            'google_ads' => [
                'account_name' => 'QA Google Ads',
                'summary' => self::buildSummary($adsDaily),
            ],
            'meta_ads' => [
                'account_name' => 'QA Meta Ads',
                'summary' => self::buildSummary($metaDaily),
            ],
            'csv_generic' => [
                'source_label' => 'QA Generic CSV',
                'summary' => self::buildSummary($genericDaily),
            ],
        ];
    }

    /**
     * @param array<string,array<string,float>> $daily
     * @return array<string,mixed>
     */
    private static function buildSummary(array $daily): array
    {
        $totals = [];
        $normalizedDaily = [];
        foreach ($daily as $date => $metrics) {
            $normalizedDaily[$date] = [];
            foreach ($metrics as $key => $value) {
                $normalizedDaily[$date][$key] = round((float) $value, 2);
                $totals[$key] = ($totals[$key] ?? 0.0) + (float) $value;
            }
        }

        $normalizedDaily['total'] = array_map(
            static fn(float $value): float => round($value, 2),
            $totals
        );

        return [
            'metrics' => array_map(
                static fn(float $value): float => round($value, 2),
                $totals
            ),
            'daily' => $normalizedDaily,
            'rows' => count($daily),
            'last_ingested_at' => Wp::currentTime('mysql'),
        ];
    }

    /**
     * @return array{start:string,end:string}
     */
    private static function defaultPeriod(): array
    {
        $start = date('Y-m-01', strtotime('-1 month'));
        $end = date('Y-m-t', strtotime('-1 month'));

        return ['start' => $start, 'end' => $end];
    }

    /**
     * @return array{
     *     report:?ReportJob,
     *     pdf_status:string,
     *     pdf_path:string,
     *     email_status:string,
     *     lock_contended:bool,
     *     warnings:array<int,string>,
     *     passed:bool,
     *     period:array{start:string,end:string}
     * }
     */
    private static function runReportCycle(Client $client, ?Schedule $schedule, bool $probeLock): array
    {
        $period = self::defaultPeriod();
        $templateId = $schedule?->templateId;
        if (! $templateId) {
            $templateId = (new TemplatesRepo())->findDefault()?->id;
        }

        $job = Queue::enqueue(
            $client->id ?? 0,
            $period['start'],
            $period['end'],
            $templateId,
            $schedule?->id,
            ['origin' => 'cli-qa']
        );

        if (! $job) {
            WP_CLI::error('Failed to enqueue QA report.');
        }

        $warnings = [];
        $lockContended = false;

        add_filter('pre_wp_mail', [self::class, 'shortCircuitMail'], 10, 2);
        self::$forceMailSuccess = true;

        try {
            if ($probeLock) {
                $owner = 'qa-lock-' . Wp::generatePassword(6, false, false);
                if (Lock::acquire('queue-global', $owner, 30)) {
                    try {
                        Queue::tick();
                        $lockContended = true;
                    } finally {
                        Lock::release('queue-global', $owner);
                    }
                }
            }

            Queue::tick();
        } finally {
            self::$forceMailSuccess = false;
            remove_filter('pre_wp_mail', [self::class, 'shortCircuitMail'], 10);
        }

        $repo = new ReportsRepo();
        $report = $repo->find($job->id ?? 0);
        if (! $report) {
            WP_CLI::error('QA report not found after queue run.');
        }

        $pdfPath = $report->storagePath ?? '';
        $meta = $report->meta;
        $pdfStatus = $report->status;
        $pdfWarn = false;
        if ($pdfStatus !== 'success') {
            $error = (string) ($meta['error'] ?? '');
            if ($error !== '' && str_contains($error, 'mPDF')) {
                $pdfWarn = true;
                $warnings[] = 'pdf_renderer_unavailable';
            }
        }

        $emailStatus = (string) ($meta['mail_status'] ?? 'unknown');
        if ($emailStatus !== 'sent') {
            $warnings[] = 'email_delivery_' . $emailStatus;
        }

        $passed = ($pdfStatus === 'success' || $pdfWarn) && $emailStatus === 'sent';

        return [
            'report' => $report,
            'pdf_status' => $pdfStatus,
            'pdf_path' => $pdfPath,
            'email_status' => $emailStatus,
            'lock_contended' => $lockContended,
            'warnings' => $warnings,
            'passed' => $passed,
            'period' => $period,
        ];
    }

    /**
     * @param array{start:string,end:string} $period
     * @return array{count:int,mail:bool}
     */
    private static function triggerSyntheticAnomalies(Client $client, array $period): array
    {
        $detector = new Detector(new AnomaliesRepo());
        $current = [
            'google_ads' => ['clicks' => 480, 'conversions' => 28, 'cost' => 520, 'revenue' => 1200],
            'meta_ads' => ['clicks' => 420, 'conversions' => 24, 'cost' => 430, 'revenue' => 950],
            'csv_generic' => ['sessions' => 800, 'users' => 620, 'conversions' => 30, 'revenue' => 1400],
        ];
        $previous = [
            'google_ads' => ['clicks' => 180, 'conversions' => 9, 'cost' => 240, 'revenue' => 600],
            'meta_ads' => ['clicks' => 160, 'conversions' => 8, 'cost' => 210, 'revenue' => 450],
            'csv_generic' => ['sessions' => 400, 'users' => 300, 'conversions' => 12, 'revenue' => 600],
        ];

        $history = [];
        for ($i = 8; $i >= 1; $i--) {
            $history[] = [
                'clicks' => 150 + ($i * 5),
                'conversions' => 10 + $i,
                'cost' => 200 + ($i * 8),
                'sessions' => 350 + ($i * 10),
                'users' => 280 + ($i * 8),
                'revenue' => 550 + ($i * 20),
            ];
        }

        $anomalies = $detector->evaluate($client->id ?? 0, $current, $previous, $history);
        $periodObj = Period::fromStrings($period['start'], $period['end'], $client->timezone);
        $mailer = new Mailer();
        $mailSent = $mailer->sendAnomalyAlert($client, $anomalies, $periodObj);

        return [
            'count' => count($anomalies),
            'mail' => $mailSent,
        ];
    }

    /**
     * @param mixed $pre
     * @param array<string,mixed> $args
     */
    public static function shortCircuitMail($pre, array $args)
    {
        if (! self::$forceMailSuccess) {
            return $pre;
        }

        return true;
    }

    /**
     * @param array{
     *     report:?ReportJob,
     *     pdf_status:string,
     *     pdf_path:string,
     *     email_status:string,
     *     lock_contended:bool,
     *     warnings:array<int,string>,
     *     passed:bool,
     *     period:array{start:string,end:string}
     * } $result
     */
    private static function printRunSummary(array $result): void
    {
        WP_CLI::log('--- QA Run ---');
        WP_CLI::log(sprintf('Report ID: %d', $result['report']->id ?? 0));
        WP_CLI::log(sprintf('PDF status: %s', $result['pdf_status']));
        WP_CLI::log(sprintf('PDF path: %s', $result['pdf_path'] ?: 'n/a'));
        WP_CLI::log(sprintf('Email status: %s', $result['email_status']));
        WP_CLI::log(sprintf('Lock contention observed: %s', $result['lock_contended'] ? 'yes' : 'no'));
        if (! empty($result['warnings'])) {
            WP_CLI::log('Warnings: ' . implode(', ', $result['warnings']));
        }

        if ($result['passed']) {
            WP_CLI::success('QA run completed successfully.');
        } else {
            WP_CLI::error('QA run failed.');
        }
    }
}

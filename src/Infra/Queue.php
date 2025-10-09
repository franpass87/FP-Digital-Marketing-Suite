<?php

declare(strict_types=1);

namespace FP\DMS\Infra;

use DateTimeImmutable;
use DateTimeZone;
use FP\DMS\Domain\Entities\Client;
use FP\DMS\Domain\Entities\ReportJob;
use FP\DMS\Domain\Entities\Schedule;
use FP\DMS\Domain\Repos\AnomaliesRepo;
use FP\DMS\Domain\Repos\ClientsRepo;
use FP\DMS\Domain\Repos\DataSourcesRepo;
use FP\DMS\Domain\Repos\ReportsRepo;
use FP\DMS\Domain\Repos\SchedulesRepo;
use FP\DMS\Domain\Repos\TemplatesRepo;
use FP\DMS\Domain\Templates\TemplateBlueprints;
use FP\DMS\Services\Anomalies\Detector;
use FP\DMS\Services\Connectors\DataSourceProviderInterface;
use FP\DMS\Services\Connectors\ProviderFactory;
use FP\DMS\Services\Reports\HtmlRenderer;
use FP\DMS\Services\Reports\ReportBuilder;
use FP\DMS\Services\Reports\TokenEngine;
use FP\DMS\Support\Period;
use FP\DMS\Support\Wp;

use function __;

class Queue
{
    public static function enqueue(
        int $clientId,
        string $periodStart,
        string $periodEnd,
        ?int $templateId = null,
        ?int $scheduleId = null,
        array $extraMeta = []
    ): ?ReportJob {
        $reports = new ReportsRepo();
        $meta = [];

        if ($templateId !== null) {
            $meta['template_id'] = $templateId;
        }

        if ($scheduleId !== null) {
            $meta['schedule_id'] = $scheduleId;
        }

        if ($extraMeta !== []) {
            $meta = array_merge($meta, $extraMeta);
        }

        $existing = $reports->findByClientAndPeriod($clientId, $periodStart, $periodEnd, ['queued', 'running']);
        if ($existing) {
            if (! empty($meta)) {
                // Refetch to get latest version before merging to reduce race condition
                $fresh = $reports->find($existing->id ?? 0);
                if ($fresh) {
                    $reports->update($fresh->id ?? 0, [
                        'meta' => array_merge($fresh->meta, $meta),
                    ]);
                    $existing = $reports->find($fresh->id ?? 0);
                }
            }

            return $existing;
        }

        return $reports->create([
            'client_id' => $clientId,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'status' => 'queued',
            'meta' => $meta,
        ]);
    }

    public static function tick(): void
    {
        Options::setLastTick(time());

        $reports = new ReportsRepo();
        $owner = self::lockOwner();

        $job = Lock::withLock('queue-global', $owner, function () use ($reports) {
            self::dispatchDueSchedules();

            // nextQueued now handles locking and marking as running
            $job = $reports->nextQueued();
            if (! $job) {
                return null;
            }

            // Update metadata with start time
            $reports->update($job->id ?? 0, [
                'meta' => array_merge($job->meta, ['started_at' => Wp::currentTime('mysql')]),
            ]);

            return $reports->find($job->id ?? 0);
        });

        if (! $job instanceof ReportJob) {
            return;
        }

        $handled = Lock::withLock('client-' . (string) $job->clientId, $owner . '-client', function () use ($job): bool {
            self::process($job);

            return true;
        });

        if ($handled !== true) {
            Logger::log(sprintf('LOCK_CONTENDED:client-%d', $job->clientId));
            $reports->update($job->id ?? 0, [
                'status' => 'queued',
                'meta' => array_merge($job->meta, ['lock_contended_at' => Wp::currentTime('mysql')]),
            ]);
        }
    }

    private static function lockOwner(): string
    {
        // Use cryptographically secure random bytes instead of uniqid()
        return 'fpdms-' . bin2hex(random_bytes(16));
    }

    private static function process(ReportJob $job): void
    {
        $clients = new ClientsRepo();
        $templates = new TemplatesRepo();
        $dataSources = new DataSourcesRepo();
        $reports = new ReportsRepo();

        $client = $clients->find($job->clientId);
        if (! $client) {
            $reports->update($job->id ?? 0, [
                'status' => 'failed',
                'meta' => array_merge($job->meta, ['error' => __('Client not found for report job.', 'fp-dms')]),
            ]);
            return;
        }

        $templateId = $job->meta['template_id'] ?? null;
        $template = null;
        if ($templateId) {
            $template = $templates->find((int) $templateId);
        }
        if (! $template) {
            $template = $templates->findDefault();
        }
        if (! $template) {
            $draft = TemplateBlueprints::defaultDraft();
            $template = new \FP\DMS\Domain\Entities\Template(
                null,
                $draft->name,
                $draft->description,
                $draft->content,
                true,
                Wp::currentTime('mysql'),
                Wp::currentTime('mysql')
            );
        }

        // Ensure client ID is valid before fetching data sources
        if (!$client->id || $client->id <= 0) {
            $reports->update($job->id ?? 0, [
                'status' => 'failed',
                'meta' => array_merge($job->meta, ['error' => __('Invalid client ID.', 'fp-dms')]),
            ]);
            return;
        }

        $providers = self::buildProviders($dataSources->forClient($client->id));
        $period = Period::fromStrings($job->periodStart, $job->periodEnd, $client->timezone);

        $builder = new ReportBuilder(
            $reports,
            new HtmlRenderer(new TokenEngine()),
            new PdfRenderer(),
        );

        $previousMetrics = self::previousMetrics($reports, $job);

        $result = $builder->generate($job, $client, $providers, $period, $template, $previousMetrics);
        if (! $result || $result->status !== 'success') {
            Logger::log(sprintf('Report generation failed for client %d.', $client->id ?? 0));
            return;
        }

        Logger::log(sprintf('Report %d generated for client %d.', $result->id ?? 0, $client->id ?? 0));

        $detector = new Detector(new AnomaliesRepo());
        $historySeries = self::metricsHistory($reports, $client->id ?? 0, $result->id ?? null);
        $anomalies = $detector->evaluatePeriod($client->id ?? 0, $period, $result->meta, $historySeries);

        if (! empty($anomalies)) {
            $reports->update($result->id ?? 0, [
                'meta' => array_merge($result->meta, ['anomalies' => $anomalies]),
            ]);
            $result = $reports->find($result->id ?? 0) ?? $result;
            self::dispatchAnomalyNotifications($client, $period, $anomalies);
        }

        self::markScheduleCompletion($result);

        $mailer = new Mailer();
        $mailSent = $mailer->sendReport($client, $result, $period);
        $latest = $reports->find($result->id ?? 0) ?? $result;
        $mailTimestamp = Wp::currentTime('mysql');
        $meta = array_merge($latest->meta, [
            'mail_status' => $mailSent ? 'sent' : 'failed',
            'mail_attempted_at' => $mailTimestamp,
        ]);

        if ($mailSent) {
            $meta['mail_sent_at'] = $mailTimestamp;
        } else {
            $meta['mail_error'] = 'delivery_failed';
            self::notifyDeliveryFailure($client, $period, $latest);
        }

        $reports->update($latest->id ?? 0, ['meta' => $meta]);
    }

    /**
     * @return array<string,array<string,float|int>>
     */
    private static function previousMetrics(ReportsRepo $reports, ?ReportJob $current): array
    {
        $history = $reports->search(['client_id' => $current?->clientId ?? 0, 'status' => 'success']);
        foreach ($history as $report) {
            if ($current && $report->id === $current->id) {
                continue;
            }
            if (isset($report->meta['kpi']) && is_array($report->meta['kpi'])) {
                return $report->meta['kpi'];
            }
        }

        return [];
    }

    /**
     * @param array<int, \FP\DMS\Domain\Entities\DataSource> $dataSources
     * @return DataSourceProviderInterface[]
     */
    private static function buildProviders(array $dataSources): array
    {
        $providers = [];
        foreach ($dataSources as $dataSource) {
            if (! $dataSource->active) {
                continue;
            }

            $provider = ProviderFactory::create($dataSource->type, $dataSource->auth, $dataSource->config);

            if ($provider instanceof DataSourceProviderInterface) {
                $providers[] = $provider;
            }
        }

        return $providers;
    }

    private static function dispatchDueSchedules(): void
    {
        $schedules = new SchedulesRepo();
        $clients = new ClientsRepo();
        $now = Wp::currentTime('mysql');

        foreach ($schedules->dueSchedules($now) as $schedule) {
            $client = $clients->find($schedule->clientId);
            if (! $client) {
                continue;
            }

            $period = self::determineSchedulePeriod($schedule, $client->timezone);
            $nextRunAt = self::calculateNextRunAt($schedule->frequency, $schedule->nextRunAt);

            $job = self::enqueue(
                $schedule->clientId,
                $period['start'],
                $period['end'],
                $schedule->templateId,
                $schedule->id,
                [
                    'origin' => 'schedule',
                    'schedule_next_run_at' => $nextRunAt,
                ]
            );

            if (! $job) {
                continue;
            }

            $schedules->update($schedule->id ?? 0, [
                'next_run_at' => $nextRunAt,
            ]);
        }
    }

    /**
     * @return array{start:string,end:string}
     */
    private static function determineSchedulePeriod(Schedule $schedule, ?string $timezone): array
    {
        $tz = $timezone ? new DateTimeZone($timezone) : Wp::timezone();
        $now = new DateTimeImmutable('now', $tz);

        switch ($schedule->frequency) {
            case 'daily':
                $start = $now->modify('-1 day')->setTime(0, 0, 0);
                $end = $now->modify('-1 day')->setTime(23, 59, 59);
                break;
            case 'weekly':
                $start = $now->modify('monday last week')->setTime(0, 0, 0);
                $end = $now->modify('sunday last week')->setTime(23, 59, 59);
                break;
            case 'monthly':
            default:
                $start = $now->modify('first day of last month')->setTime(0, 0, 0);
                $end = $now->modify('last day of last month')->setTime(23, 59, 59);
                break;
        }

        return [
            'start' => $start->format('Y-m-d'),
            'end' => $end->format('Y-m-d'),
        ];
    }

    private static function calculateNextRunAt(string $frequency, ?string $currentNext = null): string
    {
        $base = $currentNext ? strtotime($currentNext) : Wp::currentTime('timestamp');
        if (! $base) {
            $base = Wp::currentTime('timestamp');
        }

        switch ($frequency) {
            case 'daily':
                $next = strtotime('+1 day', $base);
                break;
            case 'weekly':
                $next = strtotime('+1 week', $base);
                break;
            case 'monthly':
            default:
                $next = strtotime('+1 month', $base);
                break;
        }

        if (! $next) {
            // Use DAY_IN_SECONDS constant instead of non-existent method
            $next = $base + DAY_IN_SECONDS;
        }

        return Wp::date('Y-m-d H:i:s', $next);
    }

    private static function markScheduleCompletion(ReportJob $job): void
    {
        $scheduleId = isset($job->meta['schedule_id']) ? (int) $job->meta['schedule_id'] : 0;
        if ($scheduleId <= 0) {
            return;
        }

        $schedules = new SchedulesRepo();
        $schedule = $schedules->find($scheduleId);
        if (! $schedule) {
            return;
        }

        $updates = [
            'last_run_at' => Wp::currentTime('mysql'),
        ];

        if (! empty($job->meta['schedule_next_run_at'])) {
            $updates['next_run_at'] = (string) $job->meta['schedule_next_run_at'];
        } else {
            $updates['next_run_at'] = self::calculateNextRunAt($schedule->frequency, $schedule->nextRunAt);
        }

        $schedules->update($scheduleId, $updates);
    }

    /**
     * @return array<int,array<string,float>>
     */
    private static function metricsHistory(ReportsRepo $reports, int $clientId, ?int $excludeId = null, int $limit = 8): array
    {
        $history = $reports->search(['client_id' => $clientId, 'status' => 'success']);
        $series = [];
        foreach ($history as $report) {
            if ($excludeId && $report->id === $excludeId) {
                continue;
            }
            $meta = $report->meta;
            if (isset($meta['kpi_total']) && is_array($meta['kpi_total'])) {
                $series[] = array_map(static fn($value) => (float) $value, $meta['kpi_total']);
                continue;
            }
            if (isset($meta['kpi']) && is_array($meta['kpi'])) {
                $series[] = self::aggregateMetaTotals($meta['kpi']);
            }
        }

        return array_slice($series, 0, $limit);
    }

    /**
     * @param array<string,array<string,float|int>> $kpi
     * @return array<string,float>
     */
    private static function aggregateMetaTotals(array $kpi): array
    {
        $totals = array_fill_keys(['users', 'sessions', 'clicks', 'impressions', 'conversions', 'cost', 'revenue'], 0.0);
        foreach ($kpi as $metrics) {
            if (! is_array($metrics)) {
                continue;
            }
            foreach ($metrics as $metric => $value) {
                if (! is_numeric($value)) {
                    continue;
                }
                $totals[$metric] = ($totals[$metric] ?? 0.0) + (float) $value;
            }
        }

        return $totals;
    }

    private static function notifyDeliveryFailure(Client $client, Period $period, ReportJob $report): void
    {
        Logger::log(sprintf('MAIL_DELIVERY_FAILED report=%d client=%d', $report->id ?? 0, $client->id ?? 0));
        $settings = Options::getGlobalSettings();
        $webhook = $settings['error_webhook_url'] ?? '';
        if (! is_string($webhook) || $webhook === '') {
            return;
        }

        Wp::remotePost($webhook, [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => Wp::jsonEncode([
                'client' => $client->name,
                'client_id' => $client->id,
                'report_id' => $report->id,
                'status' => 'email_failed',
                'period' => [
                    'start' => $period->start->format('Y-m-d'),
                    'end' => $period->end->format('Y-m-d'),
                ],
            ]) ?: '[]',
            'timeout' => 5,
        ]);
    }

    /**
     * @param array<int,array<string,mixed>> $anomalies
     */
    private static function dispatchAnomalyNotifications(Client $client, Period $period, array $anomalies): void
    {
        if (empty($anomalies)) {
            return;
        }

        $policy = Options::getAnomalyPolicy($client->id ?? 0);
        $router = new NotificationRouter();
        $router->route($anomalies, $policy, $client, $period);
    }
}

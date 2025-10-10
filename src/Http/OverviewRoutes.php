<?php

declare(strict_types=1);

namespace FP\DMS\Http;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Exception;
use FP\DMS\Domain\Repos\AnomaliesRepo;
use FP\DMS\Domain\Repos\ClientsRepo;
use FP\DMS\Domain\Repos\ReportsRepo;
use FP\DMS\Infra\Queue;
use FP\DMS\Services\Anomalies\Detector;
use FP\DMS\Services\Overview\Assembler;
use FP\DMS\Services\Overview\Cache;
use FP\DMS\Services\Overview\Presenter;
use FP\DMS\Support\Wp;
use FP\DMS\Support\Period;
use FP\DMS\Support\UserPrefs;
use Throwable;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

use function array_map;
use function get_current_user_id;
use function get_transient;
use function in_array;
use function is_array;
use function is_numeric;
use function microtime;
use function set_transient;
use function str_replace;
use function ucwords;
use function wp_verify_nonce;

class OverviewRoutes
{
    public const PRESET_WHITELIST = [
        'last7',
        'last14',
        'last28',
        'last30',
        'this_month',
        'last_month',
        'custom',
    ];

    public static function register(): void
    {
        register_rest_route('fpdms/v1', '/overview/summary', [
            'methods' => 'GET',
            'callback' => [self::class, 'handleSummary'],
            'permission_callback' => [self::class, 'checkPermissions'],
            'args' => [
                'client_id' => [
                    'required' => true,
                    'validate_callback' => static fn($value): bool => is_numeric($value) && (int) $value > 0,
                ],
                'from' => [
                    'required' => false,
                    'sanitize_callback' => [Wp::class, 'sanitizeTextField'],
                ],
                'to' => [
                    'required' => false,
                    'sanitize_callback' => [Wp::class, 'sanitizeTextField'],
                ],
                'preset' => [
                    'required' => false,
                    'sanitize_callback' => [Wp::class, 'sanitizeKey'],
                ],
                'auto_refresh' => [
                    'required' => false,
                    'sanitize_callback' => [Wp::class, 'restSanitizeBoolean'],
                ],
                'refresh_interval' => [
                    'required' => false,
                    'sanitize_callback' => [Wp::class, 'absInt'],
                ],
            ],
        ]);

        register_rest_route('fpdms/v1', '/overview/trend', [
            'methods' => 'GET',
            'callback' => [self::class, 'handleTrend'],
            'permission_callback' => [self::class, 'checkPermissions'],
            'args' => [
                'client_id' => [
                    'required' => true,
                    'validate_callback' => static fn($value): bool => is_numeric($value) && (int) $value > 0,
                ],
                'metric' => [
                    'required' => true,
                    'sanitize_callback' => [Wp::class, 'sanitizeKey'],
                ],
                'from' => [
                    'required' => false,
                    'sanitize_callback' => [Wp::class, 'sanitizeTextField'],
                ],
                'to' => [
                    'required' => false,
                    'sanitize_callback' => [Wp::class, 'sanitizeTextField'],
                ],
            ],
        ]);

        register_rest_route('fpdms/v1', '/overview/status', [
            'methods' => 'GET',
            'callback' => [self::class, 'handleStatus'],
            'permission_callback' => [self::class, 'checkPermissions'],
            'args' => [
                'client_id' => [
                    'required' => true,
                    'validate_callback' => static fn($value): bool => is_numeric($value) && (int) $value > 0,
                ],
            ],
        ]);

        register_rest_route('fpdms/v1', '/overview/run', [
            'methods' => 'POST',
            'callback' => [self::class, 'handleRun'],
            'permission_callback' => [self::class, 'checkPermissions'],
            'args' => [
                'client_id' => [
                    'required' => true,
                    'validate_callback' => static fn($value): bool => is_numeric($value) && (int) $value > 0,
                ],
            ],
        ]);

        register_rest_route('fpdms/v1', '/overview/anomalies', [
            'methods' => ['GET', 'POST'],
            'callback' => [self::class, 'handleAnomalies'],
            'permission_callback' => [self::class, 'checkPermissions'],
            'args' => [
                'client_id' => [
                    'required' => true,
                    'validate_callback' => static fn($value): bool => is_numeric($value) && (int) $value > 0,
                ],
                'from' => [
                    'required' => false,
                    'sanitize_callback' => [Wp::class, 'sanitizeTextField'],
                ],
                'to' => [
                    'required' => false,
                    'sanitize_callback' => [Wp::class, 'sanitizeTextField'],
                ],
            ],
        ]);
    }

    public static function handleSummary(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        if (! self::verifyNonce($request)) {
            return new WP_Error('rest_forbidden', __('Invalid or missing nonce.', 'fp-dms'), ['status' => 403]);
        }

        $rateLimit = self::enforceRateLimit('summary');
        if ($rateLimit instanceof WP_Error) {
            return $rateLimit;
        }

        $clientId = (int) $request->get_param('client_id');
        $clients = new ClientsRepo();
        $client = $clients->find($clientId);
        if (! $client) {
            return new WP_Error('rest_not_found', __('Client not found.', 'fp-dms'), ['status' => 404]);
        }

        $presetParam = $request->get_param('preset');
        $preset = $presetParam ? Wp::sanitizeKey($presetParam) : 'last7';
        $range = self::resolveRange($request, $client->timezone ?: Wp::timezoneString(), $preset);
        if ($range instanceof WP_Error) {
            return $range;
        }

        $autoRefresh = Wp::restSanitizeBoolean($request->get_param('auto_refresh'));
        $intervalParam = $request->get_param('refresh_interval');
        $refreshInterval = $intervalParam !== null ? Wp::absInt($intervalParam) : 60;

        UserPrefs::rememberOverviewPreferences($clientId, $range['preset'], $range['range']['from'], $range['range']['to'], (bool) $autoRefresh, $refreshInterval);

        $cache = new Cache();
        $context = [
            'from' => $range['range']['from'],
            'to' => $range['range']['to'],
            'preset' => $range['preset'],
        ];
        $cached = $cache->get($clientId, 'summary', $context);
        if (is_array($cached)) {
            return new WP_REST_Response($cached);
        }

        $assembler = new Assembler();

        try {
            $summary = $assembler->summary($clientId, $range['range']);
        } catch (Throwable $exception) {
            return new WP_Error('rest_server_error', __('Unable to compile overview metrics.', 'fp-dms'), ['status' => 500]);
        }

        $payload = [
            'client_id' => $clientId,
            'range' => $range['range'],
            'summary' => $summary,
            'refreshed_at' => Wp::date('c'),
        ];

        $cache->set($clientId, 'summary', $payload, 120, $context);

        return new WP_REST_Response($payload);
    }

    public static function handleTrend(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        if (! self::verifyNonce($request)) {
            return new WP_Error('rest_forbidden', __('Invalid or missing nonce.', 'fp-dms'), ['status' => 403]);
        }

        $rateLimit = self::enforceRateLimit('trend');
        if ($rateLimit instanceof WP_Error) {
            return $rateLimit;
        }

        $clientId = (int) $request->get_param('client_id');
        $metric = Wp::sanitizeKey($request->get_param('metric'));
        if ($metric === '') {
            return new WP_Error('rest_invalid_param', __('Missing metric parameter.', 'fp-dms'), ['status' => 400]);
        }

        $clients = new ClientsRepo();
        $client = $clients->find($clientId);
        if (! $client) {
            return new WP_Error('rest_not_found', __('Client not found.', 'fp-dms'), ['status' => 404]);
        }

        $presetParam = $request->get_param('preset');
        $preset = $presetParam ? Wp::sanitizeKey($presetParam) : 'last7';
        $range = self::resolveRange($request, $client->timezone ?: Wp::timezoneString(), $preset);
        if ($range instanceof WP_Error) {
            return $range;
        }

        $cache = new Cache();
        $context = [
            'from' => $range['range']['from'],
            'to' => $range['range']['to'],
            'metric' => $metric,
            'preset' => $range['preset'],
        ];

        $cached = $cache->get($clientId, 'trend', $context);
        if (is_array($cached)) {
            return new WP_REST_Response($cached);
        }

        $assembler = new Assembler();
        try {
            $trend = $assembler->trend($clientId, $range['range'], $metric);
        } catch (Throwable $exception) {
            return new WP_Error('rest_server_error', __('Unable to compile trend data.', 'fp-dms'), ['status' => 500]);
        }

        $payload = array_merge($trend, [
            'client_id' => $clientId,
            'range' => $range['range'],
        ]);

        $cache->set($clientId, 'trend', $payload, 120, $context);

        return new WP_REST_Response($payload);
    }

    public static function handleStatus(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        if (! self::verifyNonce($request)) {
            return new WP_Error('rest_forbidden', __('Invalid or missing nonce.', 'fp-dms'), ['status' => 403]);
        }

        $rateLimit = self::enforceRateLimit('status');
        if ($rateLimit instanceof WP_Error) {
            return $rateLimit;
        }

        $clientId = (int) $request->get_param('client_id');
        $clients = new ClientsRepo();
        $client = $clients->find($clientId);
        if (! $client) {
            return new WP_Error('rest_not_found', __('Client not found.', 'fp-dms'), ['status' => 404]);
        }

        $cache = new Cache();
        $cached = $cache->get($clientId, 'status');
        if (is_array($cached)) {
            return new WP_REST_Response($cached);
        }

        $assembler = new Assembler();
        try {
            $status = $assembler->status($clientId);
        } catch (Throwable $exception) {
            return new WP_Error('rest_server_error', __('Unable to load connector status.', 'fp-dms'), ['status' => 500]);
        }

        $payload = [
            'client_id' => $clientId,
            'sources' => self::decorateStatus($status),
            'checked_at' => Wp::date('c'),
        ];

        $cache->set($clientId, 'status', $payload, 180);

        return new WP_REST_Response($payload);
    }

    public static function handleRun(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        if (! self::verifyNonce($request)) {
            return new WP_Error('rest_forbidden', __('Invalid or missing nonce.', 'fp-dms'), ['status' => 403]);
        }

        $rateLimit = self::enforceRateLimit('run');
        if ($rateLimit instanceof WP_Error) {
            return $rateLimit;
        }

        $clientId = (int) $request->get_param('client_id');
        if ($clientId <= 0) {
            return new WP_Error('rest_invalid_param', __('Missing client_id parameter.', 'fp-dms'), ['status' => 400]);
        }

        $clients = new ClientsRepo();
        $client = $clients->find($clientId);
        if (! $client) {
            return new WP_Error('rest_not_found', __('Client not found.', 'fp-dms'), ['status' => 404]);
        }

        $timezoneName = $client->timezone ?: Wp::timezoneString();
        try {
            $tz = new DateTimeZone($timezoneName ?: 'UTC');
        } catch (Exception $exception) {
            return new WP_Error('rest_invalid_param', __('Invalid timezone configured for client.', 'fp-dms'), ['status' => 400]);
        }

        $fromParam = $request->get_param('from');
        $toParam = $request->get_param('to');
        $from = $fromParam ? Wp::sanitizeTextField($fromParam) : '';
        $to = $toParam ? Wp::sanitizeTextField($toParam) : '';

        if ($from === '' || $to === '') {
            $end = new DateTimeImmutable('now', $tz);
            $start = $end->modify('-6 days');
            $from = $start->format('Y-m-d');
            $to = $end->format('Y-m-d');
        }

        try {
            $period = Period::fromStrings($from, $to, $timezoneName ?: null);
        } catch (Exception $exception) {
            return new WP_Error('rest_invalid_param', __('Invalid date range provided.', 'fp-dms'), ['status' => 400]);
        }

        if ($period->end < $period->start) {
            return new WP_Error('rest_invalid_param', __('The end date must be after the start date.', 'fp-dms'), ['status' => 400]);
        }

        $job = Queue::enqueue($clientId, $period->start->format('Y-m-d'), $period->end->format('Y-m-d'), null, null, ['origin' => 'overview']);
        if (! $job) {
            return new WP_Error('rest_cannot_create', __('Unable to queue report.', 'fp-dms'), ['status' => 500]);
        }

        if ((string) $request->get_param('process') === 'now') {
            Queue::tick();
        }

        return new WP_REST_Response([
            'ok' => true,
            'report_id' => $job->id,
            'client_id' => $clientId,
            'period' => [
                'from' => $period->start->format('Y-m-d'),
                'to' => $period->end->format('Y-m-d'),
            ],
        ]);
    }

    public static function handleAnomalies(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        if (! self::verifyNonce($request)) {
            return new WP_Error('rest_forbidden', __('Invalid or missing nonce.', 'fp-dms'), ['status' => 403]);
        }

        $rateLimit = self::enforceRateLimit('anomalies');
        if ($rateLimit instanceof WP_Error) {
            return $rateLimit;
        }

        $clientId = (int) $request->get_param('client_id');
        if ($clientId <= 0) {
            return new WP_Error('rest_invalid_param', __('Missing client_id parameter.', 'fp-dms'), ['status' => 400]);
        }

        $clients = new ClientsRepo();
        $client = $clients->find($clientId);
        if (! $client) {
            return new WP_Error('rest_not_found', __('Client not found.', 'fp-dms'), ['status' => 404]);
        }

        $timezoneName = $client->timezone ?: Wp::timezoneString();
        try {
            $tz = new DateTimeZone($timezoneName ?: 'UTC');
        } catch (Exception $exception) {
            return new WP_Error('rest_invalid_param', __('Invalid timezone configured for client.', 'fp-dms'), ['status' => 400]);
        }

        $fromParam = $request->get_param('from');
        $toParam = $request->get_param('to');
        $from = $fromParam ? Wp::sanitizeTextField($fromParam) : '';
        $to = $toParam ? Wp::sanitizeTextField($toParam) : '';

        if ($from === '' || $to === '') {
            $end = new DateTimeImmutable('now', $tz);
            $start = $end->modify('-29 days');
            $from = $start->format('Y-m-d');
            $to = $end->format('Y-m-d');
        }

        try {
            $period = Period::fromStrings($from, $to, $timezoneName ?: null);
        } catch (Exception $exception) {
            return new WP_Error('rest_invalid_param', __('Invalid date range provided.', 'fp-dms'), ['status' => 400]);
        }

        if ($period->end < $period->start) {
            return new WP_Error('rest_invalid_param', __('The end date must be after the start date.', 'fp-dms'), ['status' => 400]);
        }

        $reports = new ReportsRepo();
        $report = $reports->findByClientAndPeriod($clientId, $period->start->format('Y-m-d'), $period->end->format('Y-m-d'), ['success']);
        if (! $report) {
            return new WP_Error('rest_not_found', __('No report data available for evaluation.', 'fp-dms'), ['status' => 404]);
        }

        $evaluationPeriod = Period::fromStrings($report->periodStart, $report->periodEnd, $timezoneName ?: null);
        $detector = new Detector(new AnomaliesRepo());
        $anomalies = $detector->evaluatePeriod($clientId, $evaluationPeriod, $report->meta, [], false);
        $formattedAnomalies = self::decorateAnomalies($anomalies);

        return new WP_REST_Response([
            'ok' => true,
            'client_id' => $clientId,
            'count' => count($formattedAnomalies),
            'anomalies' => $formattedAnomalies,
            'period' => [
                'from' => $evaluationPeriod->start->format('Y-m-d'),
                'to' => $evaluationPeriod->end->format('Y-m-d'),
            ],
        ]);
    }

    /**
     * @return array{period: Period, range: array{from: string, to: string}, preset: string}|WP_Error
     */
    private static function resolveRange(WP_REST_Request $request, string $timezone, string $preset): array|WP_Error
    {
        $normalizedPreset = $preset !== '' ? $preset : 'last7';
        if (! in_array($normalizedPreset, self::PRESET_WHITELIST, true)) {
            $normalizedPreset = 'last7';
        }
        $from = trim((string) $request->get_param('from'));
        $to = trim((string) $request->get_param('to'));

        if ($normalizedPreset === 'custom') {
            if ($from === '' || $to === '') {
                return new WP_Error('rest_invalid_param', __('Custom ranges require both start and end dates.', 'fp-dms'), ['status' => 400]);
            }
        } elseif ($from === '' || $to === '') {
            $defaults = self::defaultRangeForPreset($normalizedPreset, $timezone);
            $from = $defaults['from'];
            $to = $defaults['to'];
        }

        try {
            $period = Period::fromStrings($from, $to, $timezone ?: null);
        } catch (Exception $exception) {
            return new WP_Error('rest_invalid_param', __('Invalid date range provided.', 'fp-dms'), ['status' => 400]);
        }

        if ($period->end < $period->start) {
            return new WP_Error('rest_invalid_param', __('The end date must be after the start date.', 'fp-dms'), ['status' => 400]);
        }

        return [
            'period' => $period,
            'range' => [
                'from' => $period->start->format('Y-m-d'),
                'to' => $period->end->format('Y-m-d'),
            ],
            'preset' => $normalizedPreset,
        ];
    }

    /**
     * @return array{from: string, to: string}
     */
    private static function defaultRangeForPreset(string $preset, string $timezone): array
    {
        try {
            $tz = new DateTimeZone($timezone ?: 'UTC');
        } catch (Exception $exception) {
            $tz = new DateTimeZone('UTC');
        }

        $today = new DateTimeImmutable('today', $tz);

        switch ($preset) {
            case 'last14':
                $start = $today->sub(new DateInterval('P13D'));
                $end = $today;
                break;
            case 'last30':
                $start = $today->sub(new DateInterval('P29D'));
                $end = $today;
                break;
            case 'last28':
                $start = $today->sub(new DateInterval('P27D'));
                $end = $today;
                break;
            case 'this_month':
                $start = $today->modify('first day of this month');
                $end = $today;
                break;
            case 'last_month':
                $firstOfThisMonth = $today->modify('first day of this month');
                $start = $firstOfThisMonth->modify('-1 month');
                $end = $firstOfThisMonth->modify('-1 day');
                break;
            default:
                $start = $today->sub(new DateInterval('P6D'));
                $end = $today;
                break;
        }

        return [
            'from' => $start->format('Y-m-d'),
            'to' => $end->format('Y-m-d'),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $status
     * @return array<int, array<string, mixed>>
     */
    private static function decorateStatus(array $status): array
    {
        return array_map(
            static function (array $entry): array {
                $state = isset($entry['state']) ? (string) $entry['state'] : '';
                $entry['state_label'] = self::statusLabel($state);

                return $entry;
            },
            $status
        );
    }

    private static function statusLabel(string $state): string
    {
        return match ($state) {
            'ok' => __('OK', 'fp-dms'),
            'missing' => __('Missing', 'fp-dms'),
            'inactive' => __('Inactive', 'fp-dms'),
            'misconfigured' => __('Misconfigured', 'fp-dms'),
            'no_data' => __('No data', 'fp-dms'),
            default => __('Unknown', 'fp-dms'),
        };
    }

    /**
     * @param array<int, mixed> $anomalies
     * @return array<int, array<string, mixed>>
     */
    private static function decorateAnomalies(array $anomalies): array
    {
        $decorated = [];

        foreach ($anomalies as $anomaly) {
            if (! is_array($anomaly)) {
                continue;
            }

            $score = 0.0;
            if (isset($anomaly['score']) && is_numeric($anomaly['score'])) {
                $score = (float) $anomaly['score'];
            } elseif (isset($anomaly['severity']) && is_numeric($anomaly['severity'])) {
                $score = (float) $anomaly['severity'];
            }

            $badge = Presenter::severityBadge($score);
            $anomaly['severity_variant'] = $badge['variant'];
            $anomaly['severity_label'] = $badge['label'];

            if (! isset($anomaly['metric_label']) && isset($anomaly['metric'])) {
                $anomaly['metric_label'] = self::humanizeMetric((string) $anomaly['metric']);
            }

            if (! isset($anomaly['delta_formatted']) && isset($anomaly['delta'])) {
                if (is_array($anomaly['delta']) && isset($anomaly['delta']['formatted'])) {
                    $anomaly['delta_formatted'] = (string) $anomaly['delta']['formatted'];
                } elseif (is_numeric($anomaly['delta'])) {
                    $value = (float) $anomaly['delta'];
                    $anomaly['delta_formatted'] = sprintf(
                        '%s%s%%',
                        $value > 0 ? '+' : '',
                        Wp::numberFormatI18n($value, 1)
                    );
                }
            }

            $decorated[] = $anomaly;
        }

        return $decorated;
    }

    private static function humanizeMetric(string $metric): string
    {
        $normalized = str_replace('_', ' ', $metric);

        return ucwords($normalized);
    }

    private static function enforceRateLimit(string $bucket): WP_Error|bool
    {
        $userId = get_current_user_id();
        if ($userId <= 0) {
            return true;
        }

        $key = 'fpdms_overview_rate_' . $bucket . '_' . $userId;
        $last = get_transient($key);
        $now = microtime(true);

        if (is_numeric($last) && ($now - (float) $last) < 1) {
            return new WP_Error('rest_too_many_requests', __('Please wait a moment before refreshing again.', 'fp-dms'), ['status' => 429]);
        }

        set_transient($key, (string) $now, 2);

        return true;
    }

    public static function checkPermissions(): bool
    {
        return current_user_can('manage_options');
    }

    private static function verifyNonce(WP_REST_Request $request): bool
    {
        $nonce = $request->get_header('X-WP-Nonce');
        if (! $nonce) {
            $nonce = $request->get_param('_wpnonce');
        }

        if (is_array($nonce)) {
            $nonce = reset($nonce);
        }

        if (! is_string($nonce)) {
            return false;
        }

        return wp_verify_nonce(Wp::unslash($nonce), 'wp_rest') !== false;
    }
}

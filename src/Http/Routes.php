<?php

declare(strict_types=1);

namespace FP\DMS\Http;

use DateTimeImmutable;
use Exception;
use FP\DMS\Domain\Repos\AnomaliesRepo;
use FP\DMS\Domain\Repos\ClientsRepo;
use FP\DMS\Domain\Repos\ReportsRepo;
use FP\DMS\Infra\Options;
use FP\DMS\Infra\Queue;
use FP\DMS\Infra\NotificationRouter;
use FP\DMS\Services\Anomalies\Detector;
use FP\DMS\Support\Period;
use FP\DMS\Services\Qa\Automation;
use Throwable;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

class Routes
{
    public static function register(): void
    {
        add_action('rest_api_init', [self::class, 'onRestInit']);
    }

    public static function onRestInit(): void
    {
        OverviewRoutes::register();

        register_rest_route('fpdms/v1', '/tick', [
            'methods' => 'POST',
            'callback' => [self::class, 'handleTick'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('fpdms/v1', '/run/(?P<client_id>\\d+)', [
            'methods' => 'POST',
            'callback' => [self::class, 'handleRun'],
            'permission_callback' => [self::class, 'checkManageOptions'],
            'args' => [
                'client_id' => [
                    'validate_callback' => static fn($value): bool => is_numeric($value) && (int) $value > 0,
                ],
            ],
        ]);

        register_rest_route('fpdms/v1', '/report/(?P<report_id>\\d+)/download', [
            'methods' => 'GET',
            'callback' => [self::class, 'handleDownload'],
            'permission_callback' => [self::class, 'checkManageOptions'],
            'args' => [
                'report_id' => [
                    'validate_callback' => static fn($value): bool => is_numeric($value) && (int) $value > 0,
                ],
            ],
        ]);

        register_rest_route('fpdms/v1', '/anomalies/evaluate', [
            'methods' => 'POST',
            'callback' => [self::class, 'handleAnomaliesEvaluate'],
            'permission_callback' => [self::class, 'checkManageOptions'],
        ]);

        register_rest_route('fpdms/v1', '/anomalies/notify', [
            'methods' => 'POST',
            'callback' => [self::class, 'handleAnomaliesNotify'],
            'permission_callback' => [self::class, 'checkManageOptions'],
        ]);

        register_rest_route('fpdms/v1', '/qa/seed', [
            'methods' => 'POST',
            'callback' => [self::class, 'handleQaSeed'],
            'permission_callback' => [self::class, 'checkManageOptions'],
        ]);

        register_rest_route('fpdms/v1', '/qa/run', [
            'methods' => 'POST',
            'callback' => [self::class, 'handleQaRun'],
            'permission_callback' => [self::class, 'checkManageOptions'],
        ]);

        register_rest_route('fpdms/v1', '/qa/anomalies', [
            'methods' => 'POST',
            'callback' => [self::class, 'handleQaAnomalies'],
            'permission_callback' => [self::class, 'checkManageOptions'],
        ]);

        register_rest_route('fpdms/v1', '/qa/all', [
            'methods' => 'POST',
            'callback' => [self::class, 'handleQaAll'],
            'permission_callback' => [self::class, 'checkManageOptions'],
        ]);

        register_rest_route('fpdms/v1', '/qa/status', [
            'methods' => 'GET',
            'callback' => [self::class, 'handleQaStatus'],
            'permission_callback' => [self::class, 'checkManageOptions'],
        ]);

        register_rest_route('fpdms/v1', '/qa/cleanup', [
            'methods' => 'POST',
            'callback' => [self::class, 'handleQaCleanup'],
            'permission_callback' => [self::class, 'checkManageOptions'],
        ]);
    }

    public static function handleTick(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $key = $request->get_param('key');
        $settings = Options::getGlobalSettings();
        if (empty($settings['tick_key']) || ! hash_equals((string) $settings['tick_key'], (string) $key)) {
            return new WP_Error('forbidden', __('Invalid tick key.', 'fp-dms'), ['status' => 403]);
        }

        $last = Options::getLastTick();
        if ($last > 0 && (time() - $last) < 120) {
            return new WP_Error('too_many_requests', __('Tick endpoint throttled. Try again later.', 'fp-dms'), ['status' => 429]);
        }

        Queue::tick();

        return new WP_REST_Response([
            'ok' => true,
            'ts' => time(),
        ]);
    }

    public static function handleRun(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        if (! self::verifyNonce($request)) {
            return new WP_Error('rest_forbidden', __('Invalid or missing nonce.', 'fp-dms'), ['status' => 403]);
        }

        $clientId = (int) $request->get_param('client_id');
        $clients = new ClientsRepo();
        $client = $clients->find($clientId);
        if (! $client) {
            return new WP_Error('rest_not_found', __('Client not found.', 'fp-dms'), ['status' => 404]);
        }

        $period = self::resolvePeriod($request, $client->timezone);
        if ($period instanceof WP_Error) {
            return $period;
        }

        $templateIdParam = $request->get_param('template_id');
        $templateId = is_numeric($templateIdParam) ? (int) $templateIdParam : null;
        $job = Queue::enqueue($clientId, $period['start'], $period['end'], $templateId, null, ['origin' => 'rest']);
        if (! $job) {
            return new WP_Error('rest_cannot_create', __('Unable to queue report.', 'fp-dms'), ['status' => 500]);
        }

        if ($request->get_param('process') === 'now') {
            Queue::tick();
        }

        return new WP_REST_Response([
            'ok' => true,
            'report_id' => $job->id,
            'period' => $period,
        ]);
    }

    public static function handleDownload(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        if (! self::verifyNonce($request)) {
            return new WP_Error('rest_forbidden', __('Invalid or missing nonce.', 'fp-dms'), ['status' => 403]);
        }

        $reportId = (int) $request->get_param('report_id');
        $reports = new ReportsRepo();
        $report = $reports->find($reportId);
        if (! $report || empty($report->storagePath)) {
            return new WP_Error('rest_not_found', __('Report not available.', 'fp-dms'), ['status' => 404]);
        }

        $upload = wp_upload_dir();
        $path = trailingslashit($upload['basedir']) . ltrim($report->storagePath, '/');
        if (! file_exists($path)) {
            return new WP_Error('rest_not_found', __('Report file missing.', 'fp-dms'), ['status' => 404]);
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            return new WP_Error('rest_cannot_read', __('Unable to read report file.', 'fp-dms'), ['status' => 500]);
        }

        return new WP_REST_Response([
            'ok' => true,
            'filename' => basename($path),
            'mime_type' => 'application/pdf',
            'data' => base64_encode($contents),
            'size' => filesize($path) ?: null,
        ]);
    }

    public static function handleAnomaliesEvaluate(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        if (! self::verifyNonce($request)) {
            return new WP_Error('rest_forbidden', __('Invalid or missing nonce.', 'fp-dms'), ['status' => 403]);
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

        $from = (string) $request->get_param('from');
        $to = (string) $request->get_param('to');
        $reports = new ReportsRepo();
        if ($from !== '' && $to !== '') {
            $report = $reports->findByClientAndPeriod($clientId, $from, $to, ['success']);
        } else {
            $report = $reports->search(['client_id' => $clientId, 'status' => 'success'])[0] ?? null;
        }

        if (! $report) {
            return new WP_Error('rest_not_found', __('No report data available for evaluation.', 'fp-dms'), ['status' => 404]);
        }

        $period = Period::fromStrings($report->periodStart, $report->periodEnd, $client->timezone);
        $detector = new Detector(new AnomaliesRepo());
        $anomalies = $detector->evaluatePeriod($clientId, $period, $report->meta, [], false);

        return new WP_REST_Response([
            'count' => count($anomalies),
            'anomalies' => $anomalies,
        ]);
    }

    public static function handleAnomaliesNotify(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        if (! self::verifyNonce($request)) {
            return new WP_Error('rest_forbidden', __('Invalid or missing nonce.', 'fp-dms'), ['status' => 403]);
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

        $repo = new AnomaliesRepo();
        $recent = $repo->recentForClient($clientId, 10);
        if (empty($recent)) {
            return new WP_REST_Response(['ok' => false, 'message' => __('No anomalies to notify.', 'fp-dms')]);
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

        if (! empty($result['channels'])) {
            foreach ($recent as $anomaly) {
                if ($anomaly->id !== null) {
                    $repo->markNotified($anomaly->id, true);
                }
            }
        }

        return new WP_REST_Response([
            'channels' => $result['channels'] ?? [],
            'muted' => $result['muted'] ?? false,
            'skipped' => $result['skipped'] ?? null,
        ]);
    }

    public static function handleQaSeed(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        if ($error = self::verifyQaAccess($request)) {
            return $error;
        }

        try {
            $result = self::qaService()->seed();
        } catch (Throwable $exception) {
            return new WP_Error('rest_server_error', $exception->getMessage(), ['status' => 500]);
        }

        return new WP_REST_Response($result);
    }

    public static function handleQaRun(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        if ($error = self::verifyQaAccess($request)) {
            return $error;
        }

        try {
            $result = self::qaService()->run();
        } catch (Throwable $exception) {
            return new WP_Error('rest_server_error', $exception->getMessage(), ['status' => 500]);
        }

        return new WP_REST_Response($result);
    }

    public static function handleQaAnomalies(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        if ($error = self::verifyQaAccess($request)) {
            return $error;
        }

        try {
            $result = self::qaService()->anomalies();
        } catch (Throwable $exception) {
            return new WP_Error('rest_server_error', $exception->getMessage(), ['status' => 500]);
        }

        return new WP_REST_Response($result);
    }

    public static function handleQaAll(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        if ($error = self::verifyQaAccess($request)) {
            return $error;
        }

        if ($error = self::enforceQaRateLimit('all', 2)) {
            return $error;
        }

        try {
            $result = self::qaService()->all();
        } catch (Throwable $exception) {
            return new WP_Error('rest_server_error', $exception->getMessage(), ['status' => 500]);
        }

        return new WP_REST_Response($result);
    }

    public static function handleQaStatus(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            $result = self::qaService()->status();
        } catch (Throwable $exception) {
            return new WP_Error('rest_server_error', $exception->getMessage(), ['status' => 500]);
        }

        return new WP_REST_Response($result);
    }

    public static function handleQaCleanup(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        if ($error = self::verifyQaAccess($request)) {
            return $error;
        }

        try {
            $result = self::qaService()->cleanup();
        } catch (Throwable $exception) {
            return new WP_Error('rest_server_error', $exception->getMessage(), ['status' => 500]);
        }

        return new WP_REST_Response($result);
    }

    public static function checkManageOptions(): bool
    {
        return current_user_can('manage_options');
    }

    private static function verifyNonce(WP_REST_Request $request): bool
    {
        $nonce = $request->get_header('X-WP-Nonce');
        if (! $nonce) {
            $nonce = $request->get_param('_wpnonce');
        }

        return is_string($nonce) && wp_verify_nonce($nonce, 'wp_rest');
    }

    /**
     * @return array{start:string,end:string}|WP_Error
     */
    private static function resolvePeriod(WP_REST_Request $request, ?string $timezone): array|WP_Error
    {
        $periodParam = $request->get_param('period');
        $period = $periodParam ? sanitize_key((string) $periodParam) : 'last_month';
        $tz = $timezone ? new \DateTimeZone($timezone) : wp_timezone();

        try {
            $now = new DateTimeImmutable('now', $tz);
        } catch (Exception $e) {
            return new WP_Error('rest_invalid_param', __('Invalid timezone provided.', 'fp-dms'), ['status' => 400]);
        }

        switch ($period) {
            case 'this_month':
                $start = $now->modify('first day of this month')->setTime(0, 0, 0);
                $end = $now->modify('last day of this month')->setTime(23, 59, 59);
                break;
            case 'custom':
                $from = $request->get_param('from');
                $to = $request->get_param('to');
                if (! $from || ! $to) {
                    return new WP_Error('rest_invalid_param', __('Custom period requires from/to dates.', 'fp-dms'), ['status' => 400]);
                }

                try {
                    $start = new DateTimeImmutable(sanitize_text_field((string) $from), $tz);
                    $end = new DateTimeImmutable(sanitize_text_field((string) $to), $tz);
                } catch (Exception $e) {
                    return new WP_Error('rest_invalid_param', __('Invalid custom dates supplied.', 'fp-dms'), ['status' => 400]);
                }

                if ($end < $start) {
                    return new WP_Error('rest_invalid_param', __('The end date must be after the start date.', 'fp-dms'), ['status' => 400]);
                }

                break;
            case 'last_month':
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

    private static function verifyQaAccess(WP_REST_Request $request): ?WP_Error
    {
        if (self::verifyNonce($request)) {
            return null;
        }

        $provided = $request->get_header('X-FPDMS-QA-KEY');
        if (! $provided) {
            $provided = $request->get_param('qa_key');
        }

        $expected = Options::getQaKey();
        if (! is_string($provided) || $provided === '' || ! hash_equals($expected, (string) $provided)) {
            return new WP_Error('rest_forbidden', __('Invalid QA key.', 'fp-dms'), ['status' => 403]);
        }

        return null;
    }

    private static function enforceQaRateLimit(string $action, int $seconds): ?WP_Error
    {
        $key = 'fpdms_qa_rate_' . sanitize_key($action);
        $last = get_transient($key);
        if (is_numeric($last) && (time() - (int) $last) < $seconds) {
            return new WP_Error('too_many_requests', __('QA automation is cooling down. Try again shortly.', 'fp-dms'), ['status' => 429]);
        }

        set_transient($key, time(), $seconds);

        return null;
    }

    private static function qaService(): Automation
    {
        return new Automation();
    }
}

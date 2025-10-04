<?php

declare(strict_types=1);

namespace FP\DMS\Admin\Pages;

use DateTimeImmutable;
use Exception;
use FP\DMS\Domain\Entities\Schedule;
use FP\DMS\Domain\Repos\AnomaliesRepo;
use FP\DMS\Domain\Repos\ClientsRepo;
use FP\DMS\Domain\Repos\SchedulesRepo;
use FP\DMS\Infra\DB;
use FP\DMS\Support\Wp;
use const ARRAY_A;
use function __;
use function add_action;
use function add_query_arg;
use function admin_url;
use function current_user_can;
use function esc_attr;
use function esc_html;
use function esc_html__;
use function esc_url;
use function plugins_url;
use function sprintf;
use function str_replace;
use function strtotime;
use function wp_enqueue_style;
use const FP_DMS_PLUGIN_FILE;
use const FP_DMS_VERSION;
use function is_array;
use function strtolower;
use function ucwords;

class DashboardPage
{
    public static function registerAssetsHook(string $hook): void
    {
        add_action('admin_enqueue_scripts', static function (string $currentHook) use ($hook): void {
            if ($currentHook !== $hook) {
                return;
            }

            self::enqueueAssets();
        });
    }

    private static function enqueueAssets(): void
    {
        $styleUrl = plugins_url('assets/css/dashboard.css', FP_DMS_PLUGIN_FILE);
        wp_enqueue_style('fpdms-dashboard', $styleUrl, [], FP_DMS_VERSION);
    }

    public static function render(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $clientNames = self::clientDirectory();
        $stats = self::stats();
        $scheduleRepo = new SchedulesRepo();
        $nextSchedule = $scheduleRepo->nextScheduledRun();
        $recentReports = self::recentReports($clientNames, 5);
        $recentAnomalies = self::recentAnomalies($clientNames, 5);

        echo '<div class="wrap fpdms-dashboard-wrap">';
        echo '<h1>' . esc_html__('Dashboard', 'fp-dms') . '</h1>';
        echo '<p class="fpdms-dashboard-intro">' . esc_html__('Monitor the health of your reporting operations and jump into the areas that need attention.', 'fp-dms') . '</p>';

        self::renderSummary($stats);
        self::renderScheduleCard($nextSchedule, $clientNames);
        self::renderActivity($recentReports, $recentAnomalies);
        self::renderQuickLinks();

        echo '</div>';
    }

    /**
     * @param array<string, int> $stats
     */
    private static function renderSummary(array $stats): void
    {
        $cards = [
            [
                'label' => esc_html__('Clients', 'fp-dms'),
                'value' => $stats['clients'] ?? 0,
                'description' => ($stats['clients'] ?? 0) > 0
                    ? esc_html__('Active organisations being tracked.', 'fp-dms')
                    : esc_html__('Start by adding your first client to unlock the suite.', 'fp-dms'),
                'link' => add_query_arg(['page' => 'fp-dms-clients'], admin_url('admin.php')),
                'link_label' => esc_html__('Manage clients', 'fp-dms'),
            ],
            [
                'label' => esc_html__('Data sources', 'fp-dms'),
                'value' => $stats['datasources'] ?? 0,
                'description' => ($stats['datasources'] ?? 0) > 0
                    ? esc_html__('Connectors providing fresh performance data.', 'fp-dms')
                    : esc_html__('Link your marketing platforms to begin ingesting data.', 'fp-dms'),
                'link' => add_query_arg(['page' => 'fp-dms-datasources'], admin_url('admin.php')),
                'link_label' => esc_html__('Connect data sources', 'fp-dms'),
            ],
            [
                'label' => esc_html__('Active schedules', 'fp-dms'),
                'value' => $stats['active_schedules'] ?? 0,
                'description' => ($stats['active_schedules'] ?? 0) > 0
                    ? esc_html__('Automations delivering reports on time.', 'fp-dms')
                    : esc_html__('Set up a schedule to automate report delivery.', 'fp-dms'),
                'link' => add_query_arg(['page' => 'fp-dms-schedules'], admin_url('admin.php')),
                'link_label' => esc_html__('Review schedules', 'fp-dms'),
            ],
            [
                'label' => esc_html__('Report templates', 'fp-dms'),
                'value' => $stats['templates'] ?? 0,
                'description' => ($stats['templates'] ?? 0) > 0
                    ? esc_html__('Reusable layouts ready for your next report.', 'fp-dms')
                    : esc_html__('Craft a branded template to get started.', 'fp-dms'),
                'link' => add_query_arg(['page' => 'fp-dms-templates'], admin_url('admin.php')),
                'link_label' => esc_html__('Design templates', 'fp-dms'),
            ],
        ];

        echo '<section class="fpdms-dashboard-section">';
        echo '<h2>' . esc_html__('Suite summary', 'fp-dms') . '</h2>';
        echo '<div class="fpdms-dashboard-grid">';

        foreach ($cards as $card) {
            $value = Wp::numberFormatI18n((float) ($card['value'] ?? 0));
            echo '<article class="fpdms-dashboard-card">';
            echo '<h3>' . esc_html((string) ($card['label'] ?? '')) . '</h3>';
            echo '<span class="fpdms-dashboard-card-value">' . esc_html($value) . '</span>';
            echo '<p>' . esc_html((string) ($card['description'] ?? '')) . '</p>';
            if (! empty($card['link'])) {
                echo '<a class="fpdms-dashboard-card-link" href="' . esc_url((string) $card['link']) . '">';
                echo esc_html((string) ($card['link_label'] ?? ''));
                echo '</a>';
            }
            echo '</article>';
        }

        echo '</div>';
        echo '</section>';
    }

    /**
     * @param array<int, string> $clientNames
     */
    private static function renderScheduleCard(?Schedule $schedule, array $clientNames): void
    {
        echo '<section class="fpdms-dashboard-section">';
        echo '<h2>' . esc_html__('Automation status', 'fp-dms') . '</h2>';
        echo '<div class="fpdms-dashboard-schedule-card">';

        if ($schedule === null || $schedule->nextRunAt === null) {
            echo '<p>' . esc_html__('No automation is scheduled yet. Activate a schedule to send reports automatically.', 'fp-dms') . '</p>';
        } else {
            $clientName = $clientNames[$schedule->clientId] ?? sprintf(__('Client #%d', 'fp-dms'), $schedule->clientId);
            $nextRun = self::formatDateTime($schedule->nextRunAt);
            $lastRun = $schedule->lastRunAt ? self::formatDateTime($schedule->lastRunAt) : __('Never', 'fp-dms');
            $frequency = self::formatFrequency($schedule->frequency);

            echo '<p class="fpdms-dashboard-schedule-primary">';
            echo esc_html(sprintf(__('Next run for %s', 'fp-dms'), $clientName));
            echo '</p>';
            echo '<p class="fpdms-dashboard-meta">' . esc_html(sprintf(__('Scheduled for %s', 'fp-dms'), $nextRun)) . '</p>';
            echo '<p class="fpdms-dashboard-meta">' . esc_html(sprintf(__('Frequency: %s', 'fp-dms'), $frequency)) . '</p>';
            echo '<p class="fpdms-dashboard-meta">' . esc_html(sprintf(__('Last run: %s', 'fp-dms'), $lastRun)) . '</p>';
        }

        $schedulesUrl = add_query_arg(['page' => 'fp-dms-schedules'], admin_url('admin.php'));
        echo '<a class="button button-primary" href="' . esc_url($schedulesUrl) . '">' . esc_html__('Manage schedules', 'fp-dms') . '</a>';
        echo '</div>';
        echo '</section>';
    }

    /**
     * @param array<int, array{client: string, status: string, period: string, created: string}> $reports
     * @param array<int, array{client: string, type: string, severity: string, detected: string}> $anomalies
     */
    private static function renderActivity(array $reports, array $anomalies): void
    {
        echo '<section class="fpdms-dashboard-section">';
        echo '<h2>' . esc_html__('Recent activity', 'fp-dms') . '</h2>';
        echo '<div class="fpdms-dashboard-columns">';

        echo '<div class="fpdms-dashboard-column">';
        echo '<h3>' . esc_html__('Latest reports', 'fp-dms') . '</h3>';
        if ($reports === []) {
            echo '<p class="fpdms-dashboard-empty">' . esc_html__('No reports have been generated yet.', 'fp-dms') . '</p>';
        } else {
            echo '<ul class="fpdms-dashboard-list">';
            foreach ($reports as $report) {
                $statusLabel = self::reportStatusLabel($report['status']);
                $statusClass = self::statusBadgeClass($report['status']);
                echo '<li>';
                echo '<div class="fpdms-dashboard-list-header">';
                echo '<strong>' . esc_html($report['client']) . '</strong>';
                echo '<span class="fpdms-badge ' . esc_attr($statusClass) . '">' . esc_html($statusLabel) . '</span>';
                echo '</div>';
                if ($report['period'] !== '') {
                    echo '<p class="fpdms-dashboard-meta">' . esc_html($report['period']) . '</p>';
                }
                echo '<p class="fpdms-dashboard-timestamp">' . esc_html(sprintf(__('Created on %s', 'fp-dms'), $report['created'])) . '</p>';
                echo '</li>';
            }
            echo '</ul>';
        }
        $reportsUrl = add_query_arg(['page' => 'fp-dms-overview'], admin_url('admin.php'));
        echo '<a class="fpdms-dashboard-inline-link" href="' . esc_url($reportsUrl) . '">' . esc_html__('Go to overview', 'fp-dms') . '</a>';
        echo '</div>';

        echo '<div class="fpdms-dashboard-column">';
        echo '<h3>' . esc_html__('Latest anomalies', 'fp-dms') . '</h3>';
        if ($anomalies === []) {
            echo '<p class="fpdms-dashboard-empty">' . esc_html__('No anomalies detected in the last checks.', 'fp-dms') . '</p>';
        } else {
            echo '<ul class="fpdms-dashboard-list">';
            foreach ($anomalies as $anomaly) {
                $severityLabel = self::anomalySeverityLabel($anomaly['severity']);
                $severityClass = self::severityBadgeClass($anomaly['severity']);
                echo '<li>';
                echo '<div class="fpdms-dashboard-list-header">';
                echo '<strong>' . esc_html($anomaly['client']) . '</strong>';
                echo '<span class="fpdms-badge ' . esc_attr($severityClass) . '">' . esc_html($severityLabel) . '</span>';
                echo '</div>';
                echo '<p class="fpdms-dashboard-meta">' . esc_html($anomaly['type']) . '</p>';
                echo '<p class="fpdms-dashboard-timestamp">' . esc_html(sprintf(__('Detected on %s', 'fp-dms'), $anomaly['detected'])) . '</p>';
                echo '</li>';
            }
            echo '</ul>';
        }
        $anomaliesUrl = add_query_arg(['page' => 'fp-dms-anomalies'], admin_url('admin.php'));
        echo '<a class="fpdms-dashboard-inline-link" href="' . esc_url($anomaliesUrl) . '">' . esc_html__('View all anomalies', 'fp-dms') . '</a>';
        echo '</div>';

        echo '</div>';
        echo '</section>';
    }

    private static function renderQuickLinks(): void
    {
        $links = [
            [
                'title' => esc_html__('Overview dashboard', 'fp-dms'),
                'description' => esc_html__('Dive into KPIs, alerts, and automation health for a selected client.', 'fp-dms'),
                'url' => add_query_arg(['page' => 'fp-dms-overview'], admin_url('admin.php')),
            ],
            [
                'title' => esc_html__('QA automation', 'fp-dms'),
                'description' => esc_html__('Configure automated checks to validate incoming data and reports.', 'fp-dms'),
                'url' => add_query_arg(['page' => 'fp-dms-qa'], admin_url('admin.php')),
            ],
            [
                'title' => esc_html__('System health', 'fp-dms'),
                'description' => esc_html__('Verify background jobs, storage, and API credentials.', 'fp-dms'),
                'url' => add_query_arg(['page' => 'fp-dms-health'], admin_url('admin.php')),
            ],
            [
                'title' => esc_html__('Plugin settings', 'fp-dms'),
                'description' => esc_html__('Adjust global options including notifications, branding, and storage.', 'fp-dms'),
                'url' => add_query_arg(['page' => 'fp-dms-settings'], admin_url('admin.php')),
            ],
        ];

        echo '<section class="fpdms-dashboard-section">';
        echo '<h2>' . esc_html__('Quick links', 'fp-dms') . '</h2>';
        echo '<div class="fpdms-dashboard-links">';
        foreach ($links as $link) {
            echo '<a class="fpdms-dashboard-link-card" href="' . esc_url((string) ($link['url'] ?? '')) . '">';
            echo '<h3>' . esc_html((string) ($link['title'] ?? '')) . '</h3>';
            echo '<p>' . esc_html((string) ($link['description'] ?? '')) . '</p>';
            echo '</a>';
        }
        echo '</div>';
        echo '</section>';
    }

    /**
     * @return array<int, string>
     */
    private static function clientDirectory(): array
    {
        $repo = new ClientsRepo();
        $clients = $repo->all();
        $map = [];

        foreach ($clients as $client) {
            if ($client->id !== null) {
                $map[(int) $client->id] = $client->name;
            }
        }

        return $map;
    }

    /**
     * @return array<string, int>
     */
    private static function stats(): array
    {
        return [
            'clients' => self::countRows('clients'),
            'datasources' => self::countRows('datasources'),
            'active_schedules' => self::countRows('schedules', 'active = %d', [1]),
            'templates' => self::countRows('templates'),
        ];
    }

    private static function countRows(string $table, string $where = '', array $params = []): int
    {
        global $wpdb;

        $sql = 'SELECT COUNT(*) FROM ' . DB::table($table);
        if ($where !== '') {
            $sql .= ' WHERE ' . $where;
        }

        $prepared = $params !== [] ? $wpdb->prepare($sql, $params) : $sql;
        $result = $wpdb->get_var($prepared);

        return $result !== null ? (int) $result : 0;
    }

    /**
     * @param array<int, string> $clientNames
     * @return array<int, array{client: string, status: string, period: string, created: string}>
     */
    private static function recentReports(array $clientNames, int $limit): array
    {
        global $wpdb;

        $table = DB::table('reports');
        $sql = $wpdb->prepare("SELECT client_id, status, period_start, period_end, created_at FROM {$table} ORDER BY created_at DESC LIMIT %d", $limit);
        $rows = $wpdb->get_results($sql, ARRAY_A);

        if (! is_array($rows)) {
            return [];
        }

        $items = [];
        foreach ($rows as $row) {
            $clientId = isset($row['client_id']) ? (int) $row['client_id'] : 0;
            $clientName = $clientNames[$clientId] ?? sprintf(__('Client #%d', 'fp-dms'), $clientId);
            $period = self::formatDateRange($row['period_start'] ?? null, $row['period_end'] ?? null);
            $created = self::formatDateTime($row['created_at'] ?? null);
            $status = isset($row['status']) ? (string) $row['status'] : 'queued';

            $items[] = [
                'client' => $clientName,
                'status' => $status,
                'period' => $period,
                'created' => $created,
            ];
        }

        return $items;
    }

    /**
     * @param array<int, string> $clientNames
     * @return array<int, array{client: string, type: string, severity: string, detected: string}>
     */
    private static function recentAnomalies(array $clientNames, int $limit): array
    {
        $repo = new AnomaliesRepo();
        $records = $repo->recent($limit);
        $items = [];

        foreach ($records as $anomaly) {
            $clientId = $anomaly->clientId;
            $clientName = $clientNames[$clientId] ?? sprintf(__('Client #%d', 'fp-dms'), $clientId);
            $type = self::humanizeType($anomaly->type);
            $detected = self::formatDateTime($anomaly->detectedAt);

            $items[] = [
                'client' => $clientName,
                'type' => $type,
                'severity' => $anomaly->severity,
                'detected' => $detected,
            ];
        }

        return $items;
    }

    private static function formatDateTime(?string $value): string
    {
        if ($value === null || $value === '') {
            return __('Not available', 'fp-dms');
        }

        try {
            $date = new DateTimeImmutable($value, Wp::timezone());
            $timestamp = $date->getTimestamp();
        } catch (Exception) {
            $timestamp = strtotime($value);
            if (! is_int($timestamp)) {
                return $value;
            }
        }

        return Wp::date('M j, Y g:i a', $timestamp);
    }

    private static function formatDateRange(?string $start, ?string $end): string
    {
        if ($start === null || $start === '' || $end === null || $end === '') {
            return '';
        }

        $startTs = strtotime($start . ' 00:00:00');
        $endTs = strtotime($end . ' 00:00:00');

        if (! is_int($startTs) || ! is_int($endTs)) {
            return '';
        }

        $startLabel = Wp::date('M j, Y', $startTs);
        $endLabel = Wp::date('M j, Y', $endTs);

        if ($startLabel === $endLabel) {
            return $startLabel;
        }

        return $startLabel . ' – ' . $endLabel;
    }

    private static function formatFrequency(string $frequency): string
    {
        $normalized = strtolower($frequency);

        return match ($normalized) {
            'hourly' => __('Hourly', 'fp-dms'),
            'daily' => __('Daily', 'fp-dms'),
            'weekly' => __('Weekly', 'fp-dms'),
            'monthly' => __('Monthly', 'fp-dms'),
            'quarterly' => __('Quarterly', 'fp-dms'),
            'yearly' => __('Yearly', 'fp-dms'),
            default => ucwords(str_replace('_', ' ', $frequency)),
        };
    }

    private static function humanizeType(string $type): string
    {
        if ($type === '') {
            return __('Unknown anomaly', 'fp-dms');
        }

        $normalized = str_replace('_', ' ', $type);

        return ucwords($normalized);
    }

    private static function reportStatusLabel(string $status): string
    {
        return match (strtolower($status)) {
            'completed' => __('Completed', 'fp-dms'),
            'running' => __('Running', 'fp-dms'),
            'failed' => __('Failed', 'fp-dms'),
            'cancelled' => __('Cancelled', 'fp-dms'),
            default => __('Queued', 'fp-dms'),
        };
    }

    private static function statusBadgeClass(string $status): string
    {
        return match (strtolower($status)) {
            'completed' => 'fpdms-badge-success',
            'running' => 'fpdms-badge-warning',
            'failed', 'cancelled' => 'fpdms-badge-danger',
            default => 'fpdms-badge-neutral',
        };
    }

    private static function anomalySeverityLabel(string $severity): string
    {
        return match (strtolower($severity)) {
            'critical', 'error' => __('Critical', 'fp-dms'),
            'warning' => __('Warning', 'fp-dms'),
            'notice' => __('Notice', 'fp-dms'),
            default => __('Info', 'fp-dms'),
        };
    }

    private static function severityBadgeClass(string $severity): string
    {
        return match (strtolower($severity)) {
            'critical', 'error' => 'fpdms-badge-danger',
            'warning' => 'fpdms-badge-warning',
            'notice' => 'fpdms-badge-info',
            default => 'fpdms-badge-neutral',
        };
    }
}

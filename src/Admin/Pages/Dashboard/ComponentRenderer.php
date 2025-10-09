<?php

declare(strict_types=1);

namespace FP\DMS\Admin\Pages\Dashboard;

use FP\DMS\Domain\Entities\Schedule;
use FP\DMS\Support\Wp;

use function __;
use function add_query_arg;
use function admin_url;
use function esc_attr;
use function esc_html;
use function esc_html__;
use function esc_url;
use function sprintf;

/**
 * Renders dashboard UI components
 */
class ComponentRenderer
{
    /**
     * Render summary statistics cards
     *
     * @param array<string, int> $stats
     */
    public static function renderSummary(array $stats): void
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

        echo '<section class="fpdms-dashboard-section fpdms-section">';
        echo '<h2>' . esc_html__('Suite summary', 'fp-dms') . '</h2>';
        echo '<div class="fpdms-dashboard-grid">';

        foreach ($cards as $card) {
            self::renderStatCard($card);
        }

        echo '</div>';
        echo '</section>';
    }

    /**
     * Render a single stat card
     *
     * @param array{label: string, value: int, description: string, link?: string, link_label?: string} $card
     */
    private static function renderStatCard(array $card): void
    {
        $value = Wp::numberFormatI18n((float) ($card['value'] ?? 0));

        echo '<article class="fpdms-dashboard-card">';
        echo '<h3>' . esc_html((string) ($card['label'] ?? '')) . '</h3>';
        echo '<span class="fpdms-dashboard-card-value">' . esc_html($value) . '</span>';
        echo '<p>' . esc_html((string) ($card['description'] ?? '')) . '</p>';

        if (!empty($card['link'])) {
            echo '<a class="fpdms-dashboard-card-link" href="' . esc_url((string) $card['link']) . '">';
            echo esc_html((string) ($card['link_label'] ?? ''));
            echo '</a>';
        }

        echo '</article>';
    }

    /**
     * Render schedule card
     *
     * @param array<int, string> $clientNames
     */
    public static function renderScheduleCard(?Schedule $schedule, array $clientNames): void
    {
        echo '<section class="fpdms-dashboard-section fpdms-section">';
        echo '<h2>' . esc_html__('Automation status', 'fp-dms') . '</h2>';
        echo '<div class="fpdms-dashboard-schedule-card">';

        if ($schedule === null || $schedule->nextRunAt === null) {
            echo '<p>' . esc_html__('No automation is scheduled yet. Activate a schedule to send reports automatically.', 'fp-dms') . '</p>';
        } else {
            $clientName = $clientNames[$schedule->clientId] ?? sprintf(__('Client #%d', 'fp-dms'), $schedule->clientId);
            $nextRun = DateFormatter::dateTime($schedule->nextRunAt);
            $lastRun = $schedule->lastRunAt ? DateFormatter::dateTime($schedule->lastRunAt) : __('Never', 'fp-dms');
            $frequency = DateFormatter::frequency($schedule->frequency);

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
     * Render activity section (reports and anomalies)
     *
     * @param array<int, array{client: string, status: string, period: string, created: string}> $reports
     * @param array<int, array{client: string, type: string, severity: string, detected: string}> $anomalies
     */
    public static function renderActivity(array $reports, array $anomalies): void
    {
        echo '<section class="fpdms-dashboard-section fpdms-section">';
        echo '<h2>' . esc_html__('Recent activity', 'fp-dms') . '</h2>';
        echo '<div class="fpdms-dashboard-columns">';

        self::renderReportsColumn($reports);
        self::renderAnomaliesColumn($anomalies);

        echo '</div>';
        echo '</section>';
    }

    /**
     * Render reports column
     *
     * @param array<int, array{client: string, status: string, period: string, created: string}> $reports
     */
    private static function renderReportsColumn(array $reports): void
    {
        echo '<div class="fpdms-dashboard-column">';
        echo '<h3>' . esc_html__('Latest reports', 'fp-dms') . '</h3>';

        if ($reports === []) {
            echo '<p class="fpdms-dashboard-empty">' . esc_html__('No reports have been generated yet.', 'fp-dms') . '</p>';
        } else {
            echo '<ul class="fpdms-dashboard-list">';
            foreach ($reports as $report) {
                echo '<li>';
                echo '<div class="fpdms-dashboard-list-header">';
                echo '<strong>' . esc_html($report['client']) . '</strong>';
                echo BadgeRenderer::reportStatus($report['status']);
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
    }

    /**
     * Render anomalies column
     *
     * @param array<int, array{client: string, type: string, severity: string, detected: string}> $anomalies
     */
    private static function renderAnomaliesColumn(array $anomalies): void
    {
        echo '<div class="fpdms-dashboard-column">';
        echo '<h3>' . esc_html__('Latest anomalies', 'fp-dms') . '</h3>';

        if ($anomalies === []) {
            echo '<p class="fpdms-dashboard-empty">' . esc_html__('No anomalies detected in the last checks.', 'fp-dms') . '</p>';
        } else {
            echo '<ul class="fpdms-dashboard-list">';
            foreach ($anomalies as $anomaly) {
                echo '<li>';
                echo '<div class="fpdms-dashboard-list-header">';
                echo '<strong>' . esc_html($anomaly['client']) . '</strong>';
                echo BadgeRenderer::anomalySeverity($anomaly['severity']);
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
    }

    /**
     * Render quick links section
     */
    public static function renderQuickLinks(): void
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

        echo '<section class="fpdms-dashboard-section fpdms-section">';
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
}

<?php

declare(strict_types=1);

namespace FP\DMS\Admin\Pages;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use FP\DMS\Admin\Support\NoticeStore;
use FP\DMS\Domain\Repos\ClientsRepo;
use FP\DMS\Domain\Repos\SchedulesRepo;
use FP\DMS\Domain\Repos\TemplatesRepo;
use FP\DMS\Infra\Queue;
use FP\DMS\Support\I18n;
use function wp_timezone;
use function wp_unslash;

class SchedulesPage
{
    public static function render(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $schedulesRepo = new SchedulesRepo();
        $clientsRepo = new ClientsRepo();
        $templatesRepo = new TemplatesRepo();

        self::handleActions($schedulesRepo, $clientsRepo);

        $clients = $clientsRepo->all();
        $templates = $templatesRepo->all();
        $page = max(1, (int) ($_GET['paged'] ?? 1));
        $perPage = 50;
        $schedules = $schedulesRepo->all();
        $total = count($schedules);
        $display = array_slice($schedules, ($page - 1) * $perPage, $perPage);

        $clientsMap = [];
        foreach ($clients as $client) {
            $clientsMap[$client->id ?? 0] = $client;
        }

        $templatesMap = [];
        foreach ($templates as $template) {
            $templatesMap[$template->id ?? 0] = $template;
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Schedules', 'fp-dms') . '</h1>';
        NoticeStore::flash('fpdms_schedules');
        settings_errors('fpdms_schedules');

        self::renderForm($clients, $templates);
        self::renderList($display, $clientsMap, $templatesMap, $page, $perPage, $total);
        echo '</div>';
    }

    private static function handleActions(SchedulesRepo $repo, ClientsRepo $clientsRepo): void
    {
        $post = wp_unslash($_POST);

        if (! empty($post['fpdms_schedule_nonce']) && wp_verify_nonce(sanitize_text_field((string) ($post['fpdms_schedule_nonce'] ?? '')), 'fpdms_save_schedule')) {
            $clientId = (int) ($post['client_id'] ?? 0);
            $frequency = self::normalizeFrequency(sanitize_text_field((string) ($post['frequency'] ?? 'monthly')));
            $templateId = isset($post['template_id']) ? (int) $post['template_id'] : null;
            $active = ! empty($post['active']) ? 1 : 0;

            $client = $clientsRepo->find($clientId);
            $nextRunAt = self::calculateInitialNextRunAt($frequency, $client?->timezone);

            $repo->create([
                'client_id' => $clientId,
                'frequency' => $frequency,
                'template_id' => $templateId,
                'active' => $active,
                'next_run_at' => $nextRunAt,
            ]);

            NoticeStore::enqueue('fpdms_schedules', 'fpdms_schedule_saved', __('Schedule saved.', 'fp-dms'), 'updated');
            wp_safe_redirect(add_query_arg(['page' => 'fp-dms-schedules'], admin_url('admin.php')));
            exit;
        }

        $query = wp_unslash($_GET);
        if (isset($query['action'], $query['schedule']) && $query['action'] === 'run') {
            $scheduleId = (int) $query['schedule'];
            $nonce = sanitize_text_field((string) ($query['_wpnonce'] ?? ''));
            if (wp_verify_nonce($nonce, 'fpdms_run_schedule_' . $scheduleId)) {
                self::runSchedule($scheduleId);
                NoticeStore::enqueue('fpdms_schedules', 'fpdms_schedule_run', __('Schedule queued.', 'fp-dms'), 'updated');
            }
            wp_safe_redirect(add_query_arg(['page' => 'fp-dms-schedules'], admin_url('admin.php')));
            exit;
        }
    }

    private static function calculateInitialNextRunAt(string $frequency, ?string $timezone): string
    {
        $siteTz = wp_timezone();
        $clientTz = $siteTz;

        if (is_string($timezone) && $timezone !== '') {
            try {
                $clientTz = new DateTimeZone($timezone);
            } catch (Exception) {
                $clientTz = $siteTz;
            }
        }

        $now = new DateTimeImmutable('now', $clientTz);

        switch ($frequency) {
            case 'daily':
                $next = $now->modify('tomorrow')->setTime(0, 0, 0);
                break;
            case 'weekly':
                $next = $now->modify('next monday')->setTime(0, 0, 0);
                break;
            case 'monthly':
            default:
                $next = $now->modify('first day of next month')->setTime(0, 0, 0);
                break;
        }

        return $next->setTimezone($siteTz)->format('Y-m-d H:i:s');
    }

    /**
     * @param array<int, \FP\DMS\Domain\Entities\Client> $clients
     * @param array<int, \FP\DMS\Domain\Entities\Template> $templates
     */
    private static function renderForm(array $clients, array $templates): void
    {
        echo '<div class="card" style="max-width:800px;padding:20px;margin-top:20px;">';
        echo '<h2>' . esc_html__('Create schedule', 'fp-dms') . '</h2>';
        echo '<form method="post">';
        wp_nonce_field('fpdms_save_schedule', 'fpdms_schedule_nonce');
        echo '<table class="form-table"><tbody>';

        echo '<tr><th scope="row"><label for="fpdms-schedule-client">' . esc_html__('Client', 'fp-dms') . '</label></th><td>';
        echo '<select name="client_id" id="fpdms-schedule-client" required><option value="">' . esc_html__('Select client', 'fp-dms') . '</option>';
        foreach ($clients as $client) {
            echo '<option value="' . esc_attr((string) $client->id) . '">' . esc_html($client->name) . '</option>';
        }
        echo '</select></td></tr>';

        echo '<tr><th scope="row"><label for="fpdms-schedule-frequency">' . esc_html__('Frequency', 'fp-dms') . '</label></th><td>';
        echo '<select name="frequency" id="fpdms-schedule-frequency">';
        $frequencies = ['daily' => __('Daily', 'fp-dms'), 'weekly' => __('Weekly', 'fp-dms'), 'monthly' => __('Monthly', 'fp-dms')];
        foreach ($frequencies as $value => $label) {
            echo '<option value="' . esc_attr($value) . '"' . selected($value, 'monthly', false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></td></tr>';

        echo '<tr><th scope="row"><label for="fpdms-schedule-template">' . esc_html__('Template', 'fp-dms') . '</label></th><td>';
        echo '<select name="template_id" id="fpdms-schedule-template"><option value="">' . esc_html__('Default', 'fp-dms') . '</option>';
        foreach ($templates as $template) {
            echo '<option value="' . esc_attr((string) $template->id) . '">' . esc_html($template->name) . '</option>';
        }
        echo '</select></td></tr>';

        echo '<tr><th scope="row">' . esc_html__('Active', 'fp-dms') . '</th><td><label><input type="checkbox" name="active" value="1" checked> ' . esc_html__('Enabled', 'fp-dms') . '</label></td></tr>';

        echo '</tbody></table>';
        submit_button(__('Save schedule', 'fp-dms'));
        echo '</form>';
        echo '</div>';
    }

    /**
     * @param array<int, \FP\DMS\Domain\Entities\Schedule> $schedules
     * @param array<int, \FP\DMS\Domain\Entities\Client> $clients
     * @param array<int, \FP\DMS\Domain\Entities\Template> $templates
     */
    private static function renderList(array $schedules, array $clients, array $templates, int $page, int $perPage, int $total): void
    {
        echo '<h2 style="margin-top:40px;">' . esc_html__('Upcoming schedules', 'fp-dms') . '</h2>';
        self::renderPagination($page, $perPage, $total);
        echo '<table class="widefat striped">';
        echo '<thead><tr><th>' . esc_html__('Client', 'fp-dms') . '</th><th>' . esc_html__('Frequency', 'fp-dms') . '</th><th>' . esc_html__('Next run', 'fp-dms') . '</th><th>' . esc_html__('Template', 'fp-dms') . '</th><th>' . esc_html__('Actions', 'fp-dms') . '</th></tr></thead><tbody>';

        if (empty($schedules)) {
            echo '<tr><td colspan="5">' . esc_html__('No schedules configured.', 'fp-dms') . '</td></tr>';
        }

        foreach ($schedules as $schedule) {
            $client = $clients[$schedule->clientId] ?? null;
            $template = $schedule->templateId ? ($templates[$schedule->templateId] ?? null) : null;
            $runUrl = wp_nonce_url(add_query_arg([
                'page' => 'fp-dms-schedules',
                'action' => 'run',
                'schedule' => $schedule->id,
            ], admin_url('admin.php')), 'fpdms_run_schedule_' . $schedule->id);

            echo '<tr>';
            echo '<td>' . esc_html($client?->name ?? '-') . '</td>';
            echo '<td>' . esc_html(ucfirst($schedule->frequency)) . '</td>';
            $nextRun = $schedule->nextRunAt ?: I18n::__('Not scheduled');
            echo '<td>' . esc_html($nextRun) . '</td>';
            echo '<td>' . esc_html($template?->name ?? __('Default', 'fp-dms')) . '</td>';
            echo '<td><a class="button" href="' . esc_url($runUrl) . '">' . esc_html__('Run now', 'fp-dms') . '</a></td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        self::renderPagination($page, $perPage, $total);
    }

    private static function renderPagination(int $page, int $perPage, int $total): void
    {
        if ($total <= $perPage) {
            return;
        }

        $totalPages = (int) ceil($total / $perPage);
        $baseUrl = add_query_arg(['page' => 'fp-dms-schedules', 'paged' => '%#%'], admin_url('admin.php'));
        $links = paginate_links([
            'base' => $baseUrl,
            'format' => '',
            'current' => $page,
            'total' => $totalPages,
            'prev_text' => esc_html__('« Previous', 'fp-dms'),
            'next_text' => esc_html__('Next »', 'fp-dms'),
        ]);

        if ($links) {
            echo '<div class="tablenav"><div class="tablenav-pages">' . wp_kses_post($links) . '</div></div>';
        }
    }

    private static function normalizeFrequency(string $frequency): string
    {
        $normalized = strtolower($frequency);
        $allowed = ['daily', 'weekly', 'monthly'];

        return in_array($normalized, $allowed, true) ? $normalized : 'monthly';
    }

    private static function runSchedule(int $scheduleId): void
    {
        $repo = new SchedulesRepo();
        $schedule = $repo->find($scheduleId);
        if (! $schedule) {
            return;
        }

        $period = self::calculatePeriod($schedule->frequency);
        Queue::enqueue(
            $schedule->clientId,
            $period['start'],
            $period['end'],
            $schedule->templateId,
            $schedule->id,
            ['origin' => 'schedule_manual']
        );
    }

    /**
     * @return array{start:string,end:string}
     */
    private static function calculatePeriod(string $frequency): array
    {
        $now = current_time('timestamp');
        switch ($frequency) {
            case 'daily':
                $start = strtotime('-1 day', $now);
                return [
                    'start' => wp_date('Y-m-d', $start),
                    'end' => wp_date('Y-m-d', $start),
                ];
            case 'weekly':
                $start = strtotime('-7 days', $now);
                return [
                    'start' => wp_date('Y-m-d', $start),
                    'end' => wp_date('Y-m-d', $now),
                ];
            default:
                $firstDay = strtotime('first day of last month', $now);
                $lastDay = strtotime('last day of last month', $now);
                return [
                    'start' => wp_date('Y-m-d', $firstDay),
                    'end' => wp_date('Y-m-d', $lastDay),
                ];
        }
    }
}

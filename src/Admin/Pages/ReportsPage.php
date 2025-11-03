<?php

declare(strict_types=1);

namespace FP\DMS\Admin\Pages;

use FP\DMS\Admin\Pages\Shared\Breadcrumbs;
use FP\DMS\Admin\Pages\Shared\EmptyState;
use FP\DMS\Admin\Support\NoticeStore;
use FP\DMS\Domain\Repos\ClientsRepo;
use FP\DMS\Domain\Repos\ReportsRepo;
use FP\DMS\Support\Wp;

class ReportsPage
{
    public static function render(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $reportsRepo = new ReportsRepo();
        $clientsRepo = new ClientsRepo();
        
        self::handleActions($reportsRepo);

        // Filtri
        $filterStatus = Wp::sanitizeTextField($_GET['filter_status'] ?? '');
        $filterReviewStatus = Wp::sanitizeTextField($_GET['filter_review_status'] ?? '');
        $filterClientId = isset($_GET['filter_client']) ? (int) $_GET['filter_client'] : 0;

        // Build search criteria
        $criteria = [];
        if ($filterClientId > 0) {
            $criteria['client_id'] = $filterClientId;
        }
        if ($filterStatus !== '' && $filterStatus !== 'all') {
            $criteria['status'] = $filterStatus;
        }
        if ($filterReviewStatus !== '' && $filterReviewStatus !== 'all') {
            $criteria['review_status'] = $filterReviewStatus;
        }

        $reports = $reportsRepo->search($criteria);
        $clients = $clientsRepo->all();

        echo '<div class="wrap fpdms-admin-page fpdms-reports-page">';
        
        // Breadcrumbs
        Breadcrumbs::render(Breadcrumbs::getStandardItems('reports'));
        
        // Header
        echo '<div class="fpdms-page-header">';
        echo '<h1><span class="dashicons dashicons-media-document" style="margin-right:12px;"></span>' . esc_html__('Report Review', 'fp-dms') . '</h1>';
        echo '<p>' . esc_html__('Gestisci, rivedi e approva i report generati per i tuoi clienti.', 'fp-dms') . '</p>';
        echo '</div>';
        
        NoticeStore::flash('fpdms_reports');
        settings_errors('fpdms_reports');

        // Stats cards
        self::renderStatsCards($reports);

        // Filters
        self::renderFilters($clients, $filterStatus, $filterReviewStatus, $filterClientId);

        // Reports table
        self::renderReportsTable($reports, $clients);

        // Editor modal
        self::renderEditorModal();

        echo '</div>';
    }

    public static function registerAssetsHook(string $hook): void
    {
        add_action('load-' . $hook, static function (): void {
            add_action('admin_enqueue_scripts', [self::class, 'enqueueAssets']);
        });
    }

    public static function enqueueAssets(): void
    {
        $version = defined('FP_DMS_VERSION') ? FP_DMS_VERSION : '0.1.1';
        $pluginUrl = plugin_dir_url(FP_DMS_PLUGIN_FILE);

        wp_enqueue_style(
            'fpdms-reports',
            $pluginUrl . 'assets/css/reports-review.css',
            [],
            $version
        );

        wp_enqueue_script(
            'fpdms-reports',
            $pluginUrl . 'assets/js/reports-review.js',
            ['jquery'],
            $version,
            true
        );

        wp_localize_script('fpdms-reports', 'fpdmsReports', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('fpdms_report_review'),
            'i18n' => [
                'confirmApprove' => __('Sei sicuro di voler approvare questo report?', 'fp-dms'),
                'confirmReject' => __('Sei sicuro di voler rigettare questo report?', 'fp-dms'),
                'confirmDelete' => __('Sei sicuro di voler eliminare questo report? Questa azione non può essere annullata.', 'fp-dms'),
                'reviewNotesPlaceholder' => __('Aggiungi note di revisione...', 'fp-dms'),
                'success' => __('Operazione completata con successo.', 'fp-dms'),
                'error' => __('Si è verificato un errore. Riprova.', 'fp-dms'),
            ],
        ]);
    }

    private static function handleActions(ReportsRepo $repo): void
    {
        $post = Wp::unslash($_POST);

        if (empty($post['fpdms_report_action_nonce'])) {
            return;
        }

        if (!wp_verify_nonce(Wp::sanitizeTextField($post['fpdms_report_action_nonce']), 'fpdms_report_action')) {
            return;
        }

        $action = Wp::sanitizeTextField($post['action'] ?? '');
        $reportId = isset($post['report_id']) ? (int) $post['report_id'] : 0;

        if ($reportId <= 0 || !in_array($action, ['approve', 'reject', 'pending', 'delete'], true)) {
            return;
        }

        if ($action === 'delete') {
            if ($repo->delete($reportId)) {
                NoticeStore::enqueue('fpdms_reports', 'report_deleted', __('Report eliminato.', 'fp-dms'), 'updated');
            } else {
                NoticeStore::enqueue('fpdms_reports', 'report_error', __('Errore nell\'eliminazione del report.', 'fp-dms'), 'error');
            }
            return;
        }

        $reviewStatus = $action === 'approve' ? 'approved' : ($action === 'reject' ? 'rejected' : 'pending');
        $reviewNotes = Wp::ksesPost((string) ($post['review_notes'] ?? ''));
        
        $updateData = [
            'review_status' => $reviewStatus,
            'review_notes' => $reviewNotes !== '' ? $reviewNotes : null,
            'reviewed_at' => Wp::currentTime('mysql'),
            'reviewed_by' => get_current_user_id(),
        ];

        if ($repo->update($reportId, $updateData)) {
            $message = $action === 'approve' 
                ? __('Report approvato.', 'fp-dms')
                : ($action === 'reject' ? __('Report rigettato.', 'fp-dms') : __('Report ripristinato a "Da rivedere".', 'fp-dms'));
            NoticeStore::enqueue('fpdms_reports', 'report_reviewed', $message, 'updated');
        } else {
            NoticeStore::enqueue('fpdms_reports', 'report_error', __('Errore nell\'aggiornamento del report.', 'fp-dms'), 'error');
        }
    }

    private static function renderStatsCards(array $reports): void
    {
        $stats = [
            'total' => count($reports),
            'pending' => 0,
            'approved' => 0,
            'rejected' => 0,
            'in_review' => 0,
        ];

        foreach ($reports as $report) {
            $status = $report->reviewStatus ?? 'pending';
            if (isset($stats[$status])) {
                $stats[$status]++;
            }
        }

        echo '<div class="fpdms-stats-cards">';
        
        echo '<div class="fpdms-stat-card">';
        echo '<div class="fpdms-stat-icon"><span class="dashicons dashicons-media-document"></span></div>';
        echo '<div class="fpdms-stat-content">';
        echo '<div class="fpdms-stat-value">' . esc_html($stats['total']) . '</div>';
        echo '<div class="fpdms-stat-label">' . esc_html__('Totale Report', 'fp-dms') . '</div>';
        echo '</div>';
        echo '</div>';

        echo '<div class="fpdms-stat-card fpdms-stat-pending">';
        echo '<div class="fpdms-stat-icon"><span class="dashicons dashicons-clock"></span></div>';
        echo '<div class="fpdms-stat-content">';
        echo '<div class="fpdms-stat-value">' . esc_html($stats['pending']) . '</div>';
        echo '<div class="fpdms-stat-label">' . esc_html__('Da Rivedere', 'fp-dms') . '</div>';
        echo '</div>';
        echo '</div>';

        echo '<div class="fpdms-stat-card fpdms-stat-approved">';
        echo '<div class="fpdms-stat-icon"><span class="dashicons dashicons-yes-alt"></span></div>';
        echo '<div class="fpdms-stat-content">';
        echo '<div class="fpdms-stat-value">' . esc_html($stats['approved']) . '</div>';
        echo '<div class="fpdms-stat-label">' . esc_html__('Approvati', 'fp-dms') . '</div>';
        echo '</div>';
        echo '</div>';

        echo '<div class="fpdms-stat-card fpdms-stat-rejected">';
        echo '<div class="fpdms-stat-icon"><span class="dashicons dashicons-dismiss"></span></div>';
        echo '<div class="fpdms-stat-content">';
        echo '<div class="fpdms-stat-value">' . esc_html($stats['rejected']) . '</div>';
        echo '<div class="fpdms-stat-label">' . esc_html__('Rigettati', 'fp-dms') . '</div>';
        echo '</div>';
        echo '</div>';

        echo '</div>'; // .fpdms-stats-cards
    }

    private static function renderFilters(array $clients, string $filterStatus, string $filterReviewStatus, int $filterClientId): void
    {
        echo '<div class="fpdms-filters">';
        echo '<form method="get" action="' . esc_url(admin_url('admin.php')) . '">';
        echo '<input type="hidden" name="page" value="fp-dms-reports">';

        // Client filter
        echo '<select name="filter_client" id="filter-client">';
        echo '<option value="">' . esc_html__('Tutti i clienti', 'fp-dms') . '</option>';
        foreach ($clients as $client) {
            $selected = $filterClientId === ($client->id ?? 0) ? 'selected' : '';
            echo '<option value="' . esc_attr((string) ($client->id ?? 0)) . '" ' . $selected . '>' . esc_html($client->name) . '</option>';
        }
        echo '</select>';

        // Status filter
        echo '<select name="filter_status" id="filter-status">';
        echo '<option value="all">' . esc_html__('Tutti gli stati', 'fp-dms') . '</option>';
        $statuses = [
            'success' => __('Generato', 'fp-dms'),
            'queued' => __('In coda', 'fp-dms'),
            'running' => __('In esecuzione', 'fp-dms'),
            'failed' => __('Fallito', 'fp-dms'),
        ];
        foreach ($statuses as $value => $label) {
            $selected = $filterStatus === $value ? 'selected' : '';
            echo '<option value="' . esc_attr($value) . '" ' . $selected . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';

        // Review status filter
        echo '<select name="filter_review_status" id="filter-review-status">';
        echo '<option value="all">' . esc_html__('Tutti gli stati review', 'fp-dms') . '</option>';
        $reviewStatuses = [
            'pending' => __('Da rivedere', 'fp-dms'),
            'in_review' => __('In revisione', 'fp-dms'),
            'approved' => __('Approvato', 'fp-dms'),
            'rejected' => __('Rigettato', 'fp-dms'),
        ];
        foreach ($reviewStatuses as $value => $label) {
            $selected = $filterReviewStatus === $value ? 'selected' : '';
            echo '<option value="' . esc_attr($value) . '" ' . $selected . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';

        echo '<button type="submit" class="button">' . esc_html__('Filtra', 'fp-dms') . '</button>';
        echo '<a href="' . esc_url(admin_url('admin.php?page=fp-dms-reports')) . '" class="button">' . esc_html__('Reset', 'fp-dms') . '</a>';
        echo '</form>';
        echo '</div>';
    }

    private static function renderReportsTable(array $reports, array $clients): void
    {
        echo '<div class="fpdms-reports-table-container">';
        
        if (empty($reports)) {
            echo '<div class="fpdms-no-reports">';
            EmptyState::render([
                'icon' => 'dashicons-media-document',
                'title' => __('Nessun Report Generato', 'fp-dms'),
                'description' => __('Non hai ancora generato report. I report vengono creati automaticamente dagli schedule programmati oppure puoi generarne uno manualmente dalla sezione Overview.', 'fp-dms'),
                'primaryAction' => [
                    'label' => __('📊 Vai a Overview', 'fp-dms'),
                    'url' => add_query_arg(['page' => 'fp-dms-overview'], admin_url('admin.php'))
                ],
                'secondaryAction' => [
                    'label' => __('📅 Crea Schedule', 'fp-dms'),
                    'url' => add_query_arg(['page' => 'fp-dms-schedules'], admin_url('admin.php'))
                ],
                'helpText' => __('Suggerimento: Configura uno schedule per ricevere report automatici via email', 'fp-dms')
            ]);
            echo '</div>';
            echo '</div>';
            return;
        }

        echo '<table class="fpdms-reports-table widefat striped">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>' . esc_html__('Cliente', 'fp-dms') . '</th>';
        echo '<th>' . esc_html__('Periodo', 'fp-dms') . '</th>';
        echo '<th>' . esc_html__('Generato', 'fp-dms') . '</th>';
        echo '<th>' . esc_html__('Stato', 'fp-dms') . '</th>';
        echo '<th>' . esc_html__('Review', 'fp-dms') . '</th>';
        echo '<th>' . esc_html__('Azioni', 'fp-dms') . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        $clientsMap = [];
        foreach ($clients as $client) {
            $clientsMap[$client->id ?? 0] = $client->name;
        }

        foreach ($reports as $report) {
            $clientName = $clientsMap[$report->clientId] ?? __('Sconosciuto', 'fp-dms');
            $reviewStatus = $report->reviewStatus ?? 'pending';
            $reviewStatusLabel = self::getReviewStatusLabel($reviewStatus);
            $reviewStatusClass = self::getReviewStatusClass($reviewStatus);
            
            echo '<tr class="fpdms-report-row" data-report-id="' . esc_attr((string) ($report->id ?? 0)) . '">';
            
            // Cliente
            echo '<td><strong>' . esc_html($clientName) . '</strong></td>';
            
            // Periodo
            echo '<td>' . esc_html($report->periodStart) . ' / ' . esc_html($report->periodEnd) . '</td>';
            
            // Generato
            echo '<td>' . esc_html(Wp::dateI18n('d/m/Y H:i', strtotime($report->createdAt))) . '</td>';
            
            // Stato generazione
            echo '<td>' . self::renderGenerationStatus($report->status) . '</td>';
            
            // Review status
            echo '<td><span class="fpdms-review-badge fpdms-review-' . esc_attr($reviewStatusClass) . '">' . esc_html($reviewStatusLabel) . '</span></td>';
            
            // Azioni
            echo '<td class="fpdms-report-actions">';
            
            if ($report->storagePath && $report->status === 'success') {
                $pdfUrl = Wp::uploadUrl() . '/' . ltrim($report->storagePath, '/');
                echo '<a href="' . esc_url($pdfUrl) . '" target="_blank" class="button button-small" title="' . esc_attr__('Visualizza PDF', 'fp-dms') . '">';
                echo '<span class="dashicons dashicons-visibility"></span>';
                echo '</a>';
            }
            
            // Edit content button (only if HTML content exists)
            $hasHtmlContent = !empty($report->meta['html_content'] ?? '');
            if ($hasHtmlContent && $report->status === 'success') {
                echo '<button type="button" class="button button-small fpdms-edit-content-btn" data-report-id="' . esc_attr((string) ($report->id ?? 0)) . '" title="' . esc_attr__('Modifica Contenuto', 'fp-dms') . '">';
                echo '<span class="dashicons dashicons-editor-code"></span>';
                echo '</button>';
            }
            
            echo '<button type="button" class="button button-small fpdms-review-btn" data-report-id="' . esc_attr((string) ($report->id ?? 0)) . '" title="' . esc_attr__('Review', 'fp-dms') . '">';
            echo '<span class="dashicons dashicons-edit"></span>';
            echo '</button>';
            
            echo '</td>';
            echo '</tr>';
            
            // Review row (hidden by default)
            echo '<tr class="fpdms-review-row" id="review-row-' . esc_attr((string) ($report->id ?? 0)) . '" style="display: none;">';
            echo '<td colspan="6">';
            self::renderReviewForm($report);
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
        echo '</div>';
    }

    private static function renderReviewForm($report): void
    {
        echo '<div class="fpdms-review-form">';
        echo '<form method="post" action="">';
        wp_nonce_field('fpdms_report_action', 'fpdms_report_action_nonce');
        echo '<input type="hidden" name="report_id" value="' . esc_attr((string) ($report->id ?? 0)) . '">';
        
        echo '<div class="fpdms-review-info">';
        if ($report->reviewedAt) {
            $reviewer = get_userdata($report->reviewedBy ?? 0);
            $reviewerName = $reviewer ? $reviewer->display_name : __('Sconosciuto', 'fp-dms');
            echo '<p><strong>' . esc_html__('Ultimo review:', 'fp-dms') . '</strong> ';
            echo esc_html(Wp::dateI18n('d/m/Y H:i', strtotime($report->reviewedAt)));
            echo ' ' . esc_html__('da', 'fp-dms') . ' ' . esc_html($reviewerName);
            echo '</p>';
        }
        echo '</div>';
        
        echo '<div class="fpdms-review-notes-wrapper">';
        echo '<label for="review-notes-' . esc_attr((string) ($report->id ?? 0)) . '">' . esc_html__('Note di revisione:', 'fp-dms') . '</label>';
        echo '<textarea name="review_notes" id="review-notes-' . esc_attr((string) ($report->id ?? 0)) . '" rows="3" class="widefat">';
        echo esc_textarea($report->reviewNotes ?? '');
        echo '</textarea>';
        echo '</div>';
        
        echo '<div class="fpdms-review-actions">';
        echo '<button type="submit" name="action" value="approve" class="button button-primary">';
        echo '<span class="dashicons dashicons-yes"></span> ' . esc_html__('Approva', 'fp-dms');
        echo '</button>';
        
        echo '<button type="submit" name="action" value="reject" class="button">';
        echo '<span class="dashicons dashicons-no"></span> ' . esc_html__('Rigetta', 'fp-dms');
        echo '</button>';
        
        echo '<button type="submit" name="action" value="pending" class="button">';
        echo '<span class="dashicons dashicons-backup"></span> ' . esc_html__('Ripristina', 'fp-dms');
        echo '</button>';
        
        echo '<button type="submit" name="action" value="delete" class="button button-link-delete" onclick="return confirm(\'' . esc_js__('Sei sicuro di voler eliminare questo report?', 'fp-dms') . '\')">';
        echo '<span class="dashicons dashicons-trash"></span> ' . esc_html__('Elimina', 'fp-dms');
        echo '</button>';
        
        echo '<button type="button" class="button fpdms-review-cancel">' . esc_html__('Annulla', 'fp-dms') . '</button>';
        echo '</div>';
        
        echo '</form>';
        echo '</div>';
    }

    private static function renderGenerationStatus(string $status): string
    {
        $labels = [
            'success' => __('Generato', 'fp-dms'),
            'queued' => __('In coda', 'fp-dms'),
            'running' => __('In esecuzione', 'fp-dms'),
            'failed' => __('Fallito', 'fp-dms'),
        ];

        $classes = [
            'success' => 'status-success',
            'queued' => 'status-queued',
            'running' => 'status-running',
            'failed' => 'status-failed',
        ];

        $label = $labels[$status] ?? $status;
        $class = $classes[$status] ?? '';

        return '<span class="fpdms-status-badge ' . esc_attr($class) . '">' . esc_html($label) . '</span>';
    }

    private static function getReviewStatusLabel(string $status): string
    {
        $labels = [
            'pending' => __('Da rivedere', 'fp-dms'),
            'in_review' => __('In revisione', 'fp-dms'),
            'approved' => __('Approvato', 'fp-dms'),
            'rejected' => __('Rigettato', 'fp-dms'),
        ];

        return $labels[$status] ?? $status;
    }

    private static function getReviewStatusClass(string $status): string
    {
        return str_replace('_', '-', $status);
    }

    /**
     * Render editor modal for HTML content editing
     */
    private static function renderEditorModal(): void
    {
        echo '<div id="fpdms-editor-modal" class="fpdms-modal" style="display: none;">';
        echo '<div class="fpdms-modal-overlay"></div>';
        echo '<div class="fpdms-modal-container">';
        
        echo '<div class="fpdms-modal-header">';
        echo '<h2>' . esc_html__('Modifica Contenuto Report', 'fp-dms') . '</h2>';
        echo '<button type="button" class="fpdms-modal-close" aria-label="' . esc_attr__('Chiudi', 'fp-dms') . '">';
        echo '<span class="dashicons dashicons-no-alt"></span>';
        echo '</button>';
        echo '</div>';
        
        echo '<div class="fpdms-modal-body">';
        
        // Tabs for different editing modes
        echo '<div class="fpdms-editor-tabs">';
        echo '<button type="button" class="fpdms-editor-tab active" data-tab="visual">';
        echo '<span class="dashicons dashicons-welcome-write-blog"></span> ' . esc_html__('Editor Visuale', 'fp-dms');
        echo '</button>';
        echo '<button type="button" class="fpdms-editor-tab" data-tab="html">';
        echo '<span class="dashicons dashicons-editor-code"></span> ' . esc_html__('HTML', 'fp-dms');
        echo '</button>';
        echo '<button type="button" class="fpdms-editor-tab" data-tab="preview">';
        echo '<span class="dashicons dashicons-visibility"></span> ' . esc_html__('Anteprima', 'fp-dms');
        echo '</button>';
        echo '</div>';
        
        echo '<div class="fpdms-editor-content">';
        
        // Visual editor (TinyMCE)
        echo '<div class="fpdms-editor-pane active" data-pane="visual">';
        echo '<div class="fpdms-editor-toolbar">';
        echo '<p class="description">' . esc_html__('Modifica il contenuto del report. Le modifiche saranno applicate al PDF.', 'fp-dms') . '</p>';
        echo '</div>';
        wp_editor('', 'fpdms_report_content', [
            'textarea_rows' => 20,
            'media_buttons' => false,
            'teeny' => false,
            'quicktags' => true,
            'tinymce' => [
                'toolbar1' => 'formatselect,bold,italic,underline,strikethrough,forecolor,backcolor,alignleft,aligncenter,alignright,bullist,numlist,link,unlink,undo,redo',
                'toolbar2' => 'fontsizeselect,removeformat,code,pastetext',
            ],
        ]);
        echo '</div>';
        
        // HTML editor (CodeMirror-like textarea)
        echo '<div class="fpdms-editor-pane" data-pane="html">';
        echo '<div class="fpdms-editor-toolbar">';
        echo '<p class="description">' . esc_html__('Modifica direttamente il codice HTML. Attenzione: errori di sintassi possono compromettere il report.', 'fp-dms') . '</p>';
        echo '</div>';
        echo '<textarea id="fpdms-html-editor" class="fpdms-code-editor" rows="25"></textarea>';
        echo '</div>';
        
        // Preview
        echo '<div class="fpdms-editor-pane" data-pane="preview">';
        echo '<div class="fpdms-editor-toolbar">';
        echo '<p class="description">' . esc_html__('Anteprima del report come apparirà nel PDF.', 'fp-dms') . '</p>';
        echo '<button type="button" class="button" id="fpdms-refresh-preview">';
        echo '<span class="dashicons dashicons-update"></span> ' . esc_html__('Aggiorna Anteprima', 'fp-dms');
        echo '</button>';
        echo '</div>';
        echo '<div id="fpdms-preview-container" class="fpdms-preview-container">';
        echo '<p class="fpdms-preview-placeholder">' . esc_html__('Clicca "Aggiorna Anteprima" per visualizzare il report.', 'fp-dms') . '</p>';
        echo '</div>';
        echo '</div>';
        
        echo '</div>'; // .fpdms-editor-content
        
        echo '</div>'; // .fpdms-modal-body
        
        echo '<div class="fpdms-modal-footer">';
        echo '<input type="hidden" id="fpdms-editing-report-id" value="">';
        echo '<button type="button" class="button" id="fpdms-editor-cancel">' . esc_html__('Annulla', 'fp-dms') . '</button>';
        echo '<button type="button" class="button button-primary" id="fpdms-editor-save">';
        echo '<span class="dashicons dashicons-saved"></span> ' . esc_html__('Salva e Rigenera PDF', 'fp-dms');
        echo '</button>';
        echo '</div>';
        
        echo '</div>'; // .fpdms-modal-container
        echo '</div>'; // #fpdms-editor-modal
    }
}


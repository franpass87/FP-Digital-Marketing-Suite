<?php

declare(strict_types=1);

namespace FP\DMS\Admin\Ajax;

use FP\DMS\Domain\Repos\ReportsRepo;
use FP\DMS\Support\Wp;

class ReportReviewHandler
{
    public static function register(): void
    {
        add_action('wp_ajax_fpdms_update_report_review', [self::class, 'handleUpdateReview']);
        add_action('wp_ajax_fpdms_delete_report', [self::class, 'handleDeleteReport']);
        add_action('wp_ajax_fpdms_bulk_review_action', [self::class, 'handleBulkAction']);
        add_action('wp_ajax_fpdms_load_report_html', [self::class, 'handleLoadReportHtml']);
        add_action('wp_ajax_fpdms_save_report_html', [self::class, 'handleSaveReportHtml']);
    }

    public static function handleUpdateReview(): void
    {
        check_ajax_referer('fpdms_report_review', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permessi insufficienti.', 'fp-dms')], 403);
        }

        $post = Wp::unslash($_POST);
        $reportId = isset($post['report_id']) ? (int) $post['report_id'] : 0;
        $action = Wp::sanitizeTextField($post['action'] ?? '');
        $notes = Wp::ksesPost((string) ($post['notes'] ?? ''));

        if ($reportId <= 0) {
            wp_send_json_error(['message' => __('ID report non valido.', 'fp-dms')]);
        }

        if (!in_array($action, ['approve', 'reject', 'pending', 'in_review'], true)) {
            wp_send_json_error(['message' => __('Azione non valida.', 'fp-dms')]);
        }

        $repo = new ReportsRepo();
        $report = $repo->find($reportId);

        if (!$report) {
            wp_send_json_error(['message' => __('Report non trovato.', 'fp-dms')]);
        }

        $reviewStatus = match ($action) {
            'approve' => 'approved',
            'reject' => 'rejected',
            'in_review' => 'in_review',
            default => 'pending',
        };

        $updateData = [
            'review_status' => $reviewStatus,
            'review_notes' => $notes !== '' ? $notes : null,
            'reviewed_at' => Wp::currentTime('mysql'),
            'reviewed_by' => get_current_user_id(),
        ];

        if ($repo->update($reportId, $updateData)) {
            $message = match ($action) {
                'approve' => __('Report approvato con successo.', 'fp-dms'),
                'reject' => __('Report rigettato.', 'fp-dms'),
                'in_review' => __('Report contrassegnato come "In revisione".', 'fp-dms'),
                default => __('Stato review ripristinato.', 'fp-dms'),
            };

            wp_send_json_success([
                'message' => $message,
                'review_status' => $reviewStatus,
                'reviewed_at' => Wp::currentTime('mysql'),
            ]);
        } else {
            wp_send_json_error(['message' => __('Errore durante l\'aggiornamento del report.', 'fp-dms')]);
        }
    }

    public static function handleDeleteReport(): void
    {
        check_ajax_referer('fpdms_report_review', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permessi insufficienti.', 'fp-dms')], 403);
        }

        $post = Wp::unslash($_POST);
        $reportId = isset($post['report_id']) ? (int) $post['report_id'] : 0;

        if ($reportId <= 0) {
            wp_send_json_error(['message' => __('ID report non valido.', 'fp-dms')]);
        }

        $repo = new ReportsRepo();
        $report = $repo->find($reportId);

        if (!$report) {
            wp_send_json_error(['message' => __('Report non trovato.', 'fp-dms')]);
        }

        // Delete PDF file if exists
        if ($report->storagePath) {
            $upload = Wp::uploadDir();
            $pdfPath = Wp::trailingSlashIt($upload['basedir']) . ltrim($report->storagePath, '/');
            
            if (file_exists($pdfPath)) {
                @unlink($pdfPath);
            }
        }

        if ($repo->delete($reportId)) {
            wp_send_json_success(['message' => __('Report eliminato con successo.', 'fp-dms')]);
        } else {
            wp_send_json_error(['message' => __('Errore durante l\'eliminazione del report.', 'fp-dms')]);
        }
    }

    public static function handleBulkAction(): void
    {
        check_ajax_referer('fpdms_report_review', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permessi insufficienti.', 'fp-dms')], 403);
        }

        $post = Wp::unslash($_POST);
        $reportIds = isset($post['report_ids']) && is_array($post['report_ids']) 
            ? array_map('intval', $post['report_ids']) 
            : [];
        $action = Wp::sanitizeTextField($post['action'] ?? '');

        if (empty($reportIds)) {
            wp_send_json_error(['message' => __('Nessun report selezionato.', 'fp-dms')]);
        }

        if (!in_array($action, ['approve', 'reject', 'pending', 'delete'], true)) {
            wp_send_json_error(['message' => __('Azione non valida.', 'fp-dms')]);
        }

        $repo = new ReportsRepo();
        $success = 0;
        $errors = 0;

        foreach ($reportIds as $reportId) {
            if ($action === 'delete') {
                $report = $repo->find($reportId);
                if ($report && $report->storagePath) {
                    $upload = Wp::uploadDir();
                    $pdfPath = Wp::trailingSlashIt($upload['basedir']) . ltrim($report->storagePath, '/');
                    if (file_exists($pdfPath)) {
                        @unlink($pdfPath);
                    }
                }
                
                if ($repo->delete($reportId)) {
                    $success++;
                } else {
                    $errors++;
                }
            } else {
                $reviewStatus = match ($action) {
                    'approve' => 'approved',
                    'reject' => 'rejected',
                    default => 'pending',
                };

                $updateData = [
                    'review_status' => $reviewStatus,
                    'reviewed_at' => Wp::currentTime('mysql'),
                    'reviewed_by' => get_current_user_id(),
                ];

                if ($repo->update($reportId, $updateData)) {
                    $success++;
                } else {
                    $errors++;
                }
            }
        }

        $message = sprintf(
            __('%d report processati con successo, %d errori.', 'fp-dms'),
            $success,
            $errors
        );

        wp_send_json_success(['message' => $message, 'success' => $success, 'errors' => $errors]);
    }

    public static function handleLoadReportHtml(): void
    {
        check_ajax_referer('fpdms_report_review', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permessi insufficienti.', 'fp-dms')], 403);
        }

        $post = Wp::unslash($_POST);
        $reportId = isset($post['report_id']) ? (int) $post['report_id'] : 0;

        if ($reportId <= 0) {
            wp_send_json_error(['message' => __('ID report non valido.', 'fp-dms')]);
        }

        $repo = new ReportsRepo();
        $report = $repo->find($reportId);

        if (!$report) {
            wp_send_json_error(['message' => __('Report non trovato.', 'fp-dms')]);
        }

        $htmlContent = $report->meta['html_content'] ?? '';

        if (empty($htmlContent)) {
            wp_send_json_error(['message' => __('Contenuto HTML non disponibile per questo report.', 'fp-dms')]);
        }

        wp_send_json_success([
            'html' => $htmlContent,
            'report_id' => $reportId,
        ]);
    }

    public static function handleSaveReportHtml(): void
    {
        check_ajax_referer('fpdms_report_review', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permessi insufficienti.', 'fp-dms')], 403);
        }

        $post = Wp::unslash($_POST);
        $reportId = isset($post['report_id']) ? (int) $post['report_id'] : 0;
        $htmlContent = isset($post['html_content']) ? $post['html_content'] : '';

        if ($reportId <= 0) {
            wp_send_json_error(['message' => __('ID report non valido.', 'fp-dms')]);
        }

        if (empty($htmlContent)) {
            wp_send_json_error(['message' => __('Il contenuto HTML non può essere vuoto.', 'fp-dms')]);
        }

        $repo = new ReportsRepo();
        $report = $repo->find($reportId);

        if (!$report) {
            wp_send_json_error(['message' => __('Report non trovato.', 'fp-dms')]);
        }

        // Save updated HTML in meta
        $meta = $report->meta;
        $meta['html_content'] = $htmlContent;
        $meta['last_edited_at'] = Wp::currentTime('mysql');
        $meta['last_edited_by'] = get_current_user_id();

        try {
            // Regenerate PDF with new content
            $upload = Wp::uploadDir();
            $pdfPath = Wp::trailingSlashIt($upload['basedir']) . ltrim($report->storagePath ?? '', '/');

            // Use PdfRenderer to regenerate
            $pdfRenderer = new \FP\DMS\Infra\PdfRenderer();
            $pdfRenderer->render($htmlContent, $pdfPath);

            // Update report meta
            $repo->update($reportId, [
                'meta' => $meta,
            ]);

            wp_send_json_success([
                'message' => __('Report aggiornato e PDF rigenerato con successo.', 'fp-dms'),
                'pdf_url' => Wp::uploadUrl() . '/' . ltrim($report->storagePath ?? '', '/'),
            ]);
        } catch (\Exception $e) {
            error_log('[ReportReviewHandler] Failed to regenerate PDF: ' . $e->getMessage());
            wp_send_json_error(['message' => __('Errore durante la rigenerazione del PDF: ', 'fp-dms') . $e->getMessage()]);
        }
    }
}


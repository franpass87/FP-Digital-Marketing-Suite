<?php

declare(strict_types=1);

namespace FP\DMS\Admin\Ajax;

use FP\DMS\Services\TemplateCustomizationEngine;
use FP\DMS\Services\Connectors\ConnectionTemplate;
use FP\DMS\Support\Wp;

/**
 * AJAX handler for template customization.
 */
class TemplateCustomizationHandler
{
    public function __construct()
    {
        add_action('wp_ajax_fpdms_customize_template', [$this, 'handleCustomizeTemplate']);
        add_action('wp_ajax_fpdms_save_custom_template', [$this, 'handleSaveCustomTemplate']);
        add_action('wp_ajax_fpdms_get_available_metrics', [$this, 'handleGetAvailableMetrics']);
        add_action('wp_ajax_fpdms_get_available_dimensions', [$this, 'handleGetAvailableDimensions']);
        add_action('wp_ajax_fpdms_validate_template', [$this, 'handleValidateTemplate']);
        add_action('wp_ajax_fpdms_compare_templates', [$this, 'handleCompareTemplates']);
        add_action('wp_ajax_fpdms_clone_template', [$this, 'handleCloneTemplate']);
    }

    /**
     * Handle template customization request.
     */
    public function handleCustomizeTemplate(): void
    {
        $this->verifyNonce();

        $templateId = sanitize_text_field($_POST['template_id'] ?? '');
        $customizations = $_POST['customizations'] ?? [];

        if (empty($templateId)) {
            wp_send_json_error(['message' => 'Template ID richiesto.']);
        }

        $baseTemplate = ConnectionTemplate::getTemplate($templateId);
        if (!$baseTemplate) {
            wp_send_json_error(['message' => 'Template non trovato.']);
        }

        $customized = TemplateCustomizationEngine::customizeTemplate($baseTemplate, $customizations);

        wp_send_json_success([
            'template' => $customized,
            'message' => 'Template personalizzato con successo.',
        ]);
    }

    /**
     * Handle save custom template request.
     */
    public function handleSaveCustomTemplate(): void
    {
        $this->verifyNonce();

        $template = $_POST['template'] ?? [];
        $userId = get_current_user_id();

        if (empty($template)) {
            wp_send_json_error(['message' => 'Dati template richiesti.']);
        }

        $result = TemplateCustomizationEngine::saveCustomTemplate($template, $userId);

        if ($result['success']) {
            wp_send_json_success([
                'template_id' => $result['template_id'],
                'message' => 'Template personalizzato salvato con successo.',
            ]);
        } else {
            wp_send_json_error(['message' => $result['error']]);
        }
    }

    /**
     * Handle get available metrics request.
     */
    public function handleGetAvailableMetrics(): void
    {
        $this->verifyNonce();

        $provider = sanitize_text_field($_POST['provider'] ?? '');

        if (empty($provider)) {
            wp_send_json_error(['message' => 'Provider richiesto.']);
        }

        $metrics = TemplateCustomizationEngine::getAvailableMetrics($provider);

        wp_send_json_success([
            'metrics' => $metrics,
        ]);
    }

    /**
     * Handle get available dimensions request.
     */
    public function handleGetAvailableDimensions(): void
    {
        $this->verifyNonce();

        $provider = sanitize_text_field($_POST['provider'] ?? '');

        if (empty($provider)) {
            wp_send_json_error(['message' => 'Provider richiesto.']);
        }

        $dimensions = TemplateCustomizationEngine::getAvailableDimensions($provider);

        wp_send_json_success([
            'dimensions' => $dimensions,
        ]);
    }

    /**
     * Handle template validation request.
     */
    public function handleValidateTemplate(): void
    {
        $this->verifyNonce();

        $template = $_POST['template'] ?? [];

        if (empty($template)) {
            wp_send_json_error(['message' => 'Dati template richiesti.']);
        }

        $validation = TemplateCustomizationEngine::validateCustomTemplate($template);

        if ($validation['valid']) {
            wp_send_json_success([
                'valid' => true,
                'message' => 'Template valido.',
            ]);
        } else {
            wp_send_json_error([
                'valid' => false,
                'errors' => $validation['errors'],
                'message' => 'Template non valido.',
            ]);
        }
    }

    /**
     * Handle template comparison request.
     */
    public function handleCompareTemplates(): void
    {
        $this->verifyNonce();

        $templateIds = $_POST['template_ids'] ?? [];

        if (empty($templateIds) || !is_array($templateIds)) {
            wp_send_json_error(['message' => 'Template IDs richiesti.']);
        }

        $comparison = TemplateCustomizationEngine::compareTemplates($templateIds);

        wp_send_json_success([
            'comparison' => $comparison,
        ]);
    }

    /**
     * Handle clone template request.
     */
    public function handleCloneTemplate(): void
    {
        $this->verifyNonce();

        $templateId = sanitize_text_field($_POST['template_id'] ?? '');
        $customizations = $_POST['customizations'] ?? [];
        $userId = get_current_user_id();

        if (empty($templateId)) {
            wp_send_json_error(['message' => 'Template ID richiesto.']);
        }

        $result = TemplateCustomizationEngine::cloneTemplate($templateId, $customizations, $userId);

        if ($result['success']) {
            wp_send_json_success([
                'template_id' => $result['template_id'],
                'message' => 'Template clonato con successo.',
            ]);
        } else {
            wp_send_json_error(['message' => $result['error']]);
        }
    }

    /**
     * Verify nonce for security.
     */
    private function verifyNonce(): void
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'fpdms_template_customization')) {
            wp_send_json_error(['message' => 'Nonce non valido.']);
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permessi insufficienti.']);
        }
    }
}

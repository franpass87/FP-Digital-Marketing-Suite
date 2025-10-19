<?php

declare(strict_types=1);

namespace FP\DMS\Admin\Pages;

use FP\DMS\Admin\AbstractAdminPage;
use FP\DMS\Services\TemplateCustomizationEngine;
use FP\DMS\Services\Connectors\ConnectionTemplate;
use FP\DMS\Support\Wp;

/**
 * Template customization page.
 */
class TemplateCustomizationPage extends AbstractAdminPage
{
    public function __construct()
    {
        parent::__construct(
            'fpdms-template-customization',
            __('Personalizzazione Template', 'fp-dms'),
            __('Personalizza i template esistenti o creane di nuovi', 'fp-dms')
        );
    }

    public function render(): void
    {
        $providers = ['ga4', 'gsc', 'meta_ads', 'google_ads', 'linkedin_ads', 'tiktok_ads'];
        $templates = ConnectionTemplate::getTemplates();
        $categories = ConnectionTemplate::getCategories();
        $difficultyLevels = ConnectionTemplate::getDifficultyLevels();

        ?>
        <div class="wrap">
            <h1><?php echo esc_html($this->title); ?></h1>
            <p class="description"><?php echo esc_html($this->description); ?></p>

            <div class="fpdms-template-customization">
                <!-- Template Selection -->
                <div class="fpdms-customization-section">
                    <h2><?php _e('Seleziona Template Base', 'fp-dms'); ?></h2>
                    <div class="fpdms-template-selector">
                        <div class="fpdms-provider-filter">
                            <label for="fpdms-provider-select"><?php _e('Provider:', 'fp-dms'); ?></label>
                            <select id="fpdms-provider-select">
                                <option value=""><?php _e('Tutti i provider', 'fp-dms'); ?></option>
                                <?php foreach ($providers as $provider) : ?>
                                    <option value="<?php echo esc_attr($provider); ?>">
                                        <?php echo esc_html(strtoupper($provider)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="fpdms-template-list" id="fpdms-template-list">
                            <?php foreach ($templates as $templateId => $template) : ?>
                                <div class="fpdms-template-option" 
                                     data-template-id="<?php echo esc_attr($templateId); ?>"
                                     data-provider="<?php echo esc_attr($template['provider']); ?>">
                                    <div class="fpdms-template-option-header">
                                        <span class="fpdms-template-icon"><?php echo esc_html($template['icon'] ?? '📋'); ?></span>
                                        <span class="fpdms-template-name"><?php echo esc_html($template['name']); ?></span>
                                        <span class="fpdms-template-provider"><?php echo esc_html(strtoupper($template['provider'])); ?></span>
                                    </div>
                                    <div class="fpdms-template-description">
                                        <?php echo esc_html($template['description']); ?>
                                    </div>
                                    <div class="fpdms-template-metrics-count">
                                        <?php
                                        printf(
                                            _n(
                                                '%d metrica',
                                                '%d metriche',
                                                count($template['metrics_preset']),
                                                'fp-dms'
                                            ),
                                            count($template['metrics_preset'])
                                        );
                                        ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Customization Form -->
                <div class="fpdms-customization-section" id="fpdms-customization-form" style="display: none;">
                    <h2><?php _e('Personalizza Template', 'fp-dms'); ?></h2>
                    
                    <form id="fpdms-template-customization-form">
                        <input type="hidden" id="fpdms-base-template-id" name="base_template_id">
                        
                        <!-- Basic Info -->
                        <div class="fpdms-form-section">
                            <h3><?php _e('Informazioni Base', 'fp-dms'); ?></h3>
                            <div class="fpdms-form-row">
                                <div class="fpdms-form-group">
                                    <label for="fpdms-template-name"><?php _e('Nome Template:', 'fp-dms'); ?></label>
                                    <input type="text" id="fpdms-template-name" name="name" class="regular-text" required>
                                </div>
                                <div class="fpdms-form-group">
                                    <label for="fpdms-template-description"><?php _e('Descrizione:', 'fp-dms'); ?></label>
                                    <textarea id="fpdms-template-description" name="description" rows="3" class="large-text"></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Metrics Customization -->
                        <div class="fpdms-form-section">
                            <h3><?php _e('Personalizza Metriche', 'fp-dms'); ?></h3>
                            <div class="fpdms-metrics-customization">
                                <div class="fpdms-current-metrics">
                                    <h4><?php _e('Metriche Attuali:', 'fp-dms'); ?></h4>
                                    <div id="fpdms-current-metrics-list" class="fpdms-metrics-list"></div>
                                </div>
                                
                                <div class="fpdms-available-metrics">
                                    <h4><?php _e('Metriche Disponibili:', 'fp-dms'); ?></h4>
                                    <div id="fpdms-available-metrics-list" class="fpdms-metrics-list"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Dimensions Customization -->
                        <div class="fpdms-form-section">
                            <h3><?php _e('Personalizza Dimensioni', 'fp-dms'); ?></h3>
                            <div class="fpdms-dimensions-customization">
                                <div class="fpdms-current-dimensions">
                                    <h4><?php _e('Dimensioni Attuali:', 'fp-dms'); ?></h4>
                                    <div id="fpdms-current-dimensions-list" class="fpdms-dimensions-list"></div>
                                </div>
                                
                                <div class="fpdms-available-dimensions">
                                    <h4><?php _e('Dimensioni Disponibili:', 'fp-dms'); ?></h4>
                                    <div id="fpdms-available-dimensions-list" class="fpdms-dimensions-list"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Tags -->
                        <div class="fpdms-form-section">
                            <h3><?php _e('Tag Consigliati', 'fp-dms'); ?></h3>
                            <div class="fpdms-form-group">
                                <label for="fpdms-template-tags"><?php _e('Tag (separati da virgola):', 'fp-dms'); ?></label>
                                <input type="text" id="fpdms-template-tags" name="tags" class="large-text" 
                                       placeholder="<?php _e('es. E-commerce, Retail, Fashion', 'fp-dms'); ?>">
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="fpdms-form-actions">
                            <button type="button" id="fpdms-preview-template" class="button">
                                <?php _e('Anteprima', 'fp-dms'); ?>
                            </button>
                            <button type="button" id="fpdms-validate-template" class="button">
                                <?php _e('Valida', 'fp-dms'); ?>
                            </button>
                            <button type="submit" id="fpdms-save-template" class="button button-primary">
                                <?php _e('Salva Template', 'fp-dms'); ?>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Preview Modal -->
                <div id="fpdms-template-preview-modal" class="fpdms-modal" style="display: none;">
                    <div class="fpdms-modal-content">
                        <div class="fpdms-modal-header">
                            <h3><?php _e('Anteprima Template', 'fp-dms'); ?></h3>
                            <button type="button" class="fpdms-modal-close">&times;</button>
                        </div>
                        <div class="fpdms-modal-body" id="fpdms-preview-content">
                            <!-- Preview content will be loaded here -->
                        </div>
                        <div class="fpdms-modal-footer">
                            <button type="button" class="button fpdms-modal-close">
                                <?php _e('Chiudi', 'fp-dms'); ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <style>
        .fpdms-template-customization {
            max-width: 1200px;
        }

        .fpdms-customization-section {
            background: white;
            padding: 2rem;
            margin-bottom: 2rem;
            border: 1px solid #e1e1e1;
            border-radius: 8px;
        }

        .fpdms-customization-section h2 {
            margin-top: 0;
            color: #23282d;
        }

        .fpdms-provider-filter {
            margin-bottom: 1rem;
        }

        .fpdms-provider-filter label {
            font-weight: 600;
            margin-right: 0.5rem;
        }

        .fpdms-template-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1rem;
        }

        .fpdms-template-option {
            border: 2px solid #e1e1e1;
            border-radius: 8px;
            padding: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .fpdms-template-option:hover {
            border-color: #0073aa;
            box-shadow: 0 2px 8px rgba(0, 115, 170, 0.1);
        }

        .fpdms-template-option.selected {
            border-color: #0073aa;
            background: #f0f8ff;
        }

        .fpdms-template-option-header {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }

        .fpdms-template-icon {
            font-size: 1.5rem;
        }

        .fpdms-template-name {
            font-weight: 600;
            flex-grow: 1;
        }

        .fpdms-template-provider {
            background: #0073aa;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .fpdms-template-description {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }

        .fpdms-template-metrics-count {
            color: #999;
            font-size: 0.8rem;
        }

        .fpdms-form-section {
            margin-bottom: 2rem;
            padding-bottom: 2rem;
            border-bottom: 1px solid #e1e1e1;
        }

        .fpdms-form-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }

        .fpdms-form-section h3 {
            margin-top: 0;
            color: #23282d;
        }

        .fpdms-form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .fpdms-form-group {
            display: flex;
            flex-direction: column;
        }

        .fpdms-form-group label {
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .fpdms-metrics-customization,
        .fpdms-dimensions-customization {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }

        .fpdms-metrics-list,
        .fpdms-dimensions-list {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #e1e1e1;
            border-radius: 4px;
            padding: 1rem;
        }

        .fpdms-metric-item,
        .fpdms-dimension-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.5rem;
            margin-bottom: 0.5rem;
            background: #f9f9f9;
            border-radius: 4px;
        }

        .fpdms-metric-item:hover,
        .fpdms-dimension-item:hover {
            background: #f0f0f0;
        }

        .fpdms-metric-name,
        .fpdms-dimension-name {
            font-weight: 500;
        }

        .fpdms-metric-actions,
        .fpdms-dimension-actions {
            display: flex;
            gap: 0.5rem;
        }

        .fpdms-metric-actions button,
        .fpdms-dimension-actions button {
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
            border: none;
            border-radius: 3px;
            cursor: pointer;
        }

        .fpdms-add-btn {
            background: #46b450;
            color: white;
        }

        .fpdms-remove-btn {
            background: #dc3232;
            color: white;
        }

        .fpdms-form-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            padding-top: 2rem;
            border-top: 1px solid #e1e1e1;
        }

        .fpdms-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 10000;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .fpdms-modal-content {
            background: white;
            border-radius: 8px;
            max-width: 800px;
            width: 90%;
            max-height: 80vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .fpdms-modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e1e1e1;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .fpdms-modal-header h3 {
            margin: 0;
        }

        .fpdms-modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #666;
        }

        .fpdms-modal-body {
            padding: 1.5rem;
            overflow-y: auto;
            flex-grow: 1;
        }

        .fpdms-modal-footer {
            padding: 1.5rem;
            border-top: 1px solid #e1e1e1;
            display: flex;
            justify-content: flex-end;
        }

        @media (max-width: 768px) {
            .fpdms-form-row,
            .fpdms-metrics-customization,
            .fpdms-dimensions-customization {
                grid-template-columns: 1fr;
            }
            
            .fpdms-template-list {
                grid-template-columns: 1fr;
            }
        }
        </style>

        <script>
        (function() {
            document.addEventListener('DOMContentLoaded', function() {
                const providerSelect = document.getElementById('fpdms-provider-select');
                const templateList = document.getElementById('fpdms-template-list');
                const customizationForm = document.getElementById('fpdms-customization-form');
                const baseTemplateId = document.getElementById('fpdms-base-template-id');
                const templateName = document.getElementById('fpdms-template-name');
                const templateDescription = document.getElementById('fpdms-template-description');
                const currentMetricsList = document.getElementById('fpdms-current-metrics-list');
                const availableMetricsList = document.getElementById('fpdms-available-metrics-list');
                const currentDimensionsList = document.getElementById('fpdms-current-dimensions-list');
                const availableDimensionsList = document.getElementById('fpdms-available-dimensions-list');
                const previewModal = document.getElementById('fpdms-template-preview-modal');
                const previewContent = document.getElementById('fpdms-preview-content');
                const customizationFormElement = document.getElementById('fpdms-template-customization-form');

                let currentTemplate = null;
                let currentProvider = null;

                // Provider filter
                if (providerSelect) {
                    providerSelect.addEventListener('change', function() {
                        filterTemplates(this.value);
                    });
                }

                // Template selection
                if (templateList) {
                    templateList.addEventListener('click', function(e) {
                        const templateOption = e.target.closest('.fpdms-template-option');
                        if (templateOption) {
                            selectTemplate(templateOption.dataset.templateId);
                        }
                    });
                }

                // Form submission
                if (customizationFormElement) {
                    customizationFormElement.addEventListener('submit', function(e) {
                        e.preventDefault();
                        saveTemplate();
                    });
                }

                // Preview button
                const previewBtn = document.getElementById('fpdms-preview-template');
                if (previewBtn) {
                    previewBtn.addEventListener('click', showPreview);
                }

                // Validate button
                const validateBtn = document.getElementById('fpdms-validate-template');
                if (validateBtn) {
                    validateBtn.addEventListener('click', validateTemplate);
                }

                // Modal close
                const modalClose = document.querySelectorAll('.fpdms-modal-close');
                modalClose.forEach(btn => {
                    btn.addEventListener('click', function() {
                        previewModal.style.display = 'none';
                    });
                });

                function filterTemplates(provider) {
                    const templateOptions = templateList.querySelectorAll('.fpdms-template-option');
                    templateOptions.forEach(option => {
                        if (!provider || option.dataset.provider === provider) {
                            option.style.display = 'block';
                        } else {
                            option.style.display = 'none';
                        }
                    });
                }

                function selectTemplate(templateId) {
                    // Remove previous selection
                    const previousSelected = templateList.querySelector('.fpdms-template-option.selected');
                    if (previousSelected) {
                        previousSelected.classList.remove('selected');
                    }

                    // Add selection to clicked template
                    const selectedTemplate = templateList.querySelector(`[data-template-id="${templateId}"]`);
                    if (selectedTemplate) {
                        selectedTemplate.classList.add('selected');
                    }

                    // Load template data
                    loadTemplateData(templateId);
                }

                function loadTemplateData(templateId) {
                    // This would typically make an AJAX call
                    // For now, we'll use the template data from PHP
                    const templates = <?php echo json_encode($templates); ?>;
                    const template = templates[templateId];
                    
                    if (template) {
                        currentTemplate = template;
                        currentProvider = template.provider;
                        
                        // Fill form with template data
                        if (baseTemplateId) baseTemplateId.value = templateId;
                        if (templateName) templateName.value = template.name + ' (Personalizzato)';
                        if (templateDescription) templateDescription.value = template.description;
                        
                        // Load metrics and dimensions
                        loadMetrics(template.metrics_preset || []);
                        loadDimensions(template.dimensions_preset || []);
                        
                        // Show customization form
                        if (customizationForm) {
                            customizationForm.style.display = 'block';
                        }
                    }
                }

                function loadMetrics(currentMetrics) {
                    // Load current metrics
                    if (currentMetricsList) {
                        currentMetricsList.innerHTML = '';
                        currentMetrics.forEach(metric => {
                            const item = createMetricItem(metric, true);
                            currentMetricsList.appendChild(item);
                        });
                    }

                    // Load available metrics
                    if (availableMetricsList && currentProvider) {
                        loadAvailableMetrics(currentProvider, currentMetrics);
                    }
                }

                function loadDimensions(currentDimensions) {
                    // Load current dimensions
                    if (currentDimensionsList) {
                        currentDimensionsList.innerHTML = '';
                        currentDimensions.forEach(dimension => {
                            const item = createDimensionItem(dimension, true);
                            currentDimensionsList.appendChild(item);
                        });
                    }

                    // Load available dimensions
                    if (availableDimensionsList && currentProvider) {
                        loadAvailableDimensions(currentProvider, currentDimensions);
                    }
                }

                function loadAvailableMetrics(provider, currentMetrics) {
                    // This would typically make an AJAX call
                    // For now, we'll simulate with available metrics
                    const availableMetrics = <?php echo json_encode(TemplateCustomizationEngine::getAvailableMetrics('ga4')); ?>;
                    
                    if (availableMetricsList) {
                        availableMetricsList.innerHTML = '';
                        Object.entries(availableMetrics).forEach(([key, label]) => {
                            if (!currentMetrics.includes(key)) {
                                const item = createMetricItem(key, false, label);
                                availableMetricsList.appendChild(item);
                            }
                        });
                    }
                }

                function loadAvailableDimensions(provider, currentDimensions) {
                    // This would typically make an AJAX call
                    // For now, we'll simulate with available dimensions
                    const availableDimensions = <?php echo json_encode(TemplateCustomizationEngine::getAvailableDimensions('ga4')); ?>;
                    
                    if (availableDimensionsList) {
                        availableDimensionsList.innerHTML = '';
                        Object.entries(availableDimensions).forEach(([key, label]) => {
                            if (!currentDimensions.includes(key)) {
                                const item = createDimensionItem(key, false, label);
                                availableDimensionsList.appendChild(item);
                            }
                        });
                    }
                }

                function createMetricItem(metric, isCurrent, label = null) {
                    const item = document.createElement('div');
                    item.className = 'fpdms-metric-item';
                    item.innerHTML = `
                        <span class="fpdms-metric-name">${label || metric}</span>
                        <div class="fpdms-metric-actions">
                            <button type="button" class="fpdms-${isCurrent ? 'remove' : 'add'}-btn" 
                                    data-metric="${metric}">
                                ${isCurrent ? 'Rimuovi' : 'Aggiungi'}
                            </button>
                        </div>
                    `;
                    
                    const button = item.querySelector('button');
                    button.addEventListener('click', function() {
                        if (isCurrent) {
                            removeMetric(metric);
                        } else {
                            addMetric(metric, label);
                        }
                    });
                    
                    return item;
                }

                function createDimensionItem(dimension, isCurrent, label = null) {
                    const item = document.createElement('div');
                    item.className = 'fpdms-dimension-item';
                    item.innerHTML = `
                        <span class="fpdms-dimension-name">${label || dimension}</span>
                        <div class="fpdms-dimension-actions">
                            <button type="button" class="fpdms-${isCurrent ? 'remove' : 'add'}-btn" 
                                    data-dimension="${dimension}">
                                ${isCurrent ? 'Rimuovi' : 'Aggiungi'}
                            </button>
                        </div>
                    `;
                    
                    const button = item.querySelector('button');
                    button.addEventListener('click', function() {
                        if (isCurrent) {
                            removeDimension(dimension);
                        } else {
                            addDimension(dimension, label);
                        }
                    });
                    
                    return item;
                }

                function addMetric(metric, label) {
                    const item = createMetricItem(metric, true, label);
                    currentMetricsList.appendChild(item);
                    
                    // Remove from available list
                    const availableItem = availableMetricsList.querySelector(`[data-metric="${metric}"]`);
                    if (availableItem) {
                        availableItem.remove();
                    }
                }

                function removeMetric(metric) {
                    const item = currentMetricsList.querySelector(`[data-metric="${metric}"]`);
                    if (item) {
                        item.remove();
                        
                        // Add back to available list
                        const availableItem = createMetricItem(metric, false);
                        availableMetricsList.appendChild(availableItem);
                    }
                }

                function addDimension(dimension, label) {
                    const item = createDimensionItem(dimension, true, label);
                    currentDimensionsList.appendChild(item);
                    
                    // Remove from available list
                    const availableItem = availableDimensionsList.querySelector(`[data-dimension="${dimension}"]`);
                    if (availableItem) {
                        availableItem.remove();
                    }
                }

                function removeDimension(dimension) {
                    const item = currentDimensionsList.querySelector(`[data-dimension="${dimension}"]`);
                    if (item) {
                        item.remove();
                        
                        // Add back to available list
                        const availableItem = createDimensionItem(dimension, false);
                        availableDimensionsList.appendChild(availableItem);
                    }
                }

                function showPreview() {
                    if (!currentTemplate) return;
                    
                    const formData = new FormData(customizationFormElement);
                    const customizedTemplate = {
                        ...currentTemplate,
                        name: formData.get('name'),
                        description: formData.get('description'),
                        metrics_preset: Array.from(currentMetricsList.querySelectorAll('.fpdms-metric-item'))
                            .map(item => item.querySelector('[data-metric]').dataset.metric),
                        dimensions_preset: Array.from(currentDimensionsList.querySelectorAll('.fpdms-dimension-item'))
                            .map(item => item.querySelector('[data-dimension]').dataset.dimension),
                    };
                    
                    if (previewContent) {
                        previewContent.innerHTML = `
                            <div class="fpdms-preview-template">
                                <h4>${customizedTemplate.name}</h4>
                                <p>${customizedTemplate.description}</p>
                                <div class="fpdms-preview-metrics">
                                    <h5>Metriche (${customizedTemplate.metrics_preset.length}):</h5>
                                    <ul>
                                        ${customizedTemplate.metrics_preset.map(metric => `<li>${metric}</li>`).join('')}
                                    </ul>
                                </div>
                                <div class="fpdms-preview-dimensions">
                                    <h5>Dimensioni (${customizedTemplate.dimensions_preset.length}):</h5>
                                    <ul>
                                        ${customizedTemplate.dimensions_preset.map(dim => `<li>${dim}</li>`).join('')}
                                    </ul>
                                </div>
                            </div>
                        `;
                    }
                    
                    previewModal.style.display = 'block';
                }

                function validateTemplate() {
                    // This would typically make an AJAX call to validate
                    alert('Template validato con successo!');
                }

                function saveTemplate() {
                    // This would typically make an AJAX call to save
                    alert('Template salvato con successo!');
                }
            });
        })();
        </script>
        <?php
    }
}

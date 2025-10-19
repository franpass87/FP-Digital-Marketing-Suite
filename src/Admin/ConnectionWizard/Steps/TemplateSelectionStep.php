<?php

declare(strict_types=1);

namespace FP\DMS\Admin\ConnectionWizard\Steps;

use FP\DMS\Admin\ConnectionWizard\AbstractWizardStep;
use FP\DMS\Services\Connectors\ConnectionTemplate;

/**
 * Template selection step - choose a pre-configured template.
 */
class TemplateSelectionStep extends AbstractWizardStep
{
    private string $provider;

    public function __construct(string $id, string $provider)
    {
        $this->provider = $provider;

        parent::__construct(
            $id,
            __('Choose a Template', 'fp-dms'),
            __('Start with a pre-configured template or customize from scratch', 'fp-dms')
        );

        $this->skippable = true;
    }

    public function render(array $data): string
    {
        $templates = ConnectionTemplate::getTemplatesByProvider($this->provider);
        $selectedTemplate = $data['template_id'] ?? '';
        $categories = ConnectionTemplate::getCategories();
        $difficultyLevels = ConnectionTemplate::getDifficultyLevels();

        ob_start();
        ?>
        <div class="fpdms-template-selection">
            <?php if (!empty($templates)) : ?>
                <div class="fpdms-template-header">
                    <p><?php _e('Scegli un template che corrisponde al tuo caso d\'uso per iniziare più velocemente:', 'fp-dms'); ?></p>
                    
                    <!-- Filters -->
                    <div class="fpdms-template-filters">
                        <div class="fpdms-filter-group">
                            <label for="fpdms-category-filter"><?php _e('Categoria:', 'fp-dms'); ?></label>
                            <select id="fpdms-category-filter" class="fpdms-filter-select">
                                <option value=""><?php _e('Tutte le categorie', 'fp-dms'); ?></option>
                                <?php foreach ($categories as $category) : ?>
                                    <option value="<?php echo esc_attr($category); ?>">
                                        <?php echo esc_html(ucfirst($category)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="fpdms-filter-group">
                            <label for="fpdms-difficulty-filter"><?php _e('Difficoltà:', 'fp-dms'); ?></label>
                            <select id="fpdms-difficulty-filter" class="fpdms-filter-select">
                                <option value=""><?php _e('Tutti i livelli', 'fp-dms'); ?></option>
                                <?php foreach ($difficultyLevels as $level) : ?>
                                    <option value="<?php echo esc_attr($level); ?>">
                                        <?php echo esc_html(ucfirst($level)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="fpdms-filter-group">
                            <input type="text" id="fpdms-search-filter" placeholder="<?php _e('Cerca template...', 'fp-dms'); ?>" class="fpdms-search-input">
                        </div>
                    </div>
                </div>
                
                <div class="fpdms-templates-grid" id="fpdms-templates-grid">
                    <?php foreach ($templates as $templateId => $template) : ?>
                        <div class="fpdms-template-card <?php echo $selectedTemplate === $templateId ? 'selected' : ''; ?>" 
                             data-template-id="<?php echo esc_attr($templateId); ?>"
                             data-category="<?php echo esc_attr($template['category'] ?? 'general'); ?>"
                             data-difficulty="<?php echo esc_attr($template['difficulty'] ?? 'beginner'); ?>"
                             data-search-terms="<?php echo esc_attr(strtolower($template['name'] . ' ' . $template['description'] . ' ' . implode(' ', $template['recommended_for'] ?? []))); ?>">
                            
                            <div class="fpdms-template-header">
                                <div class="fpdms-template-icon">
                                    <?php echo esc_html($template['icon'] ?? '📋'); ?>
                                </div>
                                <div class="fpdms-template-badges">
                                    <span class="fpdms-difficulty-badge fpdms-difficulty-<?php echo esc_attr($template['difficulty'] ?? 'beginner'); ?>">
                                        <?php echo esc_html(ucfirst($template['difficulty'] ?? 'beginner')); ?>
                                    </span>
                                    <span class="fpdms-category-badge">
                                        <?php echo esc_html(ucfirst($template['category'] ?? 'general')); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="fpdms-template-name">
                                <?php echo esc_html($template['name']); ?>
                            </div>
                            
                            <div class="fpdms-template-description">
                                <?php echo esc_html($template['description']); ?>
                            </div>
                            
                            <?php if (!empty($template['recommended_for'])) : ?>
                                <div class="fpdms-template-tags">
                                    <?php foreach ($template['recommended_for'] as $tag) : ?>
                                        <span class="fpdms-template-tag">
                                            <?php echo esc_html($tag); ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="fpdms-template-metrics">
                                <small>
                                    <?php
                                    printf(
                                        _n(
                                            '%d metrica configurata',
                                            '%d metriche configurate',
                                            count($template['metrics_preset']),
                                            'fp-dms'
                                        ),
                                        count($template['metrics_preset'])
                                    );
                                    ?>
                                </small>
                            </div>
                            
                            <div class="fpdms-template-actions">
                                <button type="button" class="fpdms-preview-btn" data-template-id="<?php echo esc_attr($templateId); ?>">
                                    <?php _e('Anteprima', 'fp-dms'); ?>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <!-- Custom/Skip option -->
                    <div class="fpdms-template-card fpdms-custom-card <?php echo empty($selectedTemplate) ? 'selected' : ''; ?>" 
                         data-template-id=""
                         data-category="custom"
                         data-difficulty="beginner"
                         data-search-terms="custom setup manual configuration">
                        <div class="fpdms-template-icon">⚙️</div>
                        <div class="fpdms-template-name">
                            <?php _e('Configurazione Personalizzata', 'fp-dms'); ?>
                        </div>
                        <div class="fpdms-template-description">
                            <?php _e('Configura le metriche manualmente', 'fp-dms'); ?>
                        </div>
                        <div class="fpdms-template-badges">
                            <span class="fpdms-difficulty-badge fpdms-difficulty-beginner">
                                <?php _e('Principiante', 'fp-dms'); ?>
                            </span>
                        </div>
                    </div>
                </div>

                <input type="hidden" name="template_id" id="fpdms_template_id" value="<?php echo esc_attr($selectedTemplate); ?>" />
                
                <!-- Template Preview Modal -->
                <div id="fpdms-template-preview-modal" class="fpdms-modal" style="display: none;">
                    <div class="fpdms-modal-content">
                        <div class="fpdms-modal-header">
                            <h3 id="fpdms-preview-title"><?php _e('Anteprima Template', 'fp-dms'); ?></h3>
                            <button type="button" class="fpdms-modal-close">&times;</button>
                        </div>
                        <div class="fpdms-modal-body" id="fpdms-preview-content">
                            <!-- Preview content will be loaded here -->
                        </div>
                        <div class="fpdms-modal-footer">
                            <button type="button" class="button button-primary" id="fpdms-use-template">
                                <?php _e('Usa questo Template', 'fp-dms'); ?>
                            </button>
                            <button type="button" class="button fpdms-modal-close">
                                <?php _e('Chiudi', 'fp-dms'); ?>
                            </button>
                        </div>
                    </div>
                </div>
                
            <?php else : ?>
                <div class="fpdms-empty-state">
                    <div class="fpdms-empty-state-icon">📋</div>
                    <div class="fpdms-empty-state-message">
                        <?php _e('Nessun template disponibile per questo provider', 'fp-dms'); ?>
                    </div>
                    <div class="fpdms-empty-state-hint">
                        <?php _e('Continua con la configurazione manuale', 'fp-dms'); ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <script>
        (function() {
            document.addEventListener('DOMContentLoaded', function() {
                const grid = document.getElementById('fpdms-templates-grid');
                const categoryFilter = document.getElementById('fpdms-category-filter');
                const difficultyFilter = document.getElementById('fpdms-difficulty-filter');
                const searchFilter = document.getElementById('fpdms-search-filter');
                const templateIdInput = document.getElementById('fpdms_template_id');
                const previewModal = document.getElementById('fpdms-template-preview-modal');
                const previewContent = document.getElementById('fpdms-preview-content');
                const previewTitle = document.getElementById('fpdms-preview-title');
                const useTemplateBtn = document.getElementById('fpdms-use-template');
                
                if (!grid) return;
                
                // Filter functionality
                function filterTemplates() {
                    const category = categoryFilter?.value || '';
                    const difficulty = difficultyFilter?.value || '';
                    const search = searchFilter?.value.toLowerCase() || '';
                    
                    const cards = grid.querySelectorAll('.fpdms-template-card');
                    
                    cards.forEach(card => {
                        const cardCategory = card.dataset.category || '';
                        const cardDifficulty = card.dataset.difficulty || '';
                        const searchTerms = card.dataset.searchTerms || '';
                        
                        const categoryMatch = !category || cardCategory === category;
                        const difficultyMatch = !difficulty || cardDifficulty === difficulty;
                        const searchMatch = !search || searchTerms.includes(search);
                        
                        if (categoryMatch && difficultyMatch && searchMatch) {
                            card.style.display = 'block';
                        } else {
                            card.style.display = 'none';
                        }
                    });
                }
                
                // Template selection
                function selectTemplate(templateId) {
                    const cards = grid.querySelectorAll('.fpdms-template-card');
                    cards.forEach(card => {
                        card.classList.remove('selected');
                    });
                    
                    const selectedCard = grid.querySelector(`[data-template-id="${templateId}"]`);
                    if (selectedCard) {
                        selectedCard.classList.add('selected');
                    }
                    
                    if (templateIdInput) {
                        templateIdInput.value = templateId;
                    }
                }
                
                // Event listeners
                if (categoryFilter) {
                    categoryFilter.addEventListener('change', filterTemplates);
                }
                if (difficultyFilter) {
                    difficultyFilter.addEventListener('change', filterTemplates);
                }
                if (searchFilter) {
                    searchFilter.addEventListener('input', filterTemplates);
                }
                
                // Template card clicks
                grid.addEventListener('click', function(e) {
                    const card = e.target.closest('.fpdms-template-card');
                    if (card) {
                        const templateId = card.dataset.templateId;
                        selectTemplate(templateId);
                    }
                });
                
                // Preview functionality
                grid.addEventListener('click', function(e) {
                    if (e.target.classList.contains('fpdms-preview-btn')) {
                        const templateId = e.target.dataset.templateId;
                        showPreview(templateId);
                    }
                });
                
                function showPreview(templateId) {
                    // This would typically make an AJAX call to get template details
                    // For now, we'll show a basic preview
                    const template = <?php echo json_encode($templates); ?>[templateId];
                    if (template) {
                        previewTitle.textContent = template.name;
                        previewContent.innerHTML = `
                            <div class="fpdms-preview-template">
                                <h4>${template.name}</h4>
                                <p>${template.description}</p>
                                <div class="fpdms-preview-metrics">
                                    <h5><?php _e('Metriche Incluse:', 'fp-dms'); ?></h5>
                                    <ul>
                                        ${template.metrics_preset.map(metric => `<li>${metric}</li>`).join('')}
                                    </ul>
                                </div>
                                ${template.dimensions_preset ? `
                                    <div class="fpdms-preview-dimensions">
                                        <h5><?php _e('Dimensioni Incluse:', 'fp-dms'); ?></h5>
                                        <ul>
                                            ${template.dimensions_preset.map(dim => `<li>${dim}</li>`).join('')}
                                        </ul>
                                    </div>
                                ` : ''}
                                <div class="fpdms-preview-recommended">
                                    <h5><?php _e('Consigliato per:', 'fp-dms'); ?></h5>
                                    <div class="fpdms-template-tags">
                                        ${template.recommended_for.map(tag => `<span class="fpdms-template-tag">${tag}</span>`).join('')}
                                    </div>
                                </div>
                            </div>
                        `;
                        previewModal.style.display = 'block';
                    }
                }
                
                // Modal functionality
                function closeModal() {
                    previewModal.style.display = 'none';
                }
                
                previewModal.addEventListener('click', function(e) {
                    if (e.target === previewModal || e.target.classList.contains('fpdms-modal-close')) {
                        closeModal();
                    }
                });
                
                if (useTemplateBtn) {
                    useTemplateBtn.addEventListener('click', function() {
                        const templateId = useTemplateBtn.dataset.templateId;
                        selectTemplate(templateId);
                        closeModal();
                    });
                }
                
                // Initialize with current selection
                const currentTemplate = templateIdInput?.value;
                if (currentTemplate) {
                    selectTemplate(currentTemplate);
                }
            });
        })();
        </script>
        <?php
        return ob_get_clean();
    }

    public function validate(array $data): array
    {
        // Template selection is optional
        return ['valid' => true];
    }

    public function process(array $data): array
    {
        $templateId = $data['template_id'] ?? '';

        if (!empty($templateId)) {
            try {
                // Apply template to configuration
                $template = ConnectionTemplate::getTemplate($templateId);
                if ($template) {
                    $data['config']['metrics'] = $template['metrics_preset'];
                    if (isset($template['dimensions_preset'])) {
                        $data['config']['dimensions'] = $template['dimensions_preset'];
                    }
                    $data['config']['template_used'] = $templateId;
                }
            } catch (\Exception $e) {
                // If template application fails, continue without it
            }
        }

        return $data;
    }

    public function getHelp(): array
    {
        return [
            'title' => __('What are templates?', 'fp-dms'),
            'content' => __(
                'Templates are pre-configured metric sets for common use cases. They help you get started quickly with recommended metrics for your business type. You can always customize the metrics later.',
                'fp-dms'
            ),
        ];
    }
}

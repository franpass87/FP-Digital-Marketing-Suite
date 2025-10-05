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
        
        ob_start();
        ?>
        <div class="fpdms-template-selection">
            <?php if (!empty($templates)): ?>
                <p><?php _e('Choose a template that matches your use case to get started faster:', 'fp-dms'); ?></p>
                
                <div class="fpdms-templates-grid">
                    <?php foreach ($templates as $templateId => $template): ?>
                        <div class="fpdms-template-card <?php echo $selectedTemplate === $templateId ? 'selected' : ''; ?>" 
                             data-template-id="<?php echo esc_attr($templateId); ?>">
                            
                            <div class="fpdms-template-icon">
                                <?php echo esc_html($template['icon'] ?? '📋'); ?>
                            </div>
                            
                            <div class="fpdms-template-name">
                                <?php echo esc_html($template['name']); ?>
                            </div>
                            
                            <div class="fpdms-template-description">
                                <?php echo esc_html($template['description']); ?>
                            </div>
                            
                            <?php if (!empty($template['recommended_for'])): ?>
                                <div class="fpdms-template-tags">
                                    <?php foreach ($template['recommended_for'] as $tag): ?>
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
                                            '%d metric configured',
                                            '%d metrics configured',
                                            count($template['metrics_preset']),
                                            'fp-dms'
                                        ),
                                        count($template['metrics_preset'])
                                    );
                                    ?>
                                </small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <!-- Custom/Skip option -->
                    <div class="fpdms-template-card <?php echo empty($selectedTemplate) ? 'selected' : ''; ?>" 
                         data-template-id="">
                        <div class="fpdms-template-icon">⚙️</div>
                        <div class="fpdms-template-name">
                            <?php _e('Custom Setup', 'fp-dms'); ?>
                        </div>
                        <div class="fpdms-template-description">
                            <?php _e('Configure metrics manually', 'fp-dms'); ?>
                        </div>
                    </div>
                </div>

                <input type="hidden" name="template_id" id="fpdms_template_id" value="<?php echo esc_attr($selectedTemplate); ?>" />
                
            <?php else: ?>
                <div class="fpdms-empty-state">
                    <div class="fpdms-empty-state-icon">📋</div>
                    <div class="fpdms-empty-state-message">
                        <?php _e('No templates available for this provider', 'fp-dms'); ?>
                    </div>
                    <div class="fpdms-empty-state-hint">
                        <?php _e('Continue to manual configuration', 'fp-dms'); ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
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

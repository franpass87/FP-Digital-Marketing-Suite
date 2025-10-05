/**
 * Connection Wizard JavaScript
 * 
 * Handles the multi-step wizard for connecting data sources.
 */
(function($) {
    'use strict';

    class ConnectionWizard {
        constructor($container) {
            this.$container = $container;
            this.provider = $container.data('provider');
            this.currentStep = 0;
            this.data = {};
            this.validator = null;
            
            this.init();
        }

        init() {
            // Initialize validator
            this.validator = new ConnectionValidator(this.provider, {
                ajaxUrl: ajaxurl,
                nonce: fpdmsWizard.nonce
            });

            // Bind events
            this.bindEvents();

            // Initialize file upload
            this.initFileUpload();

            // Initialize template selection
            this.initTemplateSelection();

            // Setup real-time validation
            this.setupRealtimeValidation();
        }

        bindEvents() {
            const self = this;

            // Navigation buttons
            this.$container.on('click', '.fpdms-wizard-btn-next', function(e) {
                e.preventDefault();
                self.nextStep();
            });

            this.$container.on('click', '.fpdms-wizard-btn-prev', function(e) {
                e.preventDefault();
                self.prevStep();
            });

            this.$container.on('click', '.fpdms-wizard-btn-skip', function(e) {
                e.preventDefault();
                self.skipStep();
            });

            this.$container.on('click', '.fpdms-wizard-btn-finish', function(e) {
                e.preventDefault();
                self.finish();
            });

            // Help button
            this.$container.on('click', '.fpdms-btn-help', function(e) {
                e.preventDefault();
                self.showHelp($(this).data('step'));
            });

            // Auto-format buttons
            this.$container.on('click', '.fpdms-btn-apply-format', function(e) {
                e.preventDefault();
                const $field = $(this).closest('.fpdms-field').find('input, textarea');
                const formatted = $(this).closest('.fpdms-field-autoformat').find('code').text();
                $field.val(formatted).trigger('input');
            });
        }

        initFileUpload() {
            const self = this;

            this.$container.on('change', '#fpdms_sa_file', function(e) {
                const file = e.target.files[0];
                
                if (!file) return;

                if (file.type !== 'application/json') {
                    self.showError(__('Please select a JSON file', 'fp-dms'));
                    return;
                }

                const reader = new FileReader();
                reader.onload = function(event) {
                    const content = event.target.result;
                    $('#fpdms_service_account').val(content).trigger('input');
                    $('.fpdms-file-name').text(file.name);
                };
                reader.readAsText(file);
            });
        }

        initTemplateSelection() {
            const self = this;

            this.$container.on('click', '.fpdms-template-card', function() {
                const $card = $(this);
                const templateId = $card.data('template-id');

                // Update selection
                $('.fpdms-template-card').removeClass('selected');
                $card.addClass('selected');
                $('#fpdms_template_id').val(templateId);

                // Store in wizard data
                self.data.template_id = templateId;
            });
        }

        setupRealtimeValidation() {
            const self = this;

            // Validate fields on input
            this.$container.on('input', '.fpdms-validated-field', function() {
                const $field = $(this);
                const fieldName = $field.attr('name');
                const value = $field.val();

                // Debounced validation
                self.validator.debounceValidate(
                    () => self.validateField(fieldName, value),
                    (result) => {
                        ValidationUI.updateFieldUI($field[0], result);
                    }
                );
            });

            // Validate on blur (immediate)
            this.$container.on('blur', '.fpdms-validated-field', function() {
                const $field = $(this);
                const fieldName = $field.attr('name');
                const value = $field.val();

                const result = self.validateField(fieldName, value);
                ValidationUI.updateFieldUI($field[0], result);
            });
        }

        validateField(fieldName, value) {
            const validatorFn = this.validator.getValidatorForField(this.provider, fieldName);
            
            if (validatorFn) {
                return validatorFn(value);
            }

            return { valid: true };
        }

        async nextStep() {
            // Collect current step data
            const stepData = this.collectStepData();

            // Validate
            const validation = await this.validateCurrentStep(stepData);

            if (!validation.valid) {
                this.showValidationErrors(validation.errors);
                return;
            }

            // Merge with wizard data
            $.extend(true, this.data, stepData);

            // Move to next step
            this.currentStep++;
            await this.loadStep(this.currentStep);
        }

        prevStep() {
            if (this.currentStep > 0) {
                this.currentStep--;
                this.loadStep(this.currentStep);
            }
        }

        skipStep() {
            this.currentStep++;
            this.loadStep(this.currentStep);
        }

        collectStepData() {
            const data = {};
            
            // Collect all form fields in current step
            this.$container.find('.fpdms-wizard-body input, .fpdms-wizard-body textarea, .fpdms-wizard-body select').each(function() {
                const $field = $(this);
                const name = $field.attr('name');
                
                if (name) {
                    // Support nested fields (e.g., auth[service_account])
                    if (name.includes('[')) {
                        const matches = name.match(/(\w+)\[(\w+)\]/);
                        if (matches) {
                            if (!data[matches[1]]) data[matches[1]] = {};
                            data[matches[1]][matches[2]] = $field.val();
                        }
                    } else {
                        data[name] = $field.val();
                    }
                }
            });

            return data;
        }

        async validateCurrentStep(stepData) {
            // Client-side validation
            let errors = {};
            let hasErrors = false;

            this.$container.find('.fpdms-validated-field[required]').each((i, field) => {
                const $field = $(field);
                const value = $field.val();
                const fieldName = $field.attr('name');

                if (!value || value.trim() === '') {
                    errors[fieldName] = fpdmsI18n?.fieldRequired || 'This field is required';
                    hasErrors = true;
                } else {
                    // Run field-specific validation
                    const result = this.validateField(fieldName, value);
                    if (!result.valid) {
                        errors[fieldName] = result.error;
                        hasErrors = true;
                    }
                }
            });

            if (hasErrors) {
                return { valid: false, errors };
            }

            // Server-side validation via AJAX (optional)
            // For now, return valid
            return { valid: true };
        }

        showValidationErrors(errors) {
            for (const [fieldName, errorMsg] of Object.entries(errors)) {
                const $field = this.$container.find(`[name="${fieldName}"]`);
                if ($field.length) {
                    ValidationUI.updateFieldUI($field[0], {
                        valid: false,
                        error: errorMsg,
                        severity: 'error'
                    });
                }
            }

            // Show notification
            this.showError(fpdmsI18n?.validationFailed || 'Please fix the errors above');
        }

        async loadStep(stepIndex) {
            // In a real implementation, this would reload the wizard via AJAX
            // For now, just update the UI
            
            // Show loading
            const $body = this.$container.find('.fpdms-wizard-body');
            ValidationUI.showLoading($body[0], fpdmsI18n?.loading || 'Loading...');

            try {
                // Reload wizard with new step
                const response = await $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'fpdms_wizard_load_step',
                        nonce: fpdmsWizard.nonce,
                        provider: this.provider,
                        step: stepIndex,
                        data: JSON.stringify(this.data)
                    }
                });

                if (response.success) {
                    // Update wizard HTML
                    this.$container.replaceWith(response.data.html);
                    // Re-initialize with new container
                    this.$container = $('.fpdms-wizard');
                    this.init();
                } else {
                    throw new Error(response.data?.message || 'Failed to load step');
                }

            } catch (error) {
                this.showError(error.message);
            } finally {
                ValidationUI.removeLoading($body[0]);
            }
        }

        async finish() {
            // Collect all data
            const stepData = this.collectStepData();
            $.extend(true, this.data, stepData);

            // Test connection
            const $body = this.$container.find('.fpdms-wizard-body');
            const loader = ValidationUI.showLoading($body[0], fpdmsI18n?.testingConnection || 'Testing connection...');

            try {
                const result = await this.validator.testConnectionLive(this.data);

                if (result.success) {
                    // Save and redirect
                    await this.saveConnection();
                    this.showSuccess(fpdmsI18n?.connectionSuccess || 'Connection successful!');
                    
                    setTimeout(() => {
                        window.location.href = fpdmsWizard.redirectUrl || 'admin.php?page=fpdms-data-sources';
                    }, 1500);
                } else {
                    this.showError(result.data?.message || fpdmsI18n?.connectionFailed || 'Connection failed');
                }

            } catch (error) {
                this.showError(error.message);
            } finally {
                ValidationUI.removeLoading($body[0]);
            }
        }

        async saveConnection() {
            return $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: 'fpdms_save_connection',
                    nonce: fpdmsWizard.nonce,
                    provider: this.provider,
                    data: JSON.stringify(this.data)
                }
            });
        }

        showHelp(stepId) {
            // Show help modal or panel
            // Implementation depends on UI framework
            alert('Help for step: ' + stepId);
        }

        showError(message) {
            // Show error notification
            if (wp && wp.data && wp.data.dispatch) {
                wp.data.dispatch('core/notices').createErrorNotice(message);
            } else {
                alert(message);
            }
        }

        showSuccess(message) {
            // Show success notification
            if (wp && wp.data && wp.data.dispatch) {
                wp.data.dispatch('core/notices').createSuccessNotice(message);
            }
        }
    }

    // Initialize wizard when DOM is ready
    $(document).ready(function() {
        const $wizard = $('.fpdms-wizard');
        if ($wizard.length) {
            new ConnectionWizard($wizard);
        }
    });

})(jQuery);

/**
 * Connection Wizard JavaScript
 * Handles the multi-step wizard for connecting data sources.
 */
(function($) {
    'use strict';

    // Cache DOM queries
    const SELECTORS = {
        WIZARD: '.fpdms-wizard',
        WIZARD_BODY: '.fpdms-wizard-body',
        BTN_NEXT: '.fpdms-wizard-btn-next',
        BTN_PREV: '.fpdms-wizard-btn-prev',
        BTN_SKIP: '.fpdms-wizard-btn-skip',
        BTN_FINISH: '.fpdms-wizard-btn-finish',
        BTN_HELP: '.fpdms-btn-help',
        BTN_APPLY_FORMAT: '.fpdms-btn-apply-format',
        FILE_INPUT: '#fpdms_sa_file',
        SA_TEXTAREA: '#fpdms_service_account',
        TEMPLATE_CARD: '.fpdms-template-card',
        TEMPLATE_ID_INPUT: '#fpdms_template_id',
        VALIDATED_FIELD: '.fpdms-validated-field',
        FILE_NAME_DISPLAY: '.fpdms-file-name'
    };

    class ConnectionWizard {
        constructor($container) {
            this.$container = $container;
            this.provider = $container.data('provider');
            this.currentStep = 0;
            this.data = {};
            this.validator = null;
            this.eventHandlers = new Map();
            
            this.init();
        }

        init() {
            // Initialize validator
            this.validator = new ConnectionValidator(this.provider, {
                ajaxUrl: window.ajaxurl,
                nonce: window.fpdmsWizard?.nonce
            });

            // Bind events with proper cleanup tracking
            this.bindEvents();
            this.initFileUpload();
            this.initTemplateSelection();
            this.setupRealtimeValidation();
        }

        bindEvents() {
            // Use event delegation for better performance
            const handlers = {
                [SELECTORS.BTN_NEXT]: (e) => {
                    e.preventDefault();
                    this.nextStep();
                },
                [SELECTORS.BTN_PREV]: (e) => {
                    e.preventDefault();
                    this.prevStep();
                },
                [SELECTORS.BTN_SKIP]: (e) => {
                    e.preventDefault();
                    this.skipStep();
                },
                [SELECTORS.BTN_FINISH]: (e) => {
                    e.preventDefault();
                    this.finish();
                },
                [SELECTORS.BTN_HELP]: (e) => {
                    e.preventDefault();
                    this.showHelp($(e.currentTarget).data('step'));
                },
                [SELECTORS.BTN_APPLY_FORMAT]: (e) => {
                    e.preventDefault();
                    const $field = $(e.currentTarget).closest('.fpdms-field').find('input, textarea');
                    const formatted = $(e.currentTarget).closest('.fpdms-field-autoformat').find('code').text();
                    $field.val(formatted).trigger('input');
                }
            };

            // Register all handlers
            Object.entries(handlers).forEach(([selector, handler]) => {
                this.$container.on('click', selector, handler);
                this.eventHandlers.set(selector, handler);
            });
        }

        initFileUpload() {
            const handler = (e) => {
                const file = e.target.files?.[0];
                if (!file) return;

                if (file.type !== 'application/json') {
                    this.showError(window.fpdmsI18n?.selectJson || 'Please select a JSON file');
                    return;
                }

                const reader = new FileReader();
                reader.onload = (event) => {
                    const $textarea = $(SELECTORS.SA_TEXTAREA);
                    if ($textarea.length) {
                        $textarea.val(event.target.result).trigger('input');
                    }
                    const $fileName = $(SELECTORS.FILE_NAME_DISPLAY);
                    if ($fileName.length) {
                        $fileName.text(file.name);
                    }
                };
                reader.onerror = () => {
                    this.showError(window.fpdmsI18n?.fileReadError || 'Error reading file');
                };
                reader.readAsText(file);
            };

            this.$container.on('change', SELECTORS.FILE_INPUT, handler);
            this.eventHandlers.set(SELECTORS.FILE_INPUT, handler);
        }

        initTemplateSelection() {
            const handler = (e) => {
                const $card = $(e.currentTarget);
                const templateId = $card.data('template-id');

                $(SELECTORS.TEMPLATE_CARD).removeClass('selected');
                $card.addClass('selected');
                
                const $input = $(SELECTORS.TEMPLATE_ID_INPUT);
                if ($input.length) {
                    $input.val(templateId);
                }
                
                this.data.template_id = templateId;
            };

            this.$container.on('click', SELECTORS.TEMPLATE_CARD, handler);
            this.eventHandlers.set(SELECTORS.TEMPLATE_CARD, handler);
        }

        setupRealtimeValidation() {
            let debounceTimer = null;

            const inputHandler = (e) => {
                const $field = $(e.currentTarget);
                const fieldName = $field.attr('name');
                const value = $field.val();

                // Clear previous timer
                if (debounceTimer) {
                    clearTimeout(debounceTimer);
                }

                // Debounced validation
                debounceTimer = setTimeout(() => {
                    const result = this.validateField(fieldName, value);
                    ValidationUI.updateFieldUI($field[0], result);
                }, 300);
            };

            const blurHandler = (e) => {
                const $field = $(e.currentTarget);
                const fieldName = $field.attr('name');
                const value = $field.val();

                const result = this.validateField(fieldName, value);
                ValidationUI.updateFieldUI($field[0], result);
            };

            this.$container.on('input', SELECTORS.VALIDATED_FIELD, inputHandler);
            this.$container.on('blur', SELECTORS.VALIDATED_FIELD, blurHandler);
            
            this.eventHandlers.set('input-validation', inputHandler);
            this.eventHandlers.set('blur-validation', blurHandler);
        }

        validateField(fieldName, value) {
            const validatorFn = this.validator?.getValidatorForField(this.provider, fieldName);
            return validatorFn ? validatorFn(value) : { valid: true };
        }

        async nextStep() {
            const stepData = this.collectStepData();
            const validation = await this.validateCurrentStep(stepData);

            if (!validation.valid) {
                this.showValidationErrors(validation.errors);
                return;
            }

            Object.assign(this.data, stepData);
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
            const $fields = this.$container.find(`${SELECTORS.WIZARD_BODY} input, ${SELECTORS.WIZARD_BODY} textarea, ${SELECTORS.WIZARD_BODY} select`);
            
            $fields.each(function() {
                const $field = $(this);
                const name = $field.attr('name');
                
                if (!name) return;

                // Support nested fields (e.g., auth[service_account])
                const matches = name.match(/(\w+)\[(\w+)\]/);
                if (matches) {
                    data[matches[1]] = data[matches[1]] || {};
                    data[matches[1]][matches[2]] = $field.val();
                } else {
                    data[name] = $field.val();
                }
            });

            return data;
        }

        async validateCurrentStep(stepData) {
            const errors = {};
            let hasErrors = false;

            const $requiredFields = this.$container.find(`${SELECTORS.VALIDATED_FIELD}[required]`);
            
            for (const field of $requiredFields) {
                const $field = $(field);
                const value = $field.val();
                const fieldName = $field.attr('name');

                if (!value?.trim()) {
                    errors[fieldName] = window.fpdmsI18n?.fieldRequired || 'This field is required';
                    hasErrors = true;
                } else {
                    const result = this.validateField(fieldName, value);
                    if (!result.valid) {
                        errors[fieldName] = result.error;
                        hasErrors = true;
                    }
                }
            }

            return { valid: !hasErrors, errors };
        }

        showValidationErrors(errors) {
            Object.entries(errors).forEach(([fieldName, errorMsg]) => {
                const $field = this.$container.find(`[name="${fieldName}"]`);
                if ($field.length) {
                    ValidationUI.updateFieldUI($field[0], {
                        valid: false,
                        error: errorMsg,
                        severity: 'error'
                    });
                }
            });

            this.showError(window.fpdmsI18n?.validationFailed || 'Please fix the errors above');
        }

        async loadStep(stepIndex) {
            const $body = this.$container.find(SELECTORS.WIZARD_BODY);
            const loader = ValidationUI.showLoading($body[0], window.fpdmsI18n?.loading || 'Loading...');

            try {
                const response = await $.ajax({
                    url: window.ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'fpdms_wizard_load_step',
                        nonce: window.fpdmsWizard?.nonce,
                        provider: this.provider,
                        step: stepIndex,
                        data: JSON.stringify(this.data)
                    }
                });

                if (response.success) {
                    // Cleanup old event handlers before replacing
                    this.cleanup();
                    
                    this.$container.replaceWith(response.data.html);
                    this.$container = $(SELECTORS.WIZARD);
                    this.init();
                } else {
                    throw new Error(response.data?.message || 'Failed to load step');
                }
            } catch (error) {
                this.showError(error.message);
            } finally {
                if (loader && loader.parentNode) {
                    loader.remove();
                }
            }
        }

        async finish() {
            const stepData = this.collectStepData();
            Object.assign(this.data, stepData);

            const $body = this.$container.find(SELECTORS.WIZARD_BODY);
            const loader = ValidationUI.showLoading($body[0], window.fpdmsI18n?.testingConnection || 'Testing connection...');

            try {
                const result = await this.validator.testConnectionLive(this.data);

                if (result.success) {
                    await this.saveConnection();
                    this.showSuccess(window.fpdmsI18n?.connectionSuccess || 'Connection successful!');
                    
                    setTimeout(() => {
                        window.location.href = window.fpdmsWizard?.redirectUrl || 'admin.php?page=fpdms-data-sources';
                    }, 1500);
                } else {
                    this.showError(result.data?.message || window.fpdmsI18n?.connectionFailed || 'Connection failed');
                }
            } catch (error) {
                this.showError(error.message);
            } finally {
                if (loader && loader.parentNode) {
                    loader.remove();
                }
            }
        }

        async saveConnection() {
            return $.ajax({
                url: window.ajaxurl,
                method: 'POST',
                data: {
                    action: 'fpdms_save_connection',
                    nonce: window.fpdmsWizard?.nonce,
                    provider: this.provider,
                    data: JSON.stringify(this.data)
                }
            });
        }

        showHelp(stepId) {
            // TODO: Implement help modal
            console.info('Help for step:', stepId);
        }

        showError(message) {
            if (window.wp?.data?.dispatch) {
                window.wp.data.dispatch('core/notices').createErrorNotice(message);
            } else {
                alert(message);
            }
        }

        showSuccess(message) {
            if (window.wp?.data?.dispatch) {
                window.wp.data.dispatch('core/notices').createSuccessNotice(message);
            }
        }

        cleanup() {
            // Remove all event handlers to prevent memory leaks
            this.eventHandlers.forEach((handler, selector) => {
                this.$container.off('click change input blur', selector, handler);
            });
            this.eventHandlers.clear();
        }
    }

    // Initialize wizard when DOM is ready
    $(document).ready(function() {
        const $wizard = $(SELECTORS.WIZARD);
        if ($wizard.length) {
            new ConnectionWizard($wizard);
        }
    });

})(jQuery);
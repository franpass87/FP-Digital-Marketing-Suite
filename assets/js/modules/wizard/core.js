/**
 * Wizard Core
 * Logica principale del Connection Wizard
 */
import { SELECTORS } from './constants.js';
import { FileUploadHandler } from './file-upload.js';
import { TemplateSelector } from './template-selector.js';
import { ValidationHandler } from './validation.js';
import { StepsManager } from './steps.js';
import { AutoDiscoveryHandler } from './autodiscovery.js';

// Import global classes (defined in connection-validator.js)
// Note: ConnectionValidator and ValidationUI are loaded globally

// jQuery reference for ES6 modules
const $ = window.jQuery;

export class ConnectionWizard {
    constructor($container) {
        this.$container = $container;
        this.provider = $container.data('provider');
        this.data = {};
        this.validator = null;
        
        // Sub-modules
        this.fileUploadHandler = null;
        this.templateSelector = null;
        this.validationHandler = null;
        this.stepsManager = null;
        this.autoDiscoveryHandler = null;
        
        // Event handlers
        this.eventHandlers = new Map();
        
        this.init();
    }

    init() {
        console.log('🔵 [DEBUG] ConnectionWizard.init() chiamato per provider:', this.provider);
        console.log('🔵 [DEBUG] Container:', this.$container);
        
        // Initialize validator
        this.validator = new ConnectionValidator(this.provider, {
            ajaxUrl: window.ajaxurl,
            nonce: window.fpdmsWizard?.nonce
        });

        // Initialize sub-modules
        this.stepsManager = new StepsManager(this.$container, this.provider, this.data);
        this.fileUploadHandler = new FileUploadHandler(this.$container, (msg) => this.showError(msg));
        this.templateSelector = new TemplateSelector(this.$container, this.data);
        this.validationHandler = new ValidationHandler(this.$container, this.validator, this.provider);
        this.autoDiscoveryHandler = new AutoDiscoveryHandler(this.$container, this.validator, this.provider);

        // Initialize all components
        this.bindNavigationEvents();
        this.fileUploadHandler.init();
        this.templateSelector.init();
        this.validationHandler.init();
        this.autoDiscoveryHandler.init();
    }

    bindNavigationEvents() {
        const handlers = {
            [SELECTORS.BTN_NEXT]: (e) => {
                console.log('🔵 [DEBUG] Pulsante Avanti cliccato!');
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

    async nextStep() {
        console.log('🔵 [DEBUG] nextStep() chiamato');
        console.log('🔵 [DEBUG] Current step:', this.stepsManager.getCurrentStep());
        
        // Collect current step data
        const stepData = this.stepsManager.collectStepData();
        console.log('🔵 [DEBUG] Dati raccolti dallo step:', stepData);

        if (window.fpdmsDebug) {
            console.log('Next step - collected data:', stepData);
        }

        // Validate
        console.log('🔵 [DEBUG] Inizio validazione...');
        const validation = await this.validationHandler.validateRequiredFields();
        console.log('🔵 [DEBUG] Risultato validazione:', validation);

        if (window.fpdmsDebug) {
            console.log('Validation result:', validation);
        }

        if (!validation.valid) {
            console.log('❌ [DEBUG] Validazione fallita, errori:', validation.errors);
            this.validationHandler.showValidationErrors(validation.errors);
            this.showError(window.fpdmsI18n?.validationFailed || 'Please fix the errors above');
            return;
        }

        console.log('✅ [DEBUG] Validazione superata!');
        
        // Merge with wizard data
        Object.assign(this.data, stepData);

        if (window.fpdmsDebug) {
            console.log('Updated wizard data:', this.data);
        }

        // Move to next step
        console.log('🔵 [DEBUG] Incremento step...');
        this.stepsManager.incrementStep();
        console.log('🔵 [DEBUG] Nuovo step:', this.stepsManager.getCurrentStep());
        
        console.log('🔵 [DEBUG] Caricamento step successivo...');
        await this.loadCurrentStep();
        console.log('✅ [DEBUG] Step caricato!');
    }

    prevStep() {
        this.stepsManager.decrementStep();
        this.loadCurrentStep();
    }

    skipStep() {
        this.stepsManager.incrementStep();
        this.loadCurrentStep();
    }

    async loadCurrentStep() {
        console.log('🔵 [DEBUG] loadCurrentStep() chiamato');
        console.log('🔵 [DEBUG] Step da caricare:', this.stepsManager.getCurrentStep());
        
        if (window.fpdmsDebug) {
            console.log('Loading step:', this.stepsManager.getCurrentStep());
        }

        const result = await this.stepsManager.loadStep(this.stepsManager.getCurrentStep());
        console.log('🔵 [DEBUG] Risultato loadStep:', result);
        
        if (window.fpdmsDebug) {
            console.log('Step load result:', result);
        }

        if (result.success) {
            console.log('✅ [DEBUG] Step caricato con successo, sostituisco container...');
            // Cleanup old event handlers before replacing
            this.cleanup();
            
            // Create new container from HTML and replace old one
            const $newContainer = $(result.html);
            console.log('🔵 [DEBUG] Nuovo container creato:', $newContainer);
            this.$container.replaceWith($newContainer);
            this.$container = $newContainer;
            console.log('🔵 [DEBUG] Re-inizializzo wizard...');
            this.init();
            console.log('✅ [DEBUG] Wizard re-inizializzato!');
        } else {
            console.log('❌ [DEBUG] Errore nel caricamento step:', result.error);
            this.showError(result.error);
        }
    }

    async finish() {
        // Collect all data
        const stepData = this.stepsManager.collectStepData();
        Object.assign(this.data, stepData);

        const $body = this.$container.find(SELECTORS.WIZARD_BODY);
        const loader = ValidationUI.showLoading($body[0], window.fpdmsI18n?.testingConnection || 'Testing connection...');

        try {
            const result = await this.validator.testConnectionLive(this.data);

            if (result.success) {
                await this.saveConnection();
                this.showSuccess(window.fpdmsI18n?.connectionSuccess || 'Connection successful!');
                
                setTimeout(() => {
                    window.location.href = window.fpdmsWizard?.redirectUrl || 'admin.php?page=fp-dms-datasources';
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
        // Ensure client_id is included in the data
        const saveData = {
            ...this.data,
            client_id: window.fpdmsWizard?.clientId || 0
        };

        return $.ajax({
            url: window.ajaxurl,
            method: 'POST',
            data: {
                action: 'fpdms_save_connection',
                nonce: window.fpdmsWizard?.nonce,
                provider: this.provider,
                data: JSON.stringify(saveData)
            }
        });
    }

    showHelp(stepId) {
        if (window.fpdmsDebug) {
            console.info('Help for step:', stepId);
        }
        // TODO: Implement help modal or tooltip system
    }

    showError(message) {
        if (window.wp?.data?.dispatch) {
            window.wp.data.dispatch('core/notices').createErrorNotice(message);
        } else {
            // Fallback: show error message in a styled div instead of alert
            const errorDiv = document.createElement('div');
            errorDiv.className = 'notice notice-error is-dismissible';
            errorDiv.innerHTML = `<p>${message}</p>`;
            const container = this.$container[0] || document.querySelector('.wrap');
            if (container) {
                container.insertBefore(errorDiv, container.firstChild);
                // Auto-dismiss after 5 seconds
                setTimeout(() => errorDiv.remove(), 5000);
            } else {
                // Last resort fallback
                alert(message);
            }
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

        // Cleanup sub-modules
        this.fileUploadHandler?.cleanup();
        this.templateSelector?.cleanup();
        this.validationHandler?.cleanup();
        this.autoDiscoveryHandler?.cleanup();
    }
}
/**
 * Wizard Steps Manager
 * Gestisce la navigazione tra gli step del wizard
 */
import { SELECTORS } from './constants.js';

export class StepsManager {
    constructor($container, provider, data) {
        this.$container = $container;
        this.provider = provider;
        this.data = data;
        this.currentStep = 0;
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
                return {
                    success: true,
                    html: response.data.html
                };
            } else {
                throw new Error(response.data?.message || 'Failed to load step');
            }
        } catch (error) {
            return {
                success: false,
                error: error.message
            };
        } finally {
            if (loader && loader.parentNode) {
                loader.remove();
            }
        }
    }

    incrementStep() {
        this.currentStep++;
    }

    decrementStep() {
        if (this.currentStep > 0) {
            this.currentStep--;
        }
    }

    getCurrentStep() {
        return this.currentStep;
    }
}
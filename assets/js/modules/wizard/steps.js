/**
 * Wizard Steps Manager
 * Gestisce la navigazione tra gli step del wizard
 */
import { SELECTORS } from './constants.js';

// Note: ValidationUI is loaded globally from connection-validator.js

// jQuery reference for ES6 modules
const $ = window.jQuery;

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

        if (window.fpdmsDebug) {
            console.log('Loading step', stepIndex, 'with data:', this.data);
        }

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

            if (window.fpdmsDebug) {
                console.log('AJAX response:', response);
            }

            // Rimuovi il loader PRIMA di ritornare il risultato
            if (loader && loader.parentNode) {
                loader.remove();
            }

            if (response.success) {
                return {
                    success: true,
                    html: response.data.html
                };
            } else {
                throw new Error(response.data?.message || 'Failed to load step');
            }
        } catch (error) {
            if (window.fpdmsDebug) {
                console.error('Error loading step:', error);
            }
            // Rimuovi il loader anche in caso di errore
            if (loader && loader.parentNode) {
                loader.remove();
            }
            return {
                success: false,
                error: error.message
            };
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
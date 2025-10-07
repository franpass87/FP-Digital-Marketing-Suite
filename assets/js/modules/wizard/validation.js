/**
 * Wizard Validation Handler
 * Gestisce la validazione real-time dei campi
 */
import { SELECTORS } from './constants.js';

export class ValidationHandler {
    constructor($container, validator, provider) {
        this.$container = $container;
        this.validator = validator;
        this.provider = provider;
        this.debounceTimer = null;
        this.inputHandler = null;
        this.blurHandler = null;
    }

    init() {
        this.inputHandler = (e) => {
            const $field = $(e.currentTarget);
            const fieldName = $field.attr('name');
            const value = $field.val();

            // Clear previous timer
            if (this.debounceTimer) {
                clearTimeout(this.debounceTimer);
            }

            // Debounced validation
            this.debounceTimer = setTimeout(() => {
                const result = this.validateField(fieldName, value);
                ValidationUI.updateFieldUI($field[0], result);
            }, 300);
        };

        this.blurHandler = (e) => {
            const $field = $(e.currentTarget);
            const fieldName = $field.attr('name');
            const value = $field.val();

            const result = this.validateField(fieldName, value);
            ValidationUI.updateFieldUI($field[0], result);
        };

        this.$container.on('input', SELECTORS.VALIDATED_FIELD, this.inputHandler);
        this.$container.on('blur', SELECTORS.VALIDATED_FIELD, this.blurHandler);
    }

    validateField(fieldName, value) {
        const validatorFn = this.validator?.getValidatorForField(this.provider, fieldName);
        return validatorFn ? validatorFn(value) : { valid: true };
    }

    async validateRequiredFields() {
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
    }

    cleanup() {
        if (this.debounceTimer) {
            clearTimeout(this.debounceTimer);
            this.debounceTimer = null;
        }

        if (this.inputHandler) {
            this.$container.off('input', SELECTORS.VALIDATED_FIELD, this.inputHandler);
            this.inputHandler = null;
        }

        if (this.blurHandler) {
            this.$container.off('blur', SELECTORS.VALIDATED_FIELD, this.blurHandler);
            this.blurHandler = null;
        }
    }
}
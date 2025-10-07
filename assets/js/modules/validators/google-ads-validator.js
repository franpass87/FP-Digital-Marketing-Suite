/**
 * Google Ads Customer ID Validator
 */
export class GoogleAdsValidator {
    constructor(i18n = {}) {
        this.i18n = i18n;
    }

    validateCustomerId(value) {
        const input = value.trim();

        if (!input) {
            return {
                valid: false,
                error: this.i18n.customerIdRequired || 'Customer ID is required',
                severity: 'error'
            };
        }

        // Auto-format: add hyphens if missing
        const formatted = this._formatCustomerId(input);
        const pattern = /^\d{3}-\d{3}-\d{4}$/;

        if (!pattern.test(formatted)) {
            return {
                valid: false,
                error: this.i18n.customerIdFormat || 'Invalid Customer ID format',
                suggestion: this.i18n.customerIdExample || 'Use format: 123-456-7890',
                autoFormat: formatted !== input ? formatted : null,
                severity: 'error'
            };
        }

        return {
            valid: true,
            formatted: formatted,
            message: formatted !== input ? this.i18n.autoFormatted || 'Auto-formatted' : null
        };
    }

    _formatCustomerId(input) {
        let formatted = input.replace(/[^0-9]/g, '');
        if (formatted.length === 10) {
            formatted = `${formatted.slice(0, 3)}-${formatted.slice(3, 6)}-${formatted.slice(6)}`;
        }
        return formatted;
    }
}
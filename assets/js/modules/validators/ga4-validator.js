/**
 * GA4 Property ID Validator
 */
export class GA4Validator {
    constructor(i18n = {}) {
        this.i18n = i18n;
    }

    validatePropertyId(value) {
        const input = value.trim();

        if (!input) {
            return {
                valid: false,
                error: this.i18n.propertyIdRequired || 'Property ID is required',
                severity: 'error'
            };
        }

        // Check format - should be numeric only
        if (!/^\d+$/.test(input)) {
            return {
                valid: false,
                error: this.i18n.propertyIdNumeric || 'Property ID must contain only numbers',
                suggestion: this.i18n.propertyIdExample || 'Example: 123456789',
                severity: 'error'
            };
        }

        // Check reasonable length
        if (input.length < 6 || input.length > 15) {
            return {
                valid: false,
                error: this.i18n.propertyIdLength || 'Property ID seems too short or too long',
                suggestion: this.i18n.propertyIdCheck || 'Please verify you copied the correct ID',
                severity: 'warning'
            };
        }

        return { valid: true };
    }
}
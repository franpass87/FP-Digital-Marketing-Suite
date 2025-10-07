/**
 * Meta Ads Account ID Validator
 */
export class MetaAdsValidator {
    constructor(i18n = {}) {
        this.i18n = i18n;
    }

    validateAccountId(value) {
        const input = value.trim();

        if (!input) {
            return {
                valid: false,
                error: this.i18n.accountIdRequired || 'Account ID is required',
                severity: 'error'
            };
        }

        const pattern = /^act_[0-9]+$/;
        
        if (!pattern.test(input)) {
            // Try to auto-fix: add act_ prefix if missing
            const numericPart = input.replace(/[^0-9]/g, '');
            
            if (numericPart.length > 0) {
                return {
                    valid: false,
                    error: this.i18n.accountIdFormat || 'Account ID must start with "act_"',
                    suggestion: this.i18n.accountIdExample || 'Example: act_1234567890',
                    autoFormat: `act_${numericPart}`,
                    severity: 'error'
                };
            }

            return {
                valid: false,
                error: this.i18n.accountIdInvalid || 'Invalid Account ID',
                suggestion: this.i18n.accountIdExample || 'Example: act_1234567890',
                severity: 'error'
            };
        }

        return { valid: true };
    }
}
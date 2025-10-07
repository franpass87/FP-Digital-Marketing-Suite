/**
 * Connection Validator Entry Point
 * Orchestrates all validators with modular architecture
 */
import { GA4Validator, GoogleAdsValidator, MetaAdsValidator, GSCValidator, ServiceAccountValidator } from './modules/validators/index.js';
import { getI18n } from './modules/common/i18n.js';
import { DEFAULT_DEBOUNCE_MS } from './modules/common/constants.js';
import { ValidationUI } from './modules/validators/validation-ui.js';

class ConnectionValidator {
    constructor(providerType, options = {}) {
        this.providerType = providerType;
        this.debounceTimer = null;
        this.debounceDelay = options.debounceDelay || DEFAULT_DEBOUNCE_MS;
        this.ajaxUrl = options.ajaxUrl || window.ajaxurl;
        this.nonce = options.nonce || '';
        
        // Initialize i18n
        this.i18n = getI18n({});
        
        // Initialize validators
        this.validators = {
            ga4: new GA4Validator(this.i18n),
            googleAds: new GoogleAdsValidator(this.i18n),
            metaAds: new MetaAdsValidator(this.i18n),
            gsc: new GSCValidator(this.i18n),
            serviceAccount: new ServiceAccountValidator(this.i18n)
        };
    }

    /**
     * Test connection live via AJAX with error handling.
     */
    async testConnectionLive(data) {
        try {
            const formData = new FormData();
            formData.append('action', 'fpdms_test_connection_live');
            formData.append('provider', this.providerType);
            formData.append('data', JSON.stringify(data));
            formData.append('nonce', this.nonce);

            const response = await fetch(this.ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            return await response.json();
        } catch (error) {
            return {
                success: false,
                data: {
                    title: this.i18n.connectionError || 'Connection Error',
                    message: error.message || this.i18n.unknownError || 'Unknown error occurred'
                }
            };
        }
    }

    /**
     * Debounced validation - useful for input events.
     */
    debounceValidate(validationFn, callback) {
        clearTimeout(this.debounceTimer);
        this.debounceTimer = setTimeout(() => {
            const result = validationFn();
            callback(result);
        }, this.debounceDelay);
    }

    /**
     * Get validator function for a specific field.
     */
    getValidatorForField(provider, field) {
        // Service account validator is common to all Google providers
        if (field === 'service_account') {
            return (val) => this.validators.serviceAccount.validateJson(val);
        }

        const validatorMap = {
            'ga4': {
                'property_id': (val) => this.validators.ga4.validatePropertyId(val)
            },
            'gsc': {
                'site_url': (val) => this.validators.gsc.validateSiteUrl(val)
            },
            'google_ads': {
                'customer_id': (val) => this.validators.googleAds.validateCustomerId(val)
            },
            'meta_ads': {
                'account_id': (val) => this.validators.metaAds.validateAccountId(val)
            }
        };

        return validatorMap[provider]?.[field] || null;
    }

    /**
     * Clear all caches.
     */
    clearCache() {
        this.validators.serviceAccount.clearCache();
    }
}

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { ConnectionValidator, ValidationUI };
}

// Make available globally for non-module scripts
if (typeof window !== 'undefined') {
    window.ConnectionValidator = ConnectionValidator;
    window.ValidationUI = ValidationUI;
}
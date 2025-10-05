/**
 * Real-time connection validator for FPDMS connectors.
 *
 * Provides instant validation feedback as users configure data sources.
 */
class ConnectionValidator {
    constructor(providerType, options = {}) {
        this.providerType = providerType;
        this.debounceTimer = null;
        this.debounceDelay = options.debounceDelay || 500;
        this.ajaxUrl = options.ajaxUrl || window.ajaxurl;
        this.nonce = options.nonce || '';
    }

    /**
     * Validate GA4 Property ID format.
     */
    validateGA4PropertyId(value) {
        const input = value.trim();

        if (input === '') {
            return {
                valid: false,
                error: window.fpdmsI18n?.propertyIdRequired || 'Property ID is required',
                severity: 'error'
            };
        }

        // Check format - should be numeric only
        if (!/^\d+$/.test(input)) {
            return {
                valid: false,
                error: window.fpdmsI18n?.propertyIdNumeric || 'Property ID must contain only numbers',
                suggestion: window.fpdmsI18n?.propertyIdExample || 'Example: 123456789',
                severity: 'error'
            };
        }

        // Check reasonable length
        if (input.length < 6 || input.length > 15) {
            return {
                valid: false,
                error: window.fpdmsI18n?.propertyIdLength || 'Property ID seems too short or too long',
                suggestion: window.fpdmsI18n?.propertyIdCheck || 'Please verify you copied the correct ID',
                severity: 'warning'
            };
        }

        return { valid: true };
    }

    /**
     * Validate Google Ads Customer ID with auto-formatting.
     */
    validateGoogleAdsCustomerId(value) {
        const input = value.trim();

        if (input === '') {
            return {
                valid: false,
                error: window.fpdmsI18n?.customerIdRequired || 'Customer ID is required',
                severity: 'error'
            };
        }

        // Auto-format: add hyphens if missing
        let formatted = input.replace(/[^0-9]/g, '');
        if (formatted.length === 10) {
            formatted = `${formatted.slice(0, 3)}-${formatted.slice(3, 6)}-${formatted.slice(6)}`;
        }

        const pattern = /^\d{3}-\d{3}-\d{4}$/;
        if (!pattern.test(formatted)) {
            return {
                valid: false,
                error: window.fpdmsI18n?.customerIdFormat || 'Invalid Customer ID format',
                suggestion: window.fpdmsI18n?.customerIdExample || 'Use format: 123-456-7890',
                autoFormat: formatted !== input ? formatted : null,
                severity: 'error'
            };
        }

        return {
            valid: true,
            formatted: formatted,
            message: formatted !== input ? window.fpdmsI18n?.autoFormatted || 'Auto-formatted' : null
        };
    }

    /**
     * Validate Meta Ads Account ID.
     */
    validateMetaAdsAccountId(value) {
        const input = value.trim();

        if (input === '') {
            return {
                valid: false,
                error: window.fpdmsI18n?.accountIdRequired || 'Account ID is required',
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
                    error: window.fpdmsI18n?.accountIdFormat || 'Account ID must start with "act_"',
                    suggestion: window.fpdmsI18n?.accountIdExample || 'Example: act_1234567890',
                    autoFormat: `act_${numericPart}`,
                    severity: 'error'
                };
            }

            return {
                valid: false,
                error: window.fpdmsI18n?.accountIdInvalid || 'Invalid Account ID',
                suggestion: window.fpdmsI18n?.accountIdExample || 'Example: act_1234567890',
                severity: 'error'
            };
        }

        return { valid: true };
    }

    /**
     * Validate GSC Site URL.
     */
    validateGSCSiteUrl(value) {
        const input = value.trim();

        if (input === '') {
            return {
                valid: false,
                error: window.fpdmsI18n?.siteUrlRequired || 'Site URL is required',
                severity: 'error'
            };
        }

        // Check if it's a valid URL format
        let url;
        try {
            // Add protocol if missing
            const urlToTest = input.startsWith('http') ? input : `https://${input}`;
            url = new URL(urlToTest);
        } catch (e) {
            return {
                valid: false,
                error: window.fpdmsI18n?.siteUrlInvalid || 'Invalid URL format',
                suggestion: window.fpdmsI18n?.siteUrlExample || 'Example: https://www.example.com',
                severity: 'error'
            };
        }

        // Suggest canonical format
        const canonical = url.protocol + '//' + url.hostname + (url.port ? ':' + url.port : '');
        if (canonical !== input && !input.startsWith('sc-domain:')) {
            return {
                valid: true,
                autoFormat: canonical,
                message: window.fpdmsI18n?.canonicalUrl || 'Suggested canonical format'
            };
        }

        return { valid: true };
    }

    /**
     * Validate Service Account JSON.
     */
    validateServiceAccountJson(json) {
        if (!json || json.trim() === '') {
            return {
                valid: false,
                error: window.fpdmsI18n?.serviceAccountRequired || 'Service account JSON is required',
                severity: 'error'
            };
        }

        try {
            const parsed = JSON.parse(json);

            // Check required fields
            const required = ['type', 'project_id', 'private_key', 'client_email'];
            const missing = required.filter(k => !parsed[k]);

            if (missing.length > 0) {
                return {
                    valid: false,
                    error: window.fpdmsI18n?.serviceAccountMissing || 'Missing fields in JSON',
                    suggestion: `${window.fpdmsI18n?.missingFields || 'Missing'}: ${missing.join(', ')}`,
                    severity: 'error'
                };
            }

            // Check type
            if (parsed.type !== 'service_account') {
                return {
                    valid: false,
                    error: window.fpdmsI18n?.serviceAccountWrongType || 'This is not a service account JSON',
                    suggestion: window.fpdmsI18n?.downloadCorrectFile || 'Download the correct file from Google Cloud Console',
                    severity: 'error'
                };
            }

            // Extract useful info
            return {
                valid: true,
                info: {
                    email: parsed.client_email,
                    project: parsed.project_id,
                    type: parsed.type
                },
                message: window.fpdmsI18n?.validServiceAccount || 'Valid service account'
            };

        } catch (e) {
            return {
                valid: false,
                error: window.fpdmsI18n?.invalidJson || 'Invalid JSON format',
                suggestion: window.fpdmsI18n?.copyEntireFile || 'Copy the entire file content without modifications',
                severity: 'error'
            };
        }
    }

    /**
     * Test connection live via AJAX.
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
                    title: window.fpdmsI18n?.connectionError || 'Connection Error',
                    message: error.message || window.fpdmsI18n?.unknownError || 'Unknown error occurred'
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
        const validators = {
            'ga4': {
                'property_id': (val) => this.validateGA4PropertyId(val)
            },
            'gsc': {
                'site_url': (val) => this.validateGSCSiteUrl(val)
            },
            'google_ads': {
                'customer_id': (val) => this.validateGoogleAdsCustomerId(val)
            },
            'meta_ads': {
                'account_id': (val) => this.validateMetaAdsAccountId(val)
            }
        };

        // Service account validator is common to all Google providers
        if (field === 'service_account') {
            return (val) => this.validateServiceAccountJson(val);
        }

        return validators[provider]?.[field] || null;
    }
}

/**
 * UI Helper for showing validation results.
 */
class ValidationUI {
    /**
     * Update field UI with validation result.
     */
    static updateFieldUI(fieldElement, result) {
        const container = fieldElement.closest('.fpdms-field');
        if (!container) return;

        // Remove previous validation classes and messages
        container.classList.remove('fpdms-field--valid', 'fpdms-field--error', 'fpdms-field--warning');
        const oldMessage = container.querySelector('.fpdms-field-message');
        if (oldMessage) oldMessage.remove();

        // Add new validation state
        if (result.valid) {
            container.classList.add('fpdms-field--valid');
            if (result.message) {
                this.showMessage(container, result.message, 'success');
            }
            if (result.info) {
                this.showInfo(container, result.info);
            }
        } else {
            const severity = result.severity || 'error';
            container.classList.add(`fpdms-field--${severity}`);
            
            const message = result.error + (result.suggestion ? '<br><small>' + result.suggestion + '</small>' : '');
            this.showMessage(container, message, severity);
        }

        // Apply auto-format if available
        if (result.autoFormat && result.autoFormat !== fieldElement.value) {
            this.showAutoFormat(container, fieldElement, result.autoFormat);
        }
    }

    /**
     * Show validation message.
     */
    static showMessage(container, message, type = 'error') {
        const messageEl = document.createElement('div');
        messageEl.className = `fpdms-field-message fpdms-field-message--${type}`;
        messageEl.innerHTML = message;
        container.appendChild(messageEl);
    }

    /**
     * Show service account info.
     */
    static showInfo(container, info) {
        const infoEl = document.createElement('div');
        infoEl.className = 'fpdms-field-info';
        infoEl.innerHTML = `
            <div class="fpdms-sa-info">
                <strong>✅ ${window.fpdmsI18n?.validatedInfo || 'Validated'}:</strong><br>
                📧 ${info.email}<br>
                📦 ${info.project}
            </div>
        `;
        container.appendChild(infoEl);
    }

    /**
     * Show auto-format suggestion.
     */
    static showAutoFormat(container, fieldElement, formattedValue) {
        const suggestion = document.createElement('div');
        suggestion.className = 'fpdms-field-autoformat';
        suggestion.innerHTML = `
            <span>${window.fpdmsI18n?.suggestedFormat || 'Suggested format'}: <code>${formattedValue}</code></span>
            <button type="button" class="fpdms-btn-apply-format button button-small">
                ${window.fpdmsI18n?.apply || 'Apply'}
            </button>
        `;
        
        suggestion.querySelector('.fpdms-btn-apply-format').addEventListener('click', () => {
            fieldElement.value = formattedValue;
            fieldElement.dispatchEvent(new Event('input', { bubbles: true }));
            suggestion.remove();
        });

        container.appendChild(suggestion);
    }

    /**
     * Show loading state during live test.
     */
    static showLoading(container, message = null) {
        const loader = document.createElement('div');
        loader.className = 'fpdms-loading';
        loader.innerHTML = `
            <span class="spinner is-active"></span>
            <span>${message || window.fpdmsI18n?.testing || 'Testing connection...'}</span>
        `;
        container.appendChild(loader);
        return loader;
    }

    /**
     * Remove loading state.
     */
    static removeLoading(container) {
        const loader = container.querySelector('.fpdms-loading');
        if (loader) loader.remove();
    }
}

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { ConnectionValidator, ValidationUI };
}

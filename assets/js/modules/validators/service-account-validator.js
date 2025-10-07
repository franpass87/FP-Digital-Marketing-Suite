/**
 * Service Account JSON Validator
 */
export class ServiceAccountValidator {
    constructor(i18n = {}) {
        this.i18n = i18n;
        this.cache = new Map();
    }

    validateJson(json) {
        if (!json || json.trim() === '') {
            return {
                valid: false,
                error: this.i18n.serviceAccountRequired || 'Service account JSON is required',
                severity: 'error'
            };
        }

        // Check cache
        const cacheKey = `sa_${json.substring(0, 50)}`;
        if (this.cache.has(cacheKey)) {
            return this.cache.get(cacheKey);
        }

        let result;
        
        try {
            const parsed = JSON.parse(json);

            // Check required fields
            const required = ['type', 'project_id', 'private_key', 'client_email'];
            const missing = required.filter(k => !parsed[k]);

            if (missing.length > 0) {
                result = {
                    valid: false,
                    error: this.i18n.serviceAccountMissing || 'Missing fields in JSON',
                    suggestion: `${this.i18n.missingFields || 'Missing'}: ${missing.join(', ')}`,
                    severity: 'error'
                };
            } else if (parsed.type !== 'service_account') {
                result = {
                    valid: false,
                    error: this.i18n.serviceAccountWrongType || 'This is not a service account JSON',
                    suggestion: this.i18n.downloadCorrectFile || 'Download the correct file from Google Cloud Console',
                    severity: 'error'
                };
            } else {
                result = {
                    valid: true,
                    info: {
                        email: parsed.client_email,
                        project: parsed.project_id,
                        type: parsed.type
                    },
                    message: this.i18n.validServiceAccount || 'Valid service account'
                };
            }
        } catch (e) {
            result = {
                valid: false,
                error: this.i18n.invalidJson || 'Invalid JSON format',
                suggestion: this.i18n.copyEntireFile || 'Copy the entire file content without modifications',
                severity: 'error'
            };
        }

        // Cache result
        this.cache.set(cacheKey, result);
        
        // Limit cache size
        if (this.cache.size > 10) {
            const firstKey = this.cache.keys().next().value;
            this.cache.delete(firstKey);
        }

        return result;
    }

    clearCache() {
        this.cache.clear();
    }
}
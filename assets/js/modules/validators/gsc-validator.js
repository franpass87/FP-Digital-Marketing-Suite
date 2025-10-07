/**
 * Google Search Console Site URL Validator
 */
export class GSCValidator {
    constructor(i18n = {}) {
        this.i18n = i18n;
    }

    validateSiteUrl(value) {
        const input = value.trim();

        if (!input) {
            return {
                valid: false,
                error: this.i18n.siteUrlRequired || 'Site URL is required',
                severity: 'error'
            };
        }

        // Check if it's a valid URL format
        let url;
        try {
            const urlToTest = input.startsWith('http') ? input : `https://${input}`;
            url = new URL(urlToTest);
        } catch (e) {
            return {
                valid: false,
                error: this.i18n.siteUrlInvalid || 'Invalid URL format',
                suggestion: this.i18n.siteUrlExample || 'Example: https://www.example.com',
                severity: 'error'
            };
        }

        // Suggest canonical format
        const canonical = url.protocol + '//' + url.hostname + (url.port ? ':' + url.port : '');
        
        if (canonical !== input && !input.startsWith('sc-domain:')) {
            return {
                valid: true,
                autoFormat: canonical,
                message: this.i18n.canonicalUrl || 'Suggested canonical format'
            };
        }

        return { valid: true };
    }
}
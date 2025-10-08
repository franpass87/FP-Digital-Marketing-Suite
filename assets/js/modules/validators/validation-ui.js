/**
 * Validation UI Helper
 * Gestisce il rendering dei risultati di validazione
 */
export class ValidationUI {
    /**
     * Update field UI with validation result.
     */
    static updateFieldUI(fieldElement, result) {
        const container = fieldElement.closest('.fpdms-field');
        if (!container) return;

        // Remove previous validation classes and messages
        container.classList.remove('fpdms-field--valid', 'fpdms-field--error', 'fpdms-field--warning');
        
        // Remove old messages efficiently
        const oldMessages = container.querySelectorAll('.fpdms-field-message, .fpdms-field-info, .fpdms-field-autoformat');
        oldMessages.forEach(el => el.remove());

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
            
            const message = result.error + (result.suggestion ? `<br><small>${result.suggestion}</small>` : '');
            this.showMessage(container, message, severity);
        }

        // Apply auto-format if available
        if (result.autoFormat && result.autoFormat !== fieldElement.value) {
            this.showAutoFormat(container, fieldElement, result.autoFormat);
        }
    }

    /**
     * Show validation message.
     * Uses textContent for security, but allows <br> and <small> tags via safe parsing.
     */
    static showMessage(container, message, type = 'error') {
        const messageEl = document.createElement('div');
        messageEl.className = `fpdms-field-message fpdms-field-message--${type}`;
        
        // Security: Parse and sanitize message instead of using innerHTML directly
        this._setMessageContent(messageEl, message);
        
        container.appendChild(messageEl);
    }
    
    /**
     * Safely set message content, allowing only <br> and <small> tags.
     */
    static _setMessageContent(element, message) {
        if (!message) return;
        
        // Split by <br> tag
        const parts = message.split(/<br\s*\/?>/i);
        
        parts.forEach((part, index) => {
            if (index > 0) {
                element.appendChild(document.createElement('br'));
            }
            
            // Check if part contains <small> tag
            const smallMatch = part.match(/^(.*?)<small>(.*?)<\/small>(.*)$/i);
            if (smallMatch) {
                if (smallMatch[1]) {
                    element.appendChild(document.createTextNode(smallMatch[1]));
                }
                const small = document.createElement('small');
                small.textContent = smallMatch[2];
                element.appendChild(small);
                if (smallMatch[3]) {
                    element.appendChild(document.createTextNode(smallMatch[3]));
                }
            } else {
                element.appendChild(document.createTextNode(part));
            }
        });
    }

    /**
     * Show service account info.
     */
    static showInfo(container, info) {
        const i18n = window.fpdmsI18n || {};
        const infoEl = document.createElement('div');
        infoEl.className = 'fpdms-field-info';
        infoEl.innerHTML = `
            <div class="fpdms-sa-info">
                <strong>✅ ${i18n.validatedInfo || 'Validated'}:</strong><br>
                📧 ${this._escapeHtml(info.email)}<br>
                📦 ${this._escapeHtml(info.project)}
            </div>
        `;
        container.appendChild(infoEl);
    }

    /**
     * Show auto-format suggestion.
     */
    static showAutoFormat(container, fieldElement, formattedValue) {
        const i18n = window.fpdmsI18n || {};
        const suggestion = document.createElement('div');
        suggestion.className = 'fpdms-field-autoformat';
        suggestion.innerHTML = `
            <span>${i18n.suggestedFormat || 'Suggested format'}: <code>${this._escapeHtml(formattedValue)}</code></span>
            <button type="button" class="fpdms-btn-apply-format button button-small">
                ${i18n.apply || 'Apply'}
            </button>
        `;
        
        suggestion.querySelector('.fpdms-btn-apply-format').addEventListener('click', () => {
            fieldElement.value = formattedValue;
            fieldElement.dispatchEvent(new Event('input', { bubbles: true }));
            suggestion.remove();
        }, { once: true });

        container.appendChild(suggestion);
    }

    /**
     * Show loading state during live test.
     */
    static showLoading(container, message = null) {
        const i18n = window.fpdmsI18n || {};
        const loader = document.createElement('div');
        loader.className = 'fpdms-loading';
        
        // Create spinner element
        const spinner = document.createElement('span');
        spinner.className = 'spinner is-active';
        
        // Create message element
        const messageEl = document.createElement('span');
        messageEl.textContent = message || i18n.testing || 'Testing connection...';
        
        loader.appendChild(spinner);
        loader.appendChild(messageEl);
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

    /**
     * Escape HTML to prevent XSS.
     */
    static _escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}
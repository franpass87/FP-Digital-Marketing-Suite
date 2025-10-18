/**
 * Wizard File Upload Handler
 * Gestisce l'upload di file JSON (service account)
 */
import { SELECTORS } from './constants.js';

// jQuery reference for ES6 modules
const $ = window.jQuery;

export class FileUploadHandler {
    constructor($container, onError) {
        this.$container = $container;
        this.onError = onError;
        this.handler = null;
    }

    init() {
        this.handler = (e) => {
            const file = e.target.files?.[0];
            if (!file) return;

            if (file.type !== 'application/json') {
                this.onError(window.fpdmsI18n?.selectJson || 'Please select a JSON file');
                return;
            }

            this._readFile(file);
        };

        this.$container.on('change', SELECTORS.FILE_INPUT, this.handler);
    }

    _readFile(file) {
        const reader = new FileReader();
        
        reader.onload = (event) => {
            const $textarea = this.$container.find(SELECTORS.SA_TEXTAREA);
            if ($textarea.length) {
                $textarea.val(event.target.result).trigger('input');
            }
            
            const $fileName = this.$container.find(SELECTORS.FILE_NAME_DISPLAY);
            if ($fileName.length) {
                $fileName.text(file.name);
            }
        };

        reader.onerror = () => {
            this.onError(window.fpdmsI18n?.fileReadError || 'Error reading file');
        };

        reader.readAsText(file);
    }

    cleanup() {
        if (this.handler) {
            this.$container.off('change', SELECTORS.FILE_INPUT, this.handler);
            this.handler = null;
        }
    }
}
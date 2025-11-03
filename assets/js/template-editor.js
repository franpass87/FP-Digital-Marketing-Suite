/**
 * Template Editor with Live Preview
 * Gestisce l'aggiornamento in tempo reale della preview del documento
 */
(function ($) {
    'use strict';

    const TemplateEditor = {
        // Configurazione
        config: {
            debounceDelay: 800, // ms prima di aggiornare la preview
            previewUrl: fpdmsTemplateEditor.ajaxUrl,
            nonce: fpdmsTemplateEditor.nonce,
        },

        // Cache elementi DOM
        cache: {
            nameInput: null,
            descInput: null,
            contentEditor: null,
            previewContainer: null,
            previewBody: null,
            clientSelector: null,
            refreshBtn: null,
        },

        // Timers
        debounceTimer: null,

        /**
         * Inizializza l'editor
         */
        init: function () {
            this.cacheElements();
            this.bindEvents();
            this.renderInitialPreview();
            console.log('✓ Template Editor con Live Preview inizializzato');
        },

        /**
         * Cache degli elementi DOM
         */
        cacheElements: function () {
            this.cache.nameInput = $('#fpdms-template-name');
            this.cache.descInput = $('#fpdms-template-description');
            this.cache.contentEditor = $('#fpdms-template-content');
            this.cache.previewContainer = $('#fpdms-template-preview-content');
            this.cache.previewBody = $('#fpdms-preview-body');
            this.cache.clientSelector = $('#fpdms-preview-client-id');
            this.cache.refreshBtn = $('#fpdms-preview-refresh');
        },

        /**
         * Bind event listeners
         */
        bindEvents: function () {
            const self = this;

            // Update preview quando cambiano i campi
            this.cache.nameInput.on('input', () => this.scheduleUpdate());
            this.cache.descInput.on('input', () => this.scheduleUpdate());

            // TinyMCE change event
            if (typeof tinymce !== 'undefined') {
                tinymce.on('AddEditor', function(e) {
                    if (e.editor.id === 'fpdms-template-content') {
                        e.editor.on('input change', function() {
                            self.scheduleUpdate();
                        });
                    }
                });
            }

            // Fallback per textarea
            this.cache.contentEditor.on('input', () => this.scheduleUpdate());

            // Client selector change
            this.cache.clientSelector.on('change', () => this.updatePreview());

            // Refresh button
            this.cache.refreshBtn.on('click', () => this.updatePreview(true));
        },

        /**
         * Programma l'aggiornamento con debounce
         */
        scheduleUpdate: function () {
            clearTimeout(this.debounceTimer);
            this.debounceTimer = setTimeout(() => {
                this.updatePreview();
            }, this.config.debounceDelay);
        },

        /**
         * Aggiorna la preview
         */
        updatePreview: function (force = false) {
            const content = this.getEditorContent();
            const name = this.cache.nameInput.val() || '';
            const clientId = this.cache.clientSelector.val() || '';

            // Non aggiornare se il contenuto è vuoto e non è forzato
            if (!force && !content.trim()) {
                this.showEmptyState();
                return;
            }

            this.showLoading();

            const data = {
                action: 'fpdms_preview_template',
                nonce: this.config.nonce,
                content: content,
                name: name,
                client_id: clientId,
            };

            $.ajax({
                url: this.config.previewUrl,
                type: 'POST',
                data: data,
                success: (response) => {
                    if (response.success) {
                        this.renderPreview(response.data);
                    } else {
                        this.showError(response.data?.message || 'Errore durante il rendering della preview');
                    }
                },
                error: (xhr, status, error) => {
                    console.error('Preview error:', error);
                    this.showError('Errore di connessione durante l\'aggiornamento della preview');
                },
            });
        },

        /**
         * Ottiene il contenuto dall'editor (TinyMCE o textarea)
         */
        getEditorContent: function () {
            if (typeof tinymce !== 'undefined') {
                const editor = tinymce.get('fpdms-template-content');
                if (editor) {
                    return editor.getContent();
                }
            }
            return this.cache.contentEditor.val() || '';
        },

        /**
         * Renderizza la preview
         */
        renderPreview: function (data) {
            const html = `
                <div class="fpdms-preview-document-header">
                    <div class="fpdms-preview-logo-container">
                        ${data.logo_html}
                    </div>
                    <div class="fpdms-preview-client-logo-container">
                        ${data.client_logo_html}
                    </div>
                </div>
                <div class="fpdms-preview-body">
                    ${data.rendered_content}
                </div>
                ${data.footer_html ? `<div class="fpdms-preview-footer">${data.footer_html}</div>` : ''}
            `;

            this.cache.previewBody.html(html);
            this.hideLoading();
        },

        /**
         * Mostra lo stato di caricamento
         */
        showLoading: function () {
            this.cache.refreshBtn.addClass('loading').prop('disabled', true);
            this.cache.previewBody.html(`
                <div class="fpdms-preview-loading">
                    <span class="spinner is-active"></span>
                    Aggiornamento preview...
                </div>
            `);
        },

        /**
         * Nasconde lo stato di caricamento
         */
        hideLoading: function () {
            this.cache.refreshBtn.removeClass('loading').prop('disabled', false);
        },

        /**
         * Mostra stato vuoto
         */
        showEmptyState: function () {
            this.cache.previewBody.html(`
                <div class="fpdms-preview-empty">
                    <span class="dashicons dashicons-media-document"></span>
                    <p>Inizia a scrivere il contenuto per vedere l'anteprima</p>
                </div>
            `);
        },

        /**
         * Mostra errore
         */
        showError: function (message) {
            this.hideLoading();
            this.cache.previewBody.html(`
                <div class="fpdms-preview-empty" style="color: #dc2626;">
                    <span class="dashicons dashicons-warning"></span>
                    <p><strong>Errore:</strong> ${message}</p>
                </div>
            `);
        },

        /**
         * Renderizza la preview iniziale
         */
        renderInitialPreview: function () {
            const content = this.getEditorContent();
            if (content.trim()) {
                this.updatePreview();
            } else {
                this.showEmptyState();
            }
        },
    };

    // Inizializza quando il documento è pronto
    $(document).ready(function () {
        // Verifica che siamo nella pagina giusta
        if ($('#fpdms-template-preview-content').length > 0) {
            TemplateEditor.init();
        }
    });

})(jQuery);


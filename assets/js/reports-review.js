/**
 * Reports Review - Interactive Management
 * Handles report review actions, AJAX submissions, and UI updates
 */

(function($) {
    'use strict';

    const ReportsReview = {
        currentReportId: null,

        /**
         * Initialize the review interface
         */
        init: function() {
            this.bindEvents();
            this.initTooltips();
            this.initEditor();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            const self = this;

            // Toggle review form
            $(document).on('click', '.fpdms-review-btn', function(e) {
                e.preventDefault();
                const reportId = $(this).data('report-id');
                self.toggleReviewForm(reportId);
            });

            // Cancel review
            $(document).on('click', '.fpdms-review-cancel', function(e) {
                e.preventDefault();
                const $row = $(this).closest('.fpdms-review-row');
                $row.hide();
            });

            // Handle quick actions (view PDF)
            $(document).on('click', '.fpdms-quick-view-pdf', function(e) {
                e.preventDefault();
                const url = $(this).attr('href');
                window.open(url, '_blank');
            });

            // Open content editor
            $(document).on('click', '.fpdms-edit-content-btn', function(e) {
                e.preventDefault();
                const reportId = $(this).data('report-id');
                self.openEditor(reportId);
            });

            // Editor modal controls
            $(document).on('click', '.fpdms-modal-close, #fpdms-editor-cancel', function(e) {
                e.preventDefault();
                self.closeEditor();
            });

            $(document).on('click', '.fpdms-modal-overlay', function(e) {
                self.closeEditor();
            });

            // Editor tabs
            $(document).on('click', '.fpdms-editor-tab', function(e) {
                e.preventDefault();
                const tab = $(this).data('tab');
                self.switchEditorTab(tab);
            });

            // Refresh preview
            $(document).on('click', '#fpdms-refresh-preview', function(e) {
                e.preventDefault();
                self.refreshPreview();
            });

            // Save editor
            $(document).on('click', '#fpdms-editor-save', function(e) {
                e.preventDefault();
                self.saveEditor();
            });

            // Optional: Keyboard shortcuts
            $(document).on('keydown', function(e) {
                // ESC to close review form or editor
                if (e.key === 'Escape') {
                    if ($('#fpdms-editor-modal').is(':visible')) {
                        self.closeEditor();
                    } else {
                        $('.fpdms-review-row:visible').hide();
                    }
                }
            });
        },

        /**
         * Toggle review form visibility
         */
        toggleReviewForm: function(reportId) {
            const $reviewRow = $('#review-row-' + reportId);
            
            // Close other open review forms
            $('.fpdms-review-row').not($reviewRow).hide();
            
            // Toggle current form
            $reviewRow.toggle();
            
            // Focus on notes textarea if opening
            if ($reviewRow.is(':visible')) {
                $reviewRow.find('textarea').focus();
            }
        },

        /**
         * Initialize tooltips (if needed)
         */
        initTooltips: function() {
            // Add title attributes for better accessibility
            $('.fpdms-review-btn').attr('title', fpdmsReports.i18n.reviewNotesPlaceholder || 'Review');
        },

        /**
         * Update report row after review action
         */
        updateReportRow: function(reportId, data) {
            const $row = $('.fpdms-report-row[data-report-id="' + reportId + '"]');
            
            if (data.review_status) {
                const statusLabel = this.getReviewStatusLabel(data.review_status);
                const statusClass = 'fpdms-review-' + data.review_status.replace('_', '-');
                
                $row.find('.fpdms-review-badge')
                    .removeClass('fpdms-review-pending fpdms-review-in-review fpdms-review-approved fpdms-review-rejected')
                    .addClass(statusClass)
                    .text(statusLabel);
            }
            
            // Add success animation
            $row.addClass('fpdms-row-updated');
            setTimeout(function() {
                $row.removeClass('fpdms-row-updated');
            }, 2000);
        },

        /**
         * Get review status label
         */
        getReviewStatusLabel: function(status) {
            const labels = {
                'pending': 'Da rivedere',
                'in_review': 'In revisione',
                'approved': 'Approvato',
                'rejected': 'Rigettato'
            };
            return labels[status] || status;
        },

        /**
         * Show notification message
         */
        showNotice: function(message, type) {
            type = type || 'success';
            
            const $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            
            $('.fpdms-reports-page .fpdms-page-header').after($notice);
            
            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
            
            // Manual dismiss
            $notice.find('.notice-dismiss').on('click', function() {
                $notice.remove();
            });
            
            // Scroll to notice
            $('html, body').animate({
                scrollTop: $notice.offset().top - 100
            }, 300);
        },

        /**
         * Show loading state
         */
        showLoading: function($element) {
            $element.addClass('is-loading').prop('disabled', true);
            $element.find('.dashicons').addClass('dashicons-update spin');
        },

        /**
         * Hide loading state
         */
        hideLoading: function($element) {
            $element.removeClass('is-loading').prop('disabled', false);
            $element.find('.dashicons').removeClass('dashicons-update spin');
        },

        /**
         * Initialize editor
         */
        initEditor: function() {
            // Setup will be done when modal opens
        },

        /**
         * Open content editor modal
         */
        openEditor: function(reportId) {
            const self = this;
            self.currentReportId = reportId;

            // Show modal
            $('#fpdms-editor-modal').fadeIn(300);
            $('body').addClass('fpdms-modal-open');

            // Load HTML content
            self.loadReportHtml(reportId);
        },

        /**
         * Close editor modal
         */
        closeEditor: function() {
            $('#fpdms-editor-modal').fadeOut(300);
            $('body').removeClass('fpdms-modal-open');
            this.currentReportId = null;

            // Clear editors
            if (typeof tinymce !== 'undefined' && tinymce.get('fpdms_report_content')) {
                tinymce.get('fpdms_report_content').setContent('');
            }
            $('#fpdms-html-editor').val('');
            $('#fpdms-preview-container').html('<p class="fpdms-preview-placeholder">Clicca "Aggiorna Anteprima" per visualizzare il report.</p>');
        },

        /**
         * Switch editor tab
         */
        switchEditorTab: function(tab) {
            // Update tab buttons
            $('.fpdms-editor-tab').removeClass('active');
            $('.fpdms-editor-tab[data-tab="' + tab + '"]').addClass('active');

            // Update content panes
            $('.fpdms-editor-pane').removeClass('active');
            $('.fpdms-editor-pane[data-pane="' + tab + '"]').addClass('active');

            // Sync content when switching
            if (tab === 'html') {
                // Get content from TinyMCE and put in HTML editor
                if (typeof tinymce !== 'undefined' && tinymce.get('fpdms_report_content')) {
                    const content = tinymce.get('fpdms_report_content').getContent();
                    $('#fpdms-html-editor').val(content);
                }
            } else if (tab === 'visual') {
                // Get content from HTML editor and put in TinyMCE
                const htmlContent = $('#fpdms-html-editor').val();
                if (typeof tinymce !== 'undefined' && tinymce.get('fpdms_report_content')) {
                    tinymce.get('fpdms_report_content').setContent(htmlContent);
                }
            }
        },

        /**
         * Load report HTML via AJAX
         */
        loadReportHtml: function(reportId) {
            const self = this;

            $.ajax({
                url: fpdmsReports.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'fpdms_load_report_html',
                    nonce: fpdmsReports.nonce,
                    report_id: reportId
                },
                beforeSend: function() {
                    $('#fpdms-editor-save').prop('disabled', true).text('Caricamento...');
                },
                success: function(response) {
                    if (response.success) {
                        const html = response.data.html;

                        // Set in TinyMCE
                        if (typeof tinymce !== 'undefined' && tinymce.get('fpdms_report_content')) {
                            tinymce.get('fpdms_report_content').setContent(html);
                        }

                        // Set in HTML editor
                        $('#fpdms-html-editor').val(html);

                        $('#fpdms-editing-report-id').val(reportId);
                        $('#fpdms-editor-save').prop('disabled', false).html('<span class="dashicons dashicons-saved"></span> Salva e Rigenera PDF');

                        self.showNotice('Contenuto caricato con successo.', 'success');
                    } else {
                        self.showNotice(response.data.message || 'Errore nel caricamento del contenuto.', 'error');
                        self.closeEditor();
                    }
                },
                error: function() {
                    self.showNotice('Errore di connessione durante il caricamento.', 'error');
                    self.closeEditor();
                }
            });
        },

        /**
         * Save edited HTML and regenerate PDF
         */
        saveEditor: function() {
            const self = this;
            const reportId = $('#fpdms-editing-report-id').val();

            if (!reportId) {
                self.showNotice('ID report non valido.', 'error');
                return;
            }

            // Get content from active editor
            let htmlContent = '';
            const activeTab = $('.fpdms-editor-tab.active').data('tab');

            if (activeTab === 'visual') {
                if (typeof tinymce !== 'undefined' && tinymce.get('fpdms_report_content')) {
                    htmlContent = tinymce.get('fpdms_report_content').getContent();
                }
            } else {
                htmlContent = $('#fpdms-html-editor').val();
            }

            if (!htmlContent.trim()) {
                self.showNotice('Il contenuto non può essere vuoto.', 'error');
                return;
            }

            const $saveBtn = $('#fpdms-editor-save');

            $.ajax({
                url: fpdmsReports.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'fpdms_save_report_html',
                    nonce: fpdmsReports.nonce,
                    report_id: reportId,
                    html_content: htmlContent
                },
                beforeSend: function() {
                    self.showLoading($saveBtn);
                    $saveBtn.text('Salvataggio in corso...');
                },
                success: function(response) {
                    self.hideLoading($saveBtn);
                    $saveBtn.html('<span class="dashicons dashicons-saved"></span> Salva e Rigenera PDF');

                    if (response.success) {
                        self.showNotice(response.data.message, 'success');
                        self.closeEditor();

                        // Optional: reload page to show updated report
                        setTimeout(function() {
                            window.location.reload();
                        }, 1500);
                    } else {
                        self.showNotice(response.data.message || 'Errore durante il salvataggio.', 'error');
                    }
                },
                error: function() {
                    self.hideLoading($saveBtn);
                    $saveBtn.html('<span class="dashicons dashicons-saved"></span> Salva e Rigenera PDF');
                    self.showNotice('Errore di connessione durante il salvataggio.', 'error');
                }
            });
        },

        /**
         * Refresh HTML preview
         */
        refreshPreview: function() {
            let htmlContent = '';
            const activeTab = $('.fpdms-editor-tab.active').data('tab');

            // Get content from current active editor
            if (activeTab === 'visual' || activeTab === 'preview') {
                if (typeof tinymce !== 'undefined' && tinymce.get('fpdms_report_content')) {
                    htmlContent = tinymce.get('fpdms_report_content').getContent();
                }
            } else if (activeTab === 'html') {
                htmlContent = $('#fpdms-html-editor').val();
            }

            // If switching to preview from html, get from html editor
            const wasOnHtml = $('.fpdms-editor-pane[data-pane="html"]').hasClass('active');
            if (wasOnHtml) {
                htmlContent = $('#fpdms-html-editor').val();
            }

            // Display in preview container
            $('#fpdms-preview-container').html(htmlContent);
        }
    };

    // Initialize when DOM is ready
    $(document).ready(function() {
        ReportsReview.init();
    });

    // Make it globally accessible if needed
    window.FPDMSReportsReview = ReportsReview;

})(jQuery);

// Add CSS for spin animation
(function() {
    const style = document.createElement('style');
    style.textContent = `
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        .dashicons.spin {
            animation: spin 1s linear infinite;
        }
        .fpdms-row-updated {
            animation: highlight 2s ease;
        }
        @keyframes highlight {
            0%, 100% { background-color: transparent; }
            50% { background-color: #d1e7dd; }
        }
    `;
    document.head.appendChild(style);
})();


/**
 * FP Digital Marketing Suite - Keyboard Shortcuts & Accessibility
 * Enhanced navigation and accessibility features
 */

(function($) {
    'use strict';

    /**
     * Keyboard shortcuts manager
     */
    window.FP_DMS_Shortcuts = {
        
        /**
         * Initialize keyboard shortcuts
         */
        init: function() {
            $(document).on('keydown', this.handleKeyboardShortcuts.bind(this));
            this.addShortcutsHelp();
        },

        /**
         * Handle keyboard shortcuts
         */
        handleKeyboardShortcuts: function(e) {
            // Don't trigger shortcuts when typing in form fields
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.isContentEditable) {
                return;
            }

            // Alt + key combinations for navigation
            if (e.altKey && !e.ctrlKey && !e.shiftKey) {
                switch(e.key.toLowerCase()) {
                    case 'd':
                        e.preventDefault();
                        this.navigateTo('fp-digital-marketing-dashboard');
                        break;
                    case 'r':
                        e.preventDefault();
                        this.navigateTo('fp-digital-marketing-reports');
                        break;
                    case 'c':
                        e.preventDefault();
                        this.navigateTo('fp-digital-marketing-cache-performance');
                        break;
                    case 's':
                        e.preventDefault();
                        this.navigateTo('fp-digital-marketing-settings');
                        break;
                    case 'h':
                        e.preventDefault();
                        this.showShortcutsHelp();
                        break;
                }
            }

            // Ctrl + key combinations for actions
            if (e.ctrlKey && !e.altKey && !e.shiftKey) {
                switch(e.key.toLowerCase()) {
                    case 'r':
                        // Prevent default browser refresh, use our refresh
                        if (this.isOnFPDMSPage()) {
                            e.preventDefault();
                            this.refreshCurrentPage();
                        }
                        break;
                    case 's':
                        // Enhanced save with auto-save
                        if (this.isOnFPDMSPage()) {
                            e.preventDefault();
                            this.saveCurrentForm();
                        }
                        break;
                }
            }

            // Escape key handling
            if (e.key === 'Escape') {
                this.handleEscape();
            }
        },

        /**
         * Navigate to a specific page
         */
        navigateTo: function(page) {
            const url = ajaxurl.replace('/admin-ajax.php', '/admin.php?page=' + page);
            FP_DMS_Optimizations.announceToScreenReader('Navigating to ' + page.replace(/-/g, ' '));
            window.location.href = url;
        },

        /**
         * Check if we're on an FP DMS page
         */
        isOnFPDMSPage: function() {
            return window.location.href.includes('fp-digital-marketing') ||
                   window.location.href.includes('fp-digital-marketing-cache-performance') ||
                   window.location.href.includes('fp-segmentation') ||
                   window.location.href.includes('fp-utm-campaign');
        },

        /**
         * Refresh current page with loading indicator
         */
        refreshCurrentPage: function() {
            FP_DMS_Optimizations.showNotification('Refreshing page...', 'info', 2000);
            FP_DMS_Optimizations.announceToScreenReader('Refreshing page');
            
            // Add smooth refresh with fade effect
            $('body').fadeOut(200, function() {
                window.location.reload();
            });
        },

        /**
         * Save current form
         */
        saveCurrentForm: function() {
            const forms = $('form.fp-dms-autosave, form[data-autosave="true"]');
            
            if (forms.length === 0) {
                FP_DMS_Optimizations.showNotification('No saveable form found on this page', 'warning', 3000);
                return;
            }

            forms.each(function() {
                const form = $(this);
                const submitButton = form.find('input[type="submit"], button[type="submit"]').first();
                
                if (submitButton.length) {
                    FP_DMS_Optimizations.announceToScreenReader('Saving form');
                    submitButton.click();
                } else {
                    // Trigger form save event for custom handling
                    form.trigger('fp-dms-save');
                }
            });
        },

        /**
         * Handle escape key
         */
        handleEscape: function() {
            // Close any open modals or overlays
            $('.fp-dms-modal, .fp-dms-overlay').fadeOut(200);
            
            // Dismiss notifications
            $('.fp-dms-message-dismissible .fp-dms-message-dismiss').click();
            
            // Close shortcuts help if open
            $('#fp-dms-shortcuts-help').fadeOut(200);
        },

        /**
         * Show shortcuts help modal
         */
        showShortcutsHelp: function() {
            const helpModal = this.getShortcutsHelpModal();
            helpModal.fadeIn(200);
            helpModal.find('.fp-dms-shortcuts-content').focus();
        },

        /**
         * Get or create shortcuts help modal
         */
        getShortcutsHelpModal: function() {
            let modal = $('#fp-dms-shortcuts-help');
            
            if (modal.length === 0) {
                modal = $(`
                    <div id="fp-dms-shortcuts-help" class="fp-dms-modal" style="display: none;" role="dialog" aria-labelledby="shortcuts-title" aria-modal="true">
                        <div class="fp-dms-modal-overlay" aria-hidden="true"></div>
                        <div class="fp-dms-modal-content fp-dms-shortcuts-content" tabindex="-1">
                            <div class="fp-dms-modal-header">
                                <h2 id="shortcuts-title">Keyboard Shortcuts</h2>
                                <button class="fp-dms-modal-close" aria-label="Close shortcuts help">&times;</button>
                            </div>
                            <div class="fp-dms-modal-body">
                                <div class="fp-dms-shortcuts-grid">
                                    <div class="fp-dms-shortcut-section">
                                        <h3>Navigation</h3>
                                        <div class="fp-dms-shortcut">
                                            <kbd>Alt + D</kbd>
                                            <span>Dashboard</span>
                                        </div>
                                        <div class="fp-dms-shortcut">
                                            <kbd>Alt + R</kbd>
                                            <span>Reports</span>
                                        </div>
                                        <div class="fp-dms-shortcut">
                                            <kbd>Alt + C</kbd>
                                            <span>Cache Performance</span>
                                        </div>
                                        <div class="fp-dms-shortcut">
                                            <kbd>Alt + S</kbd>
                                            <span>Settings</span>
                                        </div>
                                    </div>
                                    <div class="fp-dms-shortcut-section">
                                        <h3>Actions</h3>
                                        <div class="fp-dms-shortcut">
                                            <kbd>Ctrl + S</kbd>
                                            <span>Save Form</span>
                                        </div>
                                        <div class="fp-dms-shortcut">
                                            <kbd>Ctrl + R</kbd>
                                            <span>Refresh Page</span>
                                        </div>
                                        <div class="fp-dms-shortcut">
                                            <kbd>Esc</kbd>
                                            <span>Close Modals</span>
                                        </div>
                                        <div class="fp-dms-shortcut">
                                            <kbd>Alt + H</kbd>
                                            <span>Show This Help</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `);
                
                $('body').append(modal);
                
                // Add close handlers
                modal.find('.fp-dms-modal-close, .fp-dms-modal-overlay').on('click', function() {
                    modal.fadeOut(200);
                });
                
                // Trap focus within modal
                modal.on('keydown', function(e) {
                    if (e.key === 'Tab') {
                        const focusable = modal.find('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
                        const first = focusable.first();
                        const last = focusable.last();
                        
                        if (e.shiftKey && e.target === first[0]) {
                            e.preventDefault();
                            last.focus();
                        } else if (!e.shiftKey && e.target === last[0]) {
                            e.preventDefault();
                            first.focus();
                        }
                    }
                });
            }
            
            return modal;
        },

        /**
         * Add shortcuts help indicator
         */
        addShortcutsHelp: function() {
            if (!this.isOnFPDMSPage()) {
                return;
            }

            const helpIndicator = $(`
                <div class="fp-dms-shortcuts-indicator" title="Press Alt+H for keyboard shortcuts">
                    <span>⌨️</span>
                </div>
            `);
            
            helpIndicator.on('click', this.showShortcutsHelp.bind(this));
            $('body').append(helpIndicator);
        }
    };

    /**
     * Enhanced focus management
     */
    window.FP_DMS_Focus = {
        
        /**
         * Initialize focus management
         */
        init: function() {
            this.enhanceFocusIndicators();
            this.addFocusTrap();
            this.improveTabNavigation();
        },

        /**
         * Enhance focus indicators
         */
        enhanceFocusIndicators: function() {
            // Add focus classes for better styling
            $(document).on('focusin', 'input, select, textarea, button, [tabindex]', function() {
                $(this).addClass('fp-dms-focused');
            });
            
            $(document).on('focusout', 'input, select, textarea, button, [tabindex]', function() {
                $(this).removeClass('fp-dms-focused');
            });
        },

        /**
         * Add focus trap for modals
         */
        addFocusTrap: function() {
            $(document).on('keydown', '.fp-dms-modal', function(e) {
                if (e.key === 'Tab') {
                    const modal = $(this);
                    const focusable = modal.find('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])').filter(':visible');
                    const first = focusable.first();
                    const last = focusable.last();
                    
                    if (focusable.length === 0) return;
                    
                    if (e.shiftKey) {
                        if (e.target === first[0]) {
                            e.preventDefault();
                            last.focus();
                        }
                    } else {
                        if (e.target === last[0]) {
                            e.preventDefault();
                            first.focus();
                        }
                    }
                }
            });
        },

        /**
         * Improve tab navigation
         */
        improveTabNavigation: function() {
            // Skip to main content functionality
            $(document).on('keydown', function(e) {
                if (e.key === 'Tab' && !e.shiftKey && e.target === document.body) {
                    const skipLink = $('.fp-dms-skip-link').first();
                    if (skipLink.length) {
                        e.preventDefault();
                        skipLink.focus();
                    }
                }
            });
            
            // Enhanced skip link behavior
            $(document).on('click', '.fp-dms-skip-link', function(e) {
                e.preventDefault();
                const target = $($(this).attr('href'));
                if (target.length) {
                    target.attr('tabindex', '-1').focus();
                    FP_DMS_Optimizations.announceToScreenReader('Skipped to main content');
                }
            });
        },

        /**
         * Move focus to element
         */
        moveFocusTo: function(selector) {
            const element = $(selector).first();
            if (element.length) {
                element.attr('tabindex', '-1').focus();
                return true;
            }
            return false;
        }
    };

    /**
     * Initialize when document is ready
     */
    $(document).ready(function() {
        FP_DMS_Shortcuts.init();
        FP_DMS_Focus.init();
        
        // Add global CSS for shortcuts and focus
        $('<style>').text(`
            .fp-dms-shortcuts-indicator {
                position: fixed;
                bottom: 20px;
                right: 20px;
                background: #0073aa;
                color: white;
                width: 40px;
                height: 40px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                z-index: 9999;
                transition: all 0.3s ease;
                box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            }
            
            .fp-dms-shortcuts-indicator:hover {
                background: #005a87;
                transform: scale(1.1);
            }
            
            .fp-dms-modal {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                z-index: 10000;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .fp-dms-modal-overlay {
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0,0,0,0.7);
                backdrop-filter: blur(3px);
            }
            
            .fp-dms-modal-content {
                position: relative;
                background: white;
                border-radius: 8px;
                max-width: 600px;
                max-height: 80vh;
                overflow-y: auto;
                box-shadow: 0 10px 30px rgba(0,0,0,0.3);
                margin: 20px;
            }
            
            .fp-dms-modal-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 20px 20px 0 20px;
                border-bottom: 1px solid #ddd;
                margin-bottom: 20px;
            }
            
            .fp-dms-modal-header h2 {
                margin: 0;
                color: #0073aa;
            }
            
            .fp-dms-modal-close {
                background: none;
                border: none;
                font-size: 24px;
                cursor: pointer;
                padding: 0;
                color: #666;
                width: 30px;
                height: 30px;
                display: flex;
                align-items: center;
                justify-content: center;
                border-radius: 50%;
                transition: all 0.2s ease;
            }
            
            .fp-dms-modal-close:hover {
                background: #f0f0f0;
                color: #000;
            }
            
            .fp-dms-modal-body {
                padding: 0 20px 20px 20px;
            }
            
            .fp-dms-shortcuts-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 30px;
            }
            
            .fp-dms-shortcut-section h3 {
                margin: 0 0 15px 0;
                color: #333;
                font-size: 16px;
            }
            
            .fp-dms-shortcut {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 8px 0;
                border-bottom: 1px solid #f0f0f0;
            }
            
            .fp-dms-shortcut:last-child {
                border-bottom: none;
            }
            
            .fp-dms-shortcut kbd {
                background: #f8f9fa;
                border: 1px solid #dee2e6;
                border-radius: 4px;
                color: #495057;
                font-family: monospace;
                font-size: 12px;
                padding: 4px 8px;
                box-shadow: 0 1px 0 rgba(0,0,0,0.1);
            }
            
            .fp-dms-focused {
                outline: 3px solid #005fcc !important;
                outline-offset: 2px !important;
            }
            
            @media (max-width: 600px) {
                .fp-dms-shortcuts-grid {
                    grid-template-columns: 1fr;
                }
                
                .fp-dms-modal-content {
                    margin: 10px;
                    max-height: 90vh;
                }
            }
        `).appendTo('head');
    });

})(jQuery);
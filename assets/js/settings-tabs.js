/**
 * FP Digital Marketing Suite - Settings Page Tabs JavaScript
 */

(function($) {
    'use strict';

    class FPSettingsTabs {
        constructor() {
            this.activeTab = 'general';
            this.sections = [];
            this.init();
        }

        init() {
            this.applyPageBodyClass();
            this.restoreActiveTab();
            this.createTabInterface();
            this.bindEvents();
            this.initCollapsibles();
            this.initTooltips();
        }

        createTabInterface() {
            const $form = $('#fp-dms-settings-form');
            if (!$form.length) return;

            const $wrap = $form.closest('.wrap');
            if ($wrap.find('.fp-dms-settings-tabs').length) {
                return;
            }

            const $sectionsContainer = $form.find('.wp-settings-sections').first();
            if (!$sectionsContainer.length) return;

            const sections = this.getSections();
            if (sections.length === 0) return;

            const sectionElementsMap = {};
            const availableSections = [];

            sections.forEach(section => {
                if (!section.wpSectionId) {
                    return;
                }

                const $heading = $sectionsContainer.find(`h2#${section.wpSectionId}, h3#${section.wpSectionId}`);
                if (!$heading.length) {
                    return;
                }

                const $sectionElements = $heading.nextUntil('h2, h3').addBack();
                if (!$sectionElements.length) {
                    return;
                }

                sectionElementsMap[section.id] = $sectionElements;
                availableSections.push(section);
            });

            if (!availableSections.length) {
                return;
            }

            this.sections = availableSections;

            if (!sectionElementsMap[this.activeTab]) {
                this.activeTab = availableSections[0].id;
            }

            const $tabNav = this.createTabNavigation(availableSections);
            const $tabContent = $('<div class="fp-dms-tab-content"></div>');

            availableSections.forEach(section => {
                const $panel = $(`<div class="fp-dms-tab-panel" data-tab="${section.id}"></div>`);

                $panel.append(this.createSectionHeader(section));

                const $sectionElements = sectionElementsMap[section.id];
                if ($sectionElements && $sectionElements.length) {
                    $panel.append($sectionElements);
                }

                $tabContent.append($panel);
            });

            const $pageTitle = $wrap.find('> h1').first();
            if ($pageTitle.length) {
                $pageTitle.after($tabNav);
            } else {
                $form.before($tabNav);
            }

            const $notices = $wrap.children('.notice, .settings-error');
            $sectionsContainer.before($tabContent);

            if ($notices.length) {
                $tabContent.before($notices);
            }

            $sectionsContainer.hide();

            this.showTab(this.activeTab);
        }

        createTabNavigation(sections) {
            const $nav = $('<div class="fp-dms-settings-tabs"><div class="fp-dms-tab-nav"></div></div>');
            const $navContainer = $nav.find('.fp-dms-tab-nav');

            sections.forEach(section => {
                const $button = $(`
                    <button type="button" class="fp-dms-tab-button" data-tab="${section.id}">
                        <span class="dashicons ${section.icon}"></span>
                        ${section.title}
                    </button>
                `);
                $navContainer.append($button);
            });

            return $nav;
        }

        createSectionHeader(section) {
            return $(`
                <div class="fp-dms-section-header">
                    <h3>${section.title}</h3>
                    <p>${section.description}</p>
                </div>
            `);
        }

        getSections() {
            // Define sections based on the WordPress settings sections
            return [
                {
                    id: 'general',
                    title: 'Configurazione Generale',
                    description: 'Impostazioni di base e configurazioni generali del plugin.',
                    icon: 'dashicons-admin-generic',
                    wpSectionId: 'fp_digital_marketing_general'
                },
                {
                    id: 'api-keys',
                    title: 'Chiavi API',
                    description: 'Configurazione delle chiavi API per servizi esterni come Google Analytics, Search Console e altri.',
                    icon: 'dashicons-admin-network',
                    wpSectionId: 'fp_digital_marketing_api_keys'
                },
                {
                    id: 'sync',
                    title: 'Sincronizzazione',
                    description: 'Configurazione della sincronizzazione automatica dei dati e pianificazione degli aggiornamenti.',
                    icon: 'dashicons-update',
                    wpSectionId: 'fp_digital_marketing_sync'
                },
                {
                    id: 'cache',
                    title: 'Performance & Cache',
                    description: 'Configurazione del sistema di caching per migliorare le performance delle query sui report.',
                    icon: 'dashicons-performance',
                    wpSectionId: 'fp_digital_marketing_cache'
                },
                {
                    id: 'seo',
                    title: 'SEO & Social Media',
                    description: 'Configurazione delle impostazioni SEO predefinite e template per meta tag.',
                    icon: 'dashicons-search',
                    wpSectionId: 'fp_digital_marketing_seo'
                },
                {
                    id: 'sitemap',
                    title: 'XML Sitemap',
                    description: 'Configurazione della generazione di sitemap XML modulari per migliorare l\'indicizzazione del sito.',
                    icon: 'dashicons-networking',
                    wpSectionId: 'fp_digital_marketing_sitemap'
                },
                {
                    id: 'schema',
                    title: 'Schema Markup',
                    description: 'Configurazione del markup Schema.org per migliorare la struttura dei dati del sito.',
                    icon: 'dashicons-editor-code',
                    wpSectionId: 'fp_digital_marketing_schema'
                },
                {
                    id: 'email',
                    title: 'Notifiche Email',
                    description: 'Configurazione delle notifiche email per alert, report e aggiornamenti del sistema.',
                    icon: 'dashicons-email-alt',
                    wpSectionId: 'fp_digital_marketing_email'
                }
            ];
        }

        bindEvents() {
            // Tab navigation
            $(document).on('click', '.fp-dms-tab-button', (e) => {
                e.preventDefault();
                const tab = $(e.currentTarget).data('tab');
                this.showTab(tab);
            });

            // Save active tab to localStorage
            $(document).on('click', '.fp-dms-tab-button', (e) => {
                const tab = $(e.currentTarget).data('tab');
                localStorage.setItem('fp_dms_active_tab', tab);
            });

            // Reset current tab fields
            $(document).on('click', '#fp-dms-reset-tab', (e) => {
                e.preventDefault();
                this.resetActiveTabFields();
            });

            // Collapsible sections
            $(document).on('click', '.fp-dms-collapsible-header', (e) => {
                const $collapsible = $(e.currentTarget).closest('.fp-dms-collapsible');
                $collapsible.toggleClass('expanded');
            });
        }

        showTab(tabId) {
            if (this.sections.length) {
                const hasTab = this.sections.some(section => section.id === tabId);
                if (!hasTab) {
                    tabId = this.sections[0].id;
                }
            }

            // Update navigation
            $('.fp-dms-tab-button').removeClass('active');
            $(`.fp-dms-tab-button[data-tab="${tabId}"]`).addClass('active');

            // Update content
            $('.fp-dms-tab-panel').removeClass('active');
            $(`.fp-dms-tab-panel[data-tab="${tabId}"]`).addClass('active');

            this.activeTab = tabId;

            // Trigger custom event
            $(document).trigger('fp_dms_tab_changed', [tabId]);
        }

        restoreActiveTab() {
            const savedTab = localStorage.getItem('fp_dms_active_tab');
            if (savedTab) {
                this.activeTab = savedTab;
            }
        }

        initCollapsibles() {
            // Convert complex form sections to collapsibles
            $('.form-table').each(function() {
                const $table = $(this);
                const rows = $table.find('tr').length;
                
                // If table has more than 5 rows, make it collapsible
                if (rows > 5) {
                    const $header = $table.prev('h3, h4');
                    if ($header.length) {
                        const title = $header.text();
                        $header.remove();
                        
                        const $collapsible = $(`
                            <div class="fp-dms-collapsible expanded">
                                <div class="fp-dms-collapsible-header">
                                    <h4>${title}</h4>
                                    <span class="dashicons dashicons-arrow-down-alt2"></span>
                                </div>
                                <div class="fp-dms-collapsible-content"></div>
                            </div>
                        `);
                        
                        $collapsible.find('.fp-dms-collapsible-content').append($table);
                        $table.after($collapsible);
                    }
                }
            });
        }

        initTooltips() {
            // Add help tooltips to complex settings
            const tooltips = {
                'google_client_id': 'Ottieni questo ID dalla Google Cloud Console creando un progetto OAuth 2.0.',
                'cache_enabled': 'Il caching migliora significativamente le performance riducendo il carico del database.',
                'ping_search_engines': 'Le notifiche automatiche accelerano l\'indicizzazione dei nuovi contenuti.',
                'auto_generate_descriptions': 'Le descrizioni automatiche vengono generate dal contenuto della pagina se non specificate manualmente.'
            };

            Object.keys(tooltips).forEach(fieldId => {
                const $field = $(`#${fieldId}`);
                if ($field.length) {
                    const $label = $field.closest('tr').find('th label');
                    if ($label.length) {
                        $label.append(`
                            <span class="fp-dms-help-tooltip">
                                <span class="dashicons dashicons-editor-help"></span>
                                <div class="tooltip-content">${tooltips[fieldId]}</div>
                            </span>
                        `);
                    }
                }
            });
        }

        applyPageBodyClass() {
            if (document && document.body) {
                document.body.classList.add('settings_page_fp-digital-marketing-settings');
            }
        }

        resetActiveTabFields() {
            if (typeof fpDmsSettings !== 'undefined' && fpDmsSettings.strings && !window.confirm(fpDmsSettings.strings.confirmReset)) {
                return;
            }

            const $activePanel = $('.fp-dms-tab-panel.active, .fp-dms-tab-panel.is-active');
            if (!$activePanel.length) {
                return;
            }

            $activePanel.find('input, select, textarea').each(function() {
                const $field = $(this);
                if ($field.is(':checkbox')) {
                    $field.prop('checked', $field.data('default') || false);
                } else {
                    $field.val($field.data('default') || '');
                }
            });

            if (typeof fpDmsShowMessage === 'function') {
                const message = (fpDmsSettings && fpDmsSettings.strings && fpDmsSettings.strings.resetNotice)
                    ? fpDmsSettings.strings.resetNotice
                    : 'Tab ripristinato.';
                fpDmsShowMessage(message, 'warning');
            }
        }

        // Helper method to show status messages
        showMessage(message, type = 'success') {
            const $message = $(`
                <div class="fp-dms-message ${type}">
                    ${message}
                </div>
            `);
            
            $('.fp-dms-tab-content').prepend($message);
            
            // Auto-hide after 5 seconds
            setTimeout(() => {
                $message.fadeOut(() => $message.remove());
            }, 5000);
        }

        // Helper method to show loading state
        setLoading(element, loading = true) {
            const $element = $(element);
            if (loading) {
                $element.addClass('fp-dms-loading');
            } else {
                $element.removeClass('fp-dms-loading');
            }
        }
    }

    // Initialize when DOM is ready
    $(document).ready(() => {
        if ($('#fp-dms-settings-form').length) {
            window.fpSettingsTabs = new FPSettingsTabs();
        }
    });

    // Global helper functions
    window.fpDmsShowMessage = function(message, type = 'success') {
        if (window.fpSettingsTabs) {
            window.fpSettingsTabs.showMessage(message, type);
        }
    };

    window.fpDmsSetLoading = function(element, loading = true) {
        if (window.fpSettingsTabs) {
            window.fpSettingsTabs.setLoading(element, loading);
        }
    };

})(jQuery);
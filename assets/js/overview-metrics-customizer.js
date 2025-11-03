/**
 * Overview Metrics Customizer
 * Gestisce la personalizzazione delle metriche visibili
 */
(function() {
    'use strict';

    const STORAGE_KEY = 'fpdms_visible_metrics';
    const STORAGE_PRESET_KEY = 'fpdms_business_preset';
    const DEFAULT_METRICS = ['users', 'sessions', 'clicks', 'impressions', 'cost', 'conversions', 'revenue', 'gsc_clicks', 'gsc_impressions'];
    
    // Preset per tipo di business
    const BUSINESS_PRESETS = {
        'bnb': {
            name: 'B&B / Affittacamere',
            metrics: ['users', 'sessions', 'pageviews', 'gsc_clicks', 'gsc_impressions', 'google_conversions', 'meta_conversions', 'revenue']
        },
        'hotel': {
            name: 'Hotel / Resort',
            metrics: ['users', 'sessions', 'pageviews', 'gsc_clicks', 'gsc_impressions', 'google_clicks', 'google_cost', 'meta_clicks', 'meta_cost', 'revenue']
        },
        'winery': {
            name: 'Cantina / Azienda Vinicola',
            metrics: ['users', 'sessions', 'gsc_clicks', 'gsc_impressions', 'position', 'events', 'revenue']
        },
        'restaurant': {
            name: 'Ristorante / Agriturismo',
            metrics: ['users', 'sessions', 'gsc_clicks', 'gsc_impressions', 'ctr', 'google_conversions', 'meta_conversions']
        },
        'ecommerce': {
            name: 'E-commerce / Shop Online',
            metrics: ['sessions', 'pageviews', 'new_users', 'google_clicks', 'google_cost', 'meta_clicks', 'meta_cost', 'google_conversions', 'meta_conversions', 'revenue', 'meta_revenue']
        },
        'leadgen': {
            name: 'Lead Generation / Servizi',
            metrics: ['users', 'new_users', 'gsc_clicks', 'gsc_impressions', 'google_clicks', 'google_cost', 'google_conversions', 'meta_clicks', 'meta_cost', 'meta_conversions']
        },
        'tourism': {
            name: 'Agenzia Viaggi / Tour Operator',
            metrics: ['users', 'sessions', 'pageviews', 'gsc_clicks', 'google_clicks', 'google_impressions', 'meta_clicks', 'meta_impressions', 'google_conversions', 'meta_conversions', 'revenue']
        }
    };

    // Carica preferenze salvate
    function loadPreferences() {
        try {
            const saved = localStorage.getItem(STORAGE_KEY);
            return saved ? JSON.parse(saved) : DEFAULT_METRICS;
        } catch (e) {
            return DEFAULT_METRICS;
        }
    }
    
    // Carica preset business salvato
    function loadBusinessPreset() {
        try {
            return localStorage.getItem(STORAGE_PRESET_KEY) || '';
        } catch (e) {
            return '';
        }
    }

    // Salva preferenze
    function savePreferences(metrics, businessPreset) {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(metrics));
            if (businessPreset) {
                localStorage.setItem(STORAGE_PRESET_KEY, businessPreset);
            }
            return true;
        } catch (e) {
            console.error('Failed to save metrics preferences:', e);
            return false;
        }
    }
    
    // Applica preset business
    function applyBusinessPreset(presetKey, checkboxes) {
        const preset = BUSINESS_PRESETS[presetKey];
        if (!preset) {
            return;
        }
        
        checkboxes.forEach(function(checkbox) {
            checkbox.checked = preset.metrics.includes(checkbox.value);
        });
    }

    // Applica visibilità metriche
    function applyVisibility(visibleMetrics) {
        const allCards = document.querySelectorAll('.fpdms-kpi-card');
        
        allCards.forEach(function(card) {
            const metric = card.getAttribute('data-metric');
            if (visibleMetrics.includes(metric)) {
                card.style.display = '';
            } else {
                card.style.display = 'none';
            }
        });

        // Nascondi sezioni completamente vuote
        const sections = document.querySelectorAll('.fpdms-metrics-section');
        sections.forEach(function(section) {
            const sectionCards = section.querySelectorAll('.fpdms-kpi-card');
            let hasVisible = false;
            
            sectionCards.forEach(function(card) {
                const metric = card.getAttribute('data-metric');
                if (visibleMetrics.includes(metric)) {
                    hasVisible = true;
                }
            });
            
            // Nascondi l'intera sezione se non ha card visibili
            section.style.display = hasVisible ? '' : 'none';
        });

        // Mostra messaggio se nessuna metrica selezionata
        const visibleCount = visibleMetrics.length;
        if (visibleCount === 0) {
            const firstSection = document.querySelector('.fpdms-metrics-section');
            if (firstSection) {
                const msg = document.createElement('p');
                msg.className = 'fpdms-no-metrics';
                msg.textContent = 'No metrics selected. Click "Customize Metrics" to choose which metrics to display.';
                msg.style.cssText = 'text-align:center;color:#666;padding:40px;';
                firstSection.appendChild(msg);
            }
        } else {
            const existing = document.querySelector('.fpdms-no-metrics');
            if (existing) {
                existing.remove();
            }
        }
    }

    // Inizializza
    function init() {
        const customizeBtn = document.getElementById('fpdms-customize-metrics');
        const modal = document.getElementById('fpdms-metrics-modal');
        const saveBtn = document.getElementById('fpdms-save-metrics');
        const cancelBtn = modal ? modal.querySelector('.fpdms-modal-cancel') : null;
        const closeBtn = modal ? modal.querySelector('.fpdms-modal-close') : null;
        const overlay = modal ? modal.querySelector('.fpdms-modal-overlay') : null;
        const presetSelect = document.getElementById('fpdms-business-preset');

        if (!customizeBtn || !modal) {
            return;
        }

        // Carica e applica preferenze salvate
        const visibleMetrics = loadPreferences();
        const savedPreset = loadBusinessPreset();
        applyVisibility(visibleMetrics);

        // Imposta checkboxes in base alle preferenze
        const checkboxes = modal.querySelectorAll('input[type="checkbox"]');
        checkboxes.forEach(function(checkbox) {
            checkbox.checked = visibleMetrics.includes(checkbox.value);
        });
        
        // Imposta preset salvato
        if (presetSelect && savedPreset) {
            presetSelect.value = savedPreset;
        }
        
        // Event listener per cambio preset
        if (presetSelect) {
            presetSelect.addEventListener('change', function() {
                const selectedPreset = this.value;
                
                if (selectedPreset === '' || selectedPreset === 'custom') {
                    return; // Non fare nulla se "custom" o vuoto
                }
                
                applyBusinessPreset(selectedPreset, checkboxes);
            });
        }

        // Apri modal
        customizeBtn.addEventListener('click', function() {
            modal.style.display = 'flex';
        });

        // Chiudi modal
        function closeModal() {
            modal.style.display = 'none';
        }

        if (closeBtn) {
            closeBtn.addEventListener('click', closeModal);
        }

        if (cancelBtn) {
            cancelBtn.addEventListener('click', closeModal);
        }

        if (overlay) {
            overlay.addEventListener('click', closeModal);
        }

        // Escape per chiudere
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && modal.style.display === 'flex') {
                closeModal();
            }
        });

        // Salva selezione
        if (saveBtn) {
            saveBtn.addEventListener('click', function() {
                const selected = [];
                checkboxes.forEach(function(checkbox) {
                    if (checkbox.checked) {
                        selected.push(checkbox.value);
                    }
                });

                if (selected.length === 0) {
                    alert('Select at least one metric to display.');
                    return;
                }

                const businessPreset = presetSelect ? presetSelect.value : '';
                savePreferences(selected, businessPreset);
                applyVisibility(selected);
                closeModal();

                // Mostra feedback
                const feedback = document.createElement('div');
                feedback.className = 'notice notice-success is-dismissible';
                let message = '<p><strong>Metrics updated!</strong> ';
                
                if (businessPreset && BUSINESS_PRESETS[businessPreset]) {
                    message += 'Using preset: <strong>' + BUSINESS_PRESETS[businessPreset].name + '</strong>. ';
                }
                
                message += 'Your selection has been saved.</p>';
                feedback.innerHTML = message;
                feedback.style.cssText = 'margin:16px 0;';
                
                const header = document.querySelector('#fpdms-overview-kpis-heading');
                if (header && header.parentNode) {
                    header.parentNode.insertBefore(feedback, header.nextSibling);
                    
                    setTimeout(function() {
                        feedback.remove();
                    }, 3000);
                }
            });
        }
    }

    // Init quando DOM pronto
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();


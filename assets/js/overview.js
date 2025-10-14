/**
 * Overview Main Entry Point
 * Inizializza l'applicazione overview con architettura modulare
 */
import { OverviewState, DatePresets, OverviewAPI, ChartsRenderer, OverviewUI } from './modules/overview/index.js';

(function() {
    // Verify DOM elements
    const root = document.getElementById('fpdms-overview-root');
    const configEl = document.getElementById('fpdms-overview-config');
    
    if (!root || !configEl) {
        return;
    }

    // Parse configuration
    let config = {};
    try {
        config = JSON.parse(configEl.textContent || '{}');
    } catch (error) {
        // Log error in development mode only
        if (window.fpdmsDebug) {
            console.error('FPDMS overview: invalid config', error);
        }
        return;
    }

    // Cache DOM elements
    const DOM = {
        client: document.getElementById('fpdms-overview-client'),
        presetButtons: Array.from(document.querySelectorAll('[data-fpdms-preset]')),
        custom: document.getElementById('fpdms-overview-custom'),
        dateFrom: document.getElementById('fpdms-overview-date-from'),
        dateTo: document.getElementById('fpdms-overview-date-to'),
        periodLabel: document.getElementById('fpdms-overview-period-label'),
        errorBox: document.getElementById('fpdms-overview-error'),
        errorMessage: document.getElementById('fpdms-overview-error-message'),
        summary: document.getElementById('fpdms-overview-kpis'),
        trends: document.getElementById('fpdms-overview-trends-grid'),
        anomaliesTable: document.querySelector('#fpdms-overview-anomalies tbody'),
        statusList: document.getElementById('fpdms-overview-status-list'),
        runButton: document.getElementById('fpdms-overview-action-run'),
        anomaliesButton: document.getElementById('fpdms-overview-action-anomalies'),
        actionStatus: document.getElementById('fpdms-overview-action-status'),
        refreshToggle: document.getElementById('fpdms-overview-refresh-toggle'),
        refreshSelect: document.getElementById('fpdms-overview-refresh-interval'),
        lastRefresh: document.getElementById('fpdms-overview-last-refresh')
    };

    // Initialize modules
    const presetOptions = DOM.presetButtons.map(btn => btn.getAttribute('data-fpdms-preset') || '');
    
    const state = new OverviewState(config);
    const presets = new DatePresets(presetOptions);
    const api = new OverviewAPI(config);
    const charts = new ChartsRenderer(config.i18n);
    const ui = new OverviewUI(DOM, config, charts);

    // Main data loading function
    async function loadAll(fromAuto = false) {
        if (!state.state.clientId) {
            return;
        }

        ui.clearError();
        state.clearAutoRefreshTimer();

        if (!fromAuto) {
            ui.setLoading(true);
        }
        
        ui.showRefreshingLabel();

        const range = presets.computeRange(
            state.state.preset,
            state.state.customFrom,
            state.state.customTo
        );

        const summaryParams = {
            client_id: state.state.clientId,
            preset: state.state.preset,
            auto_refresh: state.state.autoRefresh ? '1' : '0',
            refresh_interval: state.clampInterval(state.state.refreshInterval),
            ...(range.from && { from: range.from }),
            ...(range.to && { to: range.to })
        };

        const anomaliesParams = {
            client_id: state.state.clientId,
            ...(range.from && { from: range.from }),
            ...(range.to && { to: range.to })
        };

        const tasks = [
            api.fetchSummary(summaryParams)
                .then(data => {
                    ui.updateSummary(data);
                    
                    // Update last refresh timestamp
                    const refreshedAt = data?.refreshed_at || data?.summary?.refreshed_at;
                    if (refreshedAt) {
                        state.setLastRefresh(refreshedAt);
                    } else if (!state.state.lastRefresh) {
                        state.setLastRefresh(new Date().toISOString());
                    }
                })
                .catch(error => {
                    if (window.fpdmsDebug) {
                        console.error('FPDMS overview summary error', error);
                    }
                    ui.showError(error.message);
                }),

            api.fetchStatus(state.state.clientId)
                .then(data => ui.updateStatus(data))
                .catch(error => {
                    if (window.fpdmsDebug) {
                        console.error('FPDMS overview status error', error);
                    }
                    ui.showError(error.message);
                }),

            api.fetchAnomalies(anomaliesParams)
                .then(data => ui.updateAnomalies(data))
                .catch(error => {
                    if (window.fpdmsDebug) {
                        console.warn('FPDMS overview anomalies unavailable', error);
                    }
                })
        ];

        await Promise.allSettled(tasks);

        if (!fromAuto) {
            ui.setLoading(false);
        }
        
        ui.updateLastRefreshLabel(state.state.lastRefresh);
        state.scheduleAutoRefresh(() => loadAll(true), state.state.refreshInterval);
    }

    // Preset management
    function setPreset(preset, options = {}) {
        const shouldLoad = options.load !== false;
        const preserveCustom = !!options.preserveCustom;
        const normalized = presets.normalizePreset(preset);
        
        state.updateState({ preset: normalized });

        DOM.presetButtons.forEach(btn => {
            const isActive = btn.getAttribute('data-fpdms-preset') === normalized;
            btn.classList.toggle('is-active', isActive);
            btn.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        });

        if (DOM.custom) {
            DOM.custom.hidden = normalized !== 'custom';
        }

        if (normalized !== 'custom') {
            if (!preserveCustom) {
                state.updateState({ customFrom: '', customTo: '' });
                if (DOM.dateFrom) DOM.dateFrom.value = '';
                if (DOM.dateTo) DOM.dateTo.value = '';
            }
            if (shouldLoad) loadAll();
            return;
        }

        // Custom preset
        if (DOM.dateFrom) DOM.dateFrom.value = state.state.customFrom || '';
        if (DOM.dateTo) DOM.dateTo.value = state.state.customTo || '';

        if (state.state.customFrom && state.state.customTo && shouldLoad) {
            loadAll();
        }
    }

    // Initialize state from preferences
    const prefs = config.preferences || {};
    
    if (DOM.client) {
        const preferredClient = prefs.client_id ? String(prefs.client_id) : '';
        if (preferredClient) {
            const match = [...DOM.client.options].find(opt => opt.value === preferredClient);
            if (match) DOM.client.value = preferredClient;
        }
        state.updateState({ clientId: DOM.client.value });
    }

    state.loadPreferences(prefs);

    // Initialize UI controls
    if (DOM.refreshToggle) {
        DOM.refreshToggle.checked = state.state.autoRefresh;
        DOM.refreshToggle.setAttribute('aria-label', config.i18n?.autoRefresh || 'Auto-refresh');
    }

    if (DOM.refreshSelect) {
        DOM.refreshSelect.value = String(state.clampInterval(state.state.refreshInterval));
        DOM.refreshSelect.disabled = !state.state.autoRefresh;
        DOM.refreshSelect.setAttribute('aria-label', config.i18n?.autoRefreshInterval || 'Auto-refresh interval');
    }

    ui.updateLastRefreshLabel(state.state.lastRefresh);
    setPreset(state.state.preset, { load: false, preserveCustom: true });

    // Event Listeners
    DOM.client?.addEventListener('change', () => {
        state.updateState({ clientId: DOM.client.value });
        loadAll();
    });

    DOM.presetButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            setPreset(btn.getAttribute('data-fpdms-preset') || 'last7');
        });
    });

    DOM.dateFrom?.addEventListener('change', () => {
        state.updateState({ customFrom: DOM.dateFrom.value });
        if (state.state.preset === 'custom' && state.state.customTo) {
            loadAll();
        }
    });

    DOM.dateTo?.addEventListener('change', () => {
        state.updateState({ customTo: DOM.dateTo.value });
        if (state.state.preset === 'custom' && state.state.customFrom) {
            loadAll();
        }
    });

    DOM.refreshToggle?.addEventListener('change', () => {
        state.updateState({ autoRefresh: DOM.refreshToggle.checked });
        
        if (DOM.refreshSelect) {
            DOM.refreshSelect.disabled = !state.state.autoRefresh;
        }
        
        if (!state.state.autoRefresh) {
            state.clearAutoRefreshTimer();
        }
        
        loadAll();
    });

    DOM.refreshSelect?.addEventListener('change', () => {
        const interval = state.clampInterval(parseInt(DOM.refreshSelect.value, 10));
        state.updateState({ refreshInterval: interval });
        DOM.refreshSelect.value = String(interval);
        loadAll(state.state.autoRefresh);
    });

    DOM.runButton?.addEventListener('click', async () => {
        if (!state.state.clientId) return;

        ui.setActionBusy(DOM.runButton, true);
        ui.showActionStatus('info', config.i18n?.runPending || 'Queuing report…');

        const range = presets.computeRange(
            state.state.preset,
            state.state.customFrom,
            state.state.customTo
        );

        const payload = {
            client_id: state.state.clientId,
            process: 'now',
            ...(range.from && { from: range.from }),
            ...(range.to && { to: range.to })
        };

        try {
            await api.runReport(payload);
            ui.showActionStatus('success', config.i18n?.runQueued || 'Report run queued.');
            loadAll();
        } catch (error) {
            if (window.fpdmsDebug) {
                console.error('FPDMS overview run error', error);
            }
            ui.showActionStatus('error', error.message || config.i18n?.actionError || 'Action failed. Try again.');
        } finally {
            ui.setActionBusy(DOM.runButton, false);
        }
    });

    DOM.anomaliesButton?.addEventListener('click', async () => {
        if (!state.state.clientId) return;

        ui.setActionBusy(DOM.anomaliesButton, true);
        ui.showActionStatus('info', config.i18n?.anomalyRunning || 'Evaluating anomalies…');

        const range = presets.computePresetRange('last30');
        const payload = {
            client_id: state.state.clientId,
            ...(range.from && { from: range.from }),
            ...(range.to && { to: range.to })
        };

        try {
            const data = await api.evaluateAnomalies(payload);
            const count = data?.count || 0;
            
            if (data?.anomalies) {
                ui.updateAnomalies(data);
            }

            const message = count > 0
                ? (config.i18n?.anomalyComplete || 'Anomaly evaluation found %d signals.').replace('%d', String(count))
                : config.i18n?.anomalyNone || 'No anomalies detected in the last 30 days.';
            
            ui.showActionStatus('success', message);
        } catch (error) {
            if (window.fpdmsDebug) {
                console.error('FPDMS overview anomaly evaluation error', error);
            }
            ui.showActionStatus('error', error.message || config.i18n?.actionError || 'Action failed. Try again.');
        } finally {
            ui.setActionBusy(DOM.anomaliesButton, false);
        }
    });

    // Initial load
    if (state.state.clientId) {
        loadAll();
    }
})();
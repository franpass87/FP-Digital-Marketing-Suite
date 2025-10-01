(function(){
    const root = document.getElementById('fpdms-overview-root');
    const configEl = document.getElementById('fpdms-overview-config');
    if (!root || !configEl) {
        return;
    }

    let config = {};
    try {
        config = JSON.parse(configEl.textContent || '{}');
    } catch (error) {
        console.error('FPDMS overview: invalid config', error);
        return;
    }

    const clientSelect = document.getElementById('fpdms-overview-client');
    const presetButtons = Array.from(document.querySelectorAll('[data-fpdms-preset]'));
    const presetOptions = presetButtons.map((button) => button.getAttribute('data-fpdms-preset') || '');
    const customContainer = document.getElementById('fpdms-overview-custom');
    const dateFrom = document.getElementById('fpdms-overview-date-from');
    const dateTo = document.getElementById('fpdms-overview-date-to');
    const periodLabel = document.getElementById('fpdms-overview-period-label');
    const errorBox = document.getElementById('fpdms-overview-error');
    const errorMessage = document.getElementById('fpdms-overview-error-message');
    const summaryContainer = document.getElementById('fpdms-overview-kpis');
    const trendsContainer = document.getElementById('fpdms-overview-trends-grid');
    const anomaliesTable = document.querySelector('#fpdms-overview-anomalies tbody');
    const statusList = document.getElementById('fpdms-overview-status-list');
    const runButton = document.getElementById('fpdms-overview-action-run');
    const anomaliesButton = document.getElementById('fpdms-overview-action-anomalies');
    const actionStatus = document.getElementById('fpdms-overview-action-status');
    const refreshToggle = document.getElementById('fpdms-overview-refresh-toggle');
    const refreshSelect = document.getElementById('fpdms-overview-refresh-interval');
    const lastRefreshNote = document.getElementById('fpdms-overview-last-refresh');

    const refreshIntervals = Array.isArray(config.refreshIntervals)
        ? config.refreshIntervals
            .map((interval) => parseInt(interval, 10))
            .filter((interval) => !Number.isNaN(interval) && interval > 0)
        : [60, 120];

    if (refreshIntervals.length === 0) {
        const fallback = parseInt(config.defaultRefreshInterval, 10);
        if (!Number.isNaN(fallback) && fallback > 0) {
            refreshIntervals.push(fallback);
        } else {
            refreshIntervals.push(60);
        }
    }

    const defaultRefreshInterval = clampInterval(config.defaultRefreshInterval);

    const state = {
        clientId: clientSelect ? clientSelect.value : '',
        preset: 'last7',
        customFrom: '',
        customTo: '',
        autoRefresh: false,
        refreshInterval: defaultRefreshInterval,
        lastRefresh: ''
    };

    let refreshTimer = null;

    function normalizePreset(value) {
        return presetOptions.includes(value) ? value : 'last7';
    }

    function clampInterval(value) {
        const fallback = parseInt(config.defaultRefreshInterval, 10) || refreshIntervals[0] || 60;
        const seconds = parseInt(value, 10);
        if (Number.isNaN(seconds) || seconds <= 0) {
            return fallback;
        }
        if (refreshIntervals.includes(seconds)) {
            return seconds;
        }
        const sorted = refreshIntervals.slice().sort((a, b) => Math.abs(a - seconds) - Math.abs(b - seconds));
        return sorted.length ? sorted[0] : fallback;
    }

    function formatTimestamp(value) {
        if (!value) {
            return '';
        }
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) {
            return '';
        }
        return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    }

    function resetLastRefreshLabel() {
        if (!lastRefreshNote) {
            return;
        }
        const formatted = formatTimestamp(state.lastRefresh);
        if (!formatted) {
            lastRefreshNote.textContent = config.i18n?.lastRefreshNever || 'Last refresh: never';
            return;
        }
        const template = config.i18n?.lastRefresh || 'Last refresh at %s';
        lastRefreshNote.textContent = template.replace('%s', formatted);
    }

    function showRefreshingLabel() {
        if (!lastRefreshNote) {
            return;
        }
        lastRefreshNote.textContent = config.i18n?.refreshing || 'Refreshing…';
    }

    function setLastRefresh(timestamp) {
        state.lastRefresh = timestamp || '';
        resetLastRefreshLabel();
    }

    function clearAutoRefreshTimer() {
        if (refreshTimer) {
            window.clearTimeout(refreshTimer);
            refreshTimer = null;
        }
    }

    function scheduleAutoRefresh() {
        clearAutoRefreshTimer();
        if (!state.autoRefresh) {
            return;
        }
        const interval = clampInterval(state.refreshInterval) * 1000;
        refreshTimer = window.setTimeout(() => {
            loadAll(true);
        }, interval);
    }

    function formatDate(date) {
        const pad = (n) => String(n).padStart(2, '0');
        return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate());
    }

    function computePresetRange(preset) {
        const today = new Date();
        let from;
        let to;

        switch (preset) {
            case 'last14':
                to = new Date(today);
                from = new Date(today);
                from.setDate(from.getDate() - 13);
                break;
            case 'last28':
                to = new Date(today);
                from = new Date(today);
                from.setDate(from.getDate() - 27);
                break;
            case 'last30':
                to = new Date(today);
                from = new Date(today);
                from.setDate(from.getDate() - 29);
                break;
            case 'this_month': {
                to = new Date(today);
                from = new Date(today.getFullYear(), today.getMonth(), 1);
                break;
            }
            case 'last_month': {
                const firstDayCurrent = new Date(today.getFullYear(), today.getMonth(), 1);
                to = new Date(firstDayCurrent);
                to.setDate(0);
                from = new Date(firstDayCurrent);
                from.setMonth(from.getMonth() - 1);
                break;
            }
            case 'last7':
            default:
                to = new Date(today);
                from = new Date(today);
                from.setDate(from.getDate() - 6);
                break;
        }

        return {
            from: from ? formatDate(from) : '',
            to: to ? formatDate(to) : ''
        };
    }

    function computeRange() {
        if (state.preset === 'custom') {
            const from = state.customFrom ? new Date(state.customFrom + 'T00:00:00') : null;
            const to = state.customTo ? new Date(state.customTo + 'T00:00:00') : null;

            return {
                from: from ? formatDate(from) : '',
                to: to ? formatDate(to) : ''
            };
        }

        return computePresetRange(state.preset);
    }

    function setPreset(preset, options) {
        const opts = options || {};
        const shouldLoad = opts.load !== false;
        const preserveCustom = !!opts.preserveCustom;
        const normalizedPreset = normalizePreset(typeof preset === 'string' ? preset : '');
        state.preset = normalizedPreset;
        presetButtons.forEach((button) => {
            const isActive = button.getAttribute('data-fpdms-preset') === normalizedPreset;
            button.classList.toggle('is-active', isActive);
            button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        });
        if (customContainer) {
            customContainer.hidden = normalizedPreset !== 'custom';
        }
        if (normalizedPreset !== 'custom') {
            if (!preserveCustom) {
                state.customFrom = '';
                state.customTo = '';
                if (dateFrom) {
                    dateFrom.value = '';
                }
                if (dateTo) {
                    dateTo.value = '';
                }
            }
            if (shouldLoad) {
                loadAll();
            }
            return;
        }

        if (dateFrom) {
            dateFrom.value = state.customFrom || '';
        }
        if (dateTo) {
            dateTo.value = state.customTo || '';
        }

        if (state.customFrom && state.customTo) {
            if (shouldLoad) {
                loadAll();
            }
        }
    }

    function showError(message) {
        if (!errorBox || !errorMessage) {
            return;
        }
        const fallback = config.i18n?.errorGeneric || 'Error';
        errorMessage.textContent = message || fallback;
        errorBox.classList.add('is-visible');
    }

    function clearError() {
        if (!errorBox) {
            return;
        }
        errorBox.classList.remove('is-visible');
    }

    function setActionBusy(button, busy) {
        if (!button) {
            return;
        }
        button.classList.toggle('is-busy', !!busy);
        button.disabled = !!busy;
    }

    function showActionStatus(type, message) {
        if (!actionStatus) {
            return;
        }
        actionStatus.textContent = message || '';
        if (!message) {
            actionStatus.classList.remove('is-visible');
            actionStatus.removeAttribute('data-status');
            return;
        }
        actionStatus.classList.add('is-visible');
        actionStatus.setAttribute('data-status', type || 'info');
    }

    function formatCountMessage(template, count) {
        if (typeof template !== 'string') {
            return '';
        }
        return template.replace('%d', String(count));
    }

    function renderSparkline(svg, values) {
        if (!svg) {
            return;
        }
        while (svg.firstChild) {
            svg.removeChild(svg.firstChild);
        }
        if (!Array.isArray(values) || values.length === 0) {
            const text = document.createElementNS('http://www.w3.org/2000/svg', 'text');
            text.setAttribute('x', '4');
            text.setAttribute('y', '22');
            text.setAttribute('fill', '#9ca3af');
            text.textContent = config.i18n?.sparklineFallback || 'No data';
            svg.appendChild(text);
            return;
        }
        const max = Math.max.apply(null, values);
        const min = Math.min.apply(null, values);
        const range = max - min || 1;
        const height = 40;
        const width = 100;
        const points = values.map((value, index) => {
            const x = values.length === 1 ? width : (width / (values.length - 1)) * index;
            const normalized = (value - min) / range;
            const y = height - (normalized * 32 + 4);
            return { x, y };
        });
        const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        let d = '';
        points.forEach((point, index) => {
            d += (index === 0 ? 'M' : 'L') + point.x.toFixed(2) + ' ' + point.y.toFixed(2) + ' ';
        });
        path.setAttribute('d', d.trim());
        path.setAttribute('fill', 'none');
        path.setAttribute('stroke', '#2563eb');
        path.setAttribute('stroke-width', '2');
        svg.appendChild(path);
    }

    function updateSummary(data) {
        const summary = data && data.summary ? data.summary : data;
        if (!summary) {
            return;
        }
        const refreshedAt = summary.refreshed_at || (data && data.refreshed_at);
        if (refreshedAt) {
            setLastRefresh(refreshedAt);
        } else if (!state.lastRefresh) {
            setLastRefresh(new Date().toISOString());
        }
        if (summary.period && periodLabel) {
            const from = summary.period.from || '';
            const to = summary.period.to || '';
            periodLabel.textContent = from && to ? from + ' → ' + to : config.i18n?.loading || '';
        }
        if (!summaryContainer || !Array.isArray(summary.kpis)) {
            return;
        }
        const cards = Array.from(summaryContainer.querySelectorAll('.fpdms-kpi-card'));
        cards.forEach((card) => {
            const metric = card.getAttribute('data-metric');
            const kpi = summary.kpis.find((item) => item.metric === metric);
            const valueEl = card.querySelector('[data-role="value"]');
            const deltaEl = card.querySelector('[data-role="delta"]');
            const previousEl = card.querySelector('[data-role="previous"]');
            const sparklineSvg = card.querySelector('svg');
            if (!kpi) {
                if (valueEl) {
                    valueEl.textContent = '--';
                }
                if (deltaEl) {
                    deltaEl.textContent = '0%';
                    deltaEl.setAttribute('data-direction', 'flat');
                }
                if (previousEl) {
                    previousEl.textContent = (config.i18n?.previous || 'Previous') + ': --';
                }
                renderSparkline(sparklineSvg, []);
                return;
            }
            if (valueEl) {
                const rawValue = kpi.formatted_value ?? kpi.value;
                valueEl.textContent = rawValue !== undefined && rawValue !== null ? String(rawValue) : '--';
            }
            if (deltaEl) {
                const delta = kpi.delta || {};
                const formatted = delta.formatted || '0%';
                const direction = delta.direction || 'flat';
                deltaEl.textContent = formatted;
                deltaEl.setAttribute('data-direction', direction);
            }
            if (previousEl) {
                const prev = kpi.formatted_previous || String(kpi.previous_value ?? '--');
                previousEl.textContent = (config.i18n?.previous || 'Previous') + ': ' + prev;
            }
            renderSparkline(sparklineSvg, Array.isArray(kpi.sparkline) ? kpi.sparkline : []);
        });
        updateTrends(summary);
    }

    function updateTrends(summary) {
        if (!trendsContainer || !summary || !Array.isArray(summary.kpis)) {
            return;
        }
        const kpiIndex = {};
        summary.kpis.forEach((kpi) => {
            if (kpi && kpi.metric) {
                kpiIndex[kpi.metric] = kpi;
            }
        });
        Array.from(trendsContainer.querySelectorAll('.fpdms-trend-card')).forEach((card) => {
            const metric = card.getAttribute('data-metric');
            const kpi = kpiIndex[metric];
            const svg = card.querySelector('svg');
            const meta = card.querySelector('[data-role="trend-meta"]');
            if (!kpi) {
                if (meta) {
                    meta.textContent = config.i18n?.sparklineFallback || 'No data';
                }
                renderSparkline(svg, []);
                return;
            }
            if (meta) {
                meta.textContent = (config.i18n?.previous || 'Previous') + ': ' + (kpi.formatted_previous || '--');
            }
            renderSparkline(svg, Array.isArray(kpi.sparkline) ? kpi.sparkline : []);
        });
    }

    function updateStatus(data) {
        if (!statusList) {
            return;
        }
        statusList.innerHTML = '';
        const statusUpdated = document.getElementById('fpdms-overview-status-updated');
        if (statusUpdated) {
            const formatted = data && data.checked_at ? formatTimestamp(data.checked_at) : '';
            if (formatted) {
                const template = config.i18n?.statusChecked || 'Status checked at %s';
                statusUpdated.textContent = template.replace('%s', formatted);
                statusUpdated.hidden = false;
            } else {
                statusUpdated.textContent = '';
                statusUpdated.hidden = true;
            }
        }
        const entries = data && Array.isArray(data.sources) ? data.sources : (Array.isArray(data) ? data : []);
        if (!entries.length) {
            const placeholder = document.createElement('div');
            placeholder.className = 'fpdms-status-item';
            placeholder.textContent = config.i18n?.noData || 'No data available.';
            statusList.appendChild(placeholder);
            return;
        }
        entries.forEach((entry) => {
            const item = document.createElement('div');
            item.className = 'fpdms-status-item';
            const label = document.createElement('span');
            label.className = 'fpdms-status-label';
            label.textContent = entry.label || entry.type || '';
            const state = document.createElement('span');
            state.className = 'fpdms-status-state';
            const stateValue = entry.state || 'ok';
            state.setAttribute('data-state', stateValue);
            const stateLabel = entry.state_label || (stateValue ? String(stateValue).toUpperCase() : '');
            state.textContent = stateLabel;
            const message = document.createElement('span');
            message.className = 'fpdms-status-message';
            message.textContent = entry.message || '';
            const updated = document.createElement('span');
            updated.className = 'fpdms-status-updated';
            if (entry.last_updated) {
                const template = config.i18n?.statusUpdated || 'Last data update: %s';
                updated.textContent = template.replace('%s', entry.last_updated);
            }
            item.appendChild(label);
            item.appendChild(state);
            if (entry.message) {
                item.appendChild(message);
            }
            if (entry.last_updated) {
                item.appendChild(updated);
            }
            statusList.appendChild(item);
        });
    }

    function updateAnomalies(data) {
        if (!anomaliesTable) {
            return;
        }
        anomaliesTable.innerHTML = '';
        const items = data && Array.isArray(data.anomalies) ? data.anomalies : (Array.isArray(data) ? data : []);
        if (!items.length) {
            const row = document.createElement('tr');
            const cell = document.createElement('td');
            cell.colSpan = 6;
            cell.textContent = config.i18n?.noData || 'No data available.';
            row.appendChild(cell);
            anomaliesTable.appendChild(row);
            return;
        }
        items.slice(0, 10).forEach((item) => {
            const row = document.createElement('tr');
            const severity = document.createElement('td');
            const badge = document.createElement('span');
            badge.className = 'fpdms-anomaly-badge';
            const variant = item.severity_variant || item.variant || 'neutral';
            badge.setAttribute('data-variant', variant);
            badge.textContent = item.severity_label || item.severity || variant;
            severity.appendChild(badge);
            const metric = document.createElement('td');
            metric.textContent = item.metric_label || item.metric || '';
            const change = document.createElement('td');
            const rawDelta = item.delta_formatted ?? item.delta;
            change.textContent = rawDelta !== undefined && rawDelta !== null ? String(rawDelta) : '';
            const score = document.createElement('td');
            score.textContent = item.score !== undefined ? String(item.score) : '';
            const when = document.createElement('td');
            when.textContent = item.occurred_at || item.time || '';
            const actions = document.createElement('td');
            if (item.url) {
                const link = document.createElement('a');
                link.href = item.url;
                link.textContent = config.i18n?.anomalyAction || 'Resolve / Note';
                link.target = '_blank';
                link.rel = 'noopener noreferrer';
                actions.appendChild(link);
            } else {
                actions.textContent = config.i18n?.anomalyAction || 'Resolve / Note';
            }
            row.appendChild(severity);
            row.appendChild(metric);
            row.appendChild(change);
            row.appendChild(score);
            row.appendChild(when);
            row.appendChild(actions);
            anomaliesTable.appendChild(row);
        });
    }

    function request(url, params) {
        if (!url) {
            return Promise.resolve({});
        }
        const endpoint = new URL(url, window.location.origin);
        if (params) {
            Object.keys(params).forEach((key) => {
                if (params[key]) {
                    endpoint.searchParams.set(key, params[key]);
                }
            });
        }
        return fetch(endpoint.toString(), {
            credentials: 'same-origin',
            headers: {
                'X-WP-Nonce': config.nonce || ''
            }
        }).then(async (response) => {
            if (!response.ok) {
                const payload = await response.json().catch(() => ({}));
                const message = payload && payload.message ? payload.message : 'HTTP ' + response.status;
                throw new Error(message);
            }
            return response.json();
        });
    }

    function postRequest(url, payload) {
        if (!url) {
            return Promise.reject(new Error('Missing endpoint'));
        }

        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': config.nonce || ''
            },
            body: JSON.stringify(payload || {})
        }).then(async (response) => {
            if (!response.ok) {
                const body = await response.json().catch(() => ({}));
                const message = body && body.message ? body.message : 'HTTP ' + response.status;
                throw new Error(message);
            }

            return response.json();
        });
    }

    function loadAll(fromAuto) {
        if (!state.clientId) {
            return;
        }
        clearError();
        clearAutoRefreshTimer();
        const isAuto = !!fromAuto;
        if (!isAuto) {
            root.classList.add('is-loading');
        }
        showRefreshingLabel();
        const range = computeRange();
        const summaryParams = {
            client_id: state.clientId,
            preset: state.preset,
            auto_refresh: state.autoRefresh ? '1' : '0',
            refresh_interval: clampInterval(state.refreshInterval)
        };
        const anomaliesParams = { client_id: state.clientId };
        if (range.from) {
            summaryParams.from = range.from;
            anomaliesParams.from = range.from;
        }
        if (range.to) {
            summaryParams.to = range.to;
            anomaliesParams.to = range.to;
        }
        const tasks = [
            request(config.endpoints?.summary, summaryParams)
                .then((data) => {
                    updateSummary(data);
                    return data;
                })
                .catch((error) => {
                    console.error('FPDMS overview summary error', error);
                    showError(error.message);
                }),
            request(config.endpoints?.status, { client_id: state.clientId })
                .then(updateStatus)
                .catch((error) => {
                    console.error('FPDMS overview status error', error);
                    showError(error.message);
                }),
            request(config.endpoints?.anomalies, anomaliesParams)
                .then(updateAnomalies)
                .catch((error) => {
                    console.warn('FPDMS overview anomalies unavailable', error);
                })
        ];
        Promise.allSettled(tasks).then(() => {
            if (!isAuto) {
                root.classList.remove('is-loading');
            }
            resetLastRefreshLabel();
            scheduleAutoRefresh();
        });
    }

    const prefs = (config.preferences && typeof config.preferences === 'object') ? config.preferences : {};

    if (clientSelect) {
        const preferredClient = prefs.client_id ? String(prefs.client_id) : '';
        if (preferredClient) {
            const match = Array.from(clientSelect.options || []).find((option) => option.value === preferredClient);
            if (match) {
                clientSelect.value = preferredClient;
            }
        }
        state.clientId = clientSelect.value;
    }

    state.preset = normalizePreset(typeof prefs.preset === 'string' ? prefs.preset : state.preset);
    state.customFrom = typeof prefs.from === 'string' ? prefs.from : '';
    state.customTo = typeof prefs.to === 'string' ? prefs.to : '';
    state.autoRefresh = !!prefs.auto_refresh;
    const storedInterval = typeof prefs.refresh_interval === 'number'
        ? prefs.refresh_interval
        : parseInt(prefs.refresh_interval || state.refreshInterval, 10);
    state.refreshInterval = clampInterval(storedInterval);

    if (refreshToggle) {
        refreshToggle.checked = state.autoRefresh;
        refreshToggle.setAttribute('aria-label', config.i18n?.autoRefresh || 'Auto-refresh');
    }
    if (refreshSelect) {
        refreshSelect.value = String(clampInterval(state.refreshInterval));
        refreshSelect.disabled = !state.autoRefresh;
        refreshSelect.setAttribute('aria-label', config.i18n?.autoRefreshInterval || 'Auto-refresh interval');
    }

    resetLastRefreshLabel();
    setPreset(state.preset, { load: false, preserveCustom: true });

    if (clientSelect) {
        clientSelect.addEventListener('change', () => {
            state.clientId = clientSelect.value;
            loadAll();
        });
    }

    presetButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const preset = button.getAttribute('data-fpdms-preset') || 'last7';
            setPreset(preset);
        });
    });

    if (dateFrom) {
        dateFrom.addEventListener('change', () => {
            state.customFrom = dateFrom.value;
            if (state.preset === 'custom' && state.customTo) {
                loadAll();
            }
        });
    }

    if (dateTo) {
        dateTo.addEventListener('change', () => {
            state.customTo = dateTo.value;
            if (state.preset === 'custom' && state.customFrom) {
                loadAll();
            }
        });
    }

    if (refreshToggle) {
        refreshToggle.addEventListener('change', () => {
            state.autoRefresh = refreshToggle.checked;
            if (refreshSelect) {
                refreshSelect.disabled = !state.autoRefresh;
            }
            if (!state.autoRefresh) {
                clearAutoRefreshTimer();
            }
            loadAll();
        });
    }

    if (refreshSelect) {
        refreshSelect.addEventListener('change', () => {
            const interval = clampInterval(parseInt(refreshSelect.value, 10));
            state.refreshInterval = interval;
            refreshSelect.value = String(interval);
            if (state.autoRefresh) {
                loadAll(true);
            } else {
                loadAll();
            }
        });
    }

    if (runButton) {
        runButton.addEventListener('click', () => {
            if (!state.clientId) {
                return;
            }

            setActionBusy(runButton, true);
            showActionStatus('info', config.i18n?.runPending || 'Queuing report…');

            const range = computeRange();
            const payload = { client_id: state.clientId, process: 'now' };
            if (range.from) {
                payload.from = range.from;
            }
            if (range.to) {
                payload.to = range.to;
            }

            postRequest((config.actions && config.actions.run) || (config.endpoints && config.endpoints.run), payload)
                .then(() => {
                    showActionStatus('success', config.i18n?.runQueued || 'Report run queued.');
                    loadAll();
                })
                .catch((error) => {
                    console.error('FPDMS overview run error', error);
                    showActionStatus('error', error.message || config.i18n?.actionError || 'Action failed. Try again.');
                })
                .finally(() => {
                    setActionBusy(runButton, false);
                });
        });
    }

    if (anomaliesButton) {
        anomaliesButton.addEventListener('click', () => {
            if (!state.clientId) {
                return;
            }

            setActionBusy(anomaliesButton, true);
            showActionStatus('info', config.i18n?.anomalyRunning || 'Evaluating anomalies…');

            const range = computePresetRange('last30');
            const payload = { client_id: state.clientId };
            if (range.from) {
                payload.from = range.from;
            }
            if (range.to) {
                payload.to = range.to;
            }

            postRequest((config.actions && config.actions.anomalies) || (config.endpoints && config.endpoints.anomalies), payload)
                .then((data) => {
                    const count = data && typeof data.count === 'number' ? data.count : 0;
                    if (data && Array.isArray(data.anomalies)) {
                        updateAnomalies(data);
                    }
                    if (count > 0) {
                        const message = formatCountMessage(config.i18n?.anomalyComplete || 'Anomaly evaluation found %d signals.', count);
                        showActionStatus('success', message);
                    } else {
                        showActionStatus('success', config.i18n?.anomalyNone || 'No anomalies detected in the last 30 days.');
                    }
                })
                .catch((error) => {
                    console.error('FPDMS overview anomaly evaluation error', error);
                    showActionStatus('error', error.message || config.i18n?.actionError || 'Action failed. Try again.');
                })
                .finally(() => {
                    setActionBusy(anomaliesButton, false);
                });
        });
    }

    // Initial load
    if (state.clientId) {
        loadAll();
    }
})();

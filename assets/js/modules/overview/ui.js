/**
 * Overview UI Manager
 * Gestisce tutti gli aggiornamenti dell'interfaccia utente
 */
import { ChartsRenderer } from './charts.js';

export class OverviewUI {
    constructor(dom, config, chartsRenderer) {
        this.dom = dom;
        this.config = config;
        this.i18n = config.i18n || {};
        this.charts = chartsRenderer || new ChartsRenderer(this.i18n);
    }

    formatTimestamp(value) {
        if (!value) return '';
        
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) return '';
        
        return date.toLocaleTimeString([], { 
            hour: '2-digit', 
            minute: '2-digit', 
            second: '2-digit' 
        });
    }

    updateLastRefreshLabel(timestamp) {
        if (!this.dom.lastRefresh) return;

        const formatted = this.formatTimestamp(timestamp);
        
        if (!formatted) {
            this.dom.lastRefresh.textContent = this.i18n.lastRefreshNever || 'Last refresh: never';
            return;
        }

        const template = this.i18n.lastRefresh || 'Last refresh at %s';
        this.dom.lastRefresh.textContent = template.replace('%s', formatted);
    }

    showRefreshingLabel() {
        if (this.dom.lastRefresh) {
            this.dom.lastRefresh.textContent = this.i18n.refreshing || 'Refreshing…';
        }
    }

    showError(message) {
        if (!this.dom.errorBox || !this.dom.errorMessage) return;

        const fallback = this.i18n.errorGeneric || 'Error';
        this.dom.errorMessage.textContent = message || fallback;
        this.dom.errorBox.classList.add('is-visible');
    }

    clearError() {
        if (this.dom.errorBox) {
            this.dom.errorBox.classList.remove('is-visible');
        }
    }

    setActionBusy(button, busy) {
        if (!button) return;
        
        button.classList.toggle('is-busy', !!busy);
        button.disabled = !!busy;
    }

    showActionStatus(type, message) {
        if (!this.dom.actionStatus) return;

        this.dom.actionStatus.textContent = message || '';
        
        if (!message) {
            this.dom.actionStatus.classList.remove('is-visible');
            this.dom.actionStatus.removeAttribute('data-status');
            return;
        }

        this.dom.actionStatus.classList.add('is-visible');
        this.dom.actionStatus.setAttribute('data-status', type || 'info');
    }

    updateSummary(data) {
        const summary = data?.summary || data;
        if (!summary) return;

        // Update period label
        if (summary.period && this.dom.periodLabel) {
            const { from = '', to = '' } = summary.period;
            this.dom.periodLabel.textContent = from && to 
                ? `${from} → ${to}` 
                : this.i18n.loading || '';
        }

        // Update KPI cards
        if (this.dom.summary && Array.isArray(summary.kpis)) {
            this._updateKPICards(summary.kpis);
        }
    }

    _updateKPICards(kpis) {
        this.dom.summary.querySelectorAll('.fpdms-kpi-card').forEach(card => {
            const metric = card.getAttribute('data-metric');
            const kpi = kpis.find(item => item.metric === metric);
            
            const valueEl = card.querySelector('[data-role="value"]');
            const deltaEl = card.querySelector('[data-role="delta"]');
            const previousEl = card.querySelector('[data-role="previous"]');

            if (!kpi) {
                this._clearKPICard(valueEl, deltaEl, previousEl);
                return;
            }

            this._updateKPICard(kpi, valueEl, deltaEl, previousEl);
        });

        // Render sparklines
        this.charts.renderKPISparklines(this.dom.summary, kpis);
        this._updateTrends(kpis);
    }

    _clearKPICard(valueEl, deltaEl, previousEl) {
        if (valueEl) valueEl.textContent = '--';
        
        if (deltaEl) {
            deltaEl.textContent = '0%';
            deltaEl.setAttribute('data-direction', 'flat');
        }
        
        if (previousEl) {
            previousEl.textContent = `${this.i18n.previous || 'Previous'}: --`;
        }
    }

    _updateKPICard(kpi, valueEl, deltaEl, previousEl) {
        if (valueEl) {
            const rawValue = kpi.formatted_value ?? kpi.value;
            valueEl.textContent = rawValue !== undefined && rawValue !== null 
                ? String(rawValue) 
                : '--';
        }

        if (deltaEl) {
            const delta = kpi.delta || {};
            deltaEl.textContent = delta.formatted || '0%';
            deltaEl.setAttribute('data-direction', delta.direction || 'flat');
        }

        if (previousEl) {
            const prev = kpi.formatted_previous || String(kpi.previous_value ?? '--');
            previousEl.textContent = `${this.i18n.previous || 'Previous'}: ${prev}`;
        }
    }

    _updateTrends(kpis) {
        if (!this.dom.trends || !Array.isArray(kpis)) return;

        this.charts.renderTrendCharts(this.dom.trends, kpis);

        // Update meta information
        const kpiIndex = Object.fromEntries(
            kpis.filter(k => k?.metric).map(k => [k.metric, k])
        );

        this.dom.trends.querySelectorAll('.fpdms-trend-card').forEach(card => {
            const metric = card.getAttribute('data-metric');
            const kpi = kpiIndex[metric];
            const meta = card.querySelector('[data-role="trend-meta"]');

            if (!meta) return;

            if (!kpi) {
                meta.textContent = this.i18n.sparklineFallback || 'No data';
            } else {
                const prev = kpi.formatted_previous || '--';
                meta.textContent = `${this.i18n.previous || 'Previous'}: ${prev}`;
            }
        });
    }

    updateStatus(data) {
        if (!this.dom.statusList) return;

        this.dom.statusList.innerHTML = '';

        // Update timestamp
        const statusUpdated = document.getElementById('fpdms-overview-status-updated');
        if (statusUpdated) {
            const formatted = data?.checked_at ? this.formatTimestamp(data.checked_at) : '';
            
            if (formatted) {
                const template = this.i18n.statusChecked || 'Status checked at %s';
                statusUpdated.textContent = template.replace('%s', formatted);
                statusUpdated.hidden = false;
            } else {
                statusUpdated.textContent = '';
                statusUpdated.hidden = true;
            }
        }

        // Render status items
        const entries = data?.sources || (Array.isArray(data) ? data : []);
        
        if (!entries.length) {
            this._renderNoDataPlaceholder(this.dom.statusList);
            return;
        }

        entries.forEach(entry => this._renderStatusItem(entry));
    }

    _renderStatusItem(entry) {
        const item = document.createElement('div');
        item.className = 'fpdms-status-item';

        const label = document.createElement('span');
        label.className = 'fpdms-status-label';
        label.textContent = entry.label || entry.type || '';

        const state = document.createElement('span');
        state.className = 'fpdms-status-state';
        const stateValue = entry.state || 'ok';
        state.setAttribute('data-state', stateValue);
        state.textContent = entry.state_label || stateValue.toUpperCase();

        item.append(label, state);

        if (entry.message) {
            const message = document.createElement('span');
            message.className = 'fpdms-status-message';
            message.textContent = entry.message;
            item.appendChild(message);
        }

        if (entry.last_updated) {
            const updated = document.createElement('span');
            updated.className = 'fpdms-status-updated';
            const template = this.i18n.statusUpdated || 'Last data update: %s';
            updated.textContent = template.replace('%s', entry.last_updated);
            item.appendChild(updated);
        }

        this.dom.statusList.appendChild(item);
    }

    updateAnomalies(data) {
        if (!this.dom.anomaliesTable) return;

        this.dom.anomaliesTable.innerHTML = '';

        const items = data?.anomalies || (Array.isArray(data) ? data : []);
        
        if (!items.length) {
            this._renderNoDataRow(this.dom.anomaliesTable);
            return;
        }

        items.slice(0, 10).forEach(item => this._renderAnomalyRow(item));
    }

    _renderAnomalyRow(item) {
        const row = document.createElement('tr');

        // Severity
        const severityCell = document.createElement('td');
        const badge = document.createElement('span');
        badge.className = 'fpdms-anomaly-badge';
        const variant = item.severity_variant || item.variant || 'neutral';
        badge.setAttribute('data-variant', variant);
        badge.textContent = item.severity_label || item.severity || variant;
        severityCell.appendChild(badge);

        // Metric
        const metricCell = document.createElement('td');
        metricCell.textContent = item.metric_label || item.metric || '';

        // Change
        const changeCell = document.createElement('td');
        const rawDelta = item.delta_formatted ?? item.delta;
        changeCell.textContent = rawDelta !== undefined && rawDelta !== null 
            ? String(rawDelta) 
            : '';

        // Score
        const scoreCell = document.createElement('td');
        scoreCell.textContent = item.score !== undefined ? String(item.score) : '';

        // When
        const whenCell = document.createElement('td');
        whenCell.textContent = item.occurred_at || item.time || '';

        // Actions
        const actionsCell = document.createElement('td');
        if (item.url) {
            const link = document.createElement('a');
            link.href = item.url;
            link.textContent = this.i18n.anomalyAction || 'Resolve / Note';
            link.target = '_blank';
            link.rel = 'noopener noreferrer';
            actionsCell.appendChild(link);
        } else {
            actionsCell.textContent = this.i18n.anomalyAction || 'Resolve / Note';
        }

        row.append(severityCell, metricCell, changeCell, scoreCell, whenCell, actionsCell);
        this.dom.anomaliesTable.appendChild(row);
    }

    _renderNoDataPlaceholder(container) {
        const placeholder = document.createElement('div');
        placeholder.className = 'fpdms-status-item';
        placeholder.textContent = this.i18n.noData || 'No data available.';
        container.appendChild(placeholder);
    }

    _renderNoDataRow(tbody) {
        const row = document.createElement('tr');
        const cell = document.createElement('td');
        cell.colSpan = 6;
        cell.textContent = this.i18n.noData || 'No data available.';
        row.appendChild(cell);
        tbody.appendChild(row);
    }

    updateAIInsights(data) {
        const container = document.getElementById('fpdms-ai-insights-container');
        const loading = document.getElementById('fpdms-ai-insights-loading');
        const content = document.getElementById('fpdms-ai-insights-content');
        const error = document.getElementById('fpdms-ai-insights-error');
        
        if (!container || !loading || !content || !error) return;

        const insights = data?.insights;
        
        // Hide loading
        loading.style.display = 'none';

        // Check if API key is configured
        if (!insights || insights.has_api_key === false) {
            error.style.display = 'block';
            content.style.display = 'none';
            return;
        }

        // Show content
        error.style.display = 'none';
        content.style.display = 'grid';

        // Update performance analysis
        const performanceEl = document.getElementById('fpdms-ai-performance-analysis');
        if (performanceEl && insights.performance) {
            performanceEl.innerHTML = insights.performance;
        }

        // Update trend analysis
        const trendEl = document.getElementById('fpdms-ai-trend-analysis');
        if (trendEl && insights.trends) {
            trendEl.innerHTML = insights.trends;
        }

        // Update recommendations
        const recommendationsEl = document.getElementById('fpdms-ai-recommendations');
        if (recommendationsEl && insights.recommendations) {
            recommendationsEl.innerHTML = insights.recommendations;
        }
    }

    setLoading(isLoading) {
        const root = document.getElementById('fpdms-overview-root');
        if (root) {
            root.classList.toggle('is-loading', isLoading);
        }
    }
}
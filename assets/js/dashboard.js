/**
 * FP Digital Marketing Suite - Dashboard JavaScript
 */

(function($) {
    'use strict';

    class FPDashboard {
        constructor() {
            this.chart = null;
            this.initialFilters = this.getInitialFilters();
            this.currentFilters = { ...this.initialFilters };
            this.themeColors = null;
            this.themePreference = this.getInitialThemePreference();
            this.$themeToggle = null;
            this.prefersColorSchemeQuery = null;
            this.init();
        }

        init() {
            this.bindEvents();
            this.applyTheme(this.themePreference, false);
            this.watchSystemTheme();
            this.loadDashboardData();
        }

        bindEvents() {
            // Filter events
            $('#apply-filters').on('click', () => this.onFiltersChange());
            $('#chart-metric').on('change', () => this.onChartMetricChange());
            $('#reset-filters').on('click', () => this.resetFilters());
            $('#fp-dms-empty-refresh').on('click', () => {
                this.showLoading();
                this.loadDashboardData();
            });

            this.bindThemeToggle();

            // Auto-refresh every 5 minutes
            setInterval(() => this.loadDashboardData(), 5 * 60 * 1000);
        }

        bindThemeToggle() {
            this.$themeToggle = $('#fp-dms-theme-toggle');

            if (!this.$themeToggle.length) {
                return;
            }

            this.$themeToggle.on('click', (event) => {
                event.preventDefault();
                const nextPreference = event.shiftKey
                    ? 'system'
                    : this.getNextThemePreference(this.themePreference);
                this.applyTheme(nextPreference);
            });
        }

        getInitialFilters() {
            return {
                client_id: $('#client-filter').val() || 0,
                period_start: $('#date-start').val() + ' 00:00:00',
                period_end: $('#date-end').val() + ' 23:59:59',
                sources: $('#source-filter').val() || []
            };
        }

        getCurrentFilters() {
            return {
                client_id: $('#client-filter').val() || 0,
                period_start: $('#date-start').val() + ' 00:00:00',
                period_end: $('#date-end').val() + ' 23:59:59',
                sources: $('#source-filter').val() || []
            };
        }

        onFiltersChange() {
            this.currentFilters = this.getCurrentFilters();
            this.showLoading();
            this.loadDashboardData();
        }

        onChartMetricChange() {
            this.loadChartData($('#chart-metric').val());
        }

        showLoading() {
            $('#dashboard-loading').show();
            $('#dashboard-content').hide();
            $('#dashboard-empty').hide();
            this.updateEmptyMessage();
        }

        showContent() {
            $('#dashboard-loading').hide();
            $('#dashboard-content').show();
            $('#dashboard-empty').hide();
            this.updateEmptyMessage();
        }

        showEmpty() {
            $('#dashboard-loading').hide();
            $('#dashboard-content').hide();
            $('#dashboard-empty').show();
            this.updateEmptyMessage();
        }

        resetFilters() {
            $('#client-filter').val(this.initialFilters.client_id);

            const initialStart = this.initialFilters.period_start.split(' ')[0];
            const initialEnd = this.initialFilters.period_end.split(' ')[0];

            $('#date-start').val(initialStart);
            $('#date-end').val(initialEnd);

            $('#source-filter').val(this.initialFilters.sources);
            $('#source-filter').trigger('change');

            this.onFiltersChange();
        }

        loadDashboardData() {
            const params = {
                action: 'fp_dms_get_dashboard_data',
                _wpnonce: fpDmsDashboard.nonce,
                ...this.currentFilters
            };

            $.get(fpDmsDashboard.ajax_url, params)
                .done((response) => {
                    if (response.success) {
                        const hasData = this.renderDashboard(response.data);

                        if (hasData) {
                            this.loadCoreWebVitals();
                            this.loadChartData($('#chart-metric').val());
                            this.showContent();
                        } else {
                            this.showEmpty();
                            this.updateEmptyMessage(fpDmsDashboard.strings.no_data || '');
                        }
                    } else {
                        this.showError(response.data || fpDmsDashboard.strings.error);
                    }
                })
                .fail(() => {
                    this.showError(fpDmsDashboard.strings.error);
                });
        }

        loadChartData(metric) {
            const params = {
                action: 'fp_dms_get_chart_data',
                _wpnonce: fpDmsDashboard.nonce,
                metric: metric,
                ...this.currentFilters
            };

            $.get(fpDmsDashboard.ajax_url, params)
                .done((response) => {
                    if (response.success) {
                        this.renderChart(response.data);
                    }
                })
                .fail(() => {
                    // Show user-friendly error instead of console logging
                    this.showError('Failed to load chart data. Please try refreshing the page.');
                });
        }

        renderDashboard(data) {
            const hasKpis = data && data.kpis && Object.values(data.kpis).some(kpi => {
                if (!kpi) {
                    return false;
                }

                if (typeof kpi.value !== 'undefined' && kpi.value !== null) {
                    return true;
                }

                return typeof kpi.formatted_value === 'string' && kpi.formatted_value.trim() !== '';
            });

            if (!hasKpis) {
                return false;
            }

            this.themeColors = this.resolveThemeColors();
            this.renderKPICards(data.kpis, data.comparison);
            this.renderSyncStatus(data.sync_status, data.recent_errors);

            return true;
        }

        renderKPICards(kpis, comparison) {
            const container = $('#kpi-cards');
            container.empty();

            const mainKpis = ['sessions', 'users', 'impressions', 'clicks', 'ctr', 'revenue'];

            mainKpis.forEach(kpiKey => {
                const kpi = kpis[kpiKey];
                const comp = comparison[kpiKey];

                if (!kpi) return;

                const changePercent = comp ? comp.change_percentage : 0;
                const changeClass = changePercent > 0 ? 'positive' : changePercent < 0 ? 'negative' : 'neutral';
                const changeIcon = changePercent > 0 ? '↗' : changePercent < 0 ? '↘' : '→';
                const description = this.getKpiDescription(kpiKey);
                const trendSummary = `${changePercent > 0 ? '+' : changePercent < 0 ? '−' : ''}${Math.abs(changePercent).toFixed(1)}% ${this.getChangePeriodText()}`;
                const sourcesLabel = this.getSourcesLabel(kpi.sources);
                const groupMeta = this.getKpiGroupMeta(kpiKey);
                const infoId = `fp-dms-kpi-tooltip-${kpiKey}`;
                const infoLabel = `${this.getKpiLabel(kpiKey)} – ${fpDmsDashboard.strings.kpi_info_hint}`;

                const card = $(`
                    <div class="fp-dms-kpi-card" data-kpi="${kpiKey}">
                        <div class="fp-dms-kpi-header">
                            <div class="fp-dms-kpi-heading">
                                <h3>${kpi.name || this.getKpiLabel(kpiKey)}</h3>
                                ${groupMeta ? `
                                    <span class="fp-dms-kpi-pill" data-tone="${groupMeta.tone}">
                                        ${groupMeta.label}
                                    </span>
                                ` : ''}
                            </div>
                            <div class="fp-dms-kpi-actions">
                                <div class="fp-dms-kpi-icon">${this.getKpiIcon(kpiKey)}</div>
                                <button type="button" class="fp-dms-kpi-info" aria-describedby="${infoId}" aria-expanded="false" aria-label="${infoLabel}">
                                    <span aria-hidden="true">?</span>
                                    <span class="fp-dms-kpi-tooltip" role="tooltip" id="${infoId}" aria-hidden="true">
                                        ${description}
                                    </span>
                                </button>
                            </div>
                        </div>
                        <div class="fp-dms-kpi-value">
                            ${kpi.formatted_value || this.formatValue(kpi.value, kpiKey)}
                        </div>
                        <div class="fp-dms-kpi-change ${changeClass}">
                            <span class="fp-dms-change-icon">${changeIcon}</span>
                            <span class="fp-dms-change-text">
                                ${trendSummary}
                            </span>
                        </div>
                        <div class="fp-dms-kpi-description">${description}</div>
                        <div class="fp-dms-kpi-sources">
                            ${sourcesLabel}
                        </div>
                    </div>
                `);

                card.attr({
                    'title': `${this.getKpiLabel(kpiKey)} · ${groupMeta ? groupMeta.label + ' · ' : ''}${trendSummary}`,
                    'aria-label': `${this.getKpiLabel(kpiKey)}. ${groupMeta ? groupMeta.label + '. ' : ''}${description}. ${trendSummary}. ${sourcesLabel}.`
                });

                card.attr('data-trend', changeClass);
                card.attr('tabindex', '0');
                card.attr('role', 'group');
                card.attr('aria-roledescription', 'Scheda KPI');

                container.append(card);

                this.setupKpiTooltip(card);
            });
        }

        setupKpiTooltip(card) {
            const infoButton = card.find('.fp-dms-kpi-info');

            if (!infoButton.length) {
                return;
            }

            const tooltip = infoButton.find('.fp-dms-kpi-tooltip');

            const setExpanded = (expanded) => {
                infoButton.attr('aria-expanded', expanded ? 'true' : 'false');
                tooltip.attr('aria-hidden', expanded ? 'false' : 'true');
                infoButton.toggleClass('is-active', expanded);
            };

            infoButton.on('focus', () => setExpanded(true));
            infoButton.on('mouseenter', () => setExpanded(true));
            infoButton.on('blur', () => setExpanded(false));
            infoButton.on('mouseleave', () => setExpanded(false));

            infoButton.on('click', (event) => {
                const originalEvent = event.originalEvent || {};
                const detail = typeof originalEvent.detail === 'number' ? originalEvent.detail : event.detail;
                const isKeyboard = detail === 0;

                if (isKeyboard) {
                    setExpanded(true);
                    return;
                }

                event.preventDefault();
                const isActive = infoButton.hasClass('is-active');
                setExpanded(!isActive);
            });

            infoButton.on('keydown', (event) => {
                if (event.key === 'Escape') {
                    setExpanded(false);
                    infoButton.trigger('blur');
                }
            });
        }

        getSourcesLabel(sources) {
            if (!sources || !Array.isArray(sources)) {
                return fpDmsDashboard?.strings?.sources?.demo || 'Demo data';
            }

            const count = sources.length;

            if (count === 0) {
                return fpDmsDashboard?.strings?.sources?.demo || 'Demo data';
            }

            const templates = fpDmsDashboard?.strings?.sources || {};
            const template = count === 1 ? templates.single : templates.multiple;

            if (!template) {
                return `${count} ${count === 1 ? 'sorgente' : 'sorgenti'}`;
            }

            return template.replace('%d', count.toString());
        }

        getKpiGroupMeta(kpiKey) {
            const config = {
                sessions: { group: 'audience', tone: 'ocean' },
                users: { group: 'audience', tone: 'ocean' },
                impressions: { group: 'awareness', tone: 'lavender' },
                clicks: { group: 'engagement', tone: 'orchid' },
                ctr: { group: 'engagement', tone: 'orchid' },
                revenue: { group: 'monetization', tone: 'amber' }
            };

            const groups = fpDmsDashboard?.strings?.kpi_groups || {};
            const fallback = { group: 'insight', tone: 'slate' };
            const meta = config[kpiKey] || fallback;
            const label = groups[meta.group] || groups.insight || meta.group;

            return {
                ...meta,
                label
            };
        }

        renderSyncStatus(syncStats, recentErrors) {
            const container = $('#sync-status');

            const errorRate = Number(syncStats.error_rate || 0);
            const statusMeta = this.getSyncStatusMeta(errorRate);

            const lastSync = this.formatDateTimeForDisplay(syncStats.last_sync) || 'Mai eseguita';
            const lastSuccessfulSync = this.formatDateTimeForDisplay(syncStats.last_successful_sync);
            const announcementId = 'fp-dms-sync-announcement';
            const regionLabel = `Stato sincronizzazioni ${statusMeta.label}. ${statusMeta.description}`;

            let html = `
                <div class="fp-dms-sync-card" data-status="${statusMeta.className}" role="region" aria-live="polite" aria-label="${regionLabel}" aria-describedby="${announcementId}">
                    <h3>Stato Sincronizzazioni</h3>
                    <span id="${announcementId}" class="screen-reader-text">${statusMeta.announcement || statusMeta.description}</span>
                    <div class="fp-dms-sync-overview ${statusMeta.className}">
                        <div class="fp-dms-sync-status-item">
                            <span class="fp-dms-sync-icon" aria-hidden="true">${statusMeta.icon}</span>
                            <div class="fp-dms-sync-details">
                                <div class="fp-dms-sync-title">
                                    Stato Generale: ${statusMeta.label}
                                </div>
                                <div class="fp-dms-sync-subtitle">
                                    Ultima sincronizzazione: ${lastSync}
                                </div>
                                ${lastSuccessfulSync ? `
                                    <div class="fp-dms-sync-subtitle">
                                        Ultima sincronizzazione riuscita: ${lastSuccessfulSync}
                                    </div>
                                ` : ''}
                            </div>
                        </div>
                    </div>

                    ${statusMeta.tip ? `<p class="fp-dms-sync-tip" role="note">${statusMeta.tip}</p>` : ''}

                    <div class="fp-dms-sync-stats">
                        <div class="fp-dms-sync-stat">
                            <span class="fp-dms-stat-label">Totali (7 giorni)</span>
                            <span class="fp-dms-stat-value">${syncStats.total_syncs || 0}</span>
                        </div>
                        <div class="fp-dms-sync-stat">
                            <span class="fp-dms-stat-label">Riuscite</span>
                            <span class="fp-dms-stat-value success">${syncStats.successful_syncs || 0}</span>
                        </div>
                        <div class="fp-dms-sync-stat">
                            <span class="fp-dms-stat-label">Fallite</span>
                            <span class="fp-dms-stat-value error">${syncStats.failed_syncs || 0}</span>
                        </div>
                        <div class="fp-dms-sync-stat">
                            <span class="fp-dms-stat-label">Tasso Errore</span>
                            <span class="fp-dms-stat-value ${statusMeta.className}">${errorRate.toFixed(1)}%</span>
                        </div>
                    </div>
                    <div class="fp-dms-sync-badges">
                        <span class="fp-dms-sync-badge ${statusMeta.className}" title="${statusMeta.description}">
                            <span class="fp-dms-sync-badge-icon" aria-hidden="true">${statusMeta.icon}</span>
                            <span class="fp-dms-sync-badge-label">${statusMeta.shortLabel}</span>
                        </span>
                        <span class="fp-dms-sync-badge" title="Ultimo aggiornamento dati">
                            <span class="fp-dms-sync-badge-icon" aria-hidden="true">🕒</span>
                            <span class="fp-dms-sync-badge-label">${lastSync}</span>
                        </span>
                    </div>
            `;

            if (recentErrors && recentErrors.length > 0) {
                html += `
                    <div class="fp-dms-recent-errors">
                        <h4>Errori Recenti</h4>
                        <ul class="fp-dms-error-list">
                `;
                
                recentErrors.slice(0, 3).forEach(error => {
                    const errorTime = this.formatDateTimeForDisplay(error.started_at) || error.started_at;
                    html += `
                        <li class="fp-dms-error-item">
                            <span class="fp-dms-error-time">${errorTime}</span>
                            <span class="fp-dms-error-message">${error.message}</span>
                        </li>
                    `;
                });
                
                html += `
                        </ul>
                    </div>
                `;
            }

            html += '</div>';
            container.html(html);
            container.attr('role', 'region');
            container.attr('aria-live', 'polite');
        }

        renderChart(chartData) {
            const ctx = document.getElementById('trend-chart');

            if (this.chart) {
                this.chart.destroy();
            }

            const theme = this.themeColors || this.resolveThemeColors();
            const borderColor = theme.accentStrong || theme.accent || '#0073aa';
            const backgroundColor = this.hexToRgba(borderColor, 0.18);
            const gridColor = this.hexToRgba(theme.primary, 0.12);
            const tickColor = this.hexToRgba(theme.primary, 0.72);

            this.chart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: chartData.labels,
                    datasets: [{
                        label: this.getKpiLabel(chartData.metric),
                        data: chartData.data,
                        borderColor: borderColor,
                        backgroundColor: backgroundColor,
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: this.hexToRgba(theme.surface, 0.95),
                            borderColor: this.hexToRgba(theme.primary, 0.2),
                            borderWidth: 1,
                            titleColor: theme.primary,
                            bodyColor: theme.primary,
                            mode: 'index',
                            intersect: false,
                            callbacks: {
                                label: (context) => {
                                    return `${this.getKpiLabel(chartData.metric)}: ${this.formatValue(context.parsed.y, chartData.metric)}`;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            display: true,
                            ticks: {
                                color: tickColor
                            },
                            grid: {
                                color: gridColor,
                                drawBorder: false
                            },
                            title: {
                                display: true,
                                text: 'Data',
                                color: tickColor
                            }
                        },
                        y: {
                            display: true,
                            ticks: {
                                color: tickColor
                            },
                            grid: {
                                color: gridColor,
                                drawBorder: false
                            },
                            title: {
                                display: true,
                                text: this.getKpiLabel(chartData.metric),
                                color: tickColor
                            },
                            beginAtZero: true
                        }
                    }
                }
            });
        }

        parseDateTime(dateTimeString) {
            if (!dateTimeString || typeof dateTimeString !== 'string') {
                return null;
            }

            const trimmed = dateTimeString.trim();

            if (!trimmed) {
                return null;
            }

            const normalized = trimmed.includes(' ') ? trimmed.replace(' ', 'T') : trimmed;
            let parsedDate = new Date(normalized);

            if (Number.isNaN(parsedDate.getTime())) {
                const match = trimmed.match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})(?::(\d{2}))?$/);

                if (match) {
                    const [, year, month, day, hour, minute, second = '0'] = match;

                    parsedDate = new Date(
                        parseInt(year, 10),
                        parseInt(month, 10) - 1,
                        parseInt(day, 10),
                        parseInt(hour, 10),
                        parseInt(minute, 10),
                        parseInt(second, 10)
                    );
                }
            }

            return Number.isNaN(parsedDate.getTime()) ? null : parsedDate;
        }

        formatDateTimeForDisplay(dateTimeString) {
            const parsedDate = this.parseDateTime(dateTimeString);

            return parsedDate ? parsedDate.toLocaleString('it-IT') : null;
        }

        getKpiLabel(kpi) {
            const labels = {
                sessions: 'Sessioni',
                users: 'Utenti',
                pageviews: 'Visualizzazioni Pagina',
                impressions: 'Impressioni',
                clicks: 'Click',
                ctr: 'CTR',
                revenue: 'Fatturato',
                conversions: 'Conversioni',
                organic_clicks: 'Click Organici',
                organic_impressions: 'Impressioni Organiche'
            };
            return labels[kpi] || kpi;
        }

        loadCoreWebVitals() {
            const params = {
                action: 'fp_dms_get_core_web_vitals',
                _wpnonce: fpDmsDashboard.nonce,
                client_id: this.currentFilters.client_id,
                origin_url: window.location.origin
            };

            $.get(fpDmsDashboard.ajax_url, params)
                .done((response) => {
                    if (response.success) {
                        this.renderCoreWebVitals(response.data);
                    } else {
                        // Show user-friendly error message
                        $('#cwv-widgets').html('<p>Unable to load Core Web Vitals data</p>');
                        if (window.WP_DEBUG) {
                            console.error('Core Web Vitals error:', response.data);
                        }
                    }
                })
                .fail(() => {
                    $('#cwv-widgets').html('<p>Network error loading Core Web Vitals</p>');
                    if (window.WP_DEBUG) {
                        console.error('Core Web Vitals network error');
                    }
                });
        }

        renderCoreWebVitals(data) {
            const { metrics, statuses, recommendations, score } = data;
            const cwvContainer = $('#cwv-widgets');

            // Clear existing content
            cwvContainer.empty();

            // Render Core Web Vitals widgets
            const vitalsOrder = ['lcp', 'inp', 'cls'];
            vitalsOrder.forEach(metric => {
                if (metrics[metric] !== undefined) {
                    const widget = this.createCWVWidget(metric, metrics[metric], statuses[metric], score.individual[metric]);
                    cwvContainer.append(widget);
                }
            });

            // Add overall performance score
            if (score.overall > 0) {
                const scoreWidget = this.createPerformanceScoreWidget(score);
                cwvContainer.append(scoreWidget);
            }

            // Render recommendations if available
            if (recommendations && recommendations.length > 0) {
                this.renderCWVRecommendations(recommendations);
            }
        }

        createCWVWidget(metric, value, status, individualScore) {
            const metricNames = {
                lcp: fpDmsDashboard.strings.lcp,
                inp: fpDmsDashboard.strings.inp,
                cls: fpDmsDashboard.strings.cls
            };

            const statusLabels = {
                good: fpDmsDashboard.strings.good,
                needs_improvement: fpDmsDashboard.strings.needs_improvement,
                poor: fpDmsDashboard.strings.poor
            };

            const ariaLabel = `${metricNames[metric] || metric.toUpperCase()} ${status.formatted_value}. Stato ${statusLabels[status.status] || status.status}.`;

            return $(`
                <div class="fp-dms-cwv-widget status-${status.status}" data-status="${status.status}" tabindex="0" role="group" aria-label="${ariaLabel}">
                    <div class="fp-dms-cwv-metric-name">${metricNames[metric] || metric.toUpperCase()}</div>
                    <div class="fp-dms-cwv-metric-value">${status.formatted_value}</div>
                    <div class="fp-dms-cwv-metric-status status-${status.status}">
                        ${statusLabels[status.status] || status.status}
                    </div>
                    ${individualScore ? `
                        <div class="fp-dms-cwv-score">
                            <div class="fp-dms-cwv-score-title">Score</div>
                            <div class="fp-dms-cwv-score-value">${individualScore}/100</div>
                        </div>
                    ` : ''}
                </div>
            `);
        }

        createPerformanceScoreWidget(score) {
            const palette = this.themeColors || this.resolveThemeColors();
            const gradeColors = {
                A: palette.success,
                B: palette.accent,
                C: palette.warning,
                D: palette.warning,
                F: palette.error
            };
            const gradeColor = gradeColors[score.grade] || palette.primary;

            return $(`
                <div class="fp-dms-cwv-widget">
                    <div class="fp-dms-cwv-metric-name">${fpDmsDashboard.strings.performance_score}</div>
                    <div class="fp-dms-cwv-metric-value">
                        ${score.overall}/100
                        <span class="fp-dms-cwv-score-grade" style="background-color: ${gradeColor}; color: white;">
                            ${score.grade}
                        </span>
                    </div>
                </div>
            `);
        }

        renderCWVRecommendations(recommendations) {
            const container = $('#cwv-recommendations');
            
            if (recommendations.length === 0) {
                container.hide();
                return;
            }

            container.empty();
            container.append(`<h3>${fpDmsDashboard.strings.recommendations}</h3>`);

            recommendations.forEach(rec => {
                const ariaLabel = `${rec.title}. Priorità ${rec.priority}. ${rec.description}`;
                const recElement = $(`
                    <div class="fp-dms-cwv-recommendation fp-dms-cwv-priority-${rec.priority}" tabindex="0" role="article" aria-label="${ariaLabel}">
                        <div class="fp-dms-cwv-recommendation-title">${rec.title}</div>
                        <div class="fp-dms-cwv-recommendation-desc">${rec.description}</div>
                        <ul class="fp-dms-cwv-recommendation-actions">
                            ${rec.actions.map(action => `<li>${action}</li>`).join('')}
                        </ul>
                    </div>
                `);
                container.append(recElement);
            });

            container.show();
        }

        getKpiIcon(kpi) {
            const icons = {
                sessions: '👥',
                users: '👤',
                pageviews: '📄',
                impressions: '👁️',
                clicks: '👆',
                ctr: '📊',
                revenue: '💰',
                conversions: '🎯',
                organic_clicks: '🔍',
                organic_impressions: '🌐',
                lcp: '🚀',
                inp: '⚡',
                cls: '📐'
            };
            return icons[kpi] || '📈';
        }

        formatValue(value, kpi) {
            if (typeof value !== 'number') {
                value = parseFloat(value) || 0;
            }

            switch (kpi) {
                case 'revenue':
                    return '€' + value.toLocaleString('it-IT', { minimumFractionDigits: 2 });
                case 'ctr':
                    return (value * 100).toFixed(2) + '%';
                case 'bounce_rate':
                    return value.toFixed(1) + '%';
                case 'lcp':
                case 'inp':
                    return Math.round(value).toLocaleString('it-IT') + ' ms';
                case 'cls':
                    return value.toFixed(3);
                default:
                    return value.toLocaleString('it-IT');
            }
        }

        getChangePeriodText() {
            return 'vs periodo precedente';
        }

        getInitialThemePreference() {
            const stored = this.getStoredThemePreference();
            return stored || 'system';
        }

        getStoredThemePreference() {
            if (typeof window === 'undefined' || !window.localStorage) {
                return null;
            }

            try {
                const stored = window.localStorage.getItem('fpDmsTheme');
                if (stored === 'light' || stored === 'dark' || stored === 'system') {
                    return stored;
                }
            } catch (error) {
                if (window.WP_DEBUG) {
                    console.warn('FP DMS dashboard: unable to read theme preference', error);
                }
            }

            return null;
        }

        setStoredThemePreference(preference) {
            if (typeof window === 'undefined' || !window.localStorage) {
                return;
            }

            try {
                if (preference === 'light' || preference === 'dark' || preference === 'system') {
                    window.localStorage.setItem('fpDmsTheme', preference);
                } else {
                    window.localStorage.removeItem('fpDmsTheme');
                }
            } catch (error) {
                if (window.WP_DEBUG) {
                    console.warn('FP DMS dashboard: unable to persist theme preference', error);
                }
            }
        }

        getThemeStrings() {
            if (typeof fpDmsDashboard !== 'undefined'
                && fpDmsDashboard.strings
                && fpDmsDashboard.strings.theme) {
                return fpDmsDashboard.strings.theme;
            }

            return {};
        }

        getNextThemePreference(current) {
            const order = ['system', 'dark', 'light'];
            const index = order.indexOf(current);
            return order[(index + 1) % order.length] || 'system';
        }

        getSystemTheme() {
            if (typeof window === 'undefined' || typeof window.matchMedia !== 'function') {
                return 'light';
            }

            return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }

        getEffectiveTheme(preference) {
            if (preference === 'dark' || preference === 'light') {
                return preference;
            }

            return this.getSystemTheme();
        }

        applyTheme(preference = 'system', shouldPersist = true) {
            if (typeof document === 'undefined') {
                return;
            }

            this.themePreference = preference;
            const root = document.documentElement;
            const effectiveTheme = this.getEffectiveTheme(preference);

            if (!root) {
                return;
            }

            if (preference === 'system') {
                root.removeAttribute('data-fp-dms-theme');
            } else {
                root.setAttribute('data-fp-dms-theme', effectiveTheme);
            }

            if (effectiveTheme === 'dark') {
                root.classList.add('fp-dms-dark-mode');
            } else {
                root.classList.remove('fp-dms-dark-mode');
            }

            if (shouldPersist) {
                this.setStoredThemePreference(preference);
            }

            this.themeColors = this.resolveThemeColors();
            this.updateThemeToggleLabel();
            this.refreshChartTheme();
        }

        updateThemeToggleLabel() {
            if (!this.$themeToggle || !this.$themeToggle.length) {
                return;
            }

            const strings = this.getThemeStrings();
            const current = this.themePreference || 'system';
            const icons = {
                system: '🖥️',
                dark: '🌙',
                light: '☀️'
            };
            const labels = {
                system: strings.system || 'Tema: sistema',
                dark: strings.dark || 'Tema: scuro',
                light: strings.light || 'Tema: chiaro'
            };
            const statusMap = {
                system: strings.status_system || labels.system,
                dark: strings.status_dark || labels.dark,
                light: strings.status_light || labels.light
            };
            const actions = {
                system: strings.switch_to_dark || 'Attiva il tema scuro',
                dark: strings.switch_to_light || 'Attiva il tema chiaro',
                light: strings.switch_to_system || 'Segui il tema di sistema'
            };
            const resetHint = strings.reset_hint || '';

            const nextAction = actions[current] || actions.system;
            const composedTitle = [nextAction, current !== 'system' ? resetHint : '']
                .filter(Boolean)
                .join(' · ');

            this.$themeToggle.attr('data-mode', current);
            this.$themeToggle.attr('aria-label', nextAction);
            this.$themeToggle.attr('title', composedTitle);

            this.$themeToggle.find('.fp-dms-theme-toggle-icon').text(icons[current] || icons.system);
            this.$themeToggle.find('.fp-dms-theme-toggle-label').text(labels[current] || labels.system);

            const $status = this.$themeToggle.find('.fp-dms-theme-toggle-status');
            if ($status.length) {
                $status.text(statusMap[current] || labels[current]);
            }
        }

        refreshChartTheme() {
            if (!this.chart || !this.chart.data || !this.chart.data.datasets || !this.chart.data.datasets.length) {
                return;
            }

            const theme = this.themeColors || this.resolveThemeColors();
            const borderColor = theme.accentStrong || theme.accent || '#0073aa';
            const backgroundColor = this.hexToRgba(borderColor, 0.18);
            const gridColor = this.hexToRgba(theme.primary, 0.12);
            const tickColor = this.hexToRgba(theme.primary, 0.72);

            const dataset = this.chart.data.datasets[0];
            dataset.borderColor = borderColor;
            dataset.backgroundColor = backgroundColor;

            if (this.chart.options && this.chart.options.scales) {
                if (this.chart.options.scales.x) {
                    this.chart.options.scales.x.grid.color = gridColor;
                    this.chart.options.scales.x.ticks.color = tickColor;
                    if (this.chart.options.scales.x.title) {
                        this.chart.options.scales.x.title.color = tickColor;
                    }
                }

                if (this.chart.options.scales.y) {
                    this.chart.options.scales.y.grid.color = gridColor;
                    this.chart.options.scales.y.ticks.color = tickColor;
                    if (this.chart.options.scales.y.title) {
                        this.chart.options.scales.y.title.color = tickColor;
                    }
                }
            }

            if (this.chart.options && this.chart.options.plugins && this.chart.options.plugins.tooltip) {
                this.chart.options.plugins.tooltip.backgroundColor = this.hexToRgba(theme.surface, 0.95);
                this.chart.options.plugins.tooltip.borderColor = this.hexToRgba(theme.primary, 0.2);
                this.chart.options.plugins.tooltip.titleColor = theme.primary;
                this.chart.options.plugins.tooltip.bodyColor = theme.primary;
            }

            this.chart.update('none');
        }

        watchSystemTheme() {
            if (typeof window === 'undefined' || typeof window.matchMedia !== 'function') {
                return;
            }

            const query = window.matchMedia('(prefers-color-scheme: dark)');
            const listener = () => {
                if (this.themePreference === 'system') {
                    this.applyTheme('system', false);
                }
            };

            if (typeof query.addEventListener === 'function') {
                query.addEventListener('change', listener);
            } else if (typeof query.addListener === 'function') {
                query.addListener(listener);
            }

            this.prefersColorSchemeQuery = { query, listener };
        }

        resolveThemeColors() {
            if (typeof window === 'undefined' || typeof window.getComputedStyle !== 'function') {
                return {
                    primary: '#5b4bff',
                    accent: '#0ea5e9',
                    accentStrong: '#2563eb',
                    success: '#16a34a',
                    warning: '#f97316',
                    error: '#dc2626',
                    surface: '#ffffff'
                };
            }

            const styles = window.getComputedStyle(document.documentElement);
            const readVar = (token, fallback) => {
                const value = styles.getPropertyValue(token);
                return value ? value.trim() || fallback : fallback;
            };

            return {
                primary: readVar('--fp-dms-primary', '#5b4bff'),
                accent: readVar('--fp-dms-accent', '#0ea5e9'),
                accentStrong: readVar('--fp-dms-accent-strong', '#2563eb'),
                success: readVar('--fp-dms-success', '#16a34a'),
                warning: readVar('--fp-dms-warning', '#f97316'),
                error: readVar('--fp-dms-error', '#dc2626'),
                surface: readVar('--fp-dms-surface', '#ffffff')
            };
        }

        hexToRgba(color, alpha = 1) {
            if (!color) {
                return `rgba(91, 75, 255, ${alpha})`;
            }

            const trimmed = color.trim();

            if (trimmed.startsWith('rgba')) {
                return trimmed.replace(/rgba\(([^)]+)\)/, (match, values) => {
                    const parts = values.split(',').map(part => part.trim());
                    parts[3] = String(alpha);
                    return `rgba(${parts.join(', ')})`;
                });
            }

            if (trimmed.startsWith('rgb')) {
                return trimmed.replace('rgb', 'rgba').replace(')', `, ${alpha})`);
            }

            const normalized = trimmed.replace('#', '');

            if (![3, 6].includes(normalized.length)) {
                return `rgba(91, 75, 255, ${alpha})`;
            }

            const hex = normalized.length === 3
                ? normalized.split('').map(char => char + char).join('')
                : normalized;

            const r = parseInt(hex.slice(0, 2), 16);
            const g = parseInt(hex.slice(2, 4), 16);
            const b = parseInt(hex.slice(4, 6), 16);

            return `rgba(${r}, ${g}, ${b}, ${alpha})`;
        }

        showError(message) {
            if (window.WP_DEBUG) {
                console.error('Dashboard error:', message);
            }
            $('#dashboard-loading').hide();
            $('#dashboard-content').hide();
            $('#dashboard-empty').show();
            this.updateEmptyMessage(message);
        }

        updateEmptyMessage(message = '') {
            const $message = $('#dashboard-empty .fp-dms-empty-message');

            if (!$message.length) {
                return;
            }

            if (message) {
                $message.text(message).attr('aria-hidden', 'false').show();
            } else {
                $message.text('').attr('aria-hidden', 'true').hide();
            }
        }

        getKpiDescription(kpi) {
            const descriptions = {
                sessions: 'Numero totale di sessioni generate dalle sorgenti monitorate.',
                users: 'Utenti unici che hanno visitato il sito nel periodo selezionato.',
                impressions: 'Volte in cui i tuoi contenuti sono stati mostrati agli utenti.',
                clicks: 'Click registrati sulle tue campagne e sui contenuti monitorati.',
                ctr: 'Rapporto tra click e impressioni delle campagne tracciate.',
                revenue: 'Entrate attribuite alle sorgenti connesse durante il periodo.',
                conversions: 'Azioni completate dagli utenti (form, acquisti, lead).'
            };

            return descriptions[kpi] || 'Indicatore di performance aggregato per il marketing digitale.';
        }

        getSyncStatusMeta(errorRate) {
            if (errorRate > 10) {
                return {
                    className: 'error',
                    icon: '⛔',
                    label: 'Critico',
                    shortLabel: 'Errori elevati',
                    description: 'Più del 10% delle sincronizzazioni è fallito. Verifica le connessioni.',
                    announcement: 'Stato sincronizzazioni critico: oltre il 10% delle operazioni non è riuscito.',
                    tip: 'Controlla le integrazioni con più errori e aggiorna le credenziali direttamente dalle impostazioni del plugin.'
                };
            }

            if (errorRate > 5) {
                return {
                    className: 'warning',
                    icon: '⚠️',
                    label: 'Attenzione',
                    shortLabel: 'Verifica',
                    description: 'Alcune sincronizzazioni non sono andate a buon fine nelle ultime 24 ore.',
                    announcement: 'Stato sincronizzazioni con avvisi: controlla i log per verificare gli errori recenti.',
                    tip: 'Esamina i log recenti e, se necessario, rilancia manualmente le sincronizzazioni dalle impostazioni collegate.'
                };
            }

            return {
                className: 'success',
                icon: '✅',
                label: 'Ottimale',
                shortLabel: 'In salute',
                description: 'Sincronizzazioni stabili e senza errori rilevanti.',
                announcement: 'Sincronizzazioni in salute: nessun errore significativo rilevato.',
                tip: 'Tutto aggiornato: mantieni attive le automazioni per conservare questo livello di affidabilità.'
            };
        }
    }

    // Initialize dashboard when DOM is ready
    $(document).ready(function() {
        new FPDashboard();
    });

})(jQuery);
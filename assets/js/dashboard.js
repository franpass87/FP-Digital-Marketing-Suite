/**
 * FP Digital Marketing Suite - Dashboard JavaScript
 */

(function($) {
    'use strict';

    class FPDashboard {
        constructor() {
            this.chart = null;
            this.currentFilters = this.getInitialFilters();
            this.init();
        }

        init() {
            this.bindEvents();
            this.loadDashboardData();
        }

        bindEvents() {
            // Filter events
            $('#apply-filters').on('click', () => this.onFiltersChange());
            $('#chart-metric').on('change', () => this.onChartMetricChange());
            
            // Auto-refresh every 5 minutes
            setInterval(() => this.loadDashboardData(), 5 * 60 * 1000);
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
        }

        showContent() {
            $('#dashboard-loading').hide();
            $('#dashboard-content').show();
            $('#dashboard-empty').hide();
        }

        showEmpty() {
            $('#dashboard-loading').hide();
            $('#dashboard-content').hide();
            $('#dashboard-empty').show();
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
                        this.renderDashboard(response.data);
                        this.showContent();
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
                    console.error('Failed to load chart data');
                });
        }

        renderDashboard(data) {
            this.renderKPICards(data.kpis, data.comparison);
            this.renderSyncStatus(data.sync_status, data.recent_errors);
            this.loadChartData($('#chart-metric').val());
        }

        renderKPICards(kpis, comparison) {
            const container = $('#kpi-cards');
            container.empty();

            const mainKpis = ['sessions', 'users', 'impressions', 'clicks', 'ctr', 'revenue'];
            
            mainKpis.forEach(kpiKey => {
                const kpi = kpis[kpiKey];
                const comp = comparison[kpiKey];
                
                if (!kpi) return;

                const changePercent = comp ? comp.change_percent : 0;
                const changeClass = changePercent > 0 ? 'positive' : changePercent < 0 ? 'negative' : 'neutral';
                const changeIcon = changePercent > 0 ? '↗' : changePercent < 0 ? '↘' : '→';

                const card = $(`
                    <div class="fp-dms-kpi-card" data-kpi="${kpiKey}">
                        <div class="fp-dms-kpi-header">
                            <h3>${kpi.name || this.getKpiLabel(kpiKey)}</h3>
                            <div class="fp-dms-kpi-icon">${this.getKpiIcon(kpiKey)}</div>
                        </div>
                        <div class="fp-dms-kpi-value">
                            ${kpi.formatted_value || this.formatValue(kpi.value, kpiKey)}
                        </div>
                        <div class="fp-dms-kpi-change ${changeClass}">
                            <span class="fp-dms-change-icon">${changeIcon}</span>
                            <span class="fp-dms-change-text">
                                ${Math.abs(changePercent).toFixed(1)}% 
                                ${this.getChangePeriodText()}
                            </span>
                        </div>
                        <div class="fp-dms-kpi-sources">
                            ${kpi.sources ? kpi.sources.length + ' sorgenti' : 'Demo data'}
                        </div>
                    </div>
                `);

                container.append(card);
            });
        }

        renderSyncStatus(syncStats, recentErrors) {
            const container = $('#sync-status');
            
            const errorRate = syncStats.error_rate || 0;
            const statusClass = errorRate > 10 ? 'error' : errorRate > 5 ? 'warning' : 'success';
            const statusIcon = errorRate > 10 ? '❌' : errorRate > 5 ? '⚠️' : '✅';
            
            const lastSync = syncStats.last_sync ? 
                new Date(syncStats.last_sync).toLocaleString('it-IT') : 
                'Mai eseguita';

            let html = `
                <div class="fp-dms-sync-card">
                    <h3>Stato Sincronizzazioni</h3>
                    <div class="fp-dms-sync-overview ${statusClass}">
                        <div class="fp-dms-sync-status-item">
                            <span class="fp-dms-sync-icon">${statusIcon}</span>
                            <div class="fp-dms-sync-details">
                                <div class="fp-dms-sync-title">
                                    Stato Generale: ${this.getSyncStatusText(errorRate)}
                                </div>
                                <div class="fp-dms-sync-subtitle">
                                    Ultima sincronizzazione: ${lastSync}
                                </div>
                            </div>
                        </div>
                    </div>
                    
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
                            <span class="fp-dms-stat-value ${statusClass}">${errorRate.toFixed(1)}%</span>
                        </div>
                    </div>
            `;

            if (recentErrors && recentErrors.length > 0) {
                html += `
                    <div class="fp-dms-recent-errors">
                        <h4>Errori Recenti</h4>
                        <ul class="fp-dms-error-list">
                `;
                
                recentErrors.slice(0, 3).forEach(error => {
                    const errorTime = new Date(error.started_at).toLocaleString('it-IT');
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
        }

        renderChart(chartData) {
            const ctx = document.getElementById('trend-chart');
            
            if (this.chart) {
                this.chart.destroy();
            }

            this.chart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: chartData.labels,
                    datasets: [{
                        label: this.getKpiLabel(chartData.metric),
                        data: chartData.data,
                        borderColor: '#0073aa',
                        backgroundColor: 'rgba(0, 115, 170, 0.1)',
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
                            title: {
                                display: true,
                                text: 'Data'
                            }
                        },
                        y: {
                            display: true,
                            title: {
                                display: true,
                                text: this.getKpiLabel(chartData.metric)
                            },
                            beginAtZero: true
                        }
                    }
                }
            });
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
                organic_impressions: '🌐'
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
                default:
                    return value.toLocaleString('it-IT');
            }
        }

        getChangePeriodText() {
            return 'vs periodo precedente';
        }

        getSyncStatusText(errorRate) {
            if (errorRate > 10) return 'Critico';
            if (errorRate > 5) return 'Attenzione';
            return 'Ottimale';
        }

        showError(message) {
            console.error('Dashboard error:', message);
            $('#dashboard-loading').hide();
            $('#dashboard-content').hide();
            $('#dashboard-empty').show();
        }
    }

    // Initialize dashboard when DOM is ready
    $(document).ready(function() {
        new FPDashboard();
    });

})(jQuery);
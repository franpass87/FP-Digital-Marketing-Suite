/**
 * Funnel Analysis JavaScript
 * 
 * Handles interactive funnel analysis charts and customer journey visualization
 */

(function($) {
    'use strict';

    let currentFunnelId = null;
    let funnelChart = null;
    let dropoffChart = null;

    $(document).ready(function() {
        initializeEventHandlers();
    });

    function initializeEventHandlers() {
        // Modal controls
        $('.view-funnel-analysis').on('click', function() {
            currentFunnelId = $(this).data('funnel-id');
            $('#funnel-analysis-modal').show();
            loadFunnelAnalysis();
        });

        $('.view-journey-details').on('click', function() {
            const sessionId = $(this).data('session-id');
            const clientId = $(this).data('client-id');
            loadJourneyDetails(sessionId, clientId);
        });

        $('.fp-modal-close').on('click', function() {
            $(this).closest('.fp-modal').hide();
        });

        // Close modal when clicking outside
        $('.fp-modal').on('click', function(e) {
            if (e.target === this) {
                $(this).hide();
            }
        });

        // Refresh analysis button
        $('#refresh-analysis').on('click', function() {
            loadFunnelAnalysis();
        });
    }

    function loadFunnelAnalysis() {
        if (!currentFunnelId) return;

        const startDate = $('#analysis-start-date').val();
        const endDate = $('#analysis-end-date').val();

        $.ajax({
            url: fpDmsFunnelAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'fp_dms_get_funnel_data',
                nonce: fpDmsFunnelAjax.nonce,
                funnel_id: currentFunnelId,
                start_date: startDate,
                end_date: endDate
            },
            success: function(response) {
                if (response.success) {
                    renderFunnelCharts(response.data);
                    renderTimeAnalysis(response.data.time_analysis);
                } else {
                    console.error('Failed to load funnel data:', response.data);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', error);
            }
        });
    }

    function renderFunnelCharts(data) {
        renderFunnelChart(data.conversion_data);
        renderDropoffChart(data.dropoff_data);
    }

    function renderFunnelChart(conversionData) {
        const ctx = document.getElementById('funnel-chart');
        if (!ctx) return;

        if (funnelChart) {
            funnelChart.destroy();
        }

        const labels = conversionData.map(stage => stage.stage_name || `Stage ${stage.step}`);
        const sessions = conversionData.map(stage => stage.sessions);
        const conversionRates = conversionData.map(stage => stage.conversion_rate);

        funnelChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Sessions',
                    data: sessions,
                    backgroundColor: 'rgba(54, 162, 235, 0.6)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1,
                    yAxisID: 'y'
                }, {
                    label: 'Conversion Rate (%)',
                    data: conversionRates,
                    type: 'line',
                    backgroundColor: 'rgba(255, 99, 132, 0.2)',
                    borderColor: 'rgba(255, 99, 132, 1)',
                    borderWidth: 2,
                    yAxisID: 'y1',
                    fill: false
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Funnel Conversion Analysis'
                    }
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Sessions'
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        beginAtZero: true,
                        max: 100,
                        title: {
                            display: true,
                            text: 'Conversion Rate (%)'
                        },
                        grid: {
                            drawOnChartArea: false
                        }
                    }
                }
            }
        });
    }

    function renderDropoffChart(dropoffData) {
        const ctx = document.getElementById('dropoff-chart');
        if (!ctx) return;

        if (dropoffChart) {
            dropoffChart.destroy();
        }

        const labels = dropoffData.map(stage => 
            `${stage.from_stage_name || `Stage ${stage.from_step}`} → ${stage.to_stage_name || `Stage ${stage.to_step}`}`
        );
        const dropoffRates = dropoffData.map(stage => stage.dropoff_rate);
        const dropoffSessions = dropoffData.map(stage => stage.dropoff_sessions);

        dropoffChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Drop-off Rate (%)',
                    data: dropoffRates,
                    backgroundColor: 'rgba(255, 159, 64, 0.6)',
                    borderColor: 'rgba(255, 159, 64, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Stage Drop-off Analysis'
                    },
                    tooltip: {
                        callbacks: {
                            afterLabel: function(context) {
                                const index = context.dataIndex;
                                return `Sessions lost: ${dropoffSessions[index]}`;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        title: {
                            display: true,
                            text: 'Drop-off Rate (%)'
                        }
                    }
                }
            }
        });
    }

    function renderTimeAnalysis(timeData) {
        const container = $('#time-analysis-content');
        if (!container.length || !timeData) return;

        const html = `
            <div class="time-analysis-grid">
                <div class="time-metric">
                    <h4>Average Time to Convert</h4>
                    <span class="metric-value">${formatHours(timeData.avg_hours_to_convert)}</span>
                </div>
                <div class="time-metric">
                    <h4>Median Time to Convert</h4>
                    <span class="metric-value">${formatHours(timeData.median_hours_to_convert)}</span>
                </div>
                <div class="time-metric">
                    <h4>Fastest Conversion</h4>
                    <span class="metric-value">${formatHours(timeData.min_hours_to_convert)}</span>
                </div>
                <div class="time-metric">
                    <h4>Slowest Conversion</h4>
                    <span class="metric-value">${formatHours(timeData.max_hours_to_convert)}</span>
                </div>
                <div class="time-metric">
                    <h4>Total Conversions</h4>
                    <span class="metric-value">${timeData.total_conversions}</span>
                </div>
            </div>
        `;

        container.html(html);
    }

    function formatHours(hours) {
        if (hours < 1) {
            return Math.round(hours * 60) + ' minutes';
        } else if (hours < 24) {
            return Math.round(hours * 10) / 10 + ' hours';
        } else {
            const days = Math.floor(hours / 24);
            const remainingHours = Math.round(hours % 24);
            return `${days}d ${remainingHours}h`;
        }
    }

    function loadJourneyDetails(sessionId, clientId) {
        $.ajax({
            url: fpDmsFunnelAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'fp_dms_get_journey_data',
                nonce: fpDmsFunnelAjax.nonce,
                session_id: sessionId,
                client_id: clientId
            },
            success: function(response) {
                if (response.success) {
                    renderJourneyDetails(response.data);
                    $('#journey-details-modal').show();
                } else {
                    console.error('Failed to load journey data:', response.data);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', error);
            }
        });
    }

    function renderJourneyDetails(journeyData) {
        const container = $('#journey-details-content');
        if (!container.length) return;

        let html = `
            <div class="journey-overview">
                <h3>Journey Overview</h3>
                <div class="journey-stats">
                    <div class="stat">
                        <label>Session ID:</label>
                        <span>${journeyData.session_id}</span>
                    </div>
                    <div class="stat">
                        <label>User ID:</label>
                        <span>${journeyData.user_id || 'Anonymous'}</span>
                    </div>
                    <div class="stat">
                        <label>Total Events:</label>
                        <span>${journeyData.statistics.total_events}</span>
                    </div>
                    <div class="stat">
                        <label>Pageviews:</label>
                        <span>${journeyData.statistics.pageviews}</span>
                    </div>
                    <div class="stat">
                        <label>Duration:</label>
                        <span>${formatSeconds(journeyData.statistics.duration_seconds)}</span>
                    </div>
                    <div class="stat">
                        <label>Total Value:</label>
                        <span>${journeyData.statistics.total_value} EUR</span>
                    </div>
                </div>
            </div>

            <div class="journey-path">
                <h3>Journey Path</h3>
                <div class="path-timeline">
        `;

        journeyData.journey_path.forEach((event, index) => {
            html += `
                <div class="path-event">
                    <div class="event-marker">${index + 1}</div>
                    <div class="event-details">
                        <div class="event-type">${event.event_type}</div>
                        <div class="event-name">${event.event_name}</div>
                        ${event.page_url ? `<div class="event-url">${event.page_url}</div>` : ''}
                        <div class="event-time">${formatTimestamp(event.timestamp)}</div>
                    </div>
                </div>
            `;
        });

        html += `
                </div>
            </div>

            <div class="journey-touchpoints">
                <h3>Marketing Touchpoints</h3>
                <div class="touchpoints-list">
        `;

        journeyData.touchpoints.forEach(touchpoint => {
            html += `
                <div class="touchpoint">
                    <div class="touchpoint-source">${touchpoint.source}/${touchpoint.medium}</div>
                    <div class="touchpoint-details">
                        <span>Campaign: ${touchpoint.campaign}</span>
                        <span>Touches: ${touchpoint.touch_count}</span>
                        <span>Value: ${touchpoint.total_value} EUR</span>
                    </div>
                </div>
            `;
        });

        html += `
                </div>
            </div>
        `;

        container.html(html);
    }

    function formatSeconds(seconds) {
        if (seconds < 60) {
            return seconds + 's';
        } else if (seconds < 3600) {
            return Math.floor(seconds / 60) + 'm ' + (seconds % 60) + 's';
        } else {
            const hours = Math.floor(seconds / 3600);
            const minutes = Math.floor((seconds % 3600) / 60);
            return hours + 'h ' + minutes + 'm';
        }
    }

    function formatTimestamp(timestamp) {
        const date = new Date(timestamp);
        return date.toLocaleString();
    }

})(jQuery);
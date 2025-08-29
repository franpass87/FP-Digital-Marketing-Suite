/**
 * Anomaly Detection Admin JavaScript
 * 
 * @package FP_Digital_Marketing_Suite
 */

jQuery(document).ready(function($) {

    // Handle anomaly notice dismissal
    $(document).on('click', '.fp-anomaly-notice .notice-dismiss', function() {
        var notice = $(this).closest('.fp-anomaly-notice');
        var noticeKey = notice.data('notice-key');
        
        if (noticeKey) {
            $.ajax({
                url: fp_dms_anomaly_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'dismiss_anomaly_notice',
                    notice_key: noticeKey,
                    nonce: fp_dms_anomaly_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        notice.fadeOut();
                    }
                }
            });
        }
    });

    // Handle anomaly acknowledgment
    $(document).on('click', '.acknowledge-anomaly', function(e) {
        e.preventDefault();
        
        var button = $(this);
        var anomalyId = button.data('anomaly-id');
        
        if (!anomalyId) {
            return;
        }

        if (!confirm(fp_dms_anomaly_admin.strings.confirm_acknowledge)) {
            return;
        }

        button.prop('disabled', true).text('Riconoscendo...');

        $.ajax({
            url: fp_dms_anomaly_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'acknowledge_anomaly',
                anomaly_id: anomalyId,
                nonce: fp_dms_anomaly_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    var row = button.closest('tr');
                    row.addClass('acknowledged');
                    button.replaceWith('<span class="dashicons dashicons-yes" style="color: #007cba;" title="Riconosciuta"></span>');
                } else {
                    alert('Errore durante il riconoscimento dell\'anomalia.');
                    button.prop('disabled', false).text('Riconosci');
                }
            },
            error: function() {
                alert('Errore durante il riconoscimento dell\'anomalia.');
                button.prop('disabled', false).text('Riconosci');
            }
        });
    });

    // Handle rule silencing
    $(document).on('click', '.silence-rule', function(e) {
        e.preventDefault();
        
        var button = $(this);
        var ruleId = button.data('rule-id');
        
        if (!ruleId) {
            return;
        }

        var hours = prompt(fp_dms_anomaly_admin.strings.confirm_silence, '24');
        
        if (!hours || isNaN(hours) || hours <= 0) {
            return;
        }

        button.prop('disabled', true).text('Silenziando...');

        $.ajax({
            url: fp_dms_anomaly_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'silence_anomaly_rule',
                rule_id: ruleId,
                hours: parseInt(hours),
                nonce: fp_dms_anomaly_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    location.reload(); // Refresh to show updated status
                } else {
                    alert('Errore durante il silenziamento della regola.');
                    button.prop('disabled', false).text('Silenzia');
                }
            },
            error: function() {
                alert('Errore durante il silenziamento della regola.');
                button.prop('disabled', false).text('Silenzia');
            }
        });
    });

    // Handle rule unsilencing
    $(document).on('click', '.unsilence-rule', function(e) {
        e.preventDefault();
        
        var button = $(this);
        var ruleId = button.data('rule-id');
        
        if (!ruleId) {
            return;
        }

        if (!confirm('Sei sicuro di voler riattivare questa regola?')) {
            return;
        }

        button.prop('disabled', true).text('Riattivando...');

        $.ajax({
            url: fp_dms_anomaly_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'silence_anomaly_rule',
                rule_id: ruleId,
                hours: 0, // 0 hours means unsilence
                nonce: fp_dms_anomaly_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    location.reload(); // Refresh to show updated status
                } else {
                    alert('Errore durante la riattivazione della regola.');
                    button.prop('disabled', false).text('Riattiva');
                }
            },
            error: function() {
                alert('Errore durante la riattivazione della regola.');
                button.prop('disabled', false).text('Riattiva');
            }
        });
    });

    // Auto-generate rule name based on selections
    function updateRuleName() {
        var clientSelect = $('#client_id');
        var metricSelect = $('#metric');
        var methodSelect = $('#detection_method');
        var nameInput = $('#name');

        // Only auto-generate if name field is empty
        if (nameInput.val().trim() !== '') {
            return;
        }

        var clientText = clientSelect.find('option:selected').text();
        var metricText = metricSelect.find('option:selected').text();
        var methodText = methodSelect.find('option:selected').text();

        if (clientText && metricText && methodText && 
            clientText !== 'Seleziona cliente' && 
            metricText !== 'Seleziona metrica' && 
            methodText !== 'Seleziona metodo') {
            
            var suggestedName = metricText + ' - ' + methodText + ' (' + clientText + ')';
            nameInput.val(suggestedName);
        }
    }

    // Trigger rule name update when selections change
    $('#client_id, #metric, #detection_method').on('change', updateRuleName);

    // Form validation
    $('form').on('submit', function(e) {
        var errors = [];

        // Required field validation
        if (!$('#client_id').val()) {
            errors.push('Seleziona un cliente.');
        }

        if (!$('#name').val().trim()) {
            errors.push('Inserisci un nome per la regola.');
        }

        if (!$('#metric').val()) {
            errors.push('Seleziona una metrica.');
        }

        if (!$('#detection_method').val()) {
            errors.push('Seleziona un metodo di rilevazione.');
        }

        // Email validation (if provided)
        var email = $('#notification_email').val();
        if (email && !isValidEmail(email)) {
            errors.push('L\'indirizzo email non è valido.');
        }

        // Parameter validation
        var method = $('#detection_method').val();
        if (method === 'z_score' || method === 'combined') {
            var zScore = parseFloat($('#z_score_threshold').val());
            if (isNaN(zScore) || zScore < 1) {
                errors.push('La soglia Z-Score deve essere almeno 1.0.');
            }
        }

        if (method === 'moving_average' || method === 'combined') {
            var bandDevs = parseFloat($('#band_deviations').val());
            if (isNaN(bandDevs) || bandDevs < 1) {
                errors.push('Le deviazioni standard per le bande devono essere almeno 1.0.');
            }

            var windowSize = parseInt($('#window_size').val());
            if (isNaN(windowSize) || windowSize < 3 || windowSize > 30) {
                errors.push('La finestra mobile deve essere tra 3 e 30 giorni.');
            }
        }

        var historicalDays = parseInt($('#historical_days').val());
        if (isNaN(historicalDays) || historicalDays < 7 || historicalDays > 90) {
            errors.push('I giorni storici devono essere tra 7 e 90.');
        }

        if (errors.length > 0) {
            e.preventDefault();
            
            // Show errors
            var errorHtml = '<div class="notice notice-error"><ul>';
            errors.forEach(function(error) {
                errorHtml += '<li>' + error + '</li>';
            });
            errorHtml += '</ul></div>';
            
            $('.wrap h1').after(errorHtml);
            
            // Scroll to top
            $('html, body').animate({ scrollTop: 0 }, 'fast');
            
            // Remove error notices after 5 seconds
            setTimeout(function() {
                $('.notice-error').fadeOut();
            }, 5000);
        }
    });

    // Email validation helper
    function isValidEmail(email) {
        var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    // Remove error class on field change
    $('input, select, textarea').on('change input', function() {
        $(this).removeClass('error');
    });

    // Enhanced UI interactions
    
    // Highlight metric info on hover
    $('#metric').on('change', function() {
        var selectedMetric = $(this).val();
        var $helpText = $('#metric-help');
        
        // Remove existing help text
        $helpText.remove();
        
        if (selectedMetric) {
            // Add help text for the selected metric
            var helpTexts = {
                'sessions': 'Numero di sessioni utente sul sito web',
                'users': 'Numero di utenti unici',
                'pageviews': 'Numero totale di visualizzazioni di pagina',
                'bounce_rate': 'Percentuale di sessioni con una sola pagina vista',
                'conversions': 'Numero di conversioni/obiettivi raggiunti',
                'revenue': 'Ricavi totali generati',
                'impressions': 'Numero di impressioni degli annunci',
                'clicks': 'Numero di clic sugli annunci',
                'ctr': 'Click-through rate (percentuale di clic)',
                'cpc': 'Costo per clic medio',
                'cost': 'Costo totale della campagna pubblicitaria'
            };
            
            var helpText = helpTexts[selectedMetric];
            if (helpText) {
                $(this).after('<p id="metric-help" class="description">' + helpText + '</p>');
            }
        }
    });

    // Detection method help text
    $('#detection_method').on('change', function() {
        var selectedMethod = $(this).val();
        var $helpText = $('#method-help');
        
        // Remove existing help text
        $helpText.remove();
        
        if (selectedMethod) {
            var helpTexts = {
                'z_score': 'Rileva anomalie quando il valore si discosta significativamente dalla media storica',
                'moving_average': 'Rileva anomalie quando il valore esce dalle bande di deviazione standard della media mobile',
                'combined': 'Usa entrambi i metodi per una rilevazione più accurata'
            };
            
            var helpText = helpTexts[selectedMethod];
            if (helpText) {
                $(this).after('<p id="method-help" class="description">' + helpText + '</p>');
            }
        }
    });

    // Initialize tooltips and help text
    $('#metric').trigger('change');
    $('#detection_method').trigger('change');

    // Charts and visualizations (if Chart.js is available)
    if (typeof Chart !== 'undefined') {
        // Initialize severity distribution chart if container exists
        var severityChartCanvas = document.getElementById('severity-chart');
        if (severityChartCanvas) {
            var severityData = JSON.parse(severityChartCanvas.dataset.chartData);
            
            new Chart(severityChartCanvas, {
                type: 'doughnut',
                data: {
                    labels: ['Critica', 'Alta', 'Media', 'Bassa'],
                    datasets: [{
                        data: [
                            severityData.critical,
                            severityData.high,
                            severityData.medium,
                            severityData.low
                        ],
                        backgroundColor: [
                            '#d63638',
                            '#dba617',
                            '#00a0d2',
                            '#007cba'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    legend: {
                        position: 'bottom'
                    }
                }
            });
        }
    }

    // Auto-refresh anomalies list every 5 minutes
    if (window.location.href.indexOf('action=anomalies') > -1) {
        setTimeout(function() {
            location.reload();
        }, 300000); // 5 minutes
    }

    // Keyboard shortcuts
    $(document).on('keydown', function(e) {
        // Ctrl+S to save form
        if (e.ctrlKey && e.keyCode === 83) {
            e.preventDefault();
            $('form').submit();
        }
        
        // Escape to go back
        if (e.keyCode === 27) {
            var backLink = $('a:contains("Torna")').first();
            if (backLink.length) {
                window.location.href = backLink.attr('href');
            }
        }
    });

    // Show loading indicators
    $(document).ajaxStart(function() {
        $('body').addClass('loading');
    }).ajaxStop(function() {
        $('body').removeClass('loading');
    });

});

// CSS for loading indicator
var loadingCSS = `
    .loading::after {
        content: '';
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.1);
        z-index: 9999;
        pointer-events: none;
    }
    
    .loading .spinner {
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        z-index: 10000;
    }
`;

// Inject CSS
var style = document.createElement('style');
style.type = 'text/css';
style.innerHTML = loadingCSS;
document.getElementsByTagName('head')[0].appendChild(style);
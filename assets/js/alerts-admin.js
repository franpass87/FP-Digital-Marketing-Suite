/**
 * Alerts Admin JavaScript
 *
 * @package FP_Digital_Marketing_Suite
 */

jQuery(document).ready(function($) {
    
    // Handle dismissal of alert notices
    $('.fp-alert-notice').on('click', '.notice-dismiss', function() {
        var $notice = $(this).closest('.fp-alert-notice');
        var noticeKey = $notice.data('notice-key');
        
        if (noticeKey) {
            $.ajax({
                url: fpDmsAlerts.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'dismiss_alert_notice',
                    notice_key: noticeKey,
                    nonce: fpDmsAlerts.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $notice.fadeOut();
                    }
                },
                error: function() {
                    // Notice will still be hidden by WordPress default behavior
                }
            });
        }
    });

    // Form validation for alert rules
    $('#fp-alert-rule-form').on('submit', function(e) {
        var isValid = true;
        var errors = [];

        // Check required fields
        var requiredFields = ['client_id', 'name', 'metric', 'condition', 'threshold_value'];
        requiredFields.forEach(function(field) {
            var $field = $('[name="' + field + '"]');
            if (!$field.val() || $field.val() === '') {
                isValid = false;
                $field.addClass('error');
                errors.push('Il campo ' + $field.closest('tr').find('label').text().replace(' *', '') + ' è obbligatorio.');
            } else {
                $field.removeClass('error');
            }
        });

        // Validate threshold value is a number
        var thresholdValue = parseFloat($('[name="threshold_value"]').val());
        if (isNaN(thresholdValue)) {
            isValid = false;
            $('[name="threshold_value"]').addClass('error');
            errors.push('Il valore soglia deve essere un numero valido.');
        }

        // Validate email format if provided
        var email = $('[name="notification_email"]').val();
        if (email && !isValidEmail(email)) {
            isValid = false;
            $('[name="notification_email"]').addClass('error');
            errors.push('L\'indirizzo email non è valido.');
        }

        if (!isValid) {
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

    // Remove error class on field change
    $('input, select, textarea').on('change input', function() {
        $(this).removeClass('error');
    });

    // Auto-suggest rule names based on selected metric and condition
    function updateRuleName() {
        var clientName = $('#client_id option:selected').text();
        var metricName = $('#metric option:selected').text();
        var condition = $('#condition option:selected').text();
        var threshold = $('#threshold_value').val();
        
        if (clientName && clientName !== 'Seleziona cliente' && 
            metricName && metricName !== 'Seleziona metrica' && 
            condition && condition !== 'Seleziona condizione' && 
            threshold) {
            
            var suggestedName = metricName + ' ' + condition.toLowerCase() + ' ' + threshold + ' - ' + clientName;
            
            // Only suggest if name field is empty
            if (!$('#name').val()) {
                $('#name').val(suggestedName);
            }
        }
    }

    $('#client_id, #metric, #condition').on('change', updateRuleName);
    $('#threshold_value').on('blur', updateRuleName);

    // Email validation helper
    function isValidEmail(email) {
        var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

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

    // Condition examples
    $('#condition').on('change', function() {
        var selectedCondition = $(this).val();
        var $helpText = $('#condition-help');
        
        // Remove existing help text
        $helpText.remove();
        
        if (selectedCondition) {
            var examples = {
                '>': 'Esempio: Sessions > 1000 (alert quando le sessioni superano 1000)',
                '<': 'Esempio: Bounce Rate < 0.3 (alert quando il bounce rate scende sotto 30%)',
                '>=': 'Esempio: Revenue >= 5000 (alert quando i ricavi raggiungono o superano 5000€)',
                '<=': 'Esempio: CTR <= 0.02 (alert quando il CTR scende a 2% o meno)',
                '=': 'Esempio: Conversions = 0 (alert quando non ci sono conversioni)',
                '!=': 'Esempio: Users != 0 (alert quando ci sono utenti, utile per controlli di funzionamento)'
            };
            
            var example = examples[selectedCondition];
            if (example) {
                $(this).closest('td').append('<p id="condition-help" class="description">' + example + '</p>');
            }
        }
    });

    // Live preview of rule logic
    function updateRulePreview() {
        var clientName = $('#client_id option:selected').text();
        var metricName = $('#metric option:selected').text();
        var condition = $('#condition').val();
        var threshold = $('#threshold_value').val();
        
        var $preview = $('#rule-preview');
        $preview.remove();
        
        if (clientName && clientName !== 'Seleziona cliente' && 
            metricName && metricName !== 'Seleziona metrica' && 
            condition && threshold) {
            
            var conditionText = $('#condition option:selected').text();
            var previewText = 'Anteprima: Alert attivato quando "' + metricName + '" per "' + clientName + '" è ' + conditionText.toLowerCase() + ' ' + threshold;
            
            $('#threshold_value').closest('tr').after(
                '<tr id="rule-preview"><td colspan="2"><div class="notice notice-info inline"><p><strong>' + previewText + '</strong></p></div></td></tr>'
            );
        }
    }

    $('#client_id, #metric, #condition, #threshold_value').on('change input', updateRulePreview);

    // Keyboard shortcuts
    $(document).on('keydown', function(e) {
        // Ctrl+S to save form
        if (e.ctrlKey && e.which === 83) {
            e.preventDefault();
            $('#submit').click();
        }
        
        // ESC to cancel editing
        if (e.which === 27) {
            var cancelLink = $('a:contains("Annulla")');
            if (cancelLink.length) {
                window.location.href = cancelLink.attr('href');
            }
        }
    });

    // Confirmation for rule deletion
    $('.button-link-delete').on('click', function(e) {
        var ruleName = $(this).closest('tr').find('td:first strong').text();
        if (!confirm('Sei sicuro di voler eliminare la regola "' + ruleName + '"?\n\nQuesta azione non può essere annullata.')) {
            e.preventDefault();
            return false;
        }
    });

    // Auto-refresh logs table every 30 seconds if on logs tab
    if (window.location.href.indexOf('tab=logs') > -1) {
        setInterval(function() {
            location.reload();
        }, 30000);
    }
    
    // Add tooltips to action buttons
    $('.button').each(function() {
        var $button = $(this);
        var text = $button.text().trim();
        
        if (text === 'Modifica') {
            $button.attr('title', 'Modifica questa regola di alert');
        } else if (text === 'Elimina') {
            $button.attr('title', 'Elimina definitivamente questa regola');
        }
    });

});
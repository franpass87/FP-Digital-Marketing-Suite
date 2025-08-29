/**
 * Segmentation Admin JavaScript
 */

(function($) {
    'use strict';

    // Rule type field mappings
    const ruleTypeFields = {
        'event': {
            'signup': 'Registrazione',
            'purchase': 'Acquisto',
            'lead_submit': 'Invio Lead',
            'contact_form': 'Form di Contatto',
            'download': 'Download',
            'subscribe': 'Iscrizione Newsletter',
            'video_watch': 'Visualizzazione Video'
        },
        'utm': {
            'utm_source': 'Sorgente UTM',
            'utm_medium': 'Medium UTM',
            'utm_campaign': 'Campagna UTM',
            'utm_term': 'Termine UTM',
            'utm_content': 'Contenuto UTM'
        },
        'device': {
            'device_type': 'Tipo Dispositivo'
        },
        'geography': {
            'country': 'Paese'
        },
        'behavior': {
            'visit_frequency': 'Frequenza Visite',
            'total_events': 'Totale Eventi',
            'recency': 'Ultima Attività (giorni)'
        },
        'value': {
            'total_value': 'Valore Totale'
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        initRulesBuilder();
        initSegmentActions();
        initFormValidation();
    });

    /**
     * Initialize rules builder functionality
     */
    function initRulesBuilder() {
        // Handle rule type change
        $(document).on('change', '.rule-type', function() {
            updateFieldOptions($(this));
        });

        // Handle add condition button
        $('#add-condition').on('click', function() {
            addConditionRow();
        });

        // Handle remove condition button
        $(document).on('click', '.remove-condition', function() {
            removeConditionRow($(this));
        });

        // Handle rule field change for dynamic value hints
        $(document).on('change', '.rule-field, .rule-operator', function() {
            updateValueHints($(this).closest('.condition-row'));
        });

        // Initialize existing conditions
        $('.condition-row').each(function() {
            const $row = $(this);
            const $typeSelect = $row.find('.rule-type');
            if ($typeSelect.val()) {
                updateFieldOptions($typeSelect);
            }
        });

        // Auto-preview on form changes
        debounce(function() {
            previewSegment();
        }, 1000);

        $(document).on('change input', '#segment-form input, #segment-form select, #segment-form textarea', debounce(function() {
            previewSegment();
        }, 1000));
    }

    /**
     * Update field options based on rule type
     */
    function updateFieldOptions($typeSelect) {
        const $row = $typeSelect.closest('.condition-row');
        const $fieldSelect = $row.find('.rule-field');
        const ruleType = $typeSelect.val();
        const currentField = $fieldSelect.val();

        // Clear existing options
        $fieldSelect.empty().append('<option value="">Seleziona campo</option>');

        if (ruleType && ruleTypeFields[ruleType]) {
            const fields = ruleTypeFields[ruleType];
            Object.keys(fields).forEach(function(fieldKey) {
                const selected = fieldKey === currentField ? ' selected' : '';
                $fieldSelect.append(`<option value="${fieldKey}"${selected}>${fields[fieldKey]}</option>`);
            });
        }

        updateValueHints($row);
    }

    /**
     * Update value input hints based on selected field and operator
     */
    function updateValueHints($row) {
        const $valueInput = $row.find('.rule-value');
        const ruleType = $row.find('.rule-type').val();
        const field = $row.find('.rule-field').val();
        const operator = $row.find('.rule-operator').val();

        let placeholder = 'Valore';
        let title = '';

        // Set specific hints based on rule combinations
        if (ruleType === 'event' && operator === 'greater_than') {
            placeholder = 'es. 5 (più di 5 eventi)';
            title = 'Inserisci un numero';
        } else if (ruleType === 'utm' && field === 'utm_source') {
            placeholder = 'es. google, facebook, newsletter';
            title = 'Nome della sorgente di traffico';
        } else if (ruleType === 'device' && field === 'device_type') {
            placeholder = 'mobile, desktop, tablet';
            title = 'Tipo di dispositivo';
        } else if (ruleType === 'geography' && field === 'country') {
            placeholder = 'es. IT, US, FR (codice paese)';
            title = 'Codice paese ISO a 2 lettere';
        } else if (ruleType === 'behavior') {
            if (field === 'visit_frequency' || field === 'total_events') {
                placeholder = 'es. 10 (numero)';
                title = 'Inserisci un numero';
            } else if (field === 'recency') {
                placeholder = 'es. 30 (giorni)';
                title = 'Numero di giorni';
            }
        } else if (ruleType === 'value') {
            placeholder = 'es. 100.00 (valore in euro)';
            title = 'Valore monetario';
        }

        $valueInput.attr('placeholder', placeholder).attr('title', title);
    }

    /**
     * Add new condition row
     */
    function addConditionRow() {
        const $container = $('#conditions-container');
        const newIndex = $container.find('.condition-row').length;
        
        const $newRow = $(`
            <div class="condition-row" data-index="${newIndex}">
                <select name="rules[conditions][${newIndex}][type]" class="rule-type">
                    <option value="">Seleziona tipo regola</option>
                    <option value="event">Eventi</option>
                    <option value="utm">Sorgenti UTM</option>
                    <option value="device">Dispositivo</option>
                    <option value="geography">Geografia</option>
                    <option value="behavior">Comportamento</option>
                    <option value="value">Valore</option>
                </select>
                <select name="rules[conditions][${newIndex}][field]" class="rule-field">
                    <option value="">Seleziona campo</option>
                </select>
                <select name="rules[conditions][${newIndex}][operator]" class="rule-operator">
                    <option value="">Seleziona operatore</option>
                    <option value="equals">Uguale a</option>
                    <option value="not_equals">Diverso da</option>
                    <option value="contains">Contiene</option>
                    <option value="not_contains">Non contiene</option>
                    <option value="greater_than">Maggiore di</option>
                    <option value="less_than">Minore di</option>
                    <option value="in_last_days">Negli ultimi N giorni</option>
                    <option value="not_in_last_days">Non negli ultimi N giorni</option>
                </select>
                <input type="text" name="rules[conditions][${newIndex}][value]" placeholder="Valore" class="rule-value">
                <button type="button" class="button remove-condition">Rimuovi</button>
            </div>
        `);
        
        $container.append($newRow);
        updateConditionIndexes();
    }

    /**
     * Remove condition row
     */
    function removeConditionRow($button) {
        $button.closest('.condition-row').remove();
        updateConditionIndexes();
        
        // Ensure at least one condition row exists
        if ($('#conditions-container .condition-row').length === 0) {
            addConditionRow();
        }
    }

    /**
     * Update condition indexes after add/remove
     */
    function updateConditionIndexes() {
        $('#conditions-container .condition-row').each(function(index) {
            const $row = $(this);
            $row.attr('data-index', index);
            
            $row.find('select, input').each(function() {
                const name = $(this).attr('name');
                if (name) {
                    const newName = name.replace(/\[\d+\]/, `[${index}]`);
                    $(this).attr('name', newName);
                }
            });
        });
    }

    /**
     * Initialize segment actions (evaluate, view members, etc.)
     */
    function initSegmentActions() {
        // Handle evaluate segment button
        $('.evaluate-segment').on('click', function() {
            const segmentId = $(this).data('segment-id');
            const $button = $(this);
            
            evaluateSegment(segmentId, $button);
        });

        // Handle view members button (if implemented in list view)
        $('.view-members').on('click', function() {
            const segmentId = $(this).data('segment-id');
            viewSegmentMembers(segmentId);
        });
    }

    /**
     * Evaluate segment via AJAX
     */
    function evaluateSegment(segmentId, $button) {
        const originalText = $button.text();
        
        $button.text(fpSegmentation.strings.evaluating)
               .prop('disabled', true)
               .addClass('loading');

        $.post(fpSegmentation.ajax_url, {
            action: 'fp_segmentation_action',
            action_type: 'evaluate_segment',
            segment_id: segmentId,
            nonce: fpSegmentation.nonce
        })
        .done(function(response) {
            if (response.success) {
                showNotice(response.data.message, 'success');
                
                // Update member count in UI if visible
                const $memberCount = $button.closest('tr').find('td').eq(2);
                if ($memberCount.length) {
                    $memberCount.text(response.data.results.member_count.toLocaleString());
                }
                
                // Show detailed results if available
                if (response.data.results) {
                    showEvaluationResults(response.data.results);
                }
            } else {
                showNotice(response.data || fpSegmentation.strings.error, 'error');
            }
        })
        .fail(function() {
            showNotice(fpSegmentation.strings.error, 'error');
        })
        .always(function() {
            $button.text(originalText)
                   .prop('disabled', false)
                   .removeClass('loading');
        });
    }

    /**
     * Preview segment based on current form data
     */
    function previewSegment() {
        const $form = $('#segment-form');
        if (!$form.length) return;

        const clientId = $form.find('#client_id').val();
        const rules = collectRulesData();

        if (!clientId || !rules.conditions.length) {
            $('.segment-preview').remove();
            return;
        }

        $.post(fpSegmentation.ajax_url, {
            action: 'fp_segmentation_action',
            action_type: 'preview_segment',
            client_id: clientId,
            rules: rules,
            nonce: fpSegmentation.nonce
        })
        .done(function(response) {
            if (response.success) {
                showSegmentPreview(response.data.preview);
            }
        });
    }

    /**
     * Collect rules data from form
     */
    function collectRulesData() {
        const rules = {
            logic: $('select[name="rules[logic]"]').val() || 'AND',
            conditions: []
        };

        $('.condition-row').each(function() {
            const $row = $(this);
            const condition = {
                type: $row.find('.rule-type').val(),
                field: $row.find('.rule-field').val(),
                operator: $row.find('.rule-operator').val(),
                value: $row.find('.rule-value').val()
            };

            if (condition.type && condition.field && condition.operator) {
                rules.conditions.push(condition);
            }
        });

        return rules;
    }

    /**
     * Show segment preview
     */
    function showSegmentPreview(previewText) {
        $('.segment-preview').remove();
        
        const $preview = $(`
            <div class="segment-preview">
                <div class="preview-text">${previewText}</div>
            </div>
        `);
        
        $('#rules-builder').after($preview);
    }

    /**
     * Show evaluation results
     */
    function showEvaluationResults(results) {
        $('.evaluation-results').remove();
        
        const $results = $(`
            <div class="evaluation-results">
                <h4>Risultati Valutazione</h4>
                <div class="evaluation-stats">
                    <span class="evaluation-stat members">Membri: ${results.member_count}</span>
                    <span class="evaluation-stat new">Nuovi: ${results.new_members}</span>
                    <span class="evaluation-stat removed">Rimossi: ${results.removed_members}</span>
                </div>
                ${results.errors.length > 0 ? '<div class="errors"><strong>Errori:</strong><ul><li>' + results.errors.join('</li><li>') + '</li></ul></div>' : ''}
            </div>
        `);
        
        $('.segments-header, .segment-preview').last().after($results);
    }

    /**
     * Initialize form validation
     */
    function initFormValidation() {
        $('#segment-form').on('submit', function(e) {
            const $form = $(this);
            const isValid = validateSegmentForm($form);
            
            if (!isValid) {
                e.preventDefault();
                return false;
            }
        });
    }

    /**
     * Validate segment form
     */
    function validateSegmentForm($form) {
        let isValid = true;
        const errors = [];

        // Check required fields
        const name = $form.find('#segment_name').val().trim();
        const clientId = $form.find('#client_id').val();

        if (!name) {
            errors.push('Il nome del segmento è obbligatorio');
            isValid = false;
        }

        if (!clientId) {
            errors.push('Seleziona un cliente');
            isValid = false;
        }

        // Check rules
        const rules = collectRulesData();
        if (rules.conditions.length === 0) {
            errors.push('Aggiungi almeno una regola di segmentazione');
            isValid = false;
        }

        // Validate each condition
        rules.conditions.forEach((condition, index) => {
            if (!condition.type || !condition.field || !condition.operator) {
                errors.push(`Regola ${index + 1}: tutti i campi sono obbligatori`);
                isValid = false;
            }
        });

        if (!isValid) {
            showNotice('Errori nel form:<br>' + errors.join('<br>'), 'error');
        }

        return isValid;
    }

    /**
     * Show notice message
     */
    function showNotice(message, type = 'info') {
        $('.segment-notice').remove();
        
        const $notice = $(`
            <div class="segment-notice ${type}">
                ${message}
            </div>
        `);
        
        $('.wrap h1').after($notice);
        
        // Auto-hide success messages
        if (type === 'success') {
            setTimeout(() => {
                $notice.fadeOut();
            }, 5000);
        }
    }

    /**
     * Debounce function to limit API calls
     */
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

})(jQuery);
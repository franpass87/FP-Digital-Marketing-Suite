/**
 * Template Presets Handler
 * Gestisce l'applicazione dei blueprint ai template con supporto TinyMCE
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        const select = document.getElementById('fpdms-template-blueprint');
        if (!select) {
            return;
        }

        const applyBtn = document.getElementById('fpdms-apply-template-blueprint');
        const desc = document.getElementById('fpdms-template-blueprint-description');
        const nameInput = document.getElementById('fpdms-template-name');
        const descriptionInput = document.getElementById('fpdms-template-description');

        if (!applyBtn || !desc) {
            return;
        }

        // Ottieni i dati dei blueprints dal data attribute
        const blueprints = window.fpdmsTemplateBlueprints || {};

        /**
         * Marca un campo come modificato manualmente
         */
        const markManual = function (el) {
            if (!el) {
                return;
            }
            el.addEventListener('input', function () {
                if (el.dataset) {
                    delete el.dataset.autofilled;
                }
            });
        };

        markManual(nameInput);
        markManual(descriptionInput);

        /**
         * Riempie un campo se è vuoto o se è stato precedentemente auto-riempito
         */
        const fillField = function (el, value, force) {
            if (!el) {
                return;
            }
            
            const currentValue = typeof el.value === 'string' ? el.value : '';
            const currentTrimmed = currentValue.trim();

            if (force || !currentTrimmed || (el.dataset && el.dataset.autofilled === '1')) {
                if (currentValue !== value) {
                    el.value = value;
                    if (el.dataset) {
                        el.dataset.autofilled = '1';
                    }
                    // Trigger change event per eventuali listener
                    el.dispatchEvent(new Event('input', { bubbles: true }));
                } else if (el.dataset) {
                    el.dataset.autofilled = '1';
                }
            }
        };

        /**
         * Imposta il contenuto nell'editor TinyMCE o nel textarea
         */
        const setEditorContent = function (content, force) {
            const editorId = 'fpdms-template-content';
            
            // Prova prima con TinyMCE
            if (typeof tinymce !== 'undefined') {
                const editor = tinymce.get(editorId);
                if (editor) {
                    const currentContent = editor.getContent();
                    const currentTrimmed = currentContent.trim();
                    const textarea = document.getElementById(editorId);
                    
                    if (force || !currentTrimmed || (textarea && textarea.dataset && textarea.dataset.autofilled === '1')) {
                        editor.setContent(content);
                        if (textarea && textarea.dataset) {
                            textarea.dataset.autofilled = '1';
                        }
                        console.log('✓ Preset applicato a TinyMCE');
                    }
                    return;
                }
            }

            // Fallback: textarea normale (se TinyMCE non è ancora pronto)
            const textarea = document.getElementById(editorId);
            if (textarea) {
                fillField(textarea, content, force);
                console.log('✓ Preset applicato a textarea');
            }
        };

        /**
         * Applica il preset selezionato
         */
        const applyPreset = function (force) {
            const key = select.value;
            
            if (!key || !blueprints[key]) {
                console.warn('Nessun preset selezionato o preset non trovato:', key);
                return;
            }

            const preset = blueprints[key];
            console.log('Applicazione preset:', preset.name);

            fillField(nameInput, preset.name, force);
            fillField(descriptionInput, preset.description, force);
            setEditorContent(preset.content, force);
        };

        /**
         * Aggiorna la descrizione e abilita/disabilita il pulsante
         */
        const updateDescription = function () {
            const key = select.value;
            
            if (key && blueprints[key]) {
                desc.textContent = blueprints[key].description;
                applyBtn.disabled = false;
                
                // Auto-applica il preset quando viene selezionato (senza forzare)
                applyPreset(false);
            } else {
                desc.textContent = desc.dataset.default || '';
                applyBtn.disabled = true;
            }
        };

        // Event listeners
        select.addEventListener('change', updateDescription);
        
        applyBtn.addEventListener('click', function () {
            console.log('Click su "Usa preset"');
            applyPreset(true); // Forza l'applicazione
        });

        // Inizializzazione
        updateDescription();
        
        console.log('Template presets handler inizializzato');
    });
})();


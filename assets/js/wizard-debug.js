/**
 * Wizard Debug Helper
 * Script di debug per verificare il caricamento del wizard
 */
(function() {
    console.log('✅ wizard-debug.js caricato!');
    
    // Verifica jQuery
    if (typeof jQuery === 'undefined') {
        console.error('❌ jQuery non è caricato!');
        return;
    } else {
        console.log('✅ jQuery caricato:', jQuery.fn.jquery);
    }
    
    // Verifica presenza wizard container
    jQuery(document).ready(function($) {
        const $wizard = $('.fpdms-wizard');
        console.log('Wizard container trovato:', $wizard.length, $wizard);
        
        if ($wizard.length === 0) {
            console.warn('⚠️ Nessun container .fpdms-wizard trovato nella pagina');
        }
        
        // Verifica se i moduli ES6 sono supportati
        if ('noModule' in HTMLScriptElement.prototype) {
            console.log('✅ Browser supporta ES6 modules');
        } else {
            console.warn('⚠️ Browser non supporta ES6 modules');
        }
        
        // Lista tutti gli script caricati
        console.log('Scripts caricati:', Array.from(document.scripts).map(s => s.src));
        
        // Verifica window objects
        console.log('fpdmsWizard:', window.fpdmsWizard);
        console.log('fpdmsI18n:', window.fpdmsI18n);
        console.log('ajaxurl:', window.ajaxurl);
    });
})();

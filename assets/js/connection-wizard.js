/**
 * Connection Wizard Entry Point
 * Inizializza il wizard con architettura modulare
 */
import { ConnectionWizard } from './modules/wizard/core.js';
import { SELECTORS } from './modules/wizard/constants.js';

(function($) {
    'use strict';

    // Initialize wizard when DOM is ready
    $(document).ready(function() {
        const $wizard = $(SELECTORS.WIZARD);
        if ($wizard.length) {
            new ConnectionWizard($wizard);
        }
    });

})(jQuery);
/**
 * Wizard Template Selector
 * Gestisce la selezione dei template
 */
import { SELECTORS } from './constants.js';

// jQuery reference for ES6 modules
const $ = window.jQuery;

export class TemplateSelector {
    constructor($container, data) {
        this.$container = $container;
        this.data = data;
        this.handler = null;
    }

    init() {
        this.handler = (e) => {
            const $card = $(e.currentTarget);
            const templateId = $card.data('template-id');

            // Update UI
            this.$container.find(SELECTORS.TEMPLATE_CARD).removeClass('selected');
            $card.addClass('selected');
            
            // Update hidden input
            const $input = this.$container.find(SELECTORS.TEMPLATE_ID_INPUT);
            if ($input.length) {
                $input.val(templateId);
            }
            
            // Store in wizard data
            this.data.template_id = templateId;
        };

        this.$container.on('click', SELECTORS.TEMPLATE_CARD, this.handler);
    }

    cleanup() {
        if (this.handler) {
            this.$container.off('click', SELECTORS.TEMPLATE_CARD, this.handler);
            this.handler = null;
        }
    }
}
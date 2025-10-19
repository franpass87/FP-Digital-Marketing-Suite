/**
 * Wizard AutoDiscovery Handler
 * Gestisce la funzionalità di autodiscovery per risorse (properties, sites, etc.)
 */
import { SELECTORS } from './constants.js';

// jQuery reference for ES6 modules
const $ = window.jQuery;

export class AutoDiscoveryHandler {
    constructor($container, validator, provider) {
        this.$container = $container;
        this.validator = validator;
        this.provider = provider;
        this.isDiscovering = false;
    }

    init() {
        console.log('🔵 [DEBUG AUTODISCOVERY] AutoDiscoveryHandler.init() chiamato per provider:', this.provider);
        
        // Bind click events for discovery buttons
        this.$container.on('click', '.fpdms-btn-discover', (e) => {
            e.preventDefault();
            const $btn = $(e.currentTarget);
            const provider = $btn.data('provider') || this.provider;
            console.log('🔵 [DEBUG AUTODISCOVERY] Pulsante discover cliccato per provider:', provider);
            this.discoverResources(provider);
        });

        // Bind click events for resource selection
        this.$container.on('click', '.fpdms-resource-item', (e) => {
            e.preventDefault();
            const $item = $(e.currentTarget);
            const resourceId = $item.data('resource-id');
            const resourceName = $item.data('resource-name');
            const provider = $item.data('provider');
            console.log('🔵 [DEBUG AUTODISCOVERY] Risorsa selezionata:', { resourceId, resourceName, provider });
            this.selectResource(provider, resourceId, resourceName);
        });
    }

    async discoverResources(provider) {
        if (this.isDiscovering) {
            console.log('⚠️ [DEBUG AUTODISCOVERY] Discovery già in corso, ignoro richiesta');
            return;
        }

        console.log('🔵 [DEBUG AUTODISCOVERY] Inizio discovery per provider:', provider);
        this.isDiscovering = true;

        // Get service account data
        const serviceAccountData = this.getServiceAccountData();
        if (!serviceAccountData) {
            console.log('❌ [DEBUG AUTODISCOVERY] Nessun service account trovato');
            this.showError('Service account required for auto-discovery');
            this.isDiscovering = false;
            return;
        }

        const $btn = this.$container.find(`[data-provider="${provider}"]`);
        const $resourceList = this.$container.find(`#fpdms-discovered-${this.getResourceType(provider)}`);
        
        // Show loading state
        this.setButtonLoading($btn, true);
        this.showResourceList($resourceList, true);

        try {
            console.log('🔵 [DEBUG AUTODISCOVERY] Invio richiesta AJAX per discovery...');
            const response = await $.ajax({
                url: window.ajaxurl,
                method: 'POST',
                data: {
                    action: 'fpdms_discover_resources',
                    nonce: window.fpdmsWizard?.nonce,
                    provider: provider,
                    auth: JSON.stringify(serviceAccountData)
                }
            });

            console.log('🔵 [DEBUG AUTODISCOVERY] Risposta AJAX ricevuta:', response);

            if (response.success && response.data?.resources) {
                console.log('✅ [DEBUG AUTODISCOVERY] Discovery completato con successo, risorse trovate:', response.data.resources.length);
                this.displayResources($resourceList, response.data.resources, provider);
                this.showResourceList($resourceList, true);
            } else {
                console.log('❌ [DEBUG AUTODISCOVERY] Discovery fallito:', response.data?.message);
                this.showError(response.data?.message || 'Failed to discover resources');
                this.showResourceList($resourceList, false);
            }
        } catch (error) {
            console.log('❌ [DEBUG AUTODISCOVERY] Errore durante discovery:', error);
            this.showError('Network error during discovery');
            this.showResourceList($resourceList, false);
        } finally {
            this.setButtonLoading($btn, false);
            this.isDiscovering = false;
        }
    }

    getServiceAccountData() {
        // Try to get service account from various possible field names
        const possibleFields = [
            'auth[service_account]',
            'service_account',
            '#fpdms_service_account'
        ];

        for (const field of possibleFields) {
            const $field = this.$container.find(`[name="${field}"], ${field}`);
            if ($field.length && $field.val()?.trim()) {
                const value = $field.val().trim();
                try {
                    // Validate JSON format
                    JSON.parse(value);
                    return { service_account: value };
                } catch (e) {
                    console.log('⚠️ [DEBUG AUTODISCOVERY] Service account non è JSON valido:', e.message);
                }
            }
        }

        return null;
    }

    displayResources($container, resources, provider) {
        if (!resources || resources.length === 0) {
            $container.html('<p class="fpdms-no-resources">No resources found.</p>');
            return;
        }

        const resourceType = this.getResourceType(provider);
        const resourceTypeLabel = this.getResourceTypeLabel(provider);

        let html = `<div class="fpdms-resource-grid">`;
        
        resources.forEach((resource, index) => {
            const resourceId = resource.id || resource.property_id || resource.site_url || resource.customer_id || resource.account_id;
            const resourceName = resource.display_name || resource.name || resourceId;
            const resourceDescription = resource.website || resource.description || '';

            html += `
                <div class="fpdms-resource-item" 
                     data-resource-id="${resourceId}" 
                     data-resource-name="${resourceName}"
                     data-provider="${provider}">
                    <div class="fpdms-resource-header">
                        <h4>${resourceName}</h4>
                        <span class="fpdms-resource-id">${resourceId}</span>
                    </div>
                    ${resourceDescription ? `<p class="fpdms-resource-description">${resourceDescription}</p>` : ''}
                    <button type="button" class="button button-small fpdms-btn-select-resource">
                        ${window.fpdmsI18n?.selectResource || 'Select'} ${resourceTypeLabel}
                    </button>
                </div>
            `;
        });

        html += `</div>`;
        $container.html(html);
    }

    selectResource(provider, resourceId, resourceName) {
        console.log('🔵 [DEBUG AUTODISCOVERY] Selezione risorsa:', { provider, resourceId, resourceName });

        // Find the appropriate input field and set its value
        const fieldMap = {
            'ga4': 'config[property_id]',
            'gsc': 'config[site_url]',
            'google_ads': 'config[customer_id]',
            'meta_ads': 'config[account_id]'
        };

        const fieldName = fieldMap[provider];
        if (fieldName) {
            const $field = this.$container.find(`[name="${fieldName}"]`);
            if ($field.length) {
                $field.val(resourceId).trigger('input');
                console.log('✅ [DEBUG AUTODISCOVERY] Campo aggiornato:', fieldName, '=', resourceId);
                
                // Show success message
                this.showSuccess(`Selected ${resourceName} (${resourceId})`);
                
                // Hide resource list after selection
                const $resourceList = this.$container.find(`#fpdms-discovered-${this.getResourceType(provider)}`);
                this.showResourceList($resourceList, false);
            } else {
                console.log('❌ [DEBUG AUTODISCOVERY] Campo non trovato:', fieldName);
            }
        }
    }

    getResourceType(provider) {
        const typeMap = {
            'ga4': 'properties',
            'gsc': 'sites',
            'google_ads': 'customers',
            'meta_ads': 'accounts'
        };
        return typeMap[provider] || 'resources';
    }

    getResourceTypeLabel(provider) {
        const labelMap = {
            'ga4': 'Property',
            'gsc': 'Site',
            'google_ads': 'Customer',
            'meta_ads': 'Account'
        };
        return labelMap[provider] || 'Resource';
    }

    setButtonLoading($btn, isLoading) {
        if (isLoading) {
            $btn.prop('disabled', true);
            $btn.data('original-text', $btn.html());
            $btn.html('🔍 Discovering...');
        } else {
            $btn.prop('disabled', false);
            const originalText = $btn.data('original-text');
            if (originalText) {
                $btn.html(originalText);
            }
        }
    }

    showResourceList($container, show) {
        if (show) {
            $container.show();
        } else {
            $container.hide();
        }
    }

    showError(message) {
        console.log('❌ [DEBUG AUTODISCOVERY] Errore:', message);
        
        // Use the same error display method as the main wizard
        if (window.wp?.data?.dispatch) {
            window.wp.data.dispatch('core/notices').createErrorNotice(message);
        } else {
            // Fallback: show error message in a styled div
            const errorDiv = document.createElement('div');
            errorDiv.className = 'notice notice-error is-dismissible';
            errorDiv.innerHTML = `<p>${message}</p>`;
            const container = this.$container[0] || document.querySelector('.wrap');
            if (container) {
                container.insertBefore(errorDiv, container.firstChild);
                setTimeout(() => errorDiv.remove(), 5000);
            }
        }
    }

    showSuccess(message) {
        console.log('✅ [DEBUG AUTODISCOVERY] Successo:', message);
        
        if (window.wp?.data?.dispatch) {
            window.wp.data.dispatch('core/notices').createSuccessNotice(message);
        }
    }

    cleanup() {
        this.$container.off('click', '.fpdms-btn-discover');
        this.$container.off('click', '.fpdms-resource-item');
        this.isDiscovering = false;
    }
}

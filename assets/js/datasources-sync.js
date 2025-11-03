/**
 * Data Sources Sync Button Handler
 */
(function($) {
    'use strict';

    function initSyncButton() {
        // Gestisce sia il bottone in Data Sources che quello in Overview
        const syncButtons = [
            { btn: document.getElementById('fpdms-sync-datasources'), feedback: document.getElementById('fpdms-sync-feedback') },
            { btn: document.getElementById('fpdms-sync-datasources-overview'), feedback: document.getElementById('fpdms-sync-feedback-overview') }
        ];
        
        syncButtons.forEach(function(item) {
            if (!item.btn) {
                return;
            }
            
            item.btn.addEventListener('click', function(e) {
            e.preventDefault();
            
            const syncBtn = item.btn;
            const feedbackDiv = item.feedback;
            
            // Prendi client_id dal bottone o dal select cliente (Overview)
            let clientId = syncBtn.getAttribute('data-client-id');
            if (!clientId) {
                const clientSelect = document.getElementById('fpdms-overview-client');
                clientId = clientSelect ? clientSelect.value : null;
            }
            
            if (!clientId) {
                alert('Client ID not found');
                return;
            }
            
            // Disabilita il pulsante
            syncBtn.disabled = true;
            syncBtn.classList.add('is-syncing');
            
            // Mostra feedback con progress indicator
            if (feedbackDiv) {
                feedbackDiv.className = 'notice notice-info';
                feedbackDiv.innerHTML = `
                    <div class="fpdms-sync-progress">
                        <div class="fpdms-progress-container">
                            <div class="fpdms-progress-label">
                                <strong>Sincronizzazione in corso...</strong>
                                <span class="fpdms-progress-percent" id="sync-progress-percent">0%</span>
                            </div>
                            <div class="fpdms-progress-bar-container">
                                <div class="fpdms-progress-bar" id="sync-progress-bar" style="width:0%"></div>
                            </div>
                            <p style="margin-top:8px;font-size:13px;color:#6b7280;">Recupero dati da GA4, GSC, Google Ads, Meta Ads...</p>
                        </div>
                    </div>
                    <style>
                        .fpdms-sync-progress { padding: 4px 0; }
                        .fpdms-progress-container { margin: 8px 0; }
                        .fpdms-progress-label { font-size: 14px; font-weight: 600; color: #1f2937; margin-bottom: 8px; display: flex; justify-content: space-between; align-items: center; }
                        .fpdms-progress-percent { color: #667eea; font-weight: 700; }
                        .fpdms-progress-bar-container { width: 100%; height: 12px; background: #e5e7eb; border-radius: 6px; overflow: hidden; }
                        .fpdms-progress-bar { height: 100%; background: linear-gradient(90deg, #667eea 0%, #764ba2 100%); border-radius: 6px; transition: width 0.3s ease; position: relative; overflow: hidden; }
                        .fpdms-progress-bar::after { content: ""; position: absolute; top: 0; left: 0; bottom: 0; right: 0; background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent); animation: shimmer 2s infinite; }
                        @keyframes shimmer { 0% { transform: translateX(-100%); } 100% { transform: translateX(100%); } }
                    </style>
                `;
                feedbackDiv.style.display = 'block';
                
                // Simula progresso (dato che non abbiamo eventi reali dal backend)
                let progress = 0;
                const progressBar = document.getElementById('sync-progress-bar');
                const progressPercent = document.getElementById('sync-progress-percent');
                const progressInterval = setInterval(function() {
                    progress += Math.random() * 15;
                    if (progress > 90) progress = 90; // Cap at 90% until complete
                    if (progressBar && progressPercent) {
                        progressBar.style.width = progress + '%';
                        progressPercent.textContent = Math.round(progress) + '%';
                    }
                }, 500);
                
                // Salva interval per pulirlo dopo
                syncBtn.dataset.progressInterval = progressInterval;
            }
            
            // Chiamata API
            const url = fpdmsSyncData.restUrl + '?client_id=' + clientId;
            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': fpdmsSyncData.nonce
                }
            })
            .then(function(response) {
                if (!response.ok) {
                    return response.json().then(function(err) {
                        throw new Error(err.message || 'Sync failed');
                    });
                }
                return response.json();
            })
            .then(function(data) {
                // Stop progress simulation
                if (syncBtn.dataset.progressInterval) {
                    clearInterval(parseInt(syncBtn.dataset.progressInterval));
                }
                
                // Complete progress to 100%
                const progressBar = document.getElementById('sync-progress-bar');
                const progressPercent = document.getElementById('sync-progress-percent');
                if (progressBar && progressPercent) {
                    progressBar.style.width = '100%';
                    progressPercent.textContent = '100%';
                }
                
                // Conta successi/fallimenti
                let successCount = 0;
                let failCount = 0;
                
                if (data.results && typeof data.results === 'object') {
                    Object.keys(data.results).forEach(function(key) {
                        if (data.results[key].success) {
                            successCount++;
                        } else {
                            failCount++;
                        }
                    });
                }
                
                // Mostra risultato con Toast dopo breve delay
                setTimeout(function() {
                    // Hide feedback div
                    if (feedbackDiv) {
                        feedbackDiv.style.display = 'none';
                    }
                    
                    // Show toast notification
                    let message = 'Sincronizzazione completata! ';
                    if (successCount > 0) {
                        message += successCount + ' sorgente' + (successCount === 1 ? '' : 'i') + ' dati sincronizzata' + (successCount === 1 ? '' : 'e') + '. ';
                    }
                    if (failCount > 0) {
                        message += failCount + ' fallita' + (failCount === 1 ? '' : 'e') + '.';
                    }
                    
                    if (window.fpdmsToast) {
                        window.fpdmsToast.success(message, 5000);
                    }
                }, 500);
                
                // Riabilita pulsante
                syncBtn.disabled = false;
                syncBtn.classList.remove('is-syncing');
                
                // Se siamo in Overview, ricarica i dati senza reload della pagina
                if (document.getElementById('fpdms-overview-root')) {
                    // Trigger a custom event to reload overview data
                    setTimeout(function() {
                        const event = new CustomEvent('fpdms-reload-overview', { 
                            detail: { source: 'sync', clientId: clientId } 
                        });
                        document.dispatchEvent(event);
                    }, 500);
                }
            })
            .catch(function(error) {
                // Stop progress simulation
                if (syncBtn.dataset.progressInterval) {
                    clearInterval(parseInt(syncBtn.dataset.progressInterval));
                }
                
                // Hide feedback div and show toast error
                if (feedbackDiv) {
                    feedbackDiv.style.display = 'none';
                }
                
                if (window.fpdmsToast) {
                    window.fpdmsToast.error('Sincronizzazione fallita: ' + error.message, 6000);
                }
                
                // Riabilita pulsante
                syncBtn.disabled = false;
                syncBtn.classList.remove('is-syncing');
            });
            });
        });
    }

    // Init quando DOM pronto
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSyncButton);
    } else {
        initSyncButton();
    }

})(jQuery);


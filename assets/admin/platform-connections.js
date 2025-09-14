/**
 * Platform Connections Admin Interface JavaScript
 * 
 * @package FP_Digital_Marketing_Suite
 */

jQuery(document).ready(function($) {
    'use strict';

    // Test connection functionality
    $('.test-connection').on('click', function() {
        const $button = $(this);
        const platformId = $button.data('platform');
        const originalText = $button.text();

        // Disable button and show loading state
        $button.prop('disabled', true).text(fpPlatformConnections.strings.testing);

        // Add loading class to parent card
        $button.closest('.connection-card').addClass('testing');

        $.ajax({
            url: fpPlatformConnections.ajaxUrl,
            type: 'POST',
            data: {
                action: 'fp_test_connection',
                platform_id: platformId,
                nonce: fpPlatformConnections.nonce
            },
            success: function(response) {
                if (response.success) {
                    showTestResult($button, 'success', response.data.message || fpPlatformConnections.strings.testSuccess);
                } else {
                    showTestResult($button, 'error', response.data.message || response.data.error || fpPlatformConnections.strings.testFailed);
                }
            },
            error: function(xhr, status, error) {
                showTestResult($button, 'error', fpPlatformConnections.strings.testFailed + ': ' + error);
            },
            complete: function() {
                // Re-enable button and restore original text
                $button.prop('disabled', false).text(originalText);
                $button.closest('.connection-card').removeClass('testing');
            }
        });
    });

    // Refresh connections functionality
    $('#refresh-connections').on('click', function() {
        const $button = $(this);
        const originalText = $button.text();

        // Confirm action
        if (!confirm(fpPlatformConnections.strings.confirmRefresh)) {
            return;
        }

        // Disable button and show loading state
        $button.prop('disabled', true).text(fpPlatformConnections.strings.refreshing);

        // Add loading overlay to connections grid
        const $connectionsGrid = $('#connections-grid');
        $connectionsGrid.append('<div class="refresh-overlay"><div class="spinner"></div></div>');

        $.ajax({
            url: fpPlatformConnections.ajaxUrl,
            type: 'POST',
            data: {
                action: 'fp_refresh_connections',
                nonce: fpPlatformConnections.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Update connections grid
                    $connectionsGrid.html(response.data.html);
                    
                    // Update health score
                    updateHealthScore(response.data.health);
                    
                    // Show success message
                    showNotice('success', 'Stato delle connessioni aggiornato con successo.');
                    
                    // Re-bind event handlers for new content
                    bindConnectionEvents();
                } else {
                    showNotice('error', 'Errore durante l\'aggiornamento: ' + (response.data || 'Errore sconosciuto'));
                }
            },
            error: function(xhr, status, error) {
                showNotice('error', 'Errore durante l\'aggiornamento: ' + error);
            },
            complete: function() {
                // Re-enable button and restore original text
                $button.prop('disabled', false).text(originalText);
                $connectionsGrid.find('.refresh-overlay').remove();
            }
        });
    });

    /**
     * Show test result with visual feedback
     * 
     * @param {jQuery} $button Test button element
     * @param {string} type Result type ('success' or 'error')
     * @param {string} message Result message
     */
    function showTestResult($button, type, message) {
        const $card = $button.closest('.connection-card');
        
        // Remove any existing test result
        $card.find('.test-result').remove();
        
        // Create test result element
        const $result = $('<div class="test-result test-result-' + type + '">' + 
                         '<strong>' + (type === 'success' ? '✓' : '✗') + '</strong> ' + 
                         message + '</div>');
        
        // Add result to card
        $button.closest('.connection-actions').after($result);
        
        // Auto-hide after 5 seconds
        setTimeout(function() {
            $result.fadeOut(function() {
                $result.remove();
            });
        }, 5000);
    }

    /**
     * Update health score display
     * 
     * @param {Object} health Health score data
     */
    function updateHealthScore(health) {
        const $healthScore = $('.health-score');
        const $scoreNumber = $healthScore.find('.score-number');
        const $healthDetails = $('.health-details p');
        
        // Update score number
        $scoreNumber.text(health.score + '%');
        
        // Update status class
        $healthScore.removeClass('health-excellent health-good health-fair health-poor')
                   .addClass('health-' + health.status);
        
        // Update details text
        $healthDetails.text(health.connected_platforms + ' di ' + health.total_platforms + ' piattaforme connesse');
    }

    /**
     * Show admin notice
     * 
     * @param {string} type Notice type ('success', 'error', 'warning', 'info')
     * @param {string} message Notice message
     */
    function showNotice(type, message) {
        const $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
        
        // Insert notice after page title
        $('.wrap h1').after($notice);
        
        // Initialize dismiss functionality
        $notice.find('.notice-dismiss').on('click', function() {
            $notice.fadeOut();
        });
        
        // Auto-hide success notices after 3 seconds
        if (type === 'success') {
            setTimeout(function() {
                $notice.fadeOut();
            }, 3000);
        }
    }

    /**
     * Bind event handlers for dynamically loaded content
     */
    function bindConnectionEvents() {
        // Re-bind test connection events
        $('.test-connection').off('click').on('click', function() {
            const $button = $(this);
            const platformId = $button.data('platform');
            const originalText = $button.text();

            $button.prop('disabled', true).text(fpPlatformConnections.strings.testing);
            $button.closest('.connection-card').addClass('testing');

            $.ajax({
                url: fpPlatformConnections.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'fp_test_connection',
                    platform_id: platformId,
                    nonce: fpPlatformConnections.nonce
                },
                success: function(response) {
                    if (response.success) {
                        showTestResult($button, 'success', response.data.message || fpPlatformConnections.strings.testSuccess);
                    } else {
                        showTestResult($button, 'error', response.data.message || response.data.error || fpPlatformConnections.strings.testFailed);
                    }
                },
                error: function(xhr, status, error) {
                    showTestResult($button, 'error', fpPlatformConnections.strings.testFailed + ': ' + error);
                },
                complete: function() {
                    $button.prop('disabled', false).text(originalText);
                    $button.closest('.connection-card').removeClass('testing');
                }
            });
        });
    }

    // CSS for additional styling
    $('<style>')
        .prop('type', 'text/css')
        .html(`
            .connection-card.testing {
                opacity: 0.7;
                position: relative;
            }
            
            .test-result {
                margin-top: 10px;
                padding: 8px 12px;
                border-radius: 4px;
                font-size: 14px;
            }
            
            .test-result-success {
                background: #d1f2eb;
                color: #00695c;
                border: 1px solid #00a32a;
            }
            
            .test-result-error {
                background: #ffeaa7;
                color: #7d4cdb;
                border: 1px solid #d63638;
            }
            
            .refresh-overlay {
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(255, 255, 255, 0.8);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 10;
            }
            
            .refresh-overlay .spinner {
                width: 40px;
                height: 40px;
                border: 4px solid #f3f3f3;
                border-top: 4px solid #0073aa;
                border-radius: 50%;
                animation: spin 1s linear infinite;
            }
            
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
        `)
        .appendTo('head');
});
/**
 * FP Digital Marketing Suite - Performance & UX Optimizations
 * Enhanced AJAX handling, error management, and user experience improvements
 */

(function($) {
    'use strict';

    /**
     * Global optimization utilities
     */
    window.FP_DMS_Optimizations = {
        
        /**
         * Debounce function to limit API calls
         */
        debounce: function(func, wait, immediate) {
            let timeout;
            return function executedFunction() {
                const context = this;
                const args = arguments;
                const later = function() {
                    timeout = null;
                    if (!immediate) func.apply(context, args);
                };
                const callNow = immediate && !timeout;
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
                if (callNow) func.apply(context, args);
            };
        },

        /**
         * Throttle function for scroll/resize events
         */
        throttle: function(func, limit) {
            let inThrottle;
            return function() {
                const args = arguments;
                const context = this;
                if (!inThrottle) {
                    func.apply(context, args);
                    inThrottle = true;
                    setTimeout(() => inThrottle = false, limit);
                }
            };
        },

        /**
         * Enhanced AJAX wrapper with retry logic and error handling
         */
        ajaxRequest: function(options) {
            const defaults = {
                type: 'POST',
                dataType: 'json',
                timeout: 30000,
                retries: 3,
                retryDelay: 1000,
                showLoading: true,
                showError: true,
                loadingSelector: null,
                onRetry: null
            };

            const settings = $.extend({}, defaults, options);
            let retryCount = 0;

            function makeRequest() {
                // Show loading state
                if (settings.showLoading && settings.loadingSelector) {
                    $(settings.loadingSelector).addClass('fp-dms-loading');
                    $(settings.loadingSelector).append('<div class="fp-dms-loading-overlay"><div class="fp-dms-spinner"></div><span class="fp-dms-loading-text">Loading...</span></div>');
                }

                return $.ajax({
                    url: settings.url,
                    type: settings.type,
                    data: settings.data,
                    dataType: settings.dataType,
                    timeout: settings.timeout,
                    beforeSend: function(xhr) {
                        // Add nonce if available
                        if (window.fp_dms_nonce) {
                            xhr.setRequestHeader('X-WP-Nonce', window.fp_dms_nonce);
                        }
                        
                        // Rate limiting header
                        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                        
                        if (settings.beforeSend) {
                            settings.beforeSend(xhr);
                        }
                    }
                }).done(function(response) {
                    // Hide loading state
                    if (settings.showLoading && settings.loadingSelector) {
                        $(settings.loadingSelector).removeClass('fp-dms-loading');
                        $(settings.loadingSelector).find('.fp-dms-loading-overlay').remove();
                    }

                    // Announce to screen readers
                    FP_DMS_Optimizations.announceToScreenReader('Content loaded successfully');
                    
                    if (settings.success) {
                        settings.success(response);
                    }
                }).fail(function(xhr, textStatus, errorThrown) {
                    // Hide loading state
                    if (settings.showLoading && settings.loadingSelector) {
                        $(settings.loadingSelector).removeClass('fp-dms-loading');
                        $(settings.loadingSelector).find('.fp-dms-loading-overlay').remove();
                    }

                    // Retry logic
                    if (retryCount < settings.retries && (textStatus === 'timeout' || xhr.status >= 500)) {
                        retryCount++;
                        
                        if (settings.onRetry) {
                            settings.onRetry(retryCount, settings.retries);
                        }
                        
                        setTimeout(function() {
                            makeRequest();
                        }, settings.retryDelay * retryCount);
                        
                        return;
                    }

                    // Show error message
                    if (settings.showError) {
                        const errorMessage = FP_DMS_Optimizations.getErrorMessage(xhr, textStatus, errorThrown);
                        FP_DMS_Optimizations.showNotification(errorMessage, 'error');
                    }

                    // Announce error to screen readers
                    FP_DMS_Optimizations.announceToScreenReader('An error occurred while loading content');
                    
                    if (settings.error) {
                        settings.error(xhr, textStatus, errorThrown);
                    }
                });
            }

            return makeRequest();
        },

        /**
         * Get user-friendly error message
         */
        getErrorMessage: function(xhr, textStatus, errorThrown) {
            if (textStatus === 'timeout') {
                return 'Request timed out. Please check your connection and try again.';
            }
            
            if (xhr.status === 0) {
                return 'No connection. Please check your internet connection.';
            }
            
            if (xhr.status >= 500) {
                return 'Server error. Please try again later.';
            }
            
            if (xhr.status === 403) {
                return 'Permission denied. Please refresh the page and try again.';
            }
            
            if (xhr.status === 404) {
                return 'Requested resource not found.';
            }
            
            // Try to parse JSON response for custom error
            try {
                const response = JSON.parse(xhr.responseText);
                if (response.data && response.data.message) {
                    return response.data.message;
                }
                if (response.message) {
                    return response.message;
                }
            } catch (e) {
                // Ignore JSON parse errors
            }
            
            return 'An unexpected error occurred. Please try again.';
        },

        /**
         * Show notification message
         */
        showNotification: function(message, type = 'info', duration = 5000, dismissible = true) {
            const notificationContainer = this.getNotificationContainer();
            const notificationId = 'fp-notification-' + Date.now();
            
            const notification = $(`
                <div id="${notificationId}" class="fp-dms-${type}-message ${dismissible ? 'fp-dms-message-dismissible' : ''}" role="alert" aria-live="polite">
                    ${message}
                    ${dismissible ? '<button class="fp-dms-message-dismiss" aria-label="Dismiss notification">&times;</button>' : ''}
                </div>
            `);
            
            notificationContainer.append(notification);
            
            // Auto-dismiss
            if (duration > 0) {
                setTimeout(() => {
                    notification.fadeOut(300, () => notification.remove());
                }, duration);
            }
            
            // Manual dismiss
            if (dismissible) {
                notification.find('.fp-dms-message-dismiss').on('click', function() {
                    notification.fadeOut(300, () => notification.remove());
                });
            }
            
            return notificationId;
        },

        /**
         * Get or create notification container
         */
        getNotificationContainer: function() {
            let container = $('#fp-dms-notifications');
            if (container.length === 0) {
                container = $('<div id="fp-dms-notifications" style="position: fixed; top: 32px; right: 20px; z-index: 10000; max-width: 400px;"></div>');
                $('body').append(container);
            }
            return container;
        },

        /**
         * Announce content to screen readers
         */
        announceToScreenReader: function(message) {
            let liveRegion = $('#fp-dms-live-region');
            if (liveRegion.length === 0) {
                liveRegion = $('<div id="fp-dms-live-region" class="fp-dms-live-region" aria-live="polite" aria-atomic="true"></div>');
                $('body').append(liveRegion);
            }
            
            // Clear previous message
            liveRegion.text('');
            
            // Add new message with slight delay to ensure it's announced
            setTimeout(() => {
                liveRegion.text(message);
            }, 100);
        },

        /**
         * Enhanced form validation
         */
        validateForm: function(formSelector, rules = {}) {
            const form = $(formSelector);
            let isValid = true;
            const errors = [];

            // Clear previous errors
            form.find('.fp-dms-field-error').remove();
            form.find('.fp-dms-error').removeClass('fp-dms-error');

            // Validate each field
            Object.keys(rules).forEach(fieldName => {
                const field = form.find(`[name="${fieldName}"]`);
                const fieldRules = rules[fieldName];
                
                if (fieldRules.required && !field.val().trim()) {
                    this.addFieldError(field, fieldRules.required);
                    errors.push(`${fieldRules.label || fieldName} is required`);
                    isValid = false;
                }
                
                if (fieldRules.email && field.val() && !this.isValidEmail(field.val())) {
                    this.addFieldError(field, 'Please enter a valid email address');
                    errors.push(`${fieldRules.label || fieldName} must be a valid email`);
                    isValid = false;
                }
                
                if (fieldRules.url && field.val() && !this.isValidUrl(field.val())) {
                    this.addFieldError(field, 'Please enter a valid URL');
                    errors.push(`${fieldRules.label || fieldName} must be a valid URL`);
                    isValid = false;
                }
                
                if (fieldRules.minLength && field.val().length < fieldRules.minLength) {
                    this.addFieldError(field, `Must be at least ${fieldRules.minLength} characters`);
                    errors.push(`${fieldRules.label || fieldName} is too short`);
                    isValid = false;
                }
            });

            if (!isValid) {
                this.announceToScreenReader(`Form validation failed. ${errors.length} errors found.`);
                // Focus first error field
                form.find('.fp-dms-error').first().focus();
            }

            return { isValid, errors };
        },

        /**
         * Add field error styling and message
         */
        addFieldError: function(field, message) {
            field.addClass('fp-dms-error');
            field.after(`<div class="fp-dms-field-error" role="alert">${message}</div>`);
        },

        /**
         * Email validation
         */
        isValidEmail: function(email) {
            const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        },

        /**
         * URL validation
         */
        isValidUrl: function(url) {
            try {
                new URL(url);
                return true;
            } catch {
                return false;
            }
        },

        /**
         * Local storage cache with expiration
         */
        cache: {
            set: function(key, data, expirationMinutes = 60) {
                const expirationTime = new Date().getTime() + (expirationMinutes * 60 * 1000);
                const cacheData = {
                    data: data,
                    expiration: expirationTime
                };
                
                try {
                    localStorage.setItem('fp_dms_' + key, JSON.stringify(cacheData));
                } catch (e) {
                    console.warn('Failed to save to localStorage:', e);
                }
            },

            get: function(key) {
                try {
                    const cached = localStorage.getItem('fp_dms_' + key);
                    if (!cached) return null;
                    
                    const cacheData = JSON.parse(cached);
                    const now = new Date().getTime();
                    
                    if (now > cacheData.expiration) {
                        localStorage.removeItem('fp_dms_' + key);
                        return null;
                    }
                    
                    return cacheData.data;
                } catch (e) {
                    console.warn('Failed to read from localStorage:', e);
                    return null;
                }
            },

            clear: function(key) {
                try {
                    if (key) {
                        localStorage.removeItem('fp_dms_' + key);
                    } else {
                        // Clear all fp_dms_ keys
                        Object.keys(localStorage).forEach(k => {
                            if (k.startsWith('fp_dms_')) {
                                localStorage.removeItem(k);
                            }
                        });
                    }
                } catch (e) {
                    console.warn('Failed to clear localStorage:', e);
                }
            }
        },

        /**
         * Lazy loading implementation
         */
        lazyLoad: function(selector, callback) {
            const elements = document.querySelectorAll(selector);
            
            if (!('IntersectionObserver' in window)) {
                // Fallback for older browsers
                elements.forEach(callback);
                return;
            }
            
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        callback(entry.target);
                        observer.unobserve(entry.target);
                    }
                });
            }, {
                threshold: 0.1,
                rootMargin: '50px'
            });
            
            elements.forEach(el => observer.observe(el));
        },

        /**
         * Performance monitoring
         */
        performance: {
            mark: function(name) {
                if ('performance' in window && 'mark' in performance) {
                    performance.mark(name);
                }
            },
            
            measure: function(name, startMark, endMark) {
                if ('performance' in window && 'measure' in performance) {
                    try {
                        performance.measure(name, startMark, endMark);
                        const measure = performance.getEntriesByName(name)[0];
                        return measure ? measure.duration : 0;
                    } catch (e) {
                        console.warn('Performance measurement failed:', e);
                        return 0;
                    }
                }
                return 0;
            }
        }
    };

    /**
     * Enhanced dashboard initialization with optimizations
     */
    $(document).ready(function() {
        // Add skip links for accessibility
        if ($('.fp-dms-skip-link').length === 0) {
            $('body').prepend('<a href="#main" class="fp-dms-skip-link">Skip to main content</a>');
        }
        
        // Initialize lazy loading for dashboard widgets
        FP_DMS_Optimizations.lazyLoad('.fp-dms-lazy-widget', function(element) {
            $(element).removeClass('fp-dms-lazy-loading');
            $(element).trigger('fp-dms-widget-visible');
        });
        
        // Add keyboard navigation improvements
        $(document).on('keydown', function(e) {
            // Alt + D for dashboard
            if (e.altKey && e.key === 'd') {
                e.preventDefault();
                window.location.href = window.location.origin + '/wp-admin/admin.php?page=fp-digital-marketing-dashboard';
            }
        });
        
        // Enhanced error boundary for JavaScript errors
        window.addEventListener('error', function(e) {
            console.error('JavaScript error in FP DMS:', e);
            FP_DMS_Optimizations.showNotification(
                'A technical error occurred. Please refresh the page if problems persist.',
                'error',
                10000
            );
        });
        
        // Monitor performance
        if ('performance' in window && 'observer' in window.PerformanceObserver) {
            try {
                const observer = new PerformanceObserver((list) => {
                    const entries = list.getEntries();
                    entries.forEach(entry => {
                        if (entry.duration > 1000) {
                            console.warn('Slow operation detected:', entry.name, entry.duration + 'ms');
                        }
                    });
                });
                observer.observe({ entryTypes: ['measure'] });
            } catch (e) {
                // PerformanceObserver not supported
            }
        }
        
        // Auto-save form data to prevent data loss
        $('form.fp-dms-autosave').on('input change', FP_DMS_Optimizations.debounce(function() {
            const form = $(this);
            const formData = form.serialize();
            const formId = form.attr('id') || 'unknown-form';
            
            FP_DMS_Optimizations.cache.set('autosave_' + formId, formData, 30);
        }, 1000));
        
        // Restore form data on page load
        $('form.fp-dms-autosave').each(function() {
            const form = $(this);
            const formId = form.attr('id') || 'unknown-form';
            const savedData = FP_DMS_Optimizations.cache.get('autosave_' + formId);
            
            if (savedData) {
                // Parse and restore form data
                const params = new URLSearchParams(savedData);
                params.forEach((value, key) => {
                    const field = form.find(`[name="${key}"]`);
                    if (field.length) {
                        field.val(value);
                    }
                });
                
                FP_DMS_Optimizations.showNotification(
                    'Form data restored from previous session',
                    'info',
                    3000
                );
            }
        });
    });

})(jQuery);
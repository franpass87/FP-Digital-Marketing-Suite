/**
 * FP DMS Toast Notification System
 * 
 * Sistema di notifiche non invasive con auto-dismiss e animazioni
 */
(function() {
    'use strict';

    // Toast container
    let toastContainer = null;

    // Initialize toast container
    function initToastContainer() {
        if (toastContainer) {
            return toastContainer;
        }

        toastContainer = document.createElement('div');
        toastContainer.className = 'fpdms-toast-container';
        toastContainer.setAttribute('aria-live', 'polite');
        toastContainer.setAttribute('aria-atomic', 'true');
        document.body.appendChild(toastContainer);

        return toastContainer;
    }

    /**
     * Show a toast notification
     * 
     * @param {Object} options
     * @param {string} options.message - Toast message
     * @param {string} options.type - success, error, warning, info
     * @param {number} options.duration - Duration in ms (0 = no auto-dismiss)
     * @param {boolean} options.dismissible - Can be dismissed manually
     * @param {string} options.icon - Custom dashicon class
     * @param {Function} options.onClick - Click handler
     */
    function showToast(options) {
        const settings = {
            message: options.message || 'Notification',
            type: options.type || 'info',
            duration: options.duration !== undefined ? options.duration : 4000,
            dismissible: options.dismissible !== false,
            icon: options.icon || null,
            onClick: options.onClick || null
        };

        const container = initToastContainer();
        const toast = createToastElement(settings);

        // Add to container with animation
        container.appendChild(toast);
        
        // Trigger enter animation
        requestAnimationFrame(() => {
            toast.classList.add('is-visible');
        });

        // Auto-dismiss
        if (settings.duration > 0) {
            setTimeout(() => {
                dismissToast(toast);
            }, settings.duration);
        }

        // Click handler
        if (settings.onClick) {
            toast.addEventListener('click', settings.onClick);
        }

        return toast;
    }

    /**
     * Create toast DOM element
     */
    function createToastElement(settings) {
        const toast = document.createElement('div');
        toast.className = `fpdms-toast fpdms-toast-${settings.type}`;
        
        // Icon
        const iconClass = settings.icon || getDefaultIcon(settings.type);
        const icon = document.createElement('span');
        icon.className = `fpdms-toast-icon dashicons ${iconClass}`;
        toast.appendChild(icon);

        // Message
        const message = document.createElement('span');
        message.className = 'fpdms-toast-message';
        message.textContent = settings.message;
        toast.appendChild(message);

        // Dismiss button
        if (settings.dismissible) {
            const dismissBtn = document.createElement('button');
            dismissBtn.className = 'fpdms-toast-dismiss';
            dismissBtn.innerHTML = '<span class="dashicons dashicons-no-alt"></span>';
            dismissBtn.setAttribute('aria-label', 'Dismiss');
            dismissBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                dismissToast(toast);
            });
            toast.appendChild(dismissBtn);
        }

        return toast;
    }

    /**
     * Get default icon for toast type
     */
    function getDefaultIcon(type) {
        const icons = {
            success: 'dashicons-yes-alt',
            error: 'dashicons-dismiss',
            warning: 'dashicons-warning',
            info: 'dashicons-info'
        };
        return icons[type] || icons.info;
    }

    /**
     * Dismiss a toast
     */
    function dismissToast(toast) {
        toast.classList.remove('is-visible');
        toast.classList.add('is-dismissing');

        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 300);
    }

    /**
     * Convenience methods
     */
    const Toast = {
        success: (message, duration) => showToast({ message, type: 'success', duration }),
        error: (message, duration) => showToast({ message, type: 'error', duration }),
        warning: (message, duration) => showToast({ message, type: 'warning', duration }),
        info: (message, duration) => showToast({ message, type: 'info', duration }),
        show: showToast,
        dismiss: dismissToast
    };

    // Expose globally
    window.fpdmsToast = Toast;

    // Add CSS
    const style = document.createElement('style');
    style.textContent = `
        .fpdms-toast-container {
            position: fixed;
            top: 32px;
            right: 24px;
            z-index: 999999;
            display: flex;
            flex-direction: column;
            gap: 12px;
            max-width: 400px;
            pointer-events: none;
        }

        .fpdms-toast {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15), 0 0 0 1px rgba(0, 0, 0, 0.05);
            min-width: 300px;
            max-width: 400px;
            pointer-events: auto;
            transform: translateX(400px);
            opacity: 0;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .fpdms-toast.is-visible {
            transform: translateX(0);
            opacity: 1;
        }

        .fpdms-toast.is-dismissing {
            transform: translateX(400px);
            opacity: 0;
        }

        .fpdms-toast-icon {
            flex-shrink: 0;
            width: 24px;
            height: 24px;
            font-size: 24px;
        }

        .fpdms-toast-success {
            border-left: 4px solid #10b981;
        }

        .fpdms-toast-success .fpdms-toast-icon {
            color: #10b981;
        }

        .fpdms-toast-error {
            border-left: 4px solid #ef4444;
        }

        .fpdms-toast-error .fpdms-toast-icon {
            color: #ef4444;
        }

        .fpdms-toast-warning {
            border-left: 4px solid #f59e0b;
        }

        .fpdms-toast-warning .fpdms-toast-icon {
            color: #f59e0b;
        }

        .fpdms-toast-info {
            border-left: 4px solid #3b82f6;
        }

        .fpdms-toast-info .fpdms-toast-icon {
            color: #3b82f6;
        }

        .fpdms-toast-message {
            flex: 1;
            font-size: 14px;
            font-weight: 500;
            color: #1f2937;
            line-height: 1.5;
        }

        .fpdms-toast-dismiss {
            flex-shrink: 0;
            background: none;
            border: none;
            padding: 4px;
            cursor: pointer;
            border-radius: 4px;
            transition: background 0.2s;
        }

        .fpdms-toast-dismiss:hover {
            background: #f3f4f6;
        }

        .fpdms-toast-dismiss .dashicons {
            width: 16px;
            height: 16px;
            font-size: 16px;
            color: #9ca3af;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .fpdms-toast-container {
                top: 46px;
                right: 12px;
                left: 12px;
                max-width: none;
            }

            .fpdms-toast {
                min-width: auto;
                max-width: none;
            }
        }

        /* WordPress admin bar adjustment */
        body.admin-bar .fpdms-toast-container {
            top: 78px;
        }

        @media screen and (max-width: 782px) {
            body.admin-bar .fpdms-toast-container {
                top: 92px;
            }
        }

        @media screen and (max-width: 600px) {
            body.admin-bar .fpdms-toast-container {
                top: 78px;
            }
        }
    `;
    document.head.appendChild(style);

})();


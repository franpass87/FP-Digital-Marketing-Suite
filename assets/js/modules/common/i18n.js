/**
 * Common i18n utilities
 */
export function getI18n(fallback = {}) {
    if (typeof window !== 'undefined' && window.fpdmsI18n && typeof window.fpdmsI18n === 'object') {
        return window.fpdmsI18n;
    }
    return fallback || {};
}



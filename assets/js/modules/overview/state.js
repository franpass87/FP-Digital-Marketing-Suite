/**
 * Overview State Management
 * Gestisce lo stato dell'applicazione overview
 */
export class OverviewState {
    constructor(config) {
        this.config = config;
        this.refreshIntervals = this._initRefreshIntervals();
        
        this.state = {
            clientId: '',
            preset: 'last7',
            customFrom: '',
            customTo: '',
            autoRefresh: false,
            refreshInterval: this._getDefaultRefreshInterval(),
            lastRefresh: ''
        };

        this.refreshTimer = null;
    }

    _initRefreshIntervals() {
        const intervals = Array.isArray(this.config.refreshIntervals)
            ? this.config.refreshIntervals
                .map(i => parseInt(i, 10))
                .filter(i => !Number.isNaN(i) && i > 0)
            : [60, 120];

        return intervals.length ? intervals : [parseInt(this.config.defaultRefreshInterval, 10) || 60];
    }

    _getDefaultRefreshInterval() {
        return this.clampInterval(this.config.defaultRefreshInterval);
    }

    clampInterval(value) {
        const fallback = parseInt(this.config.defaultRefreshInterval, 10) || this.refreshIntervals[0] || 60;
        const seconds = parseInt(value, 10);
        
        if (Number.isNaN(seconds) || seconds <= 0) {
            return fallback;
        }
        
        if (this.refreshIntervals.includes(seconds)) {
            return seconds;
        }
        
        const sorted = this.refreshIntervals
            .slice()
            .sort((a, b) => Math.abs(a - seconds) - Math.abs(b - seconds));
        
        return sorted.length ? sorted[0] : fallback;
    }

    updateState(updates) {
        Object.assign(this.state, updates);
    }

    getState() {
        return { ...this.state };
    }

    setLastRefresh(timestamp) {
        this.state.lastRefresh = timestamp || '';
    }

    clearAutoRefreshTimer() {
        if (this.refreshTimer) {
            clearTimeout(this.refreshTimer);
            this.refreshTimer = null;
        }
    }

    scheduleAutoRefresh(callback, interval) {
        this.clearAutoRefreshTimer();
        
        if (this.state.autoRefresh) {
            const intervalMs = this.clampInterval(interval || this.state.refreshInterval) * 1000;
            this.refreshTimer = setTimeout(callback, intervalMs);
        }
    }

    loadPreferences(prefs) {
        if (!prefs) return;

        this.state.preset = prefs.preset || this.state.preset;
        this.state.customFrom = prefs.from || '';
        this.state.customTo = prefs.to || '';
        this.state.autoRefresh = !!prefs.auto_refresh;
        
        const storedInterval = typeof prefs.refresh_interval === 'number'
            ? prefs.refresh_interval
            : parseInt(prefs.refresh_interval || this.state.refreshInterval, 10);
        
        this.state.refreshInterval = this.clampInterval(storedInterval);
    }
}
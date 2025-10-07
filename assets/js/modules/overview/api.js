/**
 * Overview API Client
 * Gestisce tutte le chiamate API per overview
 */
export class OverviewAPI {
    constructor(config) {
        this.config = config;
        this.endpoints = config.endpoints || {};
        this.nonce = config.nonce || '';
    }

    async request(url, params) {
        if (!url) {
            return Promise.resolve({});
        }

        const endpoint = new URL(url, window.location.origin);
        
        if (params) {
            Object.entries(params).forEach(([key, value]) => {
                if (value) {
                    endpoint.searchParams.set(key, value);
                }
            });
        }

        try {
            const response = await fetch(endpoint.toString(), {
                credentials: 'same-origin',
                headers: {
                    'X-WP-Nonce': this.nonce
                }
            });

            if (!response.ok) {
                const payload = await response.json().catch(() => ({}));
                const message = payload?.message || `HTTP ${response.status}`;
                throw new Error(message);
            }

            return await response.json();
        } catch (error) {
            console.error('API request failed:', error);
            throw error;
        }
    }

    async postRequest(url, payload) {
        if (!url) {
            return Promise.reject(new Error('Missing endpoint'));
        }

        try {
            const response = await fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.nonce
                },
                body: JSON.stringify(payload || {})
            });

            if (!response.ok) {
                const body = await response.json().catch(() => ({}));
                const message = body?.message || `HTTP ${response.status}`;
                throw new Error(message);
            }

            return await response.json();
        } catch (error) {
            console.error('API POST request failed:', error);
            throw error;
        }
    }

    async fetchSummary(params) {
        return this.request(this.endpoints.summary, params);
    }

    async fetchStatus(clientId) {
        return this.request(this.endpoints.status, { client_id: clientId });
    }

    async fetchAnomalies(params) {
        return this.request(this.endpoints.anomalies, params);
    }

    async runReport(payload) {
        const url = this.config.actions?.run || this.endpoints.run;
        return this.postRequest(url, payload);
    }

    async evaluateAnomalies(payload) {
        const url = this.config.actions?.anomalies || this.endpoints.anomalies;
        return this.postRequest(url, payload);
    }
}
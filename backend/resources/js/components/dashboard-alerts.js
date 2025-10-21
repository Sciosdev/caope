export default function dashboardAlerts({ endpoint, pollInterval = null } = {}) {
    return {
        endpoint,
        pollInterval,
        alerts: [],
        thresholdDays: null,
        loading: true,
        error: null,
        abortController: null,
        intervalId: null,

        init() {
            this.fetchData();

            if (this.pollInterval) {
                this.intervalId = setInterval(() => this.fetchData(), this.pollInterval);
            }

            return () => this.cleanup();
        },

        async fetchData() {
            if (! this.endpoint) {
                this.error = 'No se definió el endpoint de alertas.';
                this.loading = false;
                return;
            }

            this.loading = true;
            this.error = null;

            if (this.abortController) {
                this.abortController.abort();
            }

            this.abortController = new AbortController();

            try {
                const response = await fetch(this.endpoint, {
                    method: 'GET',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                    signal: this.abortController.signal,
                });

                if (! response.ok) {
                    throw new Error('No fue posible obtener las alertas.');
                }

                const payload = await response.json();
                this.alerts = Array.isArray(payload.alerts) ? payload.alerts : [];
                this.thresholdDays = payload.threshold_days ?? null;
            } catch (error) {
                if (error.name === 'AbortError') {
                    return;
                }

                this.error = error.message || 'Ocurrió un error inesperado al consultar las alertas.';
            } finally {
                this.loading = false;
            }
        },

        refresh() {
            this.fetchData();
        },

        cleanup() {
            if (this.intervalId) {
                clearInterval(this.intervalId);
            }

            if (this.abortController) {
                this.abortController.abort();
            }
        },
    };
}


export default function dashboardPendings({ endpoint, pollInterval = null } = {}) {
    return {
        endpoint,
        pollInterval,
        cards: [],
        loading: true,
        error: null,
        abortController: null,
        init() {
            this.fetchData();

            if (this.pollInterval) {
                setInterval(() => this.fetchData(), this.pollInterval);
            }
        },
        async fetchData() {
            if (! this.endpoint) {
                this.error = 'No se definió el endpoint para cargar los pendientes.';
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
                    throw new Error('No fue posible obtener la información de pendientes.');
                }

                const data = await response.json();
                this.cards = Array.isArray(data.cards) ? data.cards : [];
            } catch (error) {
                if (error.name === 'AbortError') {
                    return;
                }

                this.error = error.message || 'Ocurrió un error inesperado al consultar los pendientes.';
            } finally {
                this.loading = false;
            }
        },
        refresh() {
            this.fetchData();
        },
    };
}

export default function dashboardMetrics({ endpoint } = {}) {
    return {
        endpoint,
        metrics: null,
        loading: true,
        error: null,
        abortController: null,

        init() {
            this.fetchData();
        },

        async fetchData() {
            if (! this.endpoint) {
                this.error = 'No se definió el endpoint de métricas.';
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
                    throw new Error('No fue posible obtener las métricas del tablero.');
                }

                this.metrics = await response.json();
            } catch (error) {
                if (error.name === 'AbortError') {
                    return;
                }

                this.error = error.message || 'Ocurrió un error inesperado al consultar las métricas.';
            } finally {
                this.loading = false;
            }
        },

        refresh() {
            this.fetchData();
        },

        get totalExpedientes() {
            return this.metrics?.expedientes?.total ?? 0;
        },

        get stateCards() {
            if (! this.metrics?.expedientes?.por_estado) {
                return [];
            }

            const mapping = [
                { key: 'abierto', label: 'Abiertos', description: 'Casos en seguimiento activo.' },
                { key: 'revision', label: 'En revisión', description: 'Pendientes de validación o cierre.' },
                { key: 'cerrado', label: 'Cerrados', description: 'Casos concluidos.' },
            ];

            return mapping.map((item) => ({
                ...item,
                value: this.metrics.expedientes.por_estado[item.key] ?? 0,
            }));
        },

        get averageCard() {
            const metric = this.metrics?.sesiones?.tiempo_promedio_validacion;

            if (! metric) {
                return null;
            }

            const count = metric.count ?? 0;
            const hasData = count > 0 && metric.seconds !== null;

            return {
                hasData,
                value: hasData && metric.human ? metric.human : 'Sin datos',
                caption: hasData
                    ? `Calculado con ${count} ${count === 1 ? 'sesión' : 'sesiones'} validadas.`
                    : 'Aún no hay sesiones validadas para calcular el promedio.',
                count,
            };
        },
    };
}


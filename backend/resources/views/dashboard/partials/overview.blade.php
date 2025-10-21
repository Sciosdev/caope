<div class="mb-4">
    <h4 class="mb-1">Panel de seguimiento</h4>
    <p class="text-muted mb-0">Monitorea el estado de los expedientes y atiende los pendientes prioritarios.</p>
</div>

<section
    class="card shadow-sm border-0 mb-5"
    x-data="dashboardMetrics({ endpoint: '{{ route('dashboard.metrics') }}' })"
    x-init="init()"
>
    <div class="card-body">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
            <div>
                <h5 class="mb-1">Indicadores clave</h5>
                <p class="text-muted small mb-0">Conteo de expedientes por estado y tiempos promedio de validación.</p>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-light text-dark" x-show="!loading" x-cloak>
                    Total expedientes: <span class="fw-semibold" x-text="totalExpedientes"></span>
                </span>
                <button type="button" class="btn btn-outline-secondary btn-sm" @click="refresh()" :disabled="loading">
                    <span class="spinner-border spinner-border-sm me-2" role="status" x-show="loading" x-cloak></span>
                    Actualizar
                </button>
            </div>
        </div>

        <div x-show="loading" class="text-center py-4" x-cloak>
            <div class="spinner-border text-primary" role="status"></div>
            <p class="text-muted mt-3 mb-0">Calculando indicadores...</p>
        </div>

        <template x-if="error">
            <div class="alert alert-danger" role="alert">
                <div class="d-flex justify-content-between align-items-center">
                    <span x-text="error"></span>
                    <button type="button" class="btn btn-sm btn-light" @click="refresh()">Reintentar</button>
                </div>
            </div>
        </template>

        <div x-show="!loading && metrics && !error" x-cloak>
            <div class="row g-3 mb-3">
                <template x-for="card in stateCards" :key="card.key">
                    <div class="col-12 col-md-4">
                        <div class="card h-100 border-0 shadow-sm">
                            <div class="card-body">
                                <p class="text-muted text-uppercase small mb-1" x-text="card.label"></p>
                                <h3 class="fw-semibold mb-2" x-text="card.value"></h3>
                                <p class="text-muted small mb-0" x-text="card.description"></p>
                            </div>
                        </div>
                    </div>
                </template>
                <template x-if="averageCard">
                    <div class="col-12 col-md-4">
                        <div class="card h-100 border-0 shadow-sm">
                            <div class="card-body">
                                <p class="text-muted text-uppercase small mb-1">Tiempo promedio de validación</p>
                                <h3 class="fw-semibold mb-2" x-text="averageCard.value"></h3>
                                <p class="text-muted small mb-0" x-text="averageCard.caption"></p>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <template x-if="!stateCards.length">
                <div class="alert alert-info mb-0" role="alert">
                    No hay expedientes registrados todavía.
                </div>
            </template>
        </div>
    </div>
</section>

<div class="row g-4">
    <div class="col-12 col-xl-8">
        <section class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
                    <div>
                        <h5 class="mb-1">Bandeja de pendientes</h5>
                        <p class="text-muted small mb-0">Consulta las acciones que requieren tu atención según tu rol.</p>
                    </div>
                    <button type="button" class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-2"
                            x-data="{}"
                            @click="window.dispatchEvent(new CustomEvent('dashboard-pendings:refresh'))">
                        <i class="ti ti-refresh"></i>
                        Actualizar
                    </button>
                </div>

                <div
                    x-data="dashboardPendings({ endpoint: '{{ route('dashboard.pending') }}' })"
                    x-init="init()"
                    x-on:dashboard-pendings:refresh.window="refresh()"
                >
                    <div x-show="loading" class="text-center py-5" x-cloak>
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Cargando...</span>
                        </div>
                        <p class="text-muted mt-3">Cargando pendientes...</p>
                    </div>

                    <template x-if="error">
                        <div class="alert alert-danger" role="alert">
                            <div class="d-flex justify-content-between align-items-center">
                                <span x-text="error"></span>
                                <button type="button" class="btn btn-sm btn-light" @click="refresh()">Reintentar</button>
                            </div>
                        </div>
                    </template>

                    <template x-if="!loading && cards.length === 0 && !error">
                        <div class="alert alert-success" role="alert">
                            <strong>¡Todo en orden!</strong> No tienes pendientes asignados en este momento.
                        </div>
                    </template>

                    <div class="row g-3" x-show="!loading && cards.length > 0" x-cloak>
                        <template x-for="card in cards" :key="card.id">
                            <div class="col-12 col-md-6">
                                <div class="card h-100 shadow-sm border-0">
                                    <div class="card-body d-flex flex-column">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <div>
                                                <h6 class="card-title mb-1" x-text="card.title"></h6>
                                                <p class="text-muted small mb-0" x-text="card.description"></p>
                                            </div>
                                            <span class="badge" :class="'bg-' + (card.variant || 'secondary')" x-text="card.count"></span>
                                        </div>

                                        <div class="flex-grow-1">
                                            <template x-if="card.items.length > 0">
                                                <ul class="list-unstyled mb-0 small">
                                                    <template x-for="item in card.items" :key="item.id">
                                                        <li class="mb-3">
                                                            <template x-if="item.url">
                                                                <a :href="item.url" class="text-decoration-none">
                                                                    <span class="fw-semibold d-block" x-text="item.primary"></span>
                                                                    <span class="text-muted" x-text="item.secondary"></span>
                                                                </a>
                                                            </template>
                                                            <template x-if="!item.url">
                                                                <div>
                                                                    <span class="fw-semibold d-block" x-text="item.primary"></span>
                                                                    <span class="text-muted" x-text="item.secondary"></span>
                                                                </div>
                                                            </template>
                                                        </li>
                                                    </template>
                                                </ul>
                                            </template>
                                            <p class="text-muted small fst-italic mb-0" x-show="card.items.length === 0">Sin registros recientes.</p>
                                        </div>
                                    </div>
                                    <div class="card-footer bg-transparent border-0 pt-0" x-show="card.link">
                                        <a :href="card.link" class="btn btn-sm btn-outline-primary w-100">Ir al detalle</a>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <div class="col-12 col-xl-4">
        <section
            class="card shadow-sm border-0 h-100"
            x-data="dashboardAlerts({ endpoint: '{{ route('dashboard.alerts') }}' })"
            x-init="init()"
        >
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-4">
                    <div>
                        <h5 class="mb-1">Alertas de expedientes</h5>
                        <p class="text-muted small mb-0">
                            <template x-if="thresholdDays">
                                <span>Sin actividad en <span class="fw-semibold" x-text="thresholdDays"></span> días o más.</span>
                            </template>
                            <template x-if="!thresholdDays">
                                <span>Seguimiento a expedientes con baja actividad.</span>
                            </template>
                        </p>
                    </div>
                    <button type="button" class="btn btn-outline-secondary btn-sm" @click="refresh()" :disabled="loading">
                        <span class="spinner-border spinner-border-sm me-2" role="status" x-show="loading" x-cloak></span>
                        Actualizar
                    </button>
                </div>

                <div x-show="loading" class="text-center py-4" x-cloak>
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="text-muted mt-3 mb-0">Buscando expedientes estancados...</p>
                </div>

                <template x-if="error">
                    <div class="alert alert-danger" role="alert">
                        <div class="d-flex justify-content-between align-items-center">
                            <span x-text="error"></span>
                            <button type="button" class="btn btn-sm btn-light" @click="refresh()">Reintentar</button>
                        </div>
                    </div>
                </template>

                <template x-if="!loading && alerts.length === 0 && !error">
                    <div class="alert alert-success" role="alert">
                        No se detectaron expedientes estancados. ¡Buen trabajo!
                    </div>
                </template>

                <div class="list-group" x-show="alerts.length > 0" x-cloak>
                    <template x-for="alert in alerts" :key="alert.id">
                        <a :href="alert.url" class="list-group-item list-group-item-action">
                            <div class="d-flex justify-content-between align-items-start gap-3">
                                <div>
                                    <h6 class="mb-1">
                                        <span x-text="alert.no_control"></span>
                                        <span class="text-muted">·</span>
                                        <span x-text="alert.paciente"></span>
                                    </h6>
                                    <p class="mb-1 small text-muted">
                                        Última actividad:
                                        <span x-text="alert.ultima_actividad_human || 'Sin registros recientes'"></span>
                                    </p>
                                    <p class="mb-0 small text-muted">
                                        Tutor: <span x-text="alert.tutor || 'No asignado'"></span>
                                        <template x-if="alert.coordinador">
                                            <span class="ms-2">Coordinador: <span x-text="alert.coordinador"></span></span>
                                        </template>
                                    </p>
                                </div>
                                <span class="badge bg-danger text-white">
                                    <i class="ti ti-alert-triangle me-1"></i>
                                    <span x-text="alert.dias_inactivo + ' días'">
                                    </span>
                                </span>
                            </div>
                        </a>
                    </template>
                </div>
            </div>
        </section>
    </div>
</div>


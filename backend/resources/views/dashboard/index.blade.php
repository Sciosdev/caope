@extends('layouts.noble')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="mb-1">Bandeja de pendientes</h4>
            <p class="text-muted mb-0">Consulta aquí las acciones que requieren tu atención según tu rol.</p>
        </div>
        <button type="button" class="btn btn-outline-secondary btn-sm d-none d-md-inline-flex align-items-center gap-2"
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
                <div class="col-12 col-md-6 col-lg-4">
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
@endsection

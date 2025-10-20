@extends('layouts.noble')

@section('title', 'Consentimientos requeridos')

@section('content')
    <div
        x-data="matrixEditor({
            carreras: @json($carreras->pluck('id')),
            tratamientos: @json($tratamientos->pluck('id')),
            selected: @json($requeridos),
        })"
        class="d-flex flex-column gap-4"
    >
        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-center">
            <div>
                <h4 class="mb-1">Tratamientos requeridos por carrera</h4>
                <p class="text-muted mb-0">Selecciona qué tratamientos deben adjuntar los estudiantes de cada carrera al
                    momento de generar su consentimiento.</p>
            </div>

            <div class="d-flex gap-2 flex-wrap">
                <button type="button" class="btn btn-outline-secondary btn-sm" @click="clearAll">Limpiar todo</button>
                <button type="button" class="btn btn-outline-primary btn-sm" @click="selectAll">Marcar todos</button>
            </div>
        </div>

        @if (session('status'))
            <div class="alert alert-success" role="alert">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger" role="alert">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('consentimientos.requeridos.update') }}" x-ref="form" @submit.prevent="submit">
            @csrf
            @method('PUT')

            <div class="table-responsive">
                <table class="table table-bordered align-middle text-center">
                    <thead class="table-light">
                        <tr>
                            <th class="text-start">Carrera / Tratamiento</th>
                            @foreach ($tratamientos as $tratamiento)
                                <th>
                                    <div class="d-flex flex-column gap-1 align-items-center">
                                        <span class="small fw-semibold">{{ $tratamiento->nombre }}</span>
                                        <button
                                            type="button"
                                            class="btn btn-link btn-sm text-decoration-none text-muted"
                                            @click="toggleColumn({{ $tratamiento->id }})"
                                        >
                                            <span x-show="isColumnFullySelected({{ $tratamiento->id }})">Quitar</span>
                                            <span x-show="!isColumnFullySelected({{ $tratamiento->id }})">Marcar</span>
                                        </button>
                                    </div>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($carreras as $carrera)
                            <tr>
                                <th class="text-start">
                                    <div class="d-flex justify-content-between align-items-center gap-2">
                                        <span>{{ $carrera->nombre }}</span>
                                        <button
                                            type="button"
                                            class="btn btn-link btn-sm text-decoration-none text-muted"
                                            @click="toggleRow({{ $carrera->id }})"
                                        >
                                            <span x-show="isRowFullySelected({{ $carrera->id }})">Quitar fila</span>
                                            <span x-show="!isRowFullySelected({{ $carrera->id }})">Marcar fila</span>
                                        </button>
                                    </div>
                                </th>
                                @foreach ($tratamientos as $tratamiento)
                                    <td>
                                        <div class="form-check d-flex justify-content-center">
                                            <input
                                                type="checkbox"
                                                class="form-check-input"
                                                :checked="isChecked({{ $carrera->id }}, {{ $tratamiento->id }})"
                                                @change="toggle({{ $carrera->id }}, {{ $tratamiento->id }})"
                                            >
                                        </div>
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $tratamientos->count() + 1 }}" class="text-center text-muted py-5">
                                    No hay carreras registradas actualmente.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-3">
                <button type="button" class="btn btn-outline-secondary" @click="clearAll">Limpiar selección</button>
                <button type="submit" class="btn btn-primary">Guardar cambios</button>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('matrixEditor', (initial) => ({
                carreras: initial.carreras || [],
                tratamientos: initial.tratamientos || [],
                selected: initial.selected || {},

                ensureArray(key) {
                    if (!Array.isArray(this.selected[key])) {
                        this.selected[key] = [];
                    }
                },

                isChecked(carreraId, tratamientoId) {
                    const key = String(carreraId);
                    this.ensureArray(key);
                    return this.selected[key].includes(tratamientoId);
                },

                toggle(carreraId, tratamientoId) {
                    const key = String(carreraId);
                    this.ensureArray(key);

                    if (this.selected[key].includes(tratamientoId)) {
                        this.selected[key] = this.selected[key].filter((id) => id !== tratamientoId);
                    } else {
                        this.selected[key].push(tratamientoId);
                    }
                },

                isRowFullySelected(carreraId) {
                    const key = String(carreraId);
                    this.ensureArray(key);

                    if (this.tratamientos.length === 0) {
                        return false;
                    }

                    return this.tratamientos.every((id) => this.selected[key].includes(id));
                },

                toggleRow(carreraId) {
                    const key = String(carreraId);
                    const selectAll = !this.isRowFullySelected(carreraId);

                    if (selectAll) {
                        this.selected[key] = [...this.tratamientos];
                    } else {
                        this.selected[key] = [];
                    }
                },

                isColumnFullySelected(tratamientoId) {
                    if (this.carreras.length === 0) {
                        return false;
                    }

                    return this.carreras.every((carreraId) => this.isChecked(carreraId, tratamientoId));
                },

                toggleColumn(tratamientoId) {
                    const shouldSelect = !this.isColumnFullySelected(tratamientoId);

                    this.carreras.forEach((carreraId) => {
                        const key = String(carreraId);
                        this.ensureArray(key);

                        const exists = this.selected[key].includes(tratamientoId);

                        if (shouldSelect && !exists) {
                            this.selected[key].push(tratamientoId);
                        }

                        if (!shouldSelect && exists) {
                            this.selected[key] = this.selected[key].filter((id) => id !== tratamientoId);
                        }
                    });
                },

                selectAll() {
                    this.carreras.forEach((carreraId) => {
                        this.selected[String(carreraId)] = [...this.tratamientos];
                    });
                },

                clearAll() {
                    this.selected = {};
                },

                submit() {
                    const form = this.$refs.form;
                    const previous = form.querySelectorAll('input[data-generated="matrix"]');
                    previous.forEach((input) => input.remove());

                    Object.entries(this.selected).forEach(([carreraId, tratamientoIds]) => {
                        if (!Array.isArray(tratamientoIds) || tratamientoIds.length === 0) {
                            return;
                        }

                        const uniqueIds = [...new Set(tratamientoIds)];

                        uniqueIds.forEach((tratamientoId) => {
                            const input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = `requeridos[${carreraId}][]`;
                            input.value = tratamientoId;
                            input.setAttribute('data-generated', 'matrix');
                            form.appendChild(input);
                        });
                    });

                    form.submit();
                },
            }));
        });
    </script>
@endpush

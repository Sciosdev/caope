@php
    $antecedentesOpciones = $antecedentesFamiliaresOpciones ?? [];
    $antecedentesPrevios = old('antecedentes_familiares');

    if (! is_array($antecedentesPrevios)) {
        $antecedentesPrevios = $expediente->antecedentes_familiares ?? [];
    }

    $antecedentesValores = [];

    foreach ($antecedentesOpciones as $key => $label) {
        $valor = $antecedentesPrevios[$key] ?? false;

        if (is_string($valor)) {
            $valor = trim($valor);
        }

        $booleanValor = filter_var($valor, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        $antecedentesValores[$key] = $booleanValor ?? false;
    }
@endphp

<div class="row g-3">
    <div class="col-md-4">
        <label for="no_control" class="form-label">Número de control</label>
        <input
            type="text"
            name="no_control"
            id="no_control"
            value="{{ old('no_control', $expediente->no_control ?? '') }}"
            class="form-control @error('no_control') is-invalid @enderror"
            maxlength="30"
            required
        >
        @error('no_control')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4">
        <label for="paciente" class="form-label">Paciente</label>
        <input
            type="text"
            name="paciente"
            id="paciente"
            value="{{ old('paciente', $expediente->paciente ?? '') }}"
            class="form-control @error('paciente') is-invalid @enderror"
            maxlength="140"
            required
        >
        @error('paciente')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4">
        <label for="apertura" class="form-label">Fecha de apertura</label>
        <input
            type="date"
            name="apertura"
            id="apertura"
            value="{{ old('apertura', optional($expediente->apertura ?? null)->format('Y-m-d')) }}"
            class="form-control @error('apertura') is-invalid @enderror"
            required
        >
        @error('apertura')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4">
        <label for="carrera" class="form-label">Carrera</label>
        <select
            name="carrera"
            id="carrera"
            class="form-select js-select2 @error('carrera') is-invalid @enderror"
            data-placeholder="Seleccione una carrera"
            required
        >
            <option value="">Seleccione</option>
            @foreach ($carreras as $carreraNombre)
                <option value="{{ $carreraNombre }}" @selected(old('carrera', $expediente->carrera ?? '') === $carreraNombre)>
                    {{ $carreraNombre }}
                </option>
            @endforeach
        </select>
        @error('carrera')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4">
        <label for="turno" class="form-label">Turno</label>
        <select
            name="turno"
            id="turno"
            class="form-select js-select2 @error('turno') is-invalid @enderror"
            data-placeholder="Seleccione un turno"
            required
        >
            <option value="">Seleccione</option>
            @foreach ($turnos as $turnoNombre)
                <option value="{{ $turnoNombre }}" @selected(old('turno', $expediente->turno ?? '') === $turnoNombre)>
                    {{ $turnoNombre }}
                </option>
            @endforeach
        </select>
        @error('turno')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4">
        <label for="tutor_id" class="form-label">Tutor asignado</label>
        <select
            name="tutor_id"
            id="tutor_id"
            class="form-select @error('tutor_id') is-invalid @enderror"
        >
            <option value="">Sin asignar</option>
            @foreach ($tutores as $tutor)
                <option value="{{ $tutor->id }}" @selected((int) old('tutor_id', $expediente->tutor_id ?? 0) === $tutor->id)>
                    {{ $tutor->name }}
                </option>
            @endforeach
        </select>
        @error('tutor_id')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4">
        <label for="coordinador_id" class="form-label">Coordinador</label>
        <select
            name="coordinador_id"
            id="coordinador_id"
            class="form-select @error('coordinador_id') is-invalid @enderror"
        >
            <option value="">Sin asignar</option>
            @foreach ($coordinadores as $coordinador)
                <option value="{{ $coordinador->id }}" @selected((int) old('coordinador_id', $expediente->coordinador_id ?? 0) === $coordinador->id)>
                    {{ $coordinador->name }}
                </option>
            @endforeach
        </select>
        @error('coordinador_id')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12">
        <div class="border rounded p-3 h-100">
            <h6 class="mb-3">Antecedentes familiares</h6>

            <div class="table-responsive">
                <table class="table table-sm align-middle mb-3">
                    <thead>
                        <tr>
                            <th class="w-75">Familiar</th>
                            <th class="text-center">Presenta antecedente</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($antecedentesOpciones as $key => $label)
                            <tr>
                                <td>{{ $label }}</td>
                                <td class="text-center">
                                    <div class="form-check d-inline-flex justify-content-center">
                                        <input type="hidden" name="antecedentes_familiares[{{ $key }}]" value="0">
                                        <input
                                            type="checkbox"
                                            class="form-check-input"
                                            id="antecedentes_{{ $key }}"
                                            name="antecedentes_familiares[{{ $key }}]"
                                            value="1"
                                            @checked($antecedentesValores[$key] ?? false)
                                        >
                                        <label class="visually-hidden" for="antecedentes_{{ $key }}">{{ $label }}</label>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @error('antecedentes_familiares')
                <div class="text-danger small">{{ $message }}</div>
            @enderror

            @foreach ($antecedentesOpciones as $key => $label)
                @error('antecedentes_familiares.' . $key)
                    <div class="text-danger small">{{ $message }}</div>
                @enderror
            @endforeach

            <div class="mt-3">
                <label for="antecedentes_observaciones" class="form-label">Observaciones</label>
                <textarea
                    name="antecedentes_observaciones"
                    id="antecedentes_observaciones"
                    class="form-control @error('antecedentes_observaciones') is-invalid @enderror"
                    rows="3"
                    maxlength="1000"
                >{{ old('antecedentes_observaciones', $expediente->antecedentes_observaciones ?? '') }}</textarea>
                @error('antecedentes_observaciones')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>
    </div>
</div>

@once
    @push('styles')
        <link rel="stylesheet" href="{{ asset('assets/vendors/select2/select2.min.css') }}">
    @endpush

    @push('scripts')
        <script src="{{ asset('assets/vendors/select2/select2.min.js') }}"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const aperturaField = document.getElementById('apertura');
                if (aperturaField && window.flatpickr) {
                    window.flatpickr(aperturaField, {
                        dateFormat: 'Y-m-d',
                        maxDate: 'today',
                    });
                }

                if (window.jQuery && typeof window.jQuery.fn.select2 === 'function') {
                    const $ = window.jQuery;
                    const $selects = $('#carrera, #turno');

                    if ($selects.length) {
                        $selects.each(function () {
                            const $element = $(this);
                            $element.select2({
                                placeholder: $element.data('placeholder') || 'Seleccione una opción',
                                allowClear: true,
                                width: '100%'
                            });
                        });
                    }
                }
            });
        </script>
    @endpush
@endonce

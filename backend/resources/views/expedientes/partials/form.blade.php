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

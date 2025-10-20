@extends('layouts.noble')

@section('title', 'Nuevo expediente')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="mb-0">Crear expediente</h4>
            <p class="text-muted small mb-0">Captura los datos iniciales del expediente.</p>
        </div>
        <a href="{{ route('expedientes.index') }}" class="btn btn-outline-secondary">Volver al listado</a>
    </div>

    <form action="{{ route('expedientes.store') }}" method="post" class="card shadow-sm">
        @csrf

        <div class="card-body">
            @include('expedientes.partials.form')
        </div>

        <div class="card-footer text-end">
            <button type="submit" class="btn btn-primary">Guardar expediente</button>
        </div>
    </form>
@endsection

@push('scripts')
    <script>
        if (window.flatpickr) {
            flatpickr('#apertura', {
                dateFormat: 'Y-m-d',
                maxDate: 'today',
            });
        }
    </script>
@endpush

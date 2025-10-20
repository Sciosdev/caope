@extends('layouts.noble')

@section('title', 'Registrar sesión')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="mb-0">Registrar nueva sesión</h4>
            <p class="text-muted small mb-0">Expediente {{ $expediente->no_control }} · {{ $expediente->paciente }}</p>
        </div>
        <div class="btn-group">
            <a href="{{ route('expedientes.sesiones.index', $expediente) }}" class="btn btn-outline-secondary">Ver sesiones</a>
            <a href="{{ route('expedientes.show', $expediente) }}" class="btn btn-outline-secondary">Volver al expediente</a>
        </div>
    </div>

    <form action="{{ route('expedientes.sesiones.store', $expediente) }}" method="post" class="card shadow-sm"
        enctype="multipart/form-data">
        @csrf

        <div class="card-body">
            @include('sesiones.partials.form')
        </div>

        <div class="card-footer text-end">
            <button type="submit" class="btn btn-primary">Guardar sesión</button>
        </div>
    </form>
@endsection

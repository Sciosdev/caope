@extends('layouts.noble')

@section('title', 'Editar sesión')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="mb-0">Editar sesión</h4>
            <p class="text-muted small mb-0">Expediente {{ $expediente->no_control }} · {{ $expediente->paciente }}</p>
        </div>
        <div class="btn-group">
            <a href="{{ route('expedientes.sesiones.show', [$expediente, $sesion]) }}" class="btn btn-outline-secondary">Ver sesión</a>
            <a href="{{ route('expedientes.sesiones.index', $expediente) }}" class="btn btn-outline-secondary">Ver sesiones</a>
            <a href="{{ route('expedientes.show', $expediente) }}" class="btn btn-outline-secondary">Volver al expediente</a>
        </div>
    </div>

    <form action="{{ route('expedientes.sesiones.update', [$expediente, $sesion]) }}" method="post" class="card shadow-sm">
        @csrf
        @method('put')

        <div class="card-body">
            @include('sesiones.partials.form')
        </div>

        <div class="card-footer d-flex justify-content-between align-items-center">
            <span class="text-muted small">Última actualización: {{ optional($sesion->updated_at)->diffForHumans() }}</span>
            <button type="submit" class="btn btn-primary">Actualizar sesión</button>
        </div>
    </form>
@endsection

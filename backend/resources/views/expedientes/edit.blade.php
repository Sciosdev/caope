@extends('layouts.noble')

@section('title', 'Editar expediente')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="mb-0">Editar expediente</h4>
            <p class="text-muted small mb-0">Actualiza la información del expediente.</p>
        </div>
        <a href="{{ route('expedientes.show', $expediente) }}" class="btn btn-outline-secondary">Ver detalle</a>
    </div>

    <form action="{{ route('expedientes.update', $expediente) }}" method="post" class="card shadow-sm">
        @csrf
        @method('put')

        <div class="card-body">
            @include('expedientes.partials.form')
        </div>

        <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
            <a href="{{ route('expedientes.show', $expediente) }}" class="btn btn-link text-decoration-none">Cancelar</a>
            <button type="submit" class="btn btn-primary">Guardar cambios</button>
        </div>
    </form>
@endsection

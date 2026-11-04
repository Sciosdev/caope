@extends('layouts.app')

@section('content')
<div class="container py-4">
    <h1 class="h4 mb-3">{{ $documento->titulo }}</h1>
    <div class="card">
        <div class="card-body">
            <p class="mb-3">Vista previa no disponible para este tipo de archivo.</p>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('admin.documentos.download', $documento) }}" class="btn btn-primary">Descargar</a>
                <a href="{{ route('admin.documentos.index') }}" class="btn btn-outline-secondary">Volver a documentos</a>
            </div>
        </div>
    </div>
</div>
@endsection

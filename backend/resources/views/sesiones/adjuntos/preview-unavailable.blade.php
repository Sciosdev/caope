@extends('layouts.noble')

@section('title', 'Documento de la sesión')

@section('content')
    <h4 class="mb-2 text-break">{{ $adjunto->nombre_original }}</h4>
    <p class="text-muted">Expediente {{ $expediente->no_control }} · Documento de la sesión</p>
    <div class="card">
        <div class="card-body">
            <p>Este tipo de archivo no tiene vista previa en el navegador. Puedes descargarlo para abrirlo.</p>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('expedientes.sesiones.adjuntos.download', [$expediente, $sesion, $adjunto]) }}" class="btn btn-primary">Descargar</a>
                <a href="{{ route('expedientes.sesiones.show', [$expediente, $sesion]) }}#documentos-sesion" class="btn btn-outline-secondary">Volver a documentos de la sesión</a>
            </div>
        </div>
    </div>
@endsection

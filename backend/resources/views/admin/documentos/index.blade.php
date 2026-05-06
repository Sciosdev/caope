@extends('layouts.app')

@section('content')
<div class="container py-4">
    <h1 class="h4 mb-3">Documentos administrativos</h1>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if ($isAdmin || $isPaps)
        <div class="card mb-4">
            <div class="card-body">
                <form action="{{ route('admin.documentos.store') }}" method="POST" enctype="multipart/form-data" class="row g-3">
                    @csrf
                    <div class="col-md-5">
                        <label class="form-label">Título</label>
                        <input type="text" name="titulo" class="form-control" required>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Archivo</label>
                        <input type="file" name="archivo" class="form-control" required>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button class="btn btn-primary w-100">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <div class="card">
        <div class="card-body table-responsive">
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>Título</th>
                        <th>Subido por</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($documentos as $documento)
                        <tr>
                            <td>{{ $documento->titulo }}</td>
                            <td>{{ $documento->subidoPor?->name ?? '—' }}</td>
                            <td>{{ $documento->aprobado_en ? 'Autorizado' : 'Pendiente de autorización' }}</td>
                            <td class="d-flex gap-2">
                                <a href="{{ route('admin.documentos.download', $documento) }}" class="btn btn-sm btn-outline-primary">Descargar</a>
                                @if ($isAdmin && ! $documento->aprobado_en)
                                    <form method="POST" action="{{ route('admin.documentos.approve', $documento) }}">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-success">Autorizar</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted">Sin documentos registrados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

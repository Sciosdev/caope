@extends('layouts.noble')

@section('title', 'Sesiones del expediente')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="mb-0">Sesiones del expediente</h4>
            <p class="text-muted small mb-0">Expediente {{ $expediente->no_control }} · {{ $expediente->paciente }}</p>
        </div>
        <div class="btn-group">
            @can('create', [App\Models\Sesion::class, $expediente])
                <a href="{{ route('expedientes.sesiones.create', $expediente) }}" class="btn btn-primary">Registrar sesión</a>
            @endcan
            <a href="{{ route('expedientes.show', $expediente) }}" class="btn btn-outline-secondary">Volver al expediente</a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('status') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    @endif

    <div class="card shadow-sm">
        <div class="card-body">
            @if ($sesiones->isEmpty())
                <p class="text-muted mb-0">Aún no hay sesiones registradas para este expediente.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Tipo</th>
                                <th>Referencia</th>
                                <th>Estado revisión</th>
                                <th>Realizada por</th>
                                <th>Validada por</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($sesiones as $sesion)
                                <tr>
                                    <td>{{ optional($sesion->fecha)->format('Y-m-d') }}</td>
                                    <td>{{ $sesion->tipo }}</td>
                                    <td>{{ $sesion->referencia_externa ?? '—' }}</td>
                                    <td>
                                        @php
                                            $badge = [
                                                'pendiente' => 'bg-secondary',
                                                'observada' => 'bg-warning text-dark',
                                                'validada' => 'bg-success',
                                            ][$sesion->status_revision] ?? 'bg-light text-dark';
                                        @endphp
                                        <span class="badge {{ $badge }}">{{ ucfirst($sesion->status_revision) }}</span>
                                    </td>
                                    <td>{{ $sesion->realizadaPor?->name ?? '—' }}</td>
                                    <td>{{ $sesion->validadaPor?->name ?? '—' }}</td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm" role="group">
                                            @can('view', $sesion)
                                                <a href="{{ route('expedientes.sesiones.show', [$expediente, $sesion]) }}" class="btn btn-outline-secondary">Ver</a>
                                            @endcan
                                            @can('update', $sesion)
                                                <a href="{{ route('expedientes.sesiones.edit', [$expediente, $sesion]) }}" class="btn btn-outline-secondary">Editar</a>
                                            @endcan
                                            @can('delete', $sesion)
                                                <form action="{{ route('expedientes.sesiones.destroy', [$expediente, $sesion]) }}" method="post" class="d-inline"
                                                    onsubmit="return confirm('¿Deseas eliminar esta sesión? Esta acción no se puede deshacer.');">
                                                    @csrf
                                                    @method('delete')
                                                    <button type="submit" class="btn btn-outline-danger">Eliminar</button>
                                                </form>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $sesiones->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection


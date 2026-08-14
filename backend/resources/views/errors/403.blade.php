@extends('layouts.noble')

@section('content')
    @php
        $esExpediente = request()->routeIs('expedientes.*') || request()->is('expedientes/*');
    @endphp

    <div class="row justify-content-center py-5">
        <div class="col-lg-8 col-xl-7">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4 p-md-5 text-center">
                    <span class="badge bg-warning-subtle text-warning-emphasis rounded-pill px-3 py-2 mb-3">
                        Acceso restringido
                    </span>

                    <h1 class="h3 mb-3">
                        {{ $esExpediente ? 'Este expediente no está disponible para tu perfil' : 'No tienes acceso a esta sección' }}
                    </h1>

                    <p class="text-muted mb-2">
                        @if ($esExpediente)
                            Solo puedes consultar expedientes que estén asignados o vinculados a tu cuenta.
                        @else
                            Tu perfil no cuenta con el permiso necesario para abrir este contenido o realizar esta acción.
                        @endif
                    </p>
                    <p class="text-muted mb-4">
                        Si necesitas acceso, solicita que revisen los permisos o asignaciones de tu cuenta.
                    </p>

                    <div class="d-flex flex-column flex-sm-row justify-content-center gap-2">
                        @can('viewAny', \App\Models\Expediente::class)
                            <a href="{{ route('expedientes.index') }}" class="btn btn-primary">
                                Volver a mis expedientes
                            </a>
                        @endcan
                        <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary">
                            Ir al inicio
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

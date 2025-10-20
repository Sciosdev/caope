<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'CAOPE') }}</title>

    {{-- NobleUI core styles --}}
    <link rel="stylesheet" href="{{ asset('assets/vendors/core/core.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/demo2/style.css') }}">

    {{-- Third-party styles via CDN (migrables a build posteriormente) --}}
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

    {{-- Hook para estilos específicos de cada vista --}}
    @stack('styles')
</head>
<body class="horizontal-menu">
    <div class="main-wrapper">
        @php
            $reportesRouteName = collect(['reportes.index', 'reports.index'])->first(fn ($name) => Route::has($name));
            $sesionesValidacionRouteName = collect([
                'sesiones.validacion',
                'sesiones.validacion.index',
                'sesiones.validar.index',
                'sesiones.validation.index',
            ])->first(fn ($name) => Route::has($name));
        @endphp

        <nav class="navbar navbar-expand-lg navbar-light bg-body border-bottom">
            <div class="container">
                <a class="navbar-brand d-flex align-items-center gap-2" href="{{ url('/') }}">
                    <img src="{{ asset('assets/images/logo-mini-dark.png') }}" height="26" alt="{{ config('app.name', 'CAOPE') }} logo">
                    <span class="fw-semibold">{{ config('app.name', 'CAOPE') }}</span>
                </a>

                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    <ul class="navbar-nav me-auto mb-2 mb-lg-0 align-items-lg-center gap-lg-2">
                        @auth
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('dashboard') ? 'active fw-semibold' : '' }}" href="{{ route('dashboard') }}">
                                    {{ __('Dashboard') }}
                                </a>
                            </li>
                            @can('expedientes.view')
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('expedientes.*') ? 'active fw-semibold' : '' }}" href="{{ route('expedientes.index') }}">
                                        {{ __('Expedientes') }}
                                    </a>
                                </li>
                            @endcan
                            @role('admin|coordinador')
                                @if ($reportesRouteName)
                                    <li class="nav-item">
                                        <a class="nav-link {{ request()->routeIs($reportesRouteName) ? 'active fw-semibold' : '' }}" href="{{ route($reportesRouteName) }}">
                                            {{ __('Reportes') }}
                                        </a>
                                    </li>
                                @endif
                            @endrole
                            @can('sesiones.validate')
                                @if ($sesionesValidacionRouteName)
                                    <li class="nav-item">
                                        <a class="nav-link {{ request()->routeIs($sesionesValidacionRouteName) ? 'active fw-semibold' : '' }}" href="{{ route($sesionesValidacionRouteName) }}">
                                            {{ __('Validación de sesiones') }}
                                        </a>
                                    </li>
                                @endif
                            @endcan
                        @endauth
                    </ul>

                    <div class="d-flex align-items-center gap-3">
                        <a href="#" class="text-muted small d-none d-lg-inline">{{ __('Ayuda') }}</a>

                        @auth
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    {{ \Illuminate\Support\Str::limit(Auth::user()->name, 18) }}
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <a class="dropdown-item" href="{{ route('profile.edit') }}">{{ __('Mi perfil') }}</a>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <form method="POST" action="{{ route('logout') }}">
                                            @csrf
                                            <button type="submit" class="dropdown-item">{{ __('Cerrar sesión') }}</button>
                                        </form>
                                    </li>
                                </ul>
                            </div>
                        @else
                            @if (Route::has('login'))
                                <a href="{{ route('login') }}" class="btn btn-sm btn-primary">{{ __('Iniciar sesión') }}</a>
                            @endif
                            @if (Route::has('register'))
                                <a href="{{ route('register') }}" class="text-muted small">{{ __('Registrarse') }}</a>
                            @endif
                        @endauth
                    </div>
                </div>
            </div>
        </nav>

        <header class="page-header border-bottom bg-body-tertiary">
            <div class="container py-4">
                <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
                    <div class="d-flex flex-column gap-2">
                        @isset($header)
                            {{ $header }}
                        @else
                            <h4 class="mb-0">{{ config('app.name', 'CAOPE') }}</h4>
                        @endisset

                        @hasSection('breadcrumbs')
                            <nav aria-label="breadcrumb">
                                <ol class="breadcrumb breadcrumb-style1 mb-0">
                                    @yield('breadcrumbs')
                                </ol>
                            </nav>
                        @endif
                    </div>

                    @hasSection('page-actions')
                        <div class="d-flex flex-wrap gap-2">
                            @yield('page-actions')
                        </div>
                    @endif
                </div>
            </div>
        </header>

        <div class="page-wrapper">
            <div class="page-content container py-4">
                {{ $slot }}
            </div>
        </div>
    </div>

    @stack('modals')

    {{-- Core scripts --}}
    <script src="{{ asset('assets/vendors/jquery/jquery.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/core/core.js') }}"></script>
    <script src="{{ asset('assets/js/app.js') }}" defer></script>

    {{-- Jetstream bundle (Alpine, etc.) --}}
    @vite(['resources/js/app.js'])

    {{-- Third-party libraries via CDN (listos para migrar a build) --}}
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    {{-- Hook para scripts específicos de cada vista --}}
    @stack('scripts')
</body>
</html>

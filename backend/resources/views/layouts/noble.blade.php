<!doctype html>
<html lang="es" data-bs-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>{{ config('app.name', 'CAOPE') }}</title>

  {{-- CSS global mínimo --}}
  <link rel="stylesheet" href="{{ asset('assets/vendors/core/core.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/css/demo2/style.css') }}">

  <link rel="stylesheet" href="{{ asset('assets/build/css/app-editor.css') }}">
  <script src="{{ asset('assets/build/js/app.js') }}" defer></script>

  {{-- Hook para CSS por página --}}
  @stack('styles')
</head>
<body class="horizontal-menu">
  <div class="main-wrapper">

    {{-- Topbar simple --}}
    @php
        $reportesRouteName = collect(['reportes.index', 'reports.index'])->first(fn ($name) => Route::has($name));
        $sesionesValidacionRouteName = collect([
            'sesiones.validacion',
            'sesiones.validacion.index',
            'sesiones.validar.index',
            'sesiones.validation.index',
        ])->first(fn ($name) => Route::has($name));
    @endphp

    <nav class="navbar">
      <div class="container d-flex justify-content-between align-items-center">
        <a class="navbar-brand d-flex align-items-center gap-2" href="{{ url('/') }}">
          <img src="{{ asset('assets/images/SDRI_oro.png') }}" height="26" alt="logo">
          <span>CAOPE</span>
        </a>
        <div class="d-flex align-items-center gap-3">
          @auth
            <a href="{{ route('dashboard') }}"
               class="text-muted small {{ request()->routeIs('dashboard') ? 'fw-semibold text-body' : '' }}">
              Pendientes
            </a>
            @can('expedientes.view')
              <a href="{{ route('expedientes.index') }}"
                 class="text-muted small {{ request()->routeIs('expedientes.*') ? 'fw-semibold text-body' : '' }}">
                Expedientes
              </a>
            @endcan
            @role('admin|coordinador')
              <a href="{{ route('consultorios.index') }}"
                 class="text-muted small {{ request()->routeIs('consultorios.*') ? 'fw-semibold text-body' : '' }}">
                Consultorios
              </a>
              @if ($reportesRouteName)
                <a href="{{ route($reportesRouteName) }}"
                   class="text-muted small {{ request()->routeIs($reportesRouteName) ? 'fw-semibold text-body' : '' }}">
                  Reportes
                </a>
              @endif
            @endrole
            @can('sesiones.validate')
              @if ($sesionesValidacionRouteName)
                <a href="{{ route($sesionesValidacionRouteName) }}"
                   class="text-muted small {{ request()->routeIs($sesionesValidacionRouteName) ? 'fw-semibold text-body' : '' }}">
                  Validación de sesiones
                </a>
              @endif
            @endcan

            <div class="dropdown">
              <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                {{ \Illuminate\Support\Str::limit(Auth::user()->name, 18) }}
              </button>
              <ul class="dropdown-menu dropdown-menu-end">
                <li>
                  <a class="dropdown-item" href="{{ route('profile.edit') }}">Mi perfil</a>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                  <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="dropdown-item">Cerrar sesión</button>
                  </form>
                </li>
              </ul>
            </div>
          @else
            @if (Route::has('login'))
              <a href="{{ route('login') }}" class="btn btn-sm btn-primary">Iniciar sesión</a>
            @endif

            @if (Route::has('register'))
              <a href="{{ route('register') }}" class="text-muted small">Registrarse</a>
            @endif
          @endauth
        </div>
      </div>
    </nav>

    {{-- Contenido de cada página --}}
    <div class="page-wrapper">
      <div class="page-content container">
        @yield('content')
      </div>
    </div>
  </div>

  {{-- JS global mínimo --}}
  <script src="{{ asset('assets/vendors/jquery/jquery.min.js') }}"></script>
  <script src="{{ asset('assets/vendors/core/core.js') }}"></script>

  {{-- Hook para JS por página --}}
  @stack('scripts')
</body>
</html>

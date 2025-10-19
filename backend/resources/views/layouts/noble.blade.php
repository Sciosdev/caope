<!doctype html>
<html lang="es" data-bs-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>{{ config('app.name', 'CAOPE') }}</title>

  {{-- CSS global mínimo --}}
  <link rel="stylesheet" href="{{ asset('assets/vendors/core/core.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/css/demo2/style.css') }}">

  {{-- Hook para CSS por página --}}
  @stack('styles')
</head>
<body class="horizontal-menu">
  <div class="main-wrapper">

    {{-- Topbar simple --}}
    <nav class="navbar">
      <div class="container d-flex justify-content-between align-items-center">
        <a class="navbar-brand d-flex align-items-center gap-2" href="{{ url('/') }}">
          <img src="{{ asset('assets/images/logo-mini-dark.png') }}" height="26" alt="logo">
          <span>CAOPE</span>
        </a>
        <div class="d-flex align-items-center gap-3">
          <a href="#" class="text-muted small">Ayuda</a>
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

<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>@yield('title','CAOPE')</title>

  <link rel="stylesheet" href="{{ asset('assets/vendors/core/core.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/vendors/flatpickr/flatpickr.min.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/vendors/select2/select2.min.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/vendors/sweetalert2/sweetalert2.min.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/vendors/datatables.net-bs5/dataTables.bootstrap5.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/css/demo2/style.css') }}">
</head>
<body class="horizontal-menu">
  <div class="main-wrapper">
    <nav class="navbar">
      <div class="container d-flex justify-content-between">
        <a class="navbar-brand" href="{{ url('/') }}">
          <img src="{{ asset('assets/images/logo-mini-dark.png') }}" height="28"> CAOPE
        </a>
        <a href="#" class="text-muted">Ayuda</a>
      </div>
    </nav>

    <div class="page-wrapper">
      <div class="page-content container">
        @yield('content')
      </div>
    </div>
  </div>

  <script src="{{ asset('assets/vendors/jquery/jquery.min.js') }}"></script>
  <script src="{{ asset('assets/vendors/core/core.js') }}"></script>
  <script src="{{ asset('assets/vendors/flatpickr/flatpickr.min.js') }}"></script>
  <script src="{{ asset('assets/vendors/select2/select2.min.js') }}"></script>
  <script src="{{ asset('assets/vendors/sweetalert2/sweetalert2.min.js') }}"></script>
  <script src="{{ asset('assets/vendors/datatables.net/dataTables.js') }}"></script>
  <script src="{{ asset('assets/vendors/datatables.net-bs5/dataTables.bootstrap5.js') }}"></script>
  @stack('scripts')
</body>
</html>

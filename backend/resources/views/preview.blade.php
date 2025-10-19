<!doctype html>
<html lang="es" data-bs-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Preview NobleUI (demo2)</title>

  <!-- CSS base de NobleUI -->
  <link rel="stylesheet" href="{{ asset('assets/vendors/core/core.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/vendors/flatpickr/flatpickr.min.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/vendors/select2/select2.min.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/vendors/sweetalert2/sweetalert2.min.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/vendors/datatables.net-bs5/dataTables.bootstrap5.css') }}">

  <!-- Estilos del layout horizontal (demo2) -->
  <link rel="stylesheet" href="{{ asset('assets/css/demo2/style.css') }}">
</head>
<body class="horizontal-menu">
  <div class="main-wrapper">

    <!-- Topbar mínima -->
    <nav class="navbar">
      <div class="container d-flex justify-content-between">
        <a class="navbar-brand" href="#"><img src="{{ asset('assets/images/logo-mini-dark.png') }}" height="28"> CAOPE</a>
        <span>Preview</span>
      </div>
    </nav>

    <!-- Contenido -->
    <div class="page-wrapper">
      <div class="page-content container">
        <h4 class="mb-3">Tabla de prueba</h4>
        <table id="tabla" class="table table-striped w-100">
          <thead><tr><th>No</th><th>Paciente</th><th>Estado</th><th>Apertura</th></tr></thead>
          <tbody>
            <tr><td>CA-2025-0001</td><td>Demo Uno</td><td><span class="badge bg-secondary">Abierto</span></td><td>2025-10-18</td></tr>
            <tr><td>CA-2025-0002</td><td>Demo Dos</td><td><span class="badge bg-warning">En revisión</span></td><td>2025-10-18</td></tr>
          </tbody>
        </table>

        <hr class="my-4">

        <label>Fecha (Flatpickr):</label>
        <input id="fecha" class="form-control w-auto" placeholder="Selecciona...">

        <div class="mt-3">
          <button id="alerta" class="btn btn-primary">SweetAlert</button>
        </div>
      </div>
    </div>
  </div>

  <!-- JS -->
  <script src="{{ asset('assets/vendors/jquery/jquery.min.js') }}"></script>
  <script src="{{ asset('assets/vendors/core/core.js') }}"></script>

  <script src="{{ asset('assets/vendors/flatpickr/flatpickr.min.js') }}"></script>
  <script src="{{ asset('assets/vendors/select2/select2.min.js') }}"></script>
  <script src="{{ asset('assets/vendors/sweetalert2/sweetalert2.min.js') }}"></script>

  <script src="{{ asset('assets/vendors/datatables.net/dataTables.js') }}"></script>
  <script src="{{ asset('assets/vendors/datatables.net-bs5/dataTables.bootstrap5.js') }}"></script>

  <script>
    // DataTable
    const tabla = new DataTable('#tabla', { responsive: true });

    // Datepicker
    flatpickr('#fecha', { dateFormat: 'Y-m-d' });

    // SweetAlert
    document.getElementById('alerta').addEventListener('click', () => {
      Swal.fire({ title: 'Listo', text: 'Assets cargaron ok', icon: 'success' });
    });
  </script>
</body>
</html>

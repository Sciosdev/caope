@extends('layouts.noble')

@section('content')
    <h4 class="mb-4">Dashboard</h4>

    <div class="row g-3">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <p class="text-muted mb-1">Expedientes abiertos</p>
                    <h3 class="mb-0">12</h3>
                </div>
            </div>
        </div>
        <div class="col-md-9">
            <div class="card">
                <div class="card-body">
                    <h6 class="mb-3">Ejemplo de tabla (DataTable)</h6>
                    <table id="tabla" class="table table-striped w-100">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Paciente</th>
                                <th>Estado</th>
                                <th>Apertura</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>CA-2025-0001</td>
                                <td>Demo Uno</td>
                                <td><span class="badge bg-secondary">Abierto</span></td>
                                <td>2025-10-18</td>
                            </tr>
                            <tr>
                                <td>CA-2025-0002</td>
                                <td>Demo Dos</td>
                                <td><span class="badge bg-warning">En revisión</span></td>
                                <td>2025-10-18</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendors/datatables.net-bs5/dataTables.bootstrap5.css') }}">
@endpush

@push('scripts')
    <script src="{{ asset('assets/vendors/datatables.net/dataTables.js') }}"></script>
    <script src="{{ asset('assets/vendors/datatables.net-bs5/dataTables.bootstrap5.js') }}"></script>
    <script>
        const tabla = new DataTable('#tabla', { responsive: true });
    </script>
@endpush

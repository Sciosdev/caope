@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
  <div class="container py-4">
    <h3 class="mb-4">Dashboard</h3>

    <div class="row g-3">
      <div class="col-md-3">
        <div class="card">
          <div class="card-body">
            <div class="fs-6 text-muted">Expedientes abiertos</div>
            <div class="fs-2 fw-semibold">
              {{ \App\Models\Expediente::where('estado','abierto')->count() }}
            </div>
          </div>
        </div>
      </div>
      <!-- aquí luego metemos más tarjetas/mini-reportes -->
    </div>
  </div>
@endsection

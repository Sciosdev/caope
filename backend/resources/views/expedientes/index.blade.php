@extends('layouts.app')

@section('title','Expedientes')

@section('content')
  <h4 class="mb-4">Expedientes</h4>

  <form class="row g-2 mb-3" method="get">
    <div class="col-sm-4">
      <input name="q" value="{{ $q }}" class="form-control" placeholder="Buscar (número o paciente)">
    </div>
    <div class="col-sm-2">
      <select name="estado" class="form-select">
        <option value="">Todos</option>
        <option value="abierto"  @selected($estado==='abierto')>Abierto</option>
        <option value="revision" @selected($estado==='revision')>En revisión</option>
        <option value="cerrado"  @selected($estado==='cerrado')>Cerrado</option>
      </select>
    </div>
    <div class="col-sm-2">
      <input name="desde" value="{{ optional($desde)->format('Y-m-d') }}" class="form-control flatpickr" placeholder="Desde">
    </div>
    <div class="col-sm-2">
      <input name="hasta" value="{{ optional($hasta)->format('Y-m-d') }}" class="form-control flatpickr" placeholder="Hasta">
    </div>
    <div class="col-sm-2 d-grid">
      <button class="btn btn-primary">Filtrar</button>
    </div>
  </form>

  <div class="table-responsive">
    <table id="expedientes-table" class="table table-striped">
      <thead>
        <tr>
          <th>No. de Control</th>
          <th>Paciente</th>
          <th>Estado</th>
          <th>Apertura</th>
          <th>Carrera</th>
          <th>Turno</th>
        </tr>
      </thead>
      <tbody>
        @foreach($expedientes as $e)
          <tr>
            <td>{{ $e->numero }}</td>
            <td>{{ $e->paciente }}</td>
            <td>
              @switch($e->estado)
                @case('abierto')  <span class="badge bg-secondary">Abierto</span> @break
                @case('revision') <span class="badge bg-warning">En revisión</span> @break
                @case('cerrado')  <span class="badge bg-success">Cerrado</span> @break
              @endswitch
            </td>
            <td>{{ $e->apertura->format('Y-m-d') }}</td>
            <td>{{ $e->carrera }}</td>
            <td>{{ $e->turno }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  <div class="mt-3">
    {{ $expedientes->links() }}
  </div>
@endsection

@push('scripts')
<script>
  flatpickr('.flatpickr', { dateFormat:'Y-m-d' });

  const dt = new DataTable('#expedientes-table', {
    responsive: true,
    searching: false, // filtramos con el form
    paging: false,    // paginación la hace Laravel
    info: false
  });
</script>
@endpush

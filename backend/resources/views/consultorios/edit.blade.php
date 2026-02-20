<x-app-layout>
    @section('breadcrumbs')
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Inicio</a></li>
        <li class="breadcrumb-item"><a href="{{ route('consultorios.index') }}">Consultorios</a></li>
        <li class="breadcrumb-item active" aria-current="page">Editar reserva</li>
    @endsection

    <div class="card">
        <div class="card-header">Modificar asignación</div>
        <div class="card-body">
            <form method="POST" action="{{ route('consultorios.update', $reserva) }}" class="row g-3">
                @csrf @method('PUT')
                <div class="col-md-2"><label class="form-label">Día</label><input type="date" name="fecha" class="form-control" value="{{ old('fecha', $reserva->fecha->format('Y-m-d')) }}" required></div>
                <div class="col-md-2"><label class="form-label">Inicio</label><input type="time" name="hora_inicio" class="form-control" value="{{ old('hora_inicio', substr($reserva->hora_inicio,0,5)) }}" required></div>
                <div class="col-md-2"><label class="form-label">Fin</label><input type="time" name="hora_fin" class="form-control" value="{{ old('hora_fin', substr($reserva->hora_fin,0,5)) }}" required></div>
                <div class="col-md-2">
                    <label class="form-label">Consultorio</label>
                    <select name="consultorio_numero" class="form-select" required>@for($i=1;$i<=14;$i++)<option value="{{ $i }}" @selected((int) old('consultorio_numero', $reserva->consultorio_numero)===$i)>Consultorio {{ $i }}</option>@endfor</select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Cubículo</label>
                    <select name="cubiculo_numero" class="form-select" required>@for($i=1;$i<=14;$i++)<option value="{{ $i }}" @selected((int) old('cubiculo_numero', $reserva->cubiculo_numero)===$i)>Cubículo {{ $i }}</option>@endfor</select>
                </div>
                <div class="col-md-4"><label class="form-label">Estrategia</label><input type="text" name="estrategia" class="form-control" value="{{ old('estrategia', $reserva->estrategia) }}" required></div>
                <div class="col-md-4"><label class="form-label">Usuario</label><select name="usuario_atendido_id" class="form-select"><option value="">--</option>@foreach($usuarios as $u)<option value="{{ $u->id }}" @selected((int) old('usuario_atendido_id', $reserva->usuario_atendido_id)===$u->id)>{{ $u->name }}</option>@endforeach</select></div>
                <div class="col-md-4"><label class="form-label">Estratega</label><select name="estratega_id" class="form-select"><option value="">--</option>@foreach($docentes as $u)<option value="{{ $u->id }}" @selected((int) old('estratega_id', $reserva->estratega_id)===$u->id)>{{ $u->name }}</option>@endforeach</select></div>
                <div class="col-md-4"><label class="form-label">Supervisor</label><select name="supervisor_id" class="form-select"><option value="">--</option>@foreach($usuarios as $u)<option value="{{ $u->id }}" @selected((int) old('supervisor_id', $reserva->supervisor_id)===$u->id)>{{ $u->name }}</option>@endforeach</select></div>
                <div class="col-12 d-flex gap-2">
                    <button class="btn btn-primary">Guardar cambios</button>
                    <a class="btn btn-outline-secondary" href="{{ route('consultorios.index') }}">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>

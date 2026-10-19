@can('delete', $expediente)
    <form action="{{ route('expedientes.destroy', $expediente) }}" method="post" class="d-inline"
        data-expediente="{{ $expediente->no_control }}"
        onsubmit="return confirm('¿Deseas eliminar el expediente ' + this.dataset.expediente + '? Esta acción no se puede deshacer.');">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn btn-outline-danger {{ $deleteButtonSize ?? '' }}"
            aria-label="Eliminar expediente {{ $expediente->no_control }}">Eliminar</button>
    </form>
@endcan

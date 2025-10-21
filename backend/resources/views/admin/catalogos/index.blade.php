<x-app-layout>
    @section('breadcrumbs')
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
        <li class="breadcrumb-item">{{ __('Catálogos') }}</li>
        <li class="breadcrumb-item active" aria-current="page">{{ __($resourceNamePlural) }}</li>
    @endsection

    @section('page-actions')
        <a href="{{ route($routePrefix . '.create') }}" class="btn btn-primary btn-sm">
            {{ __('Nuevo :resource', ['resource' => strtolower($resourceName)]) }}
        </a>
    @endsection

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('Nombre') }}</th>
                            <th scope="col">{{ __('Estado') }}</th>
                            <th scope="col">{{ __('Actualizado') }}</th>
                            <th scope="col" class="text-end">{{ __('Acciones') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($items as $item)
                            <tr>
                                <td>{{ $item->nombre }}</td>
                                <td>
                                    @if ($item->activo)
                                        <span class="badge text-bg-success">{{ __('Activo') }}</span>
                                    @else
                                        <span class="badge text-bg-secondary">{{ __('Inactivo') }}</span>
                                    @endif
                                </td>
                                <td>{{ $item->updated_at?->translatedFormat('d M Y H:i') }}</td>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-2">
                                        <a href="{{ route($routePrefix . '.edit', $item) }}" class="btn btn-outline-primary btn-sm">
                                            {{ __('Editar') }}
                                        </a>
                                        <form action="{{ route($routePrefix . '.destroy', $item) }}" method="POST"
                                            onsubmit="return confirm('{{ __('¿Deseas desactivar este elemento?') }}');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger btn-sm" {{ $item->activo ? '' : 'disabled' }}>
                                                {{ __('Desactivar') }}
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">
                                    {{ __('No hay :resource registrados todavía.', ['resource' => strtolower($resourceNamePlural)]) }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $items->links('pagination::bootstrap-5') }}
        </div>
    </div>
</x-app-layout>

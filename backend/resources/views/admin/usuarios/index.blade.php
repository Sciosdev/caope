<x-app-layout>
    @section('breadcrumbs')
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
        <li class="breadcrumb-item active" aria-current="page">{{ __('Usuarios') }}</li>
    @endsection

    @section('page-actions')
        <a href="{{ route('admin.users.create') }}" class="btn btn-primary btn-sm">{{ __('Nuevo usuario') }}</a>
    @endsection

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if ($errors->has('user'))
        <div class="alert alert-danger">{{ $errors->first('user') }}</div>
    @endif

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('Nombre') }}</th>
                            <th scope="col">{{ __('Correo electrónico') }}</th>
                            <th scope="col">{{ __('Roles') }}</th>
                            <th scope="col" class="text-end">{{ __('Acciones') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($users as $user)
                            <tr>
                                <td>{{ $user->name }}</td>
                                <td>{{ $user->email }}</td>
                                <td>
                                    @if ($user->roles->isEmpty())
                                        <span class="badge text-bg-secondary">{{ __('Sin rol') }}</span>
                                    @else
                                        @foreach ($user->roles as $role)
                                            <span class="badge text-bg-light text-capitalize">{{ $role->name }}</span>
                                        @endforeach
                                    @endif
                                </td>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-2">
                                        <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-outline-primary btn-sm">
                                            {{ __('Editar') }}
                                        </a>
                                        <form action="{{ route('admin.users.destroy', $user) }}" method="POST" onsubmit="return confirm('{{ __('¿Deseas eliminar este usuario?') }}');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger btn-sm">
                                                {{ __('Eliminar') }}
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">{{ __('No hay usuarios registrados todavía.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $users->links('pagination::bootstrap-5') }}
        </div>
    </div>
</x-app-layout>

<x-app-layout>
    @section('breadcrumbs')
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Inicio') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.users.index') }}">{{ __('Usuarios') }}</a></li>
        <li class="breadcrumb-item active" aria-current="page">{{ __('Editar usuario') }}</li>
    @endsection

    <div class="row justify-content-center">
        <div class="col-lg-8 col-xl-6">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title mb-4">{{ __('Editar usuario') }}</h5>

                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form action="{{ route('admin.users.update', $user) }}" method="POST">
                        @csrf
                        @method('PUT')
                        @include('admin.usuarios.partials.form', ['roles' => $roles, 'user' => $user])
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

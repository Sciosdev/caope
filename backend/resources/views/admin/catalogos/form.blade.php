<x-app-layout>
    @section('breadcrumbs')
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
        <li class="breadcrumb-item">{{ __('Catálogos') }}</li>
        <li class="breadcrumb-item"><a href="{{ route($routePrefix . '.index') }}">{{ __($resourceNamePlural) }}</a></li>
        <li class="breadcrumb-item active" aria-current="page">
            {{ $editing ? __('Editar :resource', ['resource' => strtolower($resourceName)]) : __('Nuevo :resource', ['resource' => strtolower($resourceName)]) }}
        </li>
    @endsection

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ $formAction }}">
                @csrf
                @if ($formMethod === 'PUT')
                    @method('PUT')
                @endif

                @include('admin.catalogos.partials.form-fields', ['item' => $item, 'editing' => $editing])
            </form>
        </div>
    </div>
</x-app-layout>

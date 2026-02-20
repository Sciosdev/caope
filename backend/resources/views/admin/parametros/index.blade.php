<x-app-layout>
    @section('breadcrumbs')
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Inicio') }}</a></li>
        <li class="breadcrumb-item active" aria-current="page">{{ __('Parámetros') }}</li>
    @endsection

    <div class="d-flex flex-column gap-4">
        @if (session('status'))
            <div class="alert alert-success mb-0">{{ session('status') }}</div>
        @endif

        @foreach ($parametros as $parametro)
            @php
                $definition = $metadata[$parametro->clave] ?? ['label' => $parametro->clave];
                $errorBag = 'parametro-' . $parametro->getKey();
                $hasErrors = $errors->getBag($errorBag)->isNotEmpty();
                $value = $hasErrors ? old('valor') : $parametro->valor;
            @endphp
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start gap-3">
                        <div>
                            <h5 class="card-title mb-1">{{ $definition['label'] }}</h5>
                            @if (!empty($definition['description']))
                                <p class="text-muted small mb-0">{{ $definition['description'] }}</p>
                            @endif
                        </div>
                        <span class="badge text-bg-light text-uppercase">{{ $parametro->tipo }}</span>
                    </div>

                    <form method="POST" action="{{ route('admin.parametros.update', $parametro) }}" class="mt-3">
                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            @if (($definition['input'] ?? 'text') === 'textarea')
                                <textarea name="valor" rows="4" class="form-control @error('valor', $errorBag) is-invalid @enderror">{{ $value }}</textarea>
                            @else
                                <input
                                    type="{{ $parametro->tipo === \App\Models\Parametro::TYPE_INTEGER ? 'number' : 'text' }}"
                                    name="valor"
                                    value="{{ is_scalar($value) ? $value : '' }}"
                                    class="form-control @error('valor', $errorBag) is-invalid @enderror"
                                >
                            @endif
                            @error('valor', $errorBag)
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary btn-sm">Guardar cambios</button>
                        </div>
                    </form>

                    <p class="text-muted small mb-0 mt-2">Última actualización: {{ optional($parametro->updated_at)->diffForHumans() ?? 'Nunca' }}</p>
                </div>
            </div>
        @endforeach
    </div>
</x-app-layout>

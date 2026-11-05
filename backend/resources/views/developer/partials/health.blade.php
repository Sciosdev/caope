    @php
        $errorCount = collect($checks)->where('status', 'error')->count();
        $warningCount = collect($checks)->where('status', 'warning')->count();
        $overallStatus = $errorCount > 0 ? 'error' : ($warningCount > 0 ? 'warning' : 'ok');
        $overallClasses = [
            'ok' => ['alert-success', 'Operación normal'],
            'warning' => ['alert-warning', 'Requiere atención'],
            'error' => ['alert-danger', 'Se detectaron errores'],
        ][$overallStatus];
    @endphp
        @if ($synchronizationWarning)
            <div class="alert alert-warning mb-0">{{ $synchronizationWarning }}</div>
        @endif

        <div class="alert {{ $overallClasses[0] }} d-flex flex-wrap justify-content-between align-items-center gap-2 mb-0">
            <strong>{{ $overallClasses[1] }}</strong>
            <span>{{ count($checks) }} comprobaciones · {{ $errorCount }} errores · {{ $warningCount }} advertencias</span>
        </div>

        <div class="row g-3">
            @foreach ($checks as $check)
                @php
                    $statusPresentation = [
                        'ok' => ['text-bg-success', 'Correcto'],
                        'warning' => ['text-bg-warning', 'Advertencia'],
                        'error' => ['text-bg-danger', 'Error'],
                    ][$check['status']];
                @endphp
                <div class="col-md-6 col-xl-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start gap-3 mb-2">
                                <h5 class="card-title mb-0">{{ $check['label'] }}</h5>
                                <span class="badge {{ $statusPresentation[0] }}">{{ $statusPresentation[1] }}</span>
                            </div>
                            <p class="mb-1">{{ $check['summary'] }}</p>
                            @if ($check['details'])
                                <p class="text-muted small mb-0 text-break">{{ $check['details'] }}</p>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

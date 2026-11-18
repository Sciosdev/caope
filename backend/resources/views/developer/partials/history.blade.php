        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Historial de despliegues</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Solicitó</th>
                                <th>Referencia</th>
                                <th>Commit</th>
                                <th>Estado</th>
                                <th class="text-end">Registro</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($deployments as $deployment)
                                <tr>
                                    <td>{{ $deployment->created_at?->format('d/m/Y H:i:s') }}</td>
                                    <td>
                                        {{ $deployment->requestedBy?->name ?? 'Usuario eliminado' }}
                                        @if ($deployment->requestedBy?->email)
                                            <div class="text-muted small">{{ $deployment->requestedBy->email }}</div>
                                        @endif
                                    </td>
                                    <td><code>{{ $deployment->ref }}</code></td>
                                    <td><code>{{ $deployment->commit_sha ? substr($deployment->commit_sha, 0, 8) : '—' }}</code></td>
                                    <td>
                                        <span class="badge {{ $deployment->badgeClass() }}">{{ $deployment->statusLabel() }}</span>
                                        <div class="small text-muted mt-1">{{ $deployment->stageLabel() }}</div>
                                    </td>
                                    <td class="text-end">
                                        @if ($deployment->workflow_url)
                                            <a href="{{ $deployment->workflow_url }}" target="_blank" rel="noopener noreferrer" class="btn btn-outline-secondary btn-sm">
                                                Ver en GitHub
                                            </a>
                                        @else
                                            <span class="text-muted">Pendiente</span>
                                        @endif
                                    </td>
                                </tr>
                                @if ($deployment->error_message)
                                    <tr>
                                        <td colspan="6" class="small text-danger bg-danger-subtle">{{ $deployment->error_message }}</td>
                                    </tr>
                                @endif
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">Todavía no hay despliegues registrados.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

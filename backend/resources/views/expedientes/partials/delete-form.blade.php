@php($lifecycleModalId = 'expediente-lifecycle-'.$expediente->getKey())
@php($hasActiveRetention = $expediente->retention_until?->isFuture() ?? false)
@php($renderLifecycleButton = !($lifecycleModalOnly ?? false))
@php($renderLifecycleModal = !($lifecycleButtonOnly ?? false))

@if ($expediente->trashed())
    @can('restore', $expediente)
        @if ($renderLifecycleButton)
            <button type="button" class="btn btn-outline-primary {{ $deleteButtonSize ?? '' }}" data-bs-toggle="modal" data-bs-target="#{{ $lifecycleModalId }}">
                Restaurar
            </button>
        @endif

        @if ($renderLifecycleModal)
            <div class="modal fade text-start" id="{{ $lifecycleModalId }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <form action="{{ route('expedientes.restore', $expediente) }}" method="post" class="modal-content">
                        @csrf
                        <input type="hidden" name="lifecycle_action" value="restore">
                        <div class="modal-header">
                            <h5 class="modal-title">Restaurar expediente {{ $expediente->no_control }}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-warning">
                                La restauración habilita nuevamente el expediente, pero no elimina su protección de conservación
                                hasta {{ optional($expediente->retention_until)->format('d/m/Y H:i') ?? 'la fecha registrada' }}.
                            </div>
                            <label class="form-label" for="restore-motivo-{{ $expediente->id }}">Motivo</label>
                            <textarea id="restore-motivo-{{ $expediente->id }}" name="motivo" class="form-control mb-3" minlength="10" maxlength="1000" required></textarea>
                            <div class="form-text mb-3">Registra solo una razón operativa breve, sin datos personales del alumno.</div>
                            <label class="form-label" for="restore-folio-{{ $expediente->id }}">
                                Escribe exactamente <strong>{{ $expediente->no_control }}</strong>
                            </label>
                            <input id="restore-folio-{{ $expediente->id }}" name="folio_confirmation" class="form-control" autocomplete="off" required>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-primary">Restaurar expediente</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    @endcan
@else
    @can('delete', $expediente)
        @if ($renderLifecycleButton)
            <button type="button" class="btn btn-outline-danger {{ $deleteButtonSize ?? '' }}" data-bs-toggle="modal" data-bs-target="#{{ $lifecycleModalId }}">
                Gestionar baja
            </button>
        @endif

        @if ($renderLifecycleModal)
            <div class="modal fade text-start" id="{{ $lifecycleModalId }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                    <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Gestionar expediente {{ $expediente->no_control }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <p>Selecciona una de las dos acciones. No son equivalentes.</p>

                        <form action="{{ route('expedientes.destroy', $expediente) }}" method="post" class="border rounded p-3 mb-4">
                            @csrf
                            @method('DELETE')
                            <input type="hidden" name="lifecycle_action" value="archive">
                            <h6>1. Archivar y conservar</h6>
                            <p class="text-muted">
                                Retira el expediente de la operación diaria y conserva íntegros sus datos y documentos durante un mínimo de 10 años desde hoy.
                            </p>
                            <label class="form-label" for="archive-motivo-{{ $expediente->id }}">Motivo</label>
                            <textarea id="archive-motivo-{{ $expediente->id }}" name="motivo" class="form-control mb-2" minlength="10" maxlength="1000" required></textarea>
                            <div class="form-text mb-3">Registra solo una razón operativa breve, sin datos personales del alumno.</div>
                            <label class="form-label" for="archive-folio-{{ $expediente->id }}">
                                Escribe exactamente <strong>{{ $expediente->no_control }}</strong>
                            </label>
                            <input id="archive-folio-{{ $expediente->id }}" name="folio_confirmation" class="form-control mb-3" autocomplete="off" required>
                            <button type="submit" class="btn btn-warning">Archivar por mínimo 10 años</button>
                        </form>

                        @if ($hasActiveRetention)
                            <div class="border border-secondary rounded p-3">
                                <h6 class="text-muted">2. Eliminación definitiva no disponible</h6>
                                <div class="alert alert-warning mb-0">
                                    Este expediente conserva una protección vigente hasta
                                    {{ $expediente->retention_until->format('d/m/Y H:i') }}.
                                    Restaurarlo no elimina esa obligación, por lo que no puede purgarse ni siquiera como registro creado por error.
                                </div>
                            </div>
                        @else
                            <form action="{{ route('expedientes.force-destroy', $expediente) }}" method="post" class="border border-danger rounded p-3">
                                @csrf
                                @method('DELETE')
                                <input type="hidden" name="lifecycle_action" value="purge_error">
                                <h6 class="text-danger">2. Eliminar porque fue creado por error</h6>
                                <div class="alert alert-danger">
                                    Esta acción elimina definitivamente el expediente, sesiones, consentimientos, anexos y documentos asociados.
                                    No puede deshacerse y no debe usarse para expedientes reales.
                                </div>
                                <label class="form-label" for="purge-motivo-{{ $expediente->id }}">Motivo del error</label>
                                <textarea id="purge-motivo-{{ $expediente->id }}" name="motivo" class="form-control mb-2" minlength="10" maxlength="1000" required></textarea>
                                <div class="form-text mb-3">Registra solo la causa del error, sin datos personales del alumno.</div>
                                <label class="form-label" for="purge-folio-{{ $expediente->id }}">
                                    Escribe exactamente <strong>{{ $expediente->no_control }}</strong>
                                </label>
                                <input id="purge-folio-{{ $expediente->id }}" name="folio_confirmation" class="form-control mb-3" autocomplete="off" required>
                                <button type="submit" class="btn btn-danger">Eliminar definitivamente por error</button>
                            </form>
                        @endif
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    </div>
                    </div>
                </div>
            </div>
        @endif
    @endcan
@endif

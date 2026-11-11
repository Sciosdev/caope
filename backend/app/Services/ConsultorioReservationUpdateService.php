<?php

namespace App\Services;

use App\Models\CatalogoConsultorio;
use App\Models\ConsultorioReserva;
use App\Models\ConsultorioReservaSolicitud;
use App\Models\Expediente;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ConsultorioReservationUpdateService
{
    /** @var list<string> */
    private const SCHEDULE_FIELDS = [
        'fecha',
        'hora_inicio',
        'hora_fin',
        'consultorio_numero',
        'cubiculo_numero',
    ];

    /** @var list<string> */
    private const EDITABLE_FIELDS = [
        'fecha',
        'hora_inicio',
        'hora_fin',
        'consultorio_numero',
        'cubiculo_numero',
        'estrategia',
        'expediente_id',
        'usuario_atendido_id',
        'estratega_id',
        'supervisor_id',
    ];

    public function __construct(
        private ConsultorioReservationGuard $guard,
        private TimelineLogger $timeline,
    ) {}

    /**
     * Update an already approved reservation in place. No new reservation is
     * created and a pending request can never be bypassed through its URL.
     *
     * @param  array<string, mixed>  $changes
     */
    public function updateApproved(
        ConsultorioReserva $reserva,
        array $changes,
        User $actor,
        bool $scheduleOnly = false,
    ): ConsultorioReserva {
        return $this->apply(
            $reserva,
            $changes,
            $actor,
            scheduleOnly: $scheduleOnly,
        );
    }

    /**
     * Apply the immutable proposal payload and mark that same request as
     * attended atomically. Its original payload remains unchanged as audit
     * evidence of what PAPS requested.
     *
     * @param  array<string, mixed>  $changes
     */
    public function approvePendingEdit(
        ConsultorioReservaSolicitud $solicitud,
        ConsultorioReserva $reserva,
        array $changes,
        User $actor,
    ): ConsultorioReserva {
        return $this->apply(
            $reserva,
            $changes,
            $actor,
            approvedSolicitudId: (int) $solicitud->getKey(),
        );
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private function apply(
        ConsultorioReserva $reserva,
        array $changes,
        User $actor,
        bool $scheduleOnly = false,
        ?int $approvedSolicitudId = null,
    ): ConsultorioReserva {
        if (! $actor->hasRole('admin')) {
            throw new AuthorizationException('Solo el administrador puede aplicar cambios de reserva.');
        }

        $changes = Arr::only($changes, $scheduleOnly ? self::SCHEDULE_FIELDS : self::EDITABLE_FIELDS);
        $changes['cubiculo_numero'] = $changes['consultorio_numero'] ?? null;
        $destination = (int) ($changes['consultorio_numero'] ?? 0);

        return DB::transaction(function () use (
            $reserva,
            $changes,
            $actor,
            $scheduleOnly,
            $approvedSolicitudId,
            $destination,
        ): ConsultorioReserva {
            $candidateExpedienteIds = collect([
                $reserva->expediente_id,
                array_key_exists('expediente_id', $changes) ? $changes['expediente_id'] : $reserva->expediente_id,
            ])
                ->filter(fn ($id): bool => is_numeric($id) && (int) $id > 0)
                ->map(fn ($id): int => (int) $id)
                ->unique()
                ->values();

            $roomNumbers = ConsultorioReserva::query()
                ->whereIn('expediente_id', $candidateExpedienteIds)
                ->pluck('consultorio_numero')
                ->push($reserva->consultorio_numero)
                ->push($destination)
                ->map(fn ($numero): int => (int) $numero)
                ->filter(fn (int $numero): bool => $numero > 0)
                ->unique()
                ->values()
                ->all();

            // Shared lock order with expediente assignment: catalog first,
            // then expediente, then the reservation and its pending request.
            $this->guard->lockConsultorios($roomNumbers, $destination);

            $expedientes = Expediente::withTrashed()
                ->whereIn('id', $candidateExpedienteIds->all())
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $locked = ConsultorioReserva::query()
                ->whereKey($reserva->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->expediente_id !== null && ! $expedientes->has((int) $locked->expediente_id)) {
                throw ValidationException::withMessages([
                    'reserva' => 'La reserva cambió mientras se abría el formulario. Recarga la página e inténtalo nuevamente.',
                ]);
            }

            $targetExpedienteId = array_key_exists('expediente_id', $changes)
                ? ($changes['expediente_id'] === null ? null : (int) $changes['expediente_id'])
                : ($locked->expediente_id === null ? null : (int) $locked->expediente_id);

            if ($targetExpedienteId !== null && ! $expedientes->has($targetExpedienteId)) {
                throw ValidationException::withMessages([
                    'expediente_id' => 'El expediente vinculado ya no está disponible.',
                ]);
            }

            foreach ($expedientes as $expediente) {
                if ($expediente->trashed()) {
                    throw ValidationException::withMessages([
                        'reserva' => 'Un expediente archivado es de solo lectura; su reserva no puede modificarse.',
                    ]);
                }
            }

            $approvedSolicitud = $this->lockPendingState($locked, $approvedSolicitudId);

            if ($scheduleOnly && $locked->expediente_id === null) {
                throw ValidationException::withMessages([
                    'reserva' => 'Esta reserva ya no está vinculada a un expediente.',
                ]);
            }

            $this->guard->assertAvailable(
                $destination,
                [(string) $changes['fecha']],
                (string) $changes['hora_inicio'],
                (string) $changes['hora_fin'],
                (int) $locked->getKey(),
            );

            $before = $this->scheduleSnapshot($locked);
            $oldExpedienteId = $locked->expediente_id === null ? null : (int) $locked->expediente_id;
            $locked->update($changes);
            $locked->refresh();
            $after = $this->scheduleSnapshot($locked);

            $affectedExpedienteIds = collect([$oldExpedienteId, $locked->expediente_id])
                ->filter(fn ($id): bool => is_numeric($id) && (int) $id > 0)
                ->map(fn ($id): int => (int) $id)
                ->unique();

            foreach ($affectedExpedienteIds as $expedienteId) {
                $expediente = $expedientes->get($expedienteId);
                if (! $expediente) {
                    continue;
                }

                $this->syncEffectiveCubiculo($expediente);

                if ($before !== $after && (int) $locked->expediente_id === $expedienteId) {
                    $this->timeline->log($expediente, 'cubiculo.reprogramado', $actor, [
                        'reserva_id' => (int) $locked->getKey(),
                        'antes' => $before,
                        'despues' => $after,
                    ]);
                }
            }

            if ($approvedSolicitud) {
                $approvedSolicitud->update(['status' => 'atendida']);
            }

            return $locked;
        }, 3);
    }

    private function lockPendingState(
        ConsultorioReserva $reserva,
        ?int $approvedSolicitudId,
    ): ?ConsultorioReservaSolicitud {
        if (! Schema::hasTable('consultorio_reserva_solicitudes')) {
            if ($approvedSolicitudId !== null) {
                throw ValidationException::withMessages([
                    'reserva' => 'Las solicitudes de modificación no están disponibles temporalmente.',
                ]);
            }

            return null;
        }

        $pending = ConsultorioReservaSolicitud::query()
            ->where('consultorio_reserva_id', $reserva->getKey())
            ->where('status', 'pendiente')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ($approvedSolicitudId === null) {
            if ($pending->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'reserva' => 'Esta reserva tiene una solicitud pendiente. Atiéndela antes de modificar la reserva directamente.',
                ]);
            }

            return null;
        }

        $approved = $pending->firstWhere('id', $approvedSolicitudId);
        if (! $approved || $approved->tipo !== 'edicion') {
            throw ValidationException::withMessages([
                'reserva' => 'La solicitud ya fue atendida o no corresponde a una edición vigente.',
            ]);
        }

        return $approved;
    }

    private function syncEffectiveCubiculo(Expediente $expediente): void
    {
        $effective = $expediente->reservasCubiculo()
            ->lockForUpdate()
            ->first(['consultorio_numero']);
        if (! $effective) {
            return;
        }

        $cubiculoId = CatalogoConsultorio::query()
            ->where('numero', $effective->consultorio_numero)
            ->value('id');

        if (! $cubiculoId) {
            // Historical reservations can reference a retired legacy number.
            // Preserve the current expediente header instead of guessing a
            // canonical identity or blocking another valid reprogramming.
            return;
        }

        if ((int) $expediente->cubiculo_id === (int) $cubiculoId) {
            return;
        }

        $expediente->forceFill(['cubiculo_id' => $cubiculoId])->save();
    }

    /**
     * @return array{fecha: ?string, hora_inicio: string, hora_fin: string, consultorio_numero: int}
     */
    private function scheduleSnapshot(ConsultorioReserva $reserva): array
    {
        return [
            'fecha' => $reserva->fecha?->toDateString(),
            'hora_inicio' => substr((string) $reserva->hora_inicio, 0, 5),
            'hora_fin' => substr((string) $reserva->hora_fin, 0, 5),
            'consultorio_numero' => (int) $reserva->consultorio_numero,
        ];
    }
}

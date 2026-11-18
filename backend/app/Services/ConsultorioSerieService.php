<?php

namespace App\Services;

use App\Models\CatalogoConsultorio;
use App\Models\ConsultorioReserva;
use App\Models\ConsultorioReservaSolicitud;
use App\Models\Expediente;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

final class ConsultorioSerieService
{
    public function __construct(private TimelineLogger $timeline) {}

    public function available(): bool
    {
        return Schema::hasColumn('consultorio_reservas', 'serie_uuid')
            && Schema::hasTable('consultorio_serie_operaciones')
            && Schema::hasTable('consultorio_reserva_solicitudes');
    }

    public function preview(ConsultorioReserva $base): array
    {
        $rows = $this->suffix($base);

        return ['ocurrencias' => $rows, 'revision' => $this->revision($rows)];
    }

    public function apply(ConsultorioReserva $base, array $data, User $actor): int
    {
        if (! $actor->hasRole('admin')) {
            throw new AuthorizationException;
        }
        if (! $this->available()) {
            $this->fail('reserva', 'La gestión de series estará disponible cuando termine la actualización. No se cambió ninguna reserva.');
        }
        if ($data['alcance'] === 'siguientes' && ! $base->serie_uuid) {
            $this->fail('alcance', 'La reserva no tiene una serie identificada. Solo se puede cambiar esta fecha.');
        }
        $move = $data['accion'] === 'reprogramar';

        return DB::transaction(function () use ($base, $data, $actor, $move): int {
            $initial = $this->suffix($base);
            $expectedRevision = $this->revision($initial);
            if (! hash_equals($expectedRevision, $data['revision'])) {
                $this->stale();
            }
            $planned = $data['alcance'] === 'esta' ? $initial->take(1) : $initial;
            // Same lock order as individual edits: physical rooms, cases, reservations,
            // then pending requests. Use current reads after the room locks (InnoDB).
            $roomNumbers = $planned->pluck('consultorio_numero');
            if ($move) {
                $roomNumbers->push((int) $data['consultorio_numero']);
            }
            $rooms = CatalogoConsultorio::query()->whereIn('numero', $roomNumbers->unique()->all())
                ->orderBy('numero')->lockForUpdate()->get()->keyBy('numero');
            if ($move && ! $rooms->get((int) $data['consultorio_numero'])?->activo) {
                $this->fail('consultorio_numero', 'Selecciona un cubículo activo.');
            }
            $cases = Expediente::withTrashed()->whereIn('id', $planned->pluck('expediente_id')->filter()->unique()->all())
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $current = $this->suffix($base, lock: true);
            if (! hash_equals($expectedRevision, $this->revision($current))) {
                $this->stale();
            }
            $rows = $data['alcance'] === 'esta' ? $current->take(1) : $current;
            $ids = $rows->modelKeys();
            if (ConsultorioReservaSolicitud::query()->whereIn('consultorio_reserva_id', $ids)->where('status', 'pendiente')
                ->orderBy('id')->lockForUpdate()->first(['id'])) {
                $this->fail('alcance', 'Hay una solicitud pendiente entre las fechas seleccionadas. Resuélvela en la bandeja; no se cambió ninguna reserva.');
            }
            $localNow = now('America/Mexico_City');
            $base = $rows->first();
            $baseDate = CarbonImmutable::parse($base->fecha->toDateString(), 'UTC');
            $delta = $move ? (int) $baseDate->diffInDays(CarbonImmutable::parse($data['fecha'], 'UTC'), false) : 0;
            $changes = [];
            $destDates = [];
            foreach ($rows as $row) {
                if (! $row->hasNotStarted($localNow)) {
                    $this->fail('alcance', 'Una de las reservas ya comenzó. No se cambió ninguna reserva.');
                }
                if ($this->identity($row) !== $this->identity($base)) {
                    $this->fail('alcance', 'Las asignaciones de la serie ya no coinciden. Gestiona la fecha por separado.');
                }
                if ($row->expediente_id && (! $cases->has($row->expediente_id) || $cases->get($row->expediente_id)->trashed())) {
                    $this->fail('alcance', 'Un expediente archivado o no disponible no permite modificar sus reservas.');
                }
                $after = null;
                if ($move) {
                    $date = CarbonImmutable::parse($row->fecha->toDateString(), 'UTC')->addDays($delta);
                    $newDate = $date->toDateString();
                    if ($date->isSunday() || $date->year > 9999) {
                        $this->fail('fecha', 'Una fecha resultante queda fuera de los días permitidos (lunes a sábado).');
                    }
                    if ($newDate.' '.$data['hora_inicio'].':00' <= $localNow->toDateTimeString()) {
                        $this->fail('fecha', 'Todas las fechas y horas de destino deben ser futuras.');
                    }
                    if (isset($destDates[$newDate])) {
                        $this->fail('hora_inicio', "Dos fechas seleccionadas quedarían en el mismo cubículo y horario el {$newDate}.");
                    }
                    $destDates[$newDate] = true;
                    $after = ['fecha' => $newDate, 'hora_inicio' => $data['hora_inicio'], 'hora_fin' => $data['hora_fin'], 'consultorio_numero' => (int) $data['consultorio_numero']];
                }
                $before = $this->slot($row);
                if ($before !== $after) {
                    $changes[] = ['reserva_id' => $row->id, 'antes' => $before, 'despues' => $after];
                }
            }
            if (! $changes) {
                $this->fail('reserva', 'El horario y el cubículo ya coinciden con lo solicitado. No hay cambios que aplicar.');
            }
            if ($move) {
                // Omit every selected row (they all move together), but never any other
                // row, including unlisted, historical, and pending reservations.
                $conflict = ConsultorioReserva::query()->whereIntegerNotInRaw('id', $ids)
                    ->where('consultorio_numero', $data['consultorio_numero'])->whereIn('fecha', array_keys($destDates))
                    ->whereTime('hora_inicio', '<', $data['hora_fin'].':00')->whereTime('hora_fin', '>', $data['hora_inicio'].':00')
                    ->orderBy('fecha')->orderBy('id')->lockForUpdate()->first(['fecha']);
                if ($conflict) {
                    $this->fail('hora_inicio', 'Empalme en el cubículo núm. '.$data['consultorio_numero'].' el '.$conflict->fecha->format('d/m/Y').' de '.$data['hora_inicio'].' a '.$data['hora_fin'].'. No se cambió ninguna reserva.');
                }
            }

            $operationId = DB::table('consultorio_serie_operaciones')->insertGetId([
                'actor_id' => $actor->id, 'reserva_origen_id' => $base->id, 'serie_uuid' => $base->serie_uuid,
                'accion' => $data['accion'], 'alcance' => $data['alcance'], 'motivo' => $data['motivo'],
                'cambios' => json_encode($changes, JSON_THROW_ON_ERROR), 'created_at' => now(),
            ]);
            $byId = $rows->keyBy('id');
            foreach ($changes as $change) {
                $row = $byId->get($change['reserva_id']);
                if ($move) {
                    $row->update($change['despues'] + ['cubiculo_numero' => (int) $data['consultorio_numero']]);
                } else {
                    // Only a space reservation. No clinical session or attachment is deleted.
                    $row->delete();
                }
                if ($row->expediente_id) {
                    $this->timeline->log($cases->get($row->expediente_id), $move ? 'cubiculo.reprogramado' : 'cubiculo.reserva_cancelada', $actor, $change + ['operacion_id' => $operationId]);
                }
            }
            foreach ($cases as $case) {
                $effective = $case->reservasCubiculo()->lockForUpdate()->first(['consultorio_numero']);
                if ($effective && ($roomId = CatalogoConsultorio::query()->where('numero', $effective->consultorio_numero)->value('id'))) {
                    $case->forceFill(['cubiculo_id' => $roomId])->save();
                }
            }

            return count($changes);
        }, 3);
    }

    private function suffix(ConsultorioReserva $anchor, bool $lock = false): Collection
    {
        $query = ConsultorioReserva::query();
        if ($anchor->serie_uuid) {
            $query->where('serie_uuid', $anchor->serie_uuid);
        } else {
            $query->whereKey($anchor->getKey());
        }
        $all = $query->orderBy('fecha')->orderBy('hora_inicio')->orderBy('id')
            ->when($lock, fn ($rows) => $rows->lockForUpdate())->get();
        $start = $all->search(fn ($row) => $row->is($anchor));
        if ($start === false) {
            $this->stale();
        }

        return $all->slice($start)->values();
    }

    private function slot(ConsultorioReserva $row): array
    {
        return ['fecha' => $row->fecha->toDateString(), 'hora_inicio' => substr($row->hora_inicio, 0, 5), 'hora_fin' => substr($row->hora_fin, 0, 5), 'consultorio_numero' => (int) $row->consultorio_numero];
    }

    private function identity(ConsultorioReserva $row): array
    {
        return $row->only(['serie_uuid', 'expediente_id', 'estrategia', 'usuario_atendido_id', 'estratega_id', 'supervisor_id']);
    }

    private function revision(Collection $rows): string
    {
        return hash('sha256', $rows->map(fn ($row) => [$row->id, $this->slot($row), $this->identity($row)])->toJson(JSON_THROW_ON_ERROR));
    }

    private function stale(): never
    {
        $this->fail('revision', 'La reserva o su serie cambió desde que se abrió el formulario. Revisa de nuevo las fechas antes de confirmar.');
    }

    private function fail(string $key, string $message): never
    {
        throw ValidationException::withMessages([$key => $message]);
    }
}

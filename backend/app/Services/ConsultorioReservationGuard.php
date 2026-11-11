<?php

namespace App\Services;

use App\Models\CatalogoConsultorio;
use App\Models\ConsultorioReserva;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use LogicException;

class ConsultorioReservationGuard
{
    /**
     * Serialize every reservation write for the same physical room.
     */
    public function lockConsultorio(int $numero): void
    {
        $this->lockConsultorios([$numero], $numero);
    }

    /**
     * Lock physical rooms in a stable order before locking an expediente or
     * reservation. The destination must remain active; historical rooms may
     * be inactive and are locked only to preserve a consistent lock order.
     *
     * @param  array<int, int>  $numeros
     */
    public function lockConsultorios(array $numeros, int $requiredActive): void
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('A consultorio must be locked inside a database transaction.');
        }

        $normalized = collect($numeros)
            ->push($requiredActive)
            ->map(fn ($numero): int => (int) $numero)
            ->filter(fn (int $numero): bool => $numero > 0)
            ->unique()
            ->sort()
            ->values();

        $consultorios = CatalogoConsultorio::query()
            ->whereIn('numero', $normalized)
            ->orderBy('numero')
            ->lockForUpdate()
            ->get(['id', 'numero', 'activo'])
            ->keyBy('numero');

        $consultorio = $consultorios->get($requiredActive);

        if ($consultorio === null || ! $consultorio->activo) {
            throw ValidationException::withMessages([
                'consultorio_numero' => 'El cubículo seleccionado no está disponible.',
            ]);
        }
    }

    /**
     * Before writing, call this after lockConsultorio() in the same transaction.
     * Unscoped reservations include pending approvals and otherwise hidden rows.
     *
     * @param  array<int, string>  $fechas
     */
    public function assertAvailable(
        int $consultorio,
        array $fechas,
        string $inicio,
        string $fin,
        ?int $ignoreId = null,
        string $errorKey = 'hora_inicio'
    ): void {
        $horaInicio = $this->normalizeTime($inicio, $errorKey);
        $horaFin = $this->normalizeTime($fin, $errorKey);

        if ($horaInicio >= $horaFin || $fechas === []) {
            throw ValidationException::withMessages([
                $errorKey => 'El horario seleccionado no es válido.',
            ]);
        }

        $fechasNormalizadas = [];
        foreach ($fechas as $fecha) {
            if (! is_string($fecha) || trim($fecha) === '') {
                throw ValidationException::withMessages([
                    $errorKey => 'La fecha seleccionada no es válida.',
                ]);
            }

            try {
                $fechasNormalizadas[] = Carbon::parse($fecha)->toDateString();
            } catch (InvalidArgumentException) {
                throw ValidationException::withMessages([
                    $errorKey => 'La fecha seleccionada no es válida.',
                ]);
            }
        }

        $query = ConsultorioReserva::query()
            ->where('consultorio_numero', $consultorio)
            ->whereIn('fecha', array_unique($fechasNormalizadas))
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->whereTime('hora_inicio', '<', $horaFin)
            ->whereTime('hora_fin', '>', $horaInicio);

        // MySQL's locking SELECT uses a current read after a competing writer
        // commits, even if request validation established an older RR snapshot.
        $overlap = DB::transactionLevel() > 0
            ? $query->lockForUpdate()->first(['id']) !== null
            : $query->exists();

        if ($overlap) {
            throw ValidationException::withMessages([
                $errorKey => 'Ese cubículo ya está reservado en el bloque seleccionado.',
            ]);
        }
    }

    private function normalizeTime(string $time, string $errorKey): string
    {
        if (preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/D', $time) !== 1) {
            throw ValidationException::withMessages([
                $errorKey => 'El horario seleccionado no es válido.',
            ]);
        }

        return strlen($time) === 5 ? $time.':00' : $time;
    }
}

<?php

namespace App\Exports;

use App\Exports\Concerns\BindsSpreadsheetValuesSafely;
use App\Models\ConsultorioReserva;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;

/**
 * @implements FromQuery<ConsultorioReserva>
 */
class ConsultorioReservasExport extends DefaultValueBinder implements FromQuery, ShouldQueue, WithHeadings, WithMapping
{
    use BindsSpreadsheetValuesSafely;

    public function __construct(private readonly int $userId) {}

    public function headings(): array
    {
        return [
            'Fecha',
            'Hora inicio',
            'Hora fin',
            'Consultorio',
            'Cubículo',
            'Estrategia',
            'Estratega',
            'Facilitador',
            'Supervisor',
            'Creado por',
            'Creado en',
        ];
    }

    /**
     * @param  ConsultorioReserva  $reserva
     */
    public function map($reserva): array
    {
        $reserva->loadMissing(['usuarioAtendido', 'estratega', 'supervisor', 'creadoPor']);

        return [
            optional($reserva->fecha)->format('Y-m-d'),
            substr((string) $reserva->hora_inicio, 0, 5),
            substr((string) $reserva->hora_fin, 0, 5),
            $reserva->consultorio_numero,
            $reserva->cubiculo_numero,
            $reserva->estrategia,
            $reserva->estratega?->name ?? '',
            $reserva->usuarioAtendido?->name ?? '',
            $reserva->supervisor?->name ?? '',
            $reserva->creadoPor?->name ?? '',
            optional($reserva->created_at)->format('Y-m-d H:i:s'),
        ];
    }

    public function query(): Builder
    {
        $user = User::query()->find($this->userId);

        $query = ConsultorioReserva::query()
            ->visibleTo($user)
            ->with(['usuarioAtendido:id,name', 'estratega:id,name', 'supervisor:id,name', 'creadoPor:id,name'])
            ->orderByDesc('fecha')
            ->orderByDesc('hora_inicio');

        if (! Schema::hasTable('consultorio_reserva_solicitudes')) {
            return $query;
        }

        return $query->whereDoesntHave('solicitudes', function (Builder $solicitudes): void {
            $solicitudes
                ->where('status', 'pendiente')
                ->whereIn('tipo', ['edicion', 'baja']);
        });
    }
}

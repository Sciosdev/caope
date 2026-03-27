<?php

namespace App\Exports;

use App\Models\ConsultorioReserva;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;

/**
 * @implements FromQuery<ConsultorioReserva>
 */
class ConsultorioReservasExport extends DefaultValueBinder implements FromQuery, WithHeadings, WithMapping, ShouldQueue
{
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
            'Usuario atendido',
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
        return ConsultorioReserva::query()
            ->with(['usuarioAtendido:id,name', 'estratega:id,name', 'supervisor:id,name', 'creadoPor:id,name'])
            ->orderByDesc('fecha')
            ->orderByDesc('hora_inicio');
    }
}

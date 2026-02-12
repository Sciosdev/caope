<?php

namespace App\Exports;

use App\Models\TimelineEvento;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;

/**
 * @implements FromQuery<TimelineEvento>
 */
class TimelineEventosExport extends DefaultValueBinder implements FromQuery, WithHeadings, WithMapping, ShouldQueue
{
    public function __construct(private readonly ?int $expedienteId = null)
    {
    }

    public function headings(): array
    {
        return [
            __('Fecha y hora'),
            __('No. de control'),
            __('Consultante'),
            __('Evento'),
            __('Actor'),
            __('Detalles'),
        ];
    }

    /**
     * @param  TimelineEvento  $timelineEvento
     */
    public function map($timelineEvento): array
    {
        $timelineEvento->loadMissing(['expediente', 'actor']);

        $payload = $timelineEvento->payload ?? [];
        $payloadString = empty($payload)
            ? ''
            : json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return [
            optional($timelineEvento->created_at)->format('Y-m-d H:i:s'),
            $timelineEvento->expediente?->no_control ?? '',
            $timelineEvento->expediente?->paciente ?? '',
            $timelineEvento->evento,
            $timelineEvento->actor?->name ?? 'Sistema',
            $payloadString,
        ];
    }

    public function query(): Builder
    {
        $query = TimelineEvento::query()
            ->with(['expediente', 'actor'])
            ->orderByDesc('created_at');

        if ($this->expedienteId !== null) {
            $query->where('expediente_id', $this->expedienteId);
        }

        return $query;
    }
}

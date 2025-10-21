<?php

namespace App\Exports;

use App\Models\Expediente;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;

/**
 * @implements FromQuery<Expediente>
 */
class ExpedientesExport extends DefaultValueBinder implements FromQuery, WithHeadings, WithMapping, ShouldQueue
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(private readonly array $filters)
    {
    }

    public function headings(): array
    {
        return [
            'No. de control',
            'Paciente',
            'Estado',
            'Fecha de apertura',
            'Tutor asignado',
            'Coordinador',
            'Capturado por',
        ];
    }

    /**
     * @param  Expediente  $expediente
     */
    public function map($expediente): array
    {
        $expediente->loadMissing(['tutor', 'coordinador', 'creadoPor']);

        return [
            $expediente->no_control,
            $expediente->paciente,
            $expediente->estado,
            optional($expediente->apertura)->format('Y-m-d'),
            optional($expediente->tutor)->name,
            optional($expediente->coordinador)->name,
            optional($expediente->creadoPor)->name,
        ];
    }

    public function query(): Builder
    {
        $query = Expediente::query()->orderByDesc('apertura');

        return $this->applyFilters($query, $this->filters);
    }

    public function bindValue(Cell $cell, $value): bool
    {
        if ($cell->getColumn() === 'A') {
            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    /**
     * @param  Builder<Expediente>  $query
     * @param  array<string, mixed>  $filters
     * @return Builder<Expediente>
     */
    private function applyFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['estado'] ?? null, fn (Builder $q, string $estado): Builder => $q->where('estado', $estado))
            ->when($filters['desde'] ?? null, fn (Builder $q, string $desde): Builder => $q->whereDate('apertura', '>=', $desde))
            ->when($filters['hasta'] ?? null, fn (Builder $q, string $hasta): Builder => $q->whereDate('apertura', '<=', $hasta))
            ->when($filters['tutor_id'] ?? null, fn (Builder $q, int $tutorId): Builder => $q->where('tutor_id', $tutorId))
            ->when($filters['coordinador_id'] ?? null, fn (Builder $q, int $coordinadorId): Builder => $q->where('coordinador_id', $coordinadorId))
            ->when($filters['creado_por'] ?? null, fn (Builder $q, int $creadoPor): Builder => $q->where('creado_por', $creadoPor));
    }
}

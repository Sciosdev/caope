<?php

namespace App\Exports;

use App\Exports\Concerns\BindsSpreadsheetValuesSafely;
use App\Models\Expediente;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;

/**
 * @implements FromQuery<Expediente>
 */
class ExpedientesExport extends DefaultValueBinder implements FromQuery, WithHeadings, WithMapping
{
    use BindsSpreadsheetValuesSafely;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(
        private readonly array $filters,
        private readonly int $userId,
        private readonly ?array $allowedExpedienteIds = null,
    ) {}

    public function headings(): array
    {
        return [
            'No. de control',
            'Alumno',
            'Estado',
            'Fecha de apertura',
            'Estratega',
            'Coordinador',
            'Facilitador',
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
        $user = User::query()->find($this->userId);
        $query = Expediente::query()
            ->visibleTo($user)
            ->orderByDesc('apertura');

        if ($this->allowedExpedienteIds !== null) {
            $this->allowedExpedienteIds === []
                ? $query->whereRaw('1 = 0')
                : $query->whereKey($this->allowedExpedienteIds);
        }

        return $this->applyFilters($query, $this->filters);
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

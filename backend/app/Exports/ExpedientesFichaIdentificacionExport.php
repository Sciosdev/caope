<?php

namespace App\Exports;

use App\Models\Expediente;
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
class ExpedientesFichaIdentificacionExport extends DefaultValueBinder implements FromQuery, WithHeadings, WithMapping
{
    /**
     * @param  Builder<Expediente>  $query
     */
    public function __construct(private readonly Builder $query)
    {
    }

    public function query(): Builder
    {
        return clone $this->query;
    }

    public function headings(): array
    {
        return [
            'No. de control',
            'Consultante',
            'Estado',
            'Apertura',
            'Carrera',
            'Turno',
            'Clínica',
            'Género',
            'Estado civil',
            'Ocupación',
            'Escolaridad',
            'Fecha de nacimiento',
            'Lugar de nacimiento',
            'Domicilio (calle)',
            'Colonia',
            'Delegación/Municipio',
            'Entidad',
            'Teléfono principal',
            'Fecha de inicio real',
        ];
    }

    /**
     * @param  Expediente  $expediente
     */
    public function map($expediente): array
    {
        return [
            $expediente->no_control,
            $expediente->paciente,
            $expediente->estado,
            optional($expediente->apertura)->format('Y-m-d'),
            $expediente->carrera,
            $expediente->turno,
            $expediente->clinica,
            $expediente->genero,
            $expediente->estado_civil,
            $expediente->ocupacion,
            $expediente->escolaridad,
            optional($expediente->fecha_nacimiento)->format('Y-m-d'),
            $expediente->lugar_nacimiento,
            $expediente->domicilio_calle,
            $expediente->colonia,
            $expediente->delegacion_municipio,
            $expediente->entidad,
            $expediente->telefono_principal,
            optional($expediente->fecha_inicio_real)->format('Y-m-d'),
        ];
    }

    public function bindValue(Cell $cell, $value): bool
    {
        if (in_array($cell->getColumn(), ['A', 'R'], true)) {
            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }
}

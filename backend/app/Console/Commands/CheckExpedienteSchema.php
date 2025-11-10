<?php

namespace App\Console\Commands;

use App\Models\Expediente;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class CheckExpedienteSchema extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'expedientes:check-schema';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Detecta columnas faltantes en la tabla de expedientes que impiden a los alumnos guardar sus expedientes.';

    public function handle(): int
    {
        $table = (new Expediente())->getTable();

        $optionalColumns = [
            'antecedentes_familiares',
            'antecedentes_observaciones',
            'antecedentes_personales_patologicos',
            'antecedentes_personales_observaciones',
            'antecedente_padecimiento_actual',
            'plan_accion',
            'diagnostico',
            'dsm_tr',
            'observaciones_relevantes',
            'aparatos_sistemas',
        ];

        $missingColumns = collect($optionalColumns)
            ->filter(fn (string $column) => ! Schema::hasColumn($table, $column))
            ->values();

        if ($missingColumns->isEmpty()) {
            $this->info('La tabla de expedientes contiene todas las columnas requeridas para el perfil Alumno.');

            return self::SUCCESS;
        }

        $this->error('La tabla de expedientes no tiene todas las columnas opcionales disponibles.');

        $missingColumns->each(function (string $column): void {
            $this->line("- {$column}");
        });

        $this->newLine();
        $this->warn('Ejecuta las migraciones pendientes (php artisan migrate) o revisa la base de datos para agregar las columnas faltantes.');

        return self::FAILURE;
    }
}

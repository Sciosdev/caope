<?php

namespace Tests\Feature\Console;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Tests\TestCase;

class CheckExpedienteSchemaCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_reports_success_when_columns_present(): void
    {
        $this->artisan('expedientes:check-schema')
            ->expectsOutput('La tabla de expedientes contiene todas las columnas requeridas para el perfil Alumno.')
            ->assertExitCode(SymfonyCommand::SUCCESS);
    }

    public function test_command_reports_missing_columns(): void
    {
        $columnsToDrop = array_filter([
            Schema::hasColumn('expedientes', 'plan_accion') ? 'plan_accion' : null,
            Schema::hasColumn('expedientes', 'diagnostico') ? 'diagnostico' : null,
        ]);

        if (! empty($columnsToDrop)) {
            Schema::table('expedientes', function (Blueprint $table) use ($columnsToDrop): void {
                $table->dropColumn($columnsToDrop);
            });
        }

        try {
            $this->artisan('expedientes:check-schema')
                ->expectsOutput('La tabla de expedientes no tiene todas las columnas opcionales disponibles.')
                ->expectsOutputToContain('- plan_accion')
                ->expectsOutputToContain('- diagnostico')
                ->assertExitCode(SymfonyCommand::FAILURE);
        } finally {
            Schema::table('expedientes', function (Blueprint $table): void {
                if (! Schema::hasColumn('expedientes', 'plan_accion')) {
                    $table->text('plan_accion')->nullable();
                }

                if (! Schema::hasColumn('expedientes', 'diagnostico')) {
                    $table->text('diagnostico')->nullable();
                }
            });
        }
    }
}

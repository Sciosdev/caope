<?php

namespace Tests\Unit\Services;

use App\Models\Consentimiento;
use App\Models\Expediente;
use App\Models\Sesion;
use App\Models\User;
use App\Services\ExpedienteStateValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpedienteStateValidatorTest extends TestCase
{
    use RefreshDatabase;

    private ExpedienteStateValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = new ExpedienteStateValidator();
    }

    public function test_requires_at_least_one_validated_session(): void
    {
        $expediente = Expediente::factory()->create();

        Sesion::factory()->for($expediente)->create([
            'status_revision' => 'pendiente',
        ]);

        $messages = $this->validator->validateClosureRequirements($expediente);

        $this->assertEquals([
            'El expediente debe tener al menos una sesión validada antes de cerrarse.',
        ], $messages->all());
    }

    public function test_detects_sessions_with_pending_observations(): void
    {
        $expediente = Expediente::factory()->create();
        $validador = User::factory()->create();

        Sesion::factory()->for($expediente)->create([
            'status_revision' => 'validada',
            'validada_por' => $validador->id,
        ]);

        Sesion::factory()->for($expediente)->create([
            'status_revision' => 'observada',
        ]);

        $messages = $this->validator->validateClosureRequirements($expediente);

        $this->assertEquals([
            'No se puede cerrar el expediente con sesiones observadas pendientes de validación.',
        ], $messages->all());
    }

    public function test_detects_pending_required_consents(): void
    {
        $expediente = Expediente::factory()->create();
        $validador = User::factory()->create();

        Sesion::factory()->for($expediente)->create([
            'status_revision' => 'validada',
            'validada_por' => $validador->id,
        ]);

        Consentimiento::factory()->for($expediente)->create([
            'requerido' => true,
            'aceptado' => false,
        ]);

        $messages = $this->validator->validateClosureRequirements($expediente);

        $this->assertEquals([
            'Todos los consentimientos requeridos deben estar aceptados y contar con su archivo firmado antes de cerrar el expediente.',
        ], $messages->all());
    }

    public function test_returns_empty_collection_when_all_rules_are_met(): void
    {
        $expediente = Expediente::factory()->create();
        $usuario = User::factory()->create();

        Sesion::factory()->for($expediente)->create([
            'status_revision' => 'validada',
            'validada_por' => $usuario->id,
        ]);

        Consentimiento::factory()->for($expediente)->create([
            'requerido' => true,
            'aceptado' => true,
            'archivo_path' => 'consentimientos/archivo.pdf',
            'subido_por' => $usuario->id,
        ]);

        $messages = $this->validator->validateClosureRequirements($expediente);

        $this->assertTrue($messages->isEmpty());
    }
}

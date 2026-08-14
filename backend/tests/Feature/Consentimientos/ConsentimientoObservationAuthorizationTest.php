<?php

namespace Tests\Feature\Consentimientos;

use App\Models\Expediente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ConsentimientoObservationAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'alumno']);
        Role::firstOrCreate(['name' => 'docente']);
    }

    public function test_facilitator_cannot_reassign_tutor_through_observations_payload(): void
    {
        $facilitador = User::factory()->create();
        $facilitador->assignRole('alumno');
        $tutorActual = User::factory()->create();
        $tutorActual->assignRole('docente');
        $nuevoTutor = User::factory()->create();
        $nuevoTutor->assignRole('docente');

        $expediente = Expediente::factory()->create([
            'creado_por' => $facilitador->id,
            'tutor_id' => $tutorActual->id,
            'estado' => 'abierto',
        ]);

        $this->actingAs($facilitador)
            ->post(route('expedientes.consentimientos.observaciones', $expediente), [
                'observaciones' => 'Actualización permitida.',
                'tutor_id' => $nuevoTutor->id,
            ])
            ->assertRedirect();

        $this->assertSame($tutorActual->id, $expediente->fresh()->tutor_id);
        $this->assertSame('Actualización permitida.', $expediente->fresh()->consentimientos_observaciones);
    }

    public function test_multi_role_facilitator_cannot_assign_itself_as_tutor_through_observations(): void
    {
        $facilitador = User::factory()->create();
        $facilitador->assignRole(['alumno', 'docente']);

        $expediente = Expediente::factory()->create([
            'creado_por' => $facilitador->id,
            'tutor_id' => null,
            'estado' => 'abierto',
        ]);

        $this->actingAs($facilitador)
            ->post(route('expedientes.consentimientos.observaciones', $expediente), [
                'tutor_id' => $facilitador->id,
            ])
            ->assertRedirect();

        $this->assertNull($expediente->fresh()->tutor_id);
    }
}

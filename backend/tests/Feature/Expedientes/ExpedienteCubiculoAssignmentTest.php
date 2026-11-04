<?php

namespace Tests\Feature\Expedientes;

use App\Models\CatalogoCarrera;
use App\Models\CatalogoConsultorio;
use App\Models\CatalogoTurno;
use App\Models\Expediente;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ExpedienteCubiculoAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private CatalogoConsultorio $firstRoom;

    private CatalogoConsultorio $secondRoom;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RoleSeeder::class);

        CatalogoCarrera::query()->create(['nombre' => 'Carrera sintética', 'activo' => true]);
        CatalogoTurno::query()->create(['nombre' => 'Matutino', 'activo' => true]);
        $this->firstRoom = CatalogoConsultorio::query()->create([
            'numero' => 3,
            'nombre' => 'Cubículo Azul Norte',
            'activo' => true,
        ]);
        $this->secondRoom = CatalogoConsultorio::query()->create([
            'numero' => 18,
            'nombre' => 'Cubículo de Entrevista Familiar',
            'activo' => true,
        ]);

        CatalogoCarrera::flushCache();
        CatalogoTurno::flushCache();
        CatalogoConsultorio::flushCache();
    }

    public function test_historical_summary_is_shown_as_pending_without_selecting_a_same_number_room(): void
    {
        $admin = $this->user('admin');
        $expediente = $this->expediente([
            'cubiculo_id' => null,
            'resumen_clinico' => array_merge(Expediente::defaultClinicalSummary(), [
                'cubiculo' => $this->firstRoom->numero,
            ]),
        ]);

        $response = $this->actingAs($admin)
            ->get(route('expedientes.edit', $expediente))
            ->assertOk()
            ->assertSee('Asignación anterior: Cubículo 3. Pendiente de confirmar en el catálogo unificado.')
            ->assertSee($this->firstRoom->nombre)
            ->assertDontSee('name="resumen_clinico[cubiculo]"', false);

        $document = new DOMDocument;
        @$document->loadHTML($response->getContent());
        $selectedOptions = (new DOMXPath($document))->query('//select[@name="cubiculo_id"]/option[@selected]');

        $this->assertSame(0, $selectedOptions->length);
        $this->assertNull($expediente->fresh()->cubiculo_id);
        $this->assertSame(3, data_get($expediente->fresh()->resumen_clinico, 'cubiculo'));
    }

    #[DataProvider('privilegedProfiles')]
    public function test_admin_and_approved_paps_can_confirm_a_canonical_room_without_rewriting_history(string $role): void
    {
        $manager = $this->user($role, approved: true);
        $expediente = $this->expediente([
            'cubiculo_id' => $this->firstRoom->id,
            'resumen_clinico' => array_merge(Expediente::defaultClinicalSummary(), [
                'cubiculo' => 7,
                'nota_alta' => 'Texto histórico original',
            ]),
        ]);

        $this->actingAs($manager)
            ->put(route('expedientes.update', $expediente), $this->updatePayload($expediente, [
                'cubiculo_id' => $this->secondRoom->id,
                'resumen_clinico' => [
                    'cubiculo' => 999,
                    'nota_alta' => 'Texto clínico actualizado',
                ],
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('expedientes.show', $expediente));

        $expediente->refresh();
        $this->assertSame($this->secondRoom->id, $expediente->cubiculo_id);
        $this->assertSame(7, data_get($expediente->resumen_clinico, 'cubiculo'));
        $this->assertSame('Texto clínico actualizado', data_get($expediente->resumen_clinico, 'nota_alta'));
        $this->assertDatabaseCount('consultorio_reservas', 0);
        $this->assertDatabaseHas('timeline_eventos', [
            'expediente_id' => $expediente->id,
            'actor_id' => $manager->id,
            'evento' => 'expediente.actualizado',
        ]);

        $event = $expediente->timelineEventos()
            ->where('evento', 'expediente.actualizado')
            ->latest('created_at')
            ->firstOrFail();
        $this->assertContains('cubiculo_id', $event->payload['campos']);
    }

    public function test_assigned_facilitator_cannot_change_the_canonical_room_with_a_forged_payload(): void
    {
        $facilitator = $this->user('alumno');
        $expediente = $this->expediente([
            'facilitador_id' => $facilitator->id,
            'cubiculo_id' => $this->firstRoom->id,
            'estado' => 'abierto',
        ]);

        $this->actingAs($facilitator)
            ->put(route('expedientes.update', $expediente), $this->updatePayload($expediente, [
                'cubiculo_id' => $this->secondRoom->id,
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('expedientes.show', $expediente));

        $this->assertSame($this->firstRoom->id, $expediente->fresh()->cubiculo_id);
        $this->assertDatabaseCount('consultorio_reservas', 0);
    }

    public function test_an_inactive_room_cannot_be_newly_assigned_to_an_expediente(): void
    {
        $admin = $this->user('admin');
        $expediente = $this->expediente(['cubiculo_id' => $this->firstRoom->id]);
        $this->secondRoom->update(['activo' => false]);
        CatalogoConsultorio::flushCache();

        $this->actingAs($admin)
            ->put(route('expedientes.update', $expediente), $this->updatePayload($expediente, [
                'cubiculo_id' => $this->secondRoom->id,
            ]))
            ->assertSessionHasErrors('cubiculo_id');

        $this->assertSame($this->firstRoom->id, $expediente->fresh()->cubiculo_id);
    }

    public function test_current_inactive_room_can_be_preserved_while_updating_clinical_data(): void
    {
        $admin = $this->user('admin');
        $expediente = $this->expediente([
            'cubiculo_id' => $this->firstRoom->id,
            'resumen_clinico' => array_merge(Expediente::defaultClinicalSummary(), [
                'cubiculo' => 11,
                'nota_alta' => 'Nota anterior',
            ]),
        ]);
        $this->firstRoom->update(['activo' => false]);
        CatalogoConsultorio::flushCache();

        $this->actingAs($admin)
            ->put(route('expedientes.update', $expediente), $this->updatePayload($expediente, [
                'cubiculo_id' => $this->firstRoom->id,
                'resumen_clinico' => [
                    'cubiculo' => 999,
                    'nota_alta' => 'Nota clínica actualizada',
                ],
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('expedientes.show', $expediente));

        $expediente->refresh();
        $this->assertSame($this->firstRoom->id, $expediente->cubiculo_id);
        $this->assertSame(11, data_get($expediente->resumen_clinico, 'cubiculo'));
        $this->assertSame('Nota clínica actualizada', data_get($expediente->resumen_clinico, 'nota_alta'));
        $this->assertDatabaseCount('consultorio_reservas', 0);
    }

    public static function privilegedProfiles(): array
    {
        return [
            'administration' => ['admin'],
            'approved PAPS' => ['paps'],
        ];
    }

    private function user(string $role, bool $approved = false): User
    {
        $user = User::factory()->create([
            'approved_at' => $approved ? now() : null,
            'is_active' => true,
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function expediente(array $overrides = []): Expediente
    {
        return Expediente::factory()->create(array_replace([
            'carrera' => 'Carrera sintética',
            'turno' => 'Matutino',
            'estado' => 'abierto',
            'resumen_clinico' => Expediente::defaultClinicalSummary(),
        ], $overrides));
    }

    private function updatePayload(Expediente $expediente, array $overrides = []): array
    {
        return array_replace([
            'no_control' => $expediente->no_control,
            'paciente' => $expediente->paciente,
            'apertura' => $expediente->apertura->toDateString(),
            'carrera' => $expediente->carrera,
            'turno' => $expediente->turno,
        ], $overrides);
    }
}

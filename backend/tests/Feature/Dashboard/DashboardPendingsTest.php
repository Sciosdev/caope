<?php

namespace Tests\Feature\Dashboard;

use App\Models\Expediente;
use App\Models\Sesion;
use App\Models\User;
use App\Notifications\ExpedienteClosureAttemptNotification;
use App\Notifications\SesionObservedNotification;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\Fluent\AssertableJson;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DashboardPendingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RoleSeeder::class);
    }

    public function test_dashboard_view_is_accessible(): void
    {
        $user = User::factory()->create();
        $user->assignRole('docente');

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Bandeja de pendientes');
    }

    public function test_tutor_receives_pending_cards(): void
    {
        $tutor = User::factory()->create();
        $tutor->assignRole('docente');

        $creador = User::factory()->create();

        $expediente = Expediente::factory()->create([
            'estado' => 'revision',
            'creado_por' => $creador->id,
            'tutor_id' => $tutor->id,
        ]);

        $pendiente = Sesion::factory()->for($expediente)->create([
            'status_revision' => 'pendiente',
            'fecha' => Carbon::parse('2024-01-10'),
        ]);

        $observada = Sesion::factory()->for($expediente)->create([
            'status_revision' => 'observada',
            'fecha' => Carbon::parse('2024-01-05'),
        ]);

        $tutor->notify(new SesionObservedNotification($observada));
        $tutor->notify(new ExpedienteClosureAttemptNotification($expediente));

        $response = $this->actingAs($tutor)->getJson(route('dashboard.pending'));

        $response->assertOk();
        $response->assertJson(fn (AssertableJson $json) => $json->has('cards', 3)
            ->has('cards.0', fn (AssertableJson $card) => $card->where('id', 'validaciones')
                ->where('count', 1)
                ->has('items', 1)
                ->etc()
            )
            ->has('cards.1', fn (AssertableJson $card) => $card->where('id', 'observados')
                ->where('count', 1)
                ->etc()
            )
            ->has('cards.2', fn (AssertableJson $card) => $card->where('id', 'intentos_cierre')
                ->where('count', 1)
                ->etc()
            )
            ->etc()
        );
    }

    public function test_observed_notification_is_hidden_when_session_is_missing(): void
    {
        $student = User::factory()->create();
        $student->assignRole('alumno');

        $tutor = User::factory()->create();
        $expediente = Expediente::factory()->create([
            'estado' => 'revision',
            'creado_por' => $student->id,
            'tutor_id' => $tutor->id,
        ]);

        $student->notifications()->create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'type' => SesionObservedNotification::class,
            'data' => [
                'expediente_id' => $expediente->id,
                'sesion_id' => 999999,
                'fecha' => '2024-02-01',
                'actor_name' => 'Docente',
                'message' => 'La sesión #999999 fue observada y requiere tu atención.',
            ],
        ]);

        $response = $this->actingAs($student)->getJson(route('dashboard.pending'));

        $response->assertOk();
        $response->assertJsonPath('cards.0.id', 'observados');
        $response->assertJsonPath('cards.0.count', 0);
        $response->assertJsonCount(0, 'cards.0.items');
    }

    public function test_student_sees_observed_card_when_notified(): void
    {
        $student = User::factory()->create();
        $student->assignRole('alumno');

        $tutor = User::factory()->create();

        $expediente = Expediente::factory()->create([
            'estado' => 'revision',
            'creado_por' => $student->id,
            'tutor_id' => $tutor->id,
        ]);

        $sesion = Sesion::factory()->for($expediente)->create([
            'status_revision' => 'observada',
            'realizada_por' => $student->id,
            'fecha' => Carbon::parse('2024-02-01'),
        ]);

        $student->notify(new SesionObservedNotification($sesion));

        $response = $this->actingAs($student)->getJson(route('dashboard.pending'));

        $response->assertOk();
        $response->assertJsonCount(1, 'cards');
        $response->assertJsonPath('cards.0.id', 'observados');
        $response->assertJsonPath('cards.0.count', 1);
    }

    public function test_coordinator_only_receives_validations_from_assigned_expedientes(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole('coordinador');
        $otroCoordinador = User::factory()->create();

        $asignado = Expediente::factory()->create([
            'coordinador_id' => $coordinador->id,
        ]);
        $ajeno = Expediente::factory()->create([
            'coordinador_id' => $otroCoordinador->id,
        ]);

        $sesionAsignada = Sesion::factory()->for($asignado)->create([
            'status_revision' => 'pendiente',
        ]);
        $sesionAjena = Sesion::factory()->for($ajeno)->create([
            'status_revision' => 'pendiente',
        ]);

        $response = $this->actingAs($coordinador)->getJson(route('dashboard.pending'));

        $response->assertOk();
        $response->assertJsonPath('cards.0.id', 'validaciones');
        $response->assertJsonPath('cards.0.count', 1);
        $response->assertJsonPath('cards.0.items.0.id', $sesionAsignada->id);
        $response->assertJsonMissing(['id' => $sesionAjena->id]);
    }

    public function test_reassignment_hides_stale_notifications_from_facilitator(): void
    {
        $facilitador = User::factory()->create();
        $facilitador->assignRole('alumno');
        $nuevoFacilitador = User::factory()->create();

        $expediente = Expediente::factory()->create([
            'creado_por' => $facilitador->id,
        ]);
        $sesion = Sesion::factory()->for($expediente)->create([
            'status_revision' => 'observada',
            'realizada_por' => $facilitador->id,
        ]);

        $facilitador->notify(new SesionObservedNotification($sesion));
        $expediente->update(['creado_por' => $nuevoFacilitador->id]);

        $response = $this->actingAs($facilitador)->getJson(route('dashboard.pending'));

        $response->assertOk();
        $response->assertJsonPath('cards.0.id', 'observados');
        $response->assertJsonPath('cards.0.count', 0);
        $response->assertJsonCount(0, 'cards.0.items');
        $response->assertJsonMissing(['primary' => 'Información ya no asignada.']);
    }

    public function test_session_author_change_hides_stale_notification_without_reassigning_expediente(): void
    {
        $facilitador = User::factory()->create();
        $facilitador->assignRole('alumno');
        $otherUser = User::factory()->create();

        $expediente = Expediente::factory()->create([
            'creado_por' => $facilitador->id,
        ]);
        $sesion = Sesion::factory()->for($expediente)->create([
            'status_revision' => 'observada',
            'realizada_por' => $facilitador->id,
        ]);

        $facilitador->notify(new SesionObservedNotification($sesion));
        $sesion->update(['realizada_por' => $otherUser->id]);

        $response = $this->actingAs($facilitador)->getJson(route('dashboard.pending'));

        $response->assertOk();
        $response->assertJsonPath('cards.0.id', 'observados');
        $response->assertJsonPath('cards.0.count', 0);
        $response->assertJsonCount(0, 'cards.0.items');
        $response->assertJsonMissing(['primary' => 'Autoría ya no asignada.']);
        $this->assertSame($facilitador->id, $expediente->fresh()->creado_por);
    }
}

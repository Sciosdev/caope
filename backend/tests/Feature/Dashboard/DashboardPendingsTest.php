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
        $actor = User::factory()->create();

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

        $tutor->notify(new SesionObservedNotification($observada, $actor, 'Faltan anexos firmados.'));
        $tutor->notify(new ExpedienteClosureAttemptNotification($expediente, $actor, ['Sesiones pendientes de validar.']));

        $response = $this->actingAs($tutor)->getJson(route('dashboard.pending'));

        $response->assertOk();
        $response->assertJson(fn (AssertableJson $json) =>
            $json->has('cards', 3)
                ->has('cards.0', fn (AssertableJson $card) =>
                    $card->where('id', 'validaciones')
                        ->where('count', 1)
                        ->has('items', 1)
                        ->etc()
                )
                ->has('cards.1', fn (AssertableJson $card) =>
                    $card->where('id', 'observados')
                        ->where('count', 1)
                        ->etc()
                )
                ->has('cards.2', fn (AssertableJson $card) =>
                    $card->where('id', 'intentos_cierre')
                        ->where('count', 1)
                        ->etc()
                )
                ->etc()
        );
    }

    public function test_student_sees_observed_card_when_notified(): void
    {
        $student = User::factory()->create();
        $student->assignRole('alumno');

        $tutor = User::factory()->create();
        $actor = User::factory()->create();

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

        $student->notify(new SesionObservedNotification($sesion, $actor, 'Revisar bitácora.'));

        $response = $this->actingAs($student)->getJson(route('dashboard.pending'));

        $response->assertOk();
        $response->assertJsonCount(1, 'cards');
        $response->assertJsonPath('cards.0.id', 'observados');
        $response->assertJsonPath('cards.0.count', 1);
    }
}

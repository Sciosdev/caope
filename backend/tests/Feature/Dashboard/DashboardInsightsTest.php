<?php

namespace Tests\Feature\Dashboard;

use App\Models\Expediente;
use App\Models\Sesion;
use App\Models\TimelineEvento;
use App\Models\User;
use Carbon\CarbonInterval;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DashboardInsightsTest extends TestCase
{
    use RefreshDatabase;

    public function test_metrics_endpoint_returns_counts_and_average_validation_time(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-01-15 12:00:00'));

        $user = User::factory()->create();
        $this->actingAs($user);

        $abiertos = Expediente::factory()->count(2)->create(['estado' => 'abierto']);
        $revision = Expediente::factory()->create(['estado' => 'revision']);
        Expediente::factory()->count(3)->create(['estado' => 'cerrado']);

        $validador = User::factory()->create();

        Sesion::factory()->create([
            'expediente_id' => $abiertos->first()->id,
            'status_revision' => 'validada',
            'validada_por' => $validador->id,
            'created_at' => Carbon::now()->subDays(5),
            'updated_at' => Carbon::now()->subDays(3),
        ]);

        Sesion::factory()->create([
            'expediente_id' => $revision->id,
            'status_revision' => 'validada',
            'validada_por' => $validador->id,
            'created_at' => Carbon::now()->subDays(4),
            'updated_at' => Carbon::now()->subDay(),
        ]);

        $response = $this->getJson(route('dashboard.metrics'));

        $response->assertOk();
        $response->assertJson([
            'expedientes' => [
                'total' => 6,
                'por_estado' => [
                    'abierto' => 2,
                    'revision' => 1,
                    'cerrado' => 3,
                ],
            ],
        ]);

        $expectedDurations = [
            Carbon::now()->subDays(5)->diffInSeconds(Carbon::now()->subDays(3)),
            Carbon::now()->subDays(4)->diffInSeconds(Carbon::now()->subDay()),
        ];

        $expectedAverage = (int) round(array_sum($expectedDurations) / count($expectedDurations));

        $this->assertSame($expectedAverage, $response->json('sesiones.tiempo_promedio_validacion.seconds'));
        $this->assertSame(
            CarbonInterval::seconds($expectedAverage)->cascade()->forHumans(['parts' => 2, 'short' => true, 'join' => true]),
            $response->json('sesiones.tiempo_promedio_validacion.human')
        );
        $this->assertSame(2, $response->json('sesiones.tiempo_promedio_validacion.count'));

        Carbon::setTestNow();
    }

    public function test_alerts_endpoint_returns_stalled_expedientes(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-02-01 09:00:00'));

        $user = User::factory()->create();
        $this->actingAs($user);

        $activo = Expediente::factory()->create([
            'estado' => 'abierto',
            'updated_at' => Carbon::now()->subDays(3),
        ]);

        TimelineEvento::create([
            'expediente_id' => $activo->id,
            'actor_id' => $user->id,
            'evento' => 'expediente.actualizado',
            'payload' => [],
            'created_at' => Carbon::now()->subDays(2),
        ]);

        $tutor = User::factory()->create();
        $coordinador = User::factory()->create();

        $estancado = Expediente::factory()->create([
            'estado' => 'revision',
            'tutor_id' => $tutor->id,
            'coordinador_id' => $coordinador->id,
            'created_at' => Carbon::now()->subDays(60),
            'updated_at' => Carbon::now()->subDays(25),
        ]);

        TimelineEvento::create([
            'expediente_id' => $estancado->id,
            'actor_id' => $user->id,
            'evento' => 'expediente.estado_cambiado',
            'payload' => [],
            'created_at' => Carbon::now()->subDays(30),
        ]);

        Sesion::factory()->create([
            'expediente_id' => $estancado->id,
            'status_revision' => 'pendiente',
            'created_at' => Carbon::now()->subDays(40),
            'updated_at' => Carbon::now()->subDays(40),
        ]);

        Expediente::factory()->create([
            'estado' => 'cerrado',
            'updated_at' => Carbon::now()->subDays(90),
        ]);

        $response = $this->getJson(route('dashboard.alerts'));

        $response->assertOk();
        $response->assertJson([
            'threshold_days' => config('dashboard.stalled_days'),
        ]);

        $alerts = $response->json('alerts');
        $this->assertCount(1, $alerts);

        $alert = $alerts[0];
        $this->assertSame($estancado->id, $alert['id']);
        $this->assertSame($estancado->no_control, $alert['no_control']);
        $this->assertSame('revision', $alert['estado']);
        $this->assertSame($tutor->name, $alert['tutor']);
        $this->assertSame($coordinador->name, $alert['coordinador']);
        $this->assertSame(Carbon::now()->subDays(25)->toIso8601String(), $alert['ultima_actividad']);
        $this->assertSame(25, $alert['dias_inactivo']);
        $this->assertNotEmpty($alert['url']);

        Carbon::setTestNow();
    }
}


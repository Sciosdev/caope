<?php

namespace Tests\Feature\Expedientes;

use App\Exports\TimelineEventosExport;
use App\Models\Expediente;
use App\Models\Sesion;
use App\Models\TimelineEvento;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TimelineExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RoleSeeder::class);
    }

    public function test_small_timeline_export_generates_file_immediately(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $expediente = Expediente::factory()->create();

        TimelineEvento::factory()->count(3)->create([
            'expediente_id' => $expediente->id,
            'actor_id' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->postJson(route('expedientes.timeline.export', $expediente), [
            'format' => 'xlsx',
        ]);

        $response->assertOk();
        $response->assertJsonPath('status', 'ready');
        $response->assertJsonStructure(['token', 'download_url']);

        $token = $response->json('token');
        $this->assertNotNull($token);

        $path = "exports/timeline_{$token}.xlsx";
        Storage::disk('local')->assertExists($path);

        $cacheData = Cache::get($token);
        $this->assertNotNull($cacheData);
        $this->assertSame('ready', $cacheData['status']);
        $this->assertSame($admin->id, $cacheData['user_id']);
        $this->assertSame($expediente->id, $cacheData['expediente_id']);

        Storage::disk('local')->delete($path);
    }

    public function test_large_timeline_export_is_queued(): void
    {
        Excel::fake();

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $expediente = Expediente::factory()->create();

        TimelineEvento::factory()->count(220)->create([
            'expediente_id' => $expediente->id,
            'actor_id' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->postJson(route('expedientes.timeline.export', $expediente), [
            'format' => 'xlsx',
        ]);

        $response->assertOk();
        $response->assertJsonPath('status', 'pending');
        $response->assertJsonStructure(['token', 'status_url']);

        $token = $response->json('token');
        $this->assertNotNull($token);

        Excel::assertQueued("exports/timeline_{$token}.xlsx", 'local');

        $cacheData = Cache::get($token);
        $this->assertNotNull($cacheData);
        $this->assertSame('pending', $cacheData['status']);
        $this->assertSame($expediente->id, $cacheData['expediente_id']);
    }

    public function test_timeline_export_status_returns_ready_payload(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $expediente = Expediente::factory()->create();

        $token = Str::uuid()->toString();

        Cache::put($token, [
            'status' => 'ready',
            'path' => "exports/timeline_{$token}.xlsx",
            'filename' => 'timeline.xlsx',
            'user_id' => $admin->id,
            'expediente_id' => $expediente->id,
            'event_ids' => [],
        ], now()->addMinutes(10));

        $response = $this->actingAs($admin)->getJson(route('expedientes.timeline.export.status', [$expediente, $token]));

        $response->assertOk();
        $response->assertJsonPath('status', 'ready');
        $response->assertJsonStructure(['download_url']);
    }

    public function test_timeline_export_download_returns_file(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $expediente = Expediente::factory()->create();

        Storage::fake('local');

        $token = Str::uuid()->toString();
        $path = "exports/timeline_{$token}.xlsx";

        Storage::disk('local')->put($path, 'dummy');

        Cache::put($token, [
            'status' => 'ready',
            'path' => $path,
            'filename' => 'timeline.xlsx',
            'user_id' => $admin->id,
            'expediente_id' => $expediente->id,
            'event_ids' => [],
        ], now()->addMinutes(10));

        $response = $this->actingAs($admin)->get(route('expedientes.timeline.export.download', [$expediente, $token]));

        $response->assertOk();
        $response->assertDownload('timeline.xlsx');
        $this->assertNull(Cache::get($token));
    }

    public function test_facilitator_timeline_and_export_hide_events_from_foreign_sessions(): void
    {
        $facilitador = User::factory()->create();
        $facilitador->assignRole('alumno');

        $otherUser = User::factory()->create();

        $expediente = Expediente::factory()->create([
            'creado_por' => $facilitador->id,
            'tutor_id' => $otherUser->id,
            'coordinador_id' => $otherUser->id,
        ]);
        $ownSession = Sesion::factory()->create([
            'expediente_id' => $expediente->id,
            'realizada_por' => $facilitador->id,
            'status_revision' => 'pendiente',
            'validada_por' => null,
        ]);
        $foreignSession = Sesion::factory()->create([
            'expediente_id' => $expediente->id,
            'realizada_por' => $otherUser->id,
            'status_revision' => 'pendiente',
            'validada_por' => null,
        ]);

        $generalEvent = TimelineEvento::factory()->create([
            'expediente_id' => $expediente->id,
            'actor_id' => $facilitador->id,
            'evento' => 'expediente.actualizado',
            'payload' => ['detalle' => 'Evento general visible'],
        ]);
        $ownSessionEvent = TimelineEvento::factory()->create([
            'expediente_id' => $expediente->id,
            'actor_id' => $facilitador->id,
            'evento' => 'sesion.actualizada',
            'payload' => [
                'sesion_id' => $ownSession->id,
                'detalle' => 'Evento de sesión propia',
            ],
        ]);
        $foreignSessionEvent = TimelineEvento::factory()->create([
            'expediente_id' => $expediente->id,
            'actor_id' => $otherUser->id,
            'evento' => 'sesion.actualizada',
            'payload' => [
                'sesion_id' => $foreignSession->id,
                'detalle' => 'Evento de sesión ajena',
            ],
        ]);
        $ownLegacyCommentEvent = TimelineEvento::factory()->create([
            'expediente_id' => $expediente->id,
            'actor_id' => $facilitador->id,
            'evento' => 'comentario.creado',
            'payload' => [
                'comentable_type' => Sesion::class,
                'comentable_id' => $ownSession->id,
                'detalle' => 'Comentario de sesión propia',
            ],
        ]);
        $foreignLegacyCommentEvent = TimelineEvento::factory()->create([
            'expediente_id' => $expediente->id,
            'actor_id' => $otherUser->id,
            'evento' => 'comentario.creado',
            'payload' => [
                'comentable_type' => Sesion::class,
                'comentable_id' => $foreignSession->id,
                'detalle' => 'Comentario de sesión ajena',
            ],
        ]);

        $expectedIds = [
            $generalEvent->id,
            $ownSessionEvent->id,
            $ownLegacyCommentEvent->id,
        ];
        sort($expectedIds);

        $response = $this->actingAs($facilitador)
            ->get(route('expedientes.show', $expediente));

        $response->assertOk();
        $response->assertViewHas('timelineEventos', function ($events) use ($expectedIds): bool {
            return $events->pluck('id')->sort()->values()->all() === $expectedIds;
        });

        $exportedIds = (new TimelineEventosExport($facilitador->id, $expediente->id))
            ->query()
            ->pluck('id')
            ->sort()
            ->values()
            ->all();

        $this->assertSame($expectedIds, $exportedIds);
        $this->assertNotContains($foreignSessionEvent->id, $exportedIds);
        $this->assertNotContains($foreignLegacyCommentEvent->id, $exportedIds);
    }

    public function test_global_timeline_scope_excludes_general_events_from_foreign_expedientes(): void
    {
        $facilitador = User::factory()->create();
        $facilitador->assignRole('alumno');

        $ownExpediente = Expediente::factory()->create([
            'creado_por' => $facilitador->id,
        ]);
        $foreignExpediente = Expediente::factory()->create([
            'creado_por' => User::factory()->create()->id,
        ]);

        $ownEvent = TimelineEvento::factory()->create([
            'expediente_id' => $ownExpediente->id,
            'evento' => 'expediente.actualizado',
            'payload' => ['detalle' => 'Evento general propio'],
        ]);
        $foreignEvent = TimelineEvento::factory()->create([
            'expediente_id' => $foreignExpediente->id,
            'evento' => 'expediente.actualizado',
            'payload' => ['detalle' => 'Evento general ajeno'],
        ]);

        $visibleIds = TimelineEvento::query()
            ->visibleTo($facilitador)
            ->pluck('id')
            ->all();

        $this->assertContains($ownEvent->id, $visibleIds);
        $this->assertNotContains($foreignEvent->id, $visibleIds);
    }

    public function test_timeline_status_and_download_are_revoked_when_session_author_changes(): void
    {
        Storage::fake('local');

        $facilitador = User::factory()->create();
        $facilitador->assignRole('alumno');

        $otherUser = User::factory()->create();
        $expediente = Expediente::factory()->create([
            'creado_por' => $facilitador->id,
        ]);
        $session = Sesion::factory()->create([
            'expediente_id' => $expediente->id,
            'realizada_por' => $facilitador->id,
            'status_revision' => 'pendiente',
            'validada_por' => null,
        ]);
        $event = TimelineEvento::factory()->create([
            'expediente_id' => $expediente->id,
            'evento' => 'sesion.actualizada',
            'payload' => [
                'sesion_id' => $session->id,
                'detalle' => 'Evento que deja de estar autorizado',
            ],
        ]);

        $statusToken = Str::uuid()->toString();
        $downloadToken = Str::uuid()->toString();

        foreach ([$statusToken, $downloadToken] as $token) {
            $path = "exports/timeline_{$token}.xlsx";
            Storage::disk('local')->put($path, 'timeline restringido');

            Cache::put($token, [
                'status' => 'ready',
                'path' => $path,
                'filename' => 'timeline.xlsx',
                'user_id' => $facilitador->id,
                'expediente_id' => $expediente->id,
                'event_ids' => [$event->id],
            ], now()->addMinutes(10));
        }

        $session->update(['realizada_por' => $otherUser->id]);

        $this->actingAs($facilitador)
            ->getJson(route('expedientes.timeline.export.status', [$expediente, $statusToken]))
            ->assertNotFound();
        $this->actingAs($facilitador)
            ->get(route('expedientes.timeline.export.download', [$expediente, $downloadToken]))
            ->assertNotFound();

        foreach ([$statusToken, $downloadToken] as $token) {
            $this->assertNull(Cache::get($token));
            Storage::disk('local')->assertMissing("exports/timeline_{$token}.xlsx");
        }

        $this->assertSame($facilitador->id, $expediente->fresh()->creado_por);
    }
}

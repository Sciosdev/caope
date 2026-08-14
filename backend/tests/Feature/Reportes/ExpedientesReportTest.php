<?php

namespace Tests\Feature\Reportes;

use App\Exports\ExpedientesExport;
use App\Models\Expediente;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ExpedientesReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RoleSeeder::class);
    }

    public function test_admin_can_view_report_with_filters(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $tutor = User::factory()->create();
        $tutor->assignRole('docente');

        $coordinador = User::factory()->create();
        $coordinador->assignRole('coordinador');

        $creador = User::factory()->create();

        $inRange = Expediente::factory()->create([
            'estado' => 'cerrado',
            'apertura' => Carbon::parse('2024-04-15'),
            'tutor_id' => $tutor->id,
            'coordinador_id' => $coordinador->id,
            'creado_por' => $creador->id,
        ]);

        $outOfRange = Expediente::factory()->create([
            'estado' => 'abierto',
            'apertura' => Carbon::parse('2023-01-10'),
            'tutor_id' => null,
            'coordinador_id' => null,
            'creado_por' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get(route('reportes.index', [
            'estado' => 'cerrado',
            'desde' => '2024-01-01',
            'hasta' => '2024-12-31',
            'tutor_id' => $tutor->id,
            'coordinador_id' => $coordinador->id,
            'creado_por' => $creador->id,
        ]));

        $response->assertOk();
        $response->assertSee($inRange->no_control);
        $response->assertDontSee($outOfRange->no_control);
    }

    public function test_small_export_generates_file_immediately(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Expediente::factory()->create(['creado_por' => $admin->id]);

        $response = $this->actingAs($admin)->postJson(route('reportes.expedientes.export'), [
            'format' => 'xlsx',
        ]);

        $response->assertOk();
        $response->assertJsonPath('status', 'ready');
        $response->assertJsonStructure(['token', 'download_url']);

        $token = $response->json('token');
        $this->assertNotNull($token);
        $this->assertSame(route('reportes.expedientes.download', $token), $response->json('download_url'));

        $path = "exports/expedientes_{$token}.xlsx";
        Storage::disk('local')->assertExists($path);

        $cacheData = Cache::get($token);
        $this->assertNotNull($cacheData);
        $this->assertSame('ready', $cacheData['status']);
        $this->assertSame($admin->id, $cacheData['user_id']);

        Storage::disk('local')->delete($path);
    }

    public function test_large_export_generates_file_immediately(): void
    {
        Excel::fake();

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Expediente::factory()->count(210)->create([
            'estado' => 'revision',
            'creado_por' => $admin->id,
            'tutor_id' => null,
            'coordinador_id' => null,
        ]);

        $response = $this->actingAs($admin)->postJson(route('reportes.expedientes.export'), [
            'format' => 'xlsx',
        ]);

        $response->assertOk();
        $response->assertJsonPath('status', 'ready');
        $response->assertJsonStructure(['token', 'download_url']);

        $token = $response->json('token');
        $this->assertSame(route('reportes.expedientes.download', $token), $response->json('download_url'));

        Excel::assertStored("exports/expedientes_{$token}.xlsx", 'local');

        $cacheData = Cache::get($token);
        $this->assertNotNull($cacheData);
        $this->assertSame('ready', $cacheData['status']);
    }

    public function test_download_ready_export_returns_file(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Storage::fake('local');

        $token = Str::uuid()->toString();
        $path = "exports/expedientes_{$token}.xlsx";

        Storage::disk('local')->put($path, 'dummy');

        Cache::put($token, [
            'status' => 'ready',
            'path' => $path,
            'filename' => 'reporte.xlsx',
            'user_id' => $admin->id,
            'expediente_ids' => [],
        ], now()->addMinutes(10));

        $response = $this->actingAs($admin)->get(route('reportes.expedientes.download', $token));

        $response->assertOk();
        $response->assertDownload('reporte.xlsx');
        $this->assertNull(Cache::get($token));
    }

    public function test_status_endpoint_returns_ready_payload(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $token = Str::uuid()->toString();

        Cache::put($token, [
            'status' => 'ready',
            'path' => "exports/expedientes_{$token}.xlsx",
            'filename' => 'reporte.xlsx',
            'user_id' => $admin->id,
            'expediente_ids' => [],
        ], now()->addMinutes(10));

        $response = $this->actingAs($admin)->getJson(route('reportes.expedientes.export.status', $token));

        $response->assertOk();
        $response->assertJsonPath('status', 'ready');
        $response->assertJsonStructure(['download_url']);
        $response->assertJsonPath('download_url', route('reportes.expedientes.download', $token));
    }

    public function test_status_and_download_tokens_are_revoked_after_expediente_reassignment(): void
    {
        Storage::fake('local');

        $facilitador = User::factory()->create();
        $facilitador->assignRole('alumno');

        $newFacilitador = User::factory()->create();
        $newFacilitador->assignRole('alumno');

        $expediente = Expediente::factory()->create([
            'creado_por' => $facilitador->id,
            'tutor_id' => null,
            'coordinador_id' => null,
        ]);

        $statusToken = Str::uuid()->toString();
        $downloadToken = Str::uuid()->toString();

        foreach ([$statusToken, $downloadToken] as $token) {
            $path = "exports/expedientes_{$token}.xlsx";
            Storage::disk('local')->put($path, 'contenido restringido');

            Cache::put($token, [
                'status' => 'ready',
                'path' => $path,
                'filename' => 'reporte.xlsx',
                'user_id' => $facilitador->id,
                'expediente_ids' => [$expediente->id],
            ], now()->addMinutes(10));
        }

        $expediente->update(['creado_por' => $newFacilitador->id]);

        $this->actingAs($facilitador)
            ->getJson(route('reportes.expedientes.export.status', $statusToken))
            ->assertNotFound();
        $this->actingAs($facilitador)
            ->get(route('reportes.expedientes.download', $downloadToken))
            ->assertNotFound();

        foreach ([$statusToken, $downloadToken] as $token) {
            $this->assertNull(Cache::get($token));
            Storage::disk('local')->assertMissing("exports/expedientes_{$token}.xlsx");
        }
    }

    public function test_report_and_export_are_limited_to_assigned_expedientes(): void
    {
        $facilitador = User::factory()->create(['name' => 'Facilitador Asignado']);
        $facilitador->assignRole('alumno');

        $docente = User::factory()->create(['name' => 'Docente Asignado']);
        $docente->assignRole('docente');

        $coordinador = User::factory()->create(['name' => 'Coordinador Asignado']);
        $coordinador->assignRole('coordinador');

        $ajeno = User::factory()->create(['name' => 'Usuario Fuera de Alcance']);

        $delFacilitador = Expediente::factory()->create([
            'no_control' => 'CA-2026-F001',
            'creado_por' => $facilitador->id,
            'tutor_id' => $ajeno->id,
            'coordinador_id' => $ajeno->id,
        ]);
        $delDocente = Expediente::factory()->create([
            'no_control' => 'CA-2026-D001',
            'creado_por' => $ajeno->id,
            'tutor_id' => $docente->id,
            'coordinador_id' => $ajeno->id,
        ]);
        $delCoordinador = Expediente::factory()->create([
            'no_control' => 'CA-2026-C001',
            'creado_por' => $ajeno->id,
            'tutor_id' => $ajeno->id,
            'coordinador_id' => $coordinador->id,
        ]);
        $fueraDeAlcance = Expediente::factory()->create([
            'no_control' => 'CA-2026-X001',
            'creado_por' => $ajeno->id,
            'tutor_id' => $ajeno->id,
            'coordinador_id' => $ajeno->id,
        ]);

        foreach ([
            [$facilitador, $delFacilitador],
            [$docente, $delDocente],
            [$coordinador, $delCoordinador],
        ] as [$user, $visible]) {
            $response = $this->actingAs($user)->get(route('reportes.index'));

            $response->assertOk();
            $response->assertSee($visible->no_control);
            $response->assertDontSee($fueraDeAlcance->no_control);

            $exportedIds = (new ExpedientesExport([], $user->id))
                ->query()
                ->pluck('id')
                ->all();

            $this->assertSame([$visible->id], $exportedIds);
        }
    }

    public function test_report_download_urls_keep_the_application_base_path(): void
    {
        URL::forceRootUrl('https://example.test/caope');
        URL::forceScheme('https');

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)->view('reportes.expedientes.index', [
            'expedientes' => Expediente::query()->paginate(),
            'filters' => [
                'estado' => null,
                'desde' => null,
                'hasta' => null,
                'tutor_id' => null,
                'coordinador_id' => null,
                'creado_por' => null,
            ],
            'tutores' => collect(),
            'coordinadores' => collect(),
            'creadores' => collect(),
        ]);

        $response->assertSee(
            'https:\/\/example.test\/caope\/reportes\/expedientes\/download',
            false
        );
    }
}

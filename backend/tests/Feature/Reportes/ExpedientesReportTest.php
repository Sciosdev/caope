<?php

namespace Tests\Feature\Reportes;

use App\Models\Expediente;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
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
        Excel::fake();

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

        Excel::assertStored("exports/expedientes_{$token}.xlsx", 'local');

        $cacheData = Cache::get($token);
        $this->assertNotNull($cacheData);
        $this->assertSame('ready', $cacheData['status']);
        $this->assertSame($admin->id, $cacheData['user_id']);
    }

    public function test_large_export_is_queued(): void
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
        $response->assertJsonPath('status', 'pending');
        $response->assertJsonStructure(['token', 'status_url']);

        $token = $response->json('token');

        Excel::assertQueued("exports/expedientes_{$token}.xlsx", 'local');

        $cacheData = Cache::get($token);
        $this->assertNotNull($cacheData);
        $this->assertSame('pending', $cacheData['status']);

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
        ], now()->addMinutes(10));

        $response = $this->actingAs($admin)->getJson(route('reportes.expedientes.export.status', $token));

        $response->assertOk();
        $response->assertJsonPath('status', 'ready');
        $response->assertJsonStructure(['download_url']);
    }
}

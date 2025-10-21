<?php

namespace Tests\Feature\Expedientes;

use App\Models\Expediente;
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
        Excel::fake();

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

        Excel::assertStored("exports/timeline_{$token}.xlsx", 'local');

        $cacheData = Cache::get($token);
        $this->assertNotNull($cacheData);
        $this->assertSame('ready', $cacheData['status']);
        $this->assertSame($admin->id, $cacheData['user_id']);
        $this->assertSame($expediente->id, $cacheData['expediente_id']);
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
        ], now()->addMinutes(10));

        $response = $this->actingAs($admin)->get(route('expedientes.timeline.export.download', [$expediente, $token]));

        $response->assertOk();
        $response->assertDownload('timeline.xlsx');
        $this->assertNull(Cache::get($token));
    }
}

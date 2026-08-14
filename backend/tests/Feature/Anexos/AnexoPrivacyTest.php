<?php

namespace Tests\Feature\Anexos;

use App\Models\Anexo;
use App\Models\Expediente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AnexoPrivacyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'alumno']);
        config(['filesystems.private_default' => 'private']);
        Storage::fake('private');
        Storage::fake('public');
    }

    public function test_forged_public_upload_is_always_stored_privately(): void
    {
        $facilitador = User::factory()->create();
        $facilitador->assignRole('alumno');
        $expediente = Expediente::factory()->create([
            'creado_por' => $facilitador->id,
            'estado' => 'abierto',
        ]);

        $response = $this->actingAs($facilitador)
            ->postJson(route('expedientes.anexos.store', $expediente), [
                'archivo' => UploadedFile::fake()->create('historia.pdf', 12, 'application/pdf'),
                'es_privado' => false,
            ]);

        $response->assertCreated();
        $anexo = $expediente->anexos()->sole();

        $this->assertSame('private', $anexo->disk);
        $this->assertTrue($anexo->es_privado);
        $this->assertArrayNotHasKey('ruta', $anexo->toArray());
        $this->assertArrayNotHasKey('disk', $anexo->toArray());
        $response->assertJsonMissingPath('ruta')->assertJsonMissingPath('disk');
        Storage::disk('private')->assertExists($anexo->ruta);
        Storage::disk('public')->assertMissing($anexo->ruta);
    }

    public function test_legacy_active_content_is_forced_to_download_with_sandbox_headers(): void
    {
        $facilitador = User::factory()->create();
        $facilitador->assignRole('alumno');
        $expediente = Expediente::factory()->create([
            'creado_por' => $facilitador->id,
            'estado' => 'abierto',
        ]);
        $path = 'expedientes/'.$expediente->id.'/anexos/legado.html';
        Storage::disk('private')->put($path, '<script>alert(document.cookie)</script>');
        $anexo = Anexo::query()->create([
            'expediente_id' => $expediente->id,
            'tipo' => 'text/html',
            'titulo' => 'Archivo legado',
            'ruta' => $path,
            'disk' => 'private',
            'es_privado' => true,
            'tamano' => 39,
            'subido_por' => $facilitador->id,
        ]);
        $url = URL::temporarySignedRoute(
            'expedientes.anexos.preview',
            now()->addMinute(),
            [$expediente, $anexo],
        );

        $this->actingAs($facilitador)
            ->get($url)
            ->assertOk()
            ->assertHeader('Content-Security-Policy', "sandbox; default-src 'none'")
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Disposition', 'attachment; filename="Archivo legado.html"');
    }

    public function test_migration_replaces_partial_private_copy_and_removes_public_original(): void
    {
        $uploader = User::factory()->create();
        $expediente = Expediente::factory()->create();
        $path = 'expedientes/'.$expediente->id.'/anexos/legado.pdf';
        Storage::disk('public')->put($path, 'contenido clínico completo');
        Storage::disk('private')->put($path, 'parcial');

        $anexo = Anexo::query()->create([
            'expediente_id' => $expediente->id,
            'tipo' => 'pdf',
            'titulo' => 'Legado',
            'ruta' => $path,
            'disk' => 'public',
            'es_privado' => false,
            'tamano' => 27,
            'subido_por' => $uploader->id,
        ]);

        $migration = require database_path('migrations/2026_08_13_000002_make_anexos_private.php');
        $migration->up();

        $anexo->refresh();
        $this->assertSame('private', $anexo->disk);
        $this->assertTrue($anexo->es_privado);
        $this->assertSame('contenido clínico completo', Storage::disk('private')->get($path));
        Storage::disk('public')->assertMissing($path);
    }

    public function test_migration_preserves_file_when_legacy_disk_aliases_private_root(): void
    {
        $uploader = User::factory()->create();
        $expediente = Expediente::factory()->create();
        $path = 'expedientes/'.$expediente->id.'/anexos/alias.pdf';

        config(['filesystems.disks.legacy_private' => config('filesystems.disks.private')]);
        Storage::disk('private')->put($path, 'contenido privado');

        $anexo = Anexo::query()->create([
            'expediente_id' => $expediente->id,
            'tipo' => 'pdf',
            'titulo' => 'Alias legado',
            'ruta' => $path,
            'disk' => 'legacy_private',
            'es_privado' => false,
            'tamano' => 17,
            'subido_por' => $uploader->id,
        ]);

        $migration = require database_path('migrations/2026_08_13_000002_make_anexos_private.php');
        $migration->up();

        $anexo->refresh();
        $this->assertSame('private', $anexo->disk);
        $this->assertTrue($anexo->es_privado);
        Storage::disk('private')->assertExists($path);
        $this->assertSame('contenido privado', Storage::disk('private')->get($path));
    }
}

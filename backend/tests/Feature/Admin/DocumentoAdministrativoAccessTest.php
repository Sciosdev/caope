<?php

namespace Tests\Feature\Admin;

use App\Models\DocumentoAdministrativo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DocumentoAdministrativoAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admin', 'coordinador', 'estratega', 'alumno', 'paps'] as $role) {
            Role::query()->firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Storage::fake('private');
    }

    public function test_facilitador_cannot_list_or_download_administrative_documents(): void
    {
        $facilitador = User::factory()->create();
        $facilitador->syncRoles(['alumno']);

        $documento = $this->approvedDocument();

        $this->actingAs($facilitador)
            ->get(route('admin.documentos.index'))
            ->assertForbidden();

        $this->get(route('admin.documentos.download', $documento))
            ->assertForbidden();
    }

    public function test_authorized_profile_can_list_and_download_an_approved_document(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->syncRoles(['coordinador']);

        $documento = $this->approvedDocument();

        $this->actingAs($coordinador)
            ->get(route('admin.documentos.index'))
            ->assertOk()
            ->assertSee('Manual administrativo');

        $this->get(route('admin.documentos.download', $documento))
            ->assertOk()
            ->assertDownload('Manual administrativo.pdf');
    }

    public function test_paps_requires_approval_to_access_administrative_documents(): void
    {
        $documento = $this->approvedDocument();
        $paps = User::factory()->create(['approved_at' => null]);
        $paps->syncRoles(['paps']);

        Storage::disk('private')->assertExists($documento->ruta);

        $this->actingAs($paps)
            ->get(route('admin.documentos.index'))
            ->assertForbidden();

        $this->get(route('admin.documentos.download', $documento))
            ->assertForbidden();

        Storage::disk('private')->assertExists($documento->ruta);

        $paps->forceFill(['approved_at' => now()])->save();

        $this->get(route('admin.documentos.index'))->assertOk();
        $this->get(route('admin.documentos.download', $documento))->assertOk();
    }

    public function test_unapproved_paps_with_operational_role_cannot_upload_documents(): void
    {
        $actor = User::factory()->create(['approved_at' => null]);
        $actor->syncRoles(['paps', 'coordinador']);

        $this->actingAs($actor)
            ->get(route('admin.documentos.index'))
            ->assertOk();

        $this->post(route('admin.documentos.store'), [
            'titulo' => 'Carga no autorizada',
            'archivo' => UploadedFile::fake()->create('privado.pdf', 10, 'application/pdf'),
        ])->assertForbidden();

        $this->assertDatabaseMissing('documentos_administrativos', [
            'titulo' => 'Carga no autorizada',
        ]);
    }

    private function approvedDocument(): DocumentoAdministrativo
    {
        Storage::disk('private')->put('documentos-administrativos/manual.pdf', 'contenido de prueba');

        return DocumentoAdministrativo::query()->create([
            'titulo' => 'Manual administrativo',
            'ruta' => 'documentos-administrativos/manual.pdf',
            'disk' => 'private',
            'mime_type' => 'application/pdf',
            'tamano' => 19,
            'aprobado_en' => now(),
        ]);
    }
}

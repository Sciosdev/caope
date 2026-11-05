<?php

namespace Tests\Feature\Expedientes;

use App\Models\Anexo;
use App\Models\Comentario;
use App\Models\Consentimiento;
use App\Models\Expediente;
use App\Models\Sesion;
use App\Models\SesionAdjunto;
use App\Models\User;
use App\Services\ExpedienteLifecycleService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ArchivedExpedientePrivacyTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $approvedPaps;

    private User $unapprovedPaps;

    private User $facilitator;

    private Expediente $expediente;

    private Consentimiento $consentimiento;

    private Anexo $anexo;

    private Sesion $sesion;

    private SesionAdjunto $adjunto;

    private Comentario $comentario;

    private string $consentimientoPath;

    private string $observacionesPath;

    private string $anexoPath;

    private string $adjuntoPath;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cache.default' => 'array',
            'filesystems.private_default' => 'private',
        ]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RoleSeeder::class);
        Storage::fake('private');
        Storage::fake('public');

        $this->admin = $this->user('admin');
        $this->approvedPaps = $this->user('paps', approved: true);
        $this->unapprovedPaps = $this->user('paps', approved: false);
        $this->facilitator = $this->user('alumno');

        $this->expediente = Expediente::factory()->create([
            'paciente' => 'Paciente sintético de privacidad',
            'estado' => 'abierto',
            'creado_por' => $this->admin->id,
            'facilitador_id' => $this->facilitator->id,
            'tutor_id' => null,
            'coordinador_id' => null,
        ]);

        $this->consentimientoPath = "expedientes/{$this->expediente->id}/consentimientos/original.pdf";
        $this->observacionesPath = "expedientes/{$this->expediente->id}/consentimientos/observaciones/original.pdf";
        $this->anexoPath = "expedientes/{$this->expediente->id}/anexos/original.pdf";

        $this->expediente->forceFill([
            'consentimientos_observaciones' => 'Observación clínica sintética.',
            'consentimientos_observaciones_path' => $this->observacionesPath,
        ])->save();

        $this->consentimiento = Consentimiento::factory()->for($this->expediente)->create([
            'tratamiento' => 'Consentimiento sintético',
            'aceptado' => true,
            'fecha' => now()->toDateString(),
            'archivo_path' => $this->consentimientoPath,
            'subido_por' => $this->admin->id,
        ]);

        $this->anexo = Anexo::query()->create([
            'expediente_id' => $this->expediente->id,
            'tipo' => 'documento',
            'titulo' => 'Anexo sintético retenido',
            'ruta' => $this->anexoPath,
            'disk' => 'private',
            'es_privado' => true,
            'tamano' => strlen($this->pdf()),
            'subido_por' => $this->admin->id,
        ]);

        $this->sesion = Sesion::factory()->create([
            'expediente_id' => $this->expediente->id,
            'fecha' => now()->toDateString(),
            'tipo' => 'Seguimiento',
            'nota' => '<p>Nota sintética retenida.</p>',
            'realizada_por' => $this->facilitator->id,
            'status_revision' => 'pendiente',
            'validada_por' => null,
        ]);
        $this->adjuntoPath = 'sesiones/'.$this->sesion->id.'/'.Str::uuid().'.pdf';
        $this->adjunto = $this->sesion->adjuntos()->create([
            'nombre_original' => 'adjunto-original.pdf',
            'ruta' => $this->adjuntoPath,
            'disk' => 'private',
            'mime_type' => 'application/pdf',
            'tamano' => strlen($this->pdf()),
            'subido_por' => $this->admin->id,
        ]);
        $this->comentario = $this->sesion->comentarios()->create([
            'user_id' => $this->admin->id,
            'contenido' => 'Comentario sintético original.',
        ]);

        foreach ([
            $this->consentimientoPath,
            $this->observacionesPath,
            $this->anexoPath,
            $this->adjuntoPath,
        ] as $path) {
            Storage::disk('private')->put($path, $this->pdf());
        }
    }

    public function test_only_admin_and_approved_paps_can_read_an_archived_clinical_record_and_its_files(): void
    {
        $this->archive();

        foreach ([$this->admin, $this->approvedPaps] as $authorized) {
            $this->actingAs($authorized);

            $this->get(route('expedientes.show', $this->expediente))->assertOk();
            $this->get(route('consentimientos.archivo', $this->consentimiento))->assertOk();
            $this->get(route('expedientes.consentimientos.observaciones.show', $this->expediente))->assertOk();
            $this->get($this->signedAnexoUrl())->assertOk();
            $this->get(route('expedientes.sesiones.adjuntos.download', [
                $this->expediente,
                $this->sesion,
                $this->adjunto,
            ]))->assertOk();
        }

        foreach ([$this->facilitator, $this->unapprovedPaps] as $unauthorized) {
            $this->actingAs($unauthorized);

            $this->get(route('expedientes.show', $this->expediente))->assertForbidden();
            $this->get(route('consentimientos.archivo', $this->consentimiento))->assertForbidden();
            $this->get(route('expedientes.consentimientos.observaciones.show', $this->expediente))->assertForbidden();
            $this->get($this->signedAnexoUrl())->assertForbidden();
            $this->get(route('expedientes.sesiones.adjuntos.download', [
                $this->expediente,
                $this->sesion,
                $this->adjunto,
            ]))->assertForbidden();
        }
    }

    public function test_even_an_admin_cannot_write_to_an_archived_record_through_direct_child_routes(): void
    {
        $this->archive();
        $this->actingAs($this->admin);

        $this->post(route('consentimientos.upload', $this->consentimiento), [
            'archivo' => UploadedFile::fake()->create('reemplazo.pdf', 8, 'application/pdf'),
            'aceptado' => '1',
        ])->assertSessionHasErrors('archivo');

        $this->post(route('expedientes.consentimientos.observaciones', $this->expediente), [
            'observaciones_archivo_eliminar' => '1',
        ])->assertNotFound();

        $this->postJson(route('api.comentarios.store'), [
            'comentable_type' => 'sesion',
            'comentable_id' => $this->sesion->id,
            'contenido' => 'Intento de escritura sobre archivo.',
        ])->assertForbidden();

        $this->putJson(route('api.comentarios.update', $this->comentario), [
            'contenido' => 'Intento de edición sobre archivo.',
        ])->assertForbidden();

        $this->assertOriginalClinicalFilesAndMetadataRemain();
        $this->assertSame('Comentario sintético original.', $this->comentario->fresh()->contenido);
        $this->assertDatabaseCount('comentarios', 1);
    }

    public function test_restoring_does_not_remove_the_hold_or_allow_destructive_file_mutations(): void
    {
        $this->archive();
        $retentionUntil = $this->expediente->retention_until->copy();
        $this->expediente = app(ExpedienteLifecycleService::class)->restore(
            $this->expediente,
            $this->admin,
            'Restauración sintética para verificar la retención.',
        );
        $this->actingAs($this->admin);

        $this->assertFalse($this->expediente->trashed());
        $this->assertTrue($this->expediente->retention_until->equalTo($retentionUntil));
        $this->assertTrue($this->expediente->retention_until->isFuture());

        $this->post(route('consentimientos.upload', $this->consentimiento), [
            'archivo' => UploadedFile::fake()->create('reemplazo.pdf', 8, 'application/pdf'),
            'aceptado' => '1',
        ])->assertSessionHasErrors('archivo');

        $this->delete(route('expedientes.consentimientos.destroy', [
            $this->expediente,
            $this->consentimiento,
        ]))->assertForbidden();

        $this->post(route('expedientes.consentimientos.observaciones', $this->expediente), [
            'observaciones_archivo' => UploadedFile::fake()->create('observaciones-nuevas.pdf', 8, 'application/pdf'),
        ])->assertSessionHasErrors('observaciones_archivo');

        $this->post(route('expedientes.consentimientos.observaciones', $this->expediente), [
            'observaciones_archivo_eliminar' => '1',
        ])->assertSessionHasErrors('observaciones_archivo');

        $this->delete(route('expedientes.anexos.destroy', [
            $this->expediente,
            $this->anexo,
        ]))->assertForbidden();

        $this->put(route('expedientes.sesiones.update', [
            $this->expediente,
            $this->sesion,
        ]), [
            'adjuntos_eliminar' => [$this->adjunto->id],
        ])->assertSessionHasErrors('adjuntos_eliminar');

        $this->delete(route('expedientes.sesiones.destroy', [
            $this->expediente,
            $this->sesion,
        ]))->assertForbidden();

        $this->putJson(route('api.comentarios.update', $this->comentario), [
            'contenido' => 'Intento de edición durante la retención.',
        ])->assertForbidden();
        $this->deleteJson(route('api.comentarios.destroy', $this->comentario))->assertForbidden();

        $this->assertOriginalClinicalFilesAndMetadataRemain();
        $this->assertDatabaseHas('anexos', ['id' => $this->anexo->id, 'ruta' => $this->anexoPath]);
        $this->assertDatabaseHas('sesiones', ['id' => $this->sesion->id]);
        $this->assertDatabaseHas('sesion_adjuntos', ['id' => $this->adjunto->id, 'ruta' => $this->adjuntoPath]);
        $this->assertDatabaseHas('comentarios', [
            'id' => $this->comentario->id,
            'contenido' => 'Comentario sintético original.',
        ]);
    }

    public function test_archived_comments_remain_read_only_after_the_retention_date_has_passed(): void
    {
        $expedienteComment = $this->expediente->comentarios()->create([
            'user_id' => $this->admin->id,
            'contenido' => 'Comentario sintético directo del expediente.',
        ]);
        $this->archive();
        $this->expediente->forceFill(['retention_until' => now()->subDay()])->save();
        $this->expediente = $this->expediente->fresh();
        $this->actingAs($this->admin);

        $this->getJson(route('api.comentarios.show', $expedienteComment))->assertOk();
        $this->getJson(route('api.comentarios.show', $this->comentario))->assertOk();

        foreach ([$expedienteComment, $this->comentario] as $comentario) {
            $this->putJson(route('api.comentarios.update', $comentario), [
                'contenido' => 'Intento de edición con retención vencida.',
            ])->assertForbidden();
            $this->deleteJson(route('api.comentarios.destroy', $comentario))->assertForbidden();
        }

        $this->assertDatabaseHas('comentarios', [
            'id' => $expedienteComment->id,
            'contenido' => 'Comentario sintético directo del expediente.',
        ]);
        $this->assertDatabaseHas('comentarios', [
            'id' => $this->comentario->id,
            'contenido' => 'Comentario sintético original.',
        ]);
    }

    private function archive(): void
    {
        $this->expediente = app(ExpedienteLifecycleService::class)->archive(
            $this->expediente,
            $this->admin,
            'Archivo sintético para verificar privacidad.',
        );

        $this->assertTrue($this->expediente->trashed());
        $this->assertTrue($this->expediente->retention_until->isFuture());
    }

    private function user(string $role, bool $approved = true): User
    {
        $user = User::factory()->create([
            'name' => 'Cuenta sintética '.$role,
            'approved_at' => $approved ? now() : null,
            'is_active' => true,
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function signedAnexoUrl(): string
    {
        return URL::temporarySignedRoute(
            'expedientes.anexos.show',
            now()->addMinute(),
            [$this->expediente, $this->anexo],
        );
    }

    private function assertOriginalClinicalFilesAndMetadataRemain(): void
    {
        $this->assertSame($this->consentimientoPath, $this->consentimiento->fresh()->archivo_path);
        $this->assertSame(
            $this->observacionesPath,
            Expediente::withTrashed()->findOrFail($this->expediente->id)->consentimientos_observaciones_path,
        );

        foreach ([
            $this->consentimientoPath,
            $this->observacionesPath,
            $this->anexoPath,
            $this->adjuntoPath,
        ] as $path) {
            Storage::disk('private')->assertExists($path);
            $this->assertSame($this->pdf(), Storage::disk('private')->get($path));
        }
    }

    private function pdf(): string
    {
        return "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n";
    }
}

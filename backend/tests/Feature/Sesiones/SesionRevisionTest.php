<?php

namespace Tests\Feature\Sesiones;

use App\Models\Expediente;
use App\Models\Sesion;
use App\Models\SesionAdjunto;
use App\Models\TimelineEvento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SesionRevisionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::firstOrCreate(['name' => 'alumno']);
        Role::firstOrCreate(['name' => 'docente']);
        Permission::firstOrCreate(['name' => 'expedientes.manage']);
    }

    public function test_registrar_sesion_crea_evento_en_timeline(): void
    {
        $alumno = User::factory()->create();
        $alumno->assignRole('alumno');

        $expediente = Expediente::factory()->create([
            'creado_por' => $alumno->id,
            'estado' => 'abierto',
            'tutor_id' => null,
        ]);

        $response = $this->actingAs($alumno)->post(route('expedientes.sesiones.store', $expediente), [
            'fecha' => now()->toDateString(),
            'tipo' => 'Seguimiento',
            'referencia_externa' => null,
            'nota' => '<p>Notas de prueba</p>',
        ]);

        $response->assertRedirect();

        $sesion = $expediente->sesiones()->latest('id')->first();

        $this->assertNotNull($sesion);
        $this->assertDatabaseHas('timeline_eventos', [
            'expediente_id' => $expediente->id,
            'evento' => 'sesion.creada',
        ]);

        $evento = TimelineEvento::where('expediente_id', $expediente->id)
            ->where('evento', 'sesion.creada')
            ->latest('created_at')
            ->first();

        $this->assertSame(null, $evento->payload['estado_anterior']);
        $this->assertSame('pendiente', $evento->payload['estado_nuevo']);
        $this->assertSame($sesion->id, $evento->payload['sesion_id']);
    }

    public function test_session_controller_stores_uploaded_attachment_on_private_disk(): void
    {
        config(['filesystems.private_default' => 'private']);
        Storage::fake('private');
        Storage::fake('public');

        $facilitador = User::factory()->create();
        $facilitador->assignRole('alumno');

        $expediente = Expediente::factory()->create([
            'creado_por' => $facilitador->id,
            'estado' => 'abierto',
            'tutor_id' => null,
        ]);

        $response = $this->actingAs($facilitador)->post(
            route('expedientes.sesiones.store', $expediente),
            [
                'fecha' => now()->toDateString(),
                'tipo' => 'Seguimiento',
                'nota' => '<p>Sesión con evidencia privada</p>',
                'adjuntos' => [
                    UploadedFile::fake()->create('evidencia-clinica.pdf', 12, 'application/pdf'),
                ],
            ],
        );

        $response->assertSessionHasNoErrors()->assertRedirect();

        $sesion = $expediente->sesiones()->latest('id')->firstOrFail();
        $adjunto = $sesion->adjuntos()->sole();

        $this->assertSame('private', $adjunto->disk);
        $this->assertSame('evidencia-clinica.pdf', $adjunto->nombre_original);
        $this->assertSame($facilitador->id, $adjunto->subido_por);
        $this->assertStringNotContainsString('/storage', $adjunto->url);
        Storage::disk('private')->assertExists($adjunto->ruta);
        Storage::disk('public')->assertMissing($adjunto->ruta);

        $this->actingAs($facilitador)
            ->get($adjunto->url)
            ->assertOk()
            ->assertDownload('evidencia-clinica.pdf');
    }

    public function test_attachment_migration_moves_legacy_public_files_to_private_storage(): void
    {
        config(['filesystems.private_default' => 'private']);
        Storage::fake('private');
        Storage::fake('public');

        $sesion = Sesion::factory()->create();
        $path = 'sesiones/legacy/evidencia.pdf';
        Storage::disk('public')->put($path, 'evidencia sensible');
        $uploader = User::factory()->create();

        $adjunto = SesionAdjunto::query()->create([
            'sesion_id' => $sesion->id,
            'nombre_original' => 'evidencia.pdf',
            'ruta' => $path,
            'disk' => 'public',
            'mime_type' => 'application/pdf',
            'tamano' => 18,
            'subido_por' => $uploader->id,
        ]);

        $migration = require database_path('migrations/2026_08_13_000001_make_sesion_attachments_private.php');
        $migration->up();

        $this->assertSame('private', $adjunto->fresh()->disk);
        Storage::disk('private')->assertExists($path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_attachment_migration_replaces_an_incomplete_private_copy_before_deleting_public_file(): void
    {
        config(['filesystems.private_default' => 'private']);
        Storage::fake('private');
        Storage::fake('public');

        $sesion = Sesion::factory()->create();
        $uploader = User::factory()->create();
        $path = 'sesiones/legacy/reintento.pdf';
        Storage::disk('public')->put($path, 'contenido completo y correcto');
        Storage::disk('private')->put($path, 'parcial');

        $adjunto = SesionAdjunto::query()->create([
            'sesion_id' => $sesion->id,
            'nombre_original' => 'reintento.pdf',
            'ruta' => $path,
            'disk' => 'public',
            'mime_type' => 'application/pdf',
            'tamano' => 28,
            'subido_por' => $uploader->id,
        ]);

        $migration = require database_path('migrations/2026_08_13_000001_make_sesion_attachments_private.php');
        $migration->up();

        $this->assertSame('private', $adjunto->fresh()->disk);
        $this->assertSame('contenido completo y correcto', Storage::disk('private')->get($path));
        Storage::disk('public')->assertMissing($path);
    }

    public function test_docente_puede_observar_sesion_y_registra_evento(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole('docente');

        $alumno = User::factory()->create();
        $alumno->assignRole('alumno');

        $expediente = Expediente::factory()->create([
            'creado_por' => $alumno->id,
            'tutor_id' => $docente->id,
            'estado' => 'revision',
        ]);

        $sesion = Sesion::factory()->create([
            'expediente_id' => $expediente->id,
            'realizada_por' => $alumno->id,
            'status_revision' => 'pendiente',
        ]);

        $payload = [
            'observaciones' => 'Falta adjuntar plan de trabajo',
            'form_action' => 'observe',
        ];

        $response = $this->actingAs($docente)->post(route('expedientes.sesiones.observe', [$expediente, $sesion]), $payload);

        $response->assertRedirect(route('expedientes.sesiones.show', [$expediente, $sesion]));

        $sesion->refresh();

        $this->assertSame('observada', $sesion->status_revision);
        $this->assertNull($sesion->validada_por);

        $evento = TimelineEvento::where('expediente_id', $expediente->id)
            ->where('evento', 'sesion.observada')
            ->latest('created_at')
            ->first();

        $this->assertNotNull($evento);
        $this->assertSame('pendiente', $evento->payload['estado_anterior']);
        $this->assertSame('observada', $evento->payload['estado_nuevo']);
        $this->assertSame($payload['observaciones'], $evento->payload['observaciones']);
        $this->assertSame($sesion->id, $evento->payload['sesion_id']);
    }

    public function test_docente_puede_validar_sesion_y_registra_evento(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole('docente');

        $alumno = User::factory()->create();
        $alumno->assignRole('alumno');

        $expediente = Expediente::factory()->create([
            'creado_por' => $alumno->id,
            'tutor_id' => $docente->id,
            'estado' => 'revision',
        ]);

        $sesion = Sesion::factory()->create([
            'expediente_id' => $expediente->id,
            'realizada_por' => $alumno->id,
            'status_revision' => 'pendiente',
        ]);

        $payload = [
            'observaciones' => 'Cumple con los criterios establecidos',
            'form_action' => 'validate',
        ];

        $response = $this->actingAs($docente)->post(route('expedientes.sesiones.validate', [$expediente, $sesion]), $payload);

        $response->assertRedirect(route('expedientes.sesiones.show', [$expediente, $sesion]));

        $sesion->refresh();

        $this->assertSame('validada', $sesion->status_revision);
        $this->assertSame($docente->id, $sesion->validada_por);

        $evento = TimelineEvento::where('expediente_id', $expediente->id)
            ->where('evento', 'sesion.validada')
            ->latest('created_at')
            ->first();

        $this->assertNotNull($evento);
        $this->assertSame('pendiente', $evento->payload['estado_anterior']);
        $this->assertSame('validada', $evento->payload['estado_nuevo']);
        $this->assertSame($payload['observaciones'], $evento->payload['observaciones']);
        $this->assertSame($sesion->id, $evento->payload['sesion_id']);
    }

    public function test_alumno_no_puede_editar_sesion_validada(): void
    {
        $alumno = User::factory()->create();
        $alumno->assignRole('alumno');

        $expediente = Expediente::factory()->create([
            'creado_por' => $alumno->id,
            'tutor_id' => null,
            'estado' => 'revision',
        ]);

        $sesion = Sesion::factory()->create([
            'expediente_id' => $expediente->id,
            'realizada_por' => $alumno->id,
            'status_revision' => 'validada',
            'validada_por' => User::factory()->create()->id,
        ]);

        $this->assertSame($expediente->id, $sesion->expediente_id);

        $response = $this->actingAs($alumno)->put(route('expedientes.sesiones.update', [$expediente, $sesion]), [
            'fecha' => now()->toDateString(),
            'tipo' => 'Seguimiento',
            'referencia_externa' => 'REF-001',
            'nota' => '<p>Intento de actualización</p>',
        ]);

        $response->assertForbidden();
    }

    public function test_facilitador_only_lists_and_opens_its_authorized_sessions_within_parent_expediente(): void
    {
        $facilitador = User::factory()->create();
        $facilitador->assignRole('alumno');

        $otherUser = User::factory()->create();

        $expediente = Expediente::factory()->create([
            'creado_por' => $facilitador->id,
            'tutor_id' => $otherUser->id,
            'estado' => 'abierto',
        ]);

        $ownSession = Sesion::factory()->create([
            'expediente_id' => $expediente->id,
            'realizada_por' => $facilitador->id,
            'tipo' => 'Sesión autorizada',
            'status_revision' => 'pendiente',
            'validada_por' => null,
        ]);
        $foreignSession = Sesion::factory()->create([
            'expediente_id' => $expediente->id,
            'realizada_por' => $otherUser->id,
            'tipo' => 'Sesión no autorizada',
            'status_revision' => 'pendiente',
            'validada_por' => null,
        ]);

        $index = $this->actingAs($facilitador)
            ->get(route('expedientes.sesiones.index', $expediente));

        $index->assertOk();
        $index->assertViewHas('sesiones', function ($sesiones) use ($ownSession): bool {
            return $sesiones->pluck('id')->all() === [$ownSession->id];
        });
        $index->assertSee('Sesión autorizada');
        $index->assertDontSee('Sesión no autorizada');

        $this->actingAs($facilitador)
            ->get(route('expedientes.sesiones.show', [$expediente, $ownSession]))
            ->assertOk();
        $this->actingAs($facilitador)
            ->get(route('expedientes.sesiones.show', [$expediente, $foreignSession]))
            ->assertForbidden();
    }

    public function test_session_show_excludes_timeline_events_with_cross_expediente_session_payload(): void
    {
        $facilitador = User::factory()->create();
        $facilitador->assignRole('alumno');

        $expediente = Expediente::factory()->create(['creado_por' => $facilitador->id]);
        $sesion = Sesion::factory()->create([
            'expediente_id' => $expediente->id,
            'realizada_por' => $facilitador->id,
        ]);

        $foreignExpediente = Expediente::factory()->create();
        TimelineEvento::factory()->create([
            'expediente_id' => $foreignExpediente->id,
            'evento' => 'sesion.observada',
            'payload' => [
                'sesion_id' => $sesion->id,
                'observaciones' => 'Dato cruzado que no debe mostrarse',
            ],
        ]);

        $response = $this->actingAs($facilitador)
            ->get(route('expedientes.sesiones.show', [$expediente, $sesion]));

        $response->assertOk();
        $response->assertDontSee('Dato cruzado que no debe mostrarse');
    }

    public function test_private_session_attachment_uses_authorized_download_and_revokes_access_after_reassignment(): void
    {
        config(['filesystems.private_default' => 'private']);
        Storage::fake('private');

        $facilitador = User::factory()->create();
        $facilitador->assignRole('alumno');

        $otherFacilitator = User::factory()->create();
        $otherFacilitator->assignRole('alumno');

        $expediente = Expediente::factory()->create([
            'creado_por' => $facilitador->id,
            'tutor_id' => null,
            'estado' => 'abierto',
        ]);
        $sesion = Sesion::factory()->create([
            'expediente_id' => $expediente->id,
            'realizada_por' => $facilitador->id,
            'status_revision' => 'pendiente',
            'validada_por' => null,
        ]);

        $path = "sesiones/{$sesion->id}/evidencia-privada.pdf";
        Storage::disk('private')->put($path, 'contenido privado');

        $adjunto = $sesion->adjuntos()->create([
            'nombre_original' => 'evidencia-privada.pdf',
            'ruta' => $path,
            'disk' => 'private',
            'mime_type' => 'application/pdf',
            'tamano' => 17,
            'subido_por' => $facilitador->id,
        ]);

        $downloadUrl = route('expedientes.sesiones.adjuntos.download', [
            $expediente,
            $sesion,
            $adjunto,
        ]);

        $this->assertSame($downloadUrl, $adjunto->url);
        $this->assertStringNotContainsString('/storage', $adjunto->url);

        $this->actingAs($facilitador)
            ->get(route('expedientes.sesiones.show', [$expediente, $sesion]))
            ->assertOk()
            ->assertSee($downloadUrl, false)
            ->assertDontSee('/storage', false);

        $this->actingAs($facilitador)
            ->get($downloadUrl)
            ->assertOk()
            ->assertDownload('evidencia-privada.pdf');

        $this->actingAs($otherFacilitator)
            ->get($downloadUrl)
            ->assertForbidden();

        $expediente->update(['creado_por' => $otherFacilitator->id]);
        $sesion->unsetRelation('expediente');

        $this->actingAs($facilitador)
            ->get($downloadUrl)
            ->assertForbidden();

        Storage::disk('private')->assertExists($path);
    }
}

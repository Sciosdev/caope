<?php

namespace Tests\Feature\Sesiones;

use App\Models\CatalogoCarrera;
use App\Models\CatalogoEstrategia;
use App\Models\CatalogoTurno;
use App\Models\Expediente;
use App\Models\Sesion;
use App\Models\SesionAdjunto;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SesionAttachmentDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $facilitator;

    private User $tutor;

    private CatalogoEstrategia $strategy;

    private Expediente $expediente;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array', 'filesystems.private_default' => 'private']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RoleSeeder::class);
        CatalogoCarrera::query()->firstOrCreate(['nombre' => 'Carrera clínica de prueba'], ['activo' => true]);
        CatalogoTurno::query()->firstOrCreate(['nombre' => 'Matutino'], ['activo' => true]);
        CatalogoCarrera::flushCache();
        CatalogoTurno::flushCache();
        Storage::fake('private');
        Storage::fake('public');
        $this->admin = $this->user('admin');
        $this->facilitator = $this->user('alumno');
        $this->tutor = $this->user('docente');
        $this->strategy = CatalogoEstrategia::query()->create(['nombre' => 'Estrategia clínica sintética', 'activo' => true]);
        $this->expediente = $this->case();
    }

    public function test_uploaded_session_document_remains_private_and_is_discoverable_from_both_lists(): void
    {
        $storeResponse = $this->actingAs($this->facilitator)->post(route('expedientes.sesiones.store', $this->expediente), [
            'fecha' => now()->toDateString(),
            'tipo' => 'Seguimiento',
            'nota' => '<p>Sesión sintética con evidencia.</p>',
            'adjuntos' => [UploadedFile::fake()->createWithContent('evidencia-subida.pdf', $this->pdf())],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $session = $this->expediente->sesiones()->sole();
        $storeResponse->assertRedirect(route('expedientes.sesiones.show', [$this->expediente, $session]));
        $attachment = $session->adjuntos()->sole();
        $this->assertSame('private', $attachment->disk);
        $this->assertSame($this->facilitator->id, (int) $attachment->subido_por);
        Storage::disk('private')->assertExists($attachment->ruta);
        Storage::disk('public')->assertMissing($attachment->ruta);
        $this->assertStringNotContainsString('/storage', $attachment->url);

        $anchor = route('expedientes.sesiones.show', [$this->expediente, $session]).'#documentos-sesion';
        $this->get(route('expedientes.sesiones.index', $this->expediente))->assertOk()
            ->assertSee($anchor, false)
            ->assertViewHas('sesiones', fn ($items) => $items->firstWhere('id', $session->id)?->adjuntos_count === 1);
        $this->get(route('expedientes.show', $this->expediente))->assertOk()
            ->assertSee($anchor, false)
            ->assertViewHas('sesiones', fn ($items) => $items->firstWhere('id', $session->id)?->adjuntos_count === 1);
        $this->assertDocumentDetail($session, $attachment);
    }

    #[DataProvider('authorizedProfiles')]
    public function test_clinically_authorized_profiles_can_find_preview_and_download_session_documents(string $role): void
    {
        $actor = match ($role) {
            'admin' => $this->admin,
            'alumno' => $this->facilitator,
            'docente' => $this->tutor,
            default => $this->user($role),
        };
        if ($role === 'estratega') {
            $this->expediente->update(['tutor_id' => $actor->id]);
        }
        if ($role === 'coordinador') {
            $actor->estrategiasCoordinadas()->attach($this->strategy);
        }
        $session = $this->fixtureSession();
        $attachment = $this->attachment($session);
        $this->actingAs($actor);

        $this->assertDocumentDetail($session, $attachment);
        $this->assertInline($this->get($this->attachmentRoute('preview', $session, $attachment))->assertOk(), 'application/pdf');
        $download = $this->get($this->attachmentRoute('download', $session, $attachment))->assertOk()
            ->assertDownload($attachment->nombre_original);
        $this->assertPrivateHeaders($download);
        Storage::disk('private')->assertExists($attachment->ruta);
    }

    #[DataProvider('unrelatedProfiles')]
    public function test_unrelated_profiles_do_not_gain_clinical_documents_or_discovery_links(string $role, bool $approved): void
    {
        $actor = $this->user($role, $approved);
        if ($role === 'coordinador') {
            $otherStrategy = CatalogoEstrategia::query()->create(['nombre' => 'Estrategia diferente sintética', 'activo' => true]);
            $actor->estrategiasCoordinadas()->attach($otherStrategy);
            $this->expediente->update(['coordinador_id' => $actor->id]);
        }
        $session = $this->fixtureSession();
        $attachment = $this->attachment($session);
        $this->actingAs($actor);

        foreach ([
            route('expedientes.sesiones.index', $this->expediente),
            route('expedientes.sesiones.show', [$this->expediente, $session]),
            $this->attachmentRoute('preview', $session, $attachment),
            $this->attachmentRoute('download', $session, $attachment),
        ] as $url) {
            $this->get($url)->assertForbidden()
                ->assertDontSee($attachment->nombre_original)
                ->assertDontSee($attachment->url, false);
        }
        Storage::disk('private')->assertExists($attachment->ruta);
    }

    public function test_assigned_facilitator_does_not_discover_another_authors_documents_in_the_same_case(): void
    {
        $other = $this->user('alumno');
        $session = $this->fixtureSession(author: $other);
        $attachment = $this->attachment($session);
        $anchor = route('expedientes.sesiones.show', [$this->expediente, $session]).'#documentos-sesion';
        $this->actingAs($this->facilitator);

        $this->get(route('expedientes.sesiones.index', $this->expediente))->assertOk()
            ->assertDontSee($anchor, false)->assertDontSee($attachment->nombre_original);
        $this->get(route('expedientes.show', $this->expediente))->assertOk()
            ->assertDontSee($anchor, false)->assertDontSee($attachment->nombre_original);
        $this->get(route('expedientes.sesiones.show', [$this->expediente, $session]))->assertForbidden();
        $this->get($this->attachmentRoute('preview', $session, $attachment))->assertForbidden();
        $this->get($this->attachmentRoute('download', $session, $attachment))->assertForbidden();
    }

    public function test_mismatched_parent_session_and_attachment_identifiers_return_404(): void
    {
        $otherCase = $this->case();
        $session = $this->fixtureSession();
        $attachment = $this->attachment($session);
        $otherSession = $this->fixtureSession();
        $otherAttachment = $this->attachment($otherSession);
        $this->actingAs($this->admin);

        foreach (['preview', 'download'] as $action) {
            foreach ([
                [$otherCase, $session, $attachment],
                [$this->expediente, $otherSession, $attachment],
                [$this->expediente, $session, $otherAttachment],
                [$this->expediente, $session, 999999],
            ] as $identifiers) {
                $this->get(route('expedientes.sesiones.adjuntos.'.$action, $identifiers))->assertNotFound();
            }
        }
        $this->assertDatabaseCount('sesion_adjuntos', 2);
        Storage::disk('private')->assertExists($attachment->ruta);
    }

    public function test_reassignment_revokes_an_old_facilitators_previously_valid_file_urls(): void
    {
        $session = $this->fixtureSession();
        $attachment = $this->attachment($session);
        $preview = $this->attachmentRoute('preview', $session, $attachment);
        $download = $this->attachmentRoute('download', $session, $attachment);
        $this->actingAs($this->facilitator)->get($preview)->assertOk();
        $this->get($download)->assertOk();

        $next = $this->user('alumno');
        $this->actingAs($this->admin)->put(route('expedientes.update', $this->expediente), [
            'facilitador_id' => $next->id,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->actingAs($this->facilitator)->get($preview)->assertForbidden();
        $this->get($download)->assertForbidden();
        $this->get(route('expedientes.sesiones.index', $this->expediente))->assertForbidden();
        $this->assertSame($next->id, (int) $this->expediente->fresh()->facilitador_id);
        Storage::disk('private')->assertExists($attachment->ruta);
    }

    public function test_validated_session_documents_remain_readable_without_granting_edit_permission(): void
    {
        $session = $this->fixtureSession(status: 'validada');
        $attachment = $this->attachment($session);
        $this->actingAs($this->facilitator);

        $this->assertFalse(Gate::forUser($this->facilitator)->allows('update', $session));
        $this->get(route('expedientes.sesiones.edit', [$this->expediente, $session]))->assertForbidden();
        $this->assertDocumentDetail($session, $attachment);
        $this->get($this->attachmentRoute('preview', $session, $attachment))->assertOk();
        $this->get($this->attachmentRoute('download', $session, $attachment))->assertOk()->assertDownload($attachment->nombre_original);
        $this->assertSame('validada', $session->fresh()->status_revision);
    }

    public function test_session_detail_always_has_a_document_section_even_when_no_file_was_uploaded(): void
    {
        $session = $this->fixtureSession();

        $this->actingAs($this->facilitator)->get(route('expedientes.sesiones.show', [$this->expediente, $session]))
            ->assertOk()->assertSee('id="documentos-sesion"', false)
            ->assertSee('Documentos de la sesión')
            ->assertSee('No hay documentos adjuntos en esta sesión.');
    }

    public function test_session_upload_form_explains_where_to_attach_and_find_documents(): void
    {
        $this->actingAs($this->facilitator)->get(route('expedientes.sesiones.create', $this->expediente))
            ->assertOk()
            ->assertSee('Documentos de la sesión')
            ->assertSee('no se adjuntan desde el editor de notas')
            ->assertSee('Máximo 5 archivos por envío, de hasta 10 MB cada uno')
            ->assertSee('Sesiones → Documentos')
            ->assertSee('aria-describedby="adjuntos-ayuda"', false);
    }

    #[DataProvider('rasterFormats')]
    public function test_real_raster_files_can_be_previewed_with_private_sandboxed_headers(string $extension, string $mime): void
    {
        $image = UploadedFile::fake()->image('pixel.'.$extension, 2, 2);
        $session = $this->fixtureSession();
        $attachment = $this->attachment($session, 'imagen.'.$extension, file_get_contents($image->getPathname()), $mime);

        $this->actingAs($this->facilitator);
        $this->assertInline($this->get($this->attachmentRoute('preview', $session, $attachment))->assertOk(), $mime);
    }

    #[DataProvider('unsupportedFormats')]
    public function test_unsafe_or_office_files_have_only_a_protected_download_fallback(string $filename, string $content, string $claimedMime): void
    {
        $session = $this->fixtureSession();
        $attachment = $this->attachment($session, $filename, $content, $claimedMime);
        $this->actingAs($this->facilitator);

        $preview = $this->get($this->attachmentRoute('preview', $session, $attachment))->assertOk()
            ->assertSee($this->attachmentRoute('download', $session, $attachment), false)
            ->assertDontSee($content, false);
        $this->assertStringStartsWith('text/html', (string) $preview->headers->get('Content-Type'));
        $this->assertPrivateHeaders($preview);
        $this->get($this->attachmentRoute('download', $session, $attachment))->assertOk()->assertDownload($filename);

        $this->actingAs($this->user('alumno'))->get($this->attachmentRoute('preview', $session, $attachment))->assertForbidden();
        $this->get($this->attachmentRoute('download', $session, $attachment))->assertForbidden();
    }

    public function test_missing_private_files_return_404_without_deleting_attachment_metadata(): void
    {
        $session = $this->fixtureSession();
        $attachment = $this->attachment($session);
        Storage::disk('private')->delete($attachment->ruta);

        $this->actingAs($this->facilitator);
        $this->get($this->attachmentRoute('preview', $session, $attachment))->assertNotFound();
        $this->get($this->attachmentRoute('download', $session, $attachment))->assertNotFound();
        $this->assertDatabaseHas('sesion_adjuntos', ['id' => $attachment->id]);
    }

    public function test_guests_and_inactive_accounts_cannot_read_session_documents(): void
    {
        $session = $this->fixtureSession();
        $attachment = $this->attachment($session);
        $urls = [
            route('expedientes.sesiones.show', [$this->expediente, $session]),
            $this->attachmentRoute('preview', $session, $attachment),
            $this->attachmentRoute('download', $session, $attachment),
        ];
        foreach ($urls as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }

        $this->facilitator->forceFill(['is_active' => false])->save();
        foreach ($urls as $url) {
            $this->actingAs($this->facilitator)->getJson($url)->assertForbidden();
        }
        Storage::disk('private')->assertExists($attachment->ruta);
    }

    public static function authorizedProfiles(): array
    {
        return [['admin'], ['paps'], ['alumno'], ['docente'], ['estratega'], ['coordinador']];
    }

    public static function unrelatedProfiles(): array
    {
        return [['alumno', true], ['docente', true], ['estratega', true], ['coordinador', true], ['developer', true], ['paps', false]];
    }

    public static function rasterFormats(): array
    {
        return [['png', 'image/png'], ['jpg', 'image/jpeg']];
    }

    public static function unsupportedFormats(): array
    {
        return [
            'HTML disguised as PDF' => ['no-es-pdf.pdf', '<!doctype html><html><body><script>alert("clinical-synthetic")</script></body></html>', 'application/pdf'],
            'legacy SVG' => ['imagen-activa.svg', '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(\'clinical-synthetic\')"></svg>', 'image/svg+xml'],
            'office document' => ['evidencia.docx', 'Documento de oficina sintético no incrustable.', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        ];
    }

    private function user(string $role, bool $approved = true): User
    {
        $user = User::factory()->create(['name' => 'Cuenta clínica sintética '.$role, 'approved_at' => $approved ? now() : null, 'is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    private function case(): Expediente
    {
        return Expediente::factory()->create([
            'paciente' => 'Paciente clínico sintético',
            'estado' => 'abierto',
            'carrera' => 'Carrera clínica de prueba',
            'turno' => 'Matutino',
            'creado_por' => $this->admin->id,
            'facilitador_id' => $this->facilitator->id,
            'tutor_id' => $this->tutor->id,
            'coordinador_id' => null,
            'estrategia_id' => $this->strategy->id,
        ]);
    }

    private function fixtureSession(?User $author = null, string $status = 'pendiente'): Sesion
    {
        return Sesion::factory()->create([
            'expediente_id' => $this->expediente->id,
            'fecha' => now()->toDateString(),
            'tipo' => 'Seguimiento',
            'nota' => '<p>Nota clínica sintética.</p>',
            'realizada_por' => ($author ?? $this->facilitator)->id,
            'status_revision' => $status,
            'validada_por' => $status === 'validada' ? $this->tutor->id : null,
        ]);
    }

    private function attachment(Sesion $session, string $filename = 'evidencia-privada.pdf', ?string $content = null, string $mime = 'application/pdf'): SesionAdjunto
    {
        $bytes = $content ?? $this->pdf();
        $path = 'sesiones/'.$session->id.'/'.Str::uuid().'.'.pathinfo($filename, PATHINFO_EXTENSION);
        Storage::disk('private')->put($path, $bytes);

        return $session->adjuntos()->create([
            'nombre_original' => $filename,
            'ruta' => $path,
            'disk' => 'private',
            'mime_type' => $mime,
            'tamano' => strlen($bytes),
            'subido_por' => $this->facilitator->id,
        ]);
    }

    private function pdf(): string
    {
        return "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n";
    }

    private function attachmentRoute(string $action, Sesion $session, SesionAdjunto $attachment): string
    {
        return route('expedientes.sesiones.adjuntos.'.$action, [$this->expediente, $session, $attachment]);
    }

    private function assertDocumentDetail(Sesion $session, SesionAdjunto $attachment): void
    {
        $this->get(route('expedientes.sesiones.show', [$this->expediente, $session]))->assertOk()
            ->assertSee('id="documentos-sesion"', false)
            ->assertSee($attachment->nombre_original)
            ->assertSee($this->attachmentRoute('preview', $session, $attachment), false)
            ->assertSee($this->attachmentRoute('download', $session, $attachment), false);
    }

    private function assertInline(TestResponse $response, string $mime): void
    {
        $response->assertHeader('Content-Type', $mime)->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringStartsWith('inline;', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('sandbox', (string) $response->headers->get('Content-Security-Policy'));
        $this->assertPrivateHeaders($response);
    }

    private function assertPrivateHeaders(TestResponse $response): void
    {
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringContainsString('no-store', $cacheControl);
    }
}

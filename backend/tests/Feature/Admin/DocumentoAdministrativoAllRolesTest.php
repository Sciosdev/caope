<?php

namespace Tests\Feature\Admin;

use App\Models\DocumentoAdministrativo;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DocumentoAdministrativoAllRolesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array', 'filesystems.private_default' => 'private']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RoleSeeder::class);
        Role::findOrCreate('tutor', 'web');
        Role::findOrCreate('perfil-personalizado', 'web');
        Storage::fake('private');
    }

    #[DataProvider('allProfiles')]
    public function test_every_active_profile_can_list_view_and_download_a_published_administrative_document(?string $role): void
    {
        $document = $this->document();
        $actor = $this->user($role);

        $this->actingAs($actor)->get(route('admin.documentos.index'))->assertOk()
            ->assertSee($document->titulo)
            ->assertSee(route('admin.documentos.show', $document), false)
            ->assertSee(route('admin.documentos.download', $document), false);

        $download = $this->get(route('admin.documentos.download', $document));
        $download->assertOk()->assertDownload($document->titulo.'.pdf');
        $this->assertPrivateHeaders($download);

        $preview = $this->get(route('admin.documentos.show', $document))->assertOk();
        $this->assertInlineHeaders($preview, 'application/pdf');
        Storage::disk('private')->assertExists($document->ruta);
        $this->assertDatabaseCount('documentos_administrativos', 1);
    }

    #[DataProvider('nonAdminProfiles')]
    public function test_unpublished_documents_are_hidden_and_forbidden_even_to_the_uploading_non_admin(?string $role): void
    {
        $actor = $this->user($role);
        $document = $this->document(approved: false);
        $document->update(['subido_por' => $actor->id]);

        $this->actingAs($actor)->get(route('admin.documentos.index'))->assertOk()
            ->assertDontSee($document->titulo);
        $this->get(route('admin.documentos.show', $document))->assertForbidden();
        $this->get(route('admin.documentos.download', $document))->assertForbidden();

        $this->assertNull($document->fresh()->aprobado_en);
        Storage::disk('private')->assertExists($document->ruta);
    }

    public function test_admin_can_inspect_an_unpublished_document_without_approving_it(): void
    {
        $document = $this->document(approved: false);

        $this->actingAs($this->user('admin'))->get(route('admin.documentos.index'))->assertOk()
            ->assertSee($document->titulo);
        $this->get(route('admin.documentos.download', $document))->assertOk()->assertDownload($document->titulo.'.pdf');
        $this->assertInlineHeaders($this->get(route('admin.documentos.show', $document))->assertOk(), 'application/pdf');

        $this->assertNull($document->fresh()->aprobado_en);
    }

    #[DataProvider('rasterExtensions')]
    public function test_supported_raster_previews_are_inline_with_sandboxed_private_headers(string $extension, string $mime): void
    {
        $image = UploadedFile::fake()->image('pixel.'.$extension, 2, 2);
        $bytes = file_get_contents($image->getPathname());
        $document = $this->document(extension: $extension, mime: $mime, content: $bytes);

        $this->actingAs($this->user('docente'));
        $this->assertInlineHeaders($this->get(route('admin.documentos.show', $document))->assertOk(), $mime);
    }

    #[DataProvider('unsupportedPreviewFormats')]
    public function test_active_html_and_svg_content_is_not_embedded_but_has_a_download_fallback(string $extension, string $mime, string $content): void
    {
        $document = $this->document(extension: $extension, mime: $mime, content: $content);

        $response = $this->actingAs($this->user('alumno'))
            ->get(route('admin.documentos.show', $document))->assertOk()
            ->assertSee(route('admin.documentos.download', $document), false)
            ->assertDontSee($content, false);
        $this->assertStringStartsWith('text/html', (string) $response->headers->get('Content-Type'));
        $this->assertPrivateHeaders($response);
        Storage::disk('private')->assertExists($document->ruta);
    }

    public function test_claimed_pdf_metadata_cannot_turn_html_into_an_inline_document(): void
    {
        $content = '<!doctype html><html><body><script>alert("synthetic-document")</script></body></html>';
        $document = $this->document(extension: 'pdf', mime: 'application/pdf', content: $content);

        $response = $this->actingAs($this->user('docente'))
            ->get(route('admin.documentos.show', $document))->assertOk()
            ->assertSee(route('admin.documentos.download', $document), false)
            ->assertDontSee($content, false);
        $this->assertStringStartsWith('text/html', (string) $response->headers->get('Content-Type'));
    }

    public function test_guests_are_redirected_to_login_for_every_read_route(): void
    {
        $document = $this->document();

        foreach ($this->readRoutes($document) as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }

        Storage::disk('private')->assertExists($document->ruta);
    }

    public function test_inactive_accounts_cannot_read_even_with_an_admin_role(): void
    {
        $document = $this->document();
        $actor = $this->user('admin');
        $actor->forceFill(['is_active' => false])->save();

        foreach ($this->readRoutes($document) as $url) {
            $this->actingAs($actor)->getJson($url)->assertForbidden();
        }

        Storage::disk('private')->assertExists($document->ruta);
    }

    public function test_unapproved_paps_can_read_published_material_but_not_pending_files_or_upload(): void
    {
        $actor = $this->user('paps', false);
        $published = $this->document();
        $pending = $this->document(approved: false);
        $files = Storage::disk('private')->allFiles();

        $this->actingAs($actor)->get(route('admin.documentos.index'))->assertOk()
            ->assertSee($published->titulo)->assertDontSee($pending->titulo);
        $this->get(route('admin.documentos.download', $published))->assertOk()->assertDownload($published->titulo.'.pdf');
        $this->assertInlineHeaders($this->get(route('admin.documentos.show', $published))->assertOk(), 'application/pdf');
        $this->get(route('admin.documentos.download', $pending))->assertForbidden();
        $this->get(route('admin.documentos.show', $pending))->assertForbidden();
        $this->post(route('admin.documentos.store'), [
            'titulo' => 'Carga sin aprobación no permitida',
            'archivo' => UploadedFile::fake()->create('no-permitido.pdf', 1, 'application/pdf'),
        ])->assertForbidden();

        $this->assertNull($actor->fresh()->approved_at);
        $this->assertDatabaseCount('documentos_administrativos', 2);
        $this->assertSame($files, Storage::disk('private')->allFiles());
    }

    public function test_missing_private_files_return_404_for_view_and_download(): void
    {
        $document = $this->document();
        Storage::disk('private')->delete($document->ruta);

        $this->actingAs($this->user('alumno'));
        $this->get(route('admin.documentos.show', $document))->assertNotFound();
        $this->get(route('admin.documentos.download', $document))->assertNotFound();
        $this->assertDatabaseHas('documentos_administrativos', ['id' => $document->id]);
    }

    public function test_download_names_preserve_accents_and_safely_replace_path_and_control_characters(): void
    {
        $document = $this->document();
        $document->update(['titulo' => 'Guía / información\\clínica'."\r\n"]);

        $response = $this->actingAs($this->user('alumno'))
            ->get(route('admin.documentos.download', $document))->assertOk();
        $disposition = (string) $response->headers->get('Content-Disposition');
        $this->assertStringStartsWith('attachment;', $disposition);
        $this->assertStringContainsString("filename*=utf-8''".rawurlencode('Guía - información-clínica.pdf'), $disposition);
        $this->assertStringNotContainsString("\r", $disposition);
        $this->assertStringNotContainsString("\n", $disposition);
        $this->assertStringNotContainsString('/', $disposition);
        $this->assertStringNotContainsString('\\', $disposition);
        $this->assertPrivateHeaders($response);
        Storage::disk('private')->assertExists($document->ruta);
    }

    #[DataProvider('nonAdminProfiles')]
    public function test_read_access_never_grants_update_delete_or_approval_and_preserves_the_private_file(?string $role): void
    {
        $actor = $this->user($role);
        $document = $this->document(approved: false);
        $before = $document->fresh()->getAttributes();
        $bytes = Storage::disk('private')->get($document->ruta);
        $files = Storage::disk('private')->allFiles();
        $this->actingAs($actor);

        $this->put(route('admin.documentos.update', $document), [
            'titulo' => 'Alteración no permitida',
            'archivo' => UploadedFile::fake()->create('reemplazo.pdf', 1, 'application/pdf'),
            'roles' => ['admin'],
        ])->assertForbidden();
        $this->delete(route('admin.documentos.destroy', $document), ['roles' => ['admin']])->assertForbidden();
        $this->post(route('admin.documentos.approve', $document), ['roles' => ['admin']])->assertForbidden();

        $this->assertSame($before, $document->fresh()->getAttributes());
        $this->assertSame($bytes, Storage::disk('private')->get($document->ruta));
        $this->assertSame($files, Storage::disk('private')->allFiles());
        $this->assertDatabaseCount('documentos_administrativos', 1);

        if ($role !== 'paps') {
            $this->post(route('admin.documentos.store'), [
                'titulo' => 'Carga no permitida',
                'archivo' => UploadedFile::fake()->create('no-permitido.pdf', 1, 'application/pdf'),
                'roles' => ['admin', 'paps'],
                'approved' => true,
            ])->assertForbidden();
            $this->assertDatabaseCount('documentos_administrativos', 1);
            $this->assertSame($files, Storage::disk('private')->allFiles());
        }
    }

    public function test_unapproved_paps_cannot_upload_approve_replace_or_delete_documents(): void
    {
        $actor = $this->user('paps', false);
        $actor->assignRole('coordinador');
        $document = $this->document(approved: false);
        $before = $document->fresh()->getAttributes();
        $files = Storage::disk('private')->allFiles();
        $this->actingAs($actor);

        $this->post(route('admin.documentos.store'), [
            'titulo' => 'Carga PAPS sin aprobar',
            'archivo' => UploadedFile::fake()->create('no-autorizado.pdf', 1, 'application/pdf'),
        ])->assertForbidden();
        $this->put(route('admin.documentos.update', $document), ['titulo' => 'Cambio no autorizado'])->assertForbidden();
        $this->delete(route('admin.documentos.destroy', $document))->assertForbidden();
        $this->post(route('admin.documentos.approve', $document))->assertForbidden();

        $this->assertSame($before, $document->fresh()->getAttributes());
        $this->assertDatabaseCount('documentos_administrativos', 1);
        $this->assertSame($files, Storage::disk('private')->allFiles());
    }

    #[DataProvider('publishingProfiles')]
    public function test_admin_and_approved_paps_keep_upload_permission_without_paps_self_publication(string $role): void
    {
        $actor = $this->user($role);

        $this->actingAs($actor)->post(route('admin.documentos.store'), [
            'titulo' => 'Manual sintético subido',
            'archivo' => UploadedFile::fake()->create('manual.pdf', 1, 'application/pdf'),
            'aprobado_en' => now()->toDateTimeString(),
            'aprobado_por' => $actor->id,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $document = DocumentoAdministrativo::query()->firstOrFail();
        $this->assertSame($actor->id, (int) $document->subido_por);
        $this->assertSame('private', $document->disk);
        $this->assertSame($role === 'admin', $document->aprobado_en !== null);
        $this->assertSame($role === 'admin' ? $actor->id : null, $document->aprobado_por);
        Storage::disk('private')->assertExists($document->ruta);
    }

    public static function allProfiles(): array
    {
        return ['admin' => ['admin']] + self::nonAdminProfiles();
    }

    public static function nonAdminProfiles(): array
    {
        return [
            'PAPS aprobado' => ['paps'],
            'facilitador' => ['alumno'],
            'coordinador' => ['coordinador'],
            'docente' => ['docente'],
            'estratega' => ['estratega'],
            'desarrollador' => ['developer'],
            'tutor' => ['tutor'],
            'rol personalizado' => ['perfil-personalizado'],
            'cuenta activa sin roles' => [null],
        ];
    }

    public static function rasterExtensions(): array
    {
        return [['png', 'image/png'], ['jpg', 'image/jpeg']];
    }

    public static function unsupportedPreviewFormats(): array
    {
        return [
            'HTML' => ['html', 'text/html', '<!doctype html><html><body><script>alert("synthetic-html")</script></body></html>'],
            'SVG' => ['svg', 'image/svg+xml', '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(\'synthetic-svg\')"></svg>'],
        ];
    }

    public static function publishingProfiles(): array
    {
        return [['admin'], ['paps']];
    }

    private function user(?string $role, bool $approved = true): User
    {
        $user = User::factory()->create([
            'name' => 'Cuenta sintética de documentos',
            'is_active' => true,
            'approved_at' => $approved ? now() : null,
        ]);
        if ($role !== null) {
            $user->assignRole($role);
        }

        return $user;
    }

    private function document(bool $approved = true, string $extension = 'pdf', string $mime = 'application/pdf', ?string $content = null): DocumentoAdministrativo
    {
        $name = (string) Str::uuid();
        $path = 'documentos-administrativos/'.$name.'.'.$extension;
        $bytes = $content ?? "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n";
        Storage::disk('private')->put($path, $bytes);

        return DocumentoAdministrativo::query()->create([
            'titulo' => 'Manual de prueba '.$name,
            'ruta' => $path,
            'disk' => 'private',
            'mime_type' => $mime,
            'tamano' => strlen($bytes),
            'aprobado_en' => $approved ? now() : null,
        ]);
    }

    private function readRoutes(DocumentoAdministrativo $document): array
    {
        return [
            route('admin.documentos.index'),
            route('admin.documentos.show', $document),
            route('admin.documentos.download', $document),
        ];
    }

    private function assertInlineHeaders(TestResponse $response, string $mime): void
    {
        $response->assertHeader('Content-Type', $mime)
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringStartsWith('inline;', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('sandbox', (string) $response->headers->get('Content-Security-Policy'));
        $this->assertPrivateHeaders($response);
    }

    private function assertPrivateHeaders(TestResponse $response): void
    {
        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringContainsString('no-store', $cacheControl);
    }
}

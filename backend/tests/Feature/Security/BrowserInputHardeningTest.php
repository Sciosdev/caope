<?php

namespace Tests\Feature\Security;

use App\Exports\Concerns\BindsSpreadsheetValuesSafely;
use App\Http\Controllers\ConsentimientoPdfController;
use App\Models\Expediente;
use App\Models\Parametro;
use App\Models\User;
use App\Support\Html\SesionNoteSanitizer;
use App\Support\Uploads\AnexoUploadOptions;
use App\Support\Uploads\ConsentimientoUploadOptions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use ReflectionMethod;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BrowserInputHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_session_notes_are_sanitized_before_storage_and_again_when_rendered(): void
    {
        Role::firstOrCreate(['name' => 'alumno']);
        $facilitador = User::factory()->create();
        $facilitador->assignRole('alumno');
        $expediente = Expediente::factory()->create([
            'creado_por' => $facilitador->id,
            'estado' => 'abierto',
        ]);

        $payload = '<p>Texto <strong>permitido</strong></p>'
            .'<img src=x onerror=alert(1)>'
            .'<script>alert(2)</script>'
            .'<a href="javascript:alert(3)">enlace</a>';

        $this->actingAs($facilitador)
            ->post(route('expedientes.sesiones.store', $expediente), [
                'fecha' => now()->toDateString(),
                'tipo' => 'Seguimiento',
                'nota' => $payload,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $sesion = $expediente->sesiones()->sole();
        $this->assertStringContainsString('<strong>permitido</strong>', $sesion->nota);
        $this->assertStringNotContainsString('<script', $sesion->nota);
        $this->assertStringNotContainsString('<img', $sesion->nota);
        $this->assertStringNotContainsString('javascript:', $sesion->nota);
        $this->assertStringNotContainsString('onerror', $sesion->nota);

        $this->actingAs($facilitador)
            ->get(route('expedientes.sesiones.show', [$expediente, $sesion]))
            ->assertOk()
            ->assertSee('<strong>permitido</strong>', false)
            ->assertDontSee('alert(2)', false)
            ->assertDontSee('<img src=x', false)
            ->assertDontSee('javascript:', false)
            ->assertDontSee('onerror', false);
    }

    public function test_spreadsheet_binder_neutralizes_formula_prefixes_for_xlsx_and_csv(): void
    {
        $binder = new class extends DefaultValueBinder
        {
            use BindsSpreadsheetValuesSafely;
        };
        $sheet = (new Spreadsheet)->getActiveSheet();

        foreach (['=1+1', '+cmd', '-2+3', '@SUM(A1:A2)', " \t=HYPERLINK(\"https://example.test\")"] as $index => $value) {
            $cell = $sheet->getCell('A'.($index + 1));
            $this->assertTrue($binder->bindValue($cell, $value));
            $this->assertSame("'".$value, $cell->getValue());
            $this->assertSame(DataType::TYPE_STRING, $cell->getDataType());
        }
    }

    public function test_configured_upload_types_cannot_enable_active_web_content(): void
    {
        Parametro::factory()->create([
            'clave' => 'uploads.anexos.mimes',
            'valor' => 'html,svg,pdf',
            'tipo' => Parametro::TYPE_STRING,
        ]);
        Parametro::factory()->create([
            'clave' => 'uploads.consentimientos.mimes',
            'valor' => 'html,svg,jpg',
            'tipo' => Parametro::TYPE_STRING,
        ]);

        $this->assertSame(['pdf'], AnexoUploadOptions::allowedExtensions());
        $this->assertSame('jpg', ConsentimientoUploadOptions::allowedExtensionsString());
        $this->assertFalse(AnexoUploadOptions::isSafeConfiguration('pdf,svg'));
        $this->assertFalse(ConsentimientoUploadOptions::isSafeConfiguration('pdf,html'));
    }

    public function test_consentimiento_logo_never_performs_remote_requests_or_reads_traversal_paths(): void
    {
        Http::fake();
        $parametro = Parametro::factory()->create([
            'clave' => 'consentimientos.logo_path',
            'valor' => 'https://audit.invalid/steal.svg',
            'tipo' => Parametro::TYPE_STRING,
        ]);
        $method = new ReflectionMethod(ConsentimientoPdfController::class, 'resolveLogoSources');
        $controller = new ConsentimientoPdfController;

        $remoteResult = $method->invoke($controller);
        Http::assertNothingSent();
        $this->assertSame(public_path('assets/images/consentimientos/escudo-unam.png'), $remoteResult['logoPath']);

        $parametro->update(['valor' => '../../.env']);
        Parametro::forget('consentimientos.logo_path');
        $traversalResult = $method->invoke($controller);

        $this->assertSame(public_path('assets/images/consentimientos/escudo-unam.png'), $traversalResult['logoPath']);
        $this->assertStringNotContainsString('APP_KEY', $traversalResult['logoDataUri']);
    }

    public function test_consultorio_dom_templates_escape_server_controlled_names_and_strategies(): void
    {
        $view = file_get_contents(resource_path('views/consultorios/index.blade.php'));

        $this->assertIsString($view);
        $this->assertStringContainsString('const escapeHtml = (value)', $view);
        $this->assertStringContainsString('escapeHtml(item.estrategia', $view);
        $this->assertStringContainsString('escapeHtml(item.estratega_nombre', $view);
        $this->assertStringContainsString('escapeHtml(item.usuario_atendido_nombre', $view);
    }

    public function test_sanitizer_preserves_only_the_intended_rich_text_subset(): void
    {
        $sanitized = app(SesionNoteSanitizer::class)->sanitize(
            '<p><em>válido</em><iframe src="https://audit.invalid"></iframe>'
            .'<a href="https://example.test">seguro</a></p>'
        );

        $this->assertStringContainsString('<em>válido</em>', $sanitized);
        $this->assertStringContainsString('href="https://example.test"', $sanitized);
        $this->assertStringNotContainsString('iframe', $sanitized);
    }
}

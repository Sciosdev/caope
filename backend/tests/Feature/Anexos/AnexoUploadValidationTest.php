<?php

namespace Tests\Feature\Anexos;

use App\Models\Expediente;
use App\Models\User;
use App\Support\Uploads\AnexoUploadOptions;
use Database\Seeders\ParametroSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AnexoUploadValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);
    }

    public function test_json_upload_accepts_all_allowed_extensions(): void
    {
        Storage::fake('private');
        Storage::fake('public');

        $this->seed(ParametroSeeder::class);

        $usuario = $this->createUploader();
        $expediente = Expediente::factory()->create([
            'creado_por' => $usuario->id,
        ]);

        foreach ($this->allowedExtensionsWithMimes() as [$extension, $mime]) {
            $archivo = $this->fakeFile($extension, $mime);
            $this->clearUploadRateLimit($usuario);

            $response = $this->actingAs($usuario)
                ->post(
                    route('expedientes.anexos.store', $expediente),
                    [
                        'archivo' => $archivo,
                        'es_privado' => true,
                    ],
                    ['HTTP_ACCEPT' => 'application/json']
                );

            $response->assertStatus(201);
        }
    }

    public function test_form_upload_accepts_all_allowed_extensions(): void
    {
        Storage::fake('private');
        Storage::fake('public');

        $this->seed(ParametroSeeder::class);

        $usuario = $this->createUploader();
        $expediente = Expediente::factory()->create([
            'creado_por' => $usuario->id,
        ]);

        foreach ($this->allowedExtensionsWithMimes() as [$extension, $mime]) {
            $archivo = $this->fakeFile($extension, $mime);
            $this->clearUploadRateLimit($usuario);

            $response = $this->actingAs($usuario)
                ->from(route('expedientes.show', $expediente))
                ->followingRedirects()
                ->post(route('expedientes.anexos.store', $expediente), [
                    'archivo' => $archivo,
                    'es_privado' => true,
                ]);

            $response->assertOk();
        }
    }

    public function test_disallowed_extensions_are_rejected_with_422(): void
    {
        Storage::fake('private');
        Storage::fake('public');

        $this->seed(ParametroSeeder::class);

        $usuario = $this->createUploader();
        $expediente = Expediente::factory()->create([
            'creado_por' => $usuario->id,
        ]);

        $this->clearUploadRateLimit($usuario);

        $archivo = UploadedFile::fake()->create('archivo.exe', 10, 'application/octet-stream');

        $response = $this->actingAs($usuario)
            ->post(
                route('expedientes.anexos.store', $expediente),
                [
                    'archivo' => $archivo,
                    'es_privado' => true,
                ],
                ['HTTP_ACCEPT' => 'application/json']
            );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('archivo');
    }

    public function test_file_size_respects_configured_limit(): void
    {
        Storage::fake('private');
        Storage::fake('public');

        $this->seed(ParametroSeeder::class);

        $usuario = $this->createUploader();
        $expediente = Expediente::factory()->create([
            'creado_por' => $usuario->id,
        ]);

        $this->clearUploadRateLimit($usuario);

        $maxKilobytes = AnexoUploadOptions::maxKilobytes();

        $archivoPermitido = UploadedFile::fake()->create('dentro-del-limite.pdf', $maxKilobytes, 'application/pdf');

        $this->actingAs($usuario)
            ->post(
                route('expedientes.anexos.store', $expediente),
                [
                    'archivo' => $archivoPermitido,
                    'es_privado' => true,
                ],
                ['HTTP_ACCEPT' => 'application/json']
            )
            ->assertStatus(201);

        $archivoGrande = UploadedFile::fake()->create('fuera-del-limite.pdf', $maxKilobytes + 1, 'application/pdf');

        $this->clearUploadRateLimit($usuario);

        $response = $this->actingAs($usuario)
            ->post(
                route('expedientes.anexos.store', $expediente),
                [
                    'archivo' => $archivoGrande,
                    'es_privado' => true,
                ],
                ['HTTP_ACCEPT' => 'application/json']
            );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('archivo');
    }

    /**
     * @return array<int, array{0: string, 1: string}>
     */
    private function allowedExtensionsWithMimes(): array
    {
        return [
            ['pdf', 'application/pdf'],
            ['jpg', 'image/jpeg'],
            ['jpeg', 'image/jpeg'],
            ['png', 'image/png'],
            ['doc', 'application/msword'],
            ['docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            ['xls', 'application/vnd.ms-excel'],
            ['xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            ['ppt', 'application/vnd.ms-powerpoint'],
            ['pptx', 'application/vnd.openxmlformats-officedocument.presentationml.presentation'],
            ['txt', 'text/plain'],
            ['csv', 'text/csv'],
        ];
    }

    private function fakeFile(string $extension, string $mime): UploadedFile
    {
        if (in_array($extension, ['jpg', 'jpeg', 'png'], true)) {
            return UploadedFile::fake()->image("anexo.{$extension}");
        }

        return UploadedFile::fake()->create("anexo.{$extension}", 10, $mime);
    }

    private function createUploader(): User
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permission = Permission::firstOrCreate(['name' => 'expedientes.manage']);

        $user = User::factory()->create();
        $user->givePermissionTo($permission);

        return $user;
    }

    private function clearUploadRateLimit(User $user): void
    {
        $identifier = (string) $user->getAuthIdentifier();

        if ($identifier !== '') {
            RateLimiter::clear('anexos|'.$identifier);
        }
    }
}

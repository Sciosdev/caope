<?php

namespace Tests\Feature\Anexos;

use App\Models\Expediente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AnexoUploadRateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::firstOrCreate(['name' => 'expedientes.manage']);
    }

    public function test_anexo_upload_is_rate_limited(): void
    {
        $usuario = User::factory()->create();
        $usuario->givePermissionTo('expedientes.manage');

        $expediente = Expediente::factory()->create([
            'creado_por' => $usuario->id,
        ]);

        $limiterKey = sprintf('uploads.anexos|anexos|%s', $usuario->getAuthIdentifier());
        RateLimiter::clear($limiterKey);

        for ($attempt = 0; $attempt < 8; $attempt++) {
            $response = $this->actingAs($usuario)
                ->from(route('expedientes.show', $expediente))
                ->post(route('expedientes.anexos.store', $expediente), []);

            $response->assertStatus(302);
            $response->assertSessionHasErrors('archivo');
        }

        $response = $this->actingAs($usuario)
            ->from(route('expedientes.show', $expediente))
            ->post(route('expedientes.anexos.store', $expediente), []);

        $response->assertStatus(429);
    }
}

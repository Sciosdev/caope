<?php

namespace Tests\Feature\Consentimientos;

use App\Models\Consentimiento;
use App\Models\Expediente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\RateLimiter;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ConsentimientoUploadRateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::firstOrCreate(['name' => 'expedientes.manage']);
    }

    public function test_consentimiento_upload_is_rate_limited(): void
    {
        $usuario = User::factory()->create();
        $usuario->givePermissionTo('expedientes.manage');

        $expediente = Expediente::factory()->create([
            'creado_por' => $usuario->id,
        ]);

        $consentimiento = Consentimiento::factory()->for($expediente)->create([
            'aceptado' => false,
            'archivo_path' => null,
        ]);

        $limiterKey = sprintf('uploads.consentimientos|consentimientos|%s', $usuario->getAuthIdentifier());
        RateLimiter::clear($limiterKey);

        try {
            Carbon::setTestNow($now = now());

            for ($attempt = 0; $attempt < 5; $attempt++) {
                $response = $this->actingAs($usuario)
                    ->from(route('expedientes.show', $expediente))
                    ->post(route('consentimientos.upload', $consentimiento), []);

                $response->assertStatus(302);
                $response->assertSessionHasErrorsIn(
                    sprintf('consentimientoUpload-%s', $consentimiento->id),
                    ['archivo']
                );
            }

            $response = $this->actingAs($usuario)
                ->from(route('expedientes.show', $expediente))
                ->post(route('consentimientos.upload', $consentimiento), []);

            $response->assertStatus(429);

            Carbon::setTestNow($now->copy()->addMinutes(10)->addSecond());

            $response = $this->actingAs($usuario)
                ->from(route('expedientes.show', $expediente))
                ->post(route('consentimientos.upload', $consentimiento), []);

            $response->assertStatus(302);
            $response->assertSessionHasErrorsIn(
                sprintf('consentimientoUpload-%s', $consentimiento->id),
                ['archivo']
            );
        } finally {
            Carbon::setTestNow();
        }
    }
}

<?php

namespace Tests\Feature\Expedientes;

use App\Models\Expediente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ExpedienteNameMaskingTest extends TestCase
{
    use RefreshDatabase;

    public function test_expediente_index_masks_patient_name(): void
    {
        $user = $this->createDocenteUser();

        $expediente = Expediente::factory()->create([
            'paciente' => 'Juan Perez',
            'tutor_id' => $user->id,
            'creado_por' => $user->id,
        ]);

        $response = $this->actingAs($user)->get(route('expedientes.index'));

        $response->assertOk();
        $response->assertSeeText('J*** P****');
        $response->assertDontSeeText('Juan Perez');
    }

    public function test_authorized_user_sees_full_patient_name_on_detail(): void
    {
        $user = $this->createDocenteUser();

        $expediente = Expediente::factory()->create([
            'paciente' => 'Juan Perez',
            'tutor_id' => $user->id,
            'creado_por' => $user->id,
        ]);

        $response = $this->actingAs($user)->get(route('expedientes.show', $expediente));

        $response->assertOk();
        $response->assertSeeText('Juan Perez');
        $response->assertDontSeeText('J*** P****');
    }

    private function createDocenteUser(): User
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $viewPermission = Permission::firstOrCreate([
            'name' => 'expedientes.view',
            'guard_name' => 'web',
        ]);

        $role = Role::firstOrCreate([
            'name' => 'docente',
            'guard_name' => 'web',
        ]);

        $role->syncPermissions([$viewPermission]);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}

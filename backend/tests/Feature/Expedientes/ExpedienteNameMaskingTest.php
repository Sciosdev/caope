<?php

namespace Tests\Feature\Expedientes;

use App\Models\Expediente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ExpedienteNameMaskingTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_sees_full_patient_name_on_index(): void
    {
        $user = $this->createDocenteUser();

        $expediente = Expediente::factory()->create([
            'paciente' => 'Juan Perez',
            'tutor_id' => $user->id,
            'creado_por' => $user->id,
        ]);

        $paginator = new LengthAwarePaginator([$expediente], 1, 15);

        $view = $this->actingAs($user)->view('expedientes.index', [
            'expedientes' => $paginator,
            'q' => '',
            'estado' => '',
            'desde' => null,
            'hasta' => null,
            'carrera' => '',
            'turno' => '',
            'carreras' => collect(),
            'turnos' => collect(),
        ]);

        $view->assertSeeText('Juan Perez');
        $view->assertDontSeeText('J*** P****');
    }

    public function test_unauthorized_user_sees_masked_patient_name_on_index(): void
    {
        $user = $this->createDocenteUser();
        $anotherUser = User::factory()->create();

        $expediente = Expediente::factory()->create([
            'paciente' => 'Juan Perez',
            'tutor_id' => $anotherUser->id,
            'creado_por' => $anotherUser->id,
        ]);

        $paginator = new LengthAwarePaginator([$expediente], 1, 15);

        $view = $this->actingAs($user)->view('expedientes.index', [
            'expedientes' => $paginator,
            'q' => '',
            'estado' => '',
            'desde' => null,
            'hasta' => null,
            'carrera' => '',
            'turno' => '',
            'carreras' => collect(),
            'turnos' => collect(),
        ]);

        $view->assertSeeText('J*** P****');
        $view->assertDontSeeText('Juan Perez');
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

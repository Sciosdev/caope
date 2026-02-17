<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserActiveAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_usuario_inactivo_no_puede_acceder_y_se_desloguea(): void
    {
        $role = Role::query()->firstOrCreate(['name' => 'alumno', 'guard_name' => 'web']);
        $user = User::factory()->create(['is_active' => false]);
        $user->assignRole($role);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }
}

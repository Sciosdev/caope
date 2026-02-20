<?php

namespace Tests\Feature\Consultorios;

use App\Models\ConsultorioReserva;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ConsultorioReservaTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_permite_empalmes_en_mismo_consultorio_y_cubiculo(): void
    {
        $role = Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole($role);

        ConsultorioReserva::query()->create([
            'fecha' => now()->addDay()->toDateString(),
            'hora_inicio' => '09:00',
            'hora_fin' => '10:00',
            'consultorio_numero' => 3,
            'cubiculo_numero' => 2,
            'estrategia' => 'Intervención breve',
            'creado_por' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->post(route('consultorios.store'), [
            'fecha' => now()->addDay()->toDateString(),
            'hora_inicio' => '09:30',
            'hora_fin' => '10:30',
            'consultorio_numero' => 3,
            'cubiculo_numero' => 2,
            'estrategia' => 'Otra estrategia',
        ]);

        $response->assertSessionHasErrors('hora_inicio');
    }

    public function test_permite_mismo_cubiculo_en_distinto_consultorio(): void
    {
        $role = Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole($role);

        ConsultorioReserva::query()->create([
            'fecha' => now()->addDay()->toDateString(),
            'hora_inicio' => '09:00',
            'hora_fin' => '10:00',
            'consultorio_numero' => 1,
            'cubiculo_numero' => 1,
            'estrategia' => 'Intervención breve',
            'creado_por' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->post(route('consultorios.store'), [
            'fecha' => now()->addDay()->toDateString(),
            'hora_inicio' => '09:30',
            'hora_fin' => '10:30',
            'consultorio_numero' => 2,
            'cubiculo_numero' => 1,
            'estrategia' => 'Otra estrategia',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseCount('consultorio_reservas', 2);
    }
}

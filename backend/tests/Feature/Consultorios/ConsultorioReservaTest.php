<?php

namespace Tests\Feature\Consultorios;

use App\Models\CatalogoConsultorio;
use App\Models\CatalogoCubiculo;
use App\Models\CatalogoEstrategia;
use App\Models\ConsultorioReserva;
use App\Models\ConsultorioReservaSolicitud;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ConsultorioReservaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        CatalogoConsultorio::query()->firstOrCreate([
            'nombre' => 'Consultorio 3',
            'numero' => 3,
            'activo' => true,
        ]);
        CatalogoConsultorio::query()->firstOrCreate([
            'nombre' => 'Consultorio 5',
            'numero' => 5,
            'activo' => true,
        ]);
        CatalogoConsultorio::query()->firstOrCreate([
            'nombre' => 'Consultorio 6',
            'numero' => 6,
            'activo' => true,
        ]);
        CatalogoCubiculo::query()->firstOrCreate([
            'nombre' => 'Cubículo 1',
            'numero' => 1,
            'activo' => true,
        ]);
        CatalogoCubiculo::query()->firstOrCreate([
            'nombre' => 'Cubículo 2',
            'numero' => 2,
            'activo' => true,
        ]);
        CatalogoCubiculo::query()->firstOrCreate([
            'nombre' => 'Cubículo 3',
            'numero' => 3,
            'activo' => true,
        ]);
        CatalogoEstrategia::query()->firstOrCreate([
            'nombre' => 'Intervención breve',
            'activo' => true,
        ]);
        CatalogoEstrategia::query()->firstOrCreate([
            'nombre' => 'Otra estrategia',
            'activo' => true,
        ]);
        CatalogoEstrategia::query()->firstOrCreate([
            'nombre' => 'Terapia individual',
            'activo' => true,
        ]);
    }

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

    public function test_permite_mismo_consultorio_y_cubiculo_en_horario_distinto(): void
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
            'hora_inicio' => '10:00',
            'hora_fin' => '11:00',
            'consultorio_numero' => 3,
            'cubiculo_numero' => 2,
            'estrategia' => 'Otra estrategia',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('consultorios.index'));
        $this->assertDatabaseCount('consultorio_reservas', 2);
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

    public function test_puede_registrar_reserva_desde_peticion_json(): void
    {
        $role = Role::query()->firstOrCreate(['name' => 'paps', 'guard_name' => 'web']);
        $paps = User::factory()->create();
        $paps->assignRole($role);

        $response = $this->actingAs($paps)->postJson(route('consultorios.store'), [
            'modo_repeticion' => 'unica',
            'fecha' => now()->addDay()->toDateString(),
            'hora_inicio' => '12:00',
            'hora_fin' => '13:00',
            'consultorio_numero' => 3,
            'cubiculo_numero' => 1,
            'estrategia' => 'Intervención breve',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Reserva registrada correctamente.');

        $this->assertDatabaseHas('consultorio_reservas', [
            'consultorio_numero' => 3,
            'cubiculo_numero' => 1,
            'hora_inicio' => '12:00',
            'hora_fin' => '13:00',
            'estrategia' => 'Intervención breve',
            'creado_por' => $paps->id,
        ]);
    }

    public function test_consulta_disponibilidad_por_fecha_y_consultorio(): void
    {
        $role = Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole($role);

        $fecha = now()->addDay()->toDateString();

        ConsultorioReserva::query()->create([
            'fecha' => $fecha,
            'hora_inicio' => '10:00',
            'hora_fin' => '11:00',
            'consultorio_numero' => 5,
            'cubiculo_numero' => 3,
            'estrategia' => 'Terapia individual',
            'creado_por' => $admin->id,
        ]);

        ConsultorioReserva::query()->create([
            'fecha' => $fecha,
            'hora_inicio' => '11:00',
            'hora_fin' => '12:00',
            'consultorio_numero' => 6,
            'cubiculo_numero' => 3,
            'estrategia' => 'No debe aparecer',
            'creado_por' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->getJson(route('consultorios.availability', [
            'fecha' => $fecha,
            'consultorio_numero' => 5,
        ]));

        $response
            ->assertOk()
            ->assertJsonPath('consultorio_numero', 5)
            ->assertJsonPath('fecha', $fecha)
            ->assertJsonCount(1, 'reservas')
            ->assertJsonPath('reservas.0.cubiculo_numero', 3)
            ->assertJsonPath('reservas.0.estrategia', 'Terapia individual');
    }




    public function test_index_muestra_usuario_que_captura_accion_realizada_y_fecha_base_actual(): void
    {
        $role = Role::query()->firstOrCreate(['name' => 'paps', 'guard_name' => 'web']);
        $paps = User::factory()->create([
            'name' => 'Usuario PAPS',
            'approved_at' => now(),
        ]);
        $paps->assignRole($role);

        $fecha = now()->toDateString();

        ConsultorioReserva::query()->create([
            'fecha' => $fecha,
            'hora_inicio' => '07:00',
            'hora_fin' => '08:00',
            'consultorio_numero' => 3,
            'cubiculo_numero' => 1,
            'estrategia' => 'Intervención breve',
            'creado_por' => $paps->id,
            'origen_expediente' => true,
        ]);

        $response = $this->actingAs($paps)->get(route('consultorios.index', [
            'fecha' => $fecha,
        ]));

        $response
            ->assertOk()
            ->assertSee('Usuario (capturó)')
            ->assertSee('Usuario PAPS')
            ->assertSee('Alta automática (asignación de cubículo desde expediente)')
            ->assertSee('Solicitar edición')
            ->assertSee('Solicitar baja')
            ->assertSee('id="bitacora-fecha-base" name="bitacora_inicio" value="'.$fecha.'"', false);
    }

    public function test_index_y_disponibilidad_ocultan_reservas_con_solicitud_pendiente(): void
    {
        $role = Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole($role);

        $fecha = now()->toDateString();

        $reservaVisible = ConsultorioReserva::query()->create([
            'fecha' => $fecha,
            'hora_inicio' => '07:00',
            'hora_fin' => '08:00',
            'consultorio_numero' => 3,
            'cubiculo_numero' => 1,
            'estrategia' => 'Intervención breve',
            'creado_por' => $admin->id,
            'origen_expediente' => true,
        ]);

        $reservaOculta = ConsultorioReserva::query()->create([
            'fecha' => $fecha,
            'hora_inicio' => '08:00',
            'hora_fin' => '09:00',
            'consultorio_numero' => 3,
            'cubiculo_numero' => 2,
            'estrategia' => 'Otra estrategia',
            'creado_por' => $admin->id,
            'origen_expediente' => true,
        ]);

        ConsultorioReservaSolicitud::query()->create([
            'consultorio_reserva_id' => $reservaOculta->id,
            'requested_by' => $admin->id,
            'tipo' => 'edicion',
            'payload' => null,
            'status' => 'pendiente',
        ]);

        $this->actingAs($admin)
            ->get(route('consultorios.index', ['fecha' => $fecha]))
            ->assertOk()
            ->assertSee('Intervención breve')
            ->assertDontSee('Otra estrategia');

        $this->actingAs($admin)
            ->getJson(route('consultorios.availability', [
                'fecha' => $fecha,
                'consultorio_numero' => 3,
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'reservas')
            ->assertJsonPath('reservas.0.cubiculo_numero', 1);
    }

    public function test_index_filtra_bitacora_por_semana_desde_fecha_base(): void
    {
        $role = Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole($role);

        ConsultorioReserva::query()->create([
            'fecha' => '2026-03-09',
            'hora_inicio' => '07:00',
            'hora_fin' => '08:00',
            'consultorio_numero' => 3,
            'cubiculo_numero' => 1,
            'estrategia' => 'Intervención lunes',
            'creado_por' => $admin->id,
        ]);

        ConsultorioReserva::query()->create([
            'fecha' => '2026-03-15',
            'hora_inicio' => '08:00',
            'hora_fin' => '09:00',
            'consultorio_numero' => 3,
            'cubiculo_numero' => 2,
            'estrategia' => 'Intervención domingo',
            'creado_por' => $admin->id,
        ]);

        ConsultorioReserva::query()->create([
            'fecha' => '2026-03-20',
            'hora_inicio' => '07:00',
            'hora_fin' => '08:00',
            'consultorio_numero' => 3,
            'cubiculo_numero' => 1,
            'estrategia' => 'Fuera de semana',
            'creado_por' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get(route('consultorios.index', [
            'fecha' => '2026-03-13',
            'bitacora_inicio' => '2026-03-13',
            'bitacora_modo' => 'semana',
        ]));

        $response
            ->assertOk()
            ->assertSee('2026-03-09')
            ->assertSee('2026-03-15')
            ->assertDontSee('2026-03-20');
    }

    public function test_index_filtra_bitacora_por_mes_desde_fecha_base(): void
    {
        $role = Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole($role);

        ConsultorioReserva::query()->create([
            'fecha' => '2026-03-01',
            'hora_inicio' => '07:00',
            'hora_fin' => '08:00',
            'consultorio_numero' => 3,
            'cubiculo_numero' => 1,
            'estrategia' => 'Inicio de mes',
            'creado_por' => $admin->id,
        ]);

        ConsultorioReserva::query()->create([
            'fecha' => '2026-03-31',
            'hora_inicio' => '08:00',
            'hora_fin' => '09:00',
            'consultorio_numero' => 3,
            'cubiculo_numero' => 2,
            'estrategia' => 'Fin de mes',
            'creado_por' => $admin->id,
        ]);

        ConsultorioReserva::query()->create([
            'fecha' => '2026-04-01',
            'hora_inicio' => '09:00',
            'hora_fin' => '10:00',
            'consultorio_numero' => 3,
            'cubiculo_numero' => 3,
            'estrategia' => 'Fuera de mes',
            'creado_por' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get(route('consultorios.index', [
            'fecha' => '2026-03-13',
            'bitacora_inicio' => '2026-03-13',
            'bitacora_modo' => 'mes',
        ]));

        $response
            ->assertOk()
            ->assertSee('2026-03-01')
            ->assertSee('2026-03-31')
            ->assertDontSee('2026-04-01');
    }

    public function test_permite_repeticion_semanal_con_selector_de_dia_habil(): void
    {
        $role = Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole($role);

        $inicio = now()->addWeek()->startOfWeek()->toDateString(); // lunes
        $fin = now()->addWeeks(4)->endOfWeek()->toDateString();

        $response = $this->actingAs($admin)->post(route('consultorios.store'), [
            'modo_repeticion' => 'semanal',
            'fecha_inicio_repeticion' => $inicio,
            'fecha_fin_repeticion' => $fin,
            'dias_semana' => ['2'],
            'hora_inicio' => '09:00',
            'hora_fin' => '10:00',
            'consultorio_numero' => 3,
            'cubiculo_numero' => 1,
            'estrategia' => 'Intervención breve',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertGreaterThan(1, ConsultorioReserva::query()->count());
        $this->assertTrue(ConsultorioReserva::query()->get()->every(fn (ConsultorioReserva $reserva) => $reserva->fecha->dayOfWeekIso === 2));
    }

    public function test_repeticion_semanal_ignora_valores_vacios_en_dias_semana(): void
    {
        $role = Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole($role);

        $inicio = now()->addWeek()->startOfWeek()->addDay()->toDateString(); // martes
        $fin = now()->addWeeks(2)->endOfWeek()->toDateString();

        $response = $this->actingAs($admin)->post(route('consultorios.store'), [
            'modo_repeticion' => 'semanal',
            'fecha_inicio_repeticion' => $inicio,
            'fecha_fin_repeticion' => $fin,
            'dias_semana' => [''],
            'hora_inicio' => '11:00',
            'hora_fin' => '12:00',
            'consultorio_numero' => 5,
            'cubiculo_numero' => 2,
            'estrategia' => 'Otra estrategia',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertGreaterThan(0, ConsultorioReserva::query()->count());
    }

    public function test_crea_reservas_masivas_con_repeticion_semanal_sin_seleccionar_dias(): void
    {
        $role = Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole($role);

        $inicio = now()->addWeek()->startOfWeek()->addDay()->toDateString(); // martes
        $fin = now()->addWeek()->addWeeks(2)->endOfWeek()->subDay()->toDateString();

        $response = $this->actingAs($admin)->post(route('consultorios.store'), [
            'modo_repeticion' => 'semanal',
            'fecha_inicio_repeticion' => $inicio,
            'fecha_fin_repeticion' => $fin,
            'hora_inicio' => '09:00',
            'hora_fin' => '10:00',
            'consultorio_numero' => 3,
            'cubiculo_numero' => 2,
            'estrategia' => 'Intervención breve',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('consultorios.index'));
        $this->assertGreaterThan(1, ConsultorioReserva::query()->count());
    }

    public function test_repeticion_semanal_autocompleta_fechas_cuando_solo_se_envia_fecha_base(): void
    {
        $role = Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole($role);

        $fechaBase = now()->addWeek()->startOfWeek()->toDateString(); // lunes

        $response = $this->actingAs($admin)->post(route('consultorios.store'), [
            'modo_repeticion' => 'semanal',
            'fecha' => $fechaBase,
            'hora_inicio' => '09:00',
            'hora_fin' => '10:00',
            'consultorio_numero' => 3,
            'cubiculo_numero' => 2,
            'estrategia' => 'Intervención breve',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('consultorios.index'));
        $this->assertGreaterThan(1, ConsultorioReserva::query()->count());
    }


    public function test_elimina_registros_seleccionados_en_bitacora(): void
    {
        $role = Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole($role);

        $primera = ConsultorioReserva::query()->create([
            'fecha' => now()->addDay()->toDateString(),
            'hora_inicio' => '09:00',
            'hora_fin' => '10:00',
            'consultorio_numero' => 3,
            'cubiculo_numero' => 1,
            'estrategia' => 'Intervención breve',
            'creado_por' => $admin->id,
        ]);

        $segunda = ConsultorioReserva::query()->create([
            'fecha' => now()->addDays(2)->toDateString(),
            'hora_inicio' => '10:00',
            'hora_fin' => '11:00',
            'consultorio_numero' => 3,
            'cubiculo_numero' => 2,
            'estrategia' => 'Otra estrategia',
            'creado_por' => $admin->id,
        ]);

        $tercera = ConsultorioReserva::query()->create([
            'fecha' => now()->addDays(3)->toDateString(),
            'hora_inicio' => '11:00',
            'hora_fin' => '12:00',
            'consultorio_numero' => 5,
            'cubiculo_numero' => 1,
            'estrategia' => 'Terapia individual',
            'creado_por' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->delete(route('consultorios.bulk-destroy'), [
            'reservas' => [$primera->id, $tercera->id],
        ]);

        $response->assertRedirect(route('consultorios.index'));
        $response->assertSessionHas('status', 'Reservas eliminadas correctamente.');

        $this->assertDatabaseMissing('consultorio_reservas', ['id' => $primera->id]);
        $this->assertDatabaseHas('consultorio_reservas', ['id' => $segunda->id]);
        $this->assertDatabaseMissing('consultorio_reservas', ['id' => $tercera->id]);
    }

    public function test_bulk_destroy_requiere_seleccion_de_reservas(): void
    {
        $role = Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole($role);

        ConsultorioReserva::query()->create([
            'fecha' => now()->addDay()->toDateString(),
            'hora_inicio' => '09:00',
            'hora_fin' => '10:00',
            'consultorio_numero' => 3,
            'cubiculo_numero' => 1,
            'estrategia' => 'Intervención breve',
            'creado_por' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->delete(route('consultorios.bulk-destroy'), [
            'reservas' => [],
        ]);

        $response->assertRedirect(route('consultorios.index'));
        $response->assertSessionHas('status', 'Selecciona al menos un registro para eliminar.');
        $this->assertDatabaseCount('consultorio_reservas', 1);
    }

    public function test_permite_recrear_bloque_tras_baja_individual(): void
    {
        $role = Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole($role);

        $payload = [
            'fecha' => now()->addDays(5)->toDateString(),
            'hora_inicio' => '07:00',
            'hora_fin' => '08:00',
            'consultorio_numero' => 3,
            'cubiculo_numero' => 1,
            'estrategia' => 'Intervención breve',
        ];

        $this->actingAs($admin)->post(route('consultorios.store'), $payload)->assertSessionHasNoErrors();

        $reserva = ConsultorioReserva::query()->firstOrFail();
        $this->actingAs($admin)->delete(route('consultorios.destroy', $reserva))->assertRedirect(route('consultorios.index'));
        $this->assertDatabaseMissing('consultorio_reservas', ['id' => $reserva->id]);

        $this->actingAs($admin)->post(route('consultorios.store'), $payload)
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('consultorios.index'));
    }

    public function test_permite_recrear_repeticion_semanal_tras_baja_seleccionada(): void
    {
        $role = Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole($role);

        $inicio = now()->addWeek()->startOfWeek()->toDateString();
        $fin = now()->addWeeks(2)->endOfWeek()->toDateString();
        $payload = [
            'modo_repeticion' => 'semanal',
            'fecha_inicio_repeticion' => $inicio,
            'fecha_fin_repeticion' => $fin,
            'dias_semana' => ['5'],
            'hora_inicio' => '11:00',
            'hora_fin' => '12:00',
            'consultorio_numero' => 5,
            'cubiculo_numero' => 2,
            'estrategia' => 'Otra estrategia',
        ];

        $this->actingAs($admin)->post(route('consultorios.store'), $payload)->assertSessionHasNoErrors();

        $ids = ConsultorioReserva::query()->pluck('id')->all();
        $this->actingAs($admin)->delete(route('consultorios.bulk-destroy'), [
            'reservas' => $ids,
        ])->assertRedirect(route('consultorios.index'));

        foreach ($ids as $id) {
            $this->assertDatabaseMissing('consultorio_reservas', ['id' => $id]);
        }

        $this->actingAs($admin)->post(route('consultorios.store'), $payload)
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('consultorios.index'));
    }

    public function test_admin_puede_editar_y_eliminar_reserva_creada_desde_expediente(): void
    {
        $adminRole = Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole($adminRole);

        $reserva = ConsultorioReserva::query()->create([
            'fecha' => now()->addDay()->toDateString(),
            'hora_inicio' => '09:00',
            'hora_fin' => '10:00',
            'consultorio_numero' => 3,
            'cubiculo_numero' => 1,
            'estrategia' => 'Intervención breve',
            'creado_por' => $admin->id,
            'origen_expediente' => true,
        ]);

        $this->actingAs($admin)->put(route('consultorios.update', $reserva), [
            'fecha' => now()->addDays(2)->toDateString(),
            'hora_inicio' => '10:00',
            'hora_fin' => '11:00',
            'consultorio_numero' => 5,
            'cubiculo_numero' => 2,
            'estrategia' => 'Otra estrategia',
        ])->assertRedirect(route('consultorios.index'));

        $this->assertDatabaseHas('consultorio_reservas', [
            'id' => $reserva->id,
            'consultorio_numero' => 5,
            'cubiculo_numero' => 2,
            'estrategia' => 'Otra estrategia',
            'origen_expediente' => true,
        ]);

        $this->actingAs($admin)->delete(route('consultorios.destroy', $reserva))
            ->assertRedirect(route('consultorios.index'));

        $this->assertDatabaseMissing('consultorio_reservas', ['id' => $reserva->id]);
    }

    public function test_paps_solo_puede_solicitar_cambios_para_reserva_creada_desde_expediente(): void
    {
        $adminRole = Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $papsRole = Role::query()->firstOrCreate(['name' => 'paps', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole($adminRole);
        $paps = User::factory()->create(['approved_at' => now()]);
        $paps->assignRole($papsRole);

        $reserva = ConsultorioReserva::query()->create([
            'fecha' => now()->addDay()->toDateString(),
            'hora_inicio' => '09:00',
            'hora_fin' => '10:00',
            'consultorio_numero' => 3,
            'cubiculo_numero' => 1,
            'estrategia' => 'Intervención breve',
            'creado_por' => $admin->id,
            'origen_expediente' => true,
        ]);

        $this->actingAs($paps)->get(route('consultorios.edit', $reserva))->assertForbidden();
        $this->actingAs($paps)->delete(route('consultorios.request-destroy', $reserva))
            ->assertRedirect(route('consultorios.index'));
        $this->actingAs($paps)->put(route('consultorios.request-update', $reserva), [
            'fecha' => now()->addDays(3)->toDateString(),
            'hora_inicio' => '11:00',
            'hora_fin' => '12:00',
            'consultorio_numero' => 3,
            'cubiculo_numero' => 1,
            'estrategia' => 'Intervención breve',
        ])->assertRedirect(route('consultorios.index'));

        $this->assertDatabaseHas('consultorio_reserva_solicitudes', [
            'consultorio_reserva_id' => $reserva->id,
            'requested_by' => $paps->id,
            'tipo' => 'baja',
            'status' => 'pendiente',
        ]);

        $this->assertDatabaseHas('consultorio_reserva_solicitudes', [
            'consultorio_reserva_id' => $reserva->id,
            'requested_by' => $paps->id,
            'tipo' => 'edicion',
            'status' => 'pendiente',
        ]);
    }

    public function test_paps_puede_solicitar_edicion_desde_bitacora_sin_salir_de_la_pagina(): void
    {
        $adminRole = Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $papsRole = Role::query()->firstOrCreate(['name' => 'paps', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole($adminRole);
        $paps = User::factory()->create(['approved_at' => now()]);
        $paps->assignRole($papsRole);

        $reserva = ConsultorioReserva::query()->create([
            'fecha' => now()->addDay()->toDateString(),
            'hora_inicio' => '10:00',
            'hora_fin' => '11:00',
            'consultorio_numero' => 3,
            'cubiculo_numero' => 1,
            'estrategia' => 'Intervención breve',
            'creado_por' => $admin->id,
            'origen_expediente' => false,
        ]);

        $this->actingAs($paps)
            ->post(route('consultorios.request-edit', $reserva))
            ->assertRedirect(route('consultorios.index'))
            ->assertSessionHas('status', 'Solicitud enviada al administrador.');

        $this->assertDatabaseHas('consultorio_reserva_solicitudes', [
            'consultorio_reserva_id' => $reserva->id,
            'requested_by' => $paps->id,
            'tipo' => 'edicion',
            'status' => 'pendiente',
        ]);
    }

    public function test_admin_puede_aprobar_solicitud_de_baja_de_consultorio(): void
    {
        $adminRole = Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $papsRole = Role::query()->firstOrCreate(['name' => 'paps', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole($adminRole);
        $paps = User::factory()->create(['approved_at' => now()]);
        $paps->assignRole($papsRole);

        $reserva = ConsultorioReserva::query()->create([
            'fecha' => now()->addDay()->toDateString(),
            'hora_inicio' => '10:00',
            'hora_fin' => '11:00',
            'consultorio_numero' => 3,
            'cubiculo_numero' => 1,
            'estrategia' => 'Intervención breve',
            'creado_por' => $admin->id,
            'origen_expediente' => false,
        ]);

        $this->actingAs($paps)
            ->delete(route('consultorios.request-destroy', $reserva))
            ->assertRedirect(route('consultorios.index'));

        $solicitud = \App\Models\ConsultorioReservaSolicitud::query()->where('consultorio_reserva_id', $reserva->id)->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.consultorios.solicitudes.approve', $solicitud))
            ->assertRedirect(route('admin.consultorios.solicitudes.index'));

        $this->assertDatabaseMissing('consultorio_reservas', ['id' => $reserva->id]);
        $this->assertDatabaseHas('consultorio_reserva_solicitudes', [
            'id' => $solicitud->id,
            'status' => 'atendida',
        ]);
    }

    public function test_paps_puede_ver_solicitudes_pendientes_pero_no_puede_aprobarlas(): void
    {
        $adminRole = Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $papsRole = Role::query()->firstOrCreate(['name' => 'paps', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole($adminRole);
        $paps = User::factory()->create(['approved_at' => now()]);
        $paps->assignRole($papsRole);

        $reserva = ConsultorioReserva::query()->create([
            'fecha' => now()->addDay()->toDateString(),
            'hora_inicio' => '10:00',
            'hora_fin' => '11:00',
            'consultorio_numero' => 3,
            'cubiculo_numero' => 1,
            'estrategia' => 'Intervención breve',
            'creado_por' => $admin->id,
            'origen_expediente' => false,
        ]);

        $solicitud = ConsultorioReservaSolicitud::query()->create([
            'consultorio_reserva_id' => $reserva->id,
            'requested_by' => $paps->id,
            'tipo' => 'edicion',
            'status' => 'pendiente',
        ]);

        $this->actingAs($paps)
            ->get(route('admin.consultorios.solicitudes.index'))
            ->assertOk()
            ->assertSee('Solicitudes pendientes de consultorios')
            ->assertDontSee('Aprobar');

        $this->actingAs($paps)
            ->post(route('admin.consultorios.solicitudes.approve', $solicitud))
            ->assertForbidden();
    }

    public function test_paps_sin_aprobacion_no_puede_ver_solicitudes_pendientes_de_consultorios(): void
    {
        $papsRole = Role::query()->firstOrCreate(['name' => 'paps', 'guard_name' => 'web']);
        $paps = User::factory()->create(['approved_at' => null]);
        $paps->assignRole($papsRole);

        $this->actingAs($paps)
            ->get(route('admin.consultorios.solicitudes.index'))
            ->assertForbidden();
    }


}

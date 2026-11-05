<?php

namespace Tests\Feature\Expedientes;

use App\Models\Consentimiento;
use App\Models\Expediente;
use App\Models\Sesion;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ExpedienteLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RoleSeeder::class);
        Carbon::setTestNow('2026-11-04 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_explicit_archive_and_restore_preserve_the_record_children_and_ten_year_hold(): void
    {
        $admin = $this->user('admin');
        $expediente = Expediente::factory()->create([
            'no_control' => 'CA-2026-0417',
            'creado_por' => $admin->id,
        ]);
        $consentimiento = Consentimiento::factory()->for($expediente)->create();
        $sesion = Sesion::factory()->create([
            'expediente_id' => $expediente->id,
            'realizada_por' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->delete(route('expedientes.destroy', $expediente), [
                'lifecycle_action' => 'archive',
                'motivo' => 'Cierre operativo con conservación obligatoria.',
                'folio_confirmation' => $expediente->no_control,
            ])
            ->assertRedirect(route('expedientes.index'))
            ->assertSessionHas('status');

        $archived = Expediente::withTrashed()->findOrFail($expediente->id);
        $this->assertTrue($archived->trashed());
        $this->assertSame($admin->id, $archived->archived_by);
        $this->assertTrue($archived->deleted_at->equalTo(now()));
        $this->assertTrue($archived->retention_until->equalTo(now()->addYearsNoOverflow(10)));
        $this->assertDatabaseHas('consentimientos', ['id' => $consentimiento->id]);
        $this->assertDatabaseHas('sesiones', ['id' => $sesion->id]);
        $this->assertDatabaseHas('expediente_lifecycle_audits', [
            'expediente_id' => $expediente->id,
            'actor_id' => $admin->id,
            'action' => 'archive',
            'folio' => $expediente->no_control,
            'motivo' => 'Cierre operativo con conservación obligatoria.',
        ]);

        $this->get(route('expedientes.index'))
            ->assertOk()
            ->assertDontSeeText($expediente->no_control);
        $this->get(route('expedientes.archived'))
            ->assertOk()
            ->assertSeeText('Expedientes archivados')
            ->assertSeeText($expediente->no_control);
        $this->get(route('expedientes.show', $archived))
            ->assertOk()
            ->assertSeeText('Expediente archivado · solo lectura.');

        $retentionUntil = $archived->retention_until->copy();
        $archivedBy = $archived->archived_by;
        $this->post(route('expedientes.restore', $archived), [
            'lifecycle_action' => 'restore',
            'motivo' => 'Reapertura operativa autorizada del expediente.',
            'folio_confirmation' => $archived->no_control,
        ])
            ->assertRedirect(route('expedientes.show', $archived))
            ->assertSessionHas('status');

        $restored = Expediente::findOrFail($expediente->id);
        $this->assertFalse($restored->trashed());
        $this->assertSame($archivedBy, $restored->archived_by);
        $this->assertTrue($restored->retention_until->equalTo($retentionUntil));
        $this->assertDatabaseHas('expediente_lifecycle_audits', [
            'expediente_id' => $expediente->id,
            'actor_id' => $admin->id,
            'action' => 'restore',
            'motivo' => 'Reapertura operativa autorizada del expediente.',
        ]);
        $this->get(route('expedientes.archived'))
            ->assertOk()
            ->assertDontSeeText($expediente->no_control);
        $this->get(route('expedientes.show', $restored))
            ->assertOk()
            ->assertSeeText('Este expediente fue restaurado y continúa protegido')
            ->assertSeeText('Eliminación definitiva no disponible')
            ->assertDontSee(route('expedientes.force-destroy', $restored), false);
    }

    public function test_exact_folio_is_required_and_an_active_error_record_can_be_purged_with_minimal_audit(): void
    {
        $admin = $this->user('admin');
        $expediente = Expediente::factory()->create([
            'no_control' => 'CA-2026-0418',
            'creado_por' => $admin->id,
        ]);

        $payload = [
            'lifecycle_action' => 'purge_error',
            'motivo' => 'Registro duplicado durante una prueba de captura.',
            'folio_confirmation' => mb_strtolower($expediente->no_control),
        ];

        $this->actingAs($admin)
            ->delete(route('expedientes.force-destroy', $expediente), $payload)
            ->assertSessionHasErrors('folio_confirmation');

        $this->assertDatabaseHas('expedientes', ['id' => $expediente->id]);
        $this->assertDatabaseMissing('expediente_lifecycle_audits', [
            'expediente_id' => $expediente->id,
            'action' => 'purge_error',
        ]);

        $payload['folio_confirmation'] = $expediente->no_control;
        $this->delete(route('expedientes.force-destroy', $expediente), $payload)
            ->assertRedirect(route('expedientes.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseMissing('expedientes', ['id' => $expediente->id]);
        $this->assertDatabaseHas('expediente_lifecycle_audits', [
            'expediente_id' => $expediente->id,
            'actor_id' => $admin->id,
            'folio' => $expediente->no_control,
            'action' => 'purge_error',
            'motivo' => $payload['motivo'],
        ]);
    }

    public function test_restored_hold_blocks_purge_in_the_service_and_in_the_interface(): void
    {
        $admin = $this->user('admin');
        $expediente = Expediente::factory()->create([
            'no_control' => 'CA-2026-0419',
            'creado_por' => $admin->id,
        ]);

        $this->actingAs($admin)->delete(route('expedientes.destroy', $expediente), [
            'lifecycle_action' => 'archive',
            'motivo' => 'Archivo preventivo sujeto a conservación obligatoria.',
            'folio_confirmation' => $expediente->no_control,
        ]);
        $archived = Expediente::withTrashed()->findOrFail($expediente->id);
        $this->post(route('expedientes.restore', $archived), [
            'lifecycle_action' => 'restore',
            'motivo' => 'Reapertura autorizada para continuar el seguimiento.',
            'folio_confirmation' => $archived->no_control,
        ]);
        $restored = Expediente::findOrFail($expediente->id);

        $this->delete(route('expedientes.force-destroy', $restored), [
            'lifecycle_action' => 'purge_error',
            'motivo' => 'Intento sintético que debe rechazar la retención.',
            'folio_confirmation' => $restored->no_control,
        ])->assertSessionHasErrors('lifecycle_action');

        $this->assertDatabaseHas('expedientes', [
            'id' => $restored->id,
            'archived_by' => $admin->id,
            'deleted_at' => null,
        ]);
        $this->assertDatabaseMissing('expediente_lifecycle_audits', [
            'expediente_id' => $restored->id,
            'action' => 'purge_error',
        ]);
    }

    public function test_archived_listing_is_limited_to_admin_and_approved_paps(): void
    {
        $admin = $this->user('admin');
        $approvedPaps = $this->user('paps', approved: true);
        $unapprovedPaps = $this->user('paps', approved: false);
        $facilitator = $this->user('alumno');
        $expediente = Expediente::factory()->create([
            'no_control' => 'CA-2026-0420',
            'creado_por' => $admin->id,
        ]);

        $this->actingAs($admin)->delete(route('expedientes.destroy', $expediente));

        foreach ([$admin, $approvedPaps] as $authorized) {
            $this->actingAs($authorized)
                ->get(route('expedientes.archived'))
                ->assertOk()
                ->assertSeeText($expediente->no_control);
        }

        foreach ([$unapprovedPaps, $facilitator] as $unauthorized) {
            $this->actingAs($unauthorized)
                ->get(route('expedientes.archived'))
                ->assertForbidden();
        }
    }

    private function user(string $role, bool $approved = true): User
    {
        $user = User::factory()->create([
            'approved_at' => $approved ? now() : null,
            'is_active' => true,
        ]);
        $user->assignRole($role);

        return $user;
    }
}

<?php

namespace Tests\Feature\Reportes;

use App\Models\CatalogoEstrategia;
use App\Models\Expediente;
use App\Models\TimelineEvento;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ExpedienteReportAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RoleSeeder::class);
        Storage::fake('local');
    }

    public static function nonAdminProfiles(): array
    {
        return [
            'coordinador' => [['coordinador']],
            'estratega' => [['estratega']],
            'docente' => [['docente']],
            'facilitador' => [['alumno']],
            'PAPS aprobado' => [['paps']],
            'roles mixtos con permiso directo' => [['coordinador', 'estratega', 'paps'], true],
        ];
    }

    #[DataProvider('nonAdminProfiles')]
    public function test_authorized_readers_cannot_open_reports_or_use_old_export_links(array $roles, bool $directAbility = false): void
    {
        $user = User::factory()->create(['is_active' => true, 'approved_at' => now()]);
        $user->assignRole($roles);
        $user->givePermissionTo('reportes.view');
        if ($directAbility) {
            $user->givePermissionTo(Permission::findOrCreate('exportReport', 'web'));
        }

        $expediente = $this->assignedExpediente($user);
        [$reportToken, $timelineToken] = $this->readyExports($user, $expediente);

        $this->actingAs($user)->get(route('expedientes.show', $expediente))->assertOk()
            ->assertSeeText($expediente->no_control)
            ->assertDontSee('data-timeline-export-url="', false);
        $this->get(route('expedientes.index'))->assertOk()
            ->assertSeeText($expediente->no_control)
            ->assertDontSee('href="'.route('reportes.index').'"', false)
            ->assertDontSee(route('expedientes.export.ficha-identificacion'), false);

        $this->get(route('reportes.index'))->assertForbidden();
        $this->get(route('reportes.expedientes.download-direct', ['format' => 'csv']))->assertForbidden();
        $this->get(route('expedientes.export.ficha-identificacion'))->assertForbidden();
        $this->postJson(route('reportes.expedientes.export'), ['format' => 'xlsx'])->assertForbidden();
        $this->postJson(route('expedientes.timeline.export', $expediente), ['format' => 'xlsx'])->assertForbidden();
        $this->assertExportLinksDenied($expediente, $reportToken, $timelineToken);
    }

    public function test_admin_sees_report_controls_and_can_download_csv_xlsx_and_ficha(): void
    {
        $this->freezeTime();
        Excel::fake();

        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole(['admin', 'estratega']);
        $expediente = $this->assignedExpediente($admin);

        $this->actingAs($admin)->get(route('expedientes.index'))->assertOk()
            ->assertSee('href="'.route('reportes.index').'"', false)
            ->assertSee(route('expedientes.export.ficha-identificacion'), false);
        $this->get(route('expedientes.show', $expediente))->assertOk()
            ->assertSee('data-timeline-export-url="'.route('expedientes.timeline.export', $expediente).'"', false);
        $this->get(route('reportes.index'))->assertOk()->assertSeeText($expediente->no_control);

        $stamp = now()->format('Ymd_His');
        foreach (['csv', 'xlsx'] as $format) {
            $this->get(route('reportes.expedientes.download-direct', ['format' => $format]))->assertOk();
            Excel::assertDownloaded("reporte_expedientes_{$stamp}.{$format}");
        }
        $this->get(route('expedientes.export.ficha-identificacion'))->assertOk();
        Excel::assertDownloaded("expedientes_ficha_identificacion_{$stamp}.xlsx");
    }

    public function test_removing_admin_role_blocks_existing_links_while_case_remains_visible(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(['admin', 'estratega']);
        $expediente = $this->assignedExpediente($user);
        [$reportToken, $timelineToken] = $this->readyExports($user, $expediente);

        $this->actingAs($user)->getJson(route('reportes.expedientes.export.status', $reportToken))->assertOk();
        $this->getJson(route('expedientes.timeline.export.status', [$expediente, $timelineToken]))->assertOk();

        $user->removeRole('admin');
        $this->actingAs($user->fresh())->get(route('expedientes.show', $expediente))->assertOk()
            ->assertDontSee('data-timeline-export-url="', false);
        $this->assertExportLinksDenied($expediente, $reportToken, $timelineToken);
    }

    private function assignedExpediente(User $user): Expediente
    {
        $estrategia = CatalogoEstrategia::create(['nombre' => 'Estrategia sintética de reportes', 'activo' => true]);
        $user->estrategiasParticipadas()->attach($estrategia);
        $user->estrategiasCoordinadas()->attach($estrategia);

        return Expediente::factory()->create([
            'estado' => 'abierto',
            'creado_por' => $user->id,
            'facilitador_id' => $user->id,
            'tutor_id' => $user->id,
            'coordinador_id' => $user->id,
            'estrategia_id' => $estrategia->id,
        ]);
    }

    private function readyExports(User $user, Expediente $expediente): array
    {
        $reportToken = (string) Str::uuid();
        $timelineToken = (string) Str::uuid();
        $event = TimelineEvento::factory()->create([
            'expediente_id' => $expediente->id,
            'actor_id' => $user->id,
            'evento' => 'expediente.actualizado',
            'payload' => ['detalle' => 'Evento sintético para autorización de reporte'],
        ]);

        foreach ([
            [$reportToken, 'expedientes', ['expediente_ids' => [$expediente->id]]],
            [$timelineToken, 'timeline', ['expediente_id' => $expediente->id, 'event_ids' => [$event->id]]],
        ] as [$token, $prefix, $ids]) {
            $path = "exports/{$prefix}_{$token}.xlsx";
            Storage::disk('local')->put($path, 'Reporte sintético ya generado');
            Cache::put($token, [
                'status' => 'ready',
                'path' => $path,
                'filename' => 'reporte.xlsx',
                'user_id' => $user->id,
                ...$ids,
            ], now()->addMinutes(10));
        }

        return [$reportToken, $timelineToken];
    }

    private function assertExportLinksDenied(Expediente $expediente, string $reportToken, string $timelineToken): void
    {
        $this->getJson(route('reportes.expedientes.export.status', $reportToken))->assertForbidden();
        $this->get(route('reportes.expedientes.download', $reportToken))->assertForbidden();
        $this->getJson(route('expedientes.timeline.export.status', [$expediente, $timelineToken]))->assertForbidden();
        $this->get(route('expedientes.timeline.export.download', [$expediente, $timelineToken]))->assertForbidden();
    }
}

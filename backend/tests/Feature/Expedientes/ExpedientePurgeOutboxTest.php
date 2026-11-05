<?php

namespace Tests\Feature\Expedientes;

use App\Models\Consentimiento;
use App\Models\Expediente;
use App\Models\ExpedienteFileDeletion;
use App\Models\User;
use App\Services\ExpedienteLifecycleService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Mockery;
use RuntimeException;
use Tests\TestCase;
use Throwable;

class ExpedientePurgeOutboxTest extends TestCase
{
    use DatabaseMigrations;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();

        config(['filesystems.private_default' => 'private']);
        Storage::fake('private');
        Storage::fake('public');
        $this->actor = User::factory()->create([
            'name' => 'Administrador sintético de purga',
            'is_active' => true,
        ]);
    }

    public function test_outer_transaction_rollback_preserves_database_outbox_audit_and_file_atomically(): void
    {
        [$expediente, $consentimiento, $path] = $this->clinicalFileFixture();

        DB::beginTransaction();

        try {
            app(ExpedienteLifecycleService::class)->purgeForError(
                $expediente,
                $this->actor,
                'Registro sintético que será revertido por la transacción exterior.',
            );

            $this->assertDatabaseMissing('expedientes', ['id' => $expediente->id]);
            $this->assertDatabaseHas('expediente_lifecycle_audits', [
                'expediente_id' => $expediente->id,
                'action' => 'purge_error',
            ]);
            $this->assertDatabaseHas('expediente_file_deletions', [
                'expediente_id' => $expediente->id,
                'status' => ExpedienteFileDeletion::STATUS_PENDING,
            ]);
            Storage::disk('private')->assertExists($path);

            throw new RuntimeException('Rollback exterior sintético.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Rollback exterior sintético.', $exception->getMessage());
            DB::rollBack();
        }

        $this->assertDatabaseHas('expedientes', [
            'id' => $expediente->id,
            'deleted_at' => null,
        ]);
        $this->assertDatabaseHas('consentimientos', [
            'id' => $consentimiento->id,
            'archivo_path' => $path,
        ]);
        $this->assertDatabaseCount('expediente_lifecycle_audits', 0);
        $this->assertDatabaseCount('expediente_file_deletions', 0);
        Storage::disk('private')->assertExists($path);
        $this->assertSame($this->pdf(), Storage::disk('private')->get($path));
    }

    public function test_active_error_purge_deletes_database_before_file_and_processes_file_only_after_real_commit(): void
    {
        [$expediente, $consentimiento, $path] = $this->clinicalFileFixture();

        DB::beginTransaction();

        try {
            $result = app(ExpedienteLifecycleService::class)->purgeForError(
                $expediente,
                $this->actor,
                'Registro activo creado por error que sí debe purgarse.',
            );

            $this->assertNull($expediente->retention_until);
            $this->assertDatabaseMissing('expedientes', ['id' => $expediente->id]);
            $this->assertDatabaseMissing('consentimientos', ['id' => $consentimiento->id]);
            $this->assertDatabaseHas('expediente_lifecycle_audits', [
                'id' => $result['audit_id'],
                'expediente_id' => $expediente->id,
                'action' => 'purge_error',
            ]);
            $this->assertDatabaseHas('expediente_file_deletions', [
                'audit_id' => $result['audit_id'],
                'path' => $path,
                'status' => ExpedienteFileDeletion::STATUS_PENDING,
            ]);
            Storage::disk('private')->assertExists($path);

            DB::commit();
        } catch (Throwable $exception) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            throw $exception;
        }

        $this->assertNull(Expediente::withTrashed()->find($expediente->id));
        Storage::disk('private')->assertMissing($path);
        $this->assertDatabaseHas('expediente_file_deletions', [
            'audit_id' => $result['audit_id'],
            'path' => $path,
            'status' => ExpedienteFileDeletion::STATUS_DELETED,
        ]);
    }

    public function test_shared_physical_path_is_retained_when_another_expediente_still_references_it(): void
    {
        [$firstExpediente, $firstConsentimiento, $sharedPath] = $this->clinicalFileFixture(
            'expedientes/compartidos/consentimiento.pdf',
        );
        $secondExpediente = $this->expediente();
        $secondConsentimiento = $this->consentimiento($secondExpediente, $sharedPath);

        $result = app(ExpedienteLifecycleService::class)->purgeForError(
            $firstExpediente,
            $this->actor,
            'Primer registro erróneo con una referencia física compartida.',
        );

        $this->assertDatabaseMissing('expedientes', ['id' => $firstExpediente->id]);
        $this->assertDatabaseMissing('consentimientos', ['id' => $firstConsentimiento->id]);
        $this->assertDatabaseHas('expedientes', ['id' => $secondExpediente->id]);
        $this->assertDatabaseHas('consentimientos', [
            'id' => $secondConsentimiento->id,
            'archivo_path' => $sharedPath,
        ]);
        Storage::disk('private')->assertExists($sharedPath);
        $this->assertSame($this->pdf(), Storage::disk('private')->get($sharedPath));
        $this->assertDatabaseHas('expediente_file_deletions', [
            'audit_id' => $result['audit_id'],
            'path' => $sharedPath,
            'status' => ExpedienteFileDeletion::STATUS_RETAINED_SHARED,
        ]);
    }

    public function test_unsafe_path_aborts_before_database_audit_outbox_or_files_are_deleted(): void
    {
        $expediente = $this->expediente();
        $consentimiento = $this->consentimiento($expediente, '../fuera-del-disco.pdf');
        Storage::disk('private')->put('marcadores/intacto.pdf', $this->pdf());

        try {
            app(ExpedienteLifecycleService::class)->purgeForError(
                $expediente,
                $this->actor,
                'Registro sintético con metadato inseguro que debe rechazarse.',
            );

            $this->fail('La purga aceptó una referencia de archivo insegura.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('lifecycle_action', $exception->errors());
        }

        $this->assertDatabaseHas('expedientes', [
            'id' => $expediente->id,
            'deleted_at' => null,
        ]);
        $this->assertDatabaseHas('consentimientos', [
            'id' => $consentimiento->id,
            'archivo_path' => '../fuera-del-disco.pdf',
        ]);
        $this->assertDatabaseCount('expediente_lifecycle_audits', 0);
        $this->assertDatabaseCount('expediente_file_deletions', 0);
        Storage::disk('private')->assertExists('marcadores/intacto.pdf');
    }

    public function test_failed_deletion_is_retried_idempotently_without_scanning_archived_files(): void
    {
        [$expediente, $consentimiento, $path] = $this->clinicalFileFixture();
        [$archivedExpediente, $archivedConsentimiento, $archivedPath] = $this->clinicalFileFixture(
            'expedientes/archivado-sin-outbox/consentimiento.pdf',
        );
        $untrackedPath = 'marcadores/sin-outbox.pdf';
        Storage::disk('private')->put($untrackedPath, $this->pdf());
        app(ExpedienteLifecycleService::class)->archive(
            $archivedExpediente,
            $this->actor,
            'Archivo sintético que no debe ser explorado por el reintento.',
        );

        $workingDisk = Storage::disk('private');
        $brokenDisk = Mockery::mock($workingDisk);
        $brokenDisk->shouldReceive('delete')->once()->with($path)->andReturn(false);
        Storage::set('private', $brokenDisk);

        $result = app(ExpedienteLifecycleService::class)->purgeForError(
            $expediente,
            $this->actor,
            'Registro sintético cuya primera eliminación física fallará.',
        );

        $this->assertDatabaseMissing('expedientes', ['id' => $expediente->id]);
        $this->assertDatabaseMissing('consentimientos', ['id' => $consentimiento->id]);
        $failedDeletion = ExpedienteFileDeletion::query()
            ->where('audit_id', $result['audit_id'])
            ->where('path', $path)
            ->firstOrFail();
        $this->assertSame(ExpedienteFileDeletion::STATUS_FAILED, $failedDeletion->status);
        $this->assertSame(1, $failedDeletion->attempts);
        $this->assertSame('storage_error:RuntimeException', $failedDeletion->last_error);
        $this->assertStringNotContainsString($path, (string) $failedDeletion->last_error);
        $workingDisk->assertExists($path);

        Storage::set('private', $workingDisk);

        $this->artisan('caope:retry-expediente-file-deletions --limit=250')
            ->assertSuccessful();

        $workingDisk->assertMissing($path);
        $retriedDeletion = $failedDeletion->fresh();
        $this->assertSame(ExpedienteFileDeletion::STATUS_DELETED, $retriedDeletion->status);
        $this->assertSame(2, $retriedDeletion->attempts);
        $this->assertNull($retriedDeletion->last_error);
        $this->assertNotNull($retriedDeletion->processed_at);

        $this->artisan('caope:retry-expediente-file-deletions --limit=250')
            ->assertSuccessful();

        $this->assertSame(2, $retriedDeletion->fresh()->attempts);
        $this->assertNotNull(Expediente::withTrashed()->find($archivedExpediente->id));
        $this->assertDatabaseHas('consentimientos', [
            'id' => $archivedConsentimiento->id,
            'archivo_path' => $archivedPath,
        ]);
        $this->assertDatabaseMissing('expediente_file_deletions', [
            'expediente_id' => $archivedExpediente->id,
        ]);
        $workingDisk->assertExists($archivedPath);
        $workingDisk->assertExists($untrackedPath);
    }

    /**
     * @return array{Expediente, Consentimiento, string}
     */
    private function clinicalFileFixture(?string $path = null): array
    {
        $expediente = $this->expediente();
        $path ??= "expedientes/{$expediente->id}/consentimientos/original.pdf";
        Storage::disk('private')->put($path, $this->pdf());

        return [$expediente, $this->consentimiento($expediente, $path), $path];
    }

    private function expediente(): Expediente
    {
        return Expediente::factory()->create([
            'paciente' => 'Paciente sintético de purga',
            'estado' => 'abierto',
            'creado_por' => $this->actor->id,
            'facilitador_id' => $this->actor->id,
            'tutor_id' => null,
            'coordinador_id' => null,
            'retention_until' => null,
        ]);
    }

    private function consentimiento(Expediente $expediente, string $path): Consentimiento
    {
        return Consentimiento::factory()->for($expediente)->create([
            'tratamiento' => 'Consentimiento sintético de purga',
            'aceptado' => true,
            'fecha' => now()->toDateString(),
            'archivo_path' => $path,
            'subido_por' => $this->actor->id,
        ]);
    }

    private function pdf(): string
    {
        return "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n";
    }
}

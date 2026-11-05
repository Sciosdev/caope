<?php

namespace App\Services;

use App\Models\Comentario;
use App\Models\Expediente;
use App\Models\ExpedienteFileDeletion;
use App\Models\ExpedienteLifecycleAudit;
use App\Models\Sesion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class ExpedienteLifecycleService
{
    public const MINIMUM_RETENTION_YEARS = 10;

    public function __construct(private ExpedienteFileDeletionService $fileDeletions) {}

    public function archive(Expediente $expediente, User $actor, string $motivo): Expediente
    {
        return DB::transaction(function () use ($expediente, $actor, $motivo): Expediente {
            $locked = Expediente::withTrashed()->lockForUpdate()->findOrFail($expediente->getKey());
            if ($locked->trashed()) {
                throw ValidationException::withMessages([
                    'lifecycle_action' => 'El expediente ya está archivado.',
                ]);
            }

            $minimumRetention = now()->addYearsNoOverflow(self::MINIMUM_RETENTION_YEARS);
            $retentionUntil = $locked->retention_until && $locked->retention_until->gt($minimumRetention)
                ? $locked->retention_until
                : $minimumRetention;

            $locked->forceFill([
                'archived_by' => $actor->getKey(),
                'retention_until' => $retentionUntil,
            ])->save();
            $locked->delete();

            $this->audit($locked, $actor, 'archive', $motivo);

            return Expediente::withTrashed()->findOrFail($locked->getKey());
        }, 3);
    }

    public function restore(Expediente $expediente, User $actor, string $motivo): Expediente
    {
        return DB::transaction(function () use ($expediente, $actor, $motivo): Expediente {
            $locked = Expediente::withTrashed()->lockForUpdate()->findOrFail($expediente->getKey());
            if (! $locked->trashed()) {
                throw ValidationException::withMessages([
                    'lifecycle_action' => 'El expediente ya se encuentra activo.',
                ]);
            }

            // retention_until y archived_by se preservan deliberadamente: una
            // restauración no debe convertirse en un atajo para purgar datos.
            $locked->restore();
            $this->audit($locked, $actor, 'restore', $motivo);

            return $locked->fresh();
        }, 3);
    }

    /**
     * @return array{audit_id: int, file_summary: array<string, int>}
     */
    public function purgeForError(Expediente $expediente, User $actor, string $motivo): array
    {
        $auditId = DB::transaction(function () use ($expediente, $actor, $motivo): int {
            $locked = Expediente::withTrashed()->lockForUpdate()->findOrFail($expediente->getKey());
            if ($locked->retention_until && $locked->retention_until->isFuture()) {
                throw ValidationException::withMessages([
                    'lifecycle_action' => sprintf(
                        'Este expediente está protegido hasta %s y no puede eliminarse definitivamente.',
                        $locked->retention_until->format('d/m/Y H:i')
                    ),
                ]);
            }

            $audit = $this->audit($locked, $actor, 'purge_error', $motivo);
            $fileReferences = $this->fileReferences($locked);
            $this->fileDeletions->assertSafeReferences($fileReferences);
            $this->queueFileDeletions($locked, $audit, $fileReferences);

            $sessionIds = DB::table('sesiones')
                ->where('expediente_id', $locked->getKey())
                ->pluck('id');

            Comentario::query()
                ->where(function ($comments) use ($locked, $sessionIds): void {
                    $comments->where(function ($expedienteComments) use ($locked): void {
                        $expedienteComments
                            ->where('comentable_type', Expediente::class)
                            ->where('comentable_id', $locked->getKey());
                    });

                    if ($sessionIds->isNotEmpty()) {
                        $comments->orWhere(function ($sessionComments) use ($sessionIds): void {
                            $sessionComments
                                ->where('comentable_type', Sesion::class)
                                ->whereIn('comentable_id', $sessionIds);
                        });
                    }
                })
                ->delete();

            // consentimientos usa RESTRICT para impedir pérdidas accidentales;
            // la purga confirmada los elimina de forma explícita y auditable.
            DB::table('consentimientos')->where('expediente_id', $locked->getKey())->delete();
            $locked->forceDelete();

            // Laravel difiere este callback hasta el commit más externo. Así,
            // una transacción llamadora que haga rollback nunca pierde archivos.
            DB::afterCommit(function () use ($audit): void {
                try {
                    $this->fileDeletions->processForAudit((int) $audit->getKey());
                } catch (Throwable $exception) {
                    Log::error('Unable to process expediente file deletion outbox after commit.', [
                        'audit_id' => $audit->getKey(),
                        'exception' => $exception::class,
                    ]);
                }
            });

            return (int) $audit->getKey();
        }, 3);

        return [
            'audit_id' => $auditId,
            'file_summary' => $this->fileDeletions->summary($auditId),
        ];
    }

    private function audit(
        Expediente $expediente,
        User $actor,
        string $action,
        string $motivo,
    ): ExpedienteLifecycleAudit {
        return ExpedienteLifecycleAudit::query()->create([
            'expediente_id' => $expediente->getKey(),
            'actor_id' => $actor->getKey(),
            'folio' => (string) $expediente->no_control,
            'action' => $action,
            'motivo' => $motivo,
            'created_at' => now(),
        ]);
    }

    private function queueFileDeletions(
        Expediente $expediente,
        ExpedienteLifecycleAudit $audit,
        array $references,
    ): void {
        foreach ($references as $reference) {
            ExpedienteFileDeletion::query()->firstOrCreate(
                [
                    'deduplication_key' => hash(
                        'sha256',
                        $audit->getKey()."\0".$reference['disk']."\0".$reference['path']
                    ),
                ],
                [
                    'audit_id' => $audit->getKey(),
                    'expediente_id' => $expediente->getKey(),
                    'disk' => $reference['disk'],
                    'path' => $reference['path'],
                    'status' => ExpedienteFileDeletion::STATUS_PENDING,
                    'attempts' => 0,
                ]
            );
        }
    }

    /**
     * @return list<array{disk: string, path: string}>
     */
    private function fileReferences(Expediente $expediente): array
    {
        $privateDisk = (string) config('filesystems.private_default', 'private');
        $references = [];

        if (filled($expediente->consentimientos_observaciones_path)) {
            $references[] = [
                'disk' => $privateDisk,
                'path' => (string) $expediente->consentimientos_observaciones_path,
            ];
        }

        foreach (DB::table('consentimientos')->where('expediente_id', $expediente->getKey())->whereNotNull('archivo_path')->pluck('archivo_path') as $path) {
            $references[] = ['disk' => $privateDisk, 'path' => (string) $path];
        }

        foreach (DB::table('anexos')->where('expediente_id', $expediente->getKey())->whereNotNull('ruta')->get(['disk', 'ruta']) as $anexo) {
            $references[] = [
                'disk' => (string) ($anexo->disk ?: $privateDisk),
                'path' => (string) $anexo->ruta,
            ];
        }

        foreach (DB::table('sesion_adjuntos')
            ->join('sesiones', 'sesiones.id', '=', 'sesion_adjuntos.sesion_id')
            ->where('sesiones.expediente_id', $expediente->getKey())
            ->whereNotNull('sesion_adjuntos.ruta')
            ->get(['sesion_adjuntos.disk', 'sesion_adjuntos.ruta']) as $adjunto) {
            $references[] = [
                'disk' => (string) ($adjunto->disk ?: 'public'),
                'path' => (string) $adjunto->ruta,
            ];
        }

        return collect($references)
            ->filter(fn (array $reference): bool => filled($reference['path']))
            ->unique(fn (array $reference): string => $reference['disk']."\0".$reference['path'])
            ->values()
            ->all();
    }
}

<?php

namespace App\Services;

use App\Models\ExpedienteFileDeletion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class ExpedienteFileDeletionService
{
    /**
     * @return array<string, int>
     */
    public function processForAudit(int $auditId): array
    {
        $ids = ExpedienteFileDeletion::query()
            ->where('audit_id', $auditId)
            ->whereIn('status', [
                ExpedienteFileDeletion::STATUS_PENDING,
                ExpedienteFileDeletion::STATUS_FAILED,
            ])
            ->orderBy('id')
            ->pluck('id');

        foreach ($ids as $id) {
            $this->process((int) $id);
        }

        return $this->summary($auditId);
    }

    /**
     * @return array{processed: int, failed: int}
     */
    public function retryPending(int $limit = 100): array
    {
        $ids = ExpedienteFileDeletion::query()
            ->where(function ($query): void {
                $query
                    ->whereIn('status', [
                        ExpedienteFileDeletion::STATUS_PENDING,
                        ExpedienteFileDeletion::STATUS_FAILED,
                    ])
                    ->orWhere(function ($stale): void {
                        $stale->where('status', ExpedienteFileDeletion::STATUS_PROCESSING)
                            ->where('updated_at', '<', now()->subMinutes(15));
                    });
            })
            ->orderBy('id')
            ->limit(max(1, min($limit, 1000)))
            ->pluck('id');

        $failed = 0;
        foreach ($ids as $id) {
            if ($this->process((int) $id) === ExpedienteFileDeletion::STATUS_FAILED) {
                $failed++;
            }
        }

        return ['processed' => $ids->count(), 'failed' => $failed];
    }

    public function process(int $deletionId): ?string
    {
        $deletion = $this->claim($deletionId);
        if (! $deletion) {
            return null;
        }

        if (! $this->isSafeFileReference($deletion->disk, $deletion->path)) {
            return $this->complete(
                $deletion,
                ExpedienteFileDeletion::STATUS_RETAINED_UNSAFE,
                'La referencia no identifica de forma segura un archivo relativo en un disco configurado.'
            );
        }

        if ($this->isStillReferenced($deletion->disk, $deletion->path)) {
            return $this->complete(
                $deletion,
                ExpedienteFileDeletion::STATUS_RETAINED_SHARED,
                'El archivo se conserva porque otro registro todavía utiliza la misma ubicación física.'
            );
        }

        try {
            $disk = Storage::disk($deletion->disk);

            if (! $disk->exists($deletion->path)) {
                return $this->complete($deletion, ExpedienteFileDeletion::STATUS_MISSING);
            }

            $deleted = $disk->delete($deletion->path);
            if (! $deleted || $disk->exists($deletion->path)) {
                throw new \RuntimeException('El adaptador de almacenamiento no confirmó la eliminación del archivo.');
            }

            return $this->complete($deletion, ExpedienteFileDeletion::STATUS_DELETED);
        } catch (Throwable $exception) {
            Log::warning('Expediente file deletion remains pending.', [
                'file_deletion_id' => $deletion->id,
                'audit_id' => $deletion->audit_id,
                'disk' => $deletion->disk,
                'path_hash' => hash('sha256', $deletion->path),
                'exception' => $exception::class,
            ]);

            return $this->complete(
                $deletion,
                ExpedienteFileDeletion::STATUS_FAILED,
                'storage_error:'.Str::limit(class_basename($exception), 180, '')
            );
        }
    }

    /**
     * @param  list<array{disk: string, path: string}>  $references
     */
    public function assertSafeReferences(array $references): void
    {
        foreach ($references as $reference) {
            if ($this->isSafeFileReference($reference['disk'], $reference['path'])) {
                continue;
            }

            throw ValidationException::withMessages([
                'lifecycle_action' => 'La eliminación se detuvo porque existe una referencia de archivo insegura. Corrige el metadato antes de volver a intentarlo.',
            ]);
        }
    }

    /**
     * @return array<string, int>
     */
    public function summary(int $auditId): array
    {
        return ExpedienteFileDeletion::query()
            ->where('audit_id', $auditId)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($total): int => (int) $total)
            ->all();
    }

    private function claim(int $deletionId): ?ExpedienteFileDeletion
    {
        return DB::transaction(function () use ($deletionId): ?ExpedienteFileDeletion {
            $deletion = ExpedienteFileDeletion::query()->lockForUpdate()->find($deletionId);
            if (! $deletion) {
                return null;
            }

            $isRetryable = in_array($deletion->status, [
                ExpedienteFileDeletion::STATUS_PENDING,
                ExpedienteFileDeletion::STATUS_FAILED,
            ], true) || ($deletion->status === ExpedienteFileDeletion::STATUS_PROCESSING
                && $deletion->updated_at?->lt(now()->subMinutes(15)));

            if (! $isRetryable) {
                return null;
            }

            $deletion->forceFill([
                'status' => ExpedienteFileDeletion::STATUS_PROCESSING,
                'attempts' => $deletion->attempts + 1,
                'last_error' => null,
                'processed_at' => null,
            ])->save();

            return $deletion;
        }, 3);
    }

    private function complete(
        ExpedienteFileDeletion $deletion,
        string $status,
        ?string $message = null,
    ): string {
        $deletion->forceFill([
            'status' => $status,
            'last_error' => $message,
            'processed_at' => $status === ExpedienteFileDeletion::STATUS_FAILED ? null : now(),
        ])->save();

        return $status;
    }

    private function isSafeFileReference(string $disk, string $path): bool
    {
        if (! preg_match('/^[A-Za-z0-9_.-]+$/', $disk) || config("filesystems.disks.{$disk}") === null) {
            return false;
        }

        if ($path === '' || trim($path) !== $path || str_contains($path, "\0")) {
            return false;
        }

        $normalized = str_replace('\\', '/', $path);
        if (str_starts_with($normalized, '/') || preg_match('/^[A-Za-z]:\//', $normalized)) {
            return false;
        }

        $segments = explode('/', $normalized);

        return ! str_ends_with($normalized, '/')
            && ! collect($segments)->contains(fn (string $segment): bool => $segment === '' || $segment === '.' || $segment === '..');
    }

    private function isStillReferenced(string $disk, string $path): bool
    {
        $identity = $this->storageIdentity($disk, $path);

        foreach ($this->storedFileReferences() as $reference) {
            if ($this->storageIdentity($reference['disk'], $reference['path']) === $identity) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array{disk: string, path: string}>
     */
    private function storedFileReferences(): array
    {
        $privateDisk = (string) config('filesystems.private_default', 'private');
        $references = [];

        foreach (DB::table('expedientes')->whereNotNull('consentimientos_observaciones_path')->pluck('consentimientos_observaciones_path') as $path) {
            $references[] = ['disk' => $privateDisk, 'path' => (string) $path];
        }

        foreach (DB::table('consentimientos')->whereNotNull('archivo_path')->pluck('archivo_path') as $path) {
            $references[] = ['disk' => $privateDisk, 'path' => (string) $path];
        }

        foreach (DB::table('anexos')->whereNotNull('ruta')->get(['disk', 'ruta']) as $anexo) {
            $references[] = [
                'disk' => (string) ($anexo->disk ?: $privateDisk),
                'path' => (string) $anexo->ruta,
            ];
        }

        foreach (DB::table('sesion_adjuntos')->whereNotNull('ruta')->get(['disk', 'ruta']) as $adjunto) {
            $references[] = [
                'disk' => (string) ($adjunto->disk ?: 'public'),
                'path' => (string) $adjunto->ruta,
            ];
        }

        return $references;
    }

    private function storageIdentity(string $disk, string $path): string
    {
        $normalizedPath = str_replace('\\', '/', $path);
        $configuration = config("filesystems.disks.{$disk}", []);

        if (($configuration['driver'] ?? null) === 'local' && filled($configuration['root'] ?? null)) {
            $identity = str_replace('\\', '/', rtrim((string) $configuration['root'], '/\\')).'/'.$normalizedPath;

            return PHP_OS_FAMILY === 'Windows' ? Str::lower($identity) : $identity;
        }

        return $disk.':'.$normalizedPath;
    }
}

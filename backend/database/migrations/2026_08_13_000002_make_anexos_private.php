<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        $privateDisk = (string) config('filesystems.private_default', 'private');
        $this->assertPrivateDisk($privateDisk);

        DB::table('anexos')
            ->orderBy('id')
            ->each(function (object $anexo) use ($privateDisk): void {
                $path = (string) $anexo->ruta;
                $sourceDisk = (string) ($anexo->disk ?: 'public');

                if ($path === '') {
                    return;
                }

                $this->assertValidDisk($sourceDisk);

                if ($sourceDisk === $privateDisk) {
                    DB::table('anexos')->where('id', $anexo->id)->update(['es_privado' => true]);

                    return;
                }

                if ($this->disksShareRoot($sourceDisk, $privateDisk)) {
                    DB::table('anexos')->where('id', $anexo->id)->update([
                        'disk' => $privateDisk,
                        'es_privado' => true,
                    ]);

                    return;
                }

                $sourceExists = Storage::disk($sourceDisk)->exists($path);
                $privateExists = Storage::disk($privateDisk)->exists($path);

                if ($sourceExists && (! $privateExists || ! $this->filesMatch($sourceDisk, $privateDisk, $path))) {
                    $stream = Storage::disk($sourceDisk)->readStream($path);

                    if (! is_resource($stream)) {
                        throw new RuntimeException("No fue posible leer el anexo {$anexo->id}.");
                    }

                    try {
                        $privateExists = Storage::disk($privateDisk)->put($path, $stream);
                    } finally {
                        fclose($stream);
                    }
                }

                if (! $privateExists || ($sourceExists && ! $this->filesMatch($sourceDisk, $privateDisk, $path))) {
                    throw new RuntimeException("No fue posible verificar la copia privada del anexo {$anexo->id}.");
                }

                DB::table('anexos')->where('id', $anexo->id)->update([
                    'disk' => $privateDisk,
                    'es_privado' => true,
                ]);

                if ($sourceExists && ! Storage::disk($sourceDisk)->delete($path)) {
                    throw new RuntimeException("No fue posible retirar la copia pública del anexo {$anexo->id}.");
                }
            });
    }

    public function down(): void
    {
        // La privacidad de archivos clínicos no se revierte automáticamente.
    }

    private function assertValidDisk(string $disk): void
    {
        if ($disk === '' || ! is_array(config("filesystems.disks.{$disk}"))) {
            throw new RuntimeException("El disco de anexos [{$disk}] no está configurado.");
        }
    }

    private function assertPrivateDisk(string $privateDisk): void
    {
        $this->assertValidDisk($privateDisk);

        if ($privateDisk === 'public' || $this->disksShareRoot('public', $privateDisk)) {
            throw new RuntimeException('Los anexos privados no pueden usar la ubicación del disco público.');
        }
    }

    private function disksShareRoot(string $firstDisk, string $secondDisk): bool
    {
        $firstRoot = $this->diskRoot($firstDisk);
        $secondRoot = $this->diskRoot($secondDisk);

        return $firstRoot !== null && $firstRoot === $secondRoot;
    }

    private function diskRoot(string $disk): ?string
    {
        $root = config("filesystems.disks.{$disk}.root");

        if (! is_string($root) || trim($root) === '') {
            return null;
        }

        return strtolower(rtrim(str_replace('\\', '/', $root), '/'));
    }

    private function filesMatch(string $sourceDisk, string $destinationDisk, string $path): bool
    {
        if (Storage::disk($sourceDisk)->size($path) !== Storage::disk($destinationDisk)->size($path)) {
            return false;
        }

        $source = Storage::disk($sourceDisk)->readStream($path);
        $destination = Storage::disk($destinationDisk)->readStream($path);

        if (! is_resource($source) || ! is_resource($destination)) {
            if (is_resource($source)) {
                fclose($source);
            }
            if (is_resource($destination)) {
                fclose($destination);
            }

            return false;
        }

        try {
            return hash_equals(hash('sha256', stream_get_contents($source)), hash('sha256', stream_get_contents($destination)));
        } finally {
            fclose($source);
            fclose($destination);
        }
    }
};

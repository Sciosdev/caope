<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (! Schema::hasColumn('sesion_adjuntos', 'disk')) {
            Schema::table('sesion_adjuntos', function (Blueprint $table): void {
                $table->string('disk', 40)->default('public')->after('ruta');
            });
        }

        $privateDisk = (string) config('filesystems.private_default', 'private');
        $this->assertDistinctDisks($privateDisk);

        DB::table('sesion_adjuntos')
            ->orderBy('id')
            ->each(function (object $adjunto) use ($privateDisk): void {
                $path = (string) $adjunto->ruta;

                if ($path === '') {
                    return;
                }

                $publicExists = Storage::disk('public')->exists($path);
                $privateExists = Storage::disk($privateDisk)->exists($path);

                if ($publicExists && (! $privateExists || ! $this->filesMatch('public', $privateDisk, $path))) {
                    $stream = Storage::disk('public')->readStream($path);

                    if (! is_resource($stream)) {
                        throw new RuntimeException("No fue posible leer el adjunto de sesión {$adjunto->id}.");
                    }

                    try {
                        $privateExists = Storage::disk($privateDisk)->put($path, $stream);
                    } finally {
                        fclose($stream);
                    }

                    if (! $privateExists) {
                        throw new RuntimeException("No fue posible proteger el adjunto de sesión {$adjunto->id}.");
                    }
                }

                if ($privateExists) {
                    if ($publicExists && ! $this->filesMatch('public', $privateDisk, $path)) {
                        throw new RuntimeException("No fue posible verificar el adjunto privado de sesión {$adjunto->id}.");
                    }

                    DB::table('sesion_adjuntos')->where('id', $adjunto->id)->update(['disk' => $privateDisk]);

                    if ($publicExists && ! Storage::disk('public')->delete($path)) {
                        throw new RuntimeException("No fue posible retirar el adjunto público de sesión {$adjunto->id}.");
                    }
                }
            });
    }

    public function down(): void
    {
        $privateDisk = (string) config('filesystems.private_default', 'private');
        $this->assertDistinctDisks($privateDisk);

        DB::table('sesion_adjuntos')
            ->where('disk', $privateDisk)
            ->orderBy('id')
            ->each(function (object $adjunto) use ($privateDisk): void {
                $path = (string) $adjunto->ruta;

                if ($path === '' || ! Storage::disk($privateDisk)->exists($path)) {
                    return;
                }

                $stream = Storage::disk($privateDisk)->readStream($path);

                if (! is_resource($stream)) {
                    throw new RuntimeException("No fue posible leer el adjunto privado de sesión {$adjunto->id}.");
                }

                try {
                    $written = Storage::disk('public')->put($path, $stream);
                } finally {
                    fclose($stream);
                }

                if (! $written) {
                    throw new RuntimeException("No fue posible restaurar el adjunto de sesión {$adjunto->id}.");
                }

                Storage::disk($privateDisk)->delete($path);
            });

        Schema::table('sesion_adjuntos', function (Blueprint $table): void {
            $table->dropColumn('disk');
        });
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

    private function assertDistinctDisks(string $privateDisk): void
    {
        if ($privateDisk === 'public') {
            throw new RuntimeException('FILESYSTEM_DISK_PRIVATE no puede apuntar al disco público.');
        }

        $privateRoot = $this->diskRoot($privateDisk);
        $publicRoot = $this->diskRoot('public');

        if ($privateRoot !== null && $privateRoot === $publicRoot) {
            throw new RuntimeException('Los discos público y privado no pueden compartir ubicación.');
        }
    }
};

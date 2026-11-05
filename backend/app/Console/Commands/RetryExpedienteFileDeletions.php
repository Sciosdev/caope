<?php

namespace App\Console\Commands;

use App\Services\ExpedienteFileDeletionService;
use Illuminate\Console\Command;

class RetryExpedienteFileDeletions extends Command
{
    protected $signature = 'caope:retry-expediente-file-deletions {--limit=100 : Máximo de referencias a procesar}';

    protected $description = 'Reintenta la eliminación segura de archivos pendientes tras purgas de expedientes';

    public function handle(ExpedienteFileDeletionService $service): int
    {
        $result = $service->retryPending((int) $this->option('limit'));

        $this->components->info(sprintf(
            'Referencias procesadas: %d; aún fallidas: %d.',
            $result['processed'],
            $result['failed'],
        ));

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Services\ProductionSecurityAudit;
use Illuminate\Console\Command;

class AuditProductionConfiguration extends Command
{
    protected $signature = 'caope:security-audit
        {--profile=production : Perfil requerido: production, staging o auto}';

    protected $description = 'Comprueba la configuración de seguridad sin mostrar valores sensibles';

    public function handle(ProductionSecurityAudit $audit): int
    {
        $profile = strtolower(trim((string) $this->option('profile')));

        if (! in_array($profile, [
            'auto',
            ProductionSecurityAudit::PROFILE_PRODUCTION,
            ProductionSecurityAudit::PROFILE_STAGING,
        ], true)) {
            $this->components->error('El perfil debe ser production, staging o auto.');

            return self::INVALID;
        }

        if ($profile === 'auto') {
            $profile = config('app.env') === ProductionSecurityAudit::PROFILE_STAGING
                ? ProductionSecurityAudit::PROFILE_STAGING
                : ProductionSecurityAudit::PROFILE_PRODUCTION;
        }

        $checks = $audit->run($profile);
        $statusLabels = [
            'ok' => 'CORRECTO',
            'warning' => 'ADVERTENCIA',
            'error' => 'ERROR',
        ];

        $this->table(
            ['Comprobación', 'Estado', 'Resultado'],
            array_map(static fn (array $check): array => [
                $check['label'],
                $statusLabels[$check['status']],
                $check['summary'],
            ], $checks)
        );

        $errorCount = count(array_filter(
            $checks,
            static fn (array $check): bool => $check['status'] === 'error'
        ));

        if ($errorCount > 0) {
            $this->components->error("La auditoría detectó {$errorCount} error(es). No se modificó la aplicación.");

            return self::FAILURE;
        }

        $this->components->info('La configuración cumple el perfil solicitado.');

        return self::SUCCESS;
    }
}

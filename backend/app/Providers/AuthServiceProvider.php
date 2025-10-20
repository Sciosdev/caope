<?php

namespace App\Providers;

use App\Models\Anexo;
use App\Models\Consentimiento;
use App\Models\Expediente;
use App\Models\Sesion;
use App\Policies\AnexoPolicy;
use App\Policies\ConsentimientoPolicy;
use App\Policies\ExpedientePolicy;
use App\Policies\SesionPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Expediente::class => ExpedientePolicy::class,
        Sesion::class => SesionPolicy::class,
        Consentimiento::class => ConsentimientoPolicy::class,
        Anexo::class => AnexoPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();
    }
}

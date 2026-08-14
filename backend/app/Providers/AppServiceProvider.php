<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Password::defaults(function (): Password {
            if (app()->environment(['production', 'staging'])) {
                return Password::min(12)
                    ->letters()
                    ->mixedCase()
                    ->numbers()
                    ->symbols();
            }

            return Password::min(8);
        });

        $buildPath = config('vite.build_path');

        if (is_string($buildPath) && $buildPath !== '') {
            Vite::useBuildDirectory($buildPath);
        }

        $manifest = config('vite.manifest');

        if (is_string($manifest) && $manifest !== '') {
            Vite::useManifestFilename($manifest);
        }
    }
}

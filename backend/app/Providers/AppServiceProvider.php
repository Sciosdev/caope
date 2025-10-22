<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

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

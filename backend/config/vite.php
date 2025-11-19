<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Vite Build Directory
    |--------------------------------------------------------------------------
    |
    | The path relative to the public directory where the Vite build outputs
    | its compiled assets. This informs Laravel where to look for the
    | manifest file and compiled bundles when resolving asset URLs.
    |
    */
    'build_path' => env('VITE_BUILD_PATH', 'assets/build'),

    /*
    |--------------------------------------------------------------------------
    | Manifest File
    |--------------------------------------------------------------------------
    |
    | Cuando Vite compila los assets, genera un manifiesto dentro del directorio
    | indicado anteriormente. El valor por defecto apunta a `manifest.json` en la
    | raíz del directorio de salida para evitar depender de carpetas ocultas.
    |
    */
    'manifest' => env('VITE_MANIFEST', 'manifest.json'),
];

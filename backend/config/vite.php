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
    | When Vite compila los assets, genera un manifiesto dentro del directorio
    | indicado anteriormente. El valor por defecto apunta a `.vite/manifest.json`
    | ya que Vite anida el manifiesto en esta carpeta interna.
    |
    */
    'manifest' => env('VITE_MANIFEST', '.vite/manifest.json'),
];

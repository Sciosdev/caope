# Error de manifiesto de Vite en producción

Se confirmó en producción que la excepción `Vite manifest not found` se produce cuando la aplicación intenta resolver los assets compilados desde una ruta de manifiesto inexistente. El siguiente stacktrace fue reproducido a partir de los registros y de una ejecución local que emula el entorno productivo:

```
Illuminate\Foundation\ViteManifestNotFoundException: Vite manifest not found at: /workspace/caope/backend/public/assets/build/.vite/manifest.json in /workspace/caope/backend/vendor/laravel/framework/src/Illuminate/Foundation/Vite.php:946
Stack trace:
#0 /workspace/caope/backend/vendor/laravel/framework/src/Illuminate/Foundation/Vite.php(384): Illuminate\Foundation\Vite->manifest('assets/build')
#1 /tmp/test_vite.php(11): Illuminate\Foundation\Vite->__invoke(Object(Illuminate\Support\Collection))
#2 {main}
```

El problema ocurre porque el despliegue copia los bundles a `public/assets/build`, pero la configuración por defecto de Laravel busca el manifiesto en `public/build/manifest.json`.

## Solución aplicada
- Se configuró Vite para generar el manifiesto como `public/assets/build/manifest.json` en lugar de colocarlo bajo `.vite/`.
- Laravel ahora busca ese archivo por defecto mediante `config/vite.php` (`build_path = assets/build`, `manifest = manifest.json`).
- Con estos ajustes el manifiesto deja de depender de directorios ocultos que podrían ser omitidos durante la publicación de los bundles.

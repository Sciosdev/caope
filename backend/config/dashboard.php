<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Días para marcar un expediente como estancado
    |--------------------------------------------------------------------------
    |
    | Define cuántos días deben pasar sin actividad reciente en un expediente
    | para que sea considerado estancado dentro del panel de control.
    */
    'stalled_days' => env('DASHBOARD_STALLED_DAYS', 14),

    /*
    |--------------------------------------------------------------------------
    | Tiempo en caché de las métricas del dashboard (segundos)
    |--------------------------------------------------------------------------
    |
    | Define por cuánto tiempo se almacenan en caché los cálculos del
    | dashboard (conteos, promedios y alertas). Usa 0 para desactivar la caché.
    */
    'cache_ttl' => env('DASHBOARD_CACHE_TTL', 60),
];


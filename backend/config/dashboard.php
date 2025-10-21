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
];


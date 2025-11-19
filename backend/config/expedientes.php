<?php

return [
    'no_control' => [
        'pattern' => env('EXPEDIENTES_NO_CONTROL_PATTERN', '/^(?:[A-Z]{2}-\d{4}-\d{4}|[A-Z]{2}-\d{4})$/'),
        'example' => env('EXPEDIENTES_NO_CONTROL_EXAMPLE', 'CA-2025-0001'),
    ],
];

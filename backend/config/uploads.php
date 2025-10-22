<?php

return [
    'anexos' => [
        'mimes' => env('ANEXOS_UPLOAD_MIMES', 'pdf,jpg,jpeg,png,doc,docx'),
        'max' => (int) env('ANEXOS_UPLOAD_MAX', 51200), // Kilobytes
    ],
];

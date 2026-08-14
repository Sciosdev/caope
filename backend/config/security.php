<?php

$publicHosts = [
    'caope.ayudafesi.com',
    'xocoyotzin.iztacala.unam.mx',
];

$hostProfiles = [
    'production' => $publicHosts,
    'staging' => $publicHosts,
    'local' => [...$publicHosts, 'localhost', '127.0.0.1', '[::1]'],
    'testing' => [...$publicHosts, 'localhost', '127.0.0.1', '[::1]'],
];

$configuredHosts = env('TRUSTED_HOSTS');
$environment = (string) env('APP_ENV', 'production');
$trustedHosts = $configuredHosts === null
    ? ($hostProfiles[$environment] ?? $publicHosts)
    : array_values(array_filter(array_map(
        static fn (string $host): string => trim($host),
        explode(',', (string) $configuredHosts),
    )));

return [
    'trusted_host_profiles' => $hostProfiles,
    'trusted_hosts' => $trustedHosts,

    // SHA-256 fingerprints of application keys that were previously published
    // in this repository. The key values themselves must never be restored.
    'compromised_app_key_hashes' => [
        'db3feb1147c197c8d554ba78e7cbf3a149b1634970014989a01efb3b5691dbcc',
        '1b610ff6455610e7b81c8da8b0cb3c314d84987bfe4c261ab5b501fad4ff38fc',
        'a6a6522067aefe52da1d2a5cf7cb23d45085ae9071a0ad161e580840ed9aef06',
    ],

    'headers' => [
        'content_security_policy' => implode('; ', [
            "default-src 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
            "object-src 'none'",
            // Alpine evaluates the existing x-* expressions dynamically.
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.datatables.net https://cdn.jsdelivr.net",
            "style-src 'self' 'unsafe-inline' https://cdn.datatables.net https://cdn.jsdelivr.net https://fonts.bunny.net",
            "font-src 'self' data: https://fonts.bunny.net",
            "img-src 'self' data: blob:",
            "connect-src 'self'",
        ]),
        'permissions_policy' => 'camera=(), geolocation=(), microphone=()',
        'referrer_policy' => 'strict-origin-when-cross-origin',
        'strict_transport_security' => 'max-age=31536000; includeSubDomains',
    ],
];

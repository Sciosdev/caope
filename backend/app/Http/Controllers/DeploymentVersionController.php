<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class DeploymentVersionController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $markerPath = storage_path('app/deployment/version.json');

        abort_unless(is_file($markerPath) && is_readable($markerPath), 404);

        $marker = json_decode((string) file_get_contents($markerPath), true);
        $sha = is_array($marker) ? (string) ($marker['sha'] ?? '') : '';

        abort_unless(preg_match('/^[a-f0-9]{40}$/i', $sha) === 1, 503);

        return response()->json([
            'sha' => strtolower($sha),
            'request_id' => (string) ($marker['request_id'] ?? ''),
            'deployed_at' => (string) ($marker['deployed_at'] ?? ''),
        ])->withHeaders([
            'Cache-Control' => 'no-store, private',
        ]);
    }
}

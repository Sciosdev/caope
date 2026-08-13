<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class DeploymentPreparationController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $configuredToken = (string) config('developer_console.deploy_webhook_token');

        abort_if($configuredToken === '', 404);

        $providedToken = (string) $request->bearerToken();
        abort_unless($providedToken !== '' && hash_equals($configuredToken, $providedToken), 403);

        $validated = $request->validate([
            'sha' => ['required', 'regex:/^[a-f0-9]{40}$/i'],
            'request_id' => ['required', 'string', 'max:100'],
        ]);

        $markerPath = storage_path('app/deployment/expected.json');
        File::ensureDirectoryExists(dirname($markerPath));
        File::put($markerPath, json_encode([
            'sha' => strtolower((string) $validated['sha']),
            'request_id' => (string) $validated['request_id'],
            'expires_at' => now()->addMinutes(30)->timestamp,
        ], JSON_THROW_ON_ERROR), true);

        return response()->json(['accepted' => true], 202)
            ->withHeaders(['Cache-Control' => 'no-store, private']);
    }
}

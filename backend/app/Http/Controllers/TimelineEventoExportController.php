<?php

namespace App\Http\Controllers;

use App\Exports\TimelineEventosExport;
use App\Jobs\FinalizeQueuedExport;
use App\Models\Expediente;
use App\Models\TimelineEvento;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TimelineEventoExportController extends Controller
{
    private const QUEUE_THRESHOLD = 200;

    public function export(Request $request, Expediente $expediente): JsonResponse
    {
        $this->authorize('view', $expediente);

        $format = $request->validate([
            'format' => ['nullable', Rule::in(['xlsx', 'csv'])],
        ])['format'] ?? 'xlsx';

        $writerType = $format === 'csv' ? ExcelWriter::CSV : ExcelWriter::XLSX;

        $token = (string) Str::uuid();
        $filename = sprintf('timeline_expediente_%s.%s', now()->format('Ymd_His'), $format);
        $path = sprintf('exports/timeline_%s.%s', $token, $format);

        $userId = (int) $request->user()->id;
        $eventIds = TimelineEvento::query()
            ->visibleTo($request->user())
            ->where('expediente_id', $expediente->getKey())
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
        $export = new TimelineEventosExport($userId, $expediente->getKey(), $eventIds);

        $total = count($eventIds);

        $cachePayload = [
            'path' => $path,
            'filename' => $filename,
            'user_id' => $userId,
            'expediente_id' => $expediente->getKey(),
            'event_ids' => $eventIds,
        ];

        if ($total > self::QUEUE_THRESHOLD) {
            Cache::put($token, array_merge($cachePayload, [
                'status' => 'pending',
            ]), now()->addMinutes(30));

            Excel::queue($export, $path, 'local', $writerType)
                ->chain([
                    new FinalizeQueuedExport($token, $path, $filename, $userId, [
                        'expediente_id' => $expediente->getKey(),
                        'event_ids' => $eventIds,
                    ]),
                ]);

            return response()->json([
                'status' => 'pending',
                'token' => $token,
                'status_url' => route('expedientes.timeline.export.status', [$expediente, $token]),
                'message' => __('Estamos generando la exportación en segundo plano. Te avisaremos cuando esté lista.'),
            ]);
        }

        Excel::store($export, $path, 'local', $writerType);

        Cache::put($token, array_merge($cachePayload, [
            'status' => 'ready',
        ]), now()->addMinutes(10));

        return response()->json([
            'status' => 'ready',
            'token' => $token,
            'download_url' => route('expedientes.timeline.export.download', [$expediente, $token]),
            'message' => __('El archivo se generó correctamente.'),
        ]);
    }

    public function status(Request $request, Expediente $expediente, string $token): JsonResponse
    {
        $this->authorize('view', $expediente);

        $data = Cache::get($token);

        if (! $data || ($data['user_id'] ?? null) !== $request->user()->id || ($data['expediente_id'] ?? null) !== $expediente->getKey()) {
            abort(404);
        }

        if (! $this->exportStillAuthorized($request->user(), $expediente, $data)) {
            $this->forgetExport($token, $data);
            abort(404);
        }

        if (($data['status'] ?? null) !== 'ready') {
            return response()->json([
                'status' => 'pending',
            ]);
        }

        return response()->json([
            'status' => 'ready',
            'download_url' => route('expedientes.timeline.export.download', [$expediente, $token]),
        ]);
    }

    public function download(Request $request, Expediente $expediente, string $token): BinaryFileResponse
    {
        $this->authorize('view', $expediente);

        $data = Cache::get($token);

        if (! $data
            || ($data['user_id'] ?? null) !== $request->user()->id
            || ($data['expediente_id'] ?? null) !== $expediente->getKey()
            || ($data['status'] ?? null) !== 'ready'
        ) {
            abort(404);
        }

        if (! $this->exportStillAuthorized($request->user(), $expediente, $data)) {
            $this->forgetExport($token, $data);
            abort(404);
        }

        if (! Storage::disk('local')->exists($data['path'])) {
            Cache::forget($token);
            abort(404);
        }

        Cache::forget($token);

        return response()->download(
            Storage::disk('local')->path($data['path']),
            $data['filename']
        )->deleteFileAfterSend(true);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function exportStillAuthorized(User $user, Expediente $expediente, array $data): bool
    {
        if (! array_key_exists('event_ids', $data) || ! is_array($data['event_ids'])) {
            return false;
        }

        $expectedIds = collect($data['event_ids'])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($expectedIds->isEmpty()) {
            return true;
        }

        return TimelineEvento::query()
            ->visibleTo($user)
            ->where('expediente_id', $expediente->getKey())
            ->whereKey($expectedIds->all())
            ->count() === $expectedIds->count();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function forgetExport(string $token, array $data): void
    {
        $path = $data['path'] ?? null;

        if (is_string($path) && Storage::disk('local')->exists($path)) {
            Storage::disk('local')->delete($path);
        }

        Cache::forget($token);
    }
}

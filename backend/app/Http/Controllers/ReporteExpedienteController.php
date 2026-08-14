<?php

namespace App\Http\Controllers;

use App\Exports\ExpedientesExport;
use App\Models\Expediente;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReporteExpedienteController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $this->validateFilters($request);
        $user = $request->user();

        $expedientes = $this->baseQuery($filters, $user)
            ->with(['tutor', 'coordinador', 'creadoPor'])
            ->orderByDesc('apertura')
            ->paginate(15)
            ->withQueryString();

        $visible = Expediente::query()->visibleTo($user);
        $tutorIds = (clone $visible)->whereNotNull('tutor_id')->distinct()->pluck('tutor_id');
        $coordinadorIds = (clone $visible)->whereNotNull('coordinador_id')->distinct()->pluck('coordinador_id');
        $creadorIds = (clone $visible)->whereNotNull('creado_por')->distinct()->pluck('creado_por');

        return view('reportes.expedientes.index', [
            'expedientes' => $expedientes,
            'filters' => $filters,
            'tutores' => User::query()->whereIn('id', $tutorIds)->orderBy('name')->get(),
            'coordinadores' => User::query()->whereIn('id', $coordinadorIds)->orderBy('name')->get(),
            'creadores' => User::query()->whereIn('id', $creadorIds)->orderBy('name')->get(),
        ]);
    }

    public function export(Request $request): JsonResponse
    {
        $filters = $this->validateFilters($request);

        $format = $request->validate([
            'format' => ['required', Rule::in(['xlsx', 'csv'])],
        ])['format'];

        $token = (string) Str::uuid();
        $filename = sprintf('reporte_expedientes_%s.%s', now()->format('Ymd_His'), $format);
        $path = sprintf('exports/expedientes_%s.%s', $token, $format);
        $writerType = $format === 'csv' ? ExcelWriter::CSV : ExcelWriter::XLSX;

        $userId = (int) $request->user()->id;
        $expedienteIds = $this->baseQuery($filters, $request->user())
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
        $export = new ExpedientesExport($filters, $userId, $expedienteIds);
        Excel::store($export, $path, 'local', $writerType);

        Cache::put($token, [
            'status' => 'ready',
            'path' => $path,
            'filename' => $filename,
            'user_id' => $userId,
            'expediente_ids' => $expedienteIds,
        ], now()->addMinutes(10));

        return response()->json([
            'status' => 'ready',
            'token' => $token,
            'download_url' => route('reportes.expedientes.download', $token),
            'message' => __('El archivo se generó correctamente.'),
        ]);
    }

    public function downloadDirect(Request $request): BinaryFileResponse
    {
        $filters = $this->validateFilters($request);

        $format = $request->validate([
            'format' => ['required', Rule::in(['xlsx', 'csv'])],
        ])['format'];

        $filename = sprintf('reporte_expedientes_%s.%s', now()->format('Ymd_His'), $format);
        $writerType = $format === 'csv' ? ExcelWriter::CSV : ExcelWriter::XLSX;
        $user = $request->user();
        $expedienteIds = $this->baseQuery($filters, $user)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        return Excel::download(
            new ExpedientesExport($filters, (int) $user->id, $expedienteIds),
            $filename,
            $writerType
        );
    }

    public function status(Request $request, string $token): JsonResponse
    {
        $data = Cache::get($token);

        if (! $data || ($data['user_id'] ?? null) !== $request->user()->id) {
            abort(404);
        }

        if (! $this->exportStillAuthorized($request->user(), $data)) {
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
            'download_url' => route('reportes.expedientes.download', $token),
        ]);
    }

    public function download(Request $request, string $token): BinaryFileResponse
    {
        $data = Cache::get($token);

        if (! $data || ($data['user_id'] ?? null) !== $request->user()->id || ($data['status'] ?? null) !== 'ready') {
            abort(404);
        }

        if (! $this->exportStillAuthorized($request->user(), $data)) {
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
     * @return array<string, mixed>
     */
    private function validateFilters(Request $request): array
    {
        $validated = $request->validate([
            'estado' => ['nullable', Rule::in(['abierto', 'revision', 'cerrado'])],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
            'tutor_id' => ['nullable', 'integer', 'exists:users,id'],
            'coordinador_id' => ['nullable', 'integer', 'exists:users,id'],
            'creado_por' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        return [
            'estado' => $validated['estado'] ?? null,
            'desde' => isset($validated['desde']) ? Carbon::parse($validated['desde'])->format('Y-m-d') : null,
            'hasta' => isset($validated['hasta']) ? Carbon::parse($validated['hasta'])->format('Y-m-d') : null,
            'tutor_id' => isset($validated['tutor_id']) ? (int) $validated['tutor_id'] : null,
            'coordinador_id' => isset($validated['coordinador_id']) ? (int) $validated['coordinador_id'] : null,
            'creado_por' => isset($validated['creado_por']) ? (int) $validated['creado_por'] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Expediente>
     */
    private function baseQuery(array $filters, User $user): Builder
    {
        return Expediente::query()
            ->visibleTo($user)
            ->when($filters['estado'] ?? null, fn (Builder $q, string $estado): Builder => $q->where('estado', $estado))
            ->when($filters['desde'] ?? null, fn (Builder $q, string $desde): Builder => $q->whereDate('apertura', '>=', $desde))
            ->when($filters['hasta'] ?? null, fn (Builder $q, string $hasta): Builder => $q->whereDate('apertura', '<=', $hasta))
            ->when($filters['tutor_id'] ?? null, fn (Builder $q, int $tutorId): Builder => $q->where('tutor_id', $tutorId))
            ->when($filters['coordinador_id'] ?? null, fn (Builder $q, int $coordinadorId): Builder => $q->where('coordinador_id', $coordinadorId))
            ->when($filters['creado_por'] ?? null, fn (Builder $q, int $creadoPor): Builder => $q->where('creado_por', $creadoPor));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function exportStillAuthorized(User $user, array $data): bool
    {
        if (! array_key_exists('expediente_ids', $data) || ! is_array($data['expediente_ids'])) {
            return false;
        }

        $expectedIds = collect($data['expediente_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($expectedIds->isEmpty()) {
            return true;
        }

        return Expediente::query()
            ->visibleTo($user)
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

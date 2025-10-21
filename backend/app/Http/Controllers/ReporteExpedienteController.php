<?php

namespace App\Http\Controllers;

use App\Exports\ExpedientesExport;
use App\Jobs\FinalizeExpedientesExport;
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
    private const QUEUE_THRESHOLD = 200;

    public function index(Request $request): View
    {
        $filters = $this->validateFilters($request);

        $expedientes = $this->baseQuery($filters)
            ->with(['tutor', 'coordinador', 'creadoPor'])
            ->orderByDesc('apertura')
            ->paginate(15)
            ->withQueryString();

        return view('reportes.expedientes.index', [
            'expedientes' => $expedientes,
            'filters' => $filters,
            'tutores' => User::role('docente')->orderBy('name')->get(),
            'coordinadores' => User::role('coordinador')->orderBy('name')->get(),
            'creadores' => User::orderBy('name')->get(),
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

        $export = new ExpedientesExport($filters);
        $total = (clone $this->baseQuery($filters))->count();

        if ($total > self::QUEUE_THRESHOLD) {
            Cache::put($token, [
                'status' => 'pending',
                'path' => $path,
                'filename' => $filename,
                'user_id' => $userId,
            ], now()->addMinutes(30));

            Excel::queue($export, $path, 'local', $writerType)
                ->chain([
                    new FinalizeExpedientesExport($token, $path, $filename, $userId),
                ]);

            return response()->json([
                'status' => 'pending',
                'token' => $token,
                'status_url' => route('reportes.expedientes.export.status', $token),
                'message' => __('Tu exportación se está procesando en segundo plano. Te avisaremos en cuanto esté lista.'),
            ]);
        }

        Excel::store($export, $path, 'local', $writerType);

        Cache::put($token, [
            'status' => 'ready',
            'path' => $path,
            'filename' => $filename,
            'user_id' => $userId,
        ], now()->addMinutes(10));

        return response()->json([
            'status' => 'ready',
            'token' => $token,
            'download_url' => route('reportes.expedientes.download', $token),
            'message' => __('El archivo se generó correctamente.'),
        ]);
    }

    public function status(Request $request, string $token): JsonResponse
    {
        $data = Cache::get($token);

        if (! $data || ($data['user_id'] ?? null) !== $request->user()->id) {
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
     * @param  Request  $request
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
    private function baseQuery(array $filters): Builder
    {
        return Expediente::query()
            ->when($filters['estado'] ?? null, fn (Builder $q, string $estado): Builder => $q->where('estado', $estado))
            ->when($filters['desde'] ?? null, fn (Builder $q, string $desde): Builder => $q->whereDate('apertura', '>=', $desde))
            ->when($filters['hasta'] ?? null, fn (Builder $q, string $hasta): Builder => $q->whereDate('apertura', '<=', $hasta))
            ->when($filters['tutor_id'] ?? null, fn (Builder $q, int $tutorId): Builder => $q->where('tutor_id', $tutorId))
            ->when($filters['coordinador_id'] ?? null, fn (Builder $q, int $coordinadorId): Builder => $q->where('coordinador_id', $coordinadorId))
            ->when($filters['creado_por'] ?? null, fn (Builder $q, int $creadoPor): Builder => $q->where('creado_por', $creadoPor));
    }
}

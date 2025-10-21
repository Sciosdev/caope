<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAnexoRequest;
use App\Models\Anexo;
use App\Models\Expediente;
use App\Services\TimelineLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class AnexoController extends Controller
{
    public function __construct(private TimelineLogger $timelineLogger)
    {
    }

    public function store(StoreAnexoRequest $request, Expediente $expediente): JsonResponse|RedirectResponse
    {
        $file = $request->file('archivo');

        if ($file === null) {
            return $this->respondWithError($request, 'No se recibió un archivo válido.', 422);
        }

        $isPrivado = $request->boolean('es_privado', true);
        $privateDisk = config('filesystems.private_default', 'private');
        $publicDisk = config('filesystems.default', 'public');
        $disk = $isPrivado ? $privateDisk : $publicDisk;
        $directory = sprintf('expedientes/%s/anexos', $expediente->getKey());
        $originalExtension = strtolower((string) $file->getClientOriginalExtension());
        $extension = $originalExtension !== '' ? $originalExtension : 'bin';
        $filename = sprintf('%s-%s.%s', Str::uuid(), now()->format('YmdHis'), $extension);

        $storedPath = $file->storeAs($directory, $filename, $disk);

        $titulo = $this->buildTitulo($file->getClientOriginalName());
        $tipo = $originalExtension !== '' ? $originalExtension : $file->getClientMimeType();

        $anexo = $expediente->anexos()->create([
            'titulo' => $titulo,
            'tipo' => $tipo,
            'ruta' => $storedPath,
            'disk' => $disk,
            'es_privado' => $isPrivado,
            'tamano' => $file->getSize() ?? 0,
            'subido_por' => $request->user()->id,
        ]);

        $anexo->load('subidoPor');

        $downloadUrl = URL::temporarySignedRoute(
            'expedientes.anexos.show',
            now()->addMinutes(30),
            [$expediente, $anexo]
        );

        $this->timelineLogger->log($expediente, 'anexo.subido', $request->user(), [
            'anexo_id' => $anexo->getKey(),
            'titulo' => $anexo->titulo,
            'tipo' => $anexo->tipo,
            'disk' => $anexo->disk,
        ]);

        $payload = [
            'id' => $anexo->getKey(),
            'titulo' => $anexo->titulo,
            'tipo' => $anexo->tipo,
            'tamano' => $anexo->tamano,
            'tamano_legible' => $this->formatSize($anexo->tamano),
            'subido_por' => $anexo->subidoPor?->name,
            'fecha' => optional($anexo->created_at)->format('Y-m-d H:i'),
            'delete_url' => route('expedientes.anexos.destroy', [$expediente, $anexo]),
            'download_url' => $downloadUrl,
            'message' => 'Anexo subido correctamente.',
        ];

        if ($request->expectsJson()) {
            return response()->json($payload, 201);
        }

        return redirect()
            ->route('expedientes.show', $expediente)
            ->with('status', $payload['message']);
    }

    public function destroy(Request $request, Expediente $expediente, Anexo $anexo): JsonResponse|RedirectResponse
    {
        $this->ensureAnexoBelongsToExpediente($expediente, $anexo);

        $this->authorize('delete', $anexo);

        $disk = $anexo->disk ?: config('filesystems.private_default', 'private');

        if ($anexo->ruta && Storage::disk($disk)->exists($anexo->ruta)) {
            Storage::disk($disk)->delete($anexo->ruta);
        }

        $payload = [
            'anexo_id' => $anexo->getKey(),
            'titulo' => $anexo->titulo,
            'tipo' => $anexo->tipo,
        ];

        $anexo->delete();

        $this->timelineLogger->log($expediente, 'anexo.eliminado', $request->user(), $payload);

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'ok',
                'message' => 'Anexo eliminado correctamente.',
            ]);
        }

        $redirectQuery = collect($request->only(['titulo', 'tipo']))
            ->map(fn ($value) => is_string($value) ? trim($value) : '')
            ->filter(fn ($value) => $value !== '')
            ->all();

        $redirectQuery['tab'] = $request->input('tab', 'anexos');

        return redirect()
            ->route('expedientes.show', array_merge(['expediente' => $expediente], $redirectQuery))
            ->with('status', 'Anexo eliminado correctamente.');
    }

    public function show(Request $request, Expediente $expediente, Anexo $anexo)
    {
        $this->ensureAnexoBelongsToExpediente($expediente, $anexo);

        $this->authorize('view', $anexo);

        $disk = $anexo->disk ?: config('filesystems.private_default', 'private');

        if (! $anexo->ruta || ! Storage::disk($disk)->exists($anexo->ruta)) {
            abort(404);
        }

        $downloadName = $this->buildDownloadName($anexo);

        return Storage::disk($disk)->download($anexo->ruta, $downloadName);
    }

    private function ensureAnexoBelongsToExpediente(Expediente $expediente, Anexo $anexo): void
    {
        abort_if((int) $anexo->expediente_id !== (int) $expediente->getKey(), 404);
    }

    private function buildTitulo(string $originalName): string
    {
        $name = trim(pathinfo($originalName, PATHINFO_FILENAME));

        return $name !== '' ? $name : $originalName;
    }

    private function formatSize(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0.0';
        }

        return number_format($bytes / 1024, 1);
    }

    private function buildDownloadName(Anexo $anexo): string
    {
        $extension = pathinfo($anexo->ruta ?? '', PATHINFO_EXTENSION);
        $extension = $extension !== '' ? '.'.$extension : '';

        return trim($anexo->titulo.$extension) ?: basename($anexo->ruta ?? 'archivo');
    }

    private function respondWithError(Request $request, string $message, int $status): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
            ], $status);
        }

        return redirect()->back()->withErrors(['archivo' => $message]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\ObserveSesionRequest;
use App\Http\Requests\StoreSesionRequest;
use App\Http\Requests\UpdateSesionRequest;
use App\Http\Requests\ValidateSesionRequest;
use App\Models\Expediente;
use App\Models\Sesion;
use App\Models\User;
use App\Notifications\SesionObservedNotification;
use App\Notifications\SesionValidatedNotification;
use App\Services\TimelineLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class SesionController extends Controller
{
    public function __construct(private TimelineLogger $timelineLogger)
    {
    }

    public function index(Request $request, Expediente $expediente): View
    {
        $this->authorize('view', $expediente);

        $sesiones = $expediente->sesiones()
            ->with(['realizadaPor', 'validadaPor'])
            ->orderByDesc('fecha')
            ->paginate(10)
            ->withQueryString();

        return view('sesiones.index', [
            'expediente' => $expediente,
            'sesiones' => $sesiones,
        ]);
    }

    public function create(Request $request, Expediente $expediente): View
    {
        $this->authorize('create', [Sesion::class, $expediente]);

        $sesion = new Sesion([
            'fecha' => Carbon::today(),
        ]);
        $sesion->setRelation('adjuntos', collect());

        return view('sesiones.create', [
            'expediente' => $expediente,
            'sesion' => $sesion,
        ]);
    }

    public function store(StoreSesionRequest $request, Expediente $expediente): RedirectResponse
    {
        $this->authorize('create', [Sesion::class, $expediente]);

        $data = $request->validatedSesionData();

        $sesion = $expediente->sesiones()->create($data + [
            'realizada_por' => $request->user()->id,
            'status_revision' => 'pendiente',
        ]);

        $this->syncAdjuntos(
            $sesion,
            Arr::wrap($request->file('adjuntos')),
            [],
            $request->user()->id,
        );

        $this->timelineLogger->log($expediente, 'sesion.creada', $request->user(), [
            'sesion_id' => $sesion->id,
            'estado_anterior' => null,
            'estado_nuevo' => 'pendiente',
            'observaciones' => null,
        ]);

        return redirect()
            ->route('expedientes.sesiones.show', [$expediente, $sesion])
            ->with('status', 'Sesión registrada correctamente.');
    }

    public function show(Expediente $expediente, Sesion $sesion): View
    {
        $this->ensureSesionBelongsToExpediente($expediente, $sesion);

        $this->authorize('view', $sesion);

        $sesion->load(['realizadaPor', 'validadaPor', 'expediente', 'adjuntos.subidoPor']);

        $historialRevision = $expediente->timelineEventos()
            ->where('payload->sesion_id', $sesion->id)
            ->with('actor')
            ->orderByDesc('created_at')
            ->get();

        return view('sesiones.show', [
            'expediente' => $expediente,
            'sesion' => $sesion,
            'historialRevision' => $historialRevision,
        ]);
    }

    public function edit(Expediente $expediente, Sesion $sesion): View
    {
        $this->ensureSesionBelongsToExpediente($expediente, $sesion);

        $this->authorize('update', $sesion);

        $sesion->loadMissing('adjuntos.subidoPor');

        return view('sesiones.edit', [
            'expediente' => $expediente,
            'sesion' => $sesion,
        ]);
    }

    public function update(UpdateSesionRequest $request, Expediente $expediente, Sesion $sesion): RedirectResponse
    {
        $this->ensureSesionBelongsToExpediente($expediente, $sesion);

        $this->authorize('update', $sesion);

        $data = $request->validatedSesionData();

        $sesion->fill($data);

        if ($sesion->status_revision !== 'pendiente') {
            $sesion->status_revision = 'pendiente';
            $sesion->validada_por = null;
        }

        $sesion->save();

        $this->syncAdjuntos(
            $sesion,
            Arr::wrap($request->file('adjuntos')),
            $request->input('adjuntos_eliminar', []),
            $request->user()->id,
        );

        return redirect()
            ->route('expedientes.sesiones.show', [$expediente, $sesion])
            ->with('status', 'Sesión actualizada correctamente.');
    }

    public function observe(ObserveSesionRequest $request, Expediente $expediente, Sesion $sesion): RedirectResponse
    {
        $this->ensureSesionBelongsToExpediente($expediente, $sesion);

        $this->authorize('observe', $sesion);

        if ($sesion->status_revision === 'validada') {
            return redirect()
                ->route('expedientes.sesiones.show', [$expediente, $sesion])
                ->withErrors(['observaciones' => 'La sesión ya fue validada y no puede ser observada.'])
                ->withInput($request->only('observaciones', 'form_action'));
        }

        $estadoAnterior = $sesion->status_revision;

        $sesion->status_revision = 'observada';
        $sesion->validada_por = null;
        $sesion->save();

        $this->timelineLogger->log($expediente, 'sesion.observada', $request->user(), [
            'sesion_id' => $sesion->id,
            'estado_anterior' => $estadoAnterior,
            'estado_nuevo' => 'observada',
            'observaciones' => $request->observation(),
        ]);

        $this->sesionRecipients($sesion)
            ->each(fn (User $user) => $user->notify(new SesionObservedNotification($sesion, $request->user(), $request->observation())));

        return redirect()
            ->route('expedientes.sesiones.show', [$expediente, $sesion])
            ->with('status', 'Sesión marcada como observada correctamente.');
    }

    public function validateSesion(ValidateSesionRequest $request, Expediente $expediente, Sesion $sesion): RedirectResponse
    {
        $this->ensureSesionBelongsToExpediente($expediente, $sesion);

        $this->authorize('validate', $sesion);

        if ($sesion->status_revision === 'validada') {
            return redirect()
                ->route('expedientes.sesiones.show', [$expediente, $sesion])
                ->with('status', 'La sesión ya se encuentra validada.');
        }

        $estadoAnterior = $sesion->status_revision;

        $sesion->status_revision = 'validada';
        $sesion->validada_por = $request->user()->id;
        $sesion->save();

        $this->timelineLogger->log($expediente, 'sesion.validada', $request->user(), [
            'sesion_id' => $sesion->id,
            'estado_anterior' => $estadoAnterior,
            'estado_nuevo' => 'validada',
            'observaciones' => $request->observation(),
        ]);

        $this->sesionRecipients($sesion)
            ->each(fn (User $user) => $user->notify(new SesionValidatedNotification($sesion, $request->user(), $request->observation())));

        return redirect()
            ->route('expedientes.sesiones.show', [$expediente, $sesion])
            ->with('status', 'Sesión validada correctamente.');
    }

    public function destroy(Expediente $expediente, Sesion $sesion): RedirectResponse
    {
        $this->ensureSesionBelongsToExpediente($expediente, $sesion);

        $this->authorize('delete', $sesion);

        $this->deleteAdjuntos($sesion);

        $sesion->delete();

        return redirect()
            ->route('expedientes.sesiones.index', $expediente)
            ->with('status', 'Sesión eliminada correctamente.');
    }

    private function ensureSesionBelongsToExpediente(Expediente $expediente, Sesion $sesion): void
    {
        abort_if((int) $sesion->expediente_id !== (int) $expediente->id, 404);
    }

    /**
     * @param  array<int, UploadedFile|null>  $adjuntos
     * @param  array<int, int>  $adjuntosEliminar
     */
    private function syncAdjuntos(Sesion $sesion, array $adjuntos, array $adjuntosEliminar, int $userId): void
    {
        if (! empty($adjuntosEliminar)) {
            $sesion->adjuntos()
                ->whereIn('id', $adjuntosEliminar)
                ->get()
                ->each(function ($adjunto): void {
                    Storage::disk('public')->delete($adjunto->ruta);
                    $adjunto->delete();
                });
        }

        Collection::make($adjuntos)
            ->filter(fn ($file) => $file instanceof UploadedFile)
            ->each(function (UploadedFile $file) use ($sesion, $userId): void {
                $path = $file->store("sesiones/{$sesion->id}", 'public');

                $sesion->adjuntos()->create([
                    'nombre_original' => $file->getClientOriginalName(),
                    'ruta' => $path,
                    'mime_type' => $file->getClientMimeType(),
                    'tamano' => $file->getSize(),
                    'subido_por' => $userId,
                ]);
            });
    }

    private function deleteAdjuntos(Sesion $sesion): void
    {
        $sesion->loadMissing('adjuntos');

        $sesion->adjuntos->each(function ($adjunto): void {
            Storage::disk('public')->delete($adjunto->ruta);
        });

        $sesion->adjuntos()->delete();
    }

    /**
     * @return Collection<int, User>
     */
    private function sesionRecipients(Sesion $sesion): Collection
    {
        $sesion->loadMissing(['realizadaPor', 'expediente.tutor', 'expediente.creadoPor', 'expediente.coordinador']);

        $expediente = $sesion->expediente;

        return collect([
            $sesion->realizadaPor,
            $expediente?->tutor,
            $expediente?->creadoPor,
            $expediente?->coordinador,
        ])->filter()
            ->unique(fn (User $user) => $user->id)
            ->values();
    }
}

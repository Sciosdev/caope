<?php

namespace App\Http\Controllers;

use App\Models\Comentario;
use App\Models\Expediente;
use App\Models\Sesion;
use App\Services\TimelineLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ComentarioController extends Controller
{
    /** @var array<string, class-string<Model>> */
    private const COMENTABLE_TYPES = [
        'expediente' => Expediente::class,
        'sesion' => Sesion::class,
    ];

    public function __construct(private TimelineLogger $timelineLogger)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'comentable_type' => ['required', Rule::in(array_keys(self::COMENTABLE_TYPES))],
            'comentable_id' => ['required', 'integer', 'min:1'],
        ]);

        $comentable = $this->resolveComentable($data['comentable_type'], (int) $data['comentable_id']);

        $this->authorize('view', $comentable);

        $comentarios = $comentable->comentarios()
            ->with('autor')
            ->get();

        return response()->json([
            'data' => $comentarios,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'comentable_type' => ['required', Rule::in(array_keys(self::COMENTABLE_TYPES))],
            'comentable_id' => ['required', 'integer', 'min:1'],
            'contenido' => ['required', 'string'],
        ]);

        $comentable = $this->resolveComentable($data['comentable_type'], (int) $data['comentable_id']);

        $this->authorize('create', [Comentario::class, $comentable]);

        $comentario = $comentable->comentarios()->create([
            'user_id' => $request->user()->id,
            'contenido' => $data['contenido'],
        ]);

        $comentario->load('autor');

        $expediente = $this->resolveExpedienteFromComentable($comentable);

        $this->timelineLogger->log($expediente, 'comentario.creado', $request->user(), [
            'comentario_id' => $comentario->id,
            'comentable_type' => $comentario->comentable_type,
            'comentable_id' => $comentario->comentable_id,
        ]);

        return response()->json([
            'data' => $comentario,
        ], 201);
    }

    public function show(Comentario $comentario): JsonResponse
    {
        $comentario->loadMissing(['comentable', 'autor']);

        $this->authorize('view', $comentario);

        return response()->json([
            'data' => $comentario,
        ]);
    }

    public function update(Request $request, Comentario $comentario): JsonResponse
    {
        $data = $request->validate([
            'contenido' => ['required', 'string'],
        ]);

        $comentario->loadMissing('comentable');

        $this->authorize('update', $comentario);

        $comentario->contenido = $data['contenido'];
        $comentario->save();

        $comentario->load('autor');

        $expediente = $this->resolveExpedienteFromComentable($comentario->comentable);

        $this->timelineLogger->log($expediente, 'comentario.actualizado', $request->user(), [
            'comentario_id' => $comentario->id,
            'comentable_type' => $comentario->comentable_type,
            'comentable_id' => $comentario->comentable_id,
        ]);

        return response()->json([
            'data' => $comentario,
        ]);
    }

    public function destroy(Request $request, Comentario $comentario): JsonResponse
    {
        $comentario->loadMissing('comentable');

        $this->authorize('delete', $comentario);

        $expediente = $this->resolveExpedienteFromComentable($comentario->comentable);

        $payload = [
            'comentario_id' => $comentario->id,
            'comentable_type' => $comentario->comentable_type,
            'comentable_id' => $comentario->comentable_id,
        ];

        $comentario->delete();

        $this->timelineLogger->log($expediente, 'comentario.eliminado', $request->user(), $payload);

        return response()->noContent();
    }

    private function resolveComentable(string $typeAlias, int $id): Model
    {
        $class = self::COMENTABLE_TYPES[$typeAlias] ?? null;

        abort_if($class === null, 422, 'Tipo de comentable no soportado.');

        /** @var Model|null $comentable */
        $comentable = $class::query()->find($id);

        abort_if($comentable === null, 404, 'El recurso especificado no existe.');

        return $comentable;
    }

    private function resolveExpedienteFromComentable(Model $comentable): Expediente
    {
        if ($comentable instanceof Expediente) {
            return $comentable;
        }

        if ($comentable instanceof Sesion) {
            $comentable->loadMissing('expediente');

            if ($comentable->expediente instanceof Expediente) {
                return $comentable->expediente;
            }
        }

        abort(422, 'No es posible registrar actividad para este comentario.');
    }
}

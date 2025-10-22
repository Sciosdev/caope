<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

abstract class CatalogoController extends Controller
{
    /**
     * The fully qualified model class that the controller manages.
     */
    protected string $modelClass;

    /**
     * View namespace used to render catalog pages.
     */
    protected string $viewPrefix = 'admin.catalogos';

    /**
     * Route name prefix used to generate responses.
     */
    protected string $routePrefix;

    /**
     * Singular label for the managed resource.
     */
    protected string $resourceName;

    /**
     * Plural label for the managed resource.
     */
    protected string $resourceNamePlural;

    public function __construct()
    {
        $this->middleware('role:admin');
    }

    public function index(): View
    {
        $items = $this->newModelQuery()->orderBy('nombre')->paginate(15);

        return view($this->viewPrefix . '.index', $this->baseViewData([
            'items' => $items,
        ]));
    }

    public function create(): View
    {
        return view($this->viewPrefix . '.form', $this->baseViewData([
            'item' => null,
            'editing' => false,
            'formAction' => route($this->routePrefix . '.store'),
            'formMethod' => 'POST',
        ]));
    }

    public function store(Request $request): RedirectResponse
    {
        $modelClass = $this->modelClass;
        /** @var Model $item */
        $item = $modelClass::create($this->validatedData($request));

        $this->flushCatalogoCache();

        return Redirect::route($this->routePrefix . '.index')->with('status', __(
            ':resource creado correctamente.',
            ['resource' => $this->resourceName]
        ));
    }

    public function edit(int|string $id): View
    {
        $item = $this->findModel($id);

        return view($this->viewPrefix . '.form', $this->baseViewData([
            'item' => $item,
            'editing' => true,
            'formAction' => route($this->routePrefix . '.update', $item),
            'formMethod' => 'PUT',
        ]));
    }

    public function update(Request $request, int|string $id): RedirectResponse
    {
        $item = $this->findModel($id);

        $item->update($this->validatedData($request, $item));

        $this->flushCatalogoCache();

        return Redirect::route($this->routePrefix . '.index')->with('status', __(
            ':resource actualizado correctamente.',
            ['resource' => $this->resourceName]
        ));
    }

    public function destroy(int|string $id): RedirectResponse
    {
        $item = $this->findModel($id);

        if ($item->activo) {
            $item->forceFill(['activo' => false])->save();
        }

        $this->flushCatalogoCache();

        return Redirect::route($this->routePrefix . '.index')->with('status', __(
            ':resource desactivado correctamente.',
            ['resource' => $this->resourceName]
        ));
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function newModelQuery()
    {
        $modelClass = $this->modelClass;

        return $modelClass::query();
    }

    protected function findModel(int|string $id): Model
    {
        $modelClass = $this->modelClass;

        return $modelClass::query()->findOrFail($id);
    }

    protected function validatedData(Request $request, ?Model $item = null): array
    {
        $model = $item ?? $this->makeModelInstance();

        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:255', $this->uniqueRule($model)],
            'activo' => ['nullable', 'boolean'],
        ]);

        $validated['activo'] = array_key_exists('activo', $validated)
            ? (bool) $validated['activo']
            : ($item ? (bool) $item->activo : true);

        return $validated;
    }

    protected function uniqueRule(Model $model)
    {
        $rule = Rule::unique($model->getTable(), 'nombre');

        if ($model->exists) {
            $rule->ignore($model->getKey());
        }

        return $rule;
    }

    protected function makeModelInstance(): Model
    {
        $modelClass = $this->modelClass;

        return new $modelClass();
    }

    protected function baseViewData(array $data = []): array
    {
        return array_merge([
            'resourceName' => $this->resourceName,
            'resourceNamePlural' => $this->resourceNamePlural,
            'routePrefix' => $this->routePrefix,
        ], $data);
    }

    protected function flushCatalogoCache(): void
    {
        $modelClass = $this->modelClass;

        if (method_exists($modelClass, 'flushCache')) {
            $modelClass::flushCache();
        }
    }
}

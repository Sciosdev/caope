<?php

namespace App\Http\Controllers\Admin;

use App\Models\CatalogoCubiculo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CatalogoCubiculoController extends CatalogoController
{
    protected string $modelClass = CatalogoCubiculo::class;

    protected string $routePrefix = 'admin.catalogos.cubiculos';

    protected string $resourceName = 'Cubículo';

    protected string $resourceNamePlural = 'Cubículos';

    protected function validatedData(Request $request, ?Model $item = null): array
    {
        $validated = parent::validatedData($request, $item);

        $validated += $request->validate([
            'numero' => ['required', 'integer', 'between:1,99', $this->uniqueNumeroRule($item)],
        ]);

        return $validated;
    }

    protected function uniqueNumeroRule(?Model $item = null)
    {
        $rule = Rule::unique('catalogo_cubiculos', 'numero');

        if ($item?->exists) {
            $rule->ignore($item->getKey());
        }

        return $rule;
    }
}


<?php

namespace App\Http\Controllers\Admin;

use App\Models\CatalogoConsultorio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CatalogoConsultorioController extends CatalogoController
{
    protected string $modelClass = CatalogoConsultorio::class;

    protected string $routePrefix = 'admin.catalogos.consultorios';

    protected string $resourceName = 'Consultorio';

    protected string $resourceNamePlural = 'Consultorios';

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
        $rule = Rule::unique('catalogo_consultorios', 'numero');

        if ($item?->exists) {
            $rule->ignore($item->getKey());
        }

        return $rule;
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Models\CatalogoEstrategia;

class CatalogoEstrategiaController extends CatalogoController
{
    protected string $modelClass = CatalogoEstrategia::class;

    protected string $routePrefix = 'admin.catalogos.estrategias';

    protected string $resourceName = 'Estrategia';

    protected string $resourceNamePlural = 'Estrategias';
}

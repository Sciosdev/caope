<?php

namespace App\Http\Controllers\Admin;

use App\Models\CatalogoCarrera;

class CatalogoCarreraController extends CatalogoController
{
    protected string $modelClass = CatalogoCarrera::class;

    protected string $routePrefix = 'admin.catalogos.carreras';

    protected string $resourceName = 'Carrera';

    protected string $resourceNamePlural = 'Carreras';
}

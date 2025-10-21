<?php

namespace App\Http\Controllers\Admin;

use App\Models\CatalogoTratamiento;

class CatalogoTratamientoController extends CatalogoController
{
    protected string $modelClass = CatalogoTratamiento::class;

    protected string $routePrefix = 'admin.catalogos.tratamientos';

    protected string $resourceName = 'Tratamiento';

    protected string $resourceNamePlural = 'Tratamientos';
}

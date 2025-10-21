<?php

namespace App\Http\Controllers\Admin;

use App\Models\CatalogoTurno;

class CatalogoTurnoController extends CatalogoController
{
    protected string $modelClass = CatalogoTurno::class;

    protected string $routePrefix = 'admin.catalogos.turnos';

    protected string $resourceName = 'Turno';

    protected string $resourceNamePlural = 'Turnos';
}

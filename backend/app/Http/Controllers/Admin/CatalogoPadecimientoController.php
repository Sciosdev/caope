<?php

namespace App\Http\Controllers\Admin;

use App\Models\CatalogoPadecimiento;

class CatalogoPadecimientoController extends CatalogoController
{
    protected string $modelClass = CatalogoPadecimiento::class;

    protected string $routePrefix = 'admin.catalogos.padecimientos';

    protected string $resourceName = 'Padecimiento';

    protected string $resourceNamePlural = 'Padecimientos';
}

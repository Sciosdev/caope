<?php

namespace App\Http\Controllers\Admin;

use App\Models\CatalogoConsultorio;
use App\Models\ConsultorioReserva;
use App\Models\ConsultorioReservaSolicitud;
use App\Models\Expediente;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CatalogoConsultorioController extends CatalogoController
{
    protected string $modelClass = CatalogoConsultorio::class;

    protected string $routePrefix = 'admin.catalogos.consultorios';

    protected string $resourceName = 'Cubículo';

    protected string $resourceNamePlural = 'Cubículos';

    public function update(Request $request, int|string $id): RedirectResponse
    {
        DB::transaction(function () use ($request, $id): void {
            $item = CatalogoConsultorio::query()->lockForUpdate()->findOrFail($id);
            $data = $this->validatedData($request, $item);

            if ((int) $data['numero'] !== (int) $item->numero && $this->isReferenced($item)) {
                throw ValidationException::withMessages([
                    'catalogo' => 'No se puede cambiar el número de un cubículo con reservas, solicitudes o expedientes vinculados. Puedes cambiar su nombre o desactivarlo.',
                ]);
            }

            $item->update($data);
        }, 3);

        $this->flushCatalogoCache();

        return redirect()->route($this->routePrefix.'.index')->with('status', 'Cubículo actualizado correctamente.');
    }

    public function forceDestroy(int|string $id): RedirectResponse
    {
        DB::transaction(function () use ($id): void {
            $item = CatalogoConsultorio::query()->lockForUpdate()->findOrFail($id);

            if ($this->isReferenced($item)) {
                throw ValidationException::withMessages([
                    'catalogo' => 'No se puede eliminar este cubículo porque tiene reservas, solicitudes o expedientes vinculados. Desactívalo para conservar el historial.',
                ]);
            }

            $item->delete();
        }, 3);

        $this->flushCatalogoCache();

        return redirect()->route($this->routePrefix.'.index')->with('status', 'Cubículo eliminado correctamente.');
    }

    private function isReferenced(CatalogoConsultorio $item): bool
    {
        if (ConsultorioReserva::query()->where('consultorio_numero', $item->numero)->lockForUpdate()->first(['id']) !== null) {
            return true;
        }

        if (Schema::hasColumn('expedientes', 'cubiculo_id')
            && Expediente::withTrashed()->where('cubiculo_id', $item->getKey())->lockForUpdate()->first(['id']) !== null) {
            return true;
        }

        return ConsultorioReservaSolicitud::query()
            ->whereIn('payload->consultorio_numero', [$item->numero, (string) $item->numero])
            ->lockForUpdate()->first(['id']) !== null;
    }

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

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Parametro;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;

class ParametrosController extends Controller
{
    public function index(): View
    {
        $parametros = Parametro::query()
            ->orderBy('clave')
            ->get();

        return view('admin.parametros.index', [
            'parametros' => $parametros,
            'metadata' => $this->definitions(),
        ]);
    }

    public function update(Request $request, Parametro $parametro): RedirectResponse
    {
        $errorBag = sprintf('parametro-%s', $parametro->getKey());

        $validated = $request->validateWithBag($errorBag, [
            'valor' => $this->rulesFor($parametro),
        ], [], [
            'valor' => 'valor',
        ]);

        $parametro->valor = $validated['valor'];
        $parametro->save();

        Parametro::forget($parametro->clave);
        Artisan::call('config:clear');

        return redirect()
            ->route('admin.parametros.index')
            ->with('status', 'Parámetro actualizado correctamente.');
    }

    /**
     * @return list<string>
     */
    private function rulesFor(Parametro $parametro): array
    {
        return match ($parametro->tipo) {
            Parametro::TYPE_INTEGER => ['required', 'integer'],
            default => ['required', 'string'],
        };
    }

    /**
     * @return array<string, array{label: string, description?: string, input?: string}>
     */
    private function definitions(): array
    {
        return [
            'uploads.anexos.mimes' => [
                'label' => 'Formatos permitidos para anexos',
                'description' => 'Lista separada por comas de extensiones aceptadas al subir anexos.',
            ],
            'uploads.anexos.max' => [
                'label' => 'Tamaño máximo de anexos (KB)',
                'description' => 'Límite máximo en kilobytes por archivo adjunto.',
            ],
            'uploads.consentimientos.mimes' => [
                'label' => 'Formatos permitidos para consentimientos',
                'description' => 'Extensiones separadas por comas para los archivos de consentimiento.',
            ],
            'uploads.consentimientos.max' => [
                'label' => 'Tamaño máximo de consentimientos (KB)',
                'description' => 'Límite de tamaño en kilobytes para los consentimientos firmados.',
            ],
            'consentimientos.texto_introduccion' => [
                'label' => 'Texto de introducción para consentimientos',
                'description' => 'Se muestra en la parte inicial de los documentos e impresiones de consentimiento.',
                'input' => 'textarea',
            ],
            'consentimientos.texto_cierre' => [
                'label' => 'Texto de cierre para consentimientos',
                'description' => 'Mensaje final que acompaña los documentos de consentimiento.',
                'input' => 'textarea',
            ],
        ];
    }
}

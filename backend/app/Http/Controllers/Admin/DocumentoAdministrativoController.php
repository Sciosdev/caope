<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DocumentoAdministrativo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentoAdministrativoController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $isAdmin = $user?->hasRole('admin') ?? false;
        $isPaps = $user?->hasRole('paps') ?? false;

        $documentos = DocumentoAdministrativo::query()
            ->with('subidoPor:id,name')
            ->when(! $isAdmin, fn ($q) => $q->whereNotNull('aprobado_en'))
            ->latest()
            ->get();

        return view('admin.documentos.index', compact('documentos', 'isAdmin', 'isPaps'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'titulo' => ['required', 'string', 'max:200'],
            'archivo' => ['required', 'file', 'max:10240'],
        ]);

        $user = $request->user();
        $path = $request->file('archivo')->store('documentos-administrativos', config('filesystems.private_default', 'private'));
        $isAdmin = $user?->hasRole('admin') ?? false;

        DocumentoAdministrativo::create([
            'titulo' => $data['titulo'],
            'ruta' => $path,
            'disk' => config('filesystems.private_default', 'private'),
            'mime_type' => $request->file('archivo')->getClientMimeType(),
            'tamano' => $request->file('archivo')->getSize(),
            'subido_por' => $user?->id,
            'aprobado_en' => $isAdmin ? now() : null,
            'aprobado_por' => $isAdmin ? $user?->id : null,
        ]);

        return back()->with('status', $isAdmin ? 'Documento agregado correctamente.' : 'Documento enviado para autorización del administrador general.');
    }

    public function approve(Request $request, DocumentoAdministrativo $documento): RedirectResponse
    {
        $documento->update([
            'aprobado_en' => now(),
            'aprobado_por' => $request->user()?->id,
        ]);

        return back()->with('status', 'Documento autorizado correctamente.');
    }

    public function download(DocumentoAdministrativo $documento)
    {
        $user = request()->user();
        $isAdmin = $user?->hasRole('admin') ?? false;
        if (! $isAdmin && is_null($documento->aprobado_en)) {
            abort(403);
        }

        return Storage::disk($documento->disk ?: config('filesystems.private_default', 'private'))
            ->download($documento->ruta, $documento->titulo);
    }
}

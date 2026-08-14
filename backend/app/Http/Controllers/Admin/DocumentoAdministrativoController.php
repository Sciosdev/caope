<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DocumentoAdministrativo;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentoAdministrativoController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $this->ensureCanViewDocuments($user);

        $isAdmin = $user?->hasRole('admin') ?? false;
        $isPaps = $user?->isApprovedPaps() ?? false;

        $documentos = DocumentoAdministrativo::query()
            ->with('subidoPor:id,name')
            ->when(! $isAdmin, fn ($q) => $q->whereNotNull('aprobado_en'))
            ->latest()
            ->get();

        return view('admin.documentos.index', compact('documentos', 'isAdmin', 'isPaps'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(
            $request->user()?->hasRole('admin') || $request->user()?->isApprovedPaps(),
            403
        );

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

    public function update(Request $request, DocumentoAdministrativo $documento): RedirectResponse
    {
        $data = $request->validate([
            'titulo' => ['required', 'string', 'max:200'],
            'archivo' => ['nullable', 'file', 'max:10240'],
        ]);

        $updates = ['titulo' => $data['titulo']];

        if ($request->hasFile('archivo')) {
            $disk = $documento->disk ?: config('filesystems.private_default', 'private');
            if ($documento->ruta && Storage::disk($disk)->exists($documento->ruta)) {
                Storage::disk($disk)->delete($documento->ruta);
            }

            $nuevoPath = $request->file('archivo')->store('documentos-administrativos', config('filesystems.private_default', 'private'));
            $updates['ruta'] = $nuevoPath;
            $updates['disk'] = config('filesystems.private_default', 'private');
            $updates['mime_type'] = $request->file('archivo')->getClientMimeType();
            $updates['tamano'] = $request->file('archivo')->getSize();
            $updates['aprobado_en'] = null;
            $updates['aprobado_por'] = null;
        }

        $documento->update($updates);

        return back()->with('status', 'Documento actualizado correctamente.');
    }

    public function destroy(DocumentoAdministrativo $documento): RedirectResponse
    {
        $disk = $documento->disk ?: config('filesystems.private_default', 'private');
        if ($documento->ruta && Storage::disk($disk)->exists($documento->ruta)) {
            Storage::disk($disk)->delete($documento->ruta);
        }

        $documento->delete();

        return back()->with('status', 'Documento eliminado correctamente.');
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
        $this->ensureCanViewDocuments($user);

        $isAdmin = $user?->hasRole('admin') ?? false;
        if (! $isAdmin && is_null($documento->aprobado_en)) {
            abort(403);
        }

        $extension = pathinfo((string) $documento->ruta, PATHINFO_EXTENSION);
        $filename = $documento->titulo;

        if ($extension !== '' && ! str_ends_with(strtolower($filename), '.'.strtolower($extension))) {
            $filename .= '.'.$extension;
        }

        return Storage::disk($documento->disk ?: config('filesystems.private_default', 'private'))
            ->download($documento->ruta, $filename);
    }

    private function ensureCanViewDocuments(?User $user): void
    {
        $canView = $user?->hasAnyRole(['admin', 'coordinador', 'estratega']) ?? false;

        if ($user?->hasRole('paps')) {
            $canView = $canView || $user->isApprovedPaps();
        }

        abort_unless($canView, 403);
    }
}

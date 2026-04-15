<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ConsultorioReserva;
use App\Models\ConsultorioReservaSolicitud;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class ConsultorioSolicitudController extends Controller
{
    private function hasSolicitudesTable(): bool
    {
        return Schema::hasTable('consultorio_reserva_solicitudes');
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $isAdmin = $user?->hasRole('admin') ?? false;
        $isApprovedPaps = ($user?->hasRole('paps') ?? false) && ! is_null($user?->approved_at);

        abort_unless($isAdmin || $isApprovedPaps, 403);

        $solicitudesPendientes = $this->hasSolicitudesTable()
            ? ConsultorioReservaSolicitud::query()
                ->with(['reserva', 'requestedBy'])
                ->where('status', 'pendiente')
                ->when(! $isAdmin, fn ($query) => $query->where('requested_by', $user?->id))
                ->latest()
                ->paginate(20)
            : collect();

        return view('admin.consultorios.solicitudes.index', [
            'solicitudesPendientes' => $solicitudesPendientes,
        ]);
    }

    public function approve(Request $request, ConsultorioReservaSolicitud $solicitud): RedirectResponse
    {
        abort_unless($request->user()?->hasRole('admin'), 403);
        abort_if($solicitud->status !== 'pendiente', 422, 'Esta solicitud ya fue atendida.');

        if ($solicitud->tipo === 'baja') {
            ConsultorioReserva::query()->whereKey($solicitud->consultorio_reserva_id)->toBase()->delete();
        }

        $solicitud->update(['status' => 'atendida']);

        return redirect()->route('admin.consultorios.solicitudes.index')->with('status', 'Solicitud aprobada correctamente.');
    }
}

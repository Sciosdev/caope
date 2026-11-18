<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsultorioReservaSolicitud extends Model
{
    use HasFactory;

    protected $table = 'consultorio_reserva_solicitudes';

    protected $fillable = [
        'consultorio_reserva_id',
        'requested_by',
        'tipo',
        'payload',
        'status',
    ];

    protected $casts = [
        'payload' => 'array',
        'resolucion' => 'array',
    ];

    /** Called under the request row lock, in the same transaction as the decision. */
    public function resolve(string $decision, User $actor, ?string $reason, ?array $before, ?array $after): void
    {
        $this->forceFill([
            'status' => 'atendida',
            'resolucion' => [
                'decision' => $decision,
                'actor_id' => $actor->getKey(),
                'fecha' => now()->toIso8601String(),
                'motivo' => $reason,
                'reserva_id' => $this->consultorio_reserva_id,
                'antes' => $before,
                'despues' => $after,
            ],
        ])->save();
    }

    public function reserva(): BelongsTo
    {
        return $this->belongsTo(ConsultorioReserva::class, 'consultorio_reserva_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}

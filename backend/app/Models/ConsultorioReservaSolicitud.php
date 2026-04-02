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
    ];

    public function reserva(): BelongsTo
    {
        return $this->belongsTo(ConsultorioReserva::class, 'consultorio_reserva_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}

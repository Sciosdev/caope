<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConsultorioReserva extends Model
{
    use HasFactory;

    protected $fillable = [
        'fecha',
        'hora_inicio',
        'hora_fin',
        'consultorio_numero',
        'cubiculo_numero',
        'estrategia',
        'usuario_atendido_id',
        'estratega_id',
        'supervisor_id',
        'creado_por',
        'origen_expediente',
    ];

    protected $casts = [
        'fecha' => 'date:Y-m-d',
        'origen_expediente' => 'boolean',
    ];

    public function usuarioAtendido(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_atendido_id');
    }

    public function estratega(): BelongsTo
    {
        return $this->belongsTo(User::class, 'estratega_id');
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function solicitudes(): HasMany
    {
        return $this->hasMany(ConsultorioReservaSolicitud::class, 'consultorio_reserva_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expediente extends Model
{
    use HasFactory;

    protected $fillable = [
        'no_control',
        'paciente',
        'estado',
        'apertura',
        'carrera_id',
        'turno_id',
    ];

    protected $casts = [
        'apertura' => 'date',
    ];

    public function carrera(): BelongsTo
    {
        return $this->belongsTo(CatalogoCarrera::class);
    }

    public function turno(): BelongsTo
    {
        return $this->belongsTo(CatalogoTurno::class);
    }
}

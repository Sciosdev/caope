<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegistroUrgencia extends Model
{
    use HasFactory;

    protected $table = 'registro_urgencias';

    protected $fillable = [
        'expediente_id',
        'nivel_riesgo',
        'motivo',
        'canalizacion_inmediata',
        'observaciones',
    ];

    protected $casts = [
        'canalizacion_inmediata' => 'boolean',
    ];

    public function expediente(): BelongsTo
    {
        return $this->belongsTo(Expediente::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

class Expediente extends Model
{
    use HasFactory;

    protected $fillable = [
        'no_control',
        'paciente',
        'estado',
        'apertura',
        'carrera',
        'turno',
        'creado_por',
        'tutor_id',
        'coordinador_id',
    ];

    protected $casts = [
        'apertura' => 'date',
    ];

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function tutor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tutor_id');
    }

    public function coordinador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coordinador_id');
    }
}

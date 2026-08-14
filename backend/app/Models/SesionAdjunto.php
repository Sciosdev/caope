<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SesionAdjunto extends Model
{
    use HasFactory;

    protected $table = 'sesion_adjuntos';

    protected $guarded = [];

    protected $appends = ['url'];

    protected $hidden = ['ruta', 'disk'];

    public function sesion(): BelongsTo
    {
        return $this->belongsTo(Sesion::class);
    }

    public function subidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subido_por');
    }

    public function getUrlAttribute(): string
    {
        return route('expedientes.sesiones.adjuntos.download', [
            $this->sesion?->expediente_id,
            $this->sesion_id,
            $this->getKey(),
        ]);
    }
}

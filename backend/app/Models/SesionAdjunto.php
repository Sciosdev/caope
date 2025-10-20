<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class SesionAdjunto extends Model
{
    use HasFactory;

    protected $table = 'sesion_adjuntos';

    protected $guarded = [];

    protected $appends = ['url'];

    protected $hidden = ['ruta'];

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
        return Storage::disk('public')->url($this->ruta);
    }
}

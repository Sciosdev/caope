<?php

namespace App\Models;

use App\Casts\SafeDate;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Sesion extends Model
{
    use HasFactory;

    protected $table = 'sesiones';

    protected $guarded = [];

    protected $casts = [
        'fecha' => SafeDate::class,
    ];

    public function expediente(): BelongsTo
    {
        return $this->belongsTo(Expediente::class);
    }

    public function realizadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'realizada_por');
    }

    public function validadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validada_por');
    }

    public function adjuntos(): HasMany
    {
        return $this->hasMany(SesionAdjunto::class);
    }

    public function comentarios(): MorphMany
    {
        return $this->morphMany(Comentario::class, 'comentable')->latest('created_at');
    }
}

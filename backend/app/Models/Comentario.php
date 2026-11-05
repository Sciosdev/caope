<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Comentario extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'contenido',
    ];

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function comentable(): MorphTo
    {
        // Los comentarios forman parte del expediente clínico retenido. La
        // política decide quién puede leerlos; la relación debe poder resolver
        // el expediente archivado para no perder su historial.
        return $this->morphTo()->withTrashed();
    }
}

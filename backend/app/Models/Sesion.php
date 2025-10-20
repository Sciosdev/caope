<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sesion extends Model
{
    use HasFactory;

    protected $table = 'sesiones';

    protected $guarded = [];

    protected $casts = [
        'fecha' => 'date',
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
}

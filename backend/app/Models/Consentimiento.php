<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Consentimiento extends Model
{
    use HasFactory;

    protected $table = 'consentimientos';

    protected $guarded = [];

    protected $casts = [
        'requerido' => 'boolean',
        'aceptado' => 'boolean',
        'fecha' => 'date',
    ];

    public function expediente(): BelongsTo
    {
        return $this->belongsTo(Expediente::class);
    }
}

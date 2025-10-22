<?php

namespace App\Models;

use App\Casts\SafeDate;
use App\Models\User;
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
        'fecha' => SafeDate::class,
    ];

    public function expediente(): BelongsTo
    {
        return $this->belongsTo(Expediente::class);
    }

    public function subidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subido_por');
    }
}

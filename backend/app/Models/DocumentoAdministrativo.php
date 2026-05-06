<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentoAdministrativo extends Model
{
    protected $table = 'documentos_administrativos';

    protected $fillable = [
        'titulo',
        'ruta',
        'disk',
        'mime_type',
        'tamano',
        'subido_por',
        'aprobado_en',
        'aprobado_por',
    ];

    protected $casts = [
        'aprobado_en' => 'datetime',
    ];

    public function subidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subido_por');
    }
}

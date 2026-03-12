<?php

namespace App\Models;

use App\Models\Concerns\CachesCatalogo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CatalogoConsultorio extends Model
{
    use HasFactory;
    use CachesCatalogo;

    protected $table = 'catalogo_consultorios';

    protected $fillable = ['nombre', 'numero', 'activo'];

    protected $casts = [
        'numero' => 'integer',
        'activo' => 'boolean',
    ];
}


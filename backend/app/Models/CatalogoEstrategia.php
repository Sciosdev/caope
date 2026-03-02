<?php

namespace App\Models;

use App\Models\Concerns\CachesCatalogo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CatalogoEstrategia extends Model
{
    use HasFactory;
    use CachesCatalogo;

    protected $table = 'catalogo_estrategias';

    protected $fillable = ['nombre', 'activo'];

    protected $casts = [
        'activo' => 'boolean',
    ];
}

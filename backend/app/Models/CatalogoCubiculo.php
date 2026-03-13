<?php

namespace App\Models;

use App\Models\Concerns\CachesCatalogo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CatalogoCubiculo extends Model
{
    use HasFactory;
    use CachesCatalogo;

    protected $table = 'catalogo_cubiculos';

    protected $fillable = ['nombre', 'numero', 'activo'];

    protected $casts = [
        'numero' => 'integer',
        'activo' => 'boolean',
    ];
}


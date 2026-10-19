<?php

namespace App\Models;

use App\Models\Concerns\CachesCatalogo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CatalogoEstrategia extends Model
{
    use CachesCatalogo;
    use HasFactory;

    protected $table = 'catalogo_estrategias';

    protected $fillable = ['nombre', 'activo'];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function coordinadores(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'coordinador_estrategia', 'estrategia_id', 'user_id')
            ->withTimestamps();
    }
}

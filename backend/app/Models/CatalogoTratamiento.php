<?php

namespace App\Models;

use App\Models\Concerns\CachesCatalogo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CatalogoTratamiento extends Model
{
    use HasFactory;
    use CachesCatalogo;

    protected $table = 'catalogo_tratamientos';

    protected $fillable = ['nombre', 'activo'];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function carreras(): BelongsToMany
    {
        return $this->belongsToMany(CatalogoCarrera::class, 'carrera_tratamiento')
            ->withPivot('obligatorio')
            ->withTimestamps();
    }

    public function carrerasQueRequieren(): BelongsToMany
    {
        return $this->carreras()->wherePivot('obligatorio', true);
    }
}

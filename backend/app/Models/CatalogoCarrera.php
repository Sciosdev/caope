<?php

namespace App\Models;

use App\Models\Concerns\CachesCatalogo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CatalogoCarrera extends Model
{
    use HasFactory;
    use CachesCatalogo;

    protected $table = 'catalogo_carreras';

    protected $fillable = ['nombre', 'activo'];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function tratamientos(): BelongsToMany
    {
        return $this->belongsToMany(CatalogoTratamiento::class, 'carrera_tratamiento')
            ->withPivot('obligatorio')
            ->withTimestamps();
    }

    public function tratamientosRequeridos(): BelongsToMany
    {
        return $this->tratamientos()->wherePivot('obligatorio', true);
    }
}

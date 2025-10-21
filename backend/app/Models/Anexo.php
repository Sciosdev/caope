<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Anexo extends Model
{
    use HasFactory;

    protected $table = 'anexos';

    protected $guarded = [];

    protected $casts = [
        'es_privado' => 'boolean',
    ];

    public function expediente(): BelongsTo
    {
        return $this->belongsTo(Expediente::class);
    }

    public function subidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subido_por');
    }

    /**
     * Aplica filtros de búsqueda sobre anexos.
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        $titulo = isset($filters['titulo']) ? trim((string) $filters['titulo']) : '';
        $tipo = isset($filters['tipo']) ? trim((string) $filters['tipo']) : '';

        return $query
            ->when($titulo !== '', function (Builder $builder) use ($titulo) {
                $builder->where('titulo', 'like', sprintf('%%%s%%', $titulo));
            })
            ->when($tipo !== '', function (Builder $builder) use ($tipo) {
                $builder->where('tipo', $tipo);
            });
    }
}

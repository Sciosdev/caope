<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimelineEvento extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'expediente_id',
        'actor_id',
        'evento',
        'payload',
        'created_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Limita eventos ligados a sesiones al mismo alcance de esas sesiones.
     * Los demás eventos pertenecen al expediente y permanecen visibles para
     * cualquier usuario autorizado a consultar ese expediente.
     *
     * @param  Builder<TimelineEvento>  $query
     * @return Builder<TimelineEvento>
     */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if ($user === null) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->hasGlobalExpedienteAccess()) {
            return $query;
        }

        $query->whereHas(
            'expediente',
            fn (Builder $expedientes) => $expedientes->visibleTo($user)
        );

        $visibleSessionIds = Sesion::query()
            ->visibleTo($user)
            ->select('sesiones.id');

        return $query->where(function (Builder $events) use ($visibleSessionIds): void {
            $events
                ->where(function (Builder $notLinkedToSession): void {
                    $notLinkedToSession
                        ->whereNull('payload->sesion_id')
                        ->where(function (Builder $notSessionComment): void {
                            $notSessionComment
                                ->whereNull('payload->comentable_type')
                                ->orWhere('payload->comentable_type', '!=', Sesion::class);
                        });
                })
                ->orWhereIn('payload->sesion_id', (clone $visibleSessionIds)
                    ->whereColumn('sesiones.expediente_id', 'timeline_eventos.expediente_id'))
                ->orWhere(function (Builder $sessionComment) use ($visibleSessionIds): void {
                    $sessionComment
                        ->where('payload->comentable_type', Sesion::class)
                        ->whereIn('payload->comentable_id', (clone $visibleSessionIds)
                            ->whereColumn('sesiones.expediente_id', 'timeline_eventos.expediente_id'));
                });
        });
    }

    public function expediente(): BelongsTo
    {
        return $this->belongsTo(Expediente::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}

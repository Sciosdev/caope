<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ConsultorioReserva extends Model
{
    use HasFactory;

    protected $fillable = [
        'fecha',
        'hora_inicio',
        'hora_fin',
        'consultorio_numero',
        'cubiculo_numero',
        'estrategia',
        'usuario_atendido_id',
        'estratega_id',
        'supervisor_id',
        'creado_por',
        'origen_expediente',
        'expediente_id',
    ];

    protected $casts = [
        'fecha' => 'date:Y-m-d',
        'origen_expediente' => 'boolean',
    ];

    /** Call once per submitted schedule, not once per occurrence. */
    public static function newSeriesUuid(?string $mode): ?string
    {
        if ($mode !== 'semanal') {
            return null;
        }
        if (! Schema::hasColumn('consultorio_reservas', 'serie_uuid')) {
            throw ValidationException::withMessages([
                'reserva' => 'Las reservas semanales estarán disponibles al terminar la actualización. No se guardó ninguna fecha.',
            ]);
        }

        return (string) Str::uuid();
    }

    /** Series membership is server-generated and intentionally not mass assignable. */
    public static function createOccurrence(array $attributes, ?string $seriesUuid): self
    {
        $reserva = new self($attributes);
        if ($seriesUuid !== null) {
            $reserva->serie_uuid = $seriesUuid;
        }
        $reserva->save();

        return $reserva;
    }

    public function hasNotStarted(?CarbonInterface $at = null): bool
    {
        $time = (string) $this->hora_inicio;
        if (! $this->fecha || ! preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/D', $time)) {
            return false;
        }

        $start = CarbonImmutable::parse($this->fecha->toDateString().' '.$time, 'America/Mexico_City');

        return $start->greaterThan($at ?? now());
    }

    protected static function booted(): void
    {
        static::saving(function (ConsultorioReserva $reserva): void {
            if (! $reserva->exists || $reserva->isDirty('estrategia')) {
                $reserva->estrategia_id = CatalogoEstrategia::query()
                    ->where('nombre', $reserva->estrategia)->value('id');
            }
        });
    }

    /**
     * Limita las reservas al alcance operativo del usuario.
     *
     * Administrador y PAPS conservan la vista global. Los demás perfiles solo
     * ven las reservas donde tienen una asignación explícita.
     */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->hasRole('admin') || $user->isApprovedPaps()) {
            return $query;
        }

        if ($user->hasRole('coordinador')) {
            return $query->whereIn($query->qualifyColumn('estrategia_id'), function (QueryBuilder $assignments) use ($user): void {
                $assignments->select('estrategia_id')
                    ->from('coordinador_estrategia')
                    ->where('user_id', $user->getKey());
            });
        }

        if (! $user->hasAnyRole(['coordinador', 'alumno', 'docente', 'estratega'])) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $assigned) use ($user): void {
            if ($user->hasRole('alumno')) {
                $assigned->orWhere('usuario_atendido_id', $user->getKey());
            }

            if ($user->hasAnyRole(['docente', 'estratega'])) {
                $assigned->orWhere(function (Builder $teacher) use ($user): void {
                    $teacher->where('estratega_id', $user->getKey())
                        ->whereIn('estrategia_id', function (QueryBuilder $assignments) use ($user): void {
                            $assignments->select('estrategia_id')->from('participante_estrategia')
                                ->where('user_id', $user->getKey());
                        });
                });
            }
        });
    }

    public function usuarioAtendido(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_atendido_id');
    }

    public function expediente(): BelongsTo
    {
        return $this->belongsTo(Expediente::class)->withTrashed();
    }

    public function cubiculo(): BelongsTo
    {
        // Keep the existing room numbers and reservation history unchanged.
        return $this->belongsTo(CatalogoConsultorio::class, 'consultorio_numero', 'numero');
    }

    public function estrategiaCatalogo(): BelongsTo
    {
        return $this->belongsTo(CatalogoEstrategia::class, 'estrategia_id');
    }

    public function estratega(): BelongsTo
    {
        return $this->belongsTo(User::class, 'estratega_id');
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function solicitudes(): HasMany
    {
        return $this->hasMany(ConsultorioReservaSolicitud::class, 'consultorio_reserva_id');
    }
}

<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    protected static function booted(): void
    {
        static::deleting(function (User $user): void {
            // comments.user_id cascades on account deletion. It must not erase
            // retained clinical history as a side effect of deleting an account.
            $retainedCases = fn (Builder $cases) => $cases->withTrashed()->where(fn (Builder $held) => $held
                ->whereNotNull('deleted_at')->orWhere('retention_until', '>', now()));
            $hasRetainedComments = Comentario::query()->where('user_id', $user->getKey())
                ->where(function (Builder $comments) use ($retainedCases): void {
                    $comments->whereHasMorph('comentable', [Expediente::class], $retainedCases)
                        ->orWhereHasMorph('comentable', [Sesion::class], fn (Builder $sessions) => $sessions
                            ->whereHas('expediente', $retainedCases));
                })->exists();

            if ($hasRetainedComments) {
                throw ValidationException::withMessages([
                    'user' => 'No se puede eliminar esta cuenta porque tiene comentarios en expedientes archivados o sujetos a conservación. Puedes desactivar su acceso.',
                ]);
            }
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'carrera',
        'turno',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'approved_at' => 'datetime',
        ];
    }

    public function expedientesCreados(): HasMany
    {
        return $this->hasMany(Expediente::class, 'creado_por');
    }

    public function expedientesTutorados(): HasMany
    {
        return $this->hasMany(Expediente::class, 'tutor_id');
    }

    public function expedientesCoordinados(): HasMany
    {
        return $this->hasMany(Expediente::class, 'coordinador_id');
    }

    public function estrategiasCoordinadas(): BelongsToMany
    {
        return $this->belongsToMany(CatalogoEstrategia::class, 'coordinador_estrategia', 'user_id', 'estrategia_id')
            ->withTimestamps();
    }

    public function estrategiasParticipadas(): BelongsToMany
    {
        return $this->belongsToMany(CatalogoEstrategia::class, 'participante_estrategia', 'user_id', 'estrategia_id')
            ->withTimestamps();
    }

    public function participatesInStrategy(?int $strategyId): bool
    {
        // Query current assignments, not a relation previously loaded in memory.
        return $strategyId !== null && $strategyId > 0
            && $this->estrategiasParticipadas()->whereKey($strategyId)->exists();
    }

    /**
     * Indica si el usuario puede consultar todos los expedientes del centro.
     *
     * Coordinador conserva el permiso de gestión para actuar sobre sus
     * asignaciones, pero ese permiso no debe ampliar su alcance a todo el
     * centro. Admin, PAPS y permisos de gestión personalizados conservan el
     * alcance global.
     */
    public function hasGlobalExpedienteAccess(): bool
    {
        if ($this->hasRole('admin')) {
            return true;
        }

        if ($this->hasRole('paps')) {
            return $this->isApprovedPaps();
        }

        return $this->can('expedientes.manage') && ! $this->hasRole('coordinador');
    }

    public function isApprovedPaps(): bool
    {
        return $this->hasRole('paps') && $this->approved_at !== null;
    }

    public function isFacilitatorOf(Expediente $expediente): bool
    {
        if ($expediente->trashed()) {
            return false;
        }

        if ($this->hasRole('coordinador') && ! $this->hasGlobalExpedienteAccess() && ! $this->isCoordinatorOf($expediente)) {
            return false;
        }

        return $this->hasRole('alumno')
            && (int) $expediente->facilitador_id === (int) $this->getKey();
    }

    public function isTutorOf(Expediente $expediente): bool
    {
        if ($expediente->trashed()) {
            return false;
        }

        if ($this->hasRole('coordinador') && ! $this->hasGlobalExpedienteAccess() && ! $this->isCoordinatorOf($expediente)) {
            return false;
        }

        return $this->hasAnyRole(['docente', 'estratega'])
            && (int) $expediente->tutor_id === (int) $this->getKey()
            && $this->participatesInStrategy($expediente->estrategia_id);
    }

    public function isCoordinatorOf(Expediente $expediente): bool
    {
        return $this->hasRole('coordinador')
            && ! $expediente->trashed()
            && $expediente->estrategia_id !== null
            && $this->estrategiasCoordinadas()->whereKey($expediente->estrategia_id)->exists();
    }

    public function isAssignedToExpediente(Expediente $expediente): bool
    {
        if ($this->hasRole('coordinador') && ! $this->hasGlobalExpedienteAccess()) {
            return $this->isCoordinatorOf($expediente);
        }

        return $this->isFacilitatorOf($expediente)
            || $this->isTutorOf($expediente)
            || $this->isCoordinatorOf($expediente);
    }

    public function timelineEventos(): HasMany
    {
        return $this->hasMany(TimelineEvento::class, 'actor_id');
    }

    public function comentarios(): HasMany
    {
        return $this->hasMany(Comentario::class);
    }
}

<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasRoles, Notifiable;

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
        'is_active',
        'approved_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
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
        return $this->hasRole('alumno')
            && (int) $expediente->creado_por === (int) $this->getKey();
    }

    public function isTutorOf(Expediente $expediente): bool
    {
        return $this->hasAnyRole(['docente', 'estratega'])
            && (int) $expediente->tutor_id === (int) $this->getKey();
    }

    public function isCoordinatorOf(Expediente $expediente): bool
    {
        return $this->hasRole('coordinador')
            && (int) $expediente->coordinador_id === (int) $this->getKey();
    }

    public function isAssignedToExpediente(Expediente $expediente): bool
    {
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

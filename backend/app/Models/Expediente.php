<?php

namespace App\Models;

use App\Casts\SafeDate;
use App\Services\Masking\NameMasker;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Expediente extends Model
{
    use HasFactory;

    public const FAMILY_HISTORY_MEMBERS = [
        'madre' => 'Madre',
        'padre' => 'Padre',
        'hermanos' => 'Hermanos',
        'abuelos' => 'Abuelos',
        'tios' => 'Tíos',
        'otros' => 'Otros',
    ];

    public const HEREDITARY_HISTORY_CONDITIONS = [
        'diabetes_mellitus' => 'Diabetes mellitus',
        'hipertension_arterial' => 'Hipertensión arterial',
        'cardiopatias' => 'Cardiopatías',
        'cancer' => 'Cáncer',
        'obesidad' => 'Obesidad',
        'enfermedad_renal' => 'Enfermedad renal crónica',
        'trastornos_mentales' => 'Trastornos mentales',
        'epilepsia' => 'Epilepsia',
        'malformaciones' => 'Malformaciones congénitas',
        'sida' => 'VIH/SIDA',
        'hepatitis' => 'Hepatitis',
        'artritis' => 'Artritis',
        'otra' => 'Otro',
        'aparentemente_sano' => 'Aparentemente sano',
    ];

    protected $fillable = [
        'no_control',
        'paciente',
        'estado',
        'apertura',
        'carrera',
        'turno',
        'creado_por',
        'tutor_id',
        'coordinador_id',
        'antecedentes_familiares',
        'antecedentes_observaciones',
    ];

    protected $casts = [
        'apertura' => SafeDate::class,
        'antecedentes_familiares' => 'array',
    ];

    /**
     * @return array<string, array<string, bool>>
     */
    public static function defaultFamilyHistory(): array
    {
        $members = collect(self::FAMILY_HISTORY_MEMBERS)->keys();

        return collect(self::HEREDITARY_HISTORY_CONDITIONS)
            ->keys()
            ->mapWithKeys(function (string $condition) use ($members) {
                $defaults = $members
                    ->mapWithKeys(fn (string $member) => [$member => false])
                    ->all();

                return [$condition => $defaults];
            })
            ->all();
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function tutor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tutor_id');
    }

    public function coordinador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coordinador_id');
    }

    public function sesiones(): HasMany
    {
        return $this->hasMany(Sesion::class);
    }

    public function consentimientos(): HasMany
    {
        return $this->hasMany(Consentimiento::class);
    }

    public function anexos(): HasMany
    {
        return $this->hasMany(Anexo::class);
    }

    public function timelineEventos(): HasMany
    {
        return $this->hasMany(TimelineEvento::class);
    }

    public function comentarios(): MorphMany
    {
        return $this->morphMany(Comentario::class, 'comentable')->latest('created_at');
    }

    public function getPacienteMaskedAttribute(): string
    {
        return NameMasker::mask($this->paciente);
    }
}

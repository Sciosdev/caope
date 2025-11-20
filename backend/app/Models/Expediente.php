<?php

namespace App\Models;

use App\Casts\SafeDate;
use App\Services\Masking\NameMasker;
use Illuminate\Support\Carbon;
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
        'abuela_materna' => 'Abuela',
        'abuelo_materno' => 'Abuelo',
        'otros_maternos' => 'Otros',
        'padre' => 'Padre',
        'abuela_paterna' => 'Abuela',
        'abuelo_paterno' => 'Abuelo',
        'otros_paternos' => 'Otros',
        'hermanos' => 'Hermanos',
    ];

    public const HEREDITARY_HISTORY_CONDITIONS = [
        'diabetes_mellitus' => 'Diabetes',
        'hipertension_arterial' => 'Hipertensión arterial',
        'cardiopatias' => 'Cardiopatías',
        'cancer' => 'Neoplasias',
        'obesidad' => 'Obesidad',
        'epilepsia' => 'Epilepsia',
        'malformaciones' => 'Malformaciones congénitas',
        'sida' => 'VIH/SIDA',
        'enfermedad_renal' => 'Enfermedades renales',
        'hepatitis' => 'Hepatitis',
        'artritis' => 'Artritis',
        'trastornos_mentales' => 'Trastornos mentales',
        'otra' => 'Otro',
        'aparentemente_sano' => 'Aparentemente sano',
    ];

    public const PERSONAL_PATHOLOGICAL_CONDITIONS = [
        'varicela' => 'Varicela',
        'rubeola' => 'Rubéola',
        'sarampion' => 'Sarampión',
        'parotiditis' => 'Parotiditis',
        'tosferina' => 'Tosferina',
        'escarlatina' => 'Escarlatina',
        'parasitosis' => 'Parasitosis',
        'hepatitis' => 'Hepatitis',
        'sida' => 'SIDA',
        'asma' => 'Asma',
        'disfunciones_endocrinas' => 'Disfunciones endócrinas',
        'hipertension' => 'Hipertensión',
        'cancer' => 'Cáncer',
        'enfermedades_transmision_sexual' => 'Enf. Transmisión Sexual',
        'epilepsia' => 'Epilepsia',
        'amigdalitis_repeticion' => 'Amigdalitis de repetición',
        'tuberculosis' => 'Tuberculosis',
        'fiebre_reumatica' => 'Fiebre reumática',
        'diabetes' => 'Diabetes',
        'enfermedades_cardiovasculares' => 'Enf. Cardiovasculares',
        'artritis' => 'Artritis',
        'traumatismos_con_secuelas' => 'Traumatismos con secuelas',
        'intervenciones_quirurgicas' => 'Intervenciones quirúrgicas',
        'transfusiones_sanguineas' => 'Transfusiones sanguíneas',
        'alergias' => 'Alergias',
    ];

    public const SYSTEMS_REVIEW_SECTIONS = [
        'digestivo' => 'Historia Psicosocial y del Desarrollo',
        'respiratorio' => 'Evaluación Psicológica (Estado Mental Actual)',
        'cardiovascular' => 'Evaluación Psicológica Observaciones Clínicas Relevantes',
        'musculo_esqueletico' => 'Músculo esquelético',
        'genito_urinario' => 'Genito urinario',
        'linfohematatico' => 'Linfohemático',
        'endocrino' => 'Endócrino',
        'nervioso' => 'Nervioso',
        'tegumentario' => 'Tegumentario',
    ];

    public const GENERO_OPTIONS = [
        'femenino',
        'masculino',
        'no_binario',
        'otro',
        'prefiere_no_decir',
    ];

    public const ESTADO_CIVIL_OPTIONS = [
        'soltero',
        'casado',
        'divorciado',
        'viudo',
        'union_libre',
        'prefiere_no_decir',
    ];

    public const CLINICAL_OUTCOME_OPTIONS = [
        'mejoria' => 'Mejoría',
        'abandono' => 'Abandono',
        'referencia' => 'Referencia',
        'termino_proceso' => 'Término del Proceso',
    ];

    protected $fillable = [
        'no_control',
        'paciente',
        'estado',
        'apertura',
        'carrera',
        'turno',
        'clinica',
        'recibo_expediente',
        'recibo_diagnostico',
        'genero',
        'estado_civil',
        'ocupacion',
        'escolaridad',
        'fecha_nacimiento',
        'lugar_nacimiento',
        'domicilio_calle',
        'colonia',
        'delegacion_municipio',
        'entidad',
        'telefono_principal',
        'fecha_inicio_real',
        'motivo_consulta',
        'alerta_ingreso',
        'contacto_emergencia_nombre',
        'contacto_emergencia_parentesco',
        'contacto_emergencia_correo',
        'contacto_emergencia_telefono',
        'contacto_emergencia_horario',
        'medico_referencia_nombre',
        'medico_referencia_correo',
        'medico_referencia_telefono',
        'creado_por',
        'tutor_id',
        'coordinador_id',
        'diagnostico',
        'dsm_tr',
        'observaciones_relevantes',
        'antecedentes_familiares',
        'antecedentes_observaciones',
        'antecedentes_personales_patologicos',
        'antecedentes_personales_observaciones',
        'antecedente_padecimiento_actual',
        'plan_accion',
        'aparatos_sistemas',
        'resumen_clinico',
    ];

    protected $casts = [
        'apertura' => SafeDate::class,
        'fecha_inicio_real' => SafeDate::class,
        'fecha_nacimiento' => SafeDate::class,
        'antecedentes_familiares' => 'array',
        'antecedentes_personales_patologicos' => 'array',
        'aparatos_sistemas' => 'array',
        'resumen_clinico' => 'array',
        'telefono_principal' => 'string',
        'contacto_emergencia_telefono' => 'string',
        'medico_referencia_telefono' => 'string',
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

    /**
     * @return array<string, array{padece: bool, fecha: ?string}>
     */
    public static function defaultPersonalPathologicalHistory(): array
    {
        return collect(self::PERSONAL_PATHOLOGICAL_CONDITIONS)
            ->keys()
            ->mapWithKeys(fn (string $condition) => [
                $condition => [
                    'padece' => false,
                    'fecha' => null,
                ],
            ])
            ->all();
    }

    /**
     * @return array<string, ?string>
     */
    public static function defaultSystemsReview(): array
    {
        return collect(self::SYSTEMS_REVIEW_SECTIONS)
            ->keys()
            ->mapWithKeys(fn (string $section) => [$section => null])
            ->all();
    }

    /**
     * @return array{
     *     nota_alta: ?string,
     *     resumen_evaluacion: ?string,
     *     recomendaciones: ?string,
     *     fecha: ?string,
     *     facilitador: ?string,
     *     autorizacion_responsable: ?string,
     *     resultado: ?string,
     *     resultado_detalle: ?string,
     * }
     */
    public static function defaultClinicalSummary(): array
    {
        return [
            'nota_alta' => null,
            'resumen_evaluacion' => null,
            'recomendaciones' => null,
            'fecha' => null,
            'facilitador' => null,
            'autorizacion_responsable' => null,
            'resultado' => null,
            'resultado_detalle' => null,
        ];
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function alumno(): BelongsTo
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

    public function getEdadAttribute(): ?int
    {
        $birthDate = $this->fecha_nacimiento;

        if ($birthDate instanceof Carbon) {
            return $birthDate->age;
        }

        if ($birthDate) {
            return Carbon::make($birthDate)?->age;
        }

        return null;
    }
}

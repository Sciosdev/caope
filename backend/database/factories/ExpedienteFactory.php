<?php

namespace Database\Factories;

use App\Models\CatalogoCarrera;
use App\Models\CatalogoTurno;
use App\Models\Expediente;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Collection;

class ExpedienteFactory extends Factory
{
    protected static int $noControlCounter = 1;

    protected $model = Expediente::class;

    public static function resetNoControlCounter(): void
    {
        static::$noControlCounter = 1;
    }

    protected function makeNoControl(): string
    {
        $example = config('expedientes.no_control.example', sprintf('CA-%s-0001', now()->format('Y')));
        $segments = explode('-', $example);

        $prefix = $segments[0] ?? 'CA';
        $counterWidth = isset($segments[2]) ? strlen($segments[2]) : 4;

        $counter = str_pad((string) static::$noControlCounter++, $counterWidth, '0', STR_PAD_LEFT);

        return sprintf('%s-%s-%s', $prefix, now()->format('Y'), $counter);
    }

    public function definition(): array
    {
        $estados = ['abierto', 'revision', 'cerrado'];

        $carreras = CatalogoCarrera::query()
            ->where('activo', true)
            ->pluck('nombre');

        if ($carreras->isEmpty()) {
            $carreras = collect([
                'Licenciatura en Enfermería',
                'Licenciatura en Psicología',
                'Cirujano Dentista',
            ]);
        }

        $turnos = CatalogoTurno::query()
            ->where('activo', true)
            ->pluck('nombre');

        if ($turnos->isEmpty()) {
            $turnos = collect(['Matutino', 'Vespertino', 'Mixto']);
        }

        $usuarios = User::query()->pluck('id');

        if ($usuarios->count() < 3) {
            $usuarios = $usuarios->merge(
                User::factory()->count(3 - $usuarios->count())->create()->pluck('id')
            );
        }

        /** @var Collection<int, int> $usuarios */
        $usuarios = $usuarios->shuffle();

        $creadoPor = $usuarios->shift();
        $tutor = $usuarios->isNotEmpty() && $this->faker->boolean(60) ? $usuarios->first() : null;
        $coordinador = $usuarios->count() > 1 && $this->faker->boolean(40) ? $usuarios->skip(1)->first() : null;

        $familyHistory = Expediente::defaultFamilyHistory();
        foreach ($familyHistory as $condition => $members) {
            foreach ($members as $member => $value) {
                $familyHistory[$condition][$member] = $this->faker->boolean(25);
            }
        }

        $personalHistory = Expediente::defaultPersonalPathologicalHistory();
        foreach ($personalHistory as $condition => $data) {
            $hasCondition = $this->faker->boolean(20);
            $personalHistory[$condition]['padece'] = $hasCondition;
            $personalHistory[$condition]['fecha'] = $hasCondition
                ? $this->faker->date('Y-m-d', 'now')
                : null;
        }

        $systemsReview = Expediente::defaultSystemsReview();
        foreach ($systemsReview as $section => $value) {
            $systemsReview[$section] = $this->faker->boolean(40)
                ? $this->faker->sentences($this->faker->numberBetween(1, 2), true)
                : null;
        }

        $apertura = $this->faker->dateTimeBetween('-6 months', 'now');
        $fechaInicioReal = $this->faker->boolean(65)
            ? $this->faker->dateTimeBetween($apertura, 'now')
            : null;
        $fechaNacimiento = $this->faker->boolean(80)
            ? $this->faker->dateTimeBetween('-40 years', '-18 years')
            : null;
        $planAccion = $this->faker->boolean(50)
            ? $this->faker->paragraphs($this->faker->numberBetween(1, 2), true)
            : null;
        $diagnostico = $this->faker->boolean(55)
            ? $this->faker->paragraphs($this->faker->numberBetween(1, 2), true)
            : null;
        $observacionesRelevantes = $this->faker->boolean(50)
            ? $this->faker->sentences($this->faker->numberBetween(1, 2), true)
            : null;
        $telefonoPrincipal = $this->faker->boolean(85)
            ? sprintf('+52 55 %04d %04d', $this->faker->numberBetween(0, 9999), $this->faker->numberBetween(0, 9999))
            : null;
        $contactoEmergenciaNombre = $this->faker->name();
        $contactoEmergenciaTelefono = sprintf('+52 55 %04d %04d', $this->faker->numberBetween(0, 9999), $this->faker->numberBetween(0, 9999));
        $medicoReferenciaTelefono = sprintf('+52 55 %04d %04d', $this->faker->numberBetween(0, 9999), $this->faker->numberBetween(0, 9999));
        $motivoConsulta = $this->faker->boolean(70)
            ? $this->faker->paragraphs($this->faker->numberBetween(2, 3), true)
            : null;
        $alertaIngreso = $this->faker->boolean(30)
            ? $this->faker->sentence(12)
            : null;

        return [
            'no_control' => $this->makeNoControl(),
            'paciente' => $this->faker->name(),
            'estado' => $this->faker->randomElement($estados),
            'apertura' => $apertura,
            'carrera' => $this->faker->randomElement($carreras->all()),
            'turno' => $this->faker->randomElement($turnos->all()),
            'clinica' => $this->faker->optional(0.6)->company(),
            'recibo_expediente' => $this->faker->optional(0.5)->bothify('EXP-####'),
            'recibo_diagnostico' => $this->faker->optional(0.5)->bothify('DX-####'),
            'genero' => $this->faker->optional(0.8)->randomElement(Expediente::GENERO_OPTIONS),
            'estado_civil' => $this->faker->optional(0.7)->randomElement(Expediente::ESTADO_CIVIL_OPTIONS),
            'ocupacion' => $this->faker->optional(0.7)->jobTitle(),
            'escolaridad' => $this->faker->optional(0.7)->randomElement([
                'Primaria',
                'Secundaria',
                'Preparatoria',
                'Licenciatura',
                'Posgrado',
            ]),
            'fecha_nacimiento' => $fechaNacimiento,
            'lugar_nacimiento' => $this->faker->optional(0.6)->city(),
            'domicilio_calle' => $this->faker->optional(0.8)->streetAddress(),
            'colonia' => $this->faker->optional(0.7)->streetName(),
            'delegacion_municipio' => $this->faker->optional(0.7)->city(),
            'entidad' => $this->faker->optional(0.7)->state(),
            'telefono_principal' => $telefonoPrincipal,
            'fecha_inicio_real' => $fechaInicioReal,
            'motivo_consulta' => $motivoConsulta,
            'alerta_ingreso' => $alertaIngreso,
            'contacto_emergencia_nombre' => $contactoEmergenciaNombre,
            'contacto_emergencia_parentesco' => $this->faker->randomElement(['Madre', 'Padre', 'Hermano/a', 'Pareja', 'Amigo/a']),
            'contacto_emergencia_correo' => $this->faker->safeEmail(),
            'contacto_emergencia_telefono' => $contactoEmergenciaTelefono,
            'contacto_emergencia_horario' => $this->faker->optional(0.5)->randomElement(['Mañanas', 'Tardes', 'Noches', 'Horario laboral']),
            'medico_referencia_nombre' => $this->faker->optional(0.6)->name(),
            'medico_referencia_correo' => $this->faker->optional(0.6)->companyEmail(),
            'medico_referencia_telefono' => $this->faker->optional(0.6)->randomElement([$medicoReferenciaTelefono, null]),
            'creado_por' => $creadoPor,
            'tutor_id' => $tutor,
            'coordinador_id' => $coordinador,
            'diagnostico' => $diagnostico,
            'dsm_tr' => $this->faker->optional(0.6)->randomElement([
                'DSM-IV, trastorno de ansiedad generalizada',
                'DSM-5, episodio depresivo moderado',
                'DSM-IV-TR, trastorno adaptativo',
                null,
            ]),
            'observaciones_relevantes' => $observacionesRelevantes,
            'antecedentes_familiares' => $familyHistory,
            'antecedentes_observaciones' => $this->faker->optional(0.4)->text(120),
            'antecedentes_personales_patologicos' => $personalHistory,
            'antecedentes_personales_observaciones' => $this->faker->optional(0.3)->text(120),
            'antecedente_padecimiento_actual' => $this->faker->optional(0.5)->paragraph(),
            'plan_accion' => $planAccion,
            'aparatos_sistemas' => $systemsReview,
            'resumen_clinico' => collect(\App\Models\Expediente::defaultClinicalSummary())
                ->map(function ($valor, string $campo) {
                    return match ($campo) {
                        'fecha' => $this->faker->optional(0.6)->date('Y-m-d'),
                        'resultado' => $this->faker->optional(0.5)->randomElement(array_keys(\App\Models\Expediente::CLINICAL_OUTCOME_OPTIONS)),
                        'resultado_detalle' => $this->faker->optional(0.5)->sentence(),
                        default => $this->faker->optional(0.6)->paragraph(),
                    };
                })
                ->all(),
        ];
    }
}

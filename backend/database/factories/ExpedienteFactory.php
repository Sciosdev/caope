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
    protected $model = Expediente::class;

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
        foreach (array_keys($familyHistory) as $member) {
            $familyHistory[$member] = $this->faker->boolean(30);
        }

        $clinicalHistory = Expediente::defaultClinicalHistory();
        foreach ($clinicalHistory as $condition => $members) {
            foreach ($members as $member => $value) {
                $clinicalHistory[$condition][$member] = $this->faker->boolean(20);
            }
        }

        $clinicalOthers = $this->faker->optional(0.3)->words($this->faker->numberBetween(2, 4), true);
        $clinicalNotes = $this->faker->optional(0.35)->sentences($this->faker->numberBetween(1, 2), true);

        return [
            'no_control' => sprintf('CA-%s-%04d', now()->format('Y'), $this->faker->unique()->numberBetween(1, 9999)),
            'paciente' => $this->faker->name(),
            'estado' => $this->faker->randomElement($estados),
            'apertura' => $this->faker->dateTimeBetween('-6 months', 'now'),
            'carrera' => $this->faker->randomElement($carreras->all()),
            'turno' => $this->faker->randomElement($turnos->all()),
            'creado_por' => $creadoPor,
            'tutor_id' => $tutor,
            'coordinador_id' => $coordinador,
            'antecedentes_familiares' => $familyHistory,
            'antecedentes_observaciones' => $this->faker->optional(0.4)->text(120),
            'antecedentes_clinicos' => $clinicalHistory,
            'antecedentes_clinicos_otros' => $clinicalOthers,
            'antecedentes_clinicos_observaciones' => $clinicalNotes,
        ];
    }
}

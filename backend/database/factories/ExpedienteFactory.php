<?php

namespace Database\Factories;

use App\Models\Expediente;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExpedienteFactory extends Factory
{
    protected $model = Expediente::class;

    public function definition(): array
    {
        $estados = ['abierto', 'revision', 'cerrado'];
        $carreras = ['Enfermería', 'Psicología', 'Odontología'];
        $turnos = ['Matutino', 'Vespertino'];

        return [
            'no' => sprintf('CA-%s-%04d', now()->format('Y'), $this->faker->unique()->numberBetween(1, 9999)),
            'paciente' => $this->faker->name(),
            'estado' => $this->faker->randomElement($estados),
            'apertura' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'carrera' => $this->faker->randomElement($carreras),
            'turno' => $this->faker->randomElement($turnos),
        ];
    }
}

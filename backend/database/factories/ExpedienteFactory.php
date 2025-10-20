<?php

namespace Database\Factories;

use App\Models\Expediente;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExpedienteFactory extends Factory
{
    protected $model = Expediente::class;

    public function definition(): array
    {
        $estados = ['abierto', 'revision', 'cerrado'];

        $usuarios = User::query()->pluck('id');

        if ($usuarios->isEmpty()) {
            $usuarios = collect([User::factory()->create()->id]);
        }

        $createdBy = $usuarios->random();
        $otrosUsuarios = $usuarios->reject(fn ($id) => $id === $createdBy);

        return [
            'no_control' => sprintf('CA-%s-%04d', now()->format('Y'), $this->faker->unique()->numberBetween(1, 9999)),
            'paciente' => $this->faker->name(),
            'estado' => $this->faker->randomElement($estados),
            'apertura' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'carrera' => $this->faker->words(3, true),
            'turno' => $this->faker->randomElement(['matutino', 'vespertino', 'mixto']),
            'creado_por' => $createdBy,
            'tutor_id' => $this->faker->boolean(60) && $otrosUsuarios->isNotEmpty() ? $otrosUsuarios->random() : null,
            'coordinador_id' => $this->faker->boolean(30) && $otrosUsuarios->isNotEmpty() ? $otrosUsuarios->random() : null,
        ];
    }
}

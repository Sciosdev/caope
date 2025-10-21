<?php

namespace Database\Factories;

use App\Models\Expediente;
use App\Models\TimelineEvento;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TimelineEventoFactory extends Factory
{
    protected $model = TimelineEvento::class;

    public function definition(): array
    {
        return [
            'expediente_id' => Expediente::factory(),
            'actor_id' => User::factory(),
            'evento' => $this->faker->randomElement([
                'expediente.creado',
                'expediente.actualizado',
                'sesion.registrada',
            ]),
            'payload' => [
                'detalle' => $this->faker->sentence(),
            ],
            'created_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
        ];
    }
}

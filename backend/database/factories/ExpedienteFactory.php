<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ExpedienteFactory extends Factory
{
    public function definition(): array
{
    $estados = ['abierto','revision','cerrado'];
    $carreras = ['Enfermería','Psicología','Odontología'];
    $turnos   = ['Matutino','Vespertino'];

    return [
        'no'       => 'CA-'.now()->year.'-'.str_pad(fake()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
        'paciente' => fake()->name(),
        'estado'   => fake()->randomElement($estados),
        'apertura' => fake()->dateTimeBetween('-30 days', 'now'),
        'carrera'  => fake()->randomElement($carreras),
        'turno'    => fake()->randomElement($turnos),
    ];
}

}

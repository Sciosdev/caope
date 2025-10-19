<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ExpedienteFactory extends Factory
{
    public function definition(): array
    {
        static $n = 1;
        $anio = now()->year;
        $num  = str_pad($n++, 4, '0', STR_PAD_LEFT);

        return [
            'numero'   => "CA-$anio-$num",
            'paciente' => fake('es_MX')->name(),
            'estado'   => fake()->randomElement(['abierto','revision','cerrado']),
            'apertura' => fake()->dateTimeBetween('-90 days','now'),
            'carrera'  => fake()->randomElement(['Odontología','Psicología','Medicina','Enfermería']),
            'turno'    => fake()->randomElement(['Matutino','Vespertino']),
            'alerta'   => fake()->boolean(15),
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\Parametro;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Parametro>
 */
class ParametroFactory extends Factory
{
    protected $model = Parametro::class;

    public function definition(): array
    {
        return [
            'clave' => $this->faker->unique()->slug(3),
            'valor' => $this->faker->sentence(),
            'tipo' => Parametro::TYPE_STRING,
        ];
    }
}

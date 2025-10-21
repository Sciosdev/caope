<?php

namespace Database\Factories;

use App\Models\Comentario;
use App\Models\Expediente;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Comentario>
 */
class ComentarioFactory extends Factory
{
    protected $model = Comentario::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'comentable_type' => Expediente::class,
            'comentable_id' => Expediente::factory(),
            'contenido' => $this->faker->paragraph(),
        ];
    }
}

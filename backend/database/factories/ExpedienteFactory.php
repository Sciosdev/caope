<?php

namespace Database\Factories;

use App\Models\CatalogoCarrera;
use App\Models\CatalogoTurno;
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
            'carrera_id' => CatalogoCarrera::query()->where('estado', 'activo')->inRandomOrder()->value('id'),
            'turno_id' => CatalogoTurno::query()->where('estado', 'activo')->inRandomOrder()->value('id'),
            'created_by' => $createdBy,
            'tutor_id' => $this->faker->boolean(60) && $otrosUsuarios->isNotEmpty() ? $otrosUsuarios->random() : null,
            'coordinador_id' => $this->faker->boolean(30) && $otrosUsuarios->isNotEmpty() ? $otrosUsuarios->random() : null,
            'updated_by' => $this->faker->boolean(50) ? $usuarios->random() : null,
        ];
    }
}

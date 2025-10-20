<?php

namespace Database\Factories;

use App\Models\Anexo;
use App\Models\Expediente;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class AnexoFactory extends Factory
{
    protected $model = Anexo::class;

    public function definition(): array
    {
        $usuarios = User::query()->pluck('id');

        if ($usuarios->isEmpty()) {
            $usuarios = User::factory()->count(1)->create()->pluck('id');
        }

        $titulo = $this->faker->sentence(3);

        return [
            'expediente_id' => Expediente::factory(),
            'tipo' => $this->faker->randomElement(['documento', 'informe', 'evidencia']),
            'titulo' => $titulo,
            'ruta' => 'anexos/' . $this->faker->uuid() . '/' . Str::slug($titulo) . '.pdf',
            'tamano' => $this->faker->numberBetween(50_000, 2_000_000),
            'subido_por' => $usuarios->random(),
        ];
    }
}

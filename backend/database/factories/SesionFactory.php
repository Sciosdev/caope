<?php

namespace Database\Factories;

use App\Models\Expediente;
use App\Models\Sesion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Collection;

class SesionFactory extends Factory
{
    protected $model = Sesion::class;

    public function definition(): array
    {
        $tipos = [
            'Primera entrevista',
            'Seguimiento',
            'Cierre de caso',
            'Canalización',
        ];

        $usuarios = User::query()->pluck('id');

        if ($usuarios->count() < 2) {
            $usuarios = $usuarios->merge(
                User::factory()->count(2 - $usuarios->count())->create()->pluck('id')
            );
        }

        /** @var Collection<int, int> $usuarios */
        $usuarios = $usuarios->shuffle();

        $realizadaPor = $usuarios->shift();
        $status = $this->faker->randomElement(['pendiente', 'observada', 'validada']);
        $validadaPor = null;

        if ($status !== 'pendiente') {
            $validadaPor = $usuarios->isNotEmpty() ? $usuarios->first() : $realizadaPor;
        }

        $fecha = $this->faker->dateTimeBetween('-6 months', 'now');

        return [
            'expediente_id' => Expediente::factory(),
            'fecha' => $fecha,
            'tipo' => $this->faker->randomElement($tipos),
            'referencia_externa' => $this->faker->boolean(20) ? $this->faker->bothify('REF-###-??') : null,
            'nota' => $this->faker->paragraphs(2, true),
            'realizada_por' => $realizadaPor,
            'status_revision' => $status,
            'validada_por' => $validadaPor,
        ];
    }
}

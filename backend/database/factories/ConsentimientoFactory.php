<?php

namespace Database\Factories;

use App\Models\CatalogoTratamiento;
use App\Models\Consentimiento;
use App\Models\Expediente;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ConsentimientoFactory extends Factory
{
    protected $model = Consentimiento::class;

    public function definition(): array
    {
        $tratamientos = CatalogoTratamiento::query()
            ->where('activo', true)
            ->pluck('nombre');

        if ($tratamientos->isEmpty()) {
            $tratamientos = collect([
                'Psicoterapia individual',
                'Asesoría grupal',
            ]);
        }

        $requerido = $this->faker->boolean(70);
        $aceptado = $requerido ? $this->faker->boolean(80) : $this->faker->boolean(40);
        $fecha = $aceptado ? $this->faker->dateTimeBetween('-6 months', 'now') : null;

        return [
            'expediente_id' => Expediente::factory(),
            'tratamiento' => $this->faker->randomElement($tratamientos->all()),
            'requerido' => $requerido,
            'aceptado' => $aceptado,
            'fecha' => $fecha,
            'archivo_path' => $aceptado ? 'consentimientos/'.$this->faker->uuid().'.pdf' : null,
            'subido_por' => $aceptado ? User::factory() : null,
        ];
    }
}

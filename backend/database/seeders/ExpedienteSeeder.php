<?php

namespace Database\Seeders;

use App\Models\Anexo;
use App\Models\CatalogoCarrera;
use App\Models\CatalogoTratamiento;
use App\Models\CatalogoTurno;
use App\Models\Consentimiento;
use App\Models\Expediente;
use App\Models\Sesion;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Faker\Factory as FakerFactory;

class ExpedienteSeeder extends Seeder
{
    public function run(): void
    {
        $faker = FakerFactory::create('es_MX');

        $estados = collect(['abierto', 'revision', 'cerrado']);
        $carreras = CatalogoCarrera::query()->where('activo', true)->pluck('nombre');
        $turnos = CatalogoTurno::query()->where('activo', true)->pluck('nombre');
        $tratamientos = CatalogoTratamiento::query()->where('activo', true)->pluck('nombre');

        $usuarios = User::query()->pluck('id');
        if ($usuarios->count() < 3) {
            $usuarios = $usuarios->merge(User::factory()->count(3 - $usuarios->count())->create()->pluck('id'));
        }
        $usuarios = $usuarios->values();

        $totalExpedientes = 40;

        for ($i = 0; $i < $totalExpedientes; $i++) {
            $estado = $estados->get($i % $estados->count());
            $carrera = $carreras->isNotEmpty()
                ? $carreras->get($i % $carreras->count())
                : $faker->randomElement(['Licenciatura en Enfermería', 'Licenciatura en Psicología']);
            $turno = $turnos->isNotEmpty()
                ? $turnos->get($i % $turnos->count())
                : $faker->randomElement(['Matutino', 'Vespertino']);

            /** @var Collection<int, int> $usuariosBarajados */
            $usuariosBarajados = $usuarios->shuffle();
            $creadoPor = $usuariosBarajados->first();
            $tutor = $usuariosBarajados->count() > 1 && $faker->boolean(70) ? $usuariosBarajados->get(1) : null;
            $coordinador = null;

            if ($usuariosBarajados->count() > 2 && $faker->boolean(55)) {
                $coordinadorCandidatos = $usuariosBarajados->slice(2);
                if ($tutor) {
                    $coordinadorCandidatos = $coordinadorCandidatos->reject(fn ($id) => $id === $tutor);
                }
                $coordinador = $coordinadorCandidatos->isNotEmpty() ? $coordinadorCandidatos->random() : null;
            }

            $apertura = Carbon::parse($faker->dateTimeBetween('-8 months', '-1 week'));

            $familyHistory = Expediente::defaultFamilyHistory();
            foreach ($familyHistory as $condition => $members) {
                foreach ($members as $member => $value) {
                    $familyHistory[$condition][$member] = $faker->boolean(25);
                }
            }
            $familyNotes = $faker->boolean(35) ? $faker->sentences($faker->numberBetween(1, 2), true) : null;

            $personalHistory = Expediente::defaultPersonalPathologicalHistory();
            foreach ($personalHistory as $condition => $record) {
                $hasCondition = $faker->boolean(20);
                $personalHistory[$condition]['padece'] = $hasCondition;
                $personalHistory[$condition]['fecha'] = $hasCondition
                    ? $faker->date('Y-m-d', 'now')
                    : null;
            }
            $personalNotes = $faker->boolean(30) ? $faker->sentences($faker->numberBetween(1, 2), true) : null;

            $systemsReview = Expediente::defaultSystemsReview();
            foreach ($systemsReview as $section => $value) {
                $systemsReview[$section] = $faker->boolean(45)
                    ? $faker->sentences($faker->numberBetween(1, 2), true)
                    : null;
            }
            $currentCondition = $faker->boolean(55)
                ? $faker->paragraphs($faker->numberBetween(1, 2), true)
                : null;
            $planAccion = $faker->boolean(60)
                ? $faker->paragraphs($faker->numberBetween(1, 2), true)
                : null;
            $diagnostico = $faker->boolean(60)
                ? $faker->paragraphs($faker->numberBetween(1, 2), true)
                : null;
            $dsmTr = $faker->boolean(55)
                ? sprintf('DSM-5 %s', $faker->bothify('F##.##'))
                : null;
            $observacionesRelevantes = $faker->boolean(55)
                ? $faker->sentences($faker->numberBetween(1, 2), true)
                : null;

            $expediente = Expediente::factory()->create([
                'estado' => $estado,
                'carrera' => $carrera,
                'turno' => $turno,
                'apertura' => $apertura,
                'creado_por' => $creadoPor,
                'tutor_id' => $tutor,
                'coordinador_id' => $coordinador,
                'diagnostico' => $diagnostico,
                'dsm_tr' => $dsmTr,
                'observaciones_relevantes' => $observacionesRelevantes,
                'antecedentes_familiares' => $familyHistory,
                'antecedentes_observaciones' => $familyNotes,
                'antecedentes_personales_patologicos' => $personalHistory,
                'antecedentes_personales_observaciones' => $personalNotes,
                'antecedente_padecimiento_actual' => $currentCondition,
                'plan_accion' => $planAccion,
                'aparatos_sistemas' => $systemsReview,
            ]);

            $sesionesCount = $faker->numberBetween(2, 5);
            $fechasSesiones = collect();
            for ($j = 0; $j < $sesionesCount; $j++) {
                $fechasSesiones->push($faker->dateTimeBetween($apertura, 'now'));
            }

            $fechasSesiones = $fechasSesiones->sort();

            foreach ($fechasSesiones as $fechaSesion) {
                /** @var Collection<int, int> $usuariosSesion */
                $usuariosSesion = $usuarios->shuffle();
                $realizadaPor = $usuariosSesion->first();
                $status = $faker->randomElement(['pendiente', 'observada', 'validada']);
                $validadaPor = null;

                if ($status !== 'pendiente') {
                    $validadores = $usuariosSesion->reject(fn ($id) => $id === $realizadaPor);
                    $validadaPor = $validadores->isNotEmpty() ? $validadores->first() : $realizadaPor;
                }

                Sesion::factory()->for($expediente)->create([
                    'fecha' => $fechaSesion,
                    'realizada_por' => $realizadaPor,
                    'status_revision' => $status,
                    'validada_por' => $validadaPor,
                    'nota' => $faker->paragraphs($faker->numberBetween(2, 4), true),
                ]);
            }

            $consentimientosCount = $tratamientos->isEmpty() ? 0 : $faker->numberBetween(0, min(2, $tratamientos->count()));
            if ($consentimientosCount > 0) {
                $tratamientosSeleccionados = $tratamientos->shuffle()->take($consentimientosCount);

                foreach ($tratamientosSeleccionados as $tratamiento) {
                    $aceptado = $faker->boolean(75);
                    $fecha = $aceptado ? $faker->dateTimeBetween($apertura, 'now') : null;

                    Consentimiento::factory()->for($expediente)->create([
                        'tratamiento' => $tratamiento,
                        'requerido' => true,
                        'aceptado' => $aceptado,
                        'fecha' => $fecha,
                        'archivo_path' => $aceptado
                            ? sprintf('expedientes/%s/consentimientos/%s.pdf', $expediente->id, Str::uuid())
                            : null,
                        'subido_por' => $aceptado ? $usuarios->random() : null,
                    ]);
                }
            }

            $debeTenerAnexos = in_array($estado, ['revision', 'cerrado'], true) && $faker->boolean(65);
            if ($debeTenerAnexos) {
                $anexosCount = $faker->numberBetween(1, 3);
                for ($k = 0; $k < $anexosCount; $k++) {
                    $titulo = $faker->sentence(3);
                    Anexo::factory()->for($expediente)->create([
                        'titulo' => $titulo,
                        'ruta' => sprintf('expedientes/%s/anexos/%s.pdf', $expediente->id, Str::slug($titulo)),
                        'tamano' => $faker->numberBetween(90_000, 900_000),
                        'subido_por' => $usuarios->random(),
                    ]);
                }
            }
        }
    }
}

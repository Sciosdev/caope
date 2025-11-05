<?php

namespace Database\Seeders;

use App\Models\Parametro;
use Illuminate\Database\Seeder;

class ParametroSeeder extends Seeder
{
    public function run(): void
    {
        $parametros = [
            [
                'clave' => 'uploads.anexos.mimes',
                'valor' => 'pdf,jpg,jpeg,png,doc,docx,xls,xlsx,ppt,pptx,txt,csv',
                'tipo' => Parametro::TYPE_STRING,
            ],
            [
                'clave' => 'uploads.anexos.max',
                'valor' => '51200',
                'tipo' => Parametro::TYPE_INTEGER,
            ],
            [
                'clave' => 'uploads.consentimientos.mimes',
                'valor' => 'pdf,jpg,jpeg',
                'tipo' => Parametro::TYPE_STRING,
            ],
            [
                'clave' => 'uploads.consentimientos.max',
                'valor' => '5120',
                'tipo' => Parametro::TYPE_INTEGER,
            ],
            [
                'clave' => 'consentimientos.texto_introduccion',
                'valor' => 'Por medio del presente documento manifiesto que he sido informado sobre el tratamiento propuesto y autorizo al Centro de Atención y Orientación Psicológica para dar seguimiento a mi proceso.',
                'tipo' => Parametro::TYPE_TEXT,
            ],
            [
                'clave' => 'consentimientos.texto_cierre',
                'valor' => 'La información aquí contenida será tratada con la más estricta confidencialidad y solo será utilizada para los fines académicos y de seguimiento terapéutico establecidos.',
                'tipo' => Parametro::TYPE_TEXT,
            ],
        ];

        foreach ($parametros as $parametro) {
            Parametro::query()->updateOrCreate(
                ['clave' => $parametro['clave']],
                [
                    'valor' => $parametro['valor'],
                    'tipo' => $parametro['tipo'],
                ]
            );
        }
    }
}

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Consentimientos del expediente {{ $expediente->no_control }}</title>
    <style>
        @page { size: letter; margin: 22px 30px; }

        * { box-sizing: border-box; }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 11px;
            color: #111827;
            margin: 0;
            background: #ffffff;
        }

        .sheet { padding: 4px 6px 0; }

        p { margin: 0; }

        .header {
            display: grid;
            grid-template-columns: 130px 1fr;
            gap: 14px;
            align-items: center;
            margin-bottom: 6px;
        }

        .header-left {
            text-align: center;
            padding-right: 10px;
            border-right: 1px solid #111827;
        }

        .crest {
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .crest img {
            max-height: 60px;
            max-width: 85px;
            object-fit: contain;
        }

        .header-left .logo {
            font-size: 28px;
            font-weight: 700;
            letter-spacing: 0.08em;
            margin-top: 2px;
        }

        .header-left .campus {
            font-size: 16px;
            margin-top: 2px;
        }

        .header-right {
            text-transform: uppercase;
            letter-spacing: 0.04em;
            font-size: 11px;
            line-height: 1.6;
        }

        .title-box {
            border-top: 1px solid #111827;
            border-bottom: 1px solid #111827;
            text-align: center;
            font-weight: 700;
            text-transform: uppercase;
            padding: 4px 6px;
            margin: 6px 0 14px;
            letter-spacing: 0.03em;
        }

        .fields {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr;
            gap: 12px;
            margin-bottom: 10px;
            font-size: 11px;
        }

        .field {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 6px;
            align-items: end;
        }

        .field-line {
            border-bottom: 1px solid #111827;
            height: 14px;
            line-height: 14px;
        }

        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #111827; padding: 6px 8px; text-align: left; }
        th {
            background: #f2f2f2;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.04em;
            text-align: center;
        }
        td { height: 22px; }
        .row-alt { background: #d9e5f4; }

        .declaration {
            margin: 10px 0 14px;
            font-size: 10px;
            line-height: 1.4;
        }

        .declaration p { margin-bottom: 6px; }

        .signatures {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 22px 40px;
            margin-top: 20px;
        }

        .signature-line {
            border-top: 1px solid #111827;
            padding-top: 4px;
            text-align: center;
            font-size: 10px;
        }

        .signature-name {
            font-size: 11px;
            margin-top: 6px;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="sheet">
        @php
            $consentimientosListado = $consentimientos->values();
            $filasMinimas = 18;
            $totalFilas = max($filasMinimas, $consentimientosListado->count());
        @endphp

        <header class="header">
            <div class="header-left">
                <div class="crest">
                    <img src="{{ public_path('assets/images/others/logo-placeholder.png') }}" alt="Escudo UNAM">
                </div>
                <div class="logo">SDRI</div>
                <div class="campus">Iztacala</div>
            </div>
            <div class="header-right">
                <div>Universidad Nacional Autónoma de México</div>
                <div>Facultad de Estudios Superiores Iztacala</div>
                <div>Jefatura de la Carrera de Cirujano Dentista</div>
            </div>
        </header>

        <div class="title-box">Consentimiento Informado y Plan de Tratamiento</div>

        <section class="fields">
            <div class="field">
                <span>Paciente:</span>
                <div class="field-line">{{ $expediente->paciente ?? '' }}</div>
            </div>
            <div class="field">
                <span>No. Expediente:</span>
                <div class="field-line">{{ $expediente->no_control }}</div>
            </div>
            <div class="field">
                <span>Fecha:</span>
                <div class="field-line">{{ optional($fechaEmision)->format('d/m/Y') }}</div>
            </div>
        </section>

        <table>
            <thead>
                <tr>
                    <th>Tipo</th>
                </tr>
            </thead>
            <tbody>
                @for ($i = 0; $i < $totalFilas; $i++)
                    @php
                        $consentimiento = $consentimientosListado[$i] ?? null;
                    @endphp
                    <tr class="{{ $i % 2 === 1 ? 'row-alt' : '' }}">
                        <td>{{ $consentimiento?->tratamiento ?? '' }}</td>
                    </tr>
                @endfor
            </tbody>
        </table>

        <section class="declaration">
            <p><strong>Declaro que:</strong></p>
            <p>Se me ha explicado, de manera clara y completa, la alteración o enfermedad bucal que padezco, así como los tratamientos que pudieran realizarse para su atención, seleccionando por sus posibles ventajas los indicados en el plan de tratamiento.</p>
            <p>También se me ha informado acerca de las posibles complicaciones que pudieran surgir a lo largo del tratamiento así como las molestias o riesgos posibles y los beneficios que se pueden esperar.</p>
            <p>Se me enteró que estos tratamientos serán realizados por estudiantes en formación, bajo la supervisión de sus profesores así como el costo que representa este tratamiento.</p>
            <p>Por otro lado, se me ha prevenido de las consecuencias de no seguir el tratamiento aconsejado y se me ha informado que tengo la libertad de retirar mi consentimiento en cualquier momento que lo juzgue conveniente.</p>
            <p>Por mi parte, manifiesto que proporcionaré con toda veracidad la información necesaria para mi tratamiento.</p>
            <p>Estando conforme con la información que se me ha dado, doy mi consentimiento para que se realicen los tratamientos indicados, firmando para ello de manera libre y voluntaria.</p>
        </section>

        <section class="signatures">
            <div class="signature-line">
                Nombre, grupo y firma del alumno responsable
                <div class="signature-name">{{ $expediente->alumno?->name ?? '' }}</div>
            </div>
            <div class="signature-line">
                Nombre y firma del profesor responsable
                <div class="signature-name">{{ optional($expediente->tutor)->name ?? '' }}</div>
            </div>
            <div class="signature-line">
                Nombre y firma del paciente o su representante
                <div class="signature-name">{{ $expediente->paciente ?? '' }}</div>
            </div>
            <div class="signature-line">
                Nombre y firma de un testigo por el paciente
                <div class="signature-name">{{ $expediente->contacto_emergencia_nombre ?? '' }}</div>
            </div>
        </section>
    </div>
</body>
</html>

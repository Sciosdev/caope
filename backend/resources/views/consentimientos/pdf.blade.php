<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Consentimientos del expediente {{ $expediente->no_control }}</title>
    <style>
        :root {
            --text: #1f2937;
            --border: #111827;
            --light: #e5e7eb;
            --stripe: #dbeafe;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            color: var(--text);
            font-family: "Helvetica Neue", Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            background: #ffffff;
        }

        .page {
            width: 816px;
            min-height: 1056px;
            margin: 0 auto;
            padding: 32px 36px 40px;
        }

        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
            margin-bottom: 16px;
        }

        .header img {
            width: 72px;
            height: auto;
        }

        .header .institution {
            flex: 1;
            text-align: center;
            font-size: 12px;
            line-height: 1.5;
        }

        .header .header-left {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 200px;
        }

        .header .header-left-text {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            font-size: 12px;
        }

        .header .header-left-text .sdri {
            font-weight: 700;
            letter-spacing: 2px;
            text-decoration: underline;
        }

        .header .institution strong {
            display: block;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .title {
            margin: 12px 0 20px;
            padding: 6px 12px;
            border: 2px solid var(--border);
            text-align: center;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.6px;
        }

        .meta {
            display: grid;
            grid-template-columns: 1fr 160px 140px;
            gap: 12px;
            margin-bottom: 12px;
        }

        .field {
            border-bottom: 1px solid var(--border);
            padding-bottom: 2px;
        }

        .field span {
            display: inline-block;
            min-width: 72px;
            font-weight: 600;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }

        th,
        td {
            border: 1px solid var(--border);
            padding: 6px 8px;
            vertical-align: top;
        }

        th {
            background: var(--light);
            text-transform: uppercase;
            font-size: 11px;
        }

        tbody tr:nth-child(even) td {
            background: var(--stripe);
        }

        .section-title {
            font-weight: 700;
            margin: 16px 0 6px;
        }

        .paragraph {
            margin: 0 0 8px;
            text-align: justify;
        }

        .signatures {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 24px 48px;
            margin-top: 28px;
        }

        .signature {
            text-align: center;
        }

        .signature .line {
            border-bottom: 1px solid var(--border);
            margin: 32px 0 6px;
        }

        .signature small {
            display: block;
            font-size: 11px;
        }

        .actions {
            text-align: right;
            margin-bottom: 16px;
        }

        .btn-print {
            display: inline-block;
            padding: 6px 12px;
            border: 1px solid var(--border);
            border-radius: 4px;
            background: #f3f4f6;
            color: var(--text);
            font-size: 12px;
            text-decoration: none;
        }

        @media print {
            .actions {
                display: none;
            }

            .page {
                width: auto;
                margin: 0;
                padding: 0;
            }
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="actions">
            <button class="btn-print" type="button" onclick="window.print()">Imprimir</button>
        </div>

        <header class="header">
            <div class="header-left">
                <img src="{{ $logoPath ?: asset('assets/images/logo-mini-dark.png') }}" alt="Escudo institucional">
                <div class="header-left-text">
                    <span class="sdri">SDRI</span>
                    <span>Iztacala</span>
                </div>
            </div>
            <div class="institution">
                <strong>Universidad Nacional Autónoma de México</strong>
                <div>Facultad de Estudios Superiores Iztacala</div>
                <div>Jefatura de la Carrera de Cirujano Dentista</div>
            </div>
        </header>

        <div class="title">Consentimiento informado y plan de tratamiento</div>

        <div class="meta">
            <div class="field"><span>Paciente:</span> {{ $expediente->paciente ?? '—' }}</div>
            <div class="field"><span>No. Expediente:</span> {{ $expediente->no_control }}</div>
            <div class="field"><span>Fecha:</span> {{ $fechaEmision->format('d/m/Y') }}</div>
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width: 60%">Tipo</th>
                    <th style="width: 20%">Aceptado</th>
                    <th style="width: 20%">Fecha</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($consentimientos as $consentimiento)
                    <tr>
                        <td>{{ $consentimiento->tratamiento }}</td>
                        <td>{{ $consentimiento->aceptado ? 'Sí' : 'No' }}</td>
                        <td>{{ optional($consentimiento->fecha)->format('d/m/Y') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3">Sin consentimientos registrados.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <p class="section-title">Declaro que:</p>
        @if ($textoIntroduccion)
            <p class="paragraph">{{ $textoIntroduccion }}</p>
        @else
            <p class="paragraph">
                Se me ha explicado, de manera clara y completa, la alteración o enfermedad bucal que padezco, así como los
                tratamientos que pudieran realizarse para su atención, seleccionando por sus posibles ventajas los indicados
                en el plan de tratamiento.
            </p>
            <p class="paragraph">
                También se me ha informado acerca de las posibles complicaciones que pudieran surgir a lo largo del tratamiento
                así como las molestias o riesgos posibles y los beneficios que se pueden esperar.
            </p>
            <p class="paragraph">
                Se me enteró que estos tratamientos serán realizados por estudiantes en formación, bajo la supervisión de sus
                profesores así como el costo que representa este tratamiento.
            </p>
            <p class="paragraph">
                Por otro lado, se me ha prevenido de las consecuencias de no seguir el tratamiento aconsejado y se me ha informado
                que tengo la libertad de retirar mi consentimiento en cualquier momento que lo juzgue conveniente.
            </p>
            <p class="paragraph">
                Por mi parte, manifiesto que proporcionaré con toda veracidad la información necesaria para mi tratamiento.
            </p>
            <p class="paragraph">
                Estando conforme con la información que se me ha dado, doy mi consentimiento para que se realicen los tratamientos
                indicados, firmando para ello de manera libre y voluntaria.
            </p>
        @endif

        @if ($textoCierre)
            <p class="paragraph">{{ $textoCierre }}</p>
        @endif

        <div class="signatures">
            <div class="signature">
                <div class="line"></div>
                <small>Nombre, grupo y firma del alumno responsable</small>
                <small>{{ $expediente->alumno?->name ?? '' }}</small>
            </div>
            <div class="signature">
                <div class="line"></div>
                <small>Nombre y firma del profesor responsable</small>
                <small>{{ $expediente->tutor?->name ?? '' }}</small>
            </div>
            <div class="signature">
                <div class="line"></div>
                <small>Nombre y firma del paciente o su representante</small>
                <small>{{ $expediente->paciente ?? '' }}</small>
            </div>
            <div class="signature">
                <div class="line"></div>
                <small>Nombre y firma de un testigo por el paciente</small>
                <small>{{ $expediente->contacto_emergencia_nombre ?? '' }}</small>
            </div>
        </div>
    </div>
</body>
</html>

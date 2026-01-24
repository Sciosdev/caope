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
            margin-bottom: 16px;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
        }

        .header-table td {
            border: none;
            padding: 0;
            vertical-align: middle;
        }

        .header img {
            width: 86px;
            height: auto;
        }

        .header .institution {
            text-align: center;
            font-size: 12px;
            line-height: 1.5;
        }

        .header .header-left {
            width: 180px;
            text-align: center;
        }

        .header .header-left-text {
            font-size: 13px;
            line-height: 1.1;
            text-align: center;
        }

        .header .header-left-text .sdri {
            font-weight: 600;
            font-size: 28px;
            letter-spacing: 1px;
            padding-bottom: 4px;
            border-bottom: 1px solid var(--border);
            margin-bottom: 2px;
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
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }

        .meta td {
            border: none;
            padding: 0 12px 0 0;
            vertical-align: bottom;
            white-space: nowrap;
        }

        .meta .label {
            font-weight: 600;
        }

        .meta .line {
            display: inline-block;
            min-width: 120px;
            border-bottom: 1px solid var(--border);
            padding-bottom: 2px;
            line-height: 1.2;
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
            text-align: center;
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
            width: 100%;
            border-collapse: collapse;
            margin-top: 32px;
        }

        .signatures td {
            border: none;
            padding: 0 24px 24px 0;
            vertical-align: top;
            width: 50%;
        }

        .signature {
            text-align: center;
        }

        .signature .info {
            font-size: 11px;
            min-height: 16px;
            margin-bottom: 2px;
        }

        .signature .line {
            border-bottom: 2px solid var(--border);
            margin: 4px 0 8px;
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

        .force-print .actions {
            display: none;
        }

        .force-print .page {
            width: auto;
            margin: 0;
            min-height: auto;
            padding: 0;
        }

        @media print {
            @page {
                size: letter;
                margin: 8mm;
            }

            body {
                font-size: 11px;
            }

            .actions {
                display: none;
            }

            .page {
                width: auto;
                margin: 0;
                min-height: auto;
                padding: 0;
            }

            .signatures {
                margin-top: 16px;
            }

            .signatures td {
                padding-right: 16px;
                padding-bottom: 16px;
            }
        }
    </style>
</head>
<body class="{{ ($forcePrintStyles ?? false) ? 'force-print' : '' }}">
    @php
        $showActions = $showActions ?? false;
    @endphp
    <div class="page">
        @if ($showActions)
            <div class="actions">
                <button class="btn-print" type="button" onclick="window.print()">Imprimir</button>
            </div>
        @endif

        <header class="header">
            <table class="header-table">
                <tr>
                    <td class="header-left">
                        <img src="{{ $logoPath ?: asset('assets/images/logo-mini-dark.png') }}" alt="Escudo institucional">
                        <div class="header-left-text">
                            <div class="sdri">SDRI</div>
                            <div>Iztacala</div>
                        </div>
                    </td>
                    <td class="institution">
                        <strong>Universidad Nacional Autónoma de México</strong>
                        <div>Facultad de Estudios Superiores Iztacala</div>
                        <div>Jefatura de la Carrera de Cirujano Dentista</div>
                    </td>
                    <td style="width: 180px;"></td>
                </tr>
            </table>
        </header>

        <div class="title">Consentimiento informado y plan de tratamiento</div>

        <table class="meta">
            <tr>
                <td>
                    <span class="label">Paciente:</span>
                    <span class="line">{{ $expediente->paciente ?? '—' }}</span>
                </td>
                <td>
                    <span class="label">No. Expediente:</span>
                    <span class="line">{{ $expediente->no_control }}</span>
                </td>
                <td>
                    <span class="label">Fecha:</span>
                    <span class="line">{{ $fechaEmision->format('d/m/Y') }}</span>
                </td>
            </tr>
        </table>

        <table>
            <thead>
                <tr>
                    <th style="width: 100%">Tipo</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $totalRows = 12;
                    $filledRows = $consentimientos->count();
                @endphp
                @forelse ($consentimientos as $consentimiento)
                    <tr>
                        <td>{{ $consentimiento->tratamiento }}</td>
                    </tr>
                @empty
                    <tr>
                        <td>Sin consentimientos registrados.</td>
                    </tr>
                @endforelse
                @php
                    $rowsToFill = max($totalRows - max($filledRows, 1), 0);
                @endphp
                @for ($i = 0; $i < $rowsToFill; $i++)
                    <tr>
                        <td>&nbsp;</td>
                    </tr>
                @endfor
            </tbody>
        </table>

        <p class="section-title">Declaro que:</p>
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

        <table class="signatures">
            <tr>
                <td>
                    <div class="signature">
                        <div class="info">{{ $expediente->alumno?->name ?? '—' }}</div>
                        <div class="line"></div>
                        <small>Nombre, grupo y firma del alumno responsable</small>
                    </div>
                </td>
                <td>
                    <div class="signature">
                        <div class="info">{{ $expediente->tutor?->name ?? '—' }}</div>
                        <div class="line"></div>
                        <small>Nombre y firma del profesor responsable</small>
                    </div>
                </td>
            </tr>
            <tr>
                <td>
                    <div class="signature">
                        <div class="info">{{ $expediente->paciente ?? '—' }}</div>
                        <div class="line"></div>
                        <small>Nombre y firma del paciente o su representante</small>
                    </div>
                </td>
                <td>
                    <div class="signature">
                        <div class="info">{{ $expediente->contacto_emergencia_nombre ?? '—' }}</div>
                        <div class="line"></div>
                        <small>Nombre y firma de un testigo por el paciente</small>
                    </div>
                </td>
            </tr>
        </table>
    </div>
    @if (request()->boolean('auto_print'))
        <script>
            window.addEventListener('load', () => {
                window.print();
            });
        </script>
    @endif
</body>
</html>

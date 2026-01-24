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
            display: block;
            width: 96px;
            height: auto;
            margin: 0 auto;
        }

        .header .institution {
            text-align: center;
            font-size: 13px;
            line-height: 1.6;
            text-transform: uppercase;
            letter-spacing: 0.6px;
        }

        .header .header-left {
            width: 140px;
            text-align: center;
            padding-right: 8px;
        }

        .header .institution strong {
            display: block;
            font-size: 14px;
            font-weight: 500;
        }

        .title {
            margin: 12px 0 20px;
            padding: 6px 12px;
            border: 2px solid var(--border);
            text-align: center;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 13px;
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
        $fallbackLogoDataUri = 'data:image/png;base64,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';
        $logoSrc = $logoDataUri ?: $fallbackLogoDataUri;
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
                        @if (! empty($logoSrc))
                            <img src="{{ $logoSrc }}" alt="Escudo institucional">
                        @endif
                    </td>
                    <td class="institution">
                        <strong>Universidad Nacional Autónoma de México</strong>
                        <div>Facultad de Estudios Superiores Iztacala</div>
                        <div>Jefatura de la Carrera de Cirujano Dentista</div>
                    </td>
                    <td style="width: 140px;"></td>
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

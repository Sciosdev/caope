<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Consentimientos del expediente {{ $expediente->no_control }}</title>
    <style>
        @page {
            size: letter;
            margin: 24px 32px 32px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            color: #1f2937;
            font-family: "DejaVu Sans", Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
        }

        .title {
            margin: 12px 0 16px;
            padding: 6px 10px;
            border: 2px solid #111827;
            text-align: center;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .meta-table,
        .consentimientos-table,
        .signatures-table,
        .header-table {
            width: 100%;
            border-collapse: collapse;
        }

        .header-table td {
            vertical-align: middle;
        }

        .header-table img {
            width: 80px;
            height: auto;
        }

        .header-title {
            text-align: center;
            font-size: 12px;
        }

        .header-title strong {
            display: block;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        .meta-table td {
            padding: 2px 6px 6px;
            border-bottom: 1px solid #111827;
        }

        .meta-label {
            font-weight: 600;
            padding-right: 6px;
            white-space: nowrap;
        }

        .consentimientos-table th,
        .consentimientos-table td {
            border: 1px solid #111827;
            padding: 6px 8px;
            vertical-align: top;
        }

        .consentimientos-table th {
            background: #e5e7eb;
            text-transform: uppercase;
            font-size: 11px;
        }

        .consentimientos-table tbody tr:nth-child(even) td {
            background: #dbeafe;
        }

        .section-title {
            font-weight: 700;
            margin: 16px 0 6px;
        }

        .paragraph {
            margin: 0 0 8px;
            text-align: justify;
        }

        .signatures-table {
            margin-top: 28px;
        }

        .signature-cell {
            width: 50%;
            text-align: center;
            padding: 12px 16px;
        }

        .signature-line {
            border-bottom: 1px solid #111827;
            margin: 28px 0 6px;
        }

        .signature-name {
            font-size: 11px;
        }
    </style>
</head>
<body>
    <table class="header-table">
        <tr>
            <td style="width: 90px;">
                <img src="{{ $logoPath }}" alt="Logo institucional">
            </td>
            <td class="header-title">
                <strong>Universidad Nacional Autónoma de México</strong>
                Facultad de Estudios Superiores Iztacala
                <br>
                Jefatura de la Carrera de Cirujano Dentista
            </td>
        </tr>
    </table>

    <div class="title">Consentimiento informado y plan de tratamiento</div>

    <table class="meta-table">
        <tr>
            <td style="width: 50%"><span class="meta-label">Paciente:</span> {{ $expediente->paciente ?? '—' }}</td>
            <td style="width: 25%"><span class="meta-label">No. Expediente:</span> {{ $expediente->no_control }}</td>
            <td style="width: 25%"><span class="meta-label">Fecha:</span> {{ $fechaEmision->format('d/m/Y') }}</td>
        </tr>
    </table>

    <table class="consentimientos-table" style="margin-top: 12px;">
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

    <table class="signatures-table">
        <tr>
            <td class="signature-cell">
                <div class="signature-line"></div>
                <div class="signature-name">Nombre, grupo y firma del alumno responsable</div>
                <div class="signature-name">{{ $expediente->alumno?->name ?? '' }}</div>
            </td>
            <td class="signature-cell">
                <div class="signature-line"></div>
                <div class="signature-name">Nombre y firma del profesor responsable</div>
                <div class="signature-name">{{ $expediente->tutor?->name ?? '' }}</div>
            </td>
        </tr>
        <tr>
            <td class="signature-cell">
                <div class="signature-line"></div>
                <div class="signature-name">Nombre y firma del paciente o su representante</div>
                <div class="signature-name">{{ $expediente->paciente ?? '' }}</div>
            </td>
            <td class="signature-cell">
                <div class="signature-line"></div>
                <div class="signature-name">Nombre y firma de un testigo por el paciente</div>
                <div class="signature-name">{{ $expediente->contacto_emergencia_nombre ?? '' }}</div>
            </td>
        </tr>
    </table>
</body>
</html>

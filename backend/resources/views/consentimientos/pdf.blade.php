<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Consentimientos del expediente {{ $expediente->no_control }}</title>
    <style>
        @page { size: letter; margin: 28px 32px; }

        * { box-sizing: border-box; }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 11px;
            color: #0f172a;
            margin: 0;
            background: #f8fafc;
        }

        .sheet {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 20px 22px 24px;
        }

        h1 {
            margin: 0 0 4px;
            font-size: 18px;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        p { margin: 0; }
        .muted { color: #64748b; }
        .small { font-size: 10px; }

        .fields {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr;
            gap: 12px;
            margin: 16px 0 12px;
        }

        .field {
            border-bottom: 1px solid #94a3b8;
            padding-bottom: 4px;
        }

        .label { display: block; font-size: 10px; color: #475569; text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 4px; }
        .value { font-size: 12px; font-weight: 600; color: #0f172a; }

        .panel {
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 14px 16px 10px;
            margin-bottom: 14px;
        }

        .panel-title {
            margin: 0 0 8px;
            font-size: 12px;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: #0f172a;
        }

        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #cbd5e1; padding: 6px 8px; text-align: left; vertical-align: top; }
        th { background: #f1f5f9; font-size: 10px; text-transform: uppercase; letter-spacing: 0.06em; }
        td { font-size: 11px; }
        .center { text-align: center; }

        .signatures {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 18px;
            margin-top: 18px;
        }

        .signature-box {
            height: 110px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 10px 12px;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
        }

        .signature-label {
            text-transform: uppercase;
            font-size: 10px;
            letter-spacing: 0.04em;
            color: #475569;
            margin-bottom: 4px;
        }

        .signature-name {
            font-weight: 600;
            font-size: 12px;
            border-top: 1px solid #94a3b8;
            padding-top: 6px;
        }

        .footer { margin-top: 12px; color: #475569; font-size: 10px; text-align: right; }
    </style>
</head>
<body>
    <div class="sheet">
        <header>
            <h1>Formato de consentimiento informado</h1>
            <p class="muted small">Generado el {{ $fechaEmision->format('d/m/Y \a \l\a\s H:i') }}</p>
        </header>

        <section class="fields">
            <div class="field">
                <span class="label">Nombre del paciente</span>
                <span class="value">{{ $expediente->paciente }}</span>
            </div>
            <div class="field">
                <span class="label">Expediente</span>
                <span class="value">{{ $expediente->no_control }}</span>
            </div>
            <div class="field">
                <span class="label">Fecha</span>
                <span class="value">{{ optional($fechaEmision)->format('d/m/Y') }}</span>
            </div>
        </section>

        <section class="panel">
            <h2 class="panel-title">Tratamientos autorizados</h2>
            @if ($consentimientos->isEmpty())
                <p class="small muted">Sin registros de tratamientos para este expediente.</p>
            @else
                <table>
                    <thead>
                        <tr>
                            <th style="width: 8%;" class="center">#</th>
                            <th style="width: 44%;">Tratamiento</th>
                            <th style="width: 16%;" class="center">Requerido</th>
                            <th style="width: 16%;" class="center">Aceptado</th>
                            <th style="width: 16%;" class="center">Fecha</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($consentimientos as $consentimiento)
                            <tr>
                                <td class="center">{{ $loop->iteration }}</td>
                                <td>{{ $consentimiento->tratamiento }}</td>
                                <td class="center">{{ $consentimiento->requerido ? 'Sí' : 'No' }}</td>
                                <td class="center">{{ $consentimiento->aceptado ? 'Sí' : 'No' }}</td>
                                <td class="center">{{ optional($consentimiento->fecha)->format('d/m/Y') ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>

        <section class="signatures">
            <div class="signature-box">
                <span class="signature-label">Firma del paciente</span>
                <span class="signature-name">{{ $expediente->paciente }}</span>
            </div>
            <div class="signature-box">
                <span class="signature-label">Firma del tutor / responsable</span>
                <span class="signature-name">{{ optional($expediente->tutor)->name ?? 'Nombre y firma' }}</span>
            </div>
        </section>

        <section class="signatures" style="margin-top: 10px;">
            <div class="signature-box">
                <span class="signature-label">Coordinador</span>
                <span class="signature-name">{{ optional($expediente->coordinador)->name ?? 'Nombre y firma' }}</span>
            </div>
            <div class="signature-box">
                <span class="signature-label">Observaciones</span>
                <span class="signature-name" style="border-top: none; padding-top: 0; font-weight: 400; font-size: 11px;">&nbsp;</span>
            </div>
        </section>

        <p class="footer">Expediente {{ $expediente->no_control }}</p>
    </div>
</body>
</html>
